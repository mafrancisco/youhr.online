<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single place where biometric punches enter the system.
 *
 * Two transports feed this service:
 *
 *  - ADMS push: the device opens a connection to us and posts tab-separated
 *    ATTLOG lines. Used by firmware that supports push mode (e.g. MB460 Plus).
 *  - On-premise agent: a process on the tenant's LAN polls a device that only
 *    speaks TCP/UDP and posts structured punches to the authenticated API. Needed
 *    because a cloud server cannot reach a device on a private network.
 *
 * Both arrive here so that validation, punch-type handling and de-duplication
 * behave identically regardless of how the data got in.
 *
 * Writes are addressed to an explicit tenant database rather than relying on the
 * active connection, because the ADMS endpoints run without tenant middleware
 * (the device cannot carry a session or a token).
 */
class BiometricLogIngestService
{
    /**
     * Ingest punches already parsed into fields.
     *
     * Each punch: ['pin' => string, 'timestamp' => string|Carbon,
     *              'status' => int|null, 'verify' => int|null]
     *
     * @return array{stored:int, duplicates:int, invalid:int}
     */
    public function ingest(int $deviceId, string $tenantDb, array $punches): array
    {
        $stored = 0;
        $duplicates = 0;
        $invalid = 0;

        foreach ($punches as $punch) {
            $pin = trim((string) ($punch['pin'] ?? ''));
            $rawTimestamp = $punch['timestamp'] ?? null;

            if ($pin === '' || empty($rawTimestamp)) {
                $invalid++;
                continue;
            }

            try {
                $ts = $rawTimestamp instanceof Carbon
                    ? $rawTimestamp
                    : Carbon::parse((string) $rawTimestamp);
            } catch (\Throwable) {
                $invalid++;
                continue;
            }

            $punchType  = $this->normalisePunchType($punch['status'] ?? 0);
            $verifyType = (int) ($punch['verify'] ?? 0);

            $exists = DB::connection('mysql')->select(
                "SELECT id FROM `{$tenantDb}`.`biometric_logs`
                 WHERE device_id = ? AND device_user_id = ? AND timestamp = ? AND punch_type = ?
                 LIMIT 1",
                [$deviceId, $pin, $ts, $punchType]
            );

            if (!empty($exists)) {
                $duplicates++;
                continue;
            }

            DB::connection('mysql')->statement(
                "INSERT INTO `{$tenantDb}`.`biometric_logs`
                 (device_id, device_user_id, timestamp, punch_type, verify_type, is_processed, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 0, NOW(), NOW())",
                [$deviceId, $pin, $ts, $punchType, $verifyType]
            );

            $stored++;
        }

        return ['stored' => $stored, 'duplicates' => $duplicates, 'invalid' => $invalid];
    }

    /**
     * Ingest raw tab-separated ATTLOG lines as sent by a push-mode device.
     *
     * Column layout, confirmed against real device output:
     *   [0]=PIN  [1]=timestamp  [2]=status  [3]=verify  [4..]=workcode/reserved
     *
     * @return array{stored:int, duplicates:int, invalid:int}
     */
    public function ingestAttlog(int $deviceId, string $tenantDb, string $content): array
    {
        $lines = array_filter(array_map('trim', explode("\n", trim($content))));

        // Raw sample, so an unfamiliar firmware's column order can be confirmed
        // from logs rather than guessed at.
        Log::debug("Biometric ATTLOG raw sample for device #{$deviceId}", [
            'lines' => array_slice(
                array_map(fn ($l) => str_replace("\t", '<TAB>', $l), $lines),
                0,
                5
            ),
        ]);

        $punches = [];
        $invalid = 0;

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\t/', $line);

            if (count($parts) < 2) {
                $invalid++;
                continue;
            }

            $punches[] = [
                'pin'       => $parts[0] ?? '',
                'timestamp' => trim($parts[1] ?? ''),
                'status'    => isset($parts[2]) ? (int) trim($parts[2]) : 0,
                'verify'    => isset($parts[3]) ? (int) trim($parts[3]) : 0,
            ];
        }

        $result = $this->ingest($deviceId, $tenantDb, $punches);
        $result['invalid'] += $invalid;

        return $result;
    }

    /**
     * Constrain the device's work-state value to the documented range.
     *
     * BiometricLogProcessorService treats 4 as "OT In" and 5 as "OT Out", and
     * anything else as a regular punch that PunchClassifierService assigns to
     * in/out/break from the employee's schedule. Only 0-5 are meaningful; some
     * firmware sends 255 when no work state was recorded, so out-of-range values
     * become a regular punch instead of being trusted as an attendance state.
     */
    private function normalisePunchType(mixed $status): int
    {
        $value = (int) $status;

        return ($value >= 0 && $value <= 5) ? $value : 0;
    }
}
