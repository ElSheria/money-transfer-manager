<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_logs_in_with_otp_before_receiving_api_token(): void
    {
        $user = User::factory()->create([
            'password' => 'secret-password',
            'role' => User::ROLE_DG,
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $login->assertOk()
            ->assertJsonStructure(['user_id', 'debug_otp']);

        $verify = $this->postJson('/api/auth/verify-otp', [
            'user_id' => $login->json('user_id'),
            'code' => $login->json('debug_otp'),
        ]);

        $verify->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }
}
