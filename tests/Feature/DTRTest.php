<?php

namespace Tests\Feature;

use App\Models\AttendanceClean;
use App\Models\AttendanceRequest;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class DTRTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_employee_can_view_dtr_for_current_month(): void
    {
        $user = $this->createEmployeeUser();
        $employee = $this->createEmployee(['email' => $user->email]);

        $month = now()->format('Y-m');
        $attDate = now()->startOfMonth()->format('m/d/Y');

        $this->createAttendanceRecord($employee->badgeID, $attDate, [
            'StartTime1' => '08:00',
            'StartTime4' => '17:00',
        ]);

        $response = $this->actingAs($user)->get('/dtr?month=' . $month);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('DTR/Index')
                ->has('days')
                ->where('month', $month)
                ->where('employee.badgeID', $employee->badgeID)
        );
    }

    public function test_employee_can_view_dtr_for_specific_month(): void
    {
        $user = $this->createEmployeeUser();
        $employee = $this->createEmployee(['email' => $user->email]);

        $this->createAttendanceRecord($employee->badgeID, '05/01/2025', [
            'StartTime1' => '08:00',
            'StartTime4' => '17:00',
        ]);

        $response = $this->actingAs($user)->get('/dtr?month=2025-05');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('DTR/Index')
                ->where('month', '2025-05')
        );
    }

    public function test_employee_can_submit_dtr(): void
    {
        $user = $this->createEmployeeUser();
        $employee = $this->createEmployee(['email' => $user->email]);

        $response = $this->actingAs($user)->post('/dtr/submit', [
            'attRange' => '2025-05',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('submissions', [
            'badgeID'  => $employee->badgeID,
            'attRange' => '2025-05',
        ]);
    }

    public function test_employee_cannot_submit_dtr_twice(): void
    {
        $user = $this->createEmployeeUser();
        $employee = $this->createEmployee(['email' => $user->email]);

        $this->createSubmission($employee->badgeID, '2025-05');

        $response = $this->actingAs($user)->post('/dtr/submit', [
            'attRange' => '2025-05',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_employee_can_edit_time_log(): void
    {
        $user = $this->createEmployeeUser();
        $employee = $this->createEmployee(['email' => $user->email]);

        // Create an attendance_clean record with blank times (no biometric data)
        $this->createAttendanceRecord($employee->badgeID, '05/05/2025', [
            'StartTime1' => '',
            'StartTime2' => '',
            'StartTime3' => '',
            'StartTime4' => '',
        ]);

        $response = $this->actingAs($user)->post('/dtr/requests', [
            'AttDate'    => '05/05/2025',
            'StartTime1' => '08:00',
            'StartTime2' => '12:00',
            'StartTime3' => '13:00',
            'StartTime4' => '17:00',
            'remarks'    => 'Forgot to log in',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify attendance_clean was directly updated
        $this->assertDatabaseHas('attendance_clean', [
            'BadgeNumber' => $employee->badgeID,
            'AttDate'     => '05/05/2025',
            'StartTime1'  => '08:00',
            'StartTime4'  => '17:00',
        ]);

        // Verify edit was tracked in request table
        $this->assertDatabaseHas('request', [
            'BadgeNumber' => $employee->badgeID,
            'AttDate'     => '05/05/2025',
            'log1'        => '1',
            'remarks'     => 'Forgot to log in',
        ]);
    }

    public function test_employee_can_cancel_adjustment_request(): void
    {
        $user = $this->createEmployeeUser();
        $employee = $this->createEmployee(['email' => $user->email]);

        $reqId = DB::table('request')->insertGetId([
            'BadgeNumber' => $employee->badgeID,
            'AttDate'     => '05/05/2025',
            'StartTime1'  => '08:00',
            'StartTime4'  => '17:00',
            'dateReq'     => now()->toDateString(),
            'remarks'     => 'Test',
        ]);

        $response = $this->actingAs($user)->delete("/dtr/requests/{$reqId}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('request', ['id' => $reqId]);
    }

    public function test_employee_cannot_cancel_another_employees_request(): void
    {
        $user = $this->createEmployeeUser();
        $this->createEmployee(['email' => $user->email, 'badgeID' => '10001']);

        $reqId = DB::table('request')->insertGetId([
            'BadgeNumber' => '99999', // different employee
            'AttDate'     => '05/05/2025',
            'StartTime1'  => '08:00',
            'dateReq'     => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->delete("/dtr/requests/{$reqId}");

        $response->assertStatus(403);
    }

    public function test_dtr_shows_submitted_status(): void
    {
        $user = $this->createEmployeeUser();
        $employee = $this->createEmployee(['email' => $user->email]);

        $this->createSubmission($employee->badgeID, '2025-05');

        $response = $this->actingAs($user)->get('/dtr?month=2025-05');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->where('submitted', true)
        );
    }
}
