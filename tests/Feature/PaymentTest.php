<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    public function test_can_create_razorpay_order(): void
    {
        $order = Order::create([
            'reference' => 'MAY-TEST01',
            'status' => 'pending_payment',
            'subtotal_paise' => 39900,
            'total_paise' => 39900,
            'currency' => 'INR',
            'shipping_address' => [
                'name' => 'Aditi Rao',
                'phone' => '9876543210',
                'line1' => '102 Green Acres',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '411001',
            ],
            'payment_method' => 'razorpay',
        ]);

        $response = $this->postJson('/api/v1/payments/razorpay/create-order', [
            'order_reference' => $order->reference,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'amount' => 399,
                    'currency' => 'INR',
                    'orderReference' => 'MAY-TEST01',
                ],
            ])
            ->assertJsonStructure([
                'data' => ['keyId', 'razorpayOrderId'],
            ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'created',
        ]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $order = Order::create([
            'reference' => 'MAY-TEST02',
            'status' => 'pending_payment',
            'subtotal_paise' => 49900,
            'total_paise' => 49900,
            'currency' => 'INR',
            'shipping_address' => [
                'name' => 'Aditi Rao',
                'phone' => '9876543210',
                'line1' => '102 Green Acres',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '411001',
            ],
            'payment_method' => 'razorpay',
        ]);

        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_fake_123',
            'status' => 'created',
            'amount_paise' => 49900,
        ]);

        $response = $this->postJson('/api/v1/payments/razorpay/verify', [
            'razorpay_order_id' => 'order_fake_123',
            'razorpay_payment_id' => 'pay_fake_456',
            'razorpay_signature' => 'invalid_signature_string',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid payment signature verification failed.',
            ]);
    }

    public function test_valid_signature_marks_order_as_paid(): void
    {
        $order = Order::create([
            'reference' => 'MAY-TEST03',
            'status' => 'pending_payment',
            'subtotal_paise' => 59900,
            'total_paise' => 59900,
            'currency' => 'INR',
            'shipping_address' => [
                'name' => 'Aditi Rao',
                'phone' => '9876543210',
                'line1' => '102 Green Acres',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '411001',
            ],
            'payment_method' => 'razorpay',
        ]);

        $gatewayOrderId = 'order_valid_123';
        $gatewayPaymentId = 'pay_valid_456';

        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => $gatewayOrderId,
            'status' => 'created',
            'amount_paise' => 59900,
        ]);

        // Calculate expected HMAC signature with test secret key
        $secret = env('RAZORPAY_KEY_SECRET', 'test_secret_key_123');
        $validSignature = hash_hmac('sha256', $gatewayOrderId.'|'.$gatewayPaymentId, $secret);

        $response = $this->postJson('/api/v1/payments/razorpay/verify', [
            'razorpay_order_id' => $gatewayOrderId,
            'razorpay_payment_id' => $gatewayPaymentId,
            'razorpay_signature' => $validSignature,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals('paid', $order->fresh()->status);
        $this->assertEquals('captured', Payment::where('gateway_order_id', $gatewayOrderId)->first()->status);
    }
}
