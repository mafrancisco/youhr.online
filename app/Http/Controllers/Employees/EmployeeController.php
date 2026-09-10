<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Biometric\AdmsController;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\Head;
use App\Models\Schedule;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        $employee = Employee::create(array_merge($data, [
            'status1'      => 'Active',
            'date_encoded' => now()->toDateString(),
        ]));

        // Queue the new employee onto every active biometric device in this tenant
        // so the user slot (PIN + name) is created automatically. The fingerprint
        // itself must still be enrolled physically at the device against this PIN.
        $this->provisionEmployeeOnDevices($employee);

        return back()->with('success', 'Employee added.');
    }

    /**
     * Push a newly created employee to the tenant's active biometric devices.
     *
     * This creates the user record (PIN = badgeID, Name = empName) on each device
     * via the ADMS command queue. It never throws into the request lifecycle:
     * a device being offline or unreachable must not block employee creation.
     * The biometric fingerprint template cannot be captured remotely — an admin
     * still enrolls the finger at the device against the PIN created here.
     */
    private function provisionEmployeeOnDevices(Employee $employee): void
    {
        try {
            $pin  = trim((string) $employee->badgeID);
            $name = trim((string) $employee->empName);

            if ($pin === '') {
                Log::warning("Biometric provision skipped: employee #{$employee->id} has no badgeID.");
                return;
            }

            $devices = BiometricDevice::where('status', 'active')
                ->whereNotNull('serial_number')
                ->get(['id', 'serial_number']);

            if ($devices->isEmpty()) {
                return;
            }

            foreach ($devices as $device) {
                // ZKTeco ADMS user-create command. getRequest() prepends "C:", so we
                // queue everything after it. Tabs separate the key=value fields.
                $command = '1:DATA UPDATE USERINFO PIN=' . $pin
                    . "\tName=" . $name
                    . "\tPri=0\tPasswd=\tCard=\tGrp=1";

                AdmsController::queueCommand($device->serial_number, $command);
            }

            Log::info("Biometric provision queued for employee #{$employee->id} (PIN {$pin}) on {$devices->count()} device(s).");
        } catch (\Throwable $e) {
            // Provisioning is best-effort; log and continue so the employee is still saved.
            Log::error("Biometric provision failed for employee #{$employee->id}: " . $e->getMessage());
        }
    }

    public function update(Request $request, Employee $emp)
    {
        $data = $request->validate([
            'badgeID'     => ['required', "unique:employees,badgeID,{$emp->id}"],
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
