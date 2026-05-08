<?php

namespace Tests\Feature;

use App\Models\Leave;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ApiLeaveTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    public function test_employee_can_list_leaves(): void
    {
        $user = $this->createEmployeeUser();
        $employee = $this->createEmployee(['email' => $user->email]);
        $this->createLeave($employee->badgeID, ['status' => 'Approved']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/leaves');

        $response->assertOk();
        $response->assertJsonStructure(['leaves' => [['id', 'controlno', 'status']]]);
        $this->assertCount(1, $response->json('leaves'));
    }

    public function test_employee_can_get_leave_credits(): void
    {
        $user = $this->createEmployeeUser();
        $employee = $this->createEmployee(['email' => $user->email]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/leaves/credits');

        $response->assertOk();
        $response->assertJsonStructure(['credits']);
    }

    public function test_employee_can_file_leave(): void
    {
        $user = $this->createEmployeeUser();
        $this->createEmployee(['email' => $user->email]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/leaves', [
            'leave_type'    => 1,
            'dates'         => ['05/05/2025', '05/06/2025'],
            'leave_details' => 'Family event',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'leave' => ['id', 'controlno', 'status']]);
    }

    public function test_employee_can_cancel_pending_leave(): void
    {
        $user = $this->createEmployeeUser();
        $employee = $this->createEmployee(['email' => $user->email]);
        $leave = $this->createLeave($employee->badgeID, ['status' => 'Pending']);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/leaves/{$leave->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('leaves', ['id' => $leave->id]);
    }

    public function test_employee_cannot_cancel_approved_leave(): void
    {
        $user = $this->createEmployeeUser();
        $employee = $this->createEmployee(['email' => $user->email]);
        $leave = $this->createLeave($employee->badgeID, ['status' => 'Approved']);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/leaves/{$leave->id}");

        $response->assertStatus(422);
    }

    public function test_employee_cannot_cancel_another_employees_leave(): void
    {
        $user = $this->createEmployeeUser();
        $this->createEmployee(['email' => $user->email, 'badgeID' => '10001']);
        $leave = $this->createLeave('99999', ['status' => 'Pending']);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/leaves/{$leave->id}");

        $response->assertStatus(403);
    }
}
