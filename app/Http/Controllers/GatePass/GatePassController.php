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
            'gatepass_date'    => ['required', 'date', 'after_or_equal:today'],
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
        $mapPass = fn($g) => [
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
            'date_time_approved' => $g->date_time_approved,
            'status'             => $g->status,
        ];

        $pending = GatePass::with('employee')
            ->where(fn($q) => $q->whereNull('date_time_approved')->orWhere('date_time_approved', ''))
            ->where('status', '!=', 'Cancelled')
            ->orderByDesc('gatepass_date')
            ->get()
            ->map($mapPass);

        $approved = GatePass::with('employee')
            ->where('status', 'Approved')
            ->where('date_time_approved', '!=', '')
            ->orderByDesc('date_time_approved')
            ->limit(100)
            ->get()
            ->map($mapPass);

        $declined = GatePass::with('employee')
            ->where('status', 'Cancelled')
            ->orderByDesc('gatepass_date')
            ->limit(100)
            ->get()
            ->map($mapPass);

        return Inertia::render('GatePass/Admin', [
            'pending'  => $pending,
            'approved' => $approved,
            'declined' => $declined,
        ]);
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
        // Ownership check: HR can download any, employees only their own
        $user = request()->user();
        if (!$user->isHR()) {
            $employee = Employee::where('email', $user->email)->firstOrFail();
            abort_unless($gp->badgeID === $employee->badgeID, 403);
        }

        $employee = $gp->employee;
        $s        = Setting::current();

        $chk = fn(bool $on) => $on ? '&#9745;' : '&#9744;';

        $systemName = htmlspecialchars($s->system_name ?: 'DTR System');
        $address    = htmlspecialchars($s->company_address ?: '');
        $signatory  = $s->authorized_signatory ? strtoupper($s->authorized_signatory) : '';
        $sigPos     = htmlspecialchars($s->authorized_signatory_position ?: '');

        $empName  = strtoupper($employee?->empName ?? '');
        $empDesig = htmlspecialchars($employee?->empDesig ?? '');

        $logoHtml = '';
        if ($s->logo_path) {
            $logoPath = $s->logoPublicPath();
            if ($logoPath && file_exists($logoPath)) {
                $logoHtml = '<img src="' . $logoPath . '" height="35" style="vertical-align:middle;"> &nbsp;';
            }
        }

        $html = '
