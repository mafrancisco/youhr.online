<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Services\BiometricLogIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ingest endpoint for the on-premise sync agent.
 *
 * Devices that only speak the polling protocol (TCP/UDP) sit on a tenant's private
 * network, which a cloud server cannot reach. An agent runs on that network, polls
 * the device locally and posts the punches here.
 *
 * Unlike the ADMS endpoints — which a device can only identify itself to by serial
 * number — this route is authenticated, and the tenant comes from the token rather
 * than from the request body. A caller therefore cannot write into a tenant it does
 * not hold a token for.
 */
class BiometricIngestController extends Controller
{
    public function __construct(private BiometricLogIngestService $ingest) {}

    /**
     * Devices the agent should poll.
     *
     * GET /api/v1/biometric/devices
     *
     * Keeping the list server-side means connection details stay in the tenant's own
     * records instead of being duplicated into each site's local config.
     */
    public function devices(): JsonResponse
    {
        $devices = BiometricDevice::active()
            ->orderBy('name')
            ->get(['id', 'name', 'model', 'serial_number', 'ip_address', 'port', 'connection_type']);

        return response()->json(['devices' => $devices]);
    }

    /**
     * Accept a batch of punches read from a device on the tenant's network.
     *
     * POST /api/v1/biometric/punches
     *
     * The agent may re-send freely: punches are de-duplicated on
     * (device, user, timestamp, punch type), so it needs no local state and a
     * crashed or restarted run cannot lose or double-count anything.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id'            => ['required_without:serial', 'integer'],
            'serial'               => ['required_without:device_id', 'string', 'max:50'],
            'reachable'            => ['nullable', 'boolean'],
            'punches'              => ['present', 'array', 'max:5000'],
            'punches.*.pin'        => ['required', 'string', 'max:20'],
            'punches.*.timestamp'  => ['required', 'string', 'max:32'],
            'punches.*.status'     => ['nullable', 'integer'],
            'punches.*.verify'     => ['nullable', 'integer'],
        ]);

        // Resolve the device inside the authenticated tenant only.
        $device = BiometricDevice::query()
            ->when(
                !empty($data['device_id']),
                fn ($q) => $q->whereKey($data['device_id']),
                fn ($q) => $q->where('serial_number', $data['serial'])
            )
            ->first();

        if (!$device) {
            return response()->json([
                'message' => 'Device not found for this tenant. Register it, and make sure its serial number matches.',
            ], 404);
        }

        $tenantDb = DB::connection('tenant')->getDatabaseName();

        $result = $this->ingest->ingest($device->id, $tenantDb, $data['punches']);

        // Record what the agent observed, so the device list reflects reality rather
        // than a value left over from whenever a connection was last attempted.
        $device->update([
            'is_online'    => (bool) ($data['reachable'] ?? true),
            'last_sync_at' => now(),
        ]);

        Log::info("Agent ingest for device #{$device->id} ({$tenantDb})", $result);

        return response()->json([
            'device'     => $device->name,
            'stored'     => $result['stored'],
            'duplicates' => $result['duplicates'],
            'invalid'    => $result['invalid'],
        ]);
    }
}
