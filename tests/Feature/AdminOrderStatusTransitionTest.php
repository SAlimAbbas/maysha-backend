<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);

        $category = \App\Models\Category::create([
            'name' => 'Skincare',
            'slug' => 'skincare',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Rose Water Mist',
            'slug' => 'rose-water-mist',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'MAY-RWM-100',
            'size_label' => '100ml',
            'price_paise' => 89900,
            'mrp_paise' => 99900,
            'stock' => 20,
            'is_active' => true,
        ]);
    }

    public function test_valid_status_transitions_succeed(): void
    {
        $order = Order::create([
            'reference' => 'MAY-TRANS-01',
            'status' => 'pending_payment',
            'subtotal_paise' => 89900,
            'total_paise' => 89900,
            'currency' => 'INR',
            'shipping_address' => ['name' => 'Test Customer', 'pincode' => '560001'],
        ]);

        // 1. pending_payment -> processing
        $res1 = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => 'processing',
            ]);
        $res1->assertStatus(200);
        $this->assertEquals('processing', $order->fresh()->status);

        // 2. processing -> shipped
        $res2 = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => 'shipped',
            ]);
        $res2->assertStatus(200);
        $this->assertEquals('shipped', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->shipped_at);

        // 3. shipped -> delivered
        $res3 = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => 'delivered',
            ]);
        $res3->assertStatus(200);
        $this->assertEquals('delivered', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->delivered_at);
    }

    public function test_invalid_status_transitions_are_rejected(): void
    {
        $order = Order::create([
            'reference' => 'MAY-TRANS-02',
            'status' => 'delivered',
            'subtotal_paise' => 89900,
            'total_paise' => 89900,
            'currency' => 'INR',
            'shipping_address' => ['name' => 'Test Customer', 'pincode' => '560001'],
        ]);

        // delivered -> pending_payment must be rejected
        $res = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => 'pending_payment',
            ]);

        $res->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertEquals('delivered', $order->fresh()->status);
    }

    public function test_cancelling_unfulfilled_order_restores_stock(): void
    {
        $initialStock = $this->variant->stock; // 20

        $order = Order::create([
            'reference' => 'MAY-TRANS-03',
            'status' => 'processing',
            'subtotal_paise' => 89900,
            'total_paise' => 89900,
            'currency' => 'INR',
            'shipping_address' => ['name' => 'Test Customer', 'pincode' => '560001'],
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'variant_id' => $this->variant->id,
            'product_name' => 'Rose Water Mist',
            'product_slug' => 'rose-water-mist',
            'size_label' => '100ml',
            'quantity' => 2,
            'unit_price_paise' => 89900,
            'unit_mrp_paise' => 99900,
            'total_paise' => 179800,
        ]);

        // Cancel order from processing
        $res = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => 'cancelled',
            ]);

        $res->assertStatus(200);
        // Stock should be incremented by 2
        $this->assertEquals($initialStock + 2, $this->variant->fresh()->stock);
    }
}
