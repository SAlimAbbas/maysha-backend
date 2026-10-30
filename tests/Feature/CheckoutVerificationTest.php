<?php

namespace Tests\Feature;

use App\Actions\CreateOrderAction;
use App\Http\Controllers\Api\V1\CheckoutVerificationController;
use App\Mail\CheckoutOtpMail;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
        SiteSetting::set('cod_enabled', true);
        SiteSetting::set('cod_fee', 50);
        SiteSetting::set('checkout_email_verification_enabled', true);
    }

    public function test_can_send_otp_to_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/checkout/send-otp', [
            'email' => 'client@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        Mail::assertQueued(CheckoutOtpMail::class, function ($mail) {
            return $mail->hasTo('client@example.com');
        });

        $this->assertTrue(Cache::has('checkout_otp:' . sha1('client@example.com')));
    }

    public function test_can_verify_correct_otp(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/checkout/send-otp', [
            'email' => 'client@example.com',
        ]);

        $otp = null;
        Mail::assertQueued(CheckoutOtpMail::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;
            return true;
        });

        $this->assertNotNull($otp);

        $verifyResponse = $this->postJson('/api/v1/checkout/verify-otp', [
            'email' => 'client@example.com',
            'otp' => $otp,
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.email', 'client@example.com');

        $token = $verifyResponse->json('data.verificationToken');
        $this->assertNotEmpty($token);

        $this->assertTrue(CheckoutVerificationController::isEmailVerified('client@example.com', $token));
    }

    public function test_rejects_incorrect_otp(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/checkout/send-otp', [
            'email' => 'client@example.com',
        ]);

        $response = $this->postJson('/api/v1/checkout/verify-otp', [
            'email' => 'client@example.com',
            'otp' => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cod_order_requires_verified_email_when_enabled(): void
    {
        $variant = ProductVariant::first();

        $response = $this->postJson('/api/v1/orders', [
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
            'shipping_address' => [
                'name' => 'Unverified Customer',
                'phone' => '9876543210',
                'line1' => 'Flat 101, Sunshine Apartments, MG Road',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400001',
            ],
            'payment_method' => 'cod',
            'guest_email' => 'unverified@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.email_verification_required.0', true);
    }

    public function test_cod_order_succeeds_with_verified_email(): void
    {
        Mail::fake();

        // 1. Send & verify OTP
        $this->postJson('/api/v1/checkout/send-otp', ['email' => 'verified@example.com']);
        $otp = null;
        Mail::assertQueued(CheckoutOtpMail::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;
            return true;
        });

        $verifyRes = $this->postJson('/api/v1/checkout/verify-otp', [
            'email' => 'verified@example.com',
            'otp' => $otp,
        ]);
        $token = $verifyRes->json('data.verificationToken');

        // 2. Place COD order with verified token
        $variant = ProductVariant::first();
        $response = $this->postJson('/api/v1/orders', [
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
            'shipping_address' => [
                'name' => 'Verified Customer',
                'phone' => '9876543210',
                'line1' => 'Flat 101, Sunshine Apartments, MG Road',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400001',
            ],
            'payment_method' => 'cod',
            'guest_email' => 'verified@example.com',
            'verification_token' => $token,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }
}
