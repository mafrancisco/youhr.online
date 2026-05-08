<?php

namespace App\Services;

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
        ]);
        $mpdf->SetAutoPageBreak(false);
        return $mpdf;
    }

    // -----------------------------------------------------------------------
    // Bulk PDF — 2 employees per page (mirrors download.php)
    // -----------------------------------------------------------------------
    public function bulk(string $startDate, string $endDate, int $empStatus): string
    {
        $mpdf = $this->makeMpdf();

        $start_date = date('F j', strtotime($startDate));
        $end_date   = date('F j, Y', strtotime($endDate));
        $attRange   = $start_date . '-' . $end_date;

        $fname    = ($empStatus == 1) ? 'Regular Employees' : 'Contractual Employees';
        $filename = $fname . ' DTR-' . $attRange . '.pdf';

        $employees = DB::select(
            "SELECT badgeID, empName, empHead FROM employees WHERE empStatus = ? ORDER BY empName",
            [$empStatus]
        );

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
        $rows = DB::select("
            SELECT id, AttDate, StartTime1, StartTime2, StartTime3, StartTime4,
                   Tardiness, undertime, OTIn, OTOut, OT, remarks
            FROM attendance_clean
            WHERE BadgeNumber = ?
              AND DATE_FORMAT(STR_TO_DATE(AttDate, '%m/%d/%Y'), '%Y-%m-%d') BETWEEN ? AND ?
            ORDER BY STR_TO_DATE(AttDate, '%m/%d/%Y') ASC
        ", [$badgeID, $startDate, $endDate]);

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
            $filledLogs = 0;
            foreach ([$row->StartTime1, $row->StartTime2, $row->StartTime3, $row->StartTime4] as $log) {
                if (!empty($log) && !in_array($log, ['Saturday', 'Sunday', 'L', 'OB'])) $filledLogs++;
            }
            if ($filledLogs > 2) {
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

            if ((int) $row->OT > 0) {
                $indiOThr  = (int) floor($row->OT / 60);
                $indiOTmin = $row->OT % 60;
                $otcom     = $indiOThr . ' hr(s) ' . $indiOTmin . ' min(s)';
            } else {
                $otcom = '';
            }

            $html .= '<tr>
                <td>' . $dayDisplay . '</td>
                <td><font color="' . $colors[0] . '">' . $times[0] . '</font></td>
                <td><font color="' . $colors[1] . '">' . $times[1] . '</font></td>
                <td><font color="' . $colors[2] . '">' . $times[2] . '</font></td>
                <td><font color="' . $colors[3] . '">' . $times[3] . '</font></td>
                <td>' . ($row->OTIn ?? '') . '</td>
                <td>' . ($row->OTOut ?? '') . '</td>
                <td>' . $hr . '</td>
                <td>' . $min . '</td>
                <td><font size="1">' . $otcom . '</font></td>
                <td>' . $remarks . '</td>
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
}
