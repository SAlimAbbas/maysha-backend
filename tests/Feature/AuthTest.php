<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_with_strong_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Aanya Sharma',
            'email' => 'aanya@example.com',
            'password' => 'StrongPass123!',
            'phone' => '9876543210',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'Aanya Sharma',
                        'email' => 'aanya@example.com',
                        'role' => 'customer',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'aanya@example.com',
            'role' => 'customer',
        ]);
    }

    public function test_registration_fails_with_weak_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Aanya Sharma',
            'email' => 'aanya@example.com',
            'password' => 'weak',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_customer_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'priya@example.com',
            'password' => Hash::make('StrongPass123!'),
            'role' => 'customer',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'priya@example.com',
            'password' => 'StrongPass123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => 'priya@example.com',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => ['token', 'user'],
            ]);
    }

    public function test_login_returns_401_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'priya@example.com',
            'password' => Hash::make('StrongPass123!'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'priya@example.com',
            'password' => 'IncorrectPass999!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials provided.',
            ]);
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => (string) $user->id,
                        'email' => $user->email,
                    ],
                ],
            ]);
    }

    public function test_customer_cannot_access_admin_endpoints(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/admin/banners', [
            'title' => 'Unauthorized Banner',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied. Administrator privileges required.',
            ]);
    }

    public function test_forgot_password_gives_uniform_response(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_admin_cannot_login_without_pin_when_2fa_enabled(): void
    {
        SiteSetting::set('admin_2fa_enabled', true);
        SiteSetting::set('admin_2fa_pin', '9281');

        User::factory()->create([
            'email' => 'admin@maysha.com',
            'password' => Hash::make('AdminSecret123!'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@maysha.com',
            'password' => 'AdminSecret123!',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Two-Factor Authentication PIN is required for administrator login.',
            ]);
    }

    public function test_admin_cannot_login_with_wrong_pin_when_2fa_enabled(): void
    {
        SiteSetting::set('admin_2fa_enabled', true);
        SiteSetting::set('admin_2fa_pin', '9281');

        User::factory()->create([
            'email' => 'admin@maysha.com',
            'password' => Hash::make('AdminSecret123!'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@maysha.com',
            'password' => 'AdminSecret123!',
            'two_factor_pin' => '0000',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid 2FA security PIN. Administrative access denied.',
            ]);
    }

    public function test_admin_can_login_with_correct_pin_when_2fa_enabled(): void
    {
        SiteSetting::set('admin_2fa_enabled', true);
        SiteSetting::set('admin_2fa_pin', '9281');

        User::factory()->create([
            'email' => 'admin@maysha.com',
            'password' => Hash::make('AdminSecret123!'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@maysha.com',
            'password' => 'AdminSecret123!',
            'two_factor_pin' => '9281',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful.',
            ])
            ->assertJsonStructure([
                'data' => ['token', 'user'],
            ]);
    }

    public function test_admin_can_login_without_pin_when_2fa_disabled(): void
    {
        SiteSetting::set('admin_2fa_enabled', false);

        User::factory()->create([
            'email' => 'admin@maysha.com',
            'password' => Hash::make('AdminSecret123!'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@maysha.com',
            'password' => 'AdminSecret123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful.',
            ]);
    }

    public function test_customer_can_login_without_pin_even_when_admin_2fa_enabled(): void
    {
        SiteSetting::set('admin_2fa_enabled', true);
        SiteSetting::set('admin_2fa_pin', '9281');

        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => Hash::make('CustomerSecret123!'),
            'role' => 'customer',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'CustomerSecret123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}
