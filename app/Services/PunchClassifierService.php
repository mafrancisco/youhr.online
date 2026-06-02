<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared punch-classification logic used by both the file-import pipeline
 * (AttendanceImportService) and the biometric-device pipeline
 * (BiometricLogProcessorService).
 *
 * Responsibilities:
 *  - Load Time Detection Rules from the database (with built-in defaults).
 *  - Load schedule rows.
 *  - Provide seedDateRange() to guarantee attendance_clean rows exist for
 *    every day in a range before classification runs.
 *  - Classify an array of raw punches into StartTime1–4 using the
 *    sequential pool-drain algorithm.
 *  - Write the classified slots back to attendance_clean (DELETE + INSERT
 *    pattern, always resetting OTIn/OTOut).
 */
class PunchClassifierService
{
    private array $rules     = [];
    private array $schedules = [];

    private array $dayMap = [
        'mon' => 'm',
        'tue' => 't',
        'wed' => 'w',
        'thu' => 'th',
        'fri' => 'f',
        'sat' => 'sat',
        'sun' => 'sun',
    ];

    // -----------------------------------------------------------------------
    // Bootstrap helpers
    // -----------------------------------------------------------------------

    public function loadRules(): void
    {
        $this->rules = [];
        $settings = DB::select("SELECT * FROM time_detection_settings");
        foreach ($settings as $s) {
            $this->rules[$s->punch_type] = [
                'before_minutes' => (int) $s->before_minutes,
                'after_minutes'  => (int) $s->after_minutes,
                'pick_rule'      => $s->pick_rule,
            ];
        }

        if (empty($this->rules)) {
            $this->rules = [
                'timein'   => ['before_minutes' => 180, 'after_minutes' => 120, 'pick_rule' => 'earliest'],
                'breakout' => ['before_minutes' => 120, 'after_minutes' => 30,  'pick_rule' => 'latest'],
                'breakin'  => ['before_minutes' => 30,  'after_minutes' => 120, 'pick_rule' => 'earliest'],
                'timeout'  => ['before_minutes' => 120, 'after_minutes' => 180, 'pick_rule' => 'latest'],
                'otin'     => ['before_minutes' => 30,  'after_minutes' => 60,  'pick_rule' => 'earliest'],
                'otout'    => ['before_minutes' => 60,  'after_minutes' => 180, 'pick_rule' => 'latest'],
            ];
        }
    }

    public function loadSchedules(): void
    {
        $this->schedules = [];
        foreach (DB::select("SELECT * FROM schedule") as $row) {
            $this->schedules[$row->id] = $row;
        }
    }

    // -----------------------------------------------------------------------
    // Date-range seeding
    // -----------------------------------------------------------------------

    /**
     * Ensure attendance_clean has one row per day in [$startDate, $endDate]
     * for the given employee.
     *
     * - Days with actual punches in the staging table (attendance) → blank row ('' × 4)
     * - Weekend days without punches  → labelled row ('Saturday'/'Sunday' × 4)
     * - Weekday days without punches  → blank row ('' × 4)
     *
     * Existing rows are NOT touched (so re-seeding is safe after a DELETE-range
     * has already been done).
     *
     * @param string   $badgeID
     * @param string   $startDate  Y-m-d
     * @param string   $endDate    Y-m-d
     * @param array    $punchDates Set of attDate strings (MM/DD/YYYY) that
     *                             have raw punches — pass [] when unknown and
     *                             the method will fall back to checking
     *                             attendance_clean for an existing row.
     */
    public function seedDateRange(string $badgeID, string $startDate, string $endDate, array $punchDates = []): void
    {
        $current = Carbon::parse($startDate)->startOfDay();
        $end     = Carbon::parse($endDate)->startOfDay();

        while ($current->lte($end)) {
            $attDate   = $current->format('m/d/Y');
            $dayOfWeek = $current->dayOfWeekIso; // 1=Mon … 7=Sun

            // Skip if a row already exists for this badge+date
            $exists = DB::selectOne(
                "SELECT id FROM attendance_clean WHERE BadgeNumber = ? AND AttDate = ? LIMIT 1",
                [$badgeID, $attDate]
            );

            if (!$exists) {
                $hasPunches = !empty($punchDates)
                    ? in_array($attDate, $punchDates)
                    : false;

                $label = '';
                if (!$hasPunches) {
                    if ($dayOfWeek === 6) $label = 'Saturday';
                    if ($dayOfWeek === 7) $label = 'Sunday';
                }

                DB::insert(
                    "INSERT INTO attendance_clean (BadgeNumber, AttDate, startTime1, startTime2, startTime3, startTime4, OTIn, OTOut)
                     VALUES (?, ?, ?, ?, ?, ?, '', '')",
                    [$badgeID, $attDate, $label, $label, $label, $label]
                );
            }

            $current->addDay();
        }
    }

