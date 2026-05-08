<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    /**
     * Get the authenticated employee's profile.
     *
     * GET /api/v1/employee/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $employee = Employee::where('email', $request->user()->email)
            ->with(['scheduleRecord', 'divisionRecord', 'unitRecord'])
            ->firstOrFail();

        return response()->json([
            'badgeID'       => $employee->badgeID,
            'empName'       => $employee->empName,
            'email'         => $employee->email,
            'empDesig'      => $employee->empDesig,
            'empHead'       => $employee->empHead,
            'status'        => $employee->status1,
            'schedule'      => $employee->scheduleRecord?->schedulename,
            'division'      => $employee->divisionRecord?->division_name,
            'unit'          => $employee->unitRecord?->unit_name,
        ]);
    }

    /**
     * List all active employees (HR only).
     *
     * GET /api/v1/employees
     */
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::active()
            ->orderBy('empName')
            ->get()
            ->map(fn($e) => [
                'id'        => $e->id,
                'badgeID'   => $e->badgeID,
                'empName'   => $e->empName,
                'email'     => $e->email,
                'empDesig'  => $e->empDesig,
                'empStatus' => $e->empStatus,
                'status'    => $e->status1,
            ]);

        return response()->json(['employees' => $employees]);
    }
}
