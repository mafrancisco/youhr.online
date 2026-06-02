<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceImportService
{
    public function __construct(private PunchClassifierService $classifier) {}

    /**
     * Import attendance from one or more DAT/CSV files.
     *
     * Step 0: Collect all BadgeNumbers from ALL uploaded files
     * Step 1: Parse ALL files and insert into attendance staging table
     * Step 2.1: Delete old attendance_clean and request records for those badges in range
     * Step 2.2: Seed attendance_clean with all dates in range per employee
     * Step 2.3: Classify punches using Time Detection Rules (via PunchClassifierService)
     * Step 2.4: Truncate attendance staging table
     */
    public function import(array $filePaths, string $startDate, string $endDate, int $empStatus): int
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->startOfDay();

        // ===================================================================
        // Step 0: Collect BadgeNumbers from ALL uploaded files
        // ===================================================================
        $badgeIDs = [];
        foreach ($filePaths as $filePath) {
            $badgeIDs = array_merge($badgeIDs, $this->collectBadgeIDs($filePath));
        }
        $badgeIDs = array_unique($badgeIDs);

        // ===================================================================
        // Step 1: Parse ALL files and insert into attendance staging table
        // ===================================================================
        $count = 0;
        foreach ($filePaths as $filePath) {
            $count += $this->parseAndInsert($filePath, $start, $end, $empStatus);
        }

        // ===================================================================
        // Step 2.1: Delete old attendance_clean and request records
        // ===================================================================
        if (!empty($badgeIDs)) {
            $idList = implode(',', array_map('intval', $badgeIDs));

            DB::statement("
                DELETE FROM attendance_clean
                WHERE BadgeNumber IN ($idList)
                  AND STR_TO_DATE(AttDate, '%m/%d/%Y')
                      BETWEEN STR_TO_DATE(?, '%Y-%m-%d') AND STR_TO_DATE(?, '%Y-%m-%d')
            ", [$startDate, $endDate]);

            DB::statement("
                DELETE FROM request
                WHERE BadgeNumber IN ($idList)
                  AND STR_TO_DATE(AttDate, '%m/%d/%Y')
                      BETWEEN STR_TO_DATE(?, '%Y-%m-%d') AND STR_TO_DATE(?, '%Y-%m-%d')
            ", [$startDate, $endDate]);
        }

        // ===================================================================
        // Step 2.2: Seed attendance_clean with all dates in range per employee
        // ===================================================================
        $employees = DB::select("
            SELECT DISTINCT BadgeNumber
            FROM attendance
            WHERE STR_TO_DATE(attDate, '%m/%d/%Y')
                  BETWEEN STR_TO_DATE(?, '%Y-%m-%d') AND STR_TO_DATE(?, '%Y-%m-%d')
        ", [$startDate, $endDate]);

        // Collect per-badge punch dates so seedDateRange can label days correctly
        $punchDatesByBadge = [];
        foreach ($employees as $emp) {
            $rows = DB::select(
                "SELECT DISTINCT attDate FROM attendance WHERE BadgeNumber = ?",
                [$emp->BadgeNumber]
            );
            $punchDatesByBadge[$emp->BadgeNumber] = array_column($rows, 'attDate');
        }

        $this->classifier->loadRules();
        $this->classifier->loadSchedules();

        foreach ($employees as $emp) {
            $this->classifier->seedDateRange(
                $emp->BadgeNumber,
                $startDate,
                $endDate,
                $punchDatesByBadge[$emp->BadgeNumber] ?? []
            );
        }

        // ===================================================================
        // Step 2.3: Classify punches using Time Detection Rules
        // ===================================================================
        $this->classifyPunchesBySchedule($startDate, $endDate);

        // ===================================================================
        // Step 2.4: Truncate attendance staging table
        // ===================================================================
        DB::statement("TRUNCATE TABLE attendance");

        return $count;
    }

    /**
     * Classify raw punches from the staging table into attendance_clean slots.
     */
    private function classifyPunchesBySchedule(string $startDate, string $endDate): void
    {
        // Only process rows that were seeded blank (startTime1 = '')
        $cleanRecords = DB::select("
            SELECT c.id, c.BadgeNumber, c.AttDate, e.schedule
            FROM attendance_clean c
            JOIN employees e ON c.BadgeNumber = e.badgeID
            WHERE c.startTime1 = ''
              AND STR_TO_DATE(c.AttDate, '%m/%d/%Y')
                  BETWEEN STR_TO_DATE(?, '%Y-%m-%d') AND STR_TO_DATE(?, '%Y-%m-%d')
        ", [$startDate, $endDate]);

        foreach ($cleanRecords as $rec) {
            $badge   = $rec->BadgeNumber;
            $attDate = $rec->AttDate;
            [$m, $d, $y] = explode('/', $attDate);
            $dateYmd = "$y-$m-$d";

            // Fetch raw punches from staging table (attType: 4=OT In, 5=OT Out)
            $rawPunches = DB::select(
                "SELECT attTime, attType FROM attendance WHERE BadgeNumber = ? AND attDate = ? ORDER BY attTime ASC",
                [$badge, $attDate]
            );

            if (empty($rawPunches)) {
                continue;
            }

            // Separate regular punches from device-reported OT punches
            $punches = [];
            $otIn    = '';
            $otOut   = '';
            foreach ($rawPunches as $p) {
                $time     = trim($p->attTime);
                $punchType = (int) $p->attType;
                if ($time === '') continue;
                try {
                    $ts = Carbon::parse("$dateYmd $time");
                } catch (\Throwable) {
                    continue;
                }
                if ($punchType === 4) {
                    $otIn = $time;          // OT In — device-reported
                } elseif ($punchType === 5) {
                    $otOut = $time;         // OT Out — device-reported
                } else {
                    $punches[] = ['time' => $time, 'timestamp' => $ts];
                }
            }

            if (empty($punches) && $otIn === '' && $otOut === '') {
                continue;
            }

            // classifyAndWrite does DELETE + INSERT, resetting OTIn/OTOut
            if (!empty($punches)) {
                $this->classifier->classifyAndWrite($badge, $attDate, $dateYmd, $rec->schedule, $punches);
            }

            // Write device-reported OT punches (must run AFTER classifyAndWrite)
            if ($otIn !== '' || $otOut !== '') {
                DB::update(
                    "UPDATE attendance_clean SET OTIn = ?, OTOut = ? WHERE BadgeNumber = ? AND AttDate = ?",
                    [$otIn, $otOut, $badge, $attDate]
                );
            }
        }
    }

    // -----------------------------------------------------------------------
    // Collect all badge IDs referenced in a single file
    // -----------------------------------------------------------------------
    private function collectBadgeIDs(string $filePath): array
    {
        $badgeIDs = [];
        $handle   = fopen($filePath, 'r');
        if (!$handle) return $badgeIDs;

        while (!feof($handle)) {
            $line = trim(fgets($handle));
            if ($line === '') continue;

            $values = $this->parseLine($line);
            if (count($values) < 1) continue;

            $id = (int) $values[0];
            if ($id > 0 && !in_array($id, $badgeIDs)) {
                $badgeIDs[] = $id;
            }
        }

        fclose($handle);
        return $badgeIDs;
    }

    // -----------------------------------------------------------------------
    // Parse a single file and insert valid records into attendance staging table
    // Note: attType from device is stored but NOT used for classification.
    //       Classification is done by Time Detection Rules in Step 2.3.
    // -----------------------------------------------------------------------
    private function parseAndInsert(string $filePath, Carbon $start, Carbon $end, int $empStatus): int
    {
        $count  = 0;
        $handle = fopen($filePath, 'r');
        if (!$handle) return $count;

        while (!feof($handle)) {
            $line = trim(fgets($handle));
            if ($line === '') continue;

            $values = $this->parseLine($line);
            if (count($values) < 2) continue;

            $id   = (int) $values[0];
            $date = $values[1] ?? '';

            // Biometric DAT format: "YYYY-MM-DD HH:MM:SS"
            $entry = explode(' ', $date);
            if (count($entry) < 2) continue;

            $datePart = $entry[0];
            $timePart = $entry[1];

            $res = explode('-', $datePart);
            $min = explode(':', $timePart);
            if (count($res) !== 3 || count($min) < 2) continue;

            // Convert to MM/DD/YYYY and HH:MM
            $attdate = $res[1] . '/' . $res[2] . '/' . $res[0];
            $attTime = $min[0] . ':' . $min[1];

            $fileDate = strtotime($datePart);
            $startTs  = $start->timestamp;
            $endTs    = $end->timestamp;

            if ($fileDate >= $startTs && $fileDate <= $endTs) {
                $badgeID = str_replace(' ', '', $id);

                $emp = Employee::where('badgeID', $badgeID)
                    ->where('empStatus', $empStatus)
                    ->first();

                if ($emp) {
                    // Store actual attType from DAT column 3:
                    // 0=regular, 1=regular, 4=OT In, 5=OT Out
                    $attTypeVal = isset($values[3]) ? (int) trim($values[3]) : 0;
                    DB::insert(
                        "INSERT INTO attendance (BadgeNumber, attDate, attTime, attType) VALUES (?, ?, ?, ?)",
                        [$id, $attdate, $attTime, $attTypeVal]
                    );
                    $count++;
                }
            }
        }

        fclose($handle);
        return $count;
    }

    // -----------------------------------------------------------------------
    // Auto-detect tab-delimited or CSV format
    // -----------------------------------------------------------------------
    private function parseLine(string $line): array
    {
        return str_contains($line, "\t") ? explode("\t", $line) : str_getcsv($line);
    }
}
