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

    /** Pre-loaded schedules keyed by schedule ID */
    private array $schedules = [];

    /** Pre-loaded approved leave dates keyed by badgeID */
    private array $leaveDates = [];

    /** Pre-loaded approved gate passes keyed by badgeID => [date => [passes]] */
    private array $gatePasses = [];

    public function compute(string $startDate, string $endDate, ?int $empStatus = null): void
    {
        // Pre-load all reference data to avoid N+1 queries
        $this->preloadSchedules();
        $this->preloadLeaves();
        $this->preloadGatePasses($startDate, $endDate);

        // First pass: compute tardiness and initial undertime
        $this->firstPass($startDate, $endDate, $empStatus);

        // Second pass: refine undertime, compute OT, apply gate pass adjustments
        $this->secondPass($startDate, $endDate, $empStatus);
    }

    // -----------------------------------------------------------------------
    // Pre-loading
    // -----------------------------------------------------------------------

    private function preloadSchedules(): void
    {
        $rows = DB::select("SELECT * FROM schedule");
        foreach ($rows as $row) {
            $this->schedules[$row->id] = $row;
        }
    }

    private function preloadLeaves(): void
    {
        $leaves = DB::select("SELECT badgeID, date_start FROM leaves WHERE status = 'Approved'");
        foreach ($leaves as $leave) {
            $badge = $leave->badgeID;
            if (!isset($this->leaveDates[$badge])) {
                $this->leaveDates[$badge] = [];
            }
            $expanded = $this->expandLeaveDates($leave->date_start ?? '');
            $this->leaveDates[$badge] = array_merge($this->leaveDates[$badge], $expanded);
        }
        foreach ($this->leaveDates as $badge => $dates) {
            $this->leaveDates[$badge] = array_unique($dates);
        }
    }

    private function preloadGatePasses(string $startDate, string $endDate): void
    {
        // Load all approved gate passes within the date range
        // gatepass_date is stored as Y-m-d format
        $passes = DB::select("
            SELECT badgeID, gatepass_type, gatepass_date, gatepass_timeout, gatepass_timein,
                   actual_timeout, actual_timein
            FROM gatepass
            WHERE date_time_approved != ''
              AND date_time_approved IS NOT NULL
              AND gatepass_date BETWEEN ? AND ?
        ", [$startDate, $endDate]);

        foreach ($passes as $gp) {
            $badge = $gp->badgeID;
            // Convert gatepass_date (Y-m-d) to MM/DD/YYYY to match AttDate format
            $dateParts = explode('-', $gp->gatepass_date);
            if (count($dateParts) === 3) {
                $attDateKey = $dateParts[1] . '/' . $dateParts[2] . '/' . $dateParts[0];
            } else {
                $attDateKey = $gp->gatepass_date;
            }

            if (!isset($this->gatePasses[$badge])) {
                $this->gatePasses[$badge] = [];
            }
            if (!isset($this->gatePasses[$badge][$attDateKey])) {
                $this->gatePasses[$badge][$attDateKey] = [];
            }
            $this->gatePasses[$badge][$attDateKey][] = $gp;
        }
    }

    // -----------------------------------------------------------------------
    // First pass — compute tardiness and initial undertime
    // -----------------------------------------------------------------------
    private function firstPass(string $startDate, string $endDate, ?int $empStatus = null): void
    {
        $statusFilter = $empStatus === null ? '' : 'e.empStatus = ? AND';
        $params = $empStatus === null
            ? [$startDate, $endDate]
            : [$empStatus, $startDate, $endDate];

        $rows = DB::select("
            SELECT c.id, c.BadgeNumber, c.AttDate, c.StartTime1, c.StartTime2, c.StartTime3, c.StartTime4,
                   e.schedule, c.OTIn, c.OTOut
            FROM attendance_clean c
            JOIN employees e ON c.BadgeNumber = e.badgeID
            WHERE {$statusFilter}
              STR_TO_DATE(c.AttDate, '%m/%d/%Y') BETWEEN ? AND ?
        ", $params);

        $tardinessUpdates = [];
        $leaveUpdates = [];

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

            [$m, $d, $y] = explode('/', $attDate);
            $dateYmd = "$y-$m-$d";
            $dayName = strtolower(date('D', strtotime($dateYmd)));
            $dayPrefix = $this->dayMap[$dayName];

            // --- Cross-midnight punch handling ---
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

            // --- Fetch schedule from pre-loaded cache ---
            $sched = $this->getScheduleForDay($schedId, $dayPrefix);
            $schedIn  = $sched['timein'];
            $schedOut = $sched['timeout'];
            $crossday = $sched['crossday'];

            $tardiness = 0;
            $undertime = 0;

            // --- Leave check ---
            if ($this->isOnLeave($badge, $attDate)) {
                $leaveUpdates[] = [$badge, $attDate];
                continue;
            }

            // Determine which time slots have valid data
            $hasTime1 = $this->isValidTime($time1);  // Time In
            $hasTime2 = $this->isValidTime($time2);  // Break Out
            $hasTime3 = $this->isValidTime($time3);  // Break In
            $hasTime4 = $this->isValidTime($time4);  // Time Out

            $hasTimeIn  = $hasTime1;
            $hasTimeOut = $hasTime4;
            $hasAnyLog  = $hasTime1 || $hasTime2 || $hasTime3 || $hasTime4;

            // Check if there's a gate pass covering missing time slots
            $hasGatePass = $this->hasGatePassOnDate($badge, $attDate);

            if (!$hasAnyLog) {
                // No logs at all
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
                // --- Condition 1: No Time In, but BreakOut/BreakIn/TimeOut present ---
                // 4 hrs tardy (unless gate pass covers it)
                if (!$hasTime1 && ($hasTime2 || $hasTime3 || $hasTime4)) {
                    if (!$hasGatePass) {
                        $tardiness = 240;
                    }
                }
                // --- Normal tardiness: Time In exists but late ---
                elseif ($hasTime1 && !empty($schedIn)) {
                    $schedInSec  = strtotime($dateYmd . ' ' . $schedIn);
                    $actualInSec = strtotime($dateYmd . ' ' . $time1);
                    if ($actualInSec > $schedInSec) {
                        $tardiness = (int) round(($actualInSec - $schedInSec) / 60);
                    }
                }

                // --- Condition 3: No Time In AND no Break Out, but BreakIn/TimeOut present ---
                // Already covered by Condition 1 (4 hrs tardy)
                if (!$hasTime1 && !$hasTime2 && ($hasTime3 || $hasTime4)) {
                    if (!$hasGatePass && $tardiness < 240) {
                        $tardiness = 240;
                    }
                }

                // --- Condition 2: No TimeOut, but TimeIn/BreakOut/BreakIn present ---
                // 4 hrs undertime (unless gate pass covers it)
                if (!$hasTime4 && ($hasTime1 || $hasTime2 || $hasTime3)) {
                    if (!$hasGatePass) {
                        $undertime = 240;
                    }
                }
                // --- Normal undertime: TimeOut exists but early ---
                elseif ($hasTime4 && !empty($schedOut)) {
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
                }

                // --- Condition 4: No BreakIn AND no TimeOut, but TimeIn/BreakOut present ---
                // Already covered by Condition 2 (4 hrs undertime)
                if (!$hasTime3 && !$hasTime4 && ($hasTime1 || $hasTime2)) {
                    if (!$hasGatePass && $undertime < 240) {
                        $undertime = 240;
                    }
                }
            }

            $tardinessUpdates[] = [$tardiness, $undertime, $badge, $attDate];
        }

        // Batch execute
        foreach ($tardinessUpdates as $params) {
            DB::update("UPDATE attendance_clean SET tardiness = ?, undertime = ? WHERE BadgeNumber = ? AND AttDate = ?", $params);
        }
        foreach ($leaveUpdates as [$badge, $attDate]) {
            DB::update("
                UPDATE attendance_clean
                SET StartTime1='L', StartTime2='L', StartTime3='L', StartTime4='L',
                    tardiness=0, undertime=0
                WHERE BadgeNumber = ? AND AttDate = ?
            ", [$badge, $attDate]);
        }
    }

    // -----------------------------------------------------------------------
    // Second pass — refine undertime, compute OT, apply gate pass adjustments
    // -----------------------------------------------------------------------
    private function secondPass(string $startDate, string $endDate, ?int $empStatus = null): void
    {
        // Load OT threshold: minutes after scheduled timeout where OT starts
        // The 'otin' setting's before_minutes field stores this offset
        $otSetting = DB::selectOne("SELECT before_minutes FROM time_detection_settings WHERE punch_type = 'otin' LIMIT 1");
        $otOffsetMinutes = $otSetting ? (int) $otSetting->before_minutes : 0;

        $statusFilter = $empStatus === null ? '' : 'e.empStatus = ? AND';
        $params = $empStatus === null
            ? [$startDate, $endDate]
            : [$empStatus, $startDate, $endDate];

        $rows = DB::select("
            SELECT c.id, c.StartTime1, c.StartTime2, c.StartTime3, c.StartTime4,
                   c.BadgeNumber, c.AttDate, c.OTIn, c.OTOut, e.schedule
            FROM attendance_clean c
            JOIN employees e ON c.BadgeNumber = e.badgeID
            WHERE {$statusFilter}
              STR_TO_DATE(c.AttDate, '%m/%d/%Y') BETWEEN ? AND ?
        ", $params);

        $updates = [];

        foreach ($rows as $row) {
            $id      = $row->id;
            $badge   = $row->BadgeNumber;
            $attDate = $row->AttDate;
            $time1   = trim($row->StartTime1 ?? '');
            $time4   = trim($row->StartTime4 ?? '');
            $schedId = $row->schedule;

            [$m, $d, $y] = explode('/', $attDate);
            $dateYmd = "$y-$m-$d";
            $dayName = strtolower(date('D', strtotime($dateYmd)));

            // Skip weekends
            if ($dayName === 'sat' || $dayName === 'sun') continue;

            // Skip if on leave
            if ($this->isOnLeave($badge, $attDate)) continue;

            $hasStartPair = $this->isValidTime($time1) && $this->isValidTime($time4);

            // Also read device-reported OT punches
            $OTin  = trim($row->OTIn  ?? '');
            $OTout = trim($row->OTOut ?? '');
            $hasDeviceOT = $this->isValidTime($OTin) && $this->isValidTime($OTout);

            // Skip days with no usable data (neither regular pair nor device OT)
            if (!$hasStartPair && !$hasDeviceOT) continue;

            $dayPrefix = $this->dayMap[$dayName];
            $undertime = 0;
            $overtime  = 0;

            if ($schedId) {
                $sched = $this->getScheduleForDay($schedId, $dayPrefix);
                $schedIn  = $sched['timein'];
                $schedOut = $sched['timeout'];
                $crossday = $sched['crossday'];

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

                // Undertime: employee left before scheduled timeout
                if ($hasStartPair && $actualOutSec >= $startTime1Sec && $actualOutSec < $schedOutSec) {
                    $undertime = (int) round(($schedOutSec - $actualOutSec) / 60);
                }

                // Overtime computation:
                // Priority 1 — device-reported OT (attType 4/5): trust OTIn and OTOut directly.
                // Priority 2 — computed OT: time4 stayed past the OT threshold.
                if ($hasDeviceOT) {
                    $otInSec  = strtotime("$dateYmd $OTin");
                    $otOutSec = strtotime("$dateYmd $OTout");
                    if ($otOutSec <= $otInSec) {
                        $otOutSec = strtotime("$dateYmd $OTout +1 day"); // cross-midnight OT
                    }
                    $overtime = (int) round(($otOutSec - $otInSec) / 60);
                    if ($overtime < 0) $overtime = 0;
                } elseif ($hasStartPair) {
                    // Computed OT: time4 beyond scheduled timeout + offset
                    $otThresholdSec = $schedOutSec + ($otOffsetMinutes * 60);
                    if ($actualOutSec > $otThresholdSec) {
                        $overtime = (int) round(($actualOutSec - $otThresholdSec) / 60);
                        if ($overtime < 0) $overtime = 0;
                    }
                }
            }

            // --- Half-day rule: TimeIn + TimeOut present but no BreakOut / BreakIn ---
            // The day counts as 0.5-day worked; enforce 4-hr undertime when no
            // undertime was already recorded (employee stayed past scheduled timeout).
            $time2sp = trim($row->StartTime2 ?? '');
            $time3sp = trim($row->StartTime3 ?? '');
            if ($hasStartPair
                && !$this->isValidTime($time2sp)
                && !$this->isValidTime($time3sp)
                && $undertime === 0) {
                $undertime = 240;
            }

            // --- Gate Pass Adjustment ---
            $gpUndertime = $this->computeGatePassUndertime($badge, $attDate, $dateYmd, $schedId, $dayPrefix);
            $undertime += $gpUndertime;

            $updates[] = [$undertime, $overtime, $id];
        }

        foreach ($updates as $params) {
            DB::update("UPDATE attendance_clean SET undertime = ?, OT = ? WHERE id = ?", $params);
        }
    }

    // -----------------------------------------------------------------------
    // Gate Pass undertime computation
    // -----------------------------------------------------------------------

    /**
     * Compute additional undertime from Personal gate passes for a given day.
     * Official Business and Official Time are NOT deducted.
     * Personal gate passes are deducted, excluding lunch break overlap.
     */
    private function computeGatePassUndertime(string $badge, string $attDate, string $dateYmd, mixed $schedId, string $dayPrefix): int
    {
        $passes = $this->gatePasses[$badge][$attDate] ?? [];
        if (empty($passes)) return 0;

        // Get lunch break times from schedule (breakout = lunch start, breakin = lunch end)
        $sched = $this->getScheduleForDay($schedId, $dayPrefix);
        $lunchStart = $sched['breakout'] ?? '';
        $lunchEnd   = $sched['breakin'] ?? '';

        $hasLunch = $this->isValidTime($lunchStart) && $this->isValidTime($lunchEnd);
        $lunchStartSec = $hasLunch ? strtotime("$dateYmd $lunchStart") : 0;
        $lunchEndSec   = $hasLunch ? strtotime("$dateYmd $lunchEnd") : 0;

        $totalPersonalMinutes = 0;

        foreach ($passes as $gp) {
            // Only Personal gate passes are deducted
            if ($gp->gatepass_type !== 'Personal') continue;

            // Use actual times if available, otherwise use requested times
            $timeout = trim($gp->actual_timeout ?: $gp->gatepass_timeout ?? '');
            $timein  = trim($gp->actual_timein ?: $gp->gatepass_timein ?? '');

            if (!$this->isValidTime($timeout) || !$this->isValidTime($timein)) continue;

            $gpOutSec = strtotime("$dateYmd $timeout");
            $gpInSec  = strtotime("$dateYmd $timein");

            // Handle cross-midnight (unlikely for gate pass but be safe)
            if ($gpInSec <= $gpOutSec) continue; // invalid: timein must be after timeout

            $gpDuration = $gpInSec - $gpOutSec; // total seconds out

            // Subtract lunch break overlap if it crosses lunch period
            if ($hasLunch) {
                $overlapStart = max($gpOutSec, $lunchStartSec);
                $overlapEnd   = min($gpInSec, $lunchEndSec);

                if ($overlapEnd > $overlapStart) {
                    // There is overlap with lunch break — subtract it
                    $gpDuration -= ($overlapEnd - $overlapStart);
                }
            }

            if ($gpDuration > 0) {
                $totalPersonalMinutes += (int) round($gpDuration / 60);
            }
        }

        return $totalPersonalMinutes;
    }

    // -----------------------------------------------------------------------
    // Schedule helper (extended to include breakout/breakin)
    // -----------------------------------------------------------------------

    private function getScheduleForDay(mixed $schedId, string $dayPrefix): array
    {
        $sched = $this->schedules[$schedId] ?? null;
        if (!$sched) {
            return ['timein' => '', 'timeout' => '', 'crossday' => 0, 'breakout' => '', 'breakin' => ''];
        }

        $timeinCol   = $dayPrefix . '_timein';
        $timeoutCol  = $dayPrefix . '_timeout';
        $crossdayCol = $dayPrefix . '_crossday';
        $breakoutCol = $dayPrefix . '_breakout';
        $breakinCol  = $dayPrefix . '_breakin';

        return [
            'timein'   => $sched->$timeinCol ?? '',
            'timeout'  => $sched->$timeoutCol ?? '',
            'crossday' => $sched->$crossdayCol ?? 0,
            'breakout' => $sched->$breakoutCol ?? '',
            'breakin'  => $sched->$breakinCol ?? '',
        ];
    }

    // -----------------------------------------------------------------------
    // Other helpers
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
        return in_array($attDate, $this->leaveDates[$badge] ?? []);
    }

    /**
     * Check if there's an approved or pending gate pass on the given date
     * that exempts the employee from missing-log penalties.
     * Only Official Business and Official Time exempt. Personal gate passes do NOT exempt.
     */
    private function hasGatePassOnDate(string $badge, string $attDate): bool
    {
        // Convert AttDate MM/DD/YYYY to Y-m-d for gate pass lookup
        $parts = explode('/', $attDate);
        if (count($parts) === 3) {
            $dateYmd = "{$parts[2]}-{$parts[0]}-{$parts[1]}";
        } else {
            $dateYmd = $attDate;
        }

        // Check pre-loaded gate passes first
        if (isset($this->gatePasses[$badge][$attDate]) && !empty($this->gatePasses[$badge][$attDate])) {
            // Only exempt if at least one is Official Business or Official Time
            foreach ($this->gatePasses[$badge][$attDate] as $gp) {
                if ($gp->gatepass_type === 'Official Business' || $gp->gatepass_type === 'Official Time') {
                    return true;
                }
            }
            return false; // Personal gate pass does NOT exempt
        }

        // Fallback: direct DB check
        $gp = DB::selectOne("
            SELECT id FROM gatepass
            WHERE badgeID = ? AND gatepass_date = ?
              AND status IN ('Pending', 'Approved')
              AND gatepass_type IN ('Official Business', 'Official Time')
            LIMIT 1
        ", [$badge, $dateYmd]);

        return $gp !== null;
    }
}
