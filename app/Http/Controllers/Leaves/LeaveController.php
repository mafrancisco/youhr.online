<?php

namespace App\Http\Controllers\Leaves;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Setting;
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
                'date_filed'  => $l->date_filed,
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
            'credits'    => $this->getEmployeeCredits($employee->badgeID),
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

        // Check if employee has leave credit baseline data
        $credit = \App\Models\LeaveCredit::where('badgeID', $employee->badgeID)->first();
        if (!$credit) {
            return back()->withErrors([
                'leave_type' => 'You have no leave credits record. Please contact HR to set up your leave credits before filing.',
            ]);
        }

        // Determine which credit pool to check based on leave type name
        $leaveType = LeaveType::find($request->leave_type);
        $typeName  = strtolower($leaveType->leave_type ?? '');
        $daysRequested = count($request->dates);

        $creditCheck = $this->checkLeaveCredits($typeName, $credit, $daysRequested);
        if ($creditCheck !== true) {
            return back()->withErrors(['leave_type' => $creditCheck]);
        }

        $details = trim(implode(' ', array_filter([
            $request->destination, $request->location, $request->sickleave,
            $request->illness, $request->women_illness,
            $request->studyleave, $request->otherleave,
        ])));

        $controlno = $this->controlNo->generate();

        $sortedDates = $request->dates;
        sort($sortedDates);

        Leave::create([
            'controlno'       => $controlno,
            'badgeID'         => $employee->badgeID,
            'leave_type'      => $request->leave_type,
            'date_start'      => implode(',', $sortedDates),
            'date_end'        => end($sortedDates),
            'leave_details'   => $details,
            'date_filed'      => now()->format('Y-m-d H:i:s'),
            'noofdays'        => count($sortedDates),
            'status'          => 'Pending',
            'credits_vl'      => 0,
            'credits_sl'      => 0,
            'ot_credits'      => 0,
            'service_credits' => 0,
            'dateUpdated'     => '',
        ]);

        return back()->with('success', 'Leave filed. Control No: ' . $controlno);
    }

    /**
     * Check if the employee has sufficient leave credits for the requested leave type.
     *
     * @return true|string True if sufficient, or error message string
     */
    private function checkLeaveCredits(string $typeName, \App\Models\LeaveCredit $credit, int $daysRequested): true|string
    {
        // Map leave type names to credit fields and limits
        // VL and SL use the dynamic balance from lcredits table
        // Others have fixed entitlements per year
        $mapping = [
            'vacation leave'            => ['field' => 'vl',        'available' => (float) $credit->vl],
            'sick leave'                => ['field' => 'sl',        'available' => (float) $credit->sl],
            'maternity leave'           => ['field' => 'maternity', 'available' => (float) $credit->maternity],
            'paternity leave'           => ['field' => 'paternity', 'available' => (float) $credit->paternity],
            'special privilege leave'   => ['field' => 'spl',       'available' => (float) $credit->spl],
            'forced leave'              => ['field' => 'forced',    'available' => (float) $credit->forced],
            'mandatory/forced leave'    => ['field' => 'forced',    'available' => (float) $credit->forced],
            'wellness leave'            => ['field' => 'wellness',  'available' => (float) $credit->wellness],
        ];

        // Find matching credit pool
        $matched = null;
        foreach ($mapping as $key => $config) {
            if (str_contains($typeName, $key) || $key === $typeName) {
                $matched = $config;
                break;
            }
        }

        // If no specific mapping found, allow filing (other leave types like study leave, VAWC, etc.)
        if (!$matched) {
            return true;
        }

        if ($daysRequested > $matched['available']) {
            $label = ucwords($matched['field'] === 'vl' ? 'Vacation Leave' :
                    ($matched['field'] === 'sl' ? 'Sick Leave' :
                    ($matched['field'] === 'spl' ? 'Special Privilege Leave' :
                    ucfirst($matched['field']) . ' Leave')));

            return "Insufficient {$label} credits. Available: {$matched['available']} day(s), Requested: {$daysRequested} day(s).";
        }

        return true;
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
        $mapLeave = function ($l) {
            $credit = \App\Models\LeaveCredit::where('badgeID', $l->badgeID)->first();
            return [
                'id'         => $l->id,
                'controlno'  => $l->controlno,
                'empName'    => $l->employee?->empName,
                'badgeID'    => $l->badgeID,
                'date_filed' => $l->date_filed,
                'type_name'  => $l->type?->full_name,
                'dates'      => $l->date_start,
                'noofdays'   => $l->noofdays,
                'details'    => $l->leave_details,
                'status'     => $l->status,
                'dateUpdated' => $l->dateUpdated,
                'credits'    => $credit ? [
                    'vl'        => (float) $credit->vl,
                    'sl'        => (float) $credit->sl,
                    'maternity' => (float) $credit->maternity,
                    'paternity' => (float) $credit->paternity,
                    'spl'       => (float) $credit->spl,
                    'forced'    => (float) $credit->forced,
                    'wellness'  => (float) $credit->wellness,
                    'ot'        => (float) $credit->ot,
                    'service'   => (float) $credit->service,
                ] : null,
            ];
        };

        $pending = Leave::with(['type', 'employee'])
            ->where('status', 'Pending')
            ->orderByDesc('id')
            ->get()
            ->map($mapLeave);

        $approved = Leave::with(['type', 'employee'])
            ->where('status', 'Approved')
            ->orderByDesc('dateUpdated')
            ->limit(100)
            ->get()
            ->map($mapLeave);

        $declined = Leave::with(['type', 'employee'])
            ->where('status', 'Cancelled')
            ->orderByDesc('dateUpdated')
            ->limit(100)
            ->get()
            ->map($mapLeave);

        return Inertia::render('Leaves/Admin', [
            'pending'  => $pending,
            'approved' => $approved,
            'declined' => $declined,
        ]);
    }

    public function update(Request $request, Leave $leave)
    {
        $request->validate([
            'status'             => ['required', 'in:Pending,Approved,Cancelled'],
            'credits_vl'         => ['nullable', 'numeric', 'min:0'],
            'credits_sl'         => ['nullable', 'numeric', 'min:0'],
            'credits_maternity'  => ['nullable', 'numeric', 'min:0'],
            'credits_paternity'  => ['nullable', 'numeric', 'min:0'],
            'credits_spl'        => ['nullable', 'numeric', 'min:0'],
            'credits_forced'     => ['nullable', 'numeric', 'min:0'],
            'credits_wellness'   => ['nullable', 'numeric', 'min:0'],
            'ot_credits'         => ['nullable', 'numeric', 'min:0'],
            'service_credits'    => ['nullable', 'numeric', 'min:0'],
        ]);

        $leave->update([
            'status'          => $request->status,
            'credits_vl'      => $request->credits_vl ?? 0,
            'credits_sl'      => $request->credits_sl ?? 0,
            'ot_credits'      => $request->ot_credits ?? 0,
            'service_credits' => $request->service_credits ?? 0,
            'dateUpdated'     => now()->toDateString(),
        ]);

        // When approved, deduct from employee's leave credits
        if ($request->status === 'Approved') {
            $this->deductLeaveCredits($leave->badgeID, $request);
            $this->markLeaveInAttendance($leave);
        }

        return back()->with('success', 'Leave updated.');
    }

    /**
     * Deduct the specified credits from the employee's leave credit balance.
     */
    private function deductLeaveCredits(string $badgeID, Request $request): void
    {
        $credit = \App\Models\LeaveCredit::where('badgeID', $badgeID)->first();
        if (!$credit) return;

        $deductions = [];
        if ($request->credits_vl > 0)        $deductions['vl']        = max(0, $credit->vl - $request->credits_vl);
        if ($request->credits_sl > 0)        $deductions['sl']        = max(0, $credit->sl - $request->credits_sl);
        if ($request->credits_maternity > 0) $deductions['maternity'] = max(0, $credit->maternity - $request->credits_maternity);
        if ($request->credits_paternity > 0) $deductions['paternity'] = max(0, $credit->paternity - $request->credits_paternity);
        if ($request->credits_spl > 0)       $deductions['spl']       = max(0, $credit->spl - $request->credits_spl);
        if ($request->credits_forced > 0)    $deductions['forced']    = max(0, $credit->forced - $request->credits_forced);
        if ($request->credits_wellness > 0)  $deductions['wellness']  = max(0, $credit->wellness - $request->credits_wellness);
        if ($request->ot_credits > 0)        $deductions['ot']        = max(0, $credit->ot - $request->ot_credits);
        if ($request->service_credits > 0)   $deductions['service']   = max(0, $credit->service - $request->service_credits);

        if (!empty($deductions)) {
            $deductions['dateupdated'] = now()->toDateString();
            $credit->update($deductions);
        }
    }

    /**
     * Mark attendance_clean records with 'L' for approved leave dates.
     */
    private function markLeaveInAttendance(Leave $leave): void
    {
        $dates = $this->expandLeaveDates($leave->date_start ?? '');

        foreach ($dates as $attDate) {
            \Illuminate\Support\Facades\DB::update("
                UPDATE attendance_clean
                SET StartTime1 = 'L', StartTime2 = 'L', StartTime3 = 'L', StartTime4 = 'L',
                    tardiness = 0, undertime = 0
                WHERE BadgeNumber = ? AND AttDate = ?
            ", [$leave->badgeID, $attDate]);
        }
    }

    /**
     * Expand leave date_start string into individual date entries (MM/DD/YYYY format).
     */
    private function expandLeaveDates(string $leaveString): array
    {
        $dates = [];
        foreach (explode(',', $leaveString) as $p) {
            $p = trim($p);
            // Range format: MM/DD-DD/YYYY
            if (preg_match('/^(\d{2})\/(\d{2})-(\d{2})\/(\d{4})$/', $p, $m)) {
                for ($d = (int) $m[2]; $d <= (int) $m[3]; $d++) {
                    $dates[] = sprintf('%02d/%02d/%04d', $m[1], $d, $m[4]);
                }
            }
            // Single date: MM/DD/YYYY
            elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $p)) {
                $dates[] = $p;
            }
            // Y-m-d format (from new leave filing)
            elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $p, $m)) {
                $dates[] = sprintf('%02d/%02d/%04d', $m[2], $m[3], $m[1]);
            }
        }
        return $dates;
    }

    private function getEmployeeCredits(string $badgeID): ?array
    {
        $credit = \App\Models\LeaveCredit::where('badgeID', $badgeID)->first();
        if (!$credit) return null;

        return [
            'vl'        => (float) $credit->vl,
            'sl'        => (float) $credit->sl,
            'maternity' => (float) $credit->maternity,
            'paternity' => (float) $credit->paternity,
            'spl'       => (float) $credit->spl,
            'forced'    => (float) $credit->forced,
            'wellness'  => (float) $credit->wellness,
            'ot'        => (float) $credit->ot,
            'service'   => (float) $credit->service,
        ];
    }

    public function downloadForm(Leave $leave)
    {
        $employee = Employee::where('badgeID', $leave->badgeID)->firstOrFail();
        $s        = Setting::current();

        $typeId  = (int) $leave->leave_type;
        $details = $leave->leave_details ?? '';

        // Parse comma-separated dates
        $rawDates = array_filter(array_map('trim', explode(',', $leave->date_start)));
        sort($rawDates);
        $dateFrom = $rawDates ? date('m/d/Y', strtotime(reset($rawDates))) : '';
        $dateTo   = $rawDates ? date('m/d/Y', strtotime(end($rawDates))) : '';
        $filedDate = $leave->date_filed ? date('m/d/Y', strtotime($leave->date_filed)) : '';

        $chk = fn(bool $on) => $on ? '&#9745;' : '&#9744;';

        // Leave type labels (CSC standard, 1-indexed)
        $leaveTypes = [
            1  => ['Vacation Leave',                     'Local'],
            2  => ['Vacation Leave',                     'Abroad'],
            3  => ['Mandatory/Forced Leave',             ''],
            4  => ['Sick Leave',                         'In Hospital'],
            5  => ['Sick Leave',                         'Out Patient'],
            6  => ['Special Privilege Leave',            ''],
            7  => ['Solo Parent Leave',                  ''],
            8  => ['Study Leave',                        ''],
            9  => ['10-Day VAWC Leave',                  ''],
            10 => ['Rehabilitation Privilege',           ''],
            11 => ['Special Leave Benefit for Women',    ''],
            12 => ['Special Emergency (Calamity) Leave', ''],
            13 => ['Adoption Leave',                     ''],
            14 => ['Others (please specify)',             ''],
        ];

        $agencyName  = $s->system_name      ?: '';
        $agencyAddr  = $s->company_address  ?: '';
        $signatory   = $s->authorized_signatory          ? strtoupper($s->authorized_signatory) : '';
        $signatoryPos = $s->authorized_signatory_position ?: '';

        // Logo
        $logoHtml = '';
        $logoPath = $s->logoPublicPath();
        if ($logoPath && file_exists($logoPath)) {
            $logoHtml = '<img src="' . $logoPath . '" height="35" style="vertical-align:middle;"> &nbsp;';
        }

        // Build type-of-leave rows (2 per table row)
        $typeRows = '';
        $typeList = array_keys($leaveTypes);
        for ($i = 0; $i < count($typeList); $i += 2) {
            $a   = $typeList[$i];
            $b   = $typeList[$i + 1] ?? null;
            $aLbl = $leaveTypes[$a][0] . ($leaveTypes[$a][1] ? ' (' . $leaveTypes[$a][1] . ')' : '');
            $bLbl = $b ? $leaveTypes[$b][0] . ($leaveTypes[$b][1] ? ' (' . $leaveTypes[$b][1] . ')' : '') : '';
            $typeRows .= '<tr>
                <td width="50%">' . $chk($typeId === $a) . ' ' . htmlspecialchars($aLbl) . '</td>
                <td width="50%">' . ($b ? $chk($typeId === $b) . ' ' . htmlspecialchars($bLbl) : '') . '</td>
            </tr>';
        }

        // Details sub-section label based on type group
        if (in_array($typeId, [1, 2, 6])) {
            $detailsLabel = 'In case of Vacation/Special Privilege Leave (Specify destination):';
        } elseif (in_array($typeId, [4, 5])) {
            $detailsLabel = 'In case of Sick Leave (Specify illness):';
        } elseif ($typeId === 8) {
            $detailsLabel = 'In case of Study Leave (Specify):';
        } elseif ($typeId === 11) {
            $detailsLabel = 'In case of Special Leave Benefit for Women (Specify illness/surgery):';
        } else {
            $detailsLabel = 'Other purpose/details:';
        }

        $creditsVL  = $leave->credits_vl      ?? '—';
        $creditsSL  = $leave->credits_sl      ?? '—';
        $creditsOT  = $leave->ot_credits      ?? '—';
        $creditsSvc = $leave->service_credits ?? '—';
        $asOf       = $leave->dateUpdated     ?: $filedDate;

        $html = '
<style>
    body        { font-family: Arial; font-size: 8pt; margin: 0; }
    table       { border-collapse: collapse; width: 100%; }
    td, th      { border: 0.5px solid #000; padding: 2px 4px; vertical-align: top; }
    .nb td      { border: none; }
    .sec        { background: #c0c0c0; font-weight: bold; font-size: 8pt; padding: 2px 4px; }
    .center     { text-align: center; }
    .bold       { font-weight: bold; }
    .small      { font-size: 7pt; }
    .sig-line   { border-top: 0.5px solid #000; margin-top: 18px; }
</style>

<!-- HEADER -->
<table>
    <tr>
        <td colspan="4" class="center" style="border:none; padding-bottom:2px;">
            ' . $logoHtml . '<b>' . htmlspecialchars($agencyName) . '</b>'
            . ($agencyAddr ? '<br><span class="small">' . htmlspecialchars($agencyAddr) . '</span>' : '') . '
        </td>
    </tr>
    <tr>
        <td colspan="4" class="center bold" style="font-size:10pt; border:none; padding:3px 0;">
            APPLICATION FOR LEAVE
        </td>
    </tr>
    <tr>
        <td colspan="4" class="center small" style="border:none; padding-bottom:4px;">
            CSC Form No. 6 &nbsp;|&nbsp; Revised 2020
        </td>
    </tr>
</table>

<table>
    <tr>
        <td width="40%"><span class="small">1. OFFICE/DEPARTMENT</span><br>
            <b>' . htmlspecialchars($agencyName) . '</b></td>
        <td width="20%"><span class="small">DATE OF FILING</span><br>
            <b>' . $filedDate . '</b></td>
        <td width="20%"><span class="small">CONTROL NO.</span><br>
            <b>' . htmlspecialchars($leave->controlno) . '</b></td>
        <td width="20%"><span class="small">STATUS</span><br>
            <b>' . htmlspecialchars($leave->status) . '</b></td>
    </tr>
    <tr>
        <td><span class="small">2. NAME (Last, First, Middle)</span><br>
            <b>' . htmlspecialchars(strtoupper($employee->empName)) . '</b></td>
        <td colspan="2"><span class="small">3. DATE OF BIRTH</span><br>&nbsp;</td>
        <td><span class="small">SALARY</span><br>&nbsp;</td>
    </tr>
    <tr>
        <td><span class="small">4. POSITION/DESIGNATION</span><br>
            <b>' . htmlspecialchars($employee->empDesig ?? '') . '</b></td>
        <td colspan="3"><span class="small">5. OFFICE</span><br>
            <b>' . htmlspecialchars($employee->unit ?? '') . '</b></td>
    </tr>
</table>

<br>
<!-- SECTION I: DETAILS OF APPLICATION -->
<table>
    <tr><td colspan="2" class="sec">I. &nbsp;DETAILS OF APPLICATION</td></tr>

    <tr><td colspan="2" style="padding:1px 4px;"><b>6.A. TYPE OF LEAVE TO BE AVAILED OF</b></td></tr>
    ' . $typeRows . '

    <tr>
        <td colspan="2">
            <b>6.B. DETAILS OF LEAVE</b><br>
            <span class="small">' . htmlspecialchars($detailsLabel) . '</span><br>
            ' . htmlspecialchars($details) . '
        </td>
    </tr>

    <tr>
        <td width="50%">
            <b>6.C. NUMBER OF WORKING DAYS APPLIED FOR</b><br>
            <b style="font-size:11pt;">' . htmlspecialchars((string)$leave->noofdays) . ' day(s)</b>
        </td>
        <td width="50%">
            <b>INCLUSIVE DATES</b><br>
            From: <b>' . $dateFrom . '</b> &nbsp; To: <b>' . $dateTo . '</b>
        </td>
    </tr>

    <tr>
        <td colspan="2">
            <b>6.D. COMMUTATION</b><br>
            ' . $chk(false) . ' Requested &nbsp;&nbsp; ' . $chk(false) . ' Not Requested
        </td>
    </tr>

    <tr>
        <td colspan="2" class="center" style="padding-top: 6px; padding-bottom: 6px;">
            <div class="sig-line" style="width:60%; margin: 0 auto;"></div>
            <b>' . htmlspecialchars(strtoupper($employee->empName)) . '</b><br>
            <span class="small">Signature of Applicant</span>
        </td>
    </tr>
</table>

<br>
<!-- SECTION II: CERTIFICATION -->
<table>
    <tr><td colspan="2" class="sec">II. &nbsp;DETAILS OF ACTION ON APPLICATION</td></tr>

    <tr>
        <td width="50%">
            <b>7.A. CERTIFICATION OF LEAVE CREDITS</b>
            <table style="border:none; margin-top:2px;">
                <tr>
                    <td style="border:none;" class="small">As of:</td>
                    <td style="border:none;"><b>' . htmlspecialchars($asOf) . '</b></td>
                </tr>
                <tr>
                    <td style="border:none;">Vacation Leave</td>
                    <td style="border:none;"><b>' . htmlspecialchars((string)$creditsVL) . '</b></td>
                </tr>
                <tr>
                    <td style="border:none;">Sick Leave</td>
                    <td style="border:none;"><b>' . htmlspecialchars((string)$creditsSL) . '</b></td>
                </tr>
                <tr>
                    <td style="border:none;">OT Credits</td>
                    <td style="border:none;"><b>' . htmlspecialchars((string)$creditsOT) . '</b></td>
                </tr>
                <tr>
                    <td style="border:none;">Service Credits</td>
                    <td style="border:none;"><b>' . htmlspecialchars((string)$creditsSvc) . '</b></td>
                </tr>
            </table>
            <br>
            <div class="sig-line"></div>
            <span class="center" style="display:block; text-align:center;">
                <b>' . htmlspecialchars($signatory) . '</b><br>
                <span class="small">' . htmlspecialchars($signatoryPos) . '</span><br>
                <span class="small">HR / Personnel Officer</span>
            </span>
        </td>
        <td width="50%">
            <b>7.B. RECOMMENDATION</b><br>
            ' . $chk(false) . ' Approval<br>
            ' . $chk(false) . ' Disapproval due to:<br>
            &nbsp;&nbsp;&nbsp;&nbsp;' . $chk(false) . ' Exigency of the service<br>
            &nbsp;&nbsp;&nbsp;&nbsp;' . $chk(false) . ' Needed services not available<br>
            &nbsp;&nbsp;&nbsp;&nbsp;' . $chk(false) . ' Negative leave balance<br>
            &nbsp;&nbsp;&nbsp;&nbsp;' . $chk(false) . ' Others (specify):<br>
            <br>
            <div class="sig-line"></div>
            <span class="center" style="display:block; text-align:center;">
                <b>' . htmlspecialchars($signatory) . '</b><br>
                <span class="small">' . htmlspecialchars($signatoryPos) . '</span><br>
                <span class="small">Authorized Official</span>
            </span>
        </td>
    </tr>

    <tr>
        <td width="50%">
            <b>7.C. APPROVED FOR</b><br>
            ' . $chk(false) . ' days with pay<br>
            ' . $chk(false) . ' days without pay<br>
            ' . $chk(false) . ' others (specify):<br><br>
        </td>
        <td width="50%">
            <b>7.D. DISAPPROVED DUE TO</b><br><br><br>
        </td>
    </tr>

    <tr>
        <td colspan="2" class="center" style="padding-top:4px; padding-bottom:4px;">
            <div class="sig-line" style="width:60%; margin: 0 auto;"></div>
            <b>' . htmlspecialchars($signatory) . '</b><br>
            <span class="small">' . htmlspecialchars($signatoryPos) . '</span><br>
            <span class="small">Authorized Official</span>
        </td>
    </tr>
</table>

<br>
<span class="small">Date &amp; Time Printed: ' . date('m/d/Y H:i:s') . '</span>';

        $mpdf = new \Mpdf\Mpdf([
            'format'        => 'A4',
            'margin_top'    => 5,
            'margin_bottom' => 5,
            'margin_left'   => 5,
            'margin_right'  => 5,
            'default_font'  => 'arial',
        ]);
        $mpdf->simpleTables  = true;
        $mpdf->packTableData = true;
        $mpdf->WriteHTML($html);

        $filename = 'Leave-' . $leave->controlno . '.pdf';
        return response()->streamDownload(
            fn() => print($mpdf->Output($filename, 'S')),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
