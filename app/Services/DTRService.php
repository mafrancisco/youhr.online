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
                'request'    => $req ? [
                    'id'         => $req->id,
                    'StartTime1' => $req->StartTime1,
                    'StartTime2' => $req->StartTime2,
                    'StartTime3' => $req->StartTime3,
                    'StartTime4' => $req->StartTime4,
                    'remarks'    => $req->remarks,
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
}
