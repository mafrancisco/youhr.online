<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    public function test_can_login_and_receive_token(): void
    {
        $this->createHRUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'username'    => 'hradmin',
            'password'    => 'password',
            'device_name' => 'test-device',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'user' => ['id', 'username', 'role']]);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->createHRUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'username'    => 'hradmin',
            'password'    => 'wrong',
            'device_name' => 'test-device',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('username');
    }

    public function test_login_requires_device_name(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'hradmin',
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('device_name');
    }

    public function test_can_get_authenticated_user_profile(): void
    {
        $user = $this->createHRUser();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJson([
            'username' => 'hradmin',
            'role'     => 'hr',
        ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_can_logout_and_revoke_token(): void
    {
        $user = $this->createHRUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertOk();
        $response->assertJson(['message' => 'Logged out.']);
    }
}
