<?php

namespace Tests\Feature;

use App\Models\GatePass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ApiGatePassTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    public function test_employee_can_list_gate_passes(): void
    {
        $user = $this->createEmployeeUser();
        $employee = $this->createEmployee(['email' => $user->email]);

        DB::table('gatepass')->insert([
            'controlno'         => 'GP-001',
            'badgeID'           => $employee->badgeID,
            'gatepass_type'     => 'Official Business',
            'gatepass_date'     => '05/05/2025',
            'purpose'           => 'Meeting',
            'destination'       => 'City Hall',
            'gatepass_datefiled' => '2025-05-04',
            'status'            => 'Pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/gate-passes');

        $response->assertOk();
        $response->assertJsonStructure(['gate_passes' => [['id', 'controlno', 'status']]]);
    }

    public function test_employee_can_file_gate_pass(): void
    {
        $user = $this->createEmployeeUser();
        $this->createEmployee(['email' => $user->email]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/gate-passes', [
            'gatepass_type'    => 'Official Business',
            'gatepass_date'    => '05/10/2025',
            'gatepass_timeout' => '09:00',
            'gatepass_timein'  => '12:00',
            'purpose'          => 'Client meeting',
            'destination'      => 'Downtown office',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'gate_pass' => ['id', 'controlno']]);
    }

    public function test_gate_pass_validates_type(): void
    {
        $user = $this->createEmployeeUser();
        $this->createEmployee(['email' => $user->email]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/gate-passes', [
            'gatepass_type' => 'Invalid Type',
            'gatepass_date' => '05/10/2025',
            'purpose'       => 'Test',
            'destination'   => 'Test',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('gatepass_type');
    }

    public function test_employee_can_cancel_pending_gate_pass(): void
    {
        $user = $this->createEmployeeUser();
        $employee = $this->createEmployee(['email' => $user->email]);

        $gpId = DB::table('gatepass')->insertGetId([
            'controlno'         => 'GP-002',
            'badgeID'           => $employee->badgeID,
            'gatepass_type'     => 'Personal',
            'gatepass_date'     => '05/10/2025',
            'purpose'           => 'Errand',
            'destination'       => 'Bank',
            'gatepass_datefiled' => '2025-05-09',
            'status'            => 'Pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/gate-passes/{$gpId}");

        $response->assertOk();
        $this->assertDatabaseMissing('gatepass', ['id' => $gpId]);
    }

    public function test_hr_only_route_blocked_for_employee(): void
    {
        $user = $this->createEmployeeUser();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/employees');

        $response->assertStatus(403);
    }

    public function test_hr_can_access_employees_list(): void
    {
        $user = $this->createHRUser();
        $this->createEmployee(['email' => 'other@test.com']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/employees');

        $response->assertOk();
        $response->assertJsonStructure(['employees']);
    }
}
