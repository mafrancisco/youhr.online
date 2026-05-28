<?php

namespace App\Services;

use App\Models\TimeDetectionSetting;
use Carbon\Carbon;

class TimeLogDetectionService
{
    private array $rules = [];

    public function __construct()
    {
        $this->loadRules();
    }

    private function loadRules(): void
    {
        $settings = TimeDetectionSetting::all();
        foreach ($settings as $s) {
            $this->rules[$s->punch_type] = [
                'before_minutes' => $s->before_minutes,
                'after_minutes'  => $s->after_minutes,
                'pick_rule'      => $s->pick_rule,
            ];
        }
    }

    /**
     * Given a list of raw time punches for a day and the employee's schedule,
     * detect which punch corresponds to Time In, Break Out, Break In, Time Out, OT In, OT Out.
     *
     * @param array $punches Array of ['time' => 'HH:MM', 'timestamp' => Carbon] sorted by time
     * @param array $schedule ['timein' => 'HH:MM', 'breakout' => 'HH:MM', 'breakin' => 'HH:MM', 'timeout' => 'HH:MM']
     * @param string $dateYmd The date in Y-m-d format
     * @return array ['StartTime1' => 'HH:MM', 'StartTime2' => 'HH:MM', 'StartTime3' => 'HH:MM', 'StartTime4' => 'HH:MM', 'OTIn' => 'HH:MM', 'OTOut' => 'HH:MM']
     */
    public function detect(array $punches, array $schedule, string $dateYmd): array
    {
        $result = [
            'StartTime1' => '', // Time In
            'StartTime2' => '', // Break Out
            'StartTime3' => '', // Break In
            'StartTime4' => '', // Time Out
            'OTIn'       => '',
            'OTOut'      => '',
        ];

        if (empty($punches) || empty($schedule['timein'])) {
            return $result;
        }

        // Detect each punch type
        $result['StartTime1'] = $this->findPunch($punches, $schedule['timein'], $dateYmd, 'timein');
        $result['StartTime2'] = $this->findPunch($punches, $schedule['breakout'] ?? '', $dateYmd, 'breakout');
        $result['StartTime3'] = $this->findPunch($punches, $schedule['breakin'] ?? '', $dateYmd, 'breakin');
        $result['StartTime4'] = $this->findPunch($punches, $schedule['timeout'] ?? '', $dateYmd, 'timeout');

        // OT detection (only if timeout is set)
        if (!empty($schedule['timeout'])) {
            $result['OTIn']  = $this->findPunch($punches, $schedule['timeout'], $dateYmd, 'otin');
            $result['OTOut'] = $this->findPunch($punches, $schedule['timeout'], $dateYmd, 'otout');
        }

        return $result;
    }

    /**
     * Find the appropriate punch within the detection window.
     */
    private function findPunch(array $punches, string $scheduledTime, string $dateYmd, string $punchType): string
    {
        if (empty($scheduledTime) || !isset($this->rules[$punchType])) {
            return '';
        }

        $rule = $this->rules[$punchType];
        $scheduledTs = Carbon::parse("$dateYmd $scheduledTime");

        // Calculate detection window
        $windowStart = $scheduledTs->copy()->subMinutes($rule['before_minutes']);
        $windowEnd   = $scheduledTs->copy()->addMinutes($rule['after_minutes']);

        // Find all punches within the window
        $candidates = [];
        foreach ($punches as $punch) {
            $punchTs = $punch['timestamp'];
            if ($punchTs->between($windowStart, $windowEnd)) {
                $candidates[] = $punch;
            }
        }

        if (empty($candidates)) {
            return '';
        }

        // Apply pick rule
        if ($rule['pick_rule'] === 'earliest') {
            usort($candidates, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);
            return $candidates[0]['time'];
        } else {
            usort($candidates, fn($a, $b) => $a['timestamp']->gt($b['timestamp']) ? -1 : 1);
            return $candidates[0]['time'];
        }
    }

    /**
     * Get the detection rules (for display/debugging).
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
