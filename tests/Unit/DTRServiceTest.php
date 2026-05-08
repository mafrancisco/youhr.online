<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Services\DTRService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class DTRServiceTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private DTRService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DTRService();
    }

    public function test_returns_all_days_in_month(): void
    {
        $employee = $this->createEmployee();

        $data = $this->service->getMonthlyDTR($employee, '2025-05');

        // May 2025 has 31 days
        $this->assertCount(31, $data['days']);
        $this->assertEquals('2025-05', $data['month']);
        $this->assertEquals($employee->empName, $data['employee']['name']);
    }

    public function test_includes_attendance_records_for_month(): void
    {
        $employee = $this->createEmployee();

        $this->createAttendanceRecord($employee->badgeID, '05/01/2025', [
            'StartTime1' => '08:00',
            'StartTime4' => '17:00',
            'Tardiness'  => 0,
        ]);

        $data = $this->service->getMonthlyDTR($employee, '2025-05');

        $firstDay = $data['days'][0];
        $this->assertEquals('05/01/2025', $firstDay['attDate']);
        $this->assertEquals('08:00', $firstDay['StartTime1']);
        $this->assertEquals('17:00', $firstDay['StartTime4']);
    }

    public function test_includes_adjustment_requests(): void
    {
        $employee = $this->createEmployee();

        DB::table('request')->insert([
            'BadgeNumber' => $employee->badgeID,
            'AttDate'     => '05/01/2025',
            'StartTime1'  => '07:55',
            'StartTime4'  => '17:00',
            'dateReq'     => '2025-05-02',
            'remarks'     => 'Correction',
        ]);

        $data = $this->service->getMonthlyDTR($employee, '2025-05');

        $firstDay = $data['days'][0];
        $this->assertNotNull($firstDay['request']);
        $this->assertEquals('07:55', $firstDay['request']['StartTime1']);
        $this->assertEquals('Correction', $firstDay['request']['remarks']);
    }

    public function test_submitted_flag_reflects_lock_status(): void
    {
        $employee = $this->createEmployee();

        // Not submitted
        $data = $this->service->getMonthlyDTR($employee, '2025-05');
        $this->assertFalse($data['submitted']);

        // Submit
        $this->createSubmission($employee->badgeID, '2025-05');

        $data = $this->service->getMonthlyDTR($employee, '2025-05');
        $this->assertTrue($data['submitted']);
    }

    public function test_does_not_include_records_from_other_months(): void
    {
        $employee = $this->createEmployee();

        // Record in June
        $this->createAttendanceRecord($employee->badgeID, '06/01/2025', [
            'StartTime1' => '08:00',
            'StartTime4' => '17:00',
        ]);

        $data = $this->service->getMonthlyDTR($employee, '2025-05');

        // All days in May should have null StartTime1
        foreach ($data['days'] as $day) {
            $this->assertNull($day['StartTime1']);
        }
    }

    public function test_february_has_correct_day_count(): void
    {
        $employee = $this->createEmployee();

        // 2025 is not a leap year
        $data = $this->service->getMonthlyDTR($employee, '2025-02');
        $this->assertCount(28, $data['days']);

        // 2024 is a leap year
        $data = $this->service->getMonthlyDTR($employee, '2024-02');
        $this->assertCount(29, $data['days']);
    }
}
