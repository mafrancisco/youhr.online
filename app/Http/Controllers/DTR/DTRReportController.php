<?php

namespace App\Http\Controllers\DTR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
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
        return Inertia::render('DTR/Report', [
            'month'     => $request->get('month', now()->format('Y-m')),
            'employees' => Employee::active()->orderBy('empName')->get(['id', 'badgeID', 'empName']),
        ]);
    }

    public function bulkDownload(Request $request)
    {
        $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date'   => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'emp_status' => ['required', 'integer', 'in:1,2'],
        ]);

        $startDate = $request->start_date;
        $endDate   = $request->end_date;
        $empStatus = (int) $request->emp_status;

        $start_date = date('F j', strtotime($startDate));
        $end_date   = date('F j, Y', strtotime($endDate));
        $attRange   = $start_date . '-' . $end_date;
        $fname      = ($empStatus == 1) ? 'Regular Employees' : 'Contractual Employees';
        $filename   = $fname . ' DTR-' . $attRange . '.pdf';

        $content = $this->pdf->bulk($startDate, $endDate, $empStatus);

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
