<?php

namespace App\Services;

use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use App\Models\BiometricLog;
use App\Models\BiometricSyncHistory;
use App\Services\ZKTecoTcpClient;
use CodingLibs\ZktecoPhp\Libs\ZKTeco;
use Illuminate\Support\Facades\Log;

class ZKTecoService
{
    private ?ZKTeco $zk = null;
    private ?ZKTecoTcpClient $tcpClient = null;
    private string $protocol = 'udp';

    /**
     * Per-protocol handshake timeouts, in seconds.
     *
     * These are deliberately short. A device on the LAN answers in well under a
     * second, so a long wait only means it is never going to answer. The combined
     * worst case must stay comfortably inside PHP's max_execution_time, since the
     * UDP and TCP attempts run back to back.
     */
    private const PROBE_TIMEOUT = 2;
    private const UDP_TIMEOUT   = 3;
    private const TCP_TIMEOUT   = 4;

    /**
     * Cheap TCP reachability check before attempting either protocol handshake.
     *
     * Without this, an unplugged or wrong-IP device costs a full handshake timeout
     * per protocol before reporting failure.
     */
    private function isPortReachable(string $ip, int $port): bool
    {
        $errno = 0;
        $errstr = '';
        $handle = @fsockopen($ip, $port, $errno, $errstr, self::PROBE_TIMEOUT);

        if ($handle === false) {
            return false;
        }

        fclose($handle);

        return true;
    }

    /**
     * Test connection to a device.
     */
    public function testConnection(string $ip, int $port = 4370): array
    {
        if (!$this->isPortReachable($ip, $port)) {
            return [
                'success' => false,
                'message' => "Cannot reach {$ip}:{$port}. Check that the device is powered on, on the same network, and that the IP and port are correct.",
            ];
        }

        // Try UDP first (legacy devices)
        try {
            $zk = new ZKTeco($ip, $port, false, self::UDP_TIMEOUT);
            $connected = $zk->connect();

            if ($connected) {
                $serialNumber = $zk->serialNumber() ?: 'Unknown';
                $deviceName = $zk->deviceName() ?: 'Unknown';
                $zk->disconnect();

                return [
                    'success'       => true,
                    'message'       => 'Connection successful (UDP).',
                    'serial_number' => $serialNumber,
                    'device_name'   => $deviceName,
                    'protocol'      => 'udp',
                ];
            }
        } catch (\Throwable $e) {
            // UDP failed, try TCP
        }

        // Try TCP (newer devices like MB460 Plus)
        try {
            $tcp = new ZKTecoTcpClient($ip, $port, self::TCP_TIMEOUT);
            $connected = $tcp->connect();

            if ($connected) {
                $serialNumber = $tcp->serialNumber() ?: 'Unknown';
                $deviceName = $tcp->deviceName() ?: 'Unknown';
                $tcp->disconnect();

                return [
                    'success'       => true,
                    'message'       => 'Connection successful (TCP).',
                    'serial_number' => $serialNumber,
                    'device_name'   => $deviceName,
                    'protocol'      => 'tcp',
                ];
            }

            // The port accepted a connection but the device ignored both protocol
            // handshakes. That is the signature of a push-mode (ADMS) device, which
            // sends data to this server instead of answering polling requests.
            return [
                'success' => false,
                'message' => "Reached {$ip}:{$port}, but the device did not respond to polling. If this device uses ADMS/push mode, it sends data to the server instead — configure its server address to this application and no polling is needed.",
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
        // Fail fast when the device is unreachable, rather than paying a full
        // handshake timeout for each protocol.
        if (!$this->isPortReachable($device->ip_address, $device->port)) {
            $device->update(['is_online' => false]);
            return false;
        }

        // Try UDP first (legacy devices)
        try {
            $this->zk = new ZKTeco($device->ip_address, $device->port, false, self::UDP_TIMEOUT);
            $connected = $this->zk->connect();

            if ($connected) {
                $this->protocol = 'udp';
                $device->update(['is_online' => true]);
                return true;
            }
        } catch (\Throwable) {
            // UDP failed
        }

        // Try TCP (newer devices like MB460 Plus)
        try {
            $this->tcpClient = new ZKTecoTcpClient($device->ip_address, $device->port, self::TCP_TIMEOUT);
            $connected = $this->tcpClient->connect();

            if ($connected) {
                $this->protocol = 'tcp';
                $device->update(['is_online' => true]);
                return true;
            }
        } catch (\Throwable $e) {
            Log::error("ZKTeco connect failed: {$device->ip_address}", ['error' => $e->getMessage()]);
        }

        $device->update(['is_online' => false]);
        return false;
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
        if ($this->tcpClient) {
            try {
                $this->tcpClient->disconnect();
            } catch (\Throwable) {
                // Ignore disconnect errors
            }
            $this->tcpClient = null;
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

            $users = $this->protocol === 'tcp'
                ? $this->tcpClient->getUsers()
                : $this->zk->getUsers();
            $this->disconnect();

            if (!is_array($users)) {
                return $this->failHistory($history, 'Failed to retrieve users from device.');
            }

            $fetched = count($users);
            $new = 0;
            $skipped = 0;

            foreach ($users as $user) {
                $uid    = (string) ($user['uid'] ?? '');
                $userId = (string) ($user['user_id'] ?? '');
                $name   = trim($user['name'] ?? '');
                $role   = (int) ($user['role'] ?? 0);

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

            $logs = $this->protocol === 'tcp'
                ? $this->tcpClient->getAttendances()
                : $this->zk->getAttendances();
            $this->disconnect();

            if (!is_array($logs)) {
                return $this->failHistory($history, 'Failed to retrieve attendance logs from device.');
            }

            $fetched = count($logs);
            $new = 0;
            $skipped = 0;

            foreach ($logs as $log) {
                // Library returns: uid, user_id, state, record_time, type, device_ip
                $userId    = (string) ($log['user_id'] ?? '');
                $timestamp = $log['record_time'] ?? null;
                $state     = (int) ($log['state'] ?? 0);
                $type      = (int) ($log['type'] ?? 0);

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
                        'punch_type'     => $state,
                    ],
                    [
                        'verify_type'  => $type,
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