<style>
    body { font-family: Arial, sans-serif; font-size: 9pt; }
    table { border-collapse: collapse; width: 100%; }
    .bordered td, .bordered th { border: 0.5px solid #000; padding: 4px 6px; }
    .nb td { border: none; padding: 2px 4px; }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .small { font-size: 7.5pt; }
    .sig-line { border-bottom: 1px solid #000; width: 60%; margin: 0 auto; margin-top: 30px; }
</style>

<!-- HEADER -->
<table class="nb">
    <tr><td class="center bold" style="font-size: 11pt;">
        ' . $logoHtml . $systemName . '
    </td></tr>
    ' . ($address ? '<tr><td class="center small">' . $address . '</td></tr>' : '') . '
    <tr><td class="center bold" style="font-size: 10pt; padding-top: 8px;">
        ASSIGNMENT SLIP / EMPLOYEE GATE PASS
    </td></tr>
</table>

<br>

<!-- DETAILS TABLE -->
<table class="bordered">
    <tr>
        <td width="65%">&nbsp;</td>
        <td width="15%" class="bold">CONTROL NO:</td>
        <td width="20%">' . htmlspecialchars($gp->controlno) . '</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td class="bold">DATE:</td>
        <td>' . htmlspecialchars($gp->gatepass_date) . '</td>
    </tr>
</table>

<br>

<table class="bordered">
    <tr>
        <td width="20%" class="bold">NAME:</td>
        <td colspan="3">' . $empName . '</td>
    </tr>
    <tr>
        <td class="bold">POSITION:</td>
        <td colspan="3">' . $empDesig . '</td>
    </tr>
    <tr>
        <td class="bold">TIME OUT:</td>
        <td width="30%">' . htmlspecialchars($gp->gatepass_timeout) . '</td>
        <td width="15%" class="bold">TIME IN:</td>
        <td width="35%">' . htmlspecialchars($gp->gatepass_timein) . '</td>
    </tr>
    <tr>
        <td class="bold">DESTINATION:</td>
        <td colspan="3">' . htmlspecialchars($gp->destination) . '</td>
    </tr>
    <tr>
        <td class="bold">PURPOSE:</td>
        <td colspan="3">' . htmlspecialchars($gp->purpose) . '</td>
    </tr>
</table>

<br>

<!-- TYPE CHECKBOXES -->
<table class="nb">
    <tr><td>&nbsp;&nbsp;&nbsp;' . $chk($gp->gatepass_type === 'Official Business') . ' &nbsp;Official Business</td></tr>
    <tr><td>&nbsp;&nbsp;&nbsp;' . $chk($gp->gatepass_type === 'Official Time') . ' &nbsp;Official Time</td></tr>
    <tr><td>&nbsp;&nbsp;&nbsp;' . $chk($gp->gatepass_type === 'Personal') . ' &nbsp;Personal</td></tr>
</table>

<br>

<!-- RECOMMENDING APPROVAL -->
<table class="nb">
    <tr><td>Recommending Approval:</td></tr>
    <tr><td><br><br></td></tr>
    <tr><td><b><u>' . $signatory . '</u></b></td></tr>
    <tr><td class="small">' . $sigPos . '</td></tr>
</table>

<br>

<!-- APPROVED -->
<table class="nb">
    <tr><td>Approved:</td></tr>
    <tr><td><br><br></td></tr>
    <tr><td><b><u>' . $signatory . '</u></b></td></tr>
    <tr><td class="small">' . $sigPos . '</td></tr>
</table>

<br>
<hr>

<!-- CERTIFICATE OF APPEARANCE (2 columns) -->
<table width="100%">
    <tr valign="top">
        <td width="50%" style="padding-right: 10px;">
            ' . $this->certificateOfAppearance() . '
        </td>
        <td width="50%" style="padding-left: 10px;">
            ' . $this->certificateOfAppearance() . '
        </td>
    </tr>
</table>

<br>
<table class="nb">
    <tr><td class="small">PSHS-00-F-HRU-16-Ver02-Rev1-10/18/20</td></tr>
</table>';

        $mpdf = new \Mpdf\Mpdf([
            'format'        => 'A4',
            'margin_top'    => 15,
            'margin_bottom' => 10,
            'margin_left'   => 20,
            'margin_right'  => 20,
            'default_font'  => 'arial',
        ]);
        $mpdf->WriteHTML($html);
        $filename = 'GatePass-' . $gp->controlno . '.pdf';

        return response()->streamDownload(
            fn() => print($mpdf->Output($filename, 'S')),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    private function certificateOfAppearance(): string
    {
        return '
        <table style="border-collapse:collapse; width:100%;">
            <tr><td style="border:none; text-align:center; font-weight:bold; font-size:9pt; padding-bottom:6px;">
                CERTIFICATE OF APPEARANCE
            </td></tr>
            <tr><td style="border:none; font-size:8pt; padding-bottom:4px;">
                This is to certify that I attended to Mr./ Ms. _______________,
                of PSH-CRC on _____ at _____ a.m./p.m.
            </td></tr>
            <tr><td style="border:none; font-size:8pt; padding-bottom:12px;">
                when he/she transacted business with my Agency/ Company.
            </td></tr>
            <tr><td style="border:none; padding-top:20px; border-bottom:0.5px solid #000; width:80%; font-size:8pt;">
                &nbsp;
            </td></tr>
            <tr><td style="border:none; text-align:center; font-size:7pt;">
                Signature over Printed Name<br>of Attending Employee/ Position
            </td></tr>
            <tr><td style="border:none; font-size:8pt; padding-top:8px;">
                Date ____________<br>
                Name of Agency/ies: ____________<br>
                Address: ____________<br>
                Tel.No.: ____________
            </td></tr>
            <tr><td style="border:none; font-size:7pt; padding-top:6px; font-style:italic;">
                In case an employee buys office supplies, said employee shall attach an
                authenticated copy of OR of purchase.
            </td></tr>
        </table>';
    }
}
