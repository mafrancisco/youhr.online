<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
    }

    // -----------------------------------------------------------------------
    // HR-only routes
    // -----------------------------------------------------------------------

    public function test_hr_can_access_employees_page(): void
    {
        $user = $this->createHRUser();

        $response = $this->actingAs($user)->get('/employees');

        $response->assertStatus(200);
    }

    public function test_employee_cannot_access_employees_page(): void
    {
        $user = $this->createEmployeeUser();

        $response = $this->actingAs($user)->get('/employees');

        $response->assertStatus(403);
    }

    public function test_hr_can_access_schedules_page(): void
    {
        $user = $this->createHRUser();

        $response = $this->actingAs($user)->get('/schedules');

        $response->assertStatus(200);
    }

    public function test_employee_cannot_access_schedules_page(): void
    {
        $user = $this->createEmployeeUser();

        $response = $this->actingAs($user)->get('/schedules');

        $response->assertStatus(403);
    }

    public function test_hr_can_access_attendance_upload(): void
    {
        $user = $this->createHRUser();

        $response = $this->actingAs($user)->get('/attendance/upload');

        $response->assertStatus(200);
    }

    public function test_employee_cannot_access_attendance_upload(): void
    {
        $user = $this->createEmployeeUser();

        $response = $this->actingAs($user)->get('/attendance/upload');

        $response->assertStatus(403);
    }

    public function test_hr_can_access_credits_page(): void
    {
        $user = $this->createHRUser();

        $response = $this->actingAs($user)->get('/credits');

        $response->assertStatus(200);
    }

    public function test_employee_cannot_access_credits_page(): void
    {
        $user = $this->createEmployeeUser();

        $response = $this->actingAs($user)->get('/credits');

        $response->assertStatus(403);
    }

    public function test_hr_can_access_settings(): void
    {
        $user = $this->createHRUser();

        $response = $this->actingAs($user)->get('/admin/settings');

        $response->assertStatus(200);
    }

    public function test_employee_cannot_access_settings(): void
    {
        $user = $this->createEmployeeUser();

        $response = $this->actingAs($user)->get('/admin/settings');

        $response->assertStatus(403);
    }

    // -----------------------------------------------------------------------
    // Employee-only routes
    // -----------------------------------------------------------------------

    public function test_employee_can_access_dtr_page(): void
    {
        $user = $this->createEmployeeUser();
        $this->createEmployee(['email' => $user->email]);

        $response = $this->actingAs($user)->get('/dtr');

        $response->assertStatus(200);
    }

    public function test_hr_cannot_access_employee_dtr_page(): void
    {
        $user = $this->createHRUser();

        $response = $this->actingAs($user)->get('/dtr');

        $response->assertStatus(403);
    }

    public function test_employee_can_access_leaves_page(): void
    {
        $user = $this->createEmployeeUser();
        $this->createEmployee(['email' => $user->email]);

        $response = $this->actingAs($user)->get('/leaves');

        $response->assertStatus(200);
    }

    public function test_hr_cannot_access_employee_leaves_page(): void
    {
        $user = $this->createHRUser();

        $response = $this->actingAs($user)->get('/leaves');

        $response->assertStatus(403);
    }

    public function test_employee_can_access_gate_passes_page(): void
    {
        $user = $this->createEmployeeUser();
        $this->createEmployee(['email' => $user->email]);

        $response = $this->actingAs($user)->get('/gate-passes');

        $response->assertStatus(200);
    }

    public function test_hr_cannot_access_employee_gate_passes_page(): void
    {
        $user = $this->createHRUser();

        $response = $this->actingAs($user)->get('/gate-passes');

        $response->assertStatus(403);
    }
}
