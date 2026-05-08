<?php

namespace App\Services;

use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use App\Models\BiometricLog;
use App\Models\BiometricSyncHistory;
use CodingLibs\ZktecoPhp\Libs\ZKTeco;
use Illuminate\Support\Facades\Log;

class ZKTecoService
{
    private ?ZKTeco $zk = null;
    private int $timeout = 5;

    /**
     * Test connection to a device.
     */
    public function testConnection(string $ip, int $port = 4370): array
    {
        try {
            $zk = new ZKTeco($ip, $port);
            $connected = $zk->connect();

            if ($connected) {
                $serialNumber = $zk->serialNumber() ?: 'Unknown';
                $deviceName = $zk->deviceName() ?: 'Unknown';
                $zk->disconnect();

                return [
                    'success'       => true,
                    'message'       => 'Connection successful.',
                    'serial_number' => $serialNumber,
                    'device_name'   => $deviceName,
                ];
            }

            return [
                'success' => false,
                'message' => 'Could not connect to device. Check IP address and port.',
            ];
        } catch (\Throwable $e) {
            Log::warning("ZKTeco connection test failed: {$ip}:{$port}", ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => "Connection failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Connect to a device.
     */
    public function connect(BiometricDevice $device): bool
    {
        try {
            $this->zk = new ZKTeco($device->ip_address, $device->port);
            $connected = $this->zk->connect();

            $device->update(['is_online' => $connected]);

            return $connected;
        } catch (\Throwable $e) {
            Log::error("ZKTeco connect failed: {$device->ip_address}", ['error' => $e->getMessage()]);
            $device->update(['is_online' => false]);
            return false;
        }
    }

    /**
     * Disconnect from the current device.
     */
    public function disconnect(): void
    {
        if ($this->zk) {
            try {
                $this->zk->disconnect();
            } catch (\Throwable) {
                // Ignore disconnect errors
            }
            $this->zk = null;
        }
    }

    /**
     * Fetch users from the device and sync to database.
     */
    public function syncUsers(BiometricDevice $device): BiometricSyncHistory
    {
        $history = BiometricSyncHistory::create([
            'device_id'  => $device->id,
            'type'       => 'users',
            'status'     => 'processing',
            'started_at' => now(),
        ]);

        try {
            if (!$this->connect($device)) {
                return $this->failHistory($history, 'Could not connect to device.');
            }

            $users = $this->zk->getUser();
            $this->disconnect();

            if (!is_array($users)) {
                return $this->failHistory($history, 'Failed to retrieve users from device.');
            }

            $fetched = count($users);
            $new = 0;
            $skipped = 0;

            foreach ($users as $user) {
                $uid    = (string) ($user['uid'] ?? $user[0] ?? '');
                $userId = (string) ($user['userid'] ?? $user['user_id'] ?? $user[1] ?? '');
                $name   = (string) ($user['name'] ?? $user[2] ?? '');
                $role   = (int) ($user['role'] ?? $user[3] ?? 0);

                if (empty($uid)) {
                    $skipped++;
                    continue;
                }

                $result = BiometricDeviceUser::updateOrCreate(
                    ['device_id' => $device->id, 'uid' => $uid],
                    ['user_id' => $userId, 'name' => $name, 'role' => $role]
                );

                if ($result->wasRecentlyCreated) {
                    $new++;
                } else {
                    $skipped++;
                }
            }

            $history->update([
                'status'          => 'completed',
                'records_fetched' => $fetched,
                'records_new'     => $new,
                'records_skipped' => $skipped,
                'completed_at'    => now(),
            ]);

            return $history;
        } catch (\Throwable $e) {
            $this->disconnect();
            return $this->failHistory($history, $e->getMessage());
        }
    }

    /**
     * Fetch attendance logs from the device and sync to database.
     */
    public function syncLogs(BiometricDevice $device): BiometricSyncHistory
    {
        $history = BiometricSyncHistory::create([
            'device_id'  => $device->id,
            'type'       => 'logs',
            'status'     => 'processing',
            'started_at' => now(),
        ]);

        try {
            if (!$this->connect($device)) {
                return $this->failHistory($history, 'Could not connect to device.');
            }

            $logs = $this->zk->getAttendance();
            $this->disconnect();

            if (!is_array($logs)) {
                return $this->failHistory($history, 'Failed to retrieve attendance logs from device.');
            }

            $fetched = count($logs);
            $new = 0;
            $skipped = 0;

            foreach ($logs as $log) {
                $userId    = (string) ($log['id'] ?? $log['uid'] ?? $log[0] ?? '');
                $timestamp = $log['timestamp'] ?? $log[3] ?? null;
                $punchType = (int) ($log['type'] ?? $log['state'] ?? $log[4] ?? 0);
                $verifyType = (int) ($log['verify'] ?? $log[2] ?? 0);

                if (empty($userId) || empty($timestamp)) {
                    $skipped++;
                    continue;
                }

                // Parse timestamp
                try {
                    $ts = \Carbon\Carbon::parse($timestamp);
                } catch (\Throwable) {
                    $skipped++;
                    continue;
                }

                // Prevent duplicates using unique constraint
                $result = BiometricLog::firstOrCreate(
                    [
                        'device_id'      => $device->id,
                        'device_user_id' => $userId,
                        'timestamp'      => $ts,
                        'punch_type'     => $punchType,
                    ],
                    [
                        'verify_type'  => $verifyType,
                        'is_processed' => false,
                    ]
                );

                if ($result->wasRecentlyCreated) {
                    $new++;
                } else {
                    $skipped++;
                }
            }

            $device->update(['last_sync_at' => now()]);

            $history->update([
                'status'          => 'completed',
                'records_fetched' => $fetched,
                'records_new'     => $new,
                'records_skipped' => $skipped,
                'completed_at'    => now(),
            ]);

            return $history;
        } catch (\Throwable $e) {
            $this->disconnect();
            return $this->failHistory($history, $e->getMessage());
        }
    }

    /**
     * Get device info (serial, name, platform, firmware).
     */
    public function getDeviceInfo(BiometricDevice $device): ?array
    {
        try {
            if (!$this->connect($device)) {
                return null;
            }

            $info = [
                'serial_number' => $this->zk->serialNumber(),
                'device_name'   => $this->zk->deviceName(),
                'platform'      => $this->zk->platform(),
                'firmware'      => $this->zk->fmVersion(),
            ];

            $this->disconnect();
            return $info;
        } catch (\Throwable $e) {
            $this->disconnect();
            Log::error("ZKTeco getDeviceInfo failed", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function failHistory(BiometricSyncHistory $history, string $message): BiometricSyncHistory
    {
        $history->update([
            'status'        => 'failed',
            'error_message' => $message,
            'completed_at'  => now(),
        ]);

        Log::error("ZKTeco sync failed for device #{$history->device_id}: {$message}");

        return $history;
    }
}
