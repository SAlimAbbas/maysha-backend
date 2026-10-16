<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CommerceAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
        $this->seed(SiteSettingSeeder::class);
    }

    public function test_logged_in_user_checkout_links_to_account_and_shows_in_account_orders(): void
    {
        $user = User::factory()->create(['email' => 'aditi@example.com']);
        $token = $user->createToken('test')->plainTextToken;

        $variant = ProductVariant::first();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/orders', [
                'items' => [
                    ['variant_id' => $variant->id, 'quantity' => 1],
                ],
                'shipping_address' => [
                    'name' => 'Aditi Sharma',
                    'phone' => '9876543210',
                    'line1' => 'Flat 201, Green View',
                    'city' => 'Bengaluru',
                    'state' => 'Karnataka',
                    'pincode' => '560001',
                ],
                'payment_method' => 'cod',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'paymentMethod' => 'cod',
                ],
            ]);

        $orderRef = $response->json('data.reference');

        // Verify order is linked to user in database
        $this->assertDatabaseHas('orders', [
            'reference' => $orderRef,
            'user_id' => $user->id,
            'guest_email' => 'aditi@example.com',
        ]);

        // Verify order appears in /api/v1/account/orders with standard envelope
        $ordersResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/account/orders');

        $ordersResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data',
                'meta' => ['pagination' => ['currentPage', 'total']],
            ]);

        $this->assertEquals($orderRef, $ordersResponse->json('data.0.reference'));
    }

    public function test_guest_cart_merges_into_user_cart_on_login(): void
    {
        $user = User::factory()->create([
            'email' => 'merge@example.com',
            'password' => bcrypt('StrongPass123!'),
        ]);

        $variant = ProductVariant::first();

        // 1. Create a guest cart
        $guestToken = 'cart_guest_'.bin2hex(random_bytes(10));
        $guestCart = Cart::create([
            'token' => $guestToken,
            'expires_at' => now()->addDays(7),
        ]);
        $guestCart->items()->create([
            'variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        // 2. Login with X-Cart-Token header
        $loginResponse = $this->withHeader('X-Cart-Token', $guestToken)
            ->postJson('/api/v1/auth/login', [
                'email' => 'merge@example.com',
                'password' => 'StrongPass123!',
            ]);

        $loginResponse->assertStatus(200);

        // 3. User cart now contains the item
        $userCart = Cart::where('user_id', $user->id)->first();
        $this->assertNotNull($userCart);
        $this->assertEquals(2, $userCart->items()->where('variant_id', $variant->id)->value('quantity'));
    }

    public function test_cod_limit_is_strictly_enforced(): void
    {
        SiteSetting::set('cod_max_rupees', 2000);

        // Variant price is higher than COD limit
        $variant = ProductVariant::first();
        $variant->update(['price_paise' => 250000]); // ₹2,500

        $response = $this->postJson('/api/v1/orders', [
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
            'shipping_address' => [
                'name' => 'Rohan Sen',
                'phone' => '9876543210',
                'line1' => 'Park Street',
                'city' => 'Kolkata',
                'state' => 'West Bengal',
                'pincode' => '700001',
            ],
            'payment_method' => 'cod',
            'guest_email' => 'rohan@example.com',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Cash on Delivery is only available for orders up to', $response->json('message'));
    }

    public function test_unpaid_order_expiration_releases_stock_and_records_event(): void
    {
        $variant = ProductVariant::first();
        $initialStock = $variant->stock;

        // Place pending order (holds 2 units)
        $order = Order::create([
            'reference' => 'MAY-EXP01',
            'status' => 'pending_payment',
            'reserved_until' => now()->subMinutes(10), // already expired
            'subtotal_paise' => 100000,
            'total_paise' => 100000,
            'currency' => 'INR',
            'shipping_address' => [
                'name' => 'Expired User',
                'phone' => '9876543210',
                'line1' => 'Test Line',
                'city' => 'Delhi',
                'state' => 'Delhi',
                'pincode' => '110001',
            ],
            'payment_method' => 'razorpay',
        ]);

        $order->items()->create([
            'variant_id' => $variant->id,
            'product_name' => $variant->product->name,
            'product_slug' => $variant->product->slug,
            'size_label' => $variant->size_label,
            'unit_price_paise' => 50000,
            'unit_mrp_paise' => 60000,
            'quantity' => 2,
            'total_paise' => 100000,
        ]);

        $variant->decrement('stock', 2);
        $this->assertEquals($initialStock - 2, $variant->fresh()->stock);

        // Run expiration command
        Artisan::call('orders:expire-unpaid');

        // Order cancelled
        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertNull($order->fresh()->reserved_until);

        // Stock restored!
        $this->assertEquals($initialStock, $variant->fresh()->stock);

        // Event recorded
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_pincode_validation_and_unserviceable_pincode(): void
    {
        // 1. Invalid regex format rejected
        $response = $this->getJson('/api/v1/shipping/pincode/1234');
        $response->assertStatus(422);

        // 2. Unknown pincode returns serviceable false
        $response = $this->getJson('/api/v1/shipping/pincode/999999');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pincode' => '999999',
                    'serviceable' => false,
                ],
            ]);
    }

    public function test_first_order_only_coupon_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $coupon = Coupon::create([
            'code' => 'FIRSTONLY',
            'type' => 'percent',
            'value' => 20,
            'first_order_only' => true,
            'is_active' => true,
        ]);

        // User already has a paid order
        Order::create([
            'reference' => 'MAY-PAST01',
            'user_id' => $user->id,
            'status' => 'delivered',
            'subtotal_paise' => 50000,
            'total_paise' => 50000,
            'currency' => 'INR',
            'shipping_address' => ['name' => 'Past Buyer', 'pincode' => '560001'],
        ]);

        $this->assertFalse($coupon->isValidFor(100000, $user));
    }

    public function test_order_invoice_generation_and_tax_calculation(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $order = Order::create([
            'reference' => 'MAY-INVTEST',
            'user_id' => $user->id,
            'status' => 'processing',
            'subtotal_paise' => 118000, // ₹1,180
            'tax_paise' => 18000, // ₹180 GST (18% inclusive)
            'total_paise' => 118000,
            'currency' => 'INR',
            'shipping_address' => [
                'name' => 'Tax Tester',
                'phone' => '9876543210',
                'line1' => 'MG Road',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560001',
            ],
            'payment_method' => 'cod',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/orders/{$order->reference}/invoice");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'orderReference' => 'MAY-INVTEST',
                    'pricing' => [
                        'tax' => 180,
                        'total' => 1180,
                    ],
                ],
            ]);

        $this->assertNotNull($response->json('data.invoiceNo'));
    }

    public function test_product_variants_expose_capped_stock_and_low_stock_flag(): void
    {
        $product = Product::active()->first();
        $variant = $product->variants()->first();
        $variant->update([
            'stock' => 3,
            'low_stock_threshold' => 5,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $vData = $response->json('data.product.variants.0');
        $this->assertEquals(3, $vData['stock']);
        $this->assertTrue($vData['lowStock']);
    }

    public function test_concern_filter_prefix_matching(): void
    {
        // Concern slug in catalog is 'acne-control'
        $response = $this->getJson('/api/v1/products?concern=acne');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertNotEmpty($response->json('data'));
    }
}
