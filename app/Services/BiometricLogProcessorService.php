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

            // Detect punch classification
            $startTime1 = $this->detectPunch($punches, $schedTimein, $dateYmd, 'timein');
            $startTime2 = $this->detectPunch($punches, $schedBreakout, $dateYmd, 'breakout');
            $startTime3 = $this->detectPunch($punches, $schedBreakin, $dateYmd, 'breakin');
            $startTime4 = $this->detectPunch($punches, $schedTimeout, $dateYmd, 'timeout');

            // Prevent same punch assigned to multiple slots
            $used = [];
            if ($startTime1) $used[] = $startTime1;

            if ($startTime2 && (in_array($startTime2, $used) || ($startTime1 && $startTime2 <= $startTime1))) {
                $startTime2 = '';
            }
            if ($startTime2) $used[] = $startTime2;

            if ($startTime3 && (in_array($startTime3, $used) || ($startTime2 && $startTime3 <= $startTime2))) {
                $startTime3 = '';
            }
            if ($startTime3) $used[] = $startTime3;

            $lastBefore = $startTime3 ?: $startTime2 ?: $startTime1;
            if ($startTime4 && (in_array($startTime4, $used) || ($lastBefore && $startTime4 <= $lastBefore))) {
                $startTime4 = '';
            }

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

    private function detectPunch(array $punches, string $scheduledTime, string $dateYmd, string $punchType): string
    {
        if (empty($scheduledTime) || !isset($this->rules[$punchType])) {
            return '';
        }

        $rule = $this->rules[$punchType];
        $scheduledTs = Carbon::parse("$dateYmd $scheduledTime");
        $windowStart = $scheduledTs->copy()->subMinutes($rule['before_minutes']);
        $windowEnd   = $scheduledTs->copy()->addMinutes($rule['after_minutes']);

        $candidates = [];
        foreach ($punches as $punch) {
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
}