    // -----------------------------------------------------------------------
    // Main classification entry point
    // -----------------------------------------------------------------------

    /**
     * Classify a pre-sorted list of raw punch times for one employee on one date
     * and write the result into attendance_clean (DELETE + INSERT).
     *
     * @param string $badgeID
     * @param string $attDate   MM/DD/YYYY
     * @param string $dateYmd   Y-m-d  (derived from attDate, passed to avoid recomputing)
     * @param int    $schedId   Employee's schedule ID
     * @param array  $punches   [ ['time' => 'HH:MM', 'timestamp' => Carbon], … ]
     *                          Must already be sorted ascending by timestamp.
     */
    public function classifyAndWrite(
        string $badgeID,
        string $attDate,
        string $dateYmd,
        mixed  $schedId,
        array  $punches
    ): void {
        [$startTime1, $startTime2, $startTime3, $startTime4] =
            $this->classifyPunches($punches, $schedId, $dateYmd);

        // Weekend with no detected punches → use day label
        $dayOfWeek = Carbon::parse($dateYmd)->dayOfWeekIso;
        if (($dayOfWeek === 6 || $dayOfWeek === 7) && !$startTime1 && !$startTime4) {
            $label      = $dayOfWeek === 6 ? 'Saturday' : 'Sunday';
            $startTime1 = $label;
            $startTime2 = $label;
            $startTime3 = $label;
            $startTime4 = $label;
        }

        // DELETE + INSERT (uniform across all pipelines), always clear OTIn/OTOut
        DB::delete(
            "DELETE FROM attendance_clean WHERE BadgeNumber = ? AND AttDate = ?",
            [$badgeID, $attDate]
        );
        DB::insert(
            "INSERT INTO attendance_clean
                (BadgeNumber, AttDate, startTime1, startTime2, startTime3, startTime4, OTIn, OTOut)
             VALUES (?, ?, ?, ?, ?, ?, '', '')",
            [$badgeID, $attDate, $startTime1 ?: '', $startTime2 ?: '', $startTime3 ?: '', $startTime4 ?: '']
        );
    }

    // -----------------------------------------------------------------------
    // Core sequential pool-drain algorithm
    // -----------------------------------------------------------------------

