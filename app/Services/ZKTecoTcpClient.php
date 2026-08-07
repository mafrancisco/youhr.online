<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * ZKTeco TCP Protocol Client
 *
 * Supports newer ZKTeco devices (MB460 Plus, etc.) that communicate over TCP
 * instead of the older UDP protocol.
 */
class ZKTecoTcpClient
{
    private $socket = null;
    private int $sessionId = 0;
    private int $replyId = 0;
    private string $ip;
    private int $port;
    private int $timeout;

    // ZKTeco protocol commands
    private const CMD_CONNECT = 1000;
    private const CMD_EXIT = 1001;
    private const CMD_ENABLEDEVICE = 1002;
    private const CMD_DISABLEDEVICE = 1003;
    private const CMD_GET_SERIALNUMBER = 1534;
    private const CMD_GET_DEVICENAME = 11;
    private const CMD_GET_PLATFORM = 1500;
    private const CMD_GET_FMVERSION = 1502;
    private const CMD_USERTEMP_RRQ = 9;
    private const CMD_ATTLOG_RRQ = 13;
    private const CMD_ACK_OK = 2000;
    private const CMD_ACK_UNAUTH = 2002;
    private const CMD_ACK_DATA = 2002;
    private const CMD_PREPARE_DATA = 1500;
    private const CMD_DATA = 1501;
    private const CMD_ACK_AUTH = 76;

    // TCP header magic bytes
    private const TCP_HEADER = "\x50\x50\x82\x7d";
    private const TCP_HEADER_SIZE = 8;
    private const PAYLOAD_HEADER_SIZE = 8;

