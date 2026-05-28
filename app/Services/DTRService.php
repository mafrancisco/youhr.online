<?php

namespace App\Services;

use App\Models\AttendanceClean;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use App\Models\Submission;
use Carbon\Carbon;

class DTRService
{
    /**
     * Get the DTR data for an employee for a given month.
     */
    public function getMonthlyDTR(Employee $employee, string $month): array
    {
        [$year, $mon] = explode('-', $month);
        $start = Carbon::create($year, $mon, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth();

        $records = $this->getRecordsInRange($employee->badgeID, $start, $end);
        $requests = $this->getRequestsInRange($employee->badgeID, $start, $end);
        $submitted = Submission::isLocked($employee->badgeID, $month);

        // Pre-load gate passes and leaves for the month to flag blocked dates
        $blockedDates = $this->getBlockedDates($employee->badgeID, $start, $end);

        $days = [];
        for ($date = $start->copy(); $date <= $end; $date->addDay()) {
            $attDate = $date->format('m/d/Y');
            $rec = $records[$attDate] ?? null;
            $req = $requests[$attDate] ?? null;

            $days[] = [
                'date'       => $date->format('Y-m-d'),
                'attDate'    => $attDate,
                'dayName'    => $date->format('D'),
                'StartTime1' => $rec?->StartTime1,
                'StartTime2' => $rec?->StartTime2,
                'StartTime3' => $rec?->StartTime3,
                'StartTime4' => $rec?->StartTime4,
                'OT'         => $rec?->OT,
                'Tardiness'  => $rec?->Tardiness,
                'undertime'  => $rec?->undertime,
                'remarks'    => $rec?->remarks,
                'obtime'     => $rec?->obtime,
                'editBlocked' => $blockedDates[$attDate] ?? null,
                'request'    => $req ? [
                    'id'         => $req->id,
                    'StartTime1' => $req->StartTime1,
                    'StartTime2' => $req->StartTime2,
                    'StartTime3' => $req->StartTime3,
                    'StartTime4' => $req->StartTime4,
                    'remarks'    => $req->remarks,
                    'log1'       => $req->log1,
                    'log2'       => $req->log2,
                    'log3'       => $req->log3,
                    'log4'       => $req->log4,
                ] : null,
            ];
        }

        return [
            'employee'  => ['name' => $employee->empName, 'badgeID' => $employee->badgeID],
            'month'     => $month,
            'days'      => $days,
            'submitted' => $submitted,
        ];
    }

    /**
     * Get attendance records for a badge within a date range.
     * Filters in PHP to remain database-agnostic (dates stored as m/d/Y strings).
     */
    private function getRecordsInRange(string $badgeID, Carbon $start, Carbon $end): array
    {
        $rows = AttendanceClean::where('BadgeNumber', $badgeID)->get();

        $keyed = [];
        foreach ($rows as $row) {
            if ($this->inRange($row->AttDate, $start, $end)) {
                $keyed[$row->AttDate] = $row;
            }
        }
        return $keyed;
    }

    /**
     * Get adjustment requests for a badge within a date range.
     */
    private function getRequestsInRange(string $badgeID, Carbon $start, Carbon $end): array
    {
        $rows = AttendanceRequest::where('BadgeNumber', $badgeID)->get();

        $keyed = [];
        foreach ($rows as $row) {
            if ($this->inRange($row->AttDate, $start, $end)) {
                $keyed[$row->AttDate] = $row;
            }
        }
        return $keyed;
    }

    private function inRange(string $attDate, Carbon $start, Carbon $end): bool
    {
        try {
            return Carbon::createFromFormat('m/d/Y', $attDate)->between($start, $end);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get dates that are blocked from editing due to existing gate passes or leaves.
     * Returns array keyed by attDate (MM/DD/YYYY) with reason string.
     */
    private function getBlockedDates(string $badgeID, Carbon $start, Carbon $end): array
    {
        $blocked = [];

        // Check gate passes (approved or pending) in range
        $gatePasses = \Illuminate\Support\Facades\DB::select("
            SELECT gatepass_date, gatepass_type, status
            FROM gatepass
            WHERE badgeID = ?
              AND gatepass_date BETWEEN ? AND ?
              AND status IN ('Pending', 'Approved')
        ", [$badgeID, $start->format('Y-m-d'), $end->format('Y-m-d')]);

        foreach ($gatePasses as $gp) {
            // Convert Y-m-d to MM/DD/YYYY
            $parts = explode('-', $gp->gatepass_date);
            if (count($parts) === 3) {
                $attDate = "{$parts[1]}/{$parts[2]}/{$parts[0]}";
                $blocked[$attDate] = "{$gp->status} Gate Pass ({$gp->gatepass_type})";
            }
        }

        // Check leaves (approved or pending) covering dates in range
        $leaves = \Illuminate\Support\Facades\DB::select(
            "SELECT date_start, status FROM leaves WHERE badgeID = ? AND status IN ('Pending', 'Approved')",
            [$badgeID]
        );

        foreach ($leaves as $leave) {
            $leaveDates = $this->expandLeaveDates($leave->date_start ?? '');
            foreach ($leaveDates as $ld) {
                if ($this->inRange($ld, $start, $end) && !isset($blocked[$ld])) {
                    $blocked[$ld] = "{$leave->status} Leave";
                }
            }
        }

        return $blocked;
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
            } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $p, $m)) {
                $dates[] = sprintf('%02d/%02d/%04d', $m[2], $m[3], $m[1]);
            }
        }
        return $dates;
    }
}
