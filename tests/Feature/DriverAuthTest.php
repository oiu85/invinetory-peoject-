<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DriverAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_login(): void
    {
        $driver = User::factory()->create([
            'type' => 'driver',
            'email' => 'driver@test.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/driver/login', [
            'email' => 'driver@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'type'],
                'token',
                'type',
            ]);
    }

    public function test_forgot_password_returns_success(): void
    {
        $driver = User::factory()->create([
            'type' => 'driver',
            'email' => 'driver@test.com',
        ]);

        $response = $this->postJson('/api/driver/forgot-password', [
            'email' => 'driver@test.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_reset_password_with_valid_token(): void
    {
        $driver = User::factory()->create([
            'type' => 'driver',
            'email' => 'driver@test.com',
            'password' => Hash::make('oldpassword'),
        ]);

        // Create reset token
        $token = 'test-token-123';
        DB::table('password_reset_tokens')->insert([
            'email' => 'driver@test.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/driver/reset-password', [
            'email' => 'driver@test.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify password was changed
        $driver->refresh();
        $this->assertTrue(Hash::check('newpassword123', $driver->password));
    }
}
