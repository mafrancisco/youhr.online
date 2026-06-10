<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\Head;
use App\Models\Schedule;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        $active = Employee::active()
            ->with(['statusRecord', 'scheduleRecord', 'divisionRecord', 'unitRecord'])
            ->orderBy('empName')
            ->get()
            ->map(fn($e) => [
                'id'           => $e->id,
                'badgeID'      => $e->badgeID,
                'empName'      => $e->empName,
                'email'        => $e->email,
                'empStatus'    => $e->empStatus,
                'statusLabel'  => $e->statusRecord?->description,
                'empDesig'     => $e->empDesig,
                'schedule'     => $e->schedule,
                'scheduleName' => $e->scheduleRecord?->schedulename,
                'empHead'      => $e->empHead,
                'division_id'  => $e->division_id,
                'division_name'=> $e->divisionRecord?->division_name,
                'unit_id'      => $e->unit_id,
                'unit_name'    => $e->unitRecord?->unit_name,
            ]);

        $inactive = Employee::inactive()
            ->orderBy('empName')
            ->get(['id', 'badgeID', 'empName', 'date_deact']);

        return Inertia::render('Employees/Index', [
            'active'    => $active,
            'inactive'  => $inactive,
            'statuses'  => EmployeeStatus::orderBy('id')->get(['id', 'description']),
            'schedules' => Schedule::orderBy('schedulename')->get(['id', 'schedulename']),
            'heads'     => Head::orderBy('headname')->get(['id', 'headname', 'headposition']),
            'divisions' => Division::orderBy('division_name')->get(['id', 'division_name']),
            'units'     => Unit::orderBy('unit_name')->get(['id', 'unit_name', 'division_id']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'badgeID'     => ['required', 'unique:employees,badgeID'],
            'empName'     => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'unique:employees,email'],
            'empStatus'   => ['required'],
            'empDesig'    => ['required', 'string'],
            'schedule'    => ['required'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'unit_id'     => ['nullable', 'integer', 'exists:units,id'],
        ]);

        Employee::create(array_merge($data, [
            'status1'      => 'Active',
            'date_encoded' => now()->toDateString(),
        ]));

        return back()->with('success', 'Employee added.');
    }

    public function update(Request $request, Employee $emp)
    {
        $data = $request->validate([
            'empName'     => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', "unique:employees,email,{$emp->id}"],
            'empStatus'   => ['required'],
            'empDesig'    => ['required', 'string'],
            'schedule'    => ['required'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'unit_id'     => ['nullable', 'integer', 'exists:units,id'],
        ]);

        $emp->update($data);
        return back()->with('success', 'Employee updated.');
    }

    public function deactivate(Employee $emp)
    {
        $emp->update(['status1' => 'Inactive', 'date_deact' => now()->toDateString()]);
        return back()->with('success', 'Employee deactivated.');
    }

    public function reactivate(Employee $emp)
    {
        $emp->update(['status1' => 'Active', 'date_deact' => '']);
        return back()->with('success', 'Employee reactivated.');
    }
}
