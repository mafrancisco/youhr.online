<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Submission;
use App\Services\DTRService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DTRController extends Controller
{
    public function __construct(private DTRService $dtrService) {}

    /**
     * Get the authenticated employee's DTR for a given month.
     *
     * GET /api/v1/dtr?month=2025-05
     */
    public function index(Request $request): JsonResponse
    {
        $employee = Employee::where('email', $request->user()->email)->firstOrFail();
        $month = $request->get('month', now()->format('Y-m'));

        $data = $this->dtrService->getMonthlyDTR($employee, $month);

        return response()->json($data);
    }

    /**
     * Submit (lock) the DTR for a given period.
     *
     * POST /api/v1/dtr/submit
     */
    public function submit(Request $request): JsonResponse
    {
        $request->validate(['attRange' => ['required', 'regex:/^\d{4}-\d{2}$/']]);

        $employee = Employee::where('email', $request->user()->email)->firstOrFail();

        if (Submission::isLocked($employee->badgeID, $request->attRange)) {
            return response()->json(['message' => 'DTR already submitted for this period.'], 422);
        }

        Submission::create([
            'badgeID'        => $employee->badgeID,
            'attRange'       => $request->attRange,
            'date_submitted' => now()->toDateString(),
            'time_submitted' => now()->format('H:i:s'),
        ]);

        return response()->json(['message' => 'DTR submitted successfully.']);
    }
}
