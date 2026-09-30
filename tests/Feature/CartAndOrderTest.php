<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartAndOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    public function test_cart_prices_are_computed_strictly_from_db_variants(): void
    {
        $variant = ProductVariant::first();

        // Client passes a fraudulent price of ₹10, server MUST ignore and use DB price
        $response = $this->withHeader('X-Cart-Token', 'test_cart_token_123')
            ->postJson('/api/v1/cart/items', [
                'variant_id' => $variant->id,
                'quantity' => 2,
                'price' => 10,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'subtotal' => $variant->price_rupees * 2,
                ],
            ]);
    }

    public function test_cannot_add_more_quantity_than_available_stock(): void
    {
        $variant = ProductVariant::first();
        $variant->update(['stock' => 3]);

        $response = $this->withHeader('X-Cart-Token', 'test_cart_token_stock')
            ->postJson('/api/v1/cart/items', [
                'variant_id' => $variant->id,
                'quantity' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_apply_valid_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'CLEAN10',
            'type' => 'percent',
            'value' => 10,
            'min_subtotal_paise' => 10000,
            'is_active' => true,
        ]);

        $variant = ProductVariant::first();

        $cartResponse = $this->withHeader('X-Cart-Token', 'test_cart_coupon')
            ->postJson('/api/v1/cart/items', [
                'variant_id' => $variant->id,
                'quantity' => 2,
            ]);

        $response = $this->withHeader('X-Cart-Token', 'test_cart_coupon')
            ->postJson('/api/v1/cart/coupon', [
                'code' => 'CLEAN10',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'couponCode' => 'CLEAN10',
                ],
            ]);

        $this->assertGreaterThan(0, $response->json('data.discount'));
    }

    public function test_transactional_order_creation_with_idempotency_key(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::first();
        $initialStock = $variant->stock;

        $idempotencyKey = 'idemp_'.bin2hex(random_bytes(16));

        $orderPayload = [
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 2],
            ],
            'shipping_address' => [
                'name' => 'Meera Patel',
                'phone' => '9876543210',
                'line1' => 'Flat 402, Lotus Residency',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560001',
            ],
            'payment_method' => 'cod',
        ];

        // 1. Initial Order Placement
        $response1 = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/orders', $orderPayload);

        $response1->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $orderRef = $response1->json('data.reference');
        $this->assertNotNull($orderRef);

        // Stock was decremented by 2
        $this->assertEquals($initialStock - 2, $variant->fresh()->stock);

        // 2. Duplicate Submission with Same Idempotency-Key
        $response2 = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/orders', $orderPayload);

        $response2->assertStatus(200);
        $this->assertEquals($orderRef, $response2->json('data.reference'));

        // Stock was NOT decremented again!
        $this->assertEquals($initialStock - 2, $variant->fresh()->stock);
    }

    public function test_idor_protection_prevents_user_from_viewing_another_users_order(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $variant = ProductVariant::first();

        $order = Order::create([
            'reference' => 'MAY-PATEL1',
            'user_id' => $userA->id,
            'status' => 'pending_payment',
            'subtotal_paise' => 39900,
            'total_paise' => 39900,
            'currency' => 'INR',
            'shipping_address' => [
                'name' => 'User A',
                'phone' => '9999999999',
                'line1' => 'Street 1',
                'city' => 'Delhi',
                'state' => 'Delhi',
                'pincode' => '110001',
            ],
            'payment_method' => 'cod',
        ]);

        // User B attempts to access User A's order by reference
        $response = $this->actingAs($userB, 'sanctum')
            ->getJson("/api/v1/orders/{$order->reference}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied. You do not have permission to view this order.',
            ]);
    }
}