    public function __construct(string $ip, int $port = 4370, int $timeout = 10)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->timeout = $timeout;
    }

    public function connect(int $password = 0): bool
    {
        try {
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if (!$this->socket) {
                return false;
            }

            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
            socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);

            $connected = @socket_connect($this->socket, $this->ip, $this->port);
            if (!$connected) {
                return false;
            }

            // Send connect command
            $reply = $this->sendCommand(self::CMD_CONNECT);
            if ($reply === false) {
                return false;
            }

            $cmd = $this->getResponseCommand($reply);

            // Handle authentication if required
            if ($cmd === self::CMD_ACK_UNAUTH && $password > 0) {
                $commKey = $this->makeCommKey($password, $this->sessionId);
                $reply = $this->sendCommand(self::CMD_ACK_AUTH, $commKey);
                if ($reply === false) {
                    return false;
                }
                $cmd = $this->getResponseCommand($reply);
            }

            return $cmd === self::CMD_ACK_OK;
        } catch (\Throwable $e) {
            Log::warning("ZKTeco TCP connect failed: {$this->ip}:{$this->port}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function disconnect(): bool
    {
        if (!$this->socket) {
            return true;
        }

        try {
            $this->sendCommand(self::CMD_EXIT);
        } catch (\Throwable) {
            // Ignore disconnect errors
        }

        @socket_close($this->socket);
        $this->socket = null;
        $this->sessionId = 0;
        return true;
    }

    public function serialNumber(): string
    {
        $reply = $this->sendCommand(self::CMD_GET_SERIALNUMBER);
        if ($reply === false) {
            return '';
        }
        return $this->extractString($reply);
    }

    public function deviceName(): string
    {
        $reply = $this->sendCommand(self::CMD_GET_DEVICENAME, "~DeviceName\x00");
        if ($reply === false) {
            return '';
        }
        $str = $this->extractString($reply);
        // Response format: "DeviceName=VALUE"
        $parts = explode('=', $str, 2);
        return $parts[1] ?? $str;
    }

    public function platform(): string
    {
        $reply = $this->sendCommand(self::CMD_GET_PLATFORM);
        if ($reply === false) {
            return '';
        }
        return $this->extractString($reply);
    }

    public function fmVersion(): string
    {
        $reply = $this->sendCommand(self::CMD_GET_FMVERSION);
        if ($reply === false) {
            return '';
        }
        return $this->extractString($reply);
    }

    /**
     * Get all users from the device.
     */
    public function getUsers(): array
    {
        $data = $this->readLargeData(self::CMD_USERTEMP_RRQ);
        if ($data === false || strlen($data) === 0) {
            return [];
        }

        $users = [];
        $recordSize = 72; // Standard user record size for newer devices

        // Try to detect record size
        if (strlen($data) >= 4) {
            // First 4 bytes might be total count
            $possibleCount = unpack('V', substr($data, 0, 4))[1];
            if ($possibleCount > 0 && $possibleCount < 10000) {
                $remaining = strlen($data) - 4;
                if ($remaining > 0 && $remaining % $possibleCount === 0) {
                    $recordSize = intval($remaining / $possibleCount);
                    $data = substr($data, 4);
                }
            }
        }

        $pos = 0;
        while ($pos + $recordSize <= strlen($data)) {
            $record = substr($data, $pos, $recordSize);
            $pos += $recordSize;

            if ($recordSize === 72) {
                $uid = unpack('v', substr($record, 0, 2))[1];
                $role = ord($record[2]);
                $userId = rtrim(substr($record, 3, 9), "\x00");
                $name = rtrim(substr($record, 11, 24), "\x00");
            } elseif ($recordSize === 28) {
                $uid = unpack('v', substr($record, 0, 2))[1];
                $role = ord($record[2]);
                $userId = rtrim(substr($record, 3, 9), "\x00");
                $name = rtrim(substr($record, 11, 16), "\x00");
            } else {
                // Try generic parsing
                $uid = unpack('v', substr($record, 0, 2))[1];
                $role = ord($record[2]);
                $userId = rtrim(substr($record, 3, 9), "\x00");
                $name = rtrim(substr($record, 11, min(24, $recordSize - 11)), "\x00");
            }

            if (!empty($userId)) {
                $users[] = [
                    'uid' => $uid,
                    'user_id' => $userId,
                    'name' => $name ?: "User {$userId}",
                    'role' => $role,
                ];
            }
        }

        return $users;
    }

    /**
     * Get attendance logs from the device.
     */
    public function getAttendances(): array
    {
        $data = $this->readLargeData(self::CMD_ATTLOG_RRQ);
        if ($data === false || strlen($data) === 0) {
            return [];
        }

        $logs = [];
        $recordSize = 16; // Standard attendance record size

        // Detect record size
        if (strlen($data) >= 4) {
            $possibleCount = unpack('V', substr($data, 0, 4))[1];
            if ($possibleCount > 0 && $possibleCount < 1000000) {
                $remaining = strlen($data) - 4;
                if ($remaining > 0 && $remaining % $possibleCount === 0) {
                    $recordSize = intval($remaining / $possibleCount);
                    $data = substr($data, 4);
                }
            }
        }

        $pos = 0;
        while ($pos + $recordSize <= strlen($data)) {
            $record = substr($data, $pos, $recordSize);
            $pos += $recordSize;

            if ($recordSize === 16) {
                $userId = rtrim(substr($record, 0, 9), "\x00");
                $timestamp = unpack('V', substr($record, 4, 4))[1] ?? 0;
                $state = ord($record[10] ?? "\x00");
                $type = ord($record[11] ?? "\x00");
            } elseif ($recordSize === 40) {
                // Newer device format
                $userId = rtrim(substr($record, 0, 9), "\x00");
                $timestamp = unpack('V', substr($record, 24, 4))[1] ?? 0;
                $state = ord($record[28] ?? "\x00");
                $type = ord($record[30] ?? "\x00");
            } else {
                // Try generic parsing
                $userId = rtrim(substr($record, 0, 9), "\x00");
                $timestamp = unpack('V', substr($record, max(4, $recordSize - 12), 4))[1] ?? 0;
                $state = 0;
                $type = 0;
            }

            if (empty($userId) || $timestamp === 0) {
                continue;
            }

            // Decode ZKTeco timestamp (seconds since 2000-01-01)
            $recordTime = $this->decodeTimestamp($timestamp);
            if (!$recordTime) {
                continue;
            }

            $logs[] = [
                'uid' => $userId,
                'user_id' => $userId,
                'state' => $state,
                'record_time' => $recordTime,
                'type' => $type,
            ];
        }

        return $logs;
    }

    /**
     * Read large data from device (handles multi-packet responses).
     */
    private function readLargeData(int $command): string|false
    {
        $reply = $this->sendCommand($command);
        if ($reply === false) {
            return false;
        }

        $cmd = $this->getResponseCommand($reply);

        // If we get CMD_PREPARE_DATA, the device will send data in chunks
        if ($cmd === self::CMD_PREPARE_DATA || strlen($reply) > self::PAYLOAD_HEADER_SIZE) {
            // Get total data size from response
            $dataSize = 0;
            if (strlen($reply) >= self::PAYLOAD_HEADER_SIZE + 4) {
                $dataSize = unpack('V', substr($reply, self::PAYLOAD_HEADER_SIZE, 4))[1];
            }

            $data = '';
            while (strlen($data) < $dataSize) {
                $chunk = $this->receiveRawTcp();
                if ($chunk === false) {
                    break;
                }
                // Skip TCP header and get payload
                if (strlen($chunk) > self::TCP_HEADER_SIZE) {
                    $payload = substr($chunk, self::TCP_HEADER_SIZE);
                    $payloadCmd = unpack('v', substr($payload, 0, 2))[1] ?? 0;
                    if ($payloadCmd === self::CMD_DATA) {
                        $data .= substr($payload, self::PAYLOAD_HEADER_SIZE);
                    } else {
                        $data .= substr($payload, self::PAYLOAD_HEADER_SIZE);
                    }
                }
            }

            // Send ACK
            $this->sendCommand(self::CMD_ACK_OK);

            return $data;
        }

        // Small data in single response
        if (strlen($reply) > self::PAYLOAD_HEADER_SIZE) {
            return substr($reply, self::PAYLOAD_HEADER_SIZE);
        }

        return '';
    }

    private function sendCommand(int $command, string $data = ''): string|false
    {
        if (!$this->socket) {
            return false;
        }

        $this->replyId++;

        // Build payload: command(2) + checksum(2) + session_id(2) + reply_id(2) + data
        $buf = pack('vvvv', $command, 0, $this->sessionId, $this->replyId) . $data;

        // Calculate and set checksum
        $chksum = $this->calcChecksum($buf);
        $buf = pack('vvvv', $command, $chksum, $this->sessionId, $this->replyId) . $data;

        // Wrap in TCP header: magic(4) + payload_length(4) + payload
        $tcpPacket = self::TCP_HEADER . pack('V', strlen($buf)) . $buf;

        $sent = @socket_send($this->socket, $tcpPacket, strlen($tcpPacket), 0);
        if ($sent === false) {
            return false;
        }

        // Receive response
        $response = $this->receiveRawTcp();
        if ($response === false) {
            return false;
        }

        // Strip TCP header to get payload
        if (strlen($response) > self::TCP_HEADER_SIZE) {
            $payload = substr($response, self::TCP_HEADER_SIZE);

            // Extract session ID from response
            if (strlen($payload) >= 8) {
                $header = unpack('vcmd/vchecksum/vsession/vreply', substr($payload, 0, 8));
                if (!empty($header['session'])) {
                    $this->sessionId = $header['session'];
                }
            }

            return $payload;
        }

        return false;
    }

    private function receiveRawTcp(): string|false
    {
        // Read TCP header first (8 bytes: 4 magic + 4 length)
        $header = '';
        $remaining = self::TCP_HEADER_SIZE;
        while ($remaining > 0) {
            $chunk = '';
            $r = @socket_recv($this->socket, $chunk, $remaining, 0);
            if ($r === false || $r === 0) {
                return false;
            }
            $header .= $chunk;
            $remaining -= $r;
        }

        // Get payload length from TCP header
        $payloadLen = unpack('V', substr($header, 4, 4))[1];
        if ($payloadLen <= 0 || $payloadLen > 1048576) {
            return false;
        }

        // Read payload
        $payload = '';
        $remaining = $payloadLen;
        while ($remaining > 0) {
            $chunk = '';
            $r = @socket_recv($this->socket, $chunk, min($remaining, 4096), 0);
            if ($r === false || $r === 0) {
                break;
            }
            $payload .= $chunk;
            $remaining -= $r;
        }

        return $header . $payload;
    }

    private function getResponseCommand(string $payload): int
    {
        if (strlen($payload) < 2) {
            return 0;
        }
        return unpack('v', substr($payload, 0, 2))[1];
    }

    private function extractString(string $payload): string
    {
        if (strlen($payload) <= self::PAYLOAD_HEADER_SIZE) {
            return '';
        }
        $data = substr($payload, self::PAYLOAD_HEADER_SIZE);
        return rtrim($data, "\x00");
    }

    private function calcChecksum(string $buf): int
    {
        // Zero out the checksum field for calculation
        $buf[2] = "\x00";
        $buf[3] = "\x00";

        $sum = 0;
        $len = strlen($buf);

        // Sum 16-bit words
        for ($i = 0; $i < $len; $i += 2) {
            if ($i + 1 < $len) {
                $sum += unpack('v', substr($buf, $i, 2))[1];
            } else {
                $sum += ord($buf[$i]);
            }
        }

        // Fold into 16 bits
        while ($sum > 0xFFFF) {
            $sum = ($sum & 0xFFFF) + ($sum >> 16);
        }

        return (~$sum) & 0xFFFF;
    }

    private function makeCommKey(int $key, int $sessionId): string
    {
        $k = 0;
        for ($i = 0; $i < 32; $i++) {
            if (($key >> $i) & 1) {
                $k = ($k << 1) | 1;
            } else {
                $k = $k << 1;
            }
        }
        $k += $sessionId;
        $k = pack('V', $k);
        return $k;
    }

    private function decodeTimestamp(int $timestamp): ?string
    {
        // ZKTeco encodes time as seconds since 2000-01-01 00:00:00
        // Format: ((year-2000)*12*31+month*31+day)*(24*60*60) + hour*60*60 + minute*60 + second
        $second = $timestamp % 60;
        $timestamp = intval($timestamp / 60);
        $minute = $timestamp % 60;
        $timestamp = intval($timestamp / 60);
        $hour = $timestamp % 24;
        $timestamp = intval($timestamp / 24);
        $day = ($timestamp % 31) + 1;
        $timestamp = intval($timestamp / 31);
        $month = ($timestamp % 12) + 1;
        $year = intval($timestamp / 12) + 2000;

        try {
            return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isConnected(): bool
    {
        return $this->socket !== null && $this->sessionId > 0;
    }
}
