<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveCredit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    /**
     * List the authenticated employee's leaves.
     *
     * GET /api/v1/leaves
     */
    public function index(Request $request): JsonResponse
    {
        $employee = Employee::where('email', $request->user()->email)->firstOrFail();

        $leaves = Leave::where('badgeID', $employee->badgeID)
            ->orderByDesc('date_filed')
            ->get()
            ->map(fn($l) => [
                'id'         => $l->id,
                'controlno'  => $l->controlno,
                'leave_type' => $l->leave_type,
                'type_name'  => $l->type?->leave_type,
                'date_start' => $l->date_start,
                'date_end'   => $l->date_end,
                'noofdays'   => $l->noofdays,
                'status'     => $l->status,
                'date_filed' => $l->date_filed,
                'details'    => $l->leave_details,
            ]);

        return response()->json(['leaves' => $leaves]);
    }

    /**
     * Get the authenticated employee's leave credit balance.
     *
     * GET /api/v1/leaves/credits
     */
    public function credits(Request $request): JsonResponse
    {
        $employee = Employee::where('email', $request->user()->email)->firstOrFail();

        $credit = LeaveCredit::where('badgeID', $employee->badgeID)->first();

        return response()->json([
            'credits' => $credit ? [
                'vl'          => $credit->vl,
                'sl'          => $credit->sl,
                'ot'          => $credit->ot,
                'service'     => $credit->service,
                'dateupdated' => $credit->dateupdated,
            ] : null,
        ]);
    }

    /**
     * File a new leave application.
     *
     * POST /api/v1/leaves
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'leave_type'    => ['required', 'integer'],
            'dates'         => ['required', 'array', 'min:1'],
            'dates.*'       => ['required', 'string'],
            'leave_details' => ['nullable', 'string', 'max:500'],
        ]);

        $employee = Employee::where('email', $request->user()->email)->firstOrFail();

        $dateStr = implode(',', $request->dates);
        $noofdays = count($request->dates);

        $leave = Leave::create([
            'controlno'    => 'LV-' . now()->format('ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'badgeID'      => $employee->badgeID,
            'leave_type'   => $request->leave_type,
            'date_start'   => $dateStr,
            'date_end'     => '',
            'leave_details' => $request->leave_details ?? '',
            'date_filed'   => now()->toDateString(),
            'noofdays'     => $noofdays,
            'status'       => 'Pending',
        ]);

        return response()->json([
            'message' => 'Leave application filed.',
            'leave'   => [
                'id'        => $leave->id,
                'controlno' => $leave->controlno,
                'status'    => $leave->status,
            ],
        ], 201);
    }

    /**
     * Cancel a pending leave.
     *
     * DELETE /api/v1/leaves/{leave}
     */
    public function destroy(Request $request, Leave $leave): JsonResponse
    {
        $employee = Employee::where('email', $request->user()->email)->firstOrFail();

        if ($leave->badgeID !== $employee->badgeID) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($leave->status !== 'Pending') {
            return response()->json(['message' => 'Only pending leaves can be cancelled.'], 422);
        }

        $leave->delete();

        return response()->json(['message' => 'Leave cancelled.']);
    }
}
