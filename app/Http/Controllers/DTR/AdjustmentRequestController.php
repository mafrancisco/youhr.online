<?php

namespace App\Http\Controllers\DTR;

use App\Http\Controllers\Controller;
use App\Models\AttendanceClean;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdjustmentRequestController extends Controller
{
    /**
     * Directly edit/revise a time log entry.
     * Only blank fields can be edited (biometric data is protected).
     * Blocked if there are existing gate passes or leaves on the date.
     * Edited entries are tracked in the request table for red-marking in reports.
     */
    public function store(Request $request)
    {
        $request->validate([
            'AttDate'    => ['required', 'string'],
            'attID'      => ['nullable', 'integer'],
            'StartTime1' => ['nullable', 'date_format:H:i'],
            'StartTime2' => ['nullable', 'date_format:H:i'],
            'StartTime3' => ['nullable', 'date_format:H:i'],
            'StartTime4' => ['nullable', 'date_format:H:i'],
            'remarks'    => ['nullable', 'string', 'max:255'],
        ]);

        $employee = Employee::where('email', $request->user()->email)->firstOrFail();
        $badgeID  = $employee->badgeID;
        $attDate  = $request->AttDate;

        // ─── Validate: check for existing Gate Pass on this date ─────────────
        $blockMessage = $this->checkExistingRecords($badgeID, $attDate);
        if ($blockMessage) {
            return back()->withErrors(['AttDate' => $blockMessage]);
        }

        // ─── Get existing attendance record ──────────────────────────────────
        $existing = AttendanceClean::where('BadgeNumber', $badgeID)
            ->where('AttDate', $attDate)
            ->first();

        if (!$existing) {
            return back()->withErrors(['AttDate' => 'No attendance record found for this date.']);
        }

        // ─── Validate: prevent overwriting biometric data ────────────────────
        $timeFields = ['StartTime1', 'StartTime2', 'StartTime3', 'StartTime4'];
        $errors = [];
        $edits = [];

        foreach ($timeFields as $field) {
            $existingValue = trim($existing->$field ?? '');
            $requestedValue = trim($request->$field ?? '');

            if ($this->isValidTime($existingValue) && $requestedValue && $requestedValue !== $existingValue) {
                $errors[$field] = "Cannot modify {$field} — biometric data already recorded ({$existingValue}).";
            } elseif (!$this->isValidTime($existingValue) && $this->isValidTime($requestedValue)) {
                $edits[$field] = $requestedValue;
            }
        }

        if (!empty($errors)) {
            return back()->withErrors($errors);
        }

        // ─── Directly update attendance_clean ────────────────────────────────
        $updateData = [];
        foreach ($edits as $field => $value) {
            $updateData[$field] = $value;
        }

        if (!empty($updateData)) {
            $existing->update($updateData);
        }

        // ─── Track the edit in request table (for red-marking in PDF) ────────
        $logFlags = [
            'log1' => isset($edits['StartTime1']) ? '1' : '0',
            'log2' => isset($edits['StartTime2']) ? '1' : '0',
            'log3' => isset($edits['StartTime3']) ? '1' : '0',
            'log4' => isset($edits['StartTime4']) ? '1' : '0',
        ];

        if (!empty($edits)) {
            AttendanceRequest::updateOrCreate(
                ['BadgeNumber' => $badgeID, 'AttDate' => $attDate],
                [
                    'attID'      => $existing->id,
                    'StartTime1' => $request->StartTime1 ?? '',
                    'StartTime2' => $request->StartTime2 ?? '',
                    'StartTime3' => $request->StartTime3 ?? '',
                    'StartTime4' => $request->StartTime4 ?? '',
                    'dateReq'    => now()->toDateString(),
                    'remarks'    => $request->remarks ?? '',
                    'log1'       => $logFlags['log1'],
                    'log2'       => $logFlags['log2'],
                    'log3'       => $logFlags['log3'],
                    'log4'       => $logFlags['log4'],
                ]
            );
        }

        return back()->with('success', 'Time log updated.');
    }

    public function destroy(Request $request, AttendanceRequest $req)
    {
        $employee = Employee::where('email', $request->user()->email)->firstOrFail();
        abort_unless($req->BadgeNumber === $employee->badgeID, 403);
        $req->delete();
        return back()->with('success', 'Edit record removed.');
    }

    /**
     * Check if there are existing approved/pending gate passes or leaves on the given date.
     * Returns error message string if blocked, null if allowed.
     */
    private function checkExistingRecords(string $badgeID, string $attDate): ?string
    {
        // Convert AttDate (MM/DD/YYYY) to Y-m-d for gate pass query
        $dateYmd = $this->attDateToYmd($attDate);

        // Check gate passes (approved or pending) on this date
        $gatePass = DB::selectOne("
            SELECT id, gatepass_type, status
            FROM gatepass
            WHERE badgeID = ?
              AND gatepass_date = ?
              AND status IN ('Pending', 'Approved')
            LIMIT 1
        ", [$badgeID, $dateYmd]);

        if ($gatePass) {
            return "Time log edit is not allowed because there is an existing {$gatePass->status} Gate Pass ({$gatePass->gatepass_type}) on the selected date.";
        }

        // Check leaves (approved or pending) covering this date
        $leaves = DB::select(
            "SELECT id, status, date_start FROM leaves WHERE badgeID = ? AND status IN ('Pending', 'Approved')",
            [$badgeID]
        );

        foreach ($leaves as $leave) {
            $leaveDates = $this->expandLeaveDates($leave->date_start ?? '');
            if (in_array($attDate, $leaveDates)) {
                return "Time log edit is not allowed because there is an existing {$leave->status} Leave application covering the selected date.";
            }
        }

        return null;
    }

    /**
     * Convert AttDate MM/DD/YYYY to Y-m-d.
     */
    private function attDateToYmd(string $attDate): string
    {
        $parts = explode('/', $attDate);
        if (count($parts) === 3) {
            return "{$parts[2]}-{$parts[0]}-{$parts[1]}";
        }
        return $attDate;
    }

    /**
     * Expand leave date_start string into individual MM/DD/YYYY entries.
     */
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

    private function isValidTime(string $time): bool
    {
        return (bool) preg_match('/^(0?[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }
}
