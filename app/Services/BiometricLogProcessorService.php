<?php

namespace App\Services;

use App\Models\BiometricEmployeeMapping;
use App\Models\BiometricLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BiometricLogProcessorService
{
    public function __construct(private PunchClassifierService $classifier) {}

    /**
     * Process unprocessed biometric logs into attendance_clean records.
     *
     * Badge ID resolution strategy:
     *  - When $requireMapping = false (default): device_user_id IS the badgeID.
     *    This is the "Sync Logs" path — no mapping table needed.
     *  - When $requireMapping = true: uses BiometricEmployeeMapping to translate
     *    device_user_id → badgeID.  This is the legacy "Process Logs" path.
     *
     * Both paths now:
     *  1. Seed attendance_clean for the full date range (absent days get blank rows).
     *  2. Classify punches via PunchClassifierService (shared algorithm).
     *  3. Write via DELETE + INSERT (resets OTIn/OTOut).
     *
     * $reprocessAll controls whether already-processed logs are included. The stored
     * logs are the source of truth and attendance_clean is derived from them, so a
     * rebuild must be able to re-read logs that were processed earlier — otherwise
     * changing a schedule or a Time Detection Rule leaves attendance impossible to
     * regenerate. is_processed is retained as a "seen" marker for incremental runs
     * rather than as a consumed-once flag.
     *
     * The whole range is written inside a transaction, and is_processed is only
     * advanced once every group has succeeded, so a mid-range failure cannot leave
     * attendance partially rebuilt with logs already marked as done.
     *
     * @param string $startDate     Y-m-d
     * @param string $endDate       Y-m-d
     * @param bool   $requireMapping  true = use mapping table, false = device_user_id = badgeID
     * @param bool   $reprocessAll    true = include already-processed logs (full rebuild)
     * @return array ['processed' => int, 'skipped' => int, 'message' => string]
     */
    public function process(string $startDate, string $endDate, bool $requireMapping = false, bool $reprocessAll = false): array
    {
        $this->classifier->loadRules();
        $this->classifier->loadSchedules();

        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->startOfDay();

        // Build device_user_id → badgeID map when mapping is required
        $userToBadge = [];
        if ($requireMapping) {
            $mappings = BiometricEmployeeMapping::with('deviceUser')->get();
            foreach ($mappings as $map) {
                if ($map->deviceUser) {
                    $userToBadge[(string) $map->deviceUser->user_id] = $map->badge_id;
                }
            }

            if (empty($userToBadge)) {
                return [
                    'processed' => 0,
                    'skipped'   => 0,
                    'message'   => 'No employee mappings found. Map biometric users to employees first.',
                ];
            }
        }

        // Fetch logs in the date range. A rebuild reads every stored log; an
        // incremental run only picks up ones not yet seen.
        $logs = BiometricLog::when(!$reprocessAll, fn($q) => $q->where('is_processed', false))
            ->whereBetween('timestamp', [$start, $end->copy()->endOfDay()])
            ->orderBy('timestamp')
            ->get();

        if ($logs->isEmpty()) {
            return [
                'processed' => 0,
                'skipped'   => 0,
                'message'   => $reprocessAll
                    ? 'No logs stored in the date range.'
                    : 'No unprocessed logs found in the date range.',
            ];
        }

        // Group logs by badgeID + date
        $grouped = [];
        $skipped = 0;

        foreach ($logs as $log) {
            $deviceUserId = (string) $log->device_user_id;

            if ($requireMapping) {
                $badgeID = $userToBadge[$deviceUserId] ?? null;
                if (!$badgeID) {
                    $skipped++;
                    continue;
                }
            } else {
                // device_user_id IS the badgeID — verify employee exists
                $badgeID  = $deviceUserId;
                $empExists = DB::selectOne("SELECT badgeID FROM employees WHERE badgeID = ? LIMIT 1", [$badgeID]);
                if (!$empExists) {
                    $skipped++;
                    continue;
                }
            }

            $attDate = $log->timestamp->format('m/d/Y');
            $key     = "{$badgeID}|{$attDate}";

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'badgeID' => $badgeID,
                    'attDate' => $attDate,
                    'dateYmd' => $log->timestamp->format('Y-m-d'),
                    'punches' => [],
                    'logIds'  => [],
                    'otIn'    => '',   // device-reported OT In (punch_type=4)
                    'otOut'   => '',   // device-reported OT Out (punch_type=5)
                ];
            }

            // Separate OT punches from regular punches
            if ($log->punch_type === 4) {
                $grouped[$key]['otIn'] = $log->timestamp->format('H:i');
            } elseif ($log->punch_type === 5) {
                $grouped[$key]['otOut'] = $log->timestamp->format('H:i');
            } else {
                $grouped[$key]['punches'][] = [
                    'time'      => $log->timestamp->format('H:i'),
                    'timestamp' => $log->timestamp,
                ];
            }
            $grouped[$key]['logIds'][] = $log->id;
        }

        if (empty($grouped)) {
            return ['processed' => 0, 'skipped' => $skipped, 'message' => "0 logs processed, {$skipped} skipped."];
        }

        // Everything that writes runs inside one transaction so a failure part-way
        // through the range cannot leave attendance half-rebuilt.
        $processed = 0;
        $processedLogIds = [];

        DB::transaction(function () use ($grouped, $startDate, $endDate, &$processed, &$skipped, &$processedLogIds) {
            // Seed full date range for every affected employee so absent days get blank rows
            $uniqueBadges = array_unique(array_column(array_values($grouped), 'badgeID'));

            foreach ($uniqueBadges as $badgeID) {
                $emp = DB::selectOne("SELECT schedule FROM employees WHERE badgeID = ? LIMIT 1", [$badgeID]);
                if (!$emp) continue;

                $punchDates = array_map(
                    fn($g) => $g['attDate'],
                    array_filter(array_values($grouped), fn($g) => $g['badgeID'] === $badgeID)
                );

                $this->classifier->seedDateRange($badgeID, $startDate, $endDate, $punchDates);
            }

            // Classify and write each badge+date group
            foreach ($grouped as $group) {
                $badgeID = $group['badgeID'];
                $attDate = $group['attDate'];
                $dateYmd = $group['dateYmd'];
                $punches = $group['punches'];

                $emp = DB::selectOne("SELECT schedule FROM employees WHERE badgeID = ? LIMIT 1", [$badgeID]);
                if (!$emp || !$emp->schedule) {
                    $skipped += count($group['logIds']);
                    continue;
                }

                // Sort punches ascending before classifying
                usort($punches, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);

                // classifyAndWrite: DELETE + INSERT, resets OTIn/OTOut
                if (!empty($punches)) {
                    $this->classifier->classifyAndWrite($badgeID, $attDate, $dateYmd, $emp->schedule, $punches);
                }

                // Write device-reported OT punches (must run AFTER classifyAndWrite)
                if ($group['otIn'] !== '' || $group['otOut'] !== '') {
                    DB::update(
                        "UPDATE attendance_clean SET OTIn = ?, OTOut = ? WHERE BadgeNumber = ? AND AttDate = ?",
                        [$group['otIn'], $group['otOut'], $badgeID, $attDate]
                    );
                }

                // Defer the is_processed flip until the whole range has been written.
                $processedLogIds = array_merge($processedLogIds, $group['logIds']);
                $processed += count($group['logIds']);
            }

            // Only now that every group succeeded do the logs get marked as seen.
            if (!empty($processedLogIds)) {
                BiometricLog::whereIn('id', $processedLogIds)->update(['is_processed' => true]);
            }
        });

        return [
            'processed' => $processed,
            'skipped'   => $skipped,
            'message'   => "{$processed} logs processed, {$skipped} skipped.",
        ];
    }
}
