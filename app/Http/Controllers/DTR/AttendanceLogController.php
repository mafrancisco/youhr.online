<?php

namespace App\Http\Controllers\DTR;

use App\Http\Controllers\Controller;
use App\Models\AttendanceClean;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceLogController extends Controller
{
    public function show(Request $request, Employee $emp): Response
    {
        $month = $request->get('month', now()->format('Y-m'));
        [$year, $mon] = explode('-', $month);
        $start = Carbon::create($year, $mon, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth();

        $records = AttendanceClean::where('BadgeNumber', $emp->badgeID)
            ->get()
            ->filter(fn($r) => $this->inRange($r->AttDate, $start, $end))
            ->values()
            ->map(fn($r) => [
                'id'         => $r->id,
                'attDate'    => $r->AttDate,
                'StartTime1' => $r->StartTime1,
                'StartTime2' => $r->StartTime2,
                'StartTime3' => $r->StartTime3,
                'StartTime4' => $r->StartTime4,
                'OTIn'       => $r->OTIn,
                'OTOut'      => $r->OTOut,
                'OT'         => $r->OT,
                'Tardiness'  => $r->Tardiness,
                'undertime'  => $r->undertime,
                'remarks'    => $r->remarks,
                'obtime'     => $r->obtime,
            ]);

        $requests = AttendanceRequest::where('BadgeNumber', $emp->badgeID)
            ->get()
            ->filter(fn($r) => $this->inRange($r->AttDate, $start, $end))
            ->values()
            ->map(fn($r) => [
                'id'         => $r->id,
                'attDate'    => $r->AttDate,
                'StartTime1' => $r->StartTime1,
                'StartTime2' => $r->StartTime2,
                'StartTime3' => $r->StartTime3,
                'StartTime4' => $r->StartTime4,
                'remarks'    => $r->remarks,
            ]);

        return Inertia::render('DTR/Log', [
            'employee' => ['id' => $emp->id, 'badgeID' => $emp->badgeID, 'empName' => $emp->empName],
            'month'    => $month,
            'records'  => $records,
            'requests' => $requests,
        ]);
    }

    public function updateTime(Request $request, AttendanceClean $clean)
    {
        $request->validate([
            'column' => ['required', 'in:StartTime1,StartTime2,StartTime3,StartTime4'],
            'time'   => ['nullable', 'date_format:H:i'],
        ]);

        $clean->update([$request->column => $request->time ?: null]);

        AttendanceRequest::where('BadgeNumber', $clean->BadgeNumber)
            ->where('AttDate', $clean->AttDate)
            ->update([AttendanceRequest::logColumn($request->column) => $request->time ?: null]);

        return back()->with('success', 'Time updated.');
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
