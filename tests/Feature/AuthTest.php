<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class AuthTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = $this->createHRUser();

        $response = $this->post('/login', [
            'username' => 'hradmin',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $this->createHRUser();

        $response = $this->post('/login', [
            'username' => 'hradmin',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_user_cannot_login_with_nonexistent_username(): void
    {
        $response = $this->post('/login', [
            'username' => 'nobody',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_login_requires_username_and_password(): void
    {
        $response = $this->post('/login', []);

        $response->assertSessionHasErrors(['username', 'password']);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = $this->createHRUser();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_authenticated_user_is_redirected_from_login_page(): void
    {
        $user = $this->createHRUser();

        $response = $this->actingAs($user)->get('/');

        // Guest middleware should redirect authenticated users
        $response->assertRedirect('/dashboard');
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/');
    }
}
