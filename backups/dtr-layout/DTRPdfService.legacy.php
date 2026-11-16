<?php

namespace App\Services;

use App\Models\EmployeeStatus;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;

class DTRPdfService
{
    private function settings(): Setting
    {
        return Setting::current();
    }

    private function makeMpdf(): Mpdf
    {
        $mpdf = new Mpdf([
            'format'         => 'A4',
            'margin_top'     => 12.7,
            'margin_bottom'  => 12.7,
            'margin_left'    => 12.7,
            'margin_right'   => 12.7,
            'default_font'   => 'arial',
            'tempDir'        => storage_path('app/mpdf-tmp'),
        ]);
        $mpdf->SetAutoPageBreak(false);
        return $mpdf;
    }

    // -----------------------------------------------------------------------
    // Bulk PDF — 2 employees per page (mirrors download.php)
    // -----------------------------------------------------------------------
    /**
     * Render one PDF covering multiple employees, two per page.
     *
     * Passing $badgeIDs restricts output to those employees; otherwise every
     * employee with the given status is included.
     */
    public function bulk(string $startDate, string $endDate, int $empStatus, array $badgeIDs = []): string
    {
        $mpdf = $this->makeMpdf();

        $start_date = date('F j', strtotime($startDate));
        $end_date   = date('F j, Y', strtotime($endDate));
        $attRange   = $start_date . '-' . $end_date;

        $fname    = EmployeeStatus::find($empStatus)?->description ?? 'Employees';
        $filename = $fname . ' DTR-' . $attRange . '.pdf';

        if (!empty($badgeIDs)) {
            $placeholders = implode(',', array_fill(0, count($badgeIDs), '?'));

            $employees = DB::select(
                "SELECT badgeID, empName, empHead FROM employees WHERE empStatus = ? AND badgeID IN ({$placeholders}) ORDER BY empName",
                array_merge([$empStatus], array_values($badgeIDs))
            );
        } else {
            $employees = DB::select(
                "SELECT badgeID, empName, empHead FROM employees WHERE empStatus = ? ORDER BY empName",
                [$empStatus]
            );
        }

        $counter  = 0;
        $pageHtml = '<table width="100%"><tr valign="top">';

        foreach ($employees as $emp) {
            $cnt = DB::selectOne("
                SELECT COUNT(*) AS cnt FROM attendance_clean
                WHERE BadgeNumber = ?
                  AND DATE_FORMAT(STR_TO_DATE(AttDate, '%m/%d/%Y'), '%Y-%m-%d') BETWEEN ? AND ?
            ", [$emp->badgeID, $startDate, $endDate])->cnt;

            if ($cnt == 0) continue;

            $head = DB::selectOne(
                "SELECT headname, headposition FROM heads WHERE headname = ?",
                [$emp->empHead]
            );

            $sub = DB::selectOne(
                "SELECT date_submitted, time_submitted FROM submissions WHERE badgeID = ? AND attRange = ?",
                [$emp->badgeID, $attRange]
            );

            $html = $this->buildDTR($emp->badgeID, $emp->empName, $head, $attRange, $fname, $startDate, $endDate, $sub);

            $pageHtml .= '<td width="50%" style="padding:5px;">' . $html . '</td>';
            $counter++;

            if ($counter % 2 === 0) {
                $pageHtml .= '</tr></table>';
                $mpdf->AddPage();
                $mpdf->WriteHTML($pageHtml);
                $pageHtml = '<table width="100%"><tr valign="top">';
            }
        }

        if ($counter % 2 !== 0) {
            $pageHtml .= '<td width="50%"></td></tr></table>';
            $mpdf->AddPage();
            $mpdf->WriteHTML($pageHtml);
        }

        return $mpdf->Output($filename, 'S');
    }

    // -----------------------------------------------------------------------
    // Individual PDF (mirrors download1.php)
    // -----------------------------------------------------------------------
    public function individual(string $badgeID, string $empName, ?object $head, string $attRange, string $startDate, string $endDate, ?object $sub): string
    {
        $mpdf = $this->makeMpdf();
        $html = $this->buildDTR($badgeID, $empName, $head, $attRange, '', $startDate, $endDate, $sub);
        $mpdf->WriteHTML($html);
        return $mpdf->Output($empName . ' DTR.pdf', 'S');
    }

    // -----------------------------------------------------------------------
    // Build a single employee DTR block
    // -----------------------------------------------------------------------
    private function buildDTR(string $badgeID, string $empName, ?object $head, string $attRange, string $fname, string $startDate, string $endDate, ?object $sub): string
    {
        $html  = $this->styles();
        $html .= $this->headerSection($empName, $attRange);
        $html .= $this->buildTable($badgeID, $startDate, $endDate);
        $html .= $this->footerSection($empName, $head, $sub);
        return $html;
    }

    private function styles(): string
    {
        return '
        <style>
            body { font-family: Arial; font-size: 8pt; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 0.5px solid #000; padding: 2px; text-align: center; font-size: 7.5pt; }
            th { background-color: #f0f0f0; font-weight: bold; }
            .noborder td { border: none; font-size: 8pt; }
        </style>';
    }

    private function headerSection(string $empName, string $attRange): string
    {
        $s        = $this->settings();
        $logoPath = $s->logoPublicPath();
        $logoHtml = $logoPath && file_exists($logoPath)
            ? '<img src="' . $logoPath . '" height="30px" style="vertical-align:middle;">'
            : '';

        $systemName = htmlspecialchars($s->system_name ?: 'Daily Time Record System');
        $address    = htmlspecialchars($s->company_address ?: '');
        $addrLine   = $address
            ? '<tr><td align="center" style="font-size:7pt;">' . $address . '</td></tr>'
            : '';

        return '
        <table class="noborder">
            <tr><td align="center">' . $logoHtml . '</td></tr>
            <tr><td align="center"><b>' . $systemName . '</b></td></tr>
            ' . $addrLine . '
            <tr><td align="center"><b>Daily Time Record</b></td></tr>
            <tr><td align="center"><b>' . strtoupper($empName) . '</b></td></tr>
            <tr><td align="center" style="font-size:7pt;">For the period: ' . $attRange . '</td></tr>
        </table>
        <br>
        <table>
            <tr>
                <th rowspan="2">Day</th>
                <th colspan="2">AM</th>
                <th colspan="2">PM</th>
                <th colspan="2">OverTime</th>
                <th colspan="2">Tardy/<br>UnderTime</th>
                <th rowspan="2">Over<br>Time</th>
                <th rowspan="2">Remarks</th>
            </tr>
            <tr>
                <td>In</td><td>Out</td>
                <td>In</td><td>Out</td>
                <td>In</td><td>Out</td>
                <td>Hr</td><td>Min</td>
            </tr>';
    }

    private function buildTable(string $badgeID, string $startDate, string $endDate): string
    {
        // Get existing attendance records keyed by AttDate
        $rawRows = DB::select("
            SELECT id, AttDate, StartTime1, StartTime2, StartTime3, StartTime4,
                   Tardiness, undertime, OTIn, OTOut, OT, remarks
            FROM attendance_clean
            WHERE BadgeNumber = ?
              AND DATE_FORMAT(STR_TO_DATE(AttDate, '%m/%d/%Y'), '%Y-%m-%d') BETWEEN ? AND ?
            ORDER BY STR_TO_DATE(AttDate, '%m/%d/%Y') ASC
        ", [$badgeID, $startDate, $endDate]);

        // Key records by AttDate for lookup
        $recordsByDate = [];
        foreach ($rawRows as $r) {
            $recordsByDate[$r->AttDate] = $r;
        }

        // Generate all dates in range
        $start = \Carbon\Carbon::parse($startDate);
        $end   = \Carbon\Carbon::parse($endDate);
        $rows  = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $attDate = $date->format('m/d/Y');
            if (isset($recordsByDate[$attDate])) {
                $rows[] = $recordsByDate[$attDate];
            } else {
                // Create a blank row for dates without records
                $rows[] = (object) [
                    'id'         => null,
                    'AttDate'    => $attDate,
                    'StartTime1' => '',
                    'StartTime2' => '',
                    'StartTime3' => '',
                    'StartTime4' => '',
                    'Tardiness'  => 0,
                    'undertime'  => 0,
                    'OTIn'       => '',
                    'OTOut'      => '',
                    'OT'         => 0,
                    'remarks'    => '',
                ];
            }
        }

        // Pre-load approved leave dates for this employee
        $leaveDates = [];
        $leaveRows = DB::select(
            "SELECT date_start FROM leaves WHERE badgeID = ? AND status = 'Approved'",
            [$badgeID]
        );
        foreach ($leaveRows as $lr) {
            foreach ($this->expandLeaveDates($lr->date_start ?? '') as $ld) {
                $leaveDates[] = $ld;
            }
        }
        $leaveDates = array_unique($leaveDates);

        // Pre-load approved gate passes for this employee in range
        $gatePassesByDate = [];
        $gpRows = DB::select("
            SELECT gatepass_type, gatepass_date, gatepass_timeout, gatepass_timein,
                   actual_timeout, actual_timein
            FROM gatepass
            WHERE badgeID = ?
              AND date_time_approved != ''
              AND date_time_approved IS NOT NULL
              AND gatepass_date BETWEEN ? AND ?
        ", [$badgeID, $startDate, $endDate]);
        foreach ($gpRows as $gp) {
            // Convert gatepass_date (Y-m-d) to MM/DD/YYYY to match AttDate format
            $dateParts = explode('-', $gp->gatepass_date);
            if (count($dateParts) === 3) {
                $attDateKey = $dateParts[1] . '/' . $dateParts[2] . '/' . $dateParts[0];
            } else {
                $attDateKey = $gp->gatepass_date;
            }
            $gatePassesByDate[$attDateKey][] = $gp;
        }

        $html    = '';
        $totlate = 0;
        $totOT   = 0;
        $totwork = 0;

        foreach ($rows as $row) {
            // Get correction flags from request table
            $req = DB::selectOne(
                "SELECT log1, log2, log3, log4 FROM request WHERE attID = ?",
                [$row->id]
            );

            $colors = [
                ($req && $req->log1 == '1') ? 'red' : 'black',
                ($req && $req->log2 == '1') ? 'red' : 'black',
                ($req && $req->log3 == '1') ? 'red' : 'black',
                ($req && $req->log4 == '1') ? 'red' : 'black',
            ];

            [$m2, $d2, $y2] = explode('/', $row->AttDate);
            $dateStr    = "$y2-$m2-$d2";
            $dayDisplay = date('M d', strtotime($dateStr));
            $dayName    = strtolower(date('l', strtotime($dateStr)));

            // Check holiday
            $holiday = DB::selectOne(
                "SELECT description FROM dateparameters WHERE actualDate = ? AND type LIKE '%Holiday%' LIMIT 1",
                [$dateStr]
            );

            $noLogs = empty($row->StartTime1) && empty($row->StartTime2)
                   && empty($row->StartTime3) && empty($row->StartTime4);

            if ($holiday && $noLogs) {
                $html .= '<tr><td>' . $dayDisplay . '</td>'
                       . '<td colspan="9" align="center">' . $holiday->description . '</td>'
                       . '<td></td></tr>';
                continue;
            }

            // Check if employee is on approved leave for this date
            $isOnLeave = in_array($row->AttDate, $leaveDates) || $row->StartTime1 === 'L';
            if ($isOnLeave && $row->StartTime1 !== 'L') {
                // Force leave markers if not already set
                $row->StartTime1 = 'L';
                $row->StartTime2 = 'L';
                $row->StartTime3 = 'L';
                $row->StartTime4 = 'L';
                $row->Tardiness  = 0;
                $row->undertime  = 0;
            }

            $specialLabels = ['A', 'L', 'T', 'OB', 'AWA'];
            $times = [$row->StartTime1, $row->StartTime2, $row->StartTime3, $row->StartTime4];
            foreach ($times as $i => $t) {
                if ($t && !in_array($t, $specialLabels) && strtotime($t . ':00') !== false) {
                    $times[$i] = date('h:i A', strtotime($t));
                }
            }

            if ($dayName === 'saturday' || $dayName === 'sunday') {
                foreach ($times as $i => $t) {
                    if (empty($t)) $times[$i] = strtoupper($dayName);
                }
            }

            // Count work days
            // 4 slots filled          → 1 full day
            // 2–3 slots filled        → 0.5 day
            // 0–1 slot filled         → absent (0)
            $filledLogs = 0;
            foreach ([$row->StartTime1, $row->StartTime2, $row->StartTime3, $row->StartTime4] as $log) {
                if (!empty($log) && !in_array($log, ['Saturday', 'Sunday', 'L', 'OB'])) $filledLogs++;
            }
            if ($filledLogs === 4) {
                $totwork += 1;
            } elseif ($filledLogs >= 2) {
                $totwork += 0.5;
            }

            $late    = (int) $row->Tardiness + (int) $row->undertime;
            $totlate += $late;
            $totOT   += (int) $row->OT;
            $hr      = (int) floor($late / 60);
            $min     = $late % 60;
            $remarks = $row->remarks ?? '';

            // Append gate pass info to remarks
            $dayGatePasses = $gatePassesByDate[$row->AttDate] ?? [];
            if (!empty($dayGatePasses)) {
                $gpLabels = [];
                foreach ($dayGatePasses as $gp) {
                    $gpTime = trim($gp->actual_timeout ?: $gp->gatepass_timeout ?? '');
                    $gpIn   = trim($gp->actual_timein ?: $gp->gatepass_timein ?? '');
                    $typeAbbr = match ($gp->gatepass_type) {
                        'Official Business' => 'OB',
                        'Official Time'     => 'OT-GP',
                        'Personal'          => 'P-GP',
                        default             => 'GP',
                    };
                    $gpLabels[] = $typeAbbr . ($gpTime && $gpIn ? " {$gpTime}-{$gpIn}" : '');
                }
                $gpStr = implode('; ', $gpLabels);
                $remarks = $remarks ? "{$remarks} | {$gpStr}" : $gpStr;
            }

            if ((int) $row->OT > 0) {
                $indiOThr  = (int) floor($row->OT / 60);
                $indiOTmin = $row->OT % 60;
                $otcom     = $indiOThr . ' hr(s) ' . $indiOTmin . ' min(s)';
            } else {
                $otcom = '';
            }

            // Gate pass: show type abbreviation in RED in AM Out / PM In columns if blank
            if (!empty($dayGatePasses)) {
                foreach ($dayGatePasses as $gp) {
                    $typeAbbr = match ($gp->gatepass_type) {
                        'Official Business' => 'OB',
                        'Official Time'     => 'OT-GP',
                        'Personal'          => 'P-GP',
                        default             => 'GP',
                    };

                    // If AM Out (times[1]) is blank or not a real time, show GP label in red
                    if (empty($times[1]) || !preg_match('/\d{1,2}:\d{2}/', $times[1])) {
                        $times[1] = '<font color="red">' . $typeAbbr . '</font>';
                    }
                    // If PM In (times[2]) is blank or not a real time, show GP label in red
                    if (empty($times[2]) || !preg_match('/\d{1,2}:\d{2}/', $times[2])) {
                        $times[2] = '<font color="red">' . $typeAbbr . '</font>';
                    }
                }
            }

            // Render times[1] and times[2] — they may already contain <font> tags from GP
            $col1Html = str_contains($times[1], '<font') ? $times[1] : '<font color="' . $colors[1] . '">' . $times[1] . '</font>';
            $col2Html = str_contains($times[2], '<font') ? $times[2] : '<font color="' . $colors[2] . '">' . $times[2] . '</font>';

            $html .= '<tr>
                <td>' . $dayDisplay . '</td>
                <td><font color="' . $colors[0] . '">' . $times[0] . '</font></td>
                <td>' . $col1Html . '</td>
                <td>' . $col2Html . '</td>
                <td><font color="' . $colors[3] . '">' . $times[3] . '</font></td>
                <td>' . ($row->OTIn ?? '') . '</td>
                <td>' . ($row->OTOut ?? '') . '</td>
                <td>' . $hr . '</td>
                <td>' . $min . '</td>
                <td><font size="1">' . $otcom . '</font></td>
                <td><font size="1">' . $remarks . '</font></td>
            </tr>';
        }

        $tothr   = (int) floor($totlate / 60);
        $totmin  = $totlate % 60;
        $days    = (int) floor($tothr / 8);
        $hours   = $tothr % 8;

        $OThr    = (int) floor($totOT / 60);
        $OTmin   = $totOT % 60;
        $OTdays  = (int) floor($OThr / 8);
        $OThours = $OThr % 8;

        $html .= '<tr>
            <td colspan="7" align="right"><b>TOTAL ABSENCES/TARDINESS</b></td>
            <td colspan="4"><b>' . $days . ' day(s) ' . $hours . ' hr(s) ' . $totmin . ' min(s)</b></td>
        </tr>
        <tr>
            <td colspan="7" align="right"><b>TOTAL NO. DAY(S) WORKED + OVERTIME</b></td>
            <td colspan="3"><b>' . $totwork . ' day(s) </b></td>
            <td><b>' . $OTdays . ' day(s) ' . $OThours . ' hr(s) ' . $OTmin . ' min(s)</b></td>
        </tr>
        </table>';

        return $html;
    }

    private function footerSection(string $empName, ?object $head, ?object $sub): string
    {
        $s        = $this->settings();
        $headName = $head ? strtoupper($head->headname) : '';
        $headPos  = $head ? $head->headposition : '';

        // Fall back to configured authorized signatory if no dept head
        if (!$headName && $s->authorized_signatory) {
            $headName = strtoupper($s->authorized_signatory);
            $headPos  = $s->authorized_signatory_position;
        }

        // Second signatory row — only show if different from dept head
        $signatoryRow = '';
        if ($s->authorized_signatory && strtoupper($s->authorized_signatory) !== $headName) {
            $signatoryRow = '
            <tr><td><br><hr></td></tr>
            <tr><td align="center"><b>' . strtoupper($s->authorized_signatory) . '</b>
                <br><font size="1">' . htmlspecialchars($s->authorized_signatory_position) . '</font></td></tr>';
        }

        $subLine = $sub
            ? 'Date &amp; Time Submitted: ' . $sub->date_submitted . ' ' . $sub->time_submitted . '<br>'
            : '';

        return '
        <br>
        <table class="noborder">
            <tr><td><font size="1">Legend: [T]-Travel [L]-Leave [A]-Absent [OB]-Official Business</font></td></tr>
            <tr><td><hr></td></tr>
            <tr><td><font size="1">I CERTIFY on my honor that above is a true and correct report of the hours of work performed, record of which was made daily at the time of arrival and departure from office.</font><br><br></td></tr>
            <tr><td align="center"><b>' . strtoupper($empName) . '</b><br><font size="1">Employee</font></td></tr>
            <tr><td><br><hr></td></tr>
            <tr><td align="center"><b>' . $headName . '</b><br><font size="1">' . $headPos . '</font></td></tr>
            ' . $signatoryRow . '
        </table>
        <br><font size="1">
        ' . $subLine . '
        Date &amp; Time Printed: ' . date('m/d/Y H:i:s') . '
        </font>';
    }

    private function expandLeaveDates(string $leaveString): array
    {
        $dates = [];
        foreach (explode(',', $leaveString) as $p) {
            $p = trim($p);
            if (preg_match('/^(\d{2})\/(\d{2})-(\d{2})\/(\d{4})$/', $p, $m)) {
                for ($d = (int) $m[2]; $d <= (int) $m[3]; $d++) {
                    $dates[] = sprintf('%02d/%02d/%04d', $m[1], $d, $m[4]);
                }
            } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $p)) {
                $dates[] = $p;
            } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $p, $m)) {
                $dates[] = sprintf('%02d/%02d/%04d', $m[2], $m[3], $m[1]);
            }
        }
        return $dates;
    }
}
