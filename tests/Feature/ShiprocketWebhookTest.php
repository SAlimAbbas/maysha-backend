<?php

namespace Tests\Feature;

use App\Mail\Logistics\ShipmentDeliveredMail;
use App\Mail\Logistics\ShipmentNdrMail;
use App\Mail\Logistics\ShipmentOutForDeliveryMail;
use App\Mail\Logistics\ShipmentShippedMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\ShippingWebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ShiprocketWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.shiprocket.base_url', 'https://apiv2.shiprocket.in/v1/external');
        Config::set('services.shiprocket.email', 'test@mayshaindia.com');
        Config::set('services.shiprocket.password', 'sr-secret-key');
        Config::set('services.shiprocket.webhook_token', 'super_secret_webhook_token_789');

        SiteSetting::set('shiprocket_enabled', true);
        SiteSetting::set('low_wallet_threshold', 500);
    }

    protected function createShipmentWithOrder(array $shipmentAttrs = [], array $orderAttrs = []): Shipment
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
        ]);

        $order = Order::create(array_merge([
            'reference' => 'MAY-WH-'.strtoupper(uniqid()),
            'status' => 'processing',
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
                'name' => 'Deepika Rao',
                'phone' => '9123456780',
                'line1' => '22 Brigade Road',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560001',
                'country' => 'India',
            ],
            'guest_email' => 'deepika@example.com',
            'guest_phone' => '9123456780',
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

        return Shipment::create(array_merge([
            'order_id' => $order->id,
            'provider' => 'shiprocket',
            'sr_order_id' => 88110,
            'sr_shipment_id' => 99110,
            'awb_code' => 'AWB-WH-123456',
            'courier_name' => 'Blue Dart',
            'status' => 'processing',
            'weight_grams' => 300,
        ], $shipmentAttrs));
    }

    public function test_webhook_rejects_missing_or_invalid_token(): void
    {
        // 1. Missing token
        $response = $this->postJson('/api/v1/logistics/hook', [
            'awb' => 'AWB-WH-123456',
            'current_status' => 'SHIPPED',
        ]);
        $response->assertStatus(401);

        // 2. Invalid token
        $response = $this->withHeader('x-api-key', 'wrong_token')
            ->postJson('/api/v1/logistics/hook', [
                'awb' => 'AWB-WH-123456',
                'current_status' => 'SHIPPED',
            ]);
        $response->assertStatus(401);
    }

    public function test_webhook_accepts_valid_token_and_logs_payload(): void
    {
        $shipment = $this->createShipmentWithOrder();

        $response = $this->withHeader('x-api-key', 'super_secret_webhook_token_789')
            ->postJson('/api/v1/logistics/hook', [
                'awb' => $shipment->awb_code,
                'current_status' => 'PICKED UP',
                'location' => 'Bengaluru Hub',
                'timestamp' => '2026-10-27 14:00:00',
                'activity' => 'Package picked up from warehouse',
            ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('shipping_webhook_logs', [
            'ip_address' => '127.0.0.1',
            'processed' => true,
        ]);

        $this->assertEquals('shipped', $shipment->fresh()->status);
        $this->assertEquals('shipped', $shipment->order->fresh()->status);
    }

    public function test_webhook_returns_ignored_for_unknown_shipment(): void
    {
        $response = $this->withHeader('x-api-key', 'super_secret_webhook_token_789')
            ->postJson('/api/v1/logistics/hook', [
                'awb' => 'AWB-NON-EXISTENT',
                'current_status' => 'DELIVERED',
            ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ignored']);
    }

    public function test_webhook_idempotency_prevents_duplicate_events(): void
    {
        $shipment = $this->createShipmentWithOrder();

        $payload = [
            'awb' => $shipment->awb_code,
            'current_status' => 'IN_TRANSIT',
            'location' => 'Hyderabad Hub',
            'timestamp' => '2026-10-27 16:00:00',
            'activity' => 'In transit to destination',
        ];

        // Send 1st time
        $this->withHeader('x-api-key', 'super_secret_webhook_token_789')
            ->postJson('/api/v1/logistics/hook', $payload)
            ->assertStatus(200);

        // Send 2nd time (replay)
        $this->withHeader('x-api-key', 'super_secret_webhook_token_789')
            ->postJson('/api/v1/logistics/hook', $payload)
            ->assertStatus(200);

        $eventsCount = ShipmentEvent::where('shipment_id', $shipment->id)
            ->where('sr_status', 'IN_TRANSIT')
            ->count();

        $this->assertEquals(1, $eventsCount);
    }

    public function test_out_of_order_protection_does_not_regress_delivered_shipment(): void
    {
        $shipment = $this->createShipmentWithOrder([
            'status' => 'delivered',
            'last_synced_at' => now(),
        ], [
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        // Stale in-transit event arriving after delivery
        $this->withHeader('x-api-key', 'super_secret_webhook_token_789')
            ->postJson('/api/v1/logistics/hook', [
                'awb' => $shipment->awb_code,
                'current_status' => 'IN_TRANSIT',
                'timestamp' => now()->subHours(5)->format('Y-m-d H:i:s'),
                'activity' => 'Transit update arrived late',
            ])
            ->assertStatus(200);

        $this->assertEquals('delivered', $shipment->fresh()->status);
        $this->assertEquals('delivered', $shipment->order->fresh()->status);
    }

    public function test_webhook_queues_notifications_on_lifecycle_events(): void
    {
        Mail::fake();

        $shipment = $this->createShipmentWithOrder();

        // 1. Shipped
        $this->withHeader('x-api-key', 'super_secret_webhook_token_789')
            ->postJson('/api/v1/logistics/hook', [
                'awb' => $shipment->awb_code,
                'current_status' => 'PICKED UP',
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ])->assertStatus(200);
        Mail::assertQueued(ShipmentShippedMail::class);

        // 2. Out for delivery
        $this->withHeader('x-api-key', 'super_secret_webhook_token_789')
            ->postJson('/api/v1/logistics/hook', [
                'awb' => $shipment->awb_code,
                'current_status' => 'OUT FOR DELIVERY',
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ])->assertStatus(200);
        Mail::assertQueued(ShipmentOutForDeliveryMail::class);

        // 3. Delivered
        $this->withHeader('x-api-key', 'super_secret_webhook_token_789')
            ->postJson('/api/v1/logistics/hook', [
                'awb' => $shipment->awb_code,
                'current_status' => 'DELIVERED',
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ])->assertStatus(200);
        Mail::assertQueued(ShipmentDeliveredMail::class);
    }

    public function test_reconcile_command_polls_stale_shipments(): void
    {
        $staleShipment = $this->createShipmentWithOrder([
            'status' => 'shipped',
            'last_synced_at' => now()->subHours(3),
        ]);

        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/auth/login' => Http::response(['token' => 'mock_token'], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/track/awb/'.$staleShipment->awb_code => Http::response([
                'tracking_data' => [
                    'track_status' => 1,
                    'shipment_track' => [
                        [
                            'current_status' => 'DELIVERED',
                            'awb_code' => $staleShipment->awb_code,
                        ],
                    ],
                    'shipment_track_activities' => [
                        [
                            'date' => now()->format('Y-m-d H:i:s'),
                            'status' => 'DELIVERED',
                            'activity' => 'Package delivered to recipient',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('shipments:reconcile')
            ->expectsOutputToContain('Successfully reconciled 1 shipments.')
            ->assertExitCode(0);

        $this->assertEquals('delivered', $staleShipment->fresh()->status);
    }

    public function test_check_wallet_command_alerts_on_low_balance(): void
    {
        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/auth/login' => Http::response(['token' => 'mock_token'], 200),
            'https://apiv2.shiprocket.in/v1/external/account/details/wallet-balance' => Http::response([
                'data' => ['balance_amount' => '150.00'],
            ], 200),
        ]);

        $this->artisan('shiprocket:check-wallet')
            ->expectsOutputToContain('Shiprocket wallet balance is LOW: ₹150.00')
            ->assertExitCode(0);
    }
}
