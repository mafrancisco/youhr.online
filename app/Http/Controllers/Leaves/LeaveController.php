<?php

namespace App\Http\Controllers\Leaves;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Services\LeaveControlNumberService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function __construct(private LeaveControlNumberService $controlNo) {}

    public function index(Request $request): Response
    {
        $employee = Employee::where('email', $request->user()->email)->firstOrFail();

        $leaves = Leave::with('type')
            ->where('badgeID', $employee->badgeID)
            ->where('status', '!=', 'Cancelled')
            ->orderByDesc('id')
            ->get()
            ->map(fn($l) => [
                'id'          => $l->id,
                'controlno'   => $l->controlno,
                'date_filed'  => $l->date_filed?->format('Y-m-d H:i'),
                'type_name'   => $l->type?->full_name,
                'dates'       => $l->date_start,
                'noofdays'    => $l->noofdays,
                'details'     => $l->leave_details,
                'status'      => $l->status,
                'dateUpdated' => $l->dateUpdated,
            ]);

        return Inertia::render('Leaves/Index', [
            'leaveTypes' => LeaveType::orderBy('id')->get(['id', 'leave_type', 'acronym']),
            'leaves'     => $leaves,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'leave_type'    => ['required', 'integer', 'exists:leave_type,id'],
            'dates'         => ['required', 'array', 'min:1'],
            'dates.*'       => ['date_format:Y-m-d'],
            'destination'   => ['nullable', 'string', 'max:100'],
            'location'      => ['nullable', 'string', 'max:255'],
            'sickleave'     => ['nullable', 'string', 'max:100'],
            'illness'       => ['nullable', 'string', 'max:255'],
            'women_illness' => ['nullable', 'string', 'max:255'],
            'studyleave'    => ['nullable', 'string', 'max:100'],
            'otherleave'    => ['nullable', 'string', 'max:100'],
        ]);

        $employee = Employee::where('email', $request->user()->email)->firstOrFail();

        $details = trim(implode(' ', array_filter([
            $request->destination, $request->location, $request->sickleave,
            $request->illness, $request->women_illness,
            $request->studyleave, $request->otherleave,
        ])));

        $controlno = $this->controlNo->generate();

        Leave::create([
            'controlno'     => $controlno,
            'badgeID'       => $employee->badgeID,
            'leave_type'    => $request->leave_type,
            'date_start'    => implode(',', $request->dates),
            'leave_details' => $details,
            'date_filed'    => now(),
            'noofdays'      => count($request->dates),
            'status'        => 'Pending',
        ]);

        return back()->with('success', 'Leave filed. Control No: ' . $controlno);
    }

    public function destroy(Request $request, Leave $leave)
    {
        $employee = Employee::where('email', $request->user()->email)->firstOrFail();
        abort_unless($leave->badgeID === $employee->badgeID && $leave->status === 'Pending', 403);
        $leave->update(['status' => 'Cancelled']);
        return back()->with('success', 'Leave cancelled.');
    }

    public function adminIndex(): Response
    {
        $leaves = Leave::with(['type', 'employee'])
            ->where('status', 'Pending')
            ->orderByDesc('id')
            ->get()
            ->map(fn($l) => [
                'id'         => $l->id,
                'controlno'  => $l->controlno,
                'empName'    => $l->employee?->empName,
                'date_filed' => $l->date_filed?->format('Y-m-d H:i'),
                'type_name'  => $l->type?->full_name,
                'dates'      => $l->date_start,
                'noofdays'   => $l->noofdays,
                'details'    => $l->leave_details,
                'status'     => $l->status,
            ]);

        return Inertia::render('Leaves/Admin', ['leaves' => $leaves]);
    }

    public function update(Request $request, Leave $leave)
    {
        $request->validate([
            'status'          => ['required', 'in:Pending,Approved,Cancelled'],
            'credits_vl'      => ['nullable', 'numeric', 'min:0'],
            'credits_sl'      => ['nullable', 'numeric', 'min:0'],
            'ot_credits'      => ['nullable', 'numeric', 'min:0'],
            'service_credits' => ['nullable', 'numeric', 'min:0'],
        ]);

        $leave->update([
            'status'          => $request->status,
            'credits_vl'      => $request->credits_vl ?? 0,
            'credits_sl'      => $request->credits_sl ?? 0,
            'ot_credits'      => $request->ot_credits ?? 0,
            'service_credits' => $request->service_credits ?? 0,
            'dateUpdated'     => now()->toDateString(),
        ]);

        return back()->with('success', 'Leave updated.');
    }

    public function downloadForm(Leave $leave)
    {
        $employee  = Employee::where('badgeID', $leave->badgeID)->firstOrFail();
        $leaveType = $leave->type;

        $html = "<h2>Leave Application - {$leave->controlno}</h2>"
              . "<p>Name: {$employee->empName}</p>"
              . "<p>Type: {$leaveType?->full_name}</p>"
              . "<p>Dates: {$leave->date_start}</p>"
              . "<p>Days: {$leave->noofdays}</p>"
              . "<p>Status: {$leave->status}</p>";

        $mpdf = new \Mpdf\Mpdf(['format' => 'A4']);
        $mpdf->WriteHTML($html);
        $filename = 'Leave-' . $leave->controlno . '.pdf';

        return response()->streamDownload(
            fn() => print($mpdf->Output($filename, 'S')),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
