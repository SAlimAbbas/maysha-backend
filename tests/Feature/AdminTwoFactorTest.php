<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin@maysha.com',
            'password' => Hash::make('AdminPass123!'),
            'role' => 'admin',
        ]);
    }

    public function test_cannot_enable_admin_2fa_without_setting_or_existing_pin(): void
    {
        SiteSetting::set('admin_2fa_pin', null);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/settings', [
                'settings' => [
                    'admin_2fa_enabled' => true,
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_enable_admin_2fa_when_providing_pin_and_pin_is_hashed(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/settings', [
                'settings' => [
                    'admin_2fa_enabled' => true,
                    'admin_2fa_pin' => '8392',
                ],
            ]);

        $response->assertStatus(200);

        $storedPin = SiteSetting::get('admin_2fa_pin');
        $this->assertNotEmpty($storedPin);
        $this->assertNotEquals('8392', $storedPin); // Verify hashed, not plain text
        $this->assertTrue(Hash::check('8392', $storedPin));
    }

    public function test_admin_login_with_hashed_pin(): void
    {
        SiteSetting::set('admin_2fa_enabled', true);
        SiteSetting::set('admin_2fa_pin', Hash::make('5432'));

        // Fails with wrong PIN
        $resFail = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@maysha.com',
            'password' => 'AdminPass123!',
            'two_factor_pin' => '0000',
        ]);
        $resFail->assertStatus(422);

        // Succeeds with correct hashed PIN
        $resSuccess = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@maysha.com',
            'password' => 'AdminPass123!',
            'two_factor_pin' => '5432',
        ]);
        $resSuccess->assertStatus(200);
    }

    public function test_totp_setup_confirm_and_login_flow(): void
    {
        $totpService = app(TotpService::class);

        // 1. Initiate setup
        $setupRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/2fa/setup');

        $setupRes->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['secret', 'qrUri', 'recoveryCodes'],
            ]);

        $secret = $setupRes->json('data.secret');
        $recoveryCodes = $setupRes->json('data.recoveryCodes');
        $this->assertCount(10, $recoveryCodes);

        // Generate valid 6-digit TOTP code using reflection or service method
        $step = (int) floor(time() / 30);
        $reflection = new \ReflectionClass($totpService);
        $calcMethod = $reflection->getMethod('calculateCode');
        $calcMethod->setAccessible(true);
        $secretBytesMethod = $reflection->getMethod('base32Decode');
        $secretBytesMethod->setAccessible(true);

        $validCode = $calcMethod->invoke($totpService, $secretBytesMethod->invoke($totpService, $secret), $step);

        // 2. Confirm setup with code
        $confirmRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/2fa/confirm', [
                'code' => $validCode,
            ]);

        $confirmRes->assertStatus(200);
        $this->assertTrue($this->admin->fresh()->hasTwoFactorEnabled());

        // 3. Login with TOTP code
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@maysha.com',
            'password' => 'AdminPass123!',
            'two_factor_code' => $validCode,
        ]);

        $loginRes->assertStatus(200)
            ->assertJson(['success' => true]);

        // 4. Login with recovery code
        $usedRecoveryCode = $recoveryCodes[0];
        $recoveryLoginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@maysha.com',
            'password' => 'AdminPass123!',
            'two_factor_code' => $usedRecoveryCode,
        ]);

        $recoveryLoginRes->assertStatus(200);
        // Verify recovery code was consumed (now 9 remaining)
        $this->assertCount(9, $this->admin->fresh()->two_factor_recovery_codes);

        // Reusing the same recovery code should now fail
        $reuseRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@maysha.com',
            'password' => 'AdminPass123!',
            'two_factor_code' => $usedRecoveryCode,
        ]);
        $reuseRes->assertStatus(422);
    }
}
