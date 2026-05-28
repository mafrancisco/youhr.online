<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TimeDetectionSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceImportService
{
    /**
     * Import attendance from one or more DAT/CSV files.
     *
     * Step 0: Collect all BadgeNumbers from ALL uploaded files
     * Step 1: Parse ALL files and insert into attendance staging table
     * Step 2.1: Delete old attendance_clean and request records for those badges in range
     * Step 2.2: Seed attendance_clean with all dates in range per employee
     * Step 2.3: Classify punches using Time Detection Rules (schedule-based)
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

        foreach ($employees as $emp) {
            $badge   = $emp->BadgeNumber;
            $current = $start->copy();

            while ($current <= $end) {
                $attDate   = $current->format('m/d/Y');
                $dayOfWeek = $current->dayOfWeekIso;

                $hasAtt = DB::selectOne(
                    "SELECT COUNT(*) AS cnt FROM attendance WHERE BadgeNumber = ? AND attDate = ?",
                    [$badge, $attDate]
                )->cnt > 0;

                if (!$hasAtt) {
                    $label = '';
                    if ($dayOfWeek == 6) $label = 'Saturday';
                    if ($dayOfWeek == 7) $label = 'Sunday';

                    DB::insert("
                        INSERT INTO attendance_clean (BadgeNumber, AttDate, startTime1, startTime2, startTime3, startTime4)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ", [$badge, $attDate, $label, $label, $label, $label]);
                } else {
                    DB::insert("
                        INSERT INTO attendance_clean (BadgeNumber, AttDate, startTime1, startTime2, startTime3, startTime4)
                        VALUES (?, ?, '', '', '', '')
                    ", [$badge, $attDate]);
                }

                $current->addDay();
            }
        }

        // ===================================================================
        // Step 2.3: Classify punches using Time Detection Rules
        //           Instead of relying on device attType, use the employee's
        //           schedule + configurable detection windows to determine
        //           which punch is TimeIn, BreakOut, BreakIn, TimeOut, OTIn, OTOut
        // ===================================================================
        $this->classifyPunchesBySchedule($startDate, $endDate);

        // ===================================================================
        // Step 2.4: Truncate attendance staging table
        // ===================================================================
        DB::statement("TRUNCATE TABLE attendance");

        return $count;
    }

    /**
     * Classify raw punches into TimeIn/BreakOut/BreakIn/TimeOut/OTIn/OTOut
     * using the employee's schedule and the Time Detection Settings.
     */
    private function classifyPunchesBySchedule(string $startDate, string $endDate): void
    {
        // Load detection rules
        $rules = [];
        $settings = DB::select("SELECT * FROM time_detection_settings");
        foreach ($settings as $s) {
            $rules[$s->punch_type] = [
                'before_minutes' => (int) $s->before_minutes,
                'after_minutes'  => (int) $s->after_minutes,
                'pick_rule'      => $s->pick_rule,
            ];
        }

        // If no rules configured, fall back to defaults
        if (empty($rules)) {
            $rules = [
                'timein'   => ['before_minutes' => 180, 'after_minutes' => 120, 'pick_rule' => 'earliest'],
                'breakout' => ['before_minutes' => 120, 'after_minutes' => 30,  'pick_rule' => 'latest'],
                'breakin'  => ['before_minutes' => 30,  'after_minutes' => 120, 'pick_rule' => 'earliest'],
                'timeout'  => ['before_minutes' => 120, 'after_minutes' => 180, 'pick_rule' => 'latest'],
                'otin'     => ['before_minutes' => 30,  'after_minutes' => 60,  'pick_rule' => 'earliest'],
                'otout'    => ['before_minutes' => 60,  'after_minutes' => 180, 'pick_rule' => 'latest'],
            ];
        }

        // Load all schedules
        $schedules = [];
        $schedRows = DB::select("SELECT * FROM schedule");
        foreach ($schedRows as $s) {
            $schedules[$s->id] = $s;
        }

        $dayMap = [
            'mon' => 'm', 'tue' => 't', 'wed' => 'w', 'thu' => 'th',
            'fri' => 'f', 'sat' => 'sat', 'sun' => 'sun',
        ];

        // Get all attendance_clean records that have punches (blank StartTime1)
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
            $schedId = $rec->schedule;

            // Convert AttDate MM/DD/YYYY to Y-m-d
            [$m, $d, $y] = explode('/', $attDate);
            $dateYmd = "$y-$m-$d";
            $dayName = strtolower(date('D', strtotime($dateYmd)));
            $dayPrefix = $dayMap[$dayName] ?? '';

            // Get schedule for this day
            $sched = $schedules[$schedId] ?? null;
            if (!$sched || !$dayPrefix) continue;

            $schedTimein  = $sched->{$dayPrefix . '_timein'} ?? '';
            $schedBreakout = $sched->{$dayPrefix . '_breakout'} ?? '';
            $schedBreakin  = $sched->{$dayPrefix . '_breakin'} ?? '';
            $schedTimeout  = $sched->{$dayPrefix . '_timeout'} ?? '';

            if (empty($schedTimein)) continue; // No schedule for this day

            // Get all raw punches for this employee on this date
            $rawPunches = DB::select(
                "SELECT attTime FROM attendance WHERE BadgeNumber = ? AND attDate = ? ORDER BY attTime ASC",
                [$badge, $attDate]
            );

            if (empty($rawPunches)) continue;

            // Build punch list with Carbon timestamps
            $punches = [];
            foreach ($rawPunches as $p) {
                $time = trim($p->attTime);
                if (empty($time)) continue;
                try {
                    $punches[] = [
                        'time'      => $time,
                        'timestamp' => Carbon::parse("$dateYmd $time"),
                    ];
                } catch (\Throwable) {
                    continue;
                }
            }

            if (empty($punches)) continue;

            // Detect each punch type using the rules
            $startTime1 = $this->detectPunch($punches, $schedTimein, $dateYmd, $rules['timein'] ?? null);
            $startTime2 = $this->detectPunch($punches, $schedBreakout, $dateYmd, $rules['breakout'] ?? null);
            $startTime3 = $this->detectPunch($punches, $schedBreakin, $dateYmd, $rules['breakin'] ?? null);
            $startTime4 = $this->detectPunch($punches, $schedTimeout, $dateYmd, $rules['timeout'] ?? null);
            $otIn       = $this->detectPunch($punches, $schedTimeout, $dateYmd, $rules['otin'] ?? null);
            $otOut      = $this->detectPunch($punches, $schedTimeout, $dateYmd, $rules['otout'] ?? null);

            // Avoid assigning the same punch to multiple slots
            $used = [];
            if ($startTime1) $used[] = $startTime1;

            // BreakOut: must be after TimeIn and different from it
            if ($startTime2 && (in_array($startTime2, $used) || ($startTime1 && $startTime2 <= $startTime1))) {
                $startTime2 = '';
            }
            if ($startTime2) $used[] = $startTime2;

            // BreakIn: must be after BreakOut
            if ($startTime3 && (in_array($startTime3, $used) || ($startTime2 && $startTime3 <= $startTime2))) {
                $startTime3 = '';
            }
            if ($startTime3) $used[] = $startTime3;

            // TimeOut: must be after BreakIn (or TimeIn if no break)
            $lastBefore = $startTime3 ?: $startTime2 ?: $startTime1;
            if ($startTime4 && (in_array($startTime4, $used) || ($lastBefore && $startTime4 <= $lastBefore))) {
                $startTime4 = '';
            }
            if ($startTime4) $used[] = $startTime4;

            // OT: must be after TimeOut
            if ($otIn && ($otIn === $startTime4 || ($startTime4 && $otIn <= $startTime4))) {
                $otIn = '';
            }
            if ($otOut && $otIn && $otOut <= $otIn) {
                $otOut = '';
            }

            // Update attendance_clean
            DB::update("
                UPDATE attendance_clean
                SET startTime1 = ?, startTime2 = ?, startTime3 = ?, startTime4 = ?,
                    OTIn = ?, OTOut = ?
                WHERE id = ?
            ", [
                $startTime1 ?: '',
                $startTime2 ?: '',
                $startTime3 ?: '',
                $startTime4 ?: '',
                $otIn ?: '',
                $otOut ?: '',
                $rec->id,
            ]);
        }
    }

    /**
     * Find the appropriate punch within the detection window.
     */
    private function detectPunch(array $punches, string $scheduledTime, string $dateYmd, ?array $rule): string
    {
        if (empty($scheduledTime) || !$rule) {
            return '';
        }

        $scheduledTs = Carbon::parse("$dateYmd $scheduledTime");
        $windowStart = $scheduledTs->copy()->subMinutes($rule['before_minutes']);
        $windowEnd   = $scheduledTs->copy()->addMinutes($rule['after_minutes']);

        // Find all punches within the window
        $candidates = [];
        foreach ($punches as $punch) {
            if ($punch['timestamp']->between($windowStart, $windowEnd)) {
                $candidates[] = $punch;
            }
        }

        if (empty($candidates)) {
            return '';
        }

        // Apply pick rule
        if ($rule['pick_rule'] === 'earliest') {
            usort($candidates, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);
        } else {
            usort($candidates, fn($a, $b) => $a['timestamp']->gt($b['timestamp']) ? -1 : 1);
        }

        return $candidates[0]['time'];
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
                    // Store with attType=0 (type is irrelevant, classification done by schedule)
                    DB::insert(
                        "INSERT INTO attendance (BadgeNumber, attDate, attTime, attType) VALUES (?, ?, ?, ?)",
                        [$id, $attdate, $attTime, 0]
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
