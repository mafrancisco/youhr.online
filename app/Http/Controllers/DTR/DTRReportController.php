<?php

namespace App\Http\Controllers\DTR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Services\DTRPdfService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DTRReportController extends Controller
{
    public function __construct(private DTRPdfService $pdf) {}

    public function bulk(Request $request): Response
    {
        $employeeStatuses = EmployeeStatus::orderBy('id')->get(['id', 'description']);
        $selectedStatus = (int) $request->get('emp_status', $employeeStatuses->first()?->id);

        return Inertia::render('DTR/Report', [
            'start_date' => $request->get('start_date', now()->startOfMonth()->format('Y-m-d')),
            'end_date'   => $request->get('end_date',   now()->endOfMonth()->format('Y-m-d')),
            'emp_status' => $selectedStatus,
            'employeeStatuses' => $employeeStatuses,
            'employees'  => Employee::active()
                ->orderBy('empName')
                ->get(['id', 'badgeID', 'empName', 'empStatus']),
        ]);
    }

    public function bulkDownload(Request $request)
    {
        $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date'   => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'emp_status' => ['required', 'integer', 'exists:empstatus,id'],
            'badges'     => ['nullable', 'string'],
        ]);

        $startDate = $request->start_date;
        $endDate   = $request->end_date;
        $empStatus = (int) $request->emp_status;

        // An explicit selection produces a single combined PDF for just those
        // employees. Opening one download per employee is not viable: browsers block
        // all but the first popup when several are triggered from one click.
        $badgeIDs = array_values(array_filter(
            array_map('trim', explode(',', (string) $request->get('badges', ''))),
            fn ($b) => $b !== ''
        ));

        if (!empty($badgeIDs)) {
            // Restrict to badges that exist under the selected status, so a
            // tampered list cannot include employees from other status groups.
            $badgeIDs = Employee::whereIn('badgeID', $badgeIDs)
                ->where('empStatus', $empStatus)
                ->pluck('badgeID')
                ->all();

            if (empty($badgeIDs)) {
                return back()->with('error', 'None of the selected employees could be found.');
            }
        }

        $start_date = date('F j', strtotime($startDate));
        $end_date   = date('F j, Y', strtotime($endDate));
        $attRange   = $start_date . '-' . $end_date;

        if (!empty($badgeIDs)) {
            $filename = 'Selected Employees DTR-' . $attRange . '.pdf';
        } else {
            $fname = EmployeeStatus::find($empStatus)?->description ?? 'Employees';
            $filename = $fname . ' DTR-' . $attRange . '.pdf';
        }

        $content = $this->pdf->bulk($startDate, $endDate, $empStatus, $badgeIDs);

        return response()->streamDownload(
            fn() => print($content),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function individual(Request $request)
    {
        $badgeParam = $request->get('badge');
        $employee   = $badgeParam && $request->user()->isHR()
            ? Employee::where('badgeID', $badgeParam)->firstOrFail()
            : Employee::where('email', $request->user()->email)->firstOrFail();

        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        $start_date = date('F j', strtotime($startDate));
        $end_date   = date('F j, Y', strtotime($endDate));
        $attRange   = $start_date . ' - ' . $end_date;

        $head = DB::selectOne(
            "SELECT headname, headposition FROM heads WHERE headname = ?",
            [$employee->empHead]
        );

        $sub = DB::selectOne(
            "SELECT date_submitted, time_submitted FROM submissions WHERE badgeID = ? AND attRange = ?",
            [$employee->badgeID, $attRange]
        );

        $filename = $employee->empName . ' DTR.pdf';
        $content  = $this->pdf->individual(
            $employee->badgeID, $employee->empName, $head, $attRange, $startDate, $endDate, $sub
        );

        return response()->streamDownload(
            fn() => print($content),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
