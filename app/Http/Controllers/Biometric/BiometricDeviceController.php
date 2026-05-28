<?php

namespace App\Http\Controllers\Biometric;

use App\Http\Controllers\Controller;
use App\Jobs\SyncBiometricLogsJob;
use App\Jobs\SyncBiometricUsersJob;
use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use App\Models\BiometricEmployeeMapping;
use App\Models\BiometricLog;
use App\Models\BiometricSyncHistory;
use App\Models\Employee;
use App\Services\ZKTecoService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BiometricDeviceController extends Controller
{
    public function __construct(private ZKTecoService $zkService) {}

    /**
     * Device list page.
     */
    public function index(): Response
    {
        $devices = BiometricDevice::withCount(['users', 'logs'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Biometric/Devices', [
            'devices' => $devices,
        ]);
    }

    /**
     * Store a new device.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'model'           => ['required', 'string', 'max:50'],
            'ip_address'      => ['required', 'ip', 'unique:biometric_devices,ip_address'],
            'port'            => ['required', 'integer', 'min:1', 'max:65535'],
            'connection_type' => ['required', 'in:LAN,WLAN'],
            'location'        => ['nullable', 'string', 'max:150'],
            'remarks'         => ['nullable', 'string', 'max:500'],
        ]);

        BiometricDevice::create($data);

        return back()->with('success', 'Device registered successfully.');
    }

    /**
     * Update a device.
     */
    public function update(Request $request, BiometricDevice $device)
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'model'           => ['required', 'string', 'max:50'],
            'ip_address'      => ['required', 'ip', "unique:biometric_devices,ip_address,{$device->id}"],
            'port'            => ['required', 'integer', 'min:1', 'max:65535'],
            'connection_type' => ['required', 'in:LAN,WLAN'],
            'location'        => ['nullable', 'string', 'max:150'],
            'status'          => ['required', 'in:active,inactive'],
            'remarks'         => ['nullable', 'string', 'max:500'],
        ]);

        $device->update($data);

        return back()->with('success', 'Device updated.');
    }

    /**
     * Delete a device.
     */
    public function destroy(BiometricDevice $device)
    {
        $device->delete();

        return back()->with('success', 'Device deleted.');
    }

    /**
     * Test connection to a device.
     */
    public function testConnection(Request $request)
    {
        $request->validate([
            'ip_address' => ['required', 'ip'],
            'port'       => ['required', 'integer', 'min:1', 'max:65535'],
        ]);

        $result = $this->zkService->testConnection($request->ip_address, (int) $request->port);

        if ($result['success']) {
            return back()->with('success', "Connected! Device: {$result['device_name']}, S/N: {$result['serial_number']}");
        }

        return back()->with('error', $result['message']);
    }

    /**
     * Device detail page — users, logs, sync history.
     */
    public function show(BiometricDevice $device): Response
    {
        $device->load(['syncHistories' => fn($q) => $q->latest()->limit(20)]);

        $users = BiometricDeviceUser::where('device_id', $device->id)
            ->with('mapping.employee')
            ->orderBy('user_id')
            ->get()
            ->map(fn($u) => [
                'id'        => $u->id,
                'uid'       => $u->uid,
                'user_id'   => $u->user_id,
                'name'      => $u->name,
                'role'      => $u->role,
                'is_mapped' => $u->mapping !== null,
                'badge_id'  => $u->mapping?->badge_id,
                'emp_name'  => $u->mapping?->employee?->empName,
            ]);

        $recentLogs = BiometricLog::where('device_id', $device->id)
            ->orderByDesc('timestamp')
            ->limit(100)
            ->get()
            ->map(fn($l) => [
                'id'             => $l->id,
                'device_user_id' => $l->device_user_id,
                'timestamp'      => $l->timestamp->format('Y-m-d H:i:s'),
                'punch_type'     => $l->punch_type,
                'punch_label'    => BiometricLog::punchLabel($l->punch_type),
                'is_processed'   => $l->is_processed,
            ]);

        $employees = Employee::active()->orderBy('empName')->get(['badgeID', 'empName']);

        return Inertia::render('Biometric/DeviceDetail', [
            'device'      => $device,
            'users'       => $users,
            'recentLogs'  => $recentLogs,
            'syncHistory' => $device->syncHistories,
            'employees'   => $employees,
        ]);
    }

    /**
     * Sync attendance logs from device (queued).
     */
    public function syncLogs(BiometricDevice $device)
    {
        SyncBiometricLogsJob::dispatch($device->id);

        return back()->with('success', 'Log sync started. This may take a moment.');
    }

    /**
     * Sync users from device (queued).
     */
    public function syncUsers(BiometricDevice $device)
    {
        SyncBiometricUsersJob::dispatch($device->id);

        return back()->with('success', 'User sync started. This may take a moment.');
    }

    /**
     * Process synced biometric logs into attendance_clean using Time Detection Rules.
     */
    public function processLogs(Request $request)
    {
        $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date'   => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $processor = new \App\Services\BiometricLogProcessorService();
        $result = $processor->process($request->start_date, $request->end_date);

        if ($result['processed'] > 0) {
            // Run DTR computation after processing
            $computer = new \App\Services\AttendanceComputationService();
            $computer->compute($request->start_date, $request->end_date);
        }

        return back()->with('success', $result['message'] . ($result['processed'] > 0 ? ' DTR computed.' : ''));
    }

    /**
     * Map a biometric device user to an employee.
     */
    public function mapUser(Request $request, BiometricDeviceUser $deviceUser)
    {
        $request->validate([
            'badge_id' => ['required', 'string', 'exists:employees,badgeID'],
        ]);

        BiometricEmployeeMapping::updateOrCreate(
            ['device_user_id' => $deviceUser->id],
            ['badge_id' => $request->badge_id]
        );

        return back()->with('success', "User mapped to employee {$request->badge_id}.");
    }

    /**
     * Remove a user mapping.
     */
    public function unmapUser(BiometricDeviceUser $deviceUser)
    {
        BiometricEmployeeMapping::where('device_user_id', $deviceUser->id)->delete();

        return back()->with('success', 'Mapping removed.');
    }

    /**
     * Sync history page for a device.
     */
    public function syncHistory(BiometricDevice $device): Response
    {
        $history = BiometricSyncHistory::where('device_id', $device->id)
            ->orderByDesc('created_at')
            ->paginate(25);

        return Inertia::render('Biometric/SyncHistory', [
            'device'  => $device,
            'history' => $history,
        ]);
    }
}
