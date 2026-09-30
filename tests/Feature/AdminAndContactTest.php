<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAndContactTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@maysha.com',
            'email_verified_at' => now(),
        ]);

        $this->customer = User::factory()->create([
            'role' => 'customer',
            'email' => 'user@example.com',
            'email_verified_at' => now(),
        ]);

        $this->category = Category::create([
            'name' => 'Serums',
            'slug' => 'serums',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied. Administrator privileges required.',
            ]);
    }

    public function test_admin_can_view_dashboard_stats(): void
    {
        // Seed an order
        Order::create([
            'reference' => 'MAY-DASH12',
            'user_id' => $this->customer->id,
            'status' => 'paid',
            'subtotal_paise' => 100000,
            'discount_paise' => 0,
            'shipping_paise' => 0,
            'total_paise' => 100000,
            'currency' => 'INR',
            'payment_method' => 'razorpay',
            'shipping_address' => [
                'name' => 'Customer',
                'line1' => 'Street 1',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560001',
            ],
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'totalRevenueRupees',
                    'totalOrders',
                    'totalCustomers',
                    'pendingOrders',
                    'lowStockCount',
                    'recentOrders',
                    'recentAudits',
                ],
            ]);

        $this->assertEquals(1000, $response->json('data.totalRevenueRupees'));
        $this->assertEquals(1, $response->json('data.totalOrders'));
    }

    public function test_admin_can_create_product_with_variants_and_audit_log_is_recorded(): void
    {
        $payload = [
            'name' => 'Squalane Barrier Cream',
            'slug' => 'squalane-barrier-cream',
            'subtitle' => 'Restores moisture barrier',
            'description' => 'Rich barrier repair cream with 100% plant-derived squalane.',
            'category_id' => $this->category->id,
            'badge' => 'NEW',
            'key_benefits' => ['Deep barrier repair', 'Non-comedogenic'],
            'variants' => [
                [
                    'sku' => 'MAY-SQ-50',
                    'size_label' => '50ml',
                    'price_rupees' => 699,
                    'mrp_rupees' => 799,
                    'stock' => 15,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'slug' => 'squalane-barrier-cream',
                    'price' => 699,
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'slug' => 'squalane-barrier-cream',
            'name' => 'Squalane Barrier Cream',
        ]);

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'MAY-SQ-50',
            'price_paise' => 69900,
            'stock' => 15,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.created',
            'actor_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_update_and_delete_product(): void
    {
        $product = Product::create([
            'name' => 'Hydrating Toner',
            'slug' => 'hydrating-toner',
            'description' => 'Alcohol-free soothing toner.',
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $updateResponse = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/products/{$product->id}", [
                'name' => 'Hydrating Glow Toner',
                'description' => 'Updated soothing formula with Centella.',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Hydrating Glow Toner',
                ],
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.updated',
            'subject_id' => $product->id,
        ]);

        $deleteResponse = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/products/{$product->id}");

        $deleteResponse->assertStatus(200);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.deleted',
            'subject_id' => $product->id,
        ]);
    }

    public function test_admin_order_status_update_and_stock_restoration_on_cancellation(): void
    {
        $product = Product::create([
            'name' => 'Niacinamide Serum',
            'slug' => 'niacinamide-serum',
            'description' => 'Blemish formula.',
            'category_id' => $this->category->id,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'MAY-NIA-30',
            'size_label' => '30ml',
            'price_paise' => 59900,
            'mrp_paise' => 69900,
            'stock' => 10,
            'is_active' => true,
        ]);

        $order = Order::create([
            'reference' => 'MAY-ORD999',
            'user_id' => $this->customer->id,
            'status' => 'paid',
            'subtotal_paise' => 59900,
            'discount_paise' => 0,
            'shipping_paise' => 0,
            'total_paise' => 59900,
            'currency' => 'INR',
            'payment_method' => 'razorpay',
            'shipping_address' => [
                'name' => 'Customer',
                'line1' => 'MG Road',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560001',
            ],
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'variant_id' => $variant->id,
            'product_name' => 'Niacinamide Serum',
            'product_slug' => 'niacinamide-serum',
            'size_label' => '30ml',
            'unit_price_paise' => 59900,
            'unit_mrp_paise' => 69900,
            'quantity' => 2,
            'total_paise' => 119800,
        ]);

        // Cancel order -> should restore 2 units of stock (10 -> 12)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => 'cancelled',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'cancelled',
                ],
            ]);

        $this->assertEquals(12, $variant->fresh()->stock);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.status_updated',
            'subject_id' => $order->id,
        ]);
    }

    public function test_admin_can_approve_review_and_recalculate_product_rating(): void
    {
        $product = Product::create([
            'name' => 'Salicylic Acid Cleanser',
            'slug' => 'salicylic-acid-cleanser',
            'description' => 'Gentle exfoliating wash.',
            'category_id' => $this->category->id,
            'rating_avg' => 5.0,
            'review_count' => 0,
        ]);

        $review = Review::create([
            'product_id' => $product->id,
            'user_id' => $this->customer->id,
            'author_name' => 'Priya S.',
            'rating' => 4,
            'title' => 'Works very well',
            'comment' => 'Cleared my blackheads in two weeks!',
            'is_approved' => false,
            'verified_purchase' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/reviews/{$review->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'rating' => 4,
                ],
            ]);

        $this->assertTrue($review->fresh()->is_approved);

        $product->refresh();
        $this->assertEquals(4.0, (float) $product->rating_avg);
        $this->assertEquals(1, $product->review_count);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'review.approved',
            'subject_id' => $review->id,
        ]);
    }

    public function test_newsletter_subscription(): void
    {
        $response = $this->postJson('/api/v1/newsletter', [
            'email' => 'subscriber@example.com',
            'source' => 'footer',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'subscriber@example.com',
            'source' => 'footer',
        ]);
    }

    public function test_newsletter_honeypot_silently_discards_bot(): void
    {
        $response = $this->postJson('/api/v1/newsletter', [
            'email' => 'spambot@example.com',
            'website' => 'http://spam-link.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('newsletter_subscribers', [
            'email' => 'spambot@example.com',
        ]);
    }

    public function test_contact_inquiry_submission_with_sanitization(): void
    {
        $payload = [
            'name' => '<b>Priya Sharma</b>',
            'email' => 'priya@example.com',
            'phone' => '+919876543210',
            'subject' => '<i>Routine Consultation</i>',
            'message' => '<script>alert("hack")</script>Can I use Niacinamide with Vitamin C?',
        ];

        $response = $this->postJson('/api/v1/contact', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $message = ContactMessage::where('email', 'priya@example.com')->first();
        $this->assertNotNull($message);
        $this->assertEquals('Priya Sharma', $message->name);
        $this->assertEquals('Routine Consultation', $message->subject);
        $this->assertStringNotContainsString('<script>', $message->message);
    }

    public function test_contact_honeypot_silently_discards_bot(): void
    {
        $payload = [
            'name' => 'Bot Spammer',
            'email' => 'bot@spammer.com',
            'message' => 'Spam content',
            'bot_trap' => 'filled_by_bot',
        ];

        $response = $this->postJson('/api/v1/contact', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('contact_messages', [
            'email' => 'bot@spammer.com',
        ]);
    }
}
