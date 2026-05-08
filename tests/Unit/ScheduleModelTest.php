<?php

namespace Tests\Unit;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ScheduleModelTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_slots_for_monday(): void
    {
        $schedule = $this->createSchedule();

        $monday = Carbon::parse('2025-05-05'); // Monday
        $slots = $schedule->slotsFor($monday);

        $this->assertEquals('08:00', $slots['timein']);
        $this->assertEquals('12:00', $slots['breakout']);
        $this->assertEquals('13:00', $slots['breakin']);
        $this->assertEquals('17:00', $slots['timeout']);
        $this->assertFalse($slots['crossday']);
        $this->assertEquals('m', $slots['prefix']);
    }

    public function test_slots_for_saturday(): void
    {
        $schedule = $this->createSchedule();

        $saturday = Carbon::parse('2025-05-03'); // Saturday
        $slots = $schedule->slotsFor($saturday);

        $this->assertEquals('', $slots['timein']);
        $this->assertEquals('', $slots['timeout']);
        $this->assertEquals('sat', $slots['prefix']);
    }

    public function test_slots_for_each_weekday(): void
    {
        $schedule = $this->createSchedule();

        $days = [
            '2025-05-05' => 'm',   // Monday
            '2025-05-06' => 't',   // Tuesday
            '2025-05-07' => 'w',   // Wednesday
            '2025-05-08' => 'th',  // Thursday
            '2025-05-09' => 'f',   // Friday
            '2025-05-10' => 'sat', // Saturday
            '2025-05-11' => 'sun', // Sunday
        ];

        foreach ($days as $date => $expectedPrefix) {
            $slots = $schedule->slotsFor(Carbon::parse($date));
            $this->assertEquals($expectedPrefix, $slots['prefix'], "Failed for date: $date");
        }
    }

    public function test_crossday_schedule(): void
    {
        $schedule = $this->createSchedule([
            'm_timein'   => '22:00',
            'm_timeout'  => '06:00',
            'm_crossday' => 1,
        ]);

        $monday = Carbon::parse('2025-05-05');
        $slots = $schedule->slotsFor($monday);

        $this->assertEquals('22:00', $slots['timein']);
        $this->assertEquals('06:00', $slots['timeout']);
        $this->assertTrue($slots['crossday']);
    }
}
