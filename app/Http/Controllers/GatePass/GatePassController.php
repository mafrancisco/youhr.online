<?php

namespace App\Http\Controllers\GatePass;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\GatePass;
use App\Services\GatePassControlNumberService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GatePassController extends Controller
{
    public function __construct(private GatePassControlNumberService $controlNo) {}

    public function index(Request $request): Response
    {
        $employee = Employee::where('email', $request->user()->email)->firstOrFail();

        $passes = GatePass::where('badgeID', $employee->badgeID)
            ->where('status', '!=', 'Cancelled')
            ->orderByDesc('gatepass_date')
            ->get()
            ->map(fn($g) => [
                'id'                 => $g->id,
                'controlno'          => $g->controlno,
                'gatepass_type'      => $g->gatepass_type,
                'gatepass_date'      => $g->gatepass_date?->format('Y-m-d'),
                'gatepass_timeout'   => $g->gatepass_timeout,
                'gatepass_timein'    => $g->gatepass_timein,
                'purpose'            => $g->purpose,
                'destination'        => $g->destination,
                'date_time_approved' => $g->date_time_approved?->format('Y-m-d H:i'),
                'status'             => $g->status,
            ]);

        return Inertia::render('GatePass/Index', ['passes' => $passes]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'gatepass_type'    => ['required', 'in:Official Business,Official Time,Personal'],
            'gatepass_date'    => ['required', 'date'],
            'gatepass_timeout' => ['nullable', 'date_format:H:i'],
            'gatepass_timein'  => ['nullable', 'date_format:H:i'],
            'purpose'          => ['required', 'string', 'max:255'],
            'destination'      => ['nullable', 'string', 'max:255'],
        ]);

        $employee  = Employee::where('email', $request->user()->email)->firstOrFail();
        $controlno = $this->controlNo->generate();

        GatePass::create([
            'controlno'          => $controlno,
            'badgeID'            => $employee->badgeID,
            'gatepass_type'      => $request->gatepass_type,
            'gatepass_date'      => $request->gatepass_date,
            'gatepass_timeout'   => $request->gatepass_timeout,
            'gatepass_timein'    => $request->gatepass_timein,
            'purpose'            => $request->purpose,
            'destination'        => $request->destination,
            'gatepass_datefiled' => now(),
            'status'             => 'Pending',
        ]);

        return back()->with('success', 'Gate pass filed. Control No: ' . $controlno);
    }

    public function update(Request $request, GatePass $gp)
    {
        $employee = Employee::where('email', $request->user()->email)->firstOrFail();
        abort_unless($gp->badgeID === $employee->badgeID && $gp->isPending(), 403);

        $request->validate([
            'gatepass_type'    => ['required', 'in:Official Business,Official Time,Personal'],
            'gatepass_date'    => ['required', 'date'],
            'gatepass_timeout' => ['nullable', 'date_format:H:i'],
            'gatepass_timein'  => ['nullable', 'date_format:H:i'],
            'purpose'          => ['nullable', 'string', 'max:255'],
            'destination'      => ['nullable', 'string', 'max:255'],
        ]);

        $gp->update($request->only(['gatepass_type','gatepass_date','gatepass_timeout','gatepass_timein','purpose','destination']));
        return back()->with('success', 'Gate pass updated.');
    }

    public function destroy(Request $request, GatePass $gp)
    {
        $employee = Employee::where('email', $request->user()->email)->firstOrFail();
        abort_unless($gp->badgeID === $employee->badgeID && $gp->isPending(), 403);
        $gp->update(['status' => 'Cancelled']);
        return back()->with('success', 'Gate pass cancelled.');
    }

    public function adminIndex(): Response
    {
        $passes = GatePass::with('employee')
            ->whereNull('date_time_approved')
            ->where('status', '!=', 'Cancelled')
            ->orderByDesc('gatepass_date')
            ->get()
            ->map(fn($g) => [
                'id'                 => $g->id,
                'controlno'          => $g->controlno,
                'empName'            => $g->employee?->empName,
                'gatepass_type'      => $g->gatepass_type,
                'gatepass_date'      => $g->gatepass_date?->format('Y-m-d'),
                'gatepass_timeout'   => $g->gatepass_timeout,
                'gatepass_timein'    => $g->gatepass_timein,
                'purpose'            => $g->purpose,
                'destination'        => $g->destination,
                'gatepass_datefiled' => $g->gatepass_datefiled?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('GatePass/Admin', ['passes' => $passes]);
    }

    public function approve(Request $request, GatePass $gp)
    {
        $request->validate([
            'actual_timeout' => ['nullable', 'date_format:H:i'],
            'actual_timein'  => ['nullable', 'date_format:H:i'],
        ]);

        $gp->update([
            'actual_timeout'      => $request->actual_timeout,
            'actual_timein'       => $request->actual_timein,
            'date_time_approved'  => now(),
            'status'              => 'Approved',
        ]);

        return back()->with('success', 'Gate pass approved.');
    }

    public function cancelAdmin(GatePass $gp)
    {
        $gp->update(['status' => 'Cancelled']);
        return back()->with('success', 'Gate pass cancelled.');
    }

    public function download(GatePass $gp)
    {
        $employee = $gp->employee;
        $html = "<h2>Gate Pass - {$gp->controlno}</h2>"
              . "<p>Name: {$employee?->empName}</p>"
              . "<p>Type: {$gp->gatepass_type}</p>"
              . "<p>Date: {$gp->gatepass_date}</p>"
              . "<p>Time Out: {$gp->gatepass_timeout} | Time In: {$gp->gatepass_timein}</p>"
              . "<p>Purpose: {$gp->purpose}</p>"
              . "<p>Destination: {$gp->destination}</p>";

        $mpdf = new \Mpdf\Mpdf(['format' => 'A4']);
        $mpdf->WriteHTML($html);
        $filename = 'GatePass-' . $gp->controlno . '.pdf';

        return response()->streamDownload(
            fn() => print($mpdf->Output($filename, 'S')),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
