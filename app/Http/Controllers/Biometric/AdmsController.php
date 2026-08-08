<?php

namespace App\Http\Controllers\Biometric;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Models\BiometricLog;
use App\Services\BiometricLogIngestService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ADMS (Active Data Management Server) Controller
 *
 * Handles push-mode communication from ZKTeco devices (MB460 Plus, etc.)
 * The device sends HTTP requests to this server with attendance data.
 *
 * Standard ZKTeco ADMS endpoints:
 * - GET  /iclock/cdata       - Device handshake/registration
 * - POST /iclock/cdata       - Device pushes attendance records
 * - GET  /iclock/getrequest  - Device polls for commands
 */
class AdmsController extends Controller
{
    /**
     * Handle device handshake (GET /iclock/cdata)
     * Device sends serial number and requests configuration.
     */
    public function handshake(Request $request)
    {
        $sn = $request->query('SN', '');

        Log::info("ADMS handshake from device: {$sn}", $request->query());

        if (empty($sn)) {
            return response('ERROR: NO SN', 400);
        }

        // Register or find the device by serial number
        // Look across all tenant databases to find which tenant owns this device
        $device = null;
        $tenantDb = null;

        $companies = DB::connection('mysql')->table('companies')->where('status', 'active')->get();
        foreach ($companies as $company) {
            $dbName = $company->database ?? null;
            if (!$dbName) continue;

            try {
                $found = DB::connection('mysql')->select(
                    "SELECT * FROM `{$dbName}`.`biometric_devices` WHERE `serial_number` = ? LIMIT 1",
                    [$sn]
                );
                if (!empty($found)) {
                    $device = $found[0];
                    $tenantDb = $dbName;
                    break;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if ($device) {
            DB::connection('mysql')->statement(
                "UPDATE `{$tenantDb}`.`biometric_devices` SET `is_online` = 1, `last_sync_at` = NOW() WHERE `id` = ?",
                [$device->id]
            );
        } else {
            Log::warning("ADMS: Unknown device SN={$sn} from IP={$request->ip()} - not registered in any tenant");
        }

        // Respond with server configuration
        // Format: KEY=VALUE pairs separated by newlines
        $config = [
            'GET OPTION FROM: ' . $sn,
            'ATTLOGStamp=0',
            'OPERLOGStamp=0',
            'ATTPHOTOStamp=0',
            'ErrorDelay=60',
            'Delay=10',
            'TransTimes=00:00;14:05',
            'TransInterval=1',
            'TransFlag=TransData AttLog\tOpLog',
            'Realtime=1',
            'ServerVer=2.4.1',
        ];

        return response(implode("\n", $config), 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Handle attendance data push (POST /iclock/cdata)
     * Device sends attendance logs in tab-separated format.
     */
    public function receiveData(Request $request)
    {
        $sn = $request->query('SN', '');
        $table = $request->query('table', '');

        Log::info("ADMS data received: SN={$sn}, table={$table}", [
            'query' => $request->query(),
            'content_length' => strlen($request->getContent()),
        ]);

        $device = null;
        $tenantDb = null;

        $companies = DB::connection('mysql')->table('companies')->where('status', 'active')->get();
        foreach ($companies as $company) {
            $dbName = $company->database ?? null;
            if (!$dbName) continue;

            try {
                $found = DB::connection('mysql')->select(
                    "SELECT * FROM `{$dbName}`.`biometric_devices` WHERE `serial_number` = ? LIMIT 1",
                    [$sn]
                );
                if (!empty($found)) {
                    $device = $found[0];
                    $tenantDb = $dbName;
                    break;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if (!$device) {
            Log::warning("ADMS: Data from unknown device SN={$sn}");
            return response('OK', 200);
        }

        DB::connection('mysql')->statement(
            "UPDATE `{$tenantDb}`.`biometric_devices` SET `is_online` = 1, `last_sync_at` = NOW() WHERE `id` = ?",
            [$device->id]
        );

        $content = $request->getContent();

        if ($table === 'ATTLOG') {
            $this->processAttendanceLogs($device, $content, $tenantDb);
        } elseif ($table === 'OPERLOG' || $table === 'USERINFO') {
            // User records arrive either as a dedicated USERINFO push or mixed into an
            // OPERLOG push as "USER PIN=..." lines alongside OPLOG operation events.
            $this->processUserRecords($device, $content, $tenantDb);

            Log::info("ADMS: Operation log received from {$sn}", ['content' => $content]);
        }

        return response('OK: ' . strlen($content), 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Handle device polling for commands (GET /iclock/getrequest)
     *
     * The device polls this endpoint on the interval set during the handshake. A
     * plain "OK" means nothing to do; returning "C:<id>:<COMMAND>" instructs the
     * device to act. Commands are queued in the cache as one-shot entries, so a
     * command is handed out once and then cleared.
     */
    public function getRequest(Request $request)
    {
        $sn = $request->query('SN', '');

        // Update device online status
        // Update device online status
        $companies = DB::connection('mysql')->table('companies')->where('status', 'active')->get();
        foreach ($companies as $company) {
            $dbName = $company->database ?? null;
            if (!$dbName) continue;

            try {
                $found = DB::connection('mysql')->select(
                    "SELECT id FROM `{$dbName}`.`biometric_devices` WHERE `serial_number` = ? LIMIT 1",
                    [$sn]
                );
                if (!empty($found)) {
                    DB::connection('mysql')->statement(
                        "UPDATE `{$dbName}`.`biometric_devices` SET `is_online` = 1 WHERE `id` = ?",
                        [$found[0]->id]
                    );
                    break;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        // Hand out the next queued command, if any are pending for this device.
        $cacheKey = self::commandCacheKey($sn);
        $queue = Cache::get($cacheKey, []);

        if (!empty($queue)) {
            $command = array_shift($queue);

            if (empty($queue)) {
                Cache::forget($cacheKey);
            } else {
                Cache::put($cacheKey, $queue, self::COMMAND_TTL);
            }

            Log::info("ADMS dispatching command to {$sn}: {$command} (" . count($queue) . ' queued behind)');

            return response("C:{$command}", 200)
                ->header('Content-Type', 'text/plain');
        }

        // Nothing to do.
        return response('OK', 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * How long a queued command stays valid while waiting for the device to poll.
     */
    private const COMMAND_TTL = 900;

    /**
     * Cache key holding pending commands for a device serial.
     */
    public static function commandCacheKey(string $serial): string
    {
        return 'adms.command.' . $serial;
    }

    /**
     * Append a command to be handed to the device on its next poll.
     *
     * The device executes one command per poll, so these are dispatched in order
     * across successive check-ins rather than all at once.
     */
    public static function queueCommand(string $serial, string $command): void
    {
        $key = self::commandCacheKey($serial);
        $queue = Cache::get($key, []);
        $queue[] = $command;

        Cache::put($key, $queue, self::COMMAND_TTL);
    }

    /**
     * Queue user-list requests for the given device PINs.
     *
     * An empty PIN returns nothing on this firmware, so each PIN must be asked for
     * individually rather than requesting the whole table at once.
     */
    public static function queueUserInfoRequests(string $serial, array $pins): int
    {
        $queued = 0;

        foreach (array_unique(array_filter($pins, fn ($p) => trim((string) $p) !== '')) as $pin) {
            self::queueCommand($serial, '1:DATA QUERY USERINFO PIN=' . trim((string) $pin));
            $queued++;
        }

        return $queued;
    }

    /**
     * Handle device info push (POST /iclock/devicecmd)
     */
    public function deviceCmd(Request $request)
    {
        $sn = $request->query('SN', '');
        Log::info("ADMS devicecmd from {$sn}", [
            'query' => $request->query(),
            'content' => $request->getContent(),
        ]);

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Parse "USER" records from an ADMS push and upsert them as device users.
     *
     * Lines look like:
     *   USER PIN=3\tName=Junlou Tordos\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=...
     *
     * Non-USER lines (OPLOG operation events, etc.) are ignored.
     */
    private function processUserRecords($device, string $content, string $tenantDb): void
    {
        $created = 0;
        $updated = 0;

        foreach (array_filter(explode("\n", trim($content))) as $line) {
            $line = trim($line);

            if ($line === '' || !preg_match('/^USER\s+(.*)$/i', $line, $m)) {
                continue;
            }

            // Split the remainder into key=value pairs on tabs (falling back to
            // whitespace for firmware that does not use tabs).
            $fields = [];
            $parts = preg_split('/\t+/', $m[1]);
            if (count($parts) === 1) {
                $parts = preg_split('/\s{1,}(?=[A-Za-z]+=)/', $m[1]);
            }

            foreach ($parts as $pair) {
                if (str_contains($pair, '=')) {
                    [$k, $v] = explode('=', $pair, 2);
                    $fields[strtolower(trim($k))] = trim($v);
                }
            }

            $pin = $fields['pin'] ?? '';
            if ($pin === '') {
                continue;
            }

            $name = $fields['name'] ?? '';
            $privilege = (int) ($fields['pri'] ?? 0);

            $existing = DB::connection('mysql')->select(
                "SELECT id FROM `{$tenantDb}`.`biometric_device_users` WHERE device_id = ? AND uid = ? LIMIT 1",
                [$device->id, $pin]
            );

            if (empty($existing)) {
                DB::connection('mysql')->statement(
                    "INSERT INTO `{$tenantDb}`.`biometric_device_users`
                     (device_id, uid, user_id, name, role, privilege, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [$device->id, $pin, $pin, $name ?: "User {$pin}", $privilege, $privilege]
                );
                $created++;
            } else {
                DB::connection('mysql')->statement(
                    "UPDATE `{$tenantDb}`.`biometric_device_users`
                     SET user_id = ?, name = ?, role = ?, privilege = ?, updated_at = NOW()
                     WHERE id = ?",
                    [$pin, $name ?: "User {$pin}", $privilege, $privilege, $existing[0]->id]
                );
                $updated++;
            }
        }

        if ($created || $updated) {
            Log::info("ADMS: users synced for device #{$device->id} — {$created} new, {$updated} updated");
        }
    }

    /**
     * Process attendance log lines from ADMS push.
     *
     * Parsing, punch-type handling and de-duplication live in
     * BiometricLogIngestService so that push and agent transports behave alike.
     */
    private function processAttendanceLogs($device, string $content, string $tenantDb): void
    {
        $result = app(BiometricLogIngestService::class)
            ->ingestAttlog($device->id, $tenantDb, $content);

        Log::info(
            "ADMS: Processed {$result['stored']} logs, skipped "
            . ($result['duplicates'] + $result['invalid'])
            . " for tenant DB {$tenantDb}"
        );
    }
}
