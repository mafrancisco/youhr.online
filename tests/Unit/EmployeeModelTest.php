<?php

namespace Tests\Unit;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class EmployeeModelTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_active_scope_returns_only_active_employees(): void
    {
        $this->createEmployee(['badgeID' => '10001', 'email' => 'a@test.com', 'status1' => 'Active']);
        $this->createEmployee(['badgeID' => '10002', 'email' => 'b@test.com', 'empName' => 'Inactive', 'status1' => 'Inactive']);

        $active = Employee::active()->get();

        $this->assertCount(1, $active);
        $this->assertEquals('10001', $active->first()->badgeID);
    }

    public function test_inactive_scope_returns_only_inactive_employees(): void
    {
        $this->createEmployee(['badgeID' => '10001', 'email' => 'a@test.com', 'status1' => 'Active']);
        $this->createEmployee(['badgeID' => '10002', 'email' => 'b@test.com', 'empName' => 'Inactive', 'status1' => 'Inactive']);

        $inactive = Employee::inactive()->get();

        $this->assertCount(1, $inactive);
        $this->assertEquals('10002', $inactive->first()->badgeID);
    }

    public function test_employee_belongs_to_schedule(): void
    {
        $schedule = $this->createSchedule();
        $employee = $this->createEmployee(['schedule' => $schedule->id]);

        $this->assertNotNull($employee->scheduleRecord);
        $this->assertEquals('Regular 8-5', $employee->scheduleRecord->schedulename);
    }
}
