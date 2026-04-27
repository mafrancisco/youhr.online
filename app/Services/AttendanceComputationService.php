<?php

namespace App\Services;

use App\Models\AttendanceClean;
use App\Models\Leave;
use Illuminate\Support\Facades\DB;

class AttendanceComputationService
{
    private array $dayMap = [
        'mon' => 'm',
        'tue' => 't',
        'wed' => 'w',
        'thu' => 'th',
        'fri' => 'f',
        'sat' => 'sat',
        'sun' => 'sun',
    ];

    public function compute(string $startDate, string $endDate): void
    {
        // First pass: compute tardiness and initial undertime
        $this->firstPass($startDate, $endDate);

        // Second pass: refine undertime and compute OT
        $this->secondPass($startDate, $endDate);
    }

    // -----------------------------------------------------------------------
    // First pass — mirrors convert.php main while loop
    // -----------------------------------------------------------------------
    private function firstPass(string $startDate, string $endDate): void
    {
        $rows = DB::select("
            SELECT c.BadgeNumber, c.AttDate, c.StartTime1, c.StartTime2, c.StartTime3, c.StartTime4,
                   e.schedule, c.OTIn, c.OTOut
            FROM attendance_clean c
            JOIN employees e ON c.BadgeNumber = e.badgeID
            WHERE STR_TO_DATE(c.AttDate, '%m/%d/%Y') BETWEEN ? AND ?
        ", [$startDate, $endDate]);

        foreach ($rows as $row) {
            $badge   = $row->BadgeNumber;
            $attDate = $row->AttDate;
            $time1   = trim($row->StartTime1 ?? '');
            $time2   = trim($row->StartTime2 ?? '');
            $time3   = trim($row->StartTime3 ?? '');
            $time4   = trim($row->StartTime4 ?? '');
            $schedId = $row->schedule;
            $OTin    = trim($row->OTIn ?? '');
            $OTout   = trim($row->OTOut ?? '');

            $hasOT = $this->isValidTime($OTin) && $this->isValidTime($OTout);

            // Convert AttDate MM/DD/YYYY → YYYY-MM-DD
            [$m, $d, $y] = explode('/', $attDate);
            $dateYmd = "$y-$m-$d";
            $dayName = strtolower(date('D', strtotime($dateYmd)));
            $dayPrefix = $this->dayMap[$dayName];

            // --- Cross-midnight punch handling (time2/time3/time4) ---
            if ($this->isValidTime($time2) || $this->isValidTime($time3) || $this->isValidTime($time4)) {
                $prevDate = date('m/d/Y', strtotime($dateYmd . ' -1 day'));

                $prev = DB::selectOne("
                    SELECT StartTime1, StartTime2, StartTime3, StartTime4
                    FROM attendance_clean
                    WHERE BadgeNumber = ? AND AttDate = ?
                ", [$badge, $prevDate]);

                if ($prev && $this->isValidTime($prev->StartTime1 ?? '')) {
                    if ($this->isValidTime($time2) && (int) date('H', strtotime($time2)) < 12) {
                        DB::update("UPDATE attendance_clean SET StartTime2 = ? WHERE BadgeNumber = ? AND AttDate = ?", [$time2, $badge, $prevDate]);
                        DB::update("UPDATE attendance_clean SET StartTime2 = '' WHERE BadgeNumber = ? AND AttDate = ?", [$badge, $attDate]);
                        $time2 = '';
                    }
                    if ($this->isValidTime($time3) && (int) date('H', strtotime($time3)) < 12) {
                        DB::update("UPDATE attendance_clean SET StartTime3 = ? WHERE BadgeNumber = ? AND AttDate = ?", [$time3, $badge, $prevDate]);
                        DB::update("UPDATE attendance_clean SET StartTime3 = '' WHERE BadgeNumber = ? AND AttDate = ?", [$badge, $attDate]);
                        $time3 = '';
                    }
                    if ($this->isValidTime($time4) && (int) date('H', strtotime($time4)) < 12) {
                        DB::update("UPDATE attendance_clean SET StartTime4 = ? WHERE BadgeNumber = ? AND AttDate = ?", [$time4, $badge, $prevDate]);
                        DB::update("UPDATE attendance_clean SET StartTime4 = '' WHERE BadgeNumber = ? AND AttDate = ?", [$badge, $attDate]);
                        $time4 = '';
                    }
                }
            }

            // --- Cross-midnight OT handling ---
            if ($this->isValidTime($OTout)) {
                $prevDate1 = date('m/d/Y', strtotime($dateYmd . ' -1 day'));
                $prev1 = DB::selectOne("
                    SELECT OTIn, OTOut FROM attendance_clean
                    WHERE BadgeNumber = ? AND AttDate = ?
                ", [$badge, $prevDate1]);

                if ($prev1 && $this->isValidTime($prev1->OTIn ?? '')) {
                    if ($this->isValidTime($OTout) && (int) date('H', strtotime($OTout)) < 12) {
                        DB::update("UPDATE attendance_clean SET OTOut = ? WHERE BadgeNumber = ? AND AttDate = ?", [$OTout, $badge, $prevDate1]);
                        DB::update("UPDATE attendance_clean SET OTOut = '' WHERE BadgeNumber = ? AND AttDate = ?", [$badge, $attDate]);
                        $OTout = '';
                    }
                }
            }

            // --- Fetch schedule ---
            $sched = DB::selectOne("
                SELECT {$dayPrefix}_timein AS timein,
                       {$dayPrefix}_timeout AS timeout,
                       {$dayPrefix}_crossday AS crossday
                FROM schedule WHERE id = ?
            ", [$schedId]);

            $schedIn  = $sched->timein  ?? '';
            $schedOut = $sched->timeout ?? '';
            $crossday = $sched->crossday ?? 0;

            $tardiness = 0;
            $undertime = 0;

            // --- Leave check ---
            if ($this->isOnLeave($badge, $attDate)) {
                DB::update("
                    UPDATE attendance_clean
                    SET StartTime1='L', StartTime2='L', StartTime3='L', StartTime4='L',
                        tardiness=0, undertime=0
                    WHERE BadgeNumber = ? AND AttDate = ?
                ", [$badge, $attDate]);
                continue;
            }

            $hasTimeIn  = $this->isValidTime($time1) || $this->isValidTime($time2);
            $hasTimeOut = $this->isValidTime($time3) || $this->isValidTime($time4);

            if (!$hasTimeIn && !$hasTimeOut) {
                if ($hasOT) {
                    $tardiness = 0;
                    $undertime = 0;
                } elseif ($dayName === 'sat' || $dayName === 'sun') {
                    $tardiness = 0;
                    $undertime = 0;
                } else {
                    $tardiness = 240;
                    $undertime = 240;
                }
            } else {
                // Tardiness
                if ($this->isValidTime($time1) && !empty($schedIn)) {
                    $schedInSec  = strtotime($dateYmd . ' ' . $schedIn);
                    $actualInSec = strtotime($dateYmd . ' ' . $time1);
                    if ($actualInSec > $schedInSec) {
                        $tardiness = (int) round(($actualInSec - $schedInSec) / 60);
                    }
                }
                if (!$hasTimeIn && !$hasOT) {
                    $tardiness += 240;
                }

                // Undertime
                if ($this->isValidTime($time4) && !empty($schedOut)) {
                    $schedInSec  = strtotime($dateYmd . ' ' . $schedIn);
                    $schedOutSec = strtotime($dateYmd . ' ' . $schedOut);

                    if ($crossday == 1 || $schedOutSec <= $schedInSec) {
                        $schedOutSec = strtotime($schedOut . ' +1 day', strtotime($dateYmd));
                    }

                    $actualOutSec = strtotime($dateYmd . ' ' . $time4);
                    if ($actualOutSec < $schedInSec) {
                        $actualOutSec = strtotime($time4 . ' +1 day', strtotime($dateYmd));
                    }

                    $diff      = ($schedOutSec - $actualOutSec) / 60;
                    $undertime = max(0, (int) round($diff));
                } else {
                    if ($hasOT) {
                        $undertime = 0;
                    } else {
                        $undertime = ($dayName === 'sat' || $dayName === 'sun') ? 0 : 240;
                    }
                }
            }

            DB::update("
                UPDATE attendance_clean SET tardiness = ?, undertime = ?
                WHERE BadgeNumber = ? AND AttDate = ?
            ", [$tardiness, $undertime, $badge, $attDate]);
        }
    }

    // -----------------------------------------------------------------------
    // Second pass — mirrors convert.php second while loop ($rcheck)
    // -----------------------------------------------------------------------
    private function secondPass(string $startDate, string $endDate): void
    {
        $rows = DB::select("
            SELECT id, StartTime1, StartTime2, StartTime3, StartTime4,
                   BadgeNumber, AttDate, OTIn, OTOut
            FROM attendance_clean
            WHERE STR_TO_DATE(AttDate, '%m/%d/%Y') BETWEEN ? AND ?
        ", [$startDate, $endDate]);

        foreach ($rows as $row) {
            $id      = $row->id;
            $badge   = $row->BadgeNumber;
            $attDate = $row->AttDate;
            $time1   = trim($row->StartTime1 ?? '');
            $time4   = trim($row->StartTime4 ?? '');
            $OTIn    = trim($row->OTIn ?? '');
            $OTout   = trim($row->OTOut ?? '');

            // Skip weekends
            [$m, $d, $y] = explode('/', $attDate);
            $dateYmd = "$y-$m-$d";
            $dayName = strtolower(date('D', strtotime($dateYmd)));
            if ($dayName === 'sat' || $dayName === 'sun') continue;

            // Skip if on leave
            if ($this->isOnLeave($badge, $attDate)) continue;

            $hasStartPair = $this->isValidTime($time1) && $this->isValidTime($time4);
            $hasOTTimes   = $this->isValidTime($OTIn) && $this->isValidTime($OTout);

            if (!$hasStartPair && !$hasOTTimes) continue;

            // Get schedule
            $empSched = DB::selectOne("SELECT schedule FROM employees WHERE badgeID = ?", [$badge]);
            $schedId  = $empSched->schedule ?? null;

            $undertime = 0;
            $overtime  = 0;

            if ($hasStartPair && $schedId) {
                $dayPrefix = $this->dayMap[$dayName];

                $sched = DB::selectOne("
                    SELECT {$dayPrefix}_timein AS timein,
                           {$dayPrefix}_timeout AS timeout,
                           {$dayPrefix}_crossday AS crossday
                    FROM schedule WHERE id = ?
                ", [$schedId]);

                $schedOut = $sched->timeout ?? '';
                $crossday = $sched->crossday ?? 0;

                $schedOutSec   = strtotime("$dateYmd $schedOut");
                $actualOutSec  = strtotime("$dateYmd $time4");
                $startTime1Sec = strtotime("$dateYmd $time1");

                if ($crossday == 1) {
                    $schedOutSec  = strtotime("$dateYmd $schedOut +1 day");
                    $actualOutSec = strtotime("$dateYmd $time4 +1 day");
                    if ($startTime1Sec > $actualOutSec) {
                        $startTime1Sec = strtotime("$dateYmd $time1 +1 day");
                    }
                }

                if ($actualOutSec < $startTime1Sec) {
                    $actualOutSec = strtotime("$dateYmd $time4 +1 day");
                }

                if ($actualOutSec >= $startTime1Sec) {
                    if ($actualOutSec < $schedOutSec) {
                        $undertime = (int) round(($schedOutSec - $actualOutSec) / 60);
                    }
                }
            }

            if ($hasOTTimes) {
                $OTinSec  = strtotime("$dateYmd $OTIn");
                $OToutSec = strtotime("$dateYmd $OTout");

                if ($OToutSec < $OTinSec) {
                    $OToutSec = strtotime("$dateYmd $OTout +1 day");
                }

                $overtime = (int) round(($OToutSec - $OTinSec) / 60);
                if ($overtime < 0) $overtime = 0;
            }

            DB::update("UPDATE attendance_clean SET undertime = ?, OT = ? WHERE id = ?", [$undertime, $overtime, $id]);
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------
    private function isValidTime(string $time): bool
    {
        return (bool) preg_match('/^(0?[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }

    private function expandLeaveDates(string $leaveString): array
    {
        $dates = [];
        foreach (explode(',', $leaveString) as $p) {
            $p = trim($p);
            if (preg_match('/^(\d{2})\/(\d{2})-(\d{2})\/(\d{4})$/', $p, $m)) {
                for ($d = (int) $m[2]; $d <= (int) $m[3]; $d++) {
                    $dates[] = sprintf('%02d/%02d/%04d', $m[1], $d, $m[4]);
                }
            } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $p)) {
                $dates[] = $p;
            }
        }
        return $dates;
    }

    private function isOnLeave(string $badge, string $attDate): bool
    {
        $leaves = DB::select(
            "SELECT date_start FROM leaves WHERE badgeID = ? AND status = 'Approved'",
            [$badge]
        );
        foreach ($leaves as $leave) {
            if (in_array($attDate, $this->expandLeaveDates($leave->date_start ?? ''))) {
                return true;
            }
        }
        return false;
    }
}
