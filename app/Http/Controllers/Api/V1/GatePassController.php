<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\GatePass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GatePassController extends Controller
{
    /**
     * List the authenticated employee's gate passes.
     *
     * GET /api/v1/gate-passes
     */
    public function index(Request $request): JsonResponse
    {
        $employee = Employee::where('email', $request->user()->email)->firstOrFail();

        $passes = GatePass::where('badgeID', $employee->badgeID)
            ->orderByDesc('gatepass_datefiled')
            ->get()
            ->map(fn($gp) => [
                'id'               => $gp->id,
                'controlno'        => $gp->controlno,
                'gatepass_type'    => $gp->gatepass_type,
                'gatepass_date'    => $gp->gatepass_date,
                'gatepass_timeout' => $gp->gatepass_timeout,
                'gatepass_timein'  => $gp->gatepass_timein,
                'purpose'          => $gp->purpose,
                'destination'      => $gp->destination,
                'status'           => $gp->status,
                'date_filed'       => $gp->gatepass_datefiled,
            ]);

        return response()->json(['gate_passes' => $passes]);
    }

    /**
     * File a new gate pass.
     *
     * POST /api/v1/gate-passes
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'gatepass_type'    => ['required', 'string', 'in:Official Business,Official Time,Personal'],
            'gatepass_date'    => ['required', 'string'],
            'gatepass_timeout' => ['nullable', 'date_format:H:i'],
            'gatepass_timein'  => ['nullable', 'date_format:H:i'],
            'purpose'          => ['required', 'string', 'max:500'],
            'destination'      => ['required', 'string', 'max:255'],
        ]);

        $employee = Employee::where('email', $request->user()->email)->firstOrFail();

        $gp = GatePass::create([
            'controlno'         => 'GP-' . now()->format('ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'badgeID'           => $employee->badgeID,
            'gatepass_type'     => $request->gatepass_type,
            'gatepass_date'     => $request->gatepass_date,
            'gatepass_timeout'  => $request->gatepass_timeout ?? '',
            'gatepass_timein'   => $request->gatepass_timein ?? '',
            'purpose'           => $request->purpose,
            'destination'       => $request->destination,
            'gatepass_datefiled' => now()->toDateString(),
            'status'            => 'Pending',
        ]);

        return response()->json([
            'message'   => 'Gate pass filed.',
            'gate_pass' => [
                'id'        => $gp->id,
                'controlno' => $gp->controlno,
                'status'    => $gp->status,
            ],
        ], 201);
    }

    /**
     * Cancel a pending gate pass.
     *
     * DELETE /api/v1/gate-passes/{gatePass}
     */
    public function destroy(Request $request, GatePass $gatePass): JsonResponse
    {
        $employee = Employee::where('email', $request->user()->email)->firstOrFail();

        if ($gatePass->badgeID !== $employee->badgeID) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($gatePass->status !== 'Pending') {
            return response()->json(['message' => 'Only pending gate passes can be cancelled.'], 422);
        }

        $gatePass->delete();

        return response()->json(['message' => 'Gate pass cancelled.']);
    }
}
