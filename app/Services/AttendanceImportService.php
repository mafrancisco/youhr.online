<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceImportService
{
    public function import(string $filePath, string $startDate, string $endDate, int $empStatus): int
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->startOfDay();

        // Step 0: Collect badge IDs from the file
        $badgeIDs = $this->collectBadgeIDs($filePath);

        // Step 1: Parse file and insert into attendance table
        $count = $this->parseAndInsert($filePath, $start, $end, $empStatus);

        // Step 2.1: Delete old attendance_clean and request records for these badges in range
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

        // Step 2.2: Seed attendance_clean for each employee with attendance in range
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
                $dayOfWeek = $current->dayOfWeekIso; // 6=Sat, 7=Sun

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

        // Step 2.3: Update attendance_clean with summarized MIN/MAX times by attType
        DB::statement("
            UPDATE attendance_clean c
            JOIN (
                SELECT
                    a.BadgeNumber,
                    a.attDate,
                    MIN(CASE WHEN a.attType = 0 THEN a.attTime END) AS st1,
                    MIN(CASE WHEN a.attType = 1 THEN a.attTime END) AS st2,
                    MIN(CASE WHEN a.attType = 2 THEN a.attTime END) AS st3,
                    MAX(CASE WHEN a.attType = 3 THEN a.attTime END) AS st4,
                    MIN(CASE WHEN a.attType = 4 THEN a.attTime END) AS oi,
                    MAX(CASE WHEN a.attType = 5 THEN a.attTime END) AS oo
                FROM attendance a
                WHERE STR_TO_DATE(a.attDate, '%m/%d/%Y')
                      BETWEEN STR_TO_DATE(?, '%Y-%m-%d') AND STR_TO_DATE(?, '%Y-%m-%d')
                GROUP BY a.BadgeNumber, a.attDate
            ) AS t ON c.BadgeNumber = t.BadgeNumber AND c.AttDate = t.attDate
            SET
                c.startTime1 = IF(t.st1 IS NOT NULL, t.st1, c.startTime1),
                c.startTime2 = IF(t.st2 IS NOT NULL, t.st2, c.startTime2),
                c.startTime3 = IF(t.st3 IS NOT NULL, t.st3, c.startTime3),
                c.startTime4 = IF(t.st4 IS NOT NULL, t.st4, c.startTime4),
                c.OTIn       = t.oi,
                c.OTOut      = t.oo
        ", [$startDate, $endDate]);

        // Truncate attendance (staging table, cleared after processing)
        DB::statement("TRUNCATE TABLE attendance");

        return $count;
    }

    // -----------------------------------------------------------------------
    // Collect all badge IDs referenced in the file
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
    // Parse file and insert valid records into the attendance staging table
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
            if (count($values) < 4) continue;

            $id      = (int) $values[0];
            $date    = $values[1] ?? '';
            $attType = (int) ($values[3] ?? 0);

            // Biometric DAT format: "YYYY-MM-DD HH:MM:SS"
            $entry = explode(' ', $date);
            if (count($entry) < 2) continue;

            $datePart = $entry[0];
            $timePart = $entry[1];

            $res = explode('-', $datePart);
            $min = explode(':', $timePart);
            if (count($res) !== 3 || count($min) < 2) continue;

            $attdate  = $res[1] . '/' . $res[2] . '/' . $res[0]; // MM/DD/YYYY
            $attTime  = $min[0] . ':' . $min[1];                  // HH:MM
            $fileDate = Carbon::parse($datePart)->startOfDay();

            if ($fileDate->between($start, $end)) {
                $badgeID = str_replace(' ', '', $id);

                $emp = Employee::where('badgeID', $badgeID)
                    ->where('empStatus', $empStatus)
                    ->first();

                if ($emp) {
                    DB::insert(
                        "INSERT INTO attendance (BadgeNumber, attDate, attTime, attType) VALUES (?, ?, ?, ?)",
                        [$id, $attdate, $attTime, $attType]
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
