<?php

namespace App\Services;

use App\Models\BiometricEmployeeMapping;
use App\Models\BiometricLog;
use App\Models\TimeDetectionSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BiometricLogProcessorService
{
    private array $rules = [];
    private array $schedules = [];
    private array $dayMap = [
        'mon' => 'm', 'tue' => 't', 'wed' => 'w', 'thu' => 'th',
        'fri' => 'f', 'sat' => 'sat', 'sun' => 'sun',
    ];

    /**
     * Process unprocessed biometric logs into attendance_clean records.
     * device_user_id IS the badgeID — no mapping needed.
     * Uses Time Detection Rules to classify punches by schedule.
     */
    public function processDirectByBadge(string $startDate, string $endDate): array
    {
        $this->loadRules();
        $this->loadSchedules();

        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->startOfDay();

        // Get unprocessed logs within date range
        $logs = BiometricLog::where('is_processed', false)
            ->whereBetween('timestamp', [$start, $end->copy()->endOfDay()])
            ->orderBy('timestamp')
            ->get();

        if ($logs->isEmpty()) {
            return ['processed' => 0, 'skipped' => 0, 'message' => 'No unprocessed logs found in the date range.'];
        }

        // Group logs by badgeID (device_user_id) + date
        $grouped = [];
        $skipped = 0;

        foreach ($logs as $log) {
            $badgeID = (string) $log->device_user_id;

            // Verify employee exists
            $empExists = DB::selectOne("SELECT badgeID FROM employees WHERE badgeID = ?", [$badgeID]);
            if (!$empExists) {
                $skipped++;
                continue;
            }

            $date = $log->timestamp->format('m/d/Y');
            $key  = "{$badgeID}|{$date}";

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'badgeID' => $badgeID,
                    'attDate' => $date,
                    'dateYmd' => $log->timestamp->format('Y-m-d'),
                    'punches' => [],
                    'logIds'  => [],
                ];
            }

            $grouped[$key]['punches'][] = [
                'time'      => $log->timestamp->format('H:i'),
                'timestamp' => $log->timestamp,
            ];
            $grouped[$key]['logIds'][] = $log->id;
        }

        // Process each employee-date group
        $processed = 0;

        foreach ($grouped as $group) {
            $badgeID = $group['badgeID'];
            $attDate = $group['attDate'];
            $dateYmd = $group['dateYmd'];
            $punches = $group['punches'];

            // Get employee schedule
            $emp = DB::selectOne("SELECT schedule FROM employees WHERE badgeID = ?", [$badgeID]);
            if (!$emp || !$emp->schedule) {
                $skipped += count($group['logIds']);
                continue;
            }

            $dayName   = strtolower(date('D', strtotime($dateYmd)));
            $dayPrefix = $this->dayMap[$dayName] ?? '';
            if (!$dayPrefix) {
                $skipped += count($group['logIds']);
                continue;
            }

            $sched = $this->schedules[$emp->schedule] ?? null;
            if (!$sched) {
                $skipped += count($group['logIds']);
                continue;
            }

            $schedTimein   = $sched->{$dayPrefix . '_timein'} ?? '';
            $schedBreakout = $sched->{$dayPrefix . '_breakout'} ?? '';
            $schedBreakin  = $sched->{$dayPrefix . '_breakin'} ?? '';
            $schedTimeout  = $sched->{$dayPrefix . '_timeout'} ?? '';

            // Sort punches by time
            usort($punches, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);

            // Sequential pairing with Time Detection Rules
            $pool = $punches;

            $startTime1 = $this->detectPunchFromPool($pool, $schedTimein, $dateYmd, 'timein');
            if ($startTime1) $pool = $this->removePunchFromPool($pool, $startTime1);

            $startTime2 = $this->detectPunchFromPool($pool, $schedBreakout, $dateYmd, 'breakout');
            if ($startTime2) $pool = $this->removePunchFromPool($pool, $startTime2);

            // Break In: first punch after Break Out
            $startTime3 = '';
            if ($startTime2) {
                $breakOutTs = Carbon::parse("$dateYmd $startTime2");
                $afterBreakout = array_filter($pool, fn($p) => $p['timestamp']->gt($breakOutTs));

                if (!empty($afterBreakout)) {
                    $rule = $this->rules['breakin'] ?? null;
                    if ($rule) {
                        $scheduledTs = Carbon::parse("$dateYmd $schedBreakin");
                        $windowEnd = $scheduledTs->copy()->addMinutes($rule['after_minutes']);
                        $filtered = array_filter($afterBreakout, fn($p) => $p['timestamp']->lte($windowEnd));

                        if (!empty($filtered)) {
                            usort($filtered, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);
                            $startTime3 = reset($filtered)['time'];
                        } else {
                            $sorted = array_values($afterBreakout);
                            usort($sorted, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);
                            $startTime3 = $sorted[0]['time'];
                        }
                    } else {
                        $sorted = array_values($afterBreakout);
                        usort($sorted, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);
                        $startTime3 = $sorted[0]['time'];
                    }
                }
            } else {
                $startTime3 = $this->detectPunchFromPool($pool, $schedBreakin, $dateYmd, 'breakin');
            }
            if ($startTime3) $pool = $this->removePunchFromPool($pool, $startTime3);

            $startTime4 = $this->detectPunchFromPool($pool, $schedTimeout, $dateYmd, 'timeout');

            // Handle weekends
            $dayOfWeek = Carbon::parse($dateYmd)->dayOfWeekIso;
            if (($dayOfWeek == 6 || $dayOfWeek == 7) && !$startTime1 && !$startTime4) {
                $label = $dayOfWeek == 6 ? 'Saturday' : 'Sunday';
                $startTime1 = $label;
                $startTime2 = $label;
                $startTime3 = $label;
                $startTime4 = $label;
            }

            // Upsert into attendance_clean (delete old + insert fresh)
            DB::delete("DELETE FROM attendance_clean WHERE BadgeNumber = ? AND AttDate = ?", [$badgeID, $attDate]);
            DB::insert("
                INSERT INTO attendance_clean (BadgeNumber, AttDate, startTime1, startTime2, startTime3, startTime4)
                VALUES (?, ?, ?, ?, ?, ?)
            ", [$badgeID, $attDate, $startTime1 ?: '', $startTime2 ?: '', $startTime3 ?: '', $startTime4 ?: '']);

            // Mark logs as processed
            BiometricLog::whereIn('id', $group['logIds'])->update(['is_processed' => true]);
            $processed += count($group['logIds']);
        }

        return [
            'processed' => $processed,
            'skipped'   => $skipped,
            'message'   => "{$processed} logs processed, {$skipped} skipped.",
        ];
    }

    /**
     * Process unprocessed biometric logs into attendance_clean records.
     * Uses Time Detection Rules to classify punches by schedule.
     *
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @return array ['processed' => int, 'skipped' => int]
     */
    public function process(string $startDate, string $endDate): array
    {
        $this->loadRules();
        $this->loadSchedules();

        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->startOfDay();

        // Get all employee mappings (biometric user_id → badgeID)
        $mappings = BiometricEmployeeMapping::with('deviceUser')->get();
        $userToBadge = [];
        foreach ($mappings as $map) {
            if ($map->deviceUser) {
                $userToBadge[(string) $map->deviceUser->user_id] = $map->badge_id;
            }
        }

        if (empty($userToBadge)) {
            return ['processed' => 0, 'skipped' => 0, 'message' => 'No employee mappings found. Map biometric users to employees first.'];
        }

        // Get unprocessed logs within date range
        $logs = BiometricLog::where('is_processed', false)
            ->whereBetween('timestamp', [$start, $end->copy()->endOfDay()])
            ->orderBy('timestamp')
            ->get();

        if ($logs->isEmpty()) {
            return ['processed' => 0, 'skipped' => 0, 'message' => 'No unprocessed logs found in the date range.'];
        }

        // Group logs by employee badge + date
        $grouped = [];
        $skipped = 0;

        foreach ($logs as $log) {
            $deviceUserId = (string) $log->device_user_id;

            // Map to employee badge
            $badgeID = $userToBadge[$deviceUserId] ?? null;
            if (!$badgeID) {
                $skipped++;
                continue;
            }

            $date = $log->timestamp->format('m/d/Y');
            $key  = "{$badgeID}|{$date}";

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'badgeID' => $badgeID,
                    'attDate' => $date,
                    'dateYmd' => $log->timestamp->format('Y-m-d'),
                    'punches' => [],
                    'logIds'  => [],
                ];
            }

            $grouped[$key]['punches'][] = [
                'time'      => $log->timestamp->format('H:i'),
                'timestamp' => $log->timestamp,
            ];
            $grouped[$key]['logIds'][] = $log->id;
        }

        // Process each employee-date group
        $processed = 0;

        foreach ($grouped as $group) {
            $badgeID = $group['badgeID'];
            $attDate = $group['attDate'];
            $dateYmd = $group['dateYmd'];
            $punches = $group['punches'];

            // Get employee schedule
            $emp = DB::selectOne("SELECT schedule FROM employees WHERE badgeID = ?", [$badgeID]);
            if (!$emp || !$emp->schedule) {
                $skipped += count($group['logIds']);
                continue;
            }

            $dayName   = strtolower(date('D', strtotime($dateYmd)));
            $dayPrefix = $this->dayMap[$dayName] ?? '';
            if (!$dayPrefix) {
                $skipped += count($group['logIds']);
                continue;
            }

            $sched = $this->schedules[$emp->schedule] ?? null;
            if (!$sched) {
                $skipped += count($group['logIds']);
                continue;
            }

            $schedTimein   = $sched->{$dayPrefix . '_timein'} ?? '';
            $schedBreakout = $sched->{$dayPrefix . '_breakout'} ?? '';
            $schedBreakin  = $sched->{$dayPrefix . '_breakin'} ?? '';
            $schedTimeout  = $sched->{$dayPrefix . '_timeout'} ?? '';

            // Sort punches by time
            usort($punches, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);

            // Sequential pairing with Time Detection Rules:
            // Each step removes the used punch from the pool
            $pool = $punches;

            // Step 1: Time In (earliest in window)
            $startTime1 = $this->detectPunchFromPool($pool, $schedTimein, $dateYmd, 'timein');
            if ($startTime1) $pool = $this->removePunchFromPool($pool, $startTime1);

            // Step 2: Break Out (latest in window from remaining)
            $startTime2 = $this->detectPunchFromPool($pool, $schedBreakout, $dateYmd, 'breakout');
            if ($startTime2) $pool = $this->removePunchFromPool($pool, $startTime2);

            // Step 3: Break In — first punch AFTER Break Out
            $startTime3 = '';
            if ($startTime2) {
                $breakOutTs = Carbon::parse("$dateYmd $startTime2");
                $afterBreakout = array_filter($pool, fn($p) => $p['timestamp']->gt($breakOutTs));

                if (!empty($afterBreakout)) {
                    $rule = $this->rules['breakin'] ?? null;
                    if ($rule) {
                        $scheduledTs = Carbon::parse("$dateYmd $schedBreakin");
                        $windowEnd = $scheduledTs->copy()->addMinutes($rule['after_minutes']);
                        $filtered = array_filter($afterBreakout, fn($p) => $p['timestamp']->lte($windowEnd));

                        if (!empty($filtered)) {
                            usort($filtered, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);
                            $startTime3 = reset($filtered)['time'];
                        } else {
                            $sorted = array_values($afterBreakout);
                            usort($sorted, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);
                            $startTime3 = $sorted[0]['time'];
                        }
                    } else {
                        $sorted = array_values($afterBreakout);
                        usort($sorted, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);
                        $startTime3 = $sorted[0]['time'];
                    }
                }
            } else {
                $startTime3 = $this->detectPunchFromPool($pool, $schedBreakin, $dateYmd, 'breakin');
            }
            if ($startTime3) $pool = $this->removePunchFromPool($pool, $startTime3);

            // Step 4: Time Out (latest in window from remaining)
            $startTime4 = $this->detectPunchFromPool($pool, $schedTimeout, $dateYmd, 'timeout');

            // Check if weekend
            $dayOfWeek = Carbon::parse($dateYmd)->dayOfWeekIso;
            if ($dayOfWeek == 6 || $dayOfWeek == 7) {
                $label = $dayOfWeek == 6 ? 'Saturday' : 'Sunday';
                // Still store the record but with weekend labels if no punches detected
                if (!$startTime1 && !$startTime4) {
                    $startTime1 = $label;
                    $startTime2 = $label;
                    $startTime3 = $label;
                    $startTime4 = $label;
                }
            }

            // Upsert into attendance_clean
            $existing = DB::selectOne(
                "SELECT id FROM attendance_clean WHERE BadgeNumber = ? AND AttDate = ?",
                [$badgeID, $attDate]
            );

            if ($existing) {
                DB::update("
                    UPDATE attendance_clean
                    SET startTime1 = ?, startTime2 = ?, startTime3 = ?, startTime4 = ?
                    WHERE id = ?
                ", [$startTime1 ?: '', $startTime2 ?: '', $startTime3 ?: '', $startTime4 ?: '', $existing->id]);
            } else {
                DB::insert("
                    INSERT INTO attendance_clean (BadgeNumber, AttDate, startTime1, startTime2, startTime3, startTime4)
                    VALUES (?, ?, ?, ?, ?, ?)
                ", [$badgeID, $attDate, $startTime1 ?: '', $startTime2 ?: '', $startTime3 ?: '', $startTime4 ?: '']);
            }

            // Mark logs as processed
            BiometricLog::whereIn('id', $group['logIds'])->update(['is_processed' => true]);
            $processed += count($group['logIds']);
        }

        return [
            'processed' => $processed,
            'skipped'   => $skipped,
            'message'   => "{$processed} logs processed, {$skipped} skipped (unmapped or no schedule).",
        ];
    }

    private function loadRules(): void
    {
        $settings = DB::select("SELECT * FROM time_detection_settings");
        foreach ($settings as $s) {
            $this->rules[$s->punch_type] = [
                'before_minutes' => (int) $s->before_minutes,
                'after_minutes'  => (int) $s->after_minutes,
                'pick_rule'      => $s->pick_rule,
            ];
        }

        // Defaults if table is empty
        if (empty($this->rules)) {
            $this->rules = [
                'timein'   => ['before_minutes' => 180, 'after_minutes' => 120, 'pick_rule' => 'earliest'],
                'breakout' => ['before_minutes' => 120, 'after_minutes' => 30,  'pick_rule' => 'latest'],
                'breakin'  => ['before_minutes' => 30,  'after_minutes' => 120, 'pick_rule' => 'earliest'],
                'timeout'  => ['before_minutes' => 120, 'after_minutes' => 180, 'pick_rule' => 'latest'],
            ];
        }
    }

    private function loadSchedules(): void
    {
        $rows = DB::select("SELECT * FROM schedule");
        foreach ($rows as $row) {
            $this->schedules[$row->id] = $row;
        }
    }

    private function detectPunchFromPool(array $pool, string $scheduledTime, string $dateYmd, string $punchType): string
    {
        if (empty($scheduledTime) || !isset($this->rules[$punchType]) || empty($pool)) {
            return '';
        }

        $rule = $this->rules[$punchType];
        $scheduledTs = Carbon::parse("$dateYmd $scheduledTime");
        $windowStart = $scheduledTs->copy()->subMinutes($rule['before_minutes']);
        $windowEnd   = $scheduledTs->copy()->addMinutes($rule['after_minutes']);

        $candidates = [];
        foreach ($pool as $punch) {
            if ($punch['timestamp']->between($windowStart, $windowEnd)) {
                $candidates[] = $punch;
            }
        }

        if (empty($candidates)) {
            return '';
        }

        if ($rule['pick_rule'] === 'earliest') {
            usort($candidates, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);
        } else {
            usort($candidates, fn($a, $b) => $a['timestamp']->gt($b['timestamp']) ? -1 : 1);
        }

        return $candidates[0]['time'];
    }

    private function removePunchFromPool(array $pool, string $time): array
    {
        $removed = false;
        return array_values(array_filter($pool, function ($p) use ($time, &$removed) {
            if (!$removed && $p['time'] === $time) {
                $removed = true;
                return false;
            }
            return true;
        }));
    }
}
