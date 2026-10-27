<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminAndCustomerShippingApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.shiprocket.base_url', 'https://apiv2.shiprocket.in/v1/external');
        Config::set('services.shiprocket.email', 'test@mayshaindia.com');
        Config::set('services.shiprocket.password', 'sr-secret-key');

        SiteSetting::set('shiprocket_enabled', true);
        SiteSetting::set('return_window_days', 7);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@mayshaindia.com',
        ]);

        $this->customer = User::factory()->create([
            'role' => 'customer',
            'email' => 'customer@example.com',
        ]);
    }

    protected function createOrder(User $owner, array $orderAttrs = []): Order
    {
        $category = Category::create([
            'name' => 'Skincare',
            'slug' => 'skincare-'.uniqid(),
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Rose Serum',
            'slug' => 'rose-serum-'.uniqid(),
            'category_id' => $category->id,
            'is_active' => true,
            'base_price_paise' => 99900,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'MAY-RS-50ML-'.uniqid(),
            'size_label' => '50ml',
            'price_paise' => 99900,
            'mrp_paise' => 129900,
            'stock_quantity' => 50,
            'weight_grams' => 300,
            'length_cm' => 12.0,
            'breadth_cm' => 8.0,
            'height_cm' => 6.0,
        ]);

        $order = Order::create(array_merge([
            'reference' => 'MAY-TEST-'.strtoupper(uniqid()),
            'user_id' => $owner->id,
            'status' => 'paid',
            'subtotal_paise' => 99900,
            'discount_paise' => 0,
            'shipping_paise' => 0,
            'tax_paise' => 15238,
            'total_paise' => 99900,
            'currency' => 'INR',
            'payment_method' => 'razorpay',
            'placed_at' => now(),
            'paid_at' => now(),
            'shipping_address' => [
                'name' => 'Rhea Kapoor',
                'phone' => '9876543210',
                'line1' => '10 Park Avenue',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560001',
                'country' => 'India',
            ],
            'guest_email' => null,
            'guest_phone' => null,
        ], $orderAttrs));

        OrderItem::create([
            'order_id' => $order->id,
            'variant_id' => $variant->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'size_label' => $variant->size_label,
            'unit_price_paise' => 99900,
            'unit_mrp_paise' => 129900,
            'quantity' => 1,
            'total_paise' => 99900,
        ]);

        return $order->fresh(['items.variant.product']);
    }

    public function test_non_admin_cannot_access_admin_shipments(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/admin/shipments')
            ->assertStatus(403);
    }

    public function test_admin_can_list_shipments_and_manage_lifecycle(): void
    {
        $order = $this->createOrder($this->customer);

        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/auth/login' => Http::response(['token' => 'mock_token'], 200),
            'https://apiv2.shiprocket.in/v1/external/orders/create/adhoc' => Http::response([
                'order_id' => 11020,
                'shipment_id' => 22030,
                'status' => 'NEW',
            ], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/serviceability*' => Http::response([
                'data' => [
                    'available_courier_companies' => [
                        [
                            'courier_company_id' => 14,
                            'courier_name' => 'Delhivery Surface',
                            'rate' => 75.0,
                            'etd' => '3 days',
                        ],
                    ],
                ],
            ], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/assign/awb' => Http::response([
                'response' => [
                    'data' => [
                        'awb_code' => 'AWB-ADM-778899',
                        'courier_company_id' => 14,
                        'courier_name' => 'Delhivery Surface',
                    ],
                ],
            ], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/generate/pickup' => Http::response([
                'response' => ['pickup_token' => 'PK-ADM-001'],
            ], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/generate/label' => Http::response([
                'label_url' => 'https://shiprocket.co/label/AWB-ADM-778899.pdf',
            ], 200),
            'https://apiv2.shiprocket.in/v1/external/account/details/wallet-balance' => Http::response([
                'data' => ['balance_amount' => '3450.00'],
            ], 200),
        ]);

        // 1. Create Shipment via Admin API
        $createRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/shipment");

        $createRes->assertStatus(200);
        $shipmentId = $createRes->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'shipment.created',
            'actor_id' => $this->admin->id,
        ]);

        // 2. Fetch live couriers
        $courierRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/shipments/{$shipmentId}/couriers");
        $courierRes->assertStatus(200)
            ->assertJsonPath('data.cheapest_courier.courier_name', 'Delhivery Surface');

        // 3. Assign Courier
        $assignRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/shipments/{$shipmentId}/assign", [
                'courier_id' => 14,
            ]);
        $assignRes->assertStatus(200)
            ->assertJsonPath('data.awb_code', 'AWB-ADM-778899');

        // 4. Schedule Pickup
        $pickupRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/shipments/{$shipmentId}/pickup");
        $pickupRes->assertStatus(200)
            ->assertJsonPath('data.pickup_token', 'PK-ADM-001');

        // 5. Generate Label
        $labelRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/shipments/{$shipmentId}/label");
        $labelRes->assertStatus(200)
            ->assertJsonPath('data.label_url', 'https://shiprocket.co/label/AWB-ADM-778899.pdf');

        // 6. Check Wallet Balance
        $walletRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/shiprocket/wallet");
        $walletRes->assertStatus(200)
            ->assertJsonPath('data.data.balance_amount', '3450.00');

        // 7. List Shipments
        $listRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/shipments");
        $listRes->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_customer_can_view_order_tracking_timeline(): void
    {
        $order = $this->createOrder($this->customer, ['status' => 'shipped']);

        $shipment = Shipment::create([
            'order_id' => $order->id,
            'provider' => 'shiprocket',
            'sr_order_id' => 11020,
            'sr_shipment_id' => 22030,
            'awb_code' => 'AWB-CUST-999',
            'courier_name' => 'Blue Dart Air',
            'status' => 'shipped',
            'tracking_url' => 'https://shiprocket.co/tracking/AWB-CUST-999',
        ]);

        $shipment->events()->create([
            'status' => 'shipped',
            'sr_status' => 'IN_TRANSIT',
            'location' => 'Bengaluru Hub',
            'note' => 'Package departed from sorting facility',
            'occurred_at' => now(),
            'source' => 'webhook',
        ]);

        // Customer owner views tracking
        $res = $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/orders/{$order->reference}/tracking");

        $res->assertStatus(200)
            ->assertJsonPath('data.orderReference', $order->reference)
            ->assertJsonPath('data.awb', 'AWB-CUST-999')
            ->assertJsonPath('data.courier', 'Blue Dart Air')
            ->assertJsonCount(1, 'data.events');

        // Another customer cannot view without authorization
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser, 'sanctum')
            ->getJson("/api/v1/orders/{$order->reference}/tracking")
            ->assertStatus(403);
    }

    public function test_customer_can_request_return_within_window(): void
    {
        $order = $this->createOrder($this->customer, [
            'status' => 'delivered',
            'delivered_at' => now()->subDays(2),
        ]);

        $shipment = Shipment::create([
            'order_id' => $order->id,
            'provider' => 'shiprocket',
            'sr_order_id' => 11020,
            'sr_shipment_id' => 22030,
            'awb_code' => 'AWB-CUST-999',
            'status' => 'delivered',
        ]);

        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/auth/login' => Http::response(['token' => 'mock_token'], 200),
            'https://apiv2.shiprocket.in/v1/external/orders/create/return' => Http::response([
                'order_id' => 33445,
                'shipment_id' => 55667,
                'status' => 'RETURN_INITIATED',
            ], 200),
        ]);

        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/account/orders/{$order->reference}/returns", [
                'reason' => 'Product arrived damaged or seal broken',
            ]);

        $res->assertStatus(200);

        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'is_return' => true,
            'parent_shipment_id' => $shipment->id,
        ]);

        $this->assertEquals('return_requested', $order->fresh()->status);
    }

    public function test_customer_cannot_request_return_after_window_expires(): void
    {
        $order = $this->createOrder($this->customer, [
            'status' => 'delivered',
            'delivered_at' => now()->subDays(10), // expired (window is 7 days)
        ]);

        Shipment::create([
            'order_id' => $order->id,
            'provider' => 'shiprocket',
            'sr_order_id' => 11020,
            'sr_shipment_id' => 22030,
            'status' => 'delivered',
        ]);

        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/account/orders/{$order->reference}/returns", [
                'reason' => 'Change of mind',
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('message', 'The return window of 7 days has expired for this order.');
    }
}