    /**
     * Classify punches into [StartTime1, StartTime2, StartTime3, StartTime4].
     *
     * @param array  $punches  Sorted ascending [ ['time'=>'HH:MM', 'timestamp'=>Carbon], … ]
     * @param mixed  $schedId
     * @param string $dateYmd  Y-m-d
     * @return array  [string, string, string, string]
     */
    public function classifyPunches(array $punches, mixed $schedId, string $dateYmd): array
    {
        $sched = $this->schedules[$schedId] ?? null;
        $dayName   = strtolower(date('D', strtotime($dateYmd)));
        $dayPrefix = $this->dayMap[$dayName] ?? '';

        if (!$sched || !$dayPrefix) {
            return ['', '', '', ''];
        }

        $schedTimein   = $sched->{$dayPrefix . '_timein'}   ?? '';
        $schedBreakout = $sched->{$dayPrefix . '_breakout'} ?? '';
        $schedBreakin  = $sched->{$dayPrefix . '_breakin'}  ?? '';
        $schedTimeout  = $sched->{$dayPrefix . '_timeout'}  ?? '';

        if (empty($schedTimein) || empty($punches)) {
            return ['', '', '', ''];
        }

        $pool = $punches;

        // 1. Time In — earliest punch within window
        $startTime1 = $this->detectFromPool($pool, $schedTimein, $dateYmd, 'timein');
        if ($startTime1) {
            $pool = $this->removeFromPool($pool, $startTime1);
        }

        // 2. Break Out — with paired-scan detection
        //
        // When two or more punches fall inside the BreakOut detection window AND
        // the first two are within 10 minutes of each other (consecutive taps),
        // treat them as a paired breakout/breakin: earliest = BreakOut, the
        // immediately following punch = forced BreakIn.  This handles the case
        // where an employee taps out and taps back in almost immediately (e.g.
        // 12:28 → 12:29), both of which sit inside the BreakOut window before
        // the BreakIn window officially opens.
        $startTime2    = '';
        $forcedBreakIn = null;

        $boRule = $this->rules['breakout'] ?? null;
        if (!empty($schedBreakout) && $boRule) {
            $boWinStart   = Carbon::parse("$dateYmd $schedBreakout")->subMinutes($boRule['before_minutes']);
            $boWinEnd     = Carbon::parse("$dateYmd $schedBreakout")->addMinutes($boRule['after_minutes']);
            $boCandidates = array_values(
                array_filter($pool, fn($p) => $p['timestamp']->between($boWinStart, $boWinEnd))
            );
            usort($boCandidates, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);

            if (!empty($boCandidates)) {
                if (count($boCandidates) >= 2
                    && $boCandidates[0]['timestamp']->diffInMinutes($boCandidates[1]['timestamp']) <= 10
                ) {
                    // Consecutive pair: first = BreakOut, second = forced BreakIn
                    $startTime2    = $boCandidates[0]['time'];
                    $forcedBreakIn = $boCandidates[1]['time'];
                } else {
                    // Single candidate (or non-consecutive multiples) — normal pick_rule
                    $startTime2 = $boRule['pick_rule'] === 'latest'
                        ? end($boCandidates)['time']
                        : $boCandidates[0]['time'];
                }
                $pool = $this->removeFromPool($pool, $startTime2);
            }
        }

        // 3. Break In
        $startTime3 = '';
        if ($forcedBreakIn !== null) {
            // BreakIn was determined by the consecutive-scan logic above
            $startTime3 = $forcedBreakIn;
            $pool = $this->removeFromPool($pool, $startTime3);
        } elseif ($startTime2) {
            // Normal: find first punch after BreakOut, guided by detection window
            $breakOutTs = Carbon::parse("$dateYmd $startTime2");
            $afterBreak = array_filter($pool, fn($p) => $p['timestamp']->gt($breakOutTs));

            if (!empty($afterBreak)) {
                $biRule = $this->rules['breakin'] ?? null;
                if ($biRule) {
                    $windowEnd  = Carbon::parse("$dateYmd $schedBreakin")->addMinutes($biRule['after_minutes']);
                    $inWindow   = array_filter($afterBreak, fn($p) => $p['timestamp']->lte($windowEnd));
                    $candidates = !empty($inWindow) ? $inWindow : $afterBreak;
                    usort($candidates, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);
                    $startTime3 = reset($candidates)['time'];
                } else {
                    $sorted = array_values($afterBreak);
                    usort($sorted, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);
                    $startTime3 = $sorted[0]['time'];
                }
            }
            if ($startTime3) {
                $pool = $this->removeFromPool($pool, $startTime3);
            }
        } else {
            // No Break Out found — try normal window detection for Break In
            $startTime3 = $this->detectFromPool($pool, $schedBreakin, $dateYmd, 'breakin');
            if ($startTime3) {
                $pool = $this->removeFromPool($pool, $startTime3);
            }
        }

        // 2b. Break Out back-fill
        //
        // If BreakOut is still empty but BreakIn was just assigned, look for any
        // remaining pool punch that comes BEFORE the BreakIn timestamp — the latest
        // such punch is the employee's actual departure for the break (e.g. 12:48
        // when the BreakOut window closed at 12:30).
        //
        // This fires for the pattern: no TimeIn, stale BreakOut punch just past the
        // window, BreakIn detected normally, TimeOut detected normally.
        // Example (May 29): 12:48 → BreakOut, 13:06 → BreakIn, 17:08 → TimeOut.
        if (!$startTime2 && $startTime3 && !empty($pool)) {
            $breakInTs = Carbon::parse("$dateYmd $startTime3");
            $before    = array_values(array_filter(
                $pool,
                fn($p) => $p['timestamp']->lt($breakInTs)
            ));

            if (!empty($before)) {
                usort($before, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);
                $startTime2 = end($before)['time'];   // latest punch before BreakIn
                $pool = $this->removeFromPool($pool, $startTime2);
            }
        }

        //
        // If BreakIn is still empty, look for a punch in the "midday gap" — after
        // the BreakOut detection window closes and before the TimeOut window opens.
        //
        // IMPORTANT guard: only promote a gap punch to BreakIn when there is at
        // least one further punch in the pool that can still become TimeOut.
        // If the gap punch is the LAST punch of the day, the employee left early
        // and did not return — the existing fallback below will assign it to
        // BreakOut instead (triggering the correct 4-hr undertime rule).
        //
        // Example where this fires (May 27):
        //   no TimeIn, 12:35 (gap), 17:08 (TimeOut) → 12:35 → BreakIn ✓
        //
        // Example where this does NOT fire (May 26):
        //   07:55 (TimeIn consumed), 12:48 (last punch, no successor)
        //   → guard fails → fallback assigns 12:48 to BreakOut ✓
        if (!$startTime3 && !empty($pool)) {
            $boRule = $this->rules['breakout'] ?? null;
            $toRule = $this->rules['timeout']  ?? null;

            $gapStart = !empty($schedBreakout) && $boRule
                ? Carbon::parse("$dateYmd $schedBreakout")->addMinutes($boRule['after_minutes'])
                : null;

            $gapEnd = !empty($schedTimeout) && $toRule
                ? Carbon::parse("$dateYmd $schedTimeout")->subMinutes($toRule['before_minutes'])
                : null;

            if ($gapStart && $gapEnd && $gapEnd->gt($gapStart)) {
                $gapCandidates = array_values(array_filter(
                    $pool,
                    fn($p) => $p['timestamp']->gte($gapStart) && $p['timestamp']->lt($gapEnd)
                ));

                if (!empty($gapCandidates)) {
                    usort($gapCandidates, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);
                    $candidate = $gapCandidates[0];

                    // Guard: only promote to BreakIn if a later punch exists in pool
                    $hasSuccessor = !empty(array_filter(
                        $pool,
                        fn($p) => $p['timestamp']->gt($candidate['timestamp'])
                    ));

                    if ($hasSuccessor) {
                        $startTime3 = $candidate['time'];
                        $pool = $this->removeFromPool($pool, $startTime3);
                    }
                }
            }
        }

        // 4. Time Out — latest punch within window from remaining pool
        $startTime4 = $this->detectFromPool($pool, $schedTimeout, $dateYmd, 'timeout');

        // Fallback: if TimeOut is still not found but punches remain in the pool,
        // decide which slot the leftover punch belongs in:
        //
        //  - If BreakOut (StartTime2) is EMPTY and the punch falls BEFORE the TimeOut
        //    window opens → assign to BreakOut.  This covers the case where an employee
        //    taps out mid-morning/midday and never returns (e.g. 12:48 when the BreakOut
        //    window closed at 12:30 and the TimeOut window doesn't open until 15:00).
        //    Having only a BreakOut with no TimeOut will correctly trigger the
        //    "4 hrs undertime" rule in AttendanceComputationService::firstPass().
        //
        //  - Otherwise (BreakOut is already filled, or the punch is late enough to be
        //    a TimeOut) → assign to TimeOut.
        if (!$startTime4 && !empty($pool)) {
            $remaining = array_values($pool);
            usort($remaining, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);
            $last = end($remaining);

            // Only act if this punch is genuinely after TimeIn
            $afterTimeIn = $startTime1 === ''
                || $last['timestamp']->gt(Carbon::parse("$dateYmd $startTime1"));

            if ($afterTimeIn) {
                if (!$startTime2 && !empty($schedTimeout)) {
                    // Determine when the TimeOut detection window opens
                    $toRule        = $this->rules['timeout'] ?? null;
                    $toWindowStart = $toRule
                        ? Carbon::parse("$dateYmd $schedTimeout")->subMinutes($toRule['before_minutes'])
                        : Carbon::parse("$dateYmd $schedTimeout")->subMinutes(120);

                    if ($last['timestamp']->lt($toWindowStart)) {
                        // Mid-day punch before TimeOut window — treat as early BreakOut
                        // (employee left early; no TimeOut → 4 hrs undertime)
                        $startTime2 = $last['time'];
                    } else {
                        $startTime4 = $last['time'];
                    }
                } else {
                    // BreakOut already filled — last remaining punch is a TimeOut
                    $startTime4 = $last['time'];
                }
            }
        }

        return [$startTime1 ?: '', $startTime2 ?: '', $startTime3 ?: '', $startTime4 ?: ''];
    }

