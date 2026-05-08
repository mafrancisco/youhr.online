<?php

namespace Tests\Unit;

use App\Services\AttendanceComputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

#[Group('integration')]
class AttendanceComputationServiceTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private AttendanceComputationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AttendanceComputationService();
    }

    public function test_no_tardiness_when_employee_arrives_on_time(): void
    {
        $schedule = $this->createSchedule();
        $employee = $this->createEmployee(['schedule' => $schedule->id]);

        // Monday 05/05/2025 — arrives at 08:00, leaves at 17:00
        $this->createAttendanceRecord($employee->badgeID, '05/05/2025', [
            'StartTime1' => '08:00',
            'StartTime2' => '12:00',
            'StartTime3' => '13:00',
            'StartTime4' => '17:00',
        ]);

        $this->service->compute('2025-05-05', '2025-05-05');

        $record = DB::selectOne(
            "SELECT Tardiness, undertime FROM attendance_clean WHERE BadgeNumber = ? AND AttDate = ?",
            [$employee->badgeID, '05/05/2025']
        );

        $this->assertEquals(0, $record->Tardiness);
        $this->assertEquals(0, $record->undertime);
    }

    public function test_tardiness_computed_when_employee_arrives_late(): void
    {
        $schedule = $this->createSchedule();
        $employee = $this->createEmployee(['schedule' => $schedule->id]);

        // Monday — arrives 30 minutes late
        $this->createAttendanceRecord($employee->badgeID, '05/05/2025', [
            'StartTime1' => '08:30',
            'StartTime2' => '12:00',
            'StartTime3' => '13:00',
            'StartTime4' => '17:00',
        ]);

        $this->service->compute('2025-05-05', '2025-05-05');

        $record = DB::selectOne(
            "SELECT Tardiness, undertime FROM attendance_clean WHERE BadgeNumber = ? AND AttDate = ?",
            [$employee->badgeID, '05/05/2025']
        );

        $this->assertEquals(30, $record->Tardiness);
        $this->assertEquals(0, $record->undertime);
    }

    public function test_undertime_computed_when_employee_leaves_early(): void
    {
        $schedule = $this->createSchedule();
        $employee = $this->createEmployee(['schedule' => $schedule->id]);

        // Monday — leaves 1 hour early
        $this->createAttendanceRecord($employee->badgeID, '05/05/2025', [
            'StartTime1' => '08:00',
            'StartTime2' => '12:00',
            'StartTime3' => '13:00',
            'StartTime4' => '16:00',
        ]);

        $this->service->compute('2025-05-05', '2025-05-05');

        $record = DB::selectOne(
            "SELECT Tardiness, undertime FROM attendance_clean WHERE BadgeNumber = ? AND AttDate = ?",
            [$employee->badgeID, '05/05/2025']
        );

        $this->assertEquals(0, $record->Tardiness);
        $this->assertEquals(60, $record->undertime);
    }

    public function test_absent_employee_gets_max_tardiness_and_undertime(): void
    {
        $schedule = $this->createSchedule();
        $employee = $this->createEmployee(['schedule' => $schedule->id]);

        // Monday — no time in or out (absent)
        $this->createAttendanceRecord($employee->badgeID, '05/05/2025', [
            'StartTime1' => '',
            'StartTime2' => '',
            'StartTime3' => '',
            'StartTime4' => '',
        ]);

        $this->service->compute('2025-05-05', '2025-05-05');

        $record = DB::selectOne(
            "SELECT Tardiness, undertime FROM attendance_clean WHERE BadgeNumber = ? AND AttDate = ?",
            [$employee->badgeID, '05/05/2025']
        );

        $this->assertEquals(240, $record->Tardiness);
        $this->assertEquals(240, $record->undertime);
    }

    public function test_weekend_records_have_zero_tardiness(): void
    {
        $schedule = $this->createSchedule();
        $employee = $this->createEmployee(['schedule' => $schedule->id]);

        // Saturday 05/03/2025 — no time records
        $this->createAttendanceRecord($employee->badgeID, '05/03/2025', [
            'StartTime1' => 'Saturday',
            'StartTime2' => 'Saturday',
            'StartTime3' => 'Saturday',
            'StartTime4' => 'Saturday',
        ]);

        $this->service->compute('2025-05-03', '2025-05-03');

        $record = DB::selectOne(
            "SELECT Tardiness, undertime FROM attendance_clean WHERE BadgeNumber = ? AND AttDate = ?",
            [$employee->badgeID, '05/03/2025']
        );

        $this->assertEquals(0, $record->Tardiness);
        $this->assertEquals(0, $record->undertime);
    }

    public function test_employee_on_leave_gets_leave_markers(): void
    {
        $schedule = $this->createSchedule();
        $employee = $this->createEmployee(['schedule' => $schedule->id]);

        // Create approved leave for 05/05/2025
        $this->createLeave($employee->badgeID, [
            'date_start' => '05/05/2025',
            'status'     => 'Approved',
        ]);

        $this->createAttendanceRecord($employee->badgeID, '05/05/2025', [
            'StartTime1' => '',
            'StartTime2' => '',
            'StartTime3' => '',
            'StartTime4' => '',
        ]);

        $this->service->compute('2025-05-05', '2025-05-05');

        $record = DB::selectOne(
            "SELECT StartTime1, StartTime2, StartTime3, StartTime4, Tardiness, undertime
             FROM attendance_clean WHERE BadgeNumber = ? AND AttDate = ?",
            [$employee->badgeID, '05/05/2025']
        );

        $this->assertEquals('L', $record->StartTime1);
        $this->assertEquals('L', $record->StartTime2);
        $this->assertEquals('L', $record->StartTime3);
        $this->assertEquals('L', $record->StartTime4);
        $this->assertEquals(0, $record->Tardiness);
        $this->assertEquals(0, $record->undertime);
    }

    public function test_overtime_computed_correctly(): void
    {
        $schedule = $this->createSchedule();
        $employee = $this->createEmployee(['schedule' => $schedule->id]);

        // Monday — normal shift + 2 hours OT
        $this->createAttendanceRecord($employee->badgeID, '05/05/2025', [
            'StartTime1' => '08:00',
            'StartTime2' => '12:00',
            'StartTime3' => '13:00',
            'StartTime4' => '17:00',
            'OTIn'       => '17:30',
            'OTOut'      => '19:30',
        ]);

        $this->service->compute('2025-05-05', '2025-05-05');

        $record = DB::selectOne(
            "SELECT OT, Tardiness, undertime FROM attendance_clean WHERE BadgeNumber = ? AND AttDate = ?",
            [$employee->badgeID, '05/05/2025']
        );

        $this->assertEquals(120, $record->OT); // 2 hours = 120 minutes
        $this->assertEquals(0, $record->Tardiness);
        $this->assertEquals(0, $record->undertime);
    }

    public function test_computation_handles_multiple_employees(): void
    {
        $schedule = $this->createSchedule();
        $emp1 = $this->createEmployee(['badgeID' => '10001', 'email' => 'emp1@test.com', 'schedule' => $schedule->id]);
        $emp2 = $this->createEmployee(['badgeID' => '10002', 'email' => 'emp2@test.com', 'empName' => 'Jane Doe', 'schedule' => $schedule->id]);

        // Emp1 on time, Emp2 late
        $this->createAttendanceRecord('10001', '05/05/2025', [
            'StartTime1' => '08:00', 'StartTime4' => '17:00',
        ]);
        $this->createAttendanceRecord('10002', '05/05/2025', [
            'StartTime1' => '09:00', 'StartTime4' => '17:00',
        ]);

        $this->service->compute('2025-05-05', '2025-05-05');

        $r1 = DB::selectOne("SELECT Tardiness FROM attendance_clean WHERE BadgeNumber = '10001' AND AttDate = '05/05/2025'");
        $r2 = DB::selectOne("SELECT Tardiness FROM attendance_clean WHERE BadgeNumber = '10002' AND AttDate = '05/05/2025'");

        $this->assertEquals(0, $r1->Tardiness);
        $this->assertEquals(60, $r2->Tardiness);
    }

    public function test_date_range_only_processes_records_in_range(): void
    {
        $schedule = $this->createSchedule();
        $employee = $this->createEmployee(['schedule' => $schedule->id]);

        // Record inside range
        $this->createAttendanceRecord($employee->badgeID, '05/05/2025', [
            'StartTime1' => '08:30', 'StartTime4' => '17:00',
        ]);
        // Record outside range
        $this->createAttendanceRecord($employee->badgeID, '05/12/2025', [
            'StartTime1' => '09:00', 'StartTime4' => '17:00',
        ]);

        $this->service->compute('2025-05-05', '2025-05-09');

        $inRange = DB::selectOne("SELECT Tardiness FROM attendance_clean WHERE BadgeNumber = ? AND AttDate = '05/05/2025'", [$employee->badgeID]);
        $outRange = DB::selectOne("SELECT Tardiness FROM attendance_clean WHERE BadgeNumber = ? AND AttDate = '05/12/2025'", [$employee->badgeID]);

        $this->assertEquals(30, $inRange->Tardiness);
        $this->assertEquals(0, $outRange->Tardiness); // untouched
    }
}
