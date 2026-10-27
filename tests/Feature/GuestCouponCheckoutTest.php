<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GuestCouponCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\CouponSeeder::class);

        $category = \App\Models\Category::create([
            'name' => 'Skincare',
            'slug' => 'skincare',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Face Cleanser',
            'slug' => 'face-cleanser',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'MAY-FC-100',
            'size_label' => '100ml',
            'price_paise' => 100000, // ₹1,000
            'mrp_paise' => 120000,
            'stock' => 50,
            'is_active' => true,
        ]);
    }

    public function test_guest_checkout_with_each_seeded_coupon_returns_201(): void
    {
        $seededCoupons = ['MAYSHA10', 'WELCOME10', 'CLEAN10'];

        foreach ($seededCoupons as $index => $couponCode) {
            $guestEmail = "guest{$index}@example.com";
            $guestPhone = "987654321{$index}";

            $response = $this->postJson('/api/v1/orders', [
                'guest_email' => $guestEmail,
                'items' => [
                    ['variant_id' => $this->variant->id, 'quantity' => 1],
                ],
                'shipping_address' => [
                    'name' => "Guest {$couponCode}",
                    'phone' => $guestPhone,
                    'line1' => 'MG Road',
                    'city' => 'Bengaluru',
                    'state' => 'Karnataka',
                    'pincode' => '560001',
                ],
                'payment_method' => 'cod',
                'coupon_code' => $couponCode,
            ]);

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                ]);

            $this->assertDatabaseHas('orders', [
                'guest_email' => $guestEmail,
                'coupon_code' => $couponCode,
            ]);

            $this->assertDatabaseHas('coupon_redemptions', [
                'order_id' => $response->json('data.id'),
            ]);
        }
    }

    public function test_second_use_of_first_order_only_coupon_by_same_email_or_phone_returns_422(): void
    {
        $coupon = Coupon::where('code', 'WELCOME10')->first();
        $coupon->update([
            'first_order_only' => true,
            'per_user_limit' => 1,
        ]);

        $email = 'repeatguest@example.com';
        $phone = '9998887776';

        // 1. First order succeeds
        $res1 = $this->postJson('/api/v1/orders', [
            'guest_email' => $email,
            'items' => [
                ['variant_id' => $this->variant->id, 'quantity' => 1],
            ],
            'shipping_address' => [
                'name' => 'First Order Guest',
                'phone' => $phone,
                'line1' => 'MG Road',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560001',
            ],
            'payment_method' => 'cod',
            'coupon_code' => 'WELCOME10',
        ]);

        $res1->assertStatus(201);

        // 2. Second use by same email returns 422
        $res2 = $this->postJson('/api/v1/orders', [
            'guest_email' => $email,
            'items' => [
                ['variant_id' => $this->variant->id, 'quantity' => 1],
            ],
            'shipping_address' => [
                'name' => 'Second Try Same Email',
                'phone' => '9111222333', // different phone
                'line1' => 'Brigade Road',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560001',
            ],
            'payment_method' => 'cod',
            'coupon_code' => 'WELCOME10',
        ]);

        $res2->assertStatus(422);

        // 3. Second use by same phone (with different email) also returns 422
        $res3 = $this->postJson('/api/v1/orders', [
            'guest_email' => 'different@example.com',
            'items' => [
                ['variant_id' => $this->variant->id, 'quantity' => 1],
            ],
            'shipping_address' => [
                'name' => 'Second Try Same Phone',
                'phone' => $phone, // same phone
                'line1' => 'Indiranagar',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560001',
            ],
            'payment_method' => 'cod',
            'coupon_code' => 'WELCOME10',
        ]);

        $res3->assertStatus(422);
    }

    public function test_unpaid_order_expiry_releases_coupon_redemption(): void
    {
        $coupon = Coupon::where('code', 'MAYSHA10')->first();
        $coupon->update([
            'per_user_limit' => 1,
        ]);

        $email = 'unpaid@example.com';
        $phone = '9777666555';

        // 1. Place order with online payment (status = pending_payment)
        $res1 = $this->postJson('/api/v1/orders', [
            'guest_email' => $email,
            'items' => [
                ['variant_id' => $this->variant->id, 'quantity' => 1],
            ],
            'shipping_address' => [
                'name' => 'Unpaid Guest',
                'phone' => $phone,
                'line1' => 'MG Road',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560001',
            ],
            'payment_method' => 'razorpay',
            'coupon_code' => 'MAYSHA10',
        ]);

        $res1->assertStatus(201);
        $orderId = $res1->json('data.id');

        $this->assertDatabaseHas('coupon_redemptions', [
            'order_id' => $orderId,
            'coupon_id' => $coupon->id,
        ]);

        // Fast-forward order reservation expiry
        $order = Order::find($orderId);
        $order->update([
            'reserved_until' => now()->subMinutes(1),
        ]);

        // Run expiration command
        Artisan::call('orders:expire-unpaid');

        // Verify order is cancelled
        $this->assertEquals('cancelled', $order->fresh()->status);

        // Verify coupon redemption was released
        $this->assertDatabaseMissing('coupon_redemptions', [
            'order_id' => $orderId,
        ]);

        // Guest can now reuse the coupon
        $res2 = $this->postJson('/api/v1/orders', [
            'guest_email' => $email,
            'items' => [
                ['variant_id' => $this->variant->id, 'quantity' => 1],
            ],
            'shipping_address' => [
                'name' => 'Unpaid Guest Retry',
                'phone' => $phone,
                'line1' => 'MG Road',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560001',
            ],
            'payment_method' => 'cod',
            'coupon_code' => 'MAYSHA10',
        ]);

        $res2->assertStatus(201);
    }
}
