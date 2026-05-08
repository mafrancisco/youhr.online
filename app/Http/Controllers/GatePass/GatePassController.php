<?php

namespace App\Http\Controllers\GatePass;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\GatePass;
use App\Models\Setting;
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
                'gatepass_date'      => $g->gatepass_date,
                'gatepass_timeout'   => $g->gatepass_timeout,
                'gatepass_timein'    => $g->gatepass_timein,
                'purpose'            => $g->purpose,
                'destination'        => $g->destination,
                'date_time_approved' => $g->date_time_approved ?: null,
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
            'controlno'           => $controlno,
            'badgeID'             => $employee->badgeID,
            'gatepass_type'       => $request->gatepass_type,
            'gatepass_date'       => $request->gatepass_date,
            'gatepass_timeout'    => $request->gatepass_timeout ?? '',
            'gatepass_timein'     => $request->gatepass_timein  ?? '',
            'purpose'             => $request->purpose,
            'destination'         => $request->destination ?? '',
            'gatepass_datefiled'  => now()->format('Y-m-d H:i:s'),
            'status'              => 'Pending',
            'date_time_approved'  => '',
            'actual_timeout'      => '',
            'actual_timein'       => '',
            'time_consumed'       => '',
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
            ->where(fn($q) => $q->whereNull('date_time_approved')->orWhere('date_time_approved', ''))
            ->where('status', '!=', 'Cancelled')
            ->orderByDesc('gatepass_date')
            ->get()
            ->map(fn($g) => [
                'id'                 => $g->id,
                'controlno'          => $g->controlno,
                'empName'            => $g->employee?->empName,
                'gatepass_type'      => $g->gatepass_type,
                'gatepass_date'      => $g->gatepass_date,
                'gatepass_timeout'   => $g->gatepass_timeout,
                'gatepass_timein'    => $g->gatepass_timein,
                'purpose'            => $g->purpose,
                'destination'        => $g->destination,
                'gatepass_datefiled' => $g->gatepass_datefiled,
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
            'actual_timeout'      => $request->actual_timeout ?? '',
            'actual_timein'       => $request->actual_timein  ?? '',
            'date_time_approved'  => now()->format('Y-m-d H:i:s'),
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
        $s        = Setting::current();

        $logoHtml = '';
        if ($s->logo_path) {
            $logoPath = $s->logoPublicPath();
            if (file_exists($logoPath)) {
                $logoHtml = '<img src="' . $logoPath . '" height="40px"><br>';
            }
        }
        $signatory = $s->authorized_signatory
            ? '<br><br><table width="100%"><tr>
                <td width="50%" align="center"><b>' . strtoupper($employee?->empName ?? '') . '</b><br><small>Employee</small></td>
                <td width="50%" align="center"><b>' . strtoupper($s->authorized_signatory) . '</b><br><small>' . htmlspecialchars($s->authorized_signatory_position) . '</small></td>
               </tr></table>'
            : '';

        $html = '<div style="text-align:center;font-family:Arial;">'
              . $logoHtml
              . '<b>' . htmlspecialchars($s->system_name) . '</b><br>'
              . ($s->company_address ? '<small>' . htmlspecialchars($s->company_address) . '</small><br>' : '')
              . '</div><br>'
              . '<h3 style="text-align:center;">Gate Pass</h3>'
              . '<p><b>Control No:</b> ' . $gp->controlno . '</p>'
              . '<p><b>Name:</b> ' . ($employee?->empName ?? '') . '</p>'
              . '<p><b>Type:</b> ' . $gp->gatepass_type . '</p>'
              . '<p><b>Date:</b> ' . $gp->gatepass_date . '</p>'
              . '<p><b>Time Out:</b> ' . $gp->gatepass_timeout . ' &nbsp; <b>Time In:</b> ' . $gp->gatepass_timein . '</p>'
              . '<p><b>Purpose:</b> ' . htmlspecialchars($gp->purpose) . '</p>'
              . '<p><b>Destination:</b> ' . htmlspecialchars($gp->destination) . '</p>'
              . '<p><b>Status:</b> ' . $gp->status . '</p>'
              . $signatory;

        $mpdf = new \Mpdf\Mpdf(['format' => 'A4', 'margin_top' => 20, 'margin_bottom' => 20, 'margin_left' => 25, 'margin_right' => 25]);
        $mpdf->WriteHTML($html);
        $filename = 'GatePass-' . $gp->controlno . '.pdf';

        return response()->streamDownload(
            fn() => print($mpdf->Output($filename, 'S')),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
