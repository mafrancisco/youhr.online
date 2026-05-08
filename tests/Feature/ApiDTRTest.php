<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ApiDTRTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    public function test_employee_can_get_dtr(): void
    {
        $user = $this->createEmployeeUser();
        $employee = $this->createEmployee(['email' => $user->email]);
        Sanctum::actingAs($user);

        $this->createAttendanceRecord($employee->badgeID, '05/01/2025', [
            'StartTime1' => '08:00',
            'StartTime4' => '17:00',
        ]);

        $response = $this->getJson('/api/v1/dtr?month=2025-05');

        $response->assertOk();
        $response->assertJsonStructure([
            'employee' => ['name', 'badgeID'],
            'month',
            'days',
            'submitted',
        ]);
        $this->assertCount(31, $response->json('days'));
    }

    public function test_employee_can_submit_dtr(): void
    {
        $user = $this->createEmployeeUser();
        $this->createEmployee(['email' => $user->email]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/dtr/submit', [
            'attRange' => '2025-05',
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'DTR submitted successfully.']);
    }

    public function test_employee_cannot_submit_dtr_twice(): void
    {
        $user = $this->createEmployeeUser();
        $employee = $this->createEmployee(['email' => $user->email]);
        $this->createSubmission($employee->badgeID, '2025-05');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/dtr/submit', [
            'attRange' => '2025-05',
        ]);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_cannot_access_dtr(): void
    {
        $response = $this->getJson('/api/v1/dtr');

        $response->assertStatus(401);
    }
}