    // -----------------------------------------------------------------------
    // Low-level helpers
    // -----------------------------------------------------------------------

    /**
     * Pick a punch from $pool that falls within the configured detection window
     * for the given $punchType ('timein', 'breakout', 'breakin', 'timeout', …).
     */
    public function detectFromPool(array $pool, string $scheduledTime, string $dateYmd, string $punchType): string
    {
        $rule = $this->rules[$punchType] ?? null;

        if (empty($scheduledTime) || !$rule || empty($pool)) {
            return '';
        }

        $scheduledTs = Carbon::parse("$dateYmd $scheduledTime");
        $windowStart = $scheduledTs->copy()->subMinutes($rule['before_minutes']);
        $windowEnd   = $scheduledTs->copy()->addMinutes($rule['after_minutes']);

        $candidates = array_filter($pool, fn($p) => $p['timestamp']->between($windowStart, $windowEnd));

        if (empty($candidates)) {
            return '';
        }

        if ($rule['pick_rule'] === 'earliest') {
            usort($candidates, fn($a, $b) => $a['timestamp']->lt($b['timestamp']) ? -1 : 1);
        } else {
            usort($candidates, fn($a, $b) => $a['timestamp']->gt($b['timestamp']) ? -1 : 1);
        }

        return reset($candidates)['time'];
    }

    /**
     * Remove the first occurrence of $time from $pool (by time string).
     */
    public function removeFromPool(array $pool, string $time): array
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
