<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_user_is_identified_correctly(): void
    {
        $user = User::create([
            'username' => 'hr1',
            'password' => 'password',
            'fullname' => 'HR User',
            'email'    => 'hr@test.com',
            'type'     => 1,
        ]);

        $this->assertTrue($user->isHR());
        $this->assertFalse($user->isEmployee());
    }

    public function test_employee_user_is_identified_correctly(): void
    {
        $user = User::create([
            'username' => 'emp1',
            'password' => 'password',
            'fullname' => 'Employee User',
            'email'    => 'emp@test.com',
            'type'     => 2,
        ]);

        $this->assertFalse($user->isHR());
        $this->assertTrue($user->isEmployee());
    }

    public function test_auth_identifier_is_username(): void
    {
        $user = new User();
        $this->assertEquals('username', $user->getAuthIdentifierName());
    }

    public function test_password_is_hashed_automatically(): void
    {
        $user = User::create([
            'username' => 'test',
            'password' => 'plaintext',
            'fullname' => 'Test',
            'email'    => 'test@test.com',
            'type'     => 1,
        ]);

        $this->assertNotEquals('plaintext', $user->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('plaintext', $user->password));
    }
}
