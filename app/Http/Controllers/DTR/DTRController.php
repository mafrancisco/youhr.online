<?php

namespace App\Http\Controllers\DTR;

use App\Http\Controllers\Controller;
use App\Models\AttendanceClean;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DTRController extends Controller
{
    public function index(Request $request): Response
    {
        $employee = Employee::where('email', $request->user()->email)->firstOrFail();

        $month = $request->get('month', now()->format('Y-m'));
        [$year, $mon] = explode('-', $month);
        $start = Carbon::create($year, $mon, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth();

        $records = AttendanceClean::where('BadgeNumber', $employee->badgeID)
            ->get()
            ->filter(fn($r) => $this->inRange($r->AttDate, $start, $end))
            ->keyBy('AttDate');

        $requests = AttendanceRequest::where('BadgeNumber', $employee->badgeID)
            ->get()
            ->filter(fn($r) => $this->inRange($r->AttDate, $start, $end))
            ->keyBy('AttDate');

        $submitted = Submission::isLocked($employee->badgeID, $month);

        $days = [];
        for ($date = $start->copy(); $date <= $end; $date->addDay()) {
            $attDate = $date->format('m/d/Y');
            $rec = $records->get($attDate);
            $req = $requests->get($attDate);

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

        return Inertia::render('DTR/Index', [
            'employee'  => ['name' => $employee->empName, 'badgeID' => $employee->badgeID],
            'month'     => $month,
            'days'      => $days,
            'submitted' => $submitted,
        ]);
    }

    private function inRange(string $attDate, Carbon $start, Carbon $end): bool
    {
        try {
            $d = Carbon::createFromFormat('m/d/Y', $attDate);
            return $d->between($start, $end);
        } catch (\Exception) {
            return false;
        }
    }
}
