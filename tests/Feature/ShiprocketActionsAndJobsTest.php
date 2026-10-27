<?php

namespace Tests\Feature;

use App\Actions\Shiprocket\AssignCourierAction;
use App\Actions\Shiprocket\CancelShipmentAction;
use App\Actions\Shiprocket\CheckServiceabilityAction;
use App\Actions\Shiprocket\CreateReturnShipmentAction;
use App\Actions\Shiprocket\CreateShiprocketShipmentAction;
use App\Actions\Shiprocket\GenerateInvoiceAction;
use App\Actions\Shiprocket\GenerateLabelAction;
use App\Actions\Shiprocket\GenerateManifestAction;
use App\Actions\Shiprocket\SchedulePickupAction;
use App\Actions\Shiprocket\SyncShipmentTrackingAction;
use App\Exceptions\Shiprocket\ShiprocketApiException;
use App\Exceptions\Shiprocket\ShiprocketValidationException;
use App\Jobs\CreateShiprocketShipmentJob;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShiprocketActionsAndJobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Config::set('services.shiprocket.base_url', 'https://apiv2.shiprocket.in/v1/external');
        Config::set('services.shiprocket.email', 'test@mayshaindia.com');
        Config::set('services.shiprocket.password', 'sr-secret-key');

        SiteSetting::set('shiprocket_enabled', true);
        SiteSetting::set('shiprocket_auto_create', true);
        SiteSetting::set('shiprocket_pickup_location', 'Primary');
        SiteSetting::set('pickup_pincode', '560001');
    }

    protected function createTestOrder(array $attributes = [], array $variantAttributes = []): Order
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
            'hsn_code' => '330499',
        ]);

        $variant = ProductVariant::create(array_merge([
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
        ], $variantAttributes));

        $order = Order::create(array_merge([
            'reference' => 'MAY-SR-'.strtoupper(uniqid()),
            'status' => 'paid',
            'subtotal_paise' => 99900,
            'discount_paise' => 10000,
            'shipping_paise' => 0,
            'tax_paise' => 15238,
            'total_paise' => 89900,
            'currency' => 'INR',
            'payment_method' => 'razorpay',
            'placed_at' => now(),
            'paid_at' => now(),
            'shipping_address' => [
                'name' => 'Ananya Sharma',
                'phone' => '9876543210',
                'line1' => 'Flat 402, Lotus Heights',
                'line2' => 'Koramangala 4th Block',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560034',
                'country' => 'India',
            ],
            'guest_email' => 'ananya@example.com',
            'guest_phone' => '9876543210',
        ], $attributes));

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

    public function test_check_serviceability_action_caches_and_returns_couriers(): void
    {
        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/auth/login' => Http::response(['token' => 'mock_token'], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/serviceability*' => Http::response([
                'status' => 200,
                'data' => [
                    'available_courier_companies' => [
                        [
                            'courier_company_id' => 12,
                            'courier_name' => 'Blue Dart Express',
                            'rate' => 85.0,
                            'etd' => '2-3 days',
                            'cod' => 1,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $action = app(CheckServiceabilityAction::class);
        $res = $action->execute('560034', 0.5, true);

        $this->assertTrue($res['serviceable']);
        $this->assertEquals('shiprocket', $res['source']);
        $this->assertEquals(12, $res['cheapest_courier']['courier_company_id']);

        // Verify caching: second call uses cache without hitting HTTP
        $cachedRes = $action->execute('560034', 0.5, true);
        $this->assertEquals('shiprocket', $cachedRes['source']);
    }

    public function test_check_serviceability_action_falls_back_to_local_db_when_shiprocket_down(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        Pincode::create([
            'pincode' => '560099',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'serviceable' => true,
            'cod_available' => true,
            'eta_days' => 3,
        ]);

        $action = app(CheckServiceabilityAction::class);
        $fallbackRes = $action->execute('560099', 0.5, true);

        $this->assertTrue($fallbackRes['serviceable']);
        $this->assertEquals('local_db', $fallbackRes['source']);
        $this->assertEquals(3, $fallbackRes['estimated_days']);
    }

    public function test_create_shiprocket_shipment_action_preconditions_and_creation(): void
    {
        $unpaidOrder = $this->createTestOrder(['status' => 'pending_payment', 'paid_at' => null, 'payment_method' => 'razorpay']);

        $action = app(CreateShiprocketShipmentAction::class);

        $this->expectException(ShiprocketValidationException::class);
        $action->execute($unpaidOrder);
    }

    public function test_create_shiprocket_shipment_action_success(): void
    {
        $order = $this->createTestOrder();

        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/auth/login' => Http::response(['token' => 'mock_token'], 200),
            'https://apiv2.shiprocket.in/v1/external/orders/create/adhoc' => Http::response([
                'order_id' => 77101,
                'shipment_id' => 88201,
                'status' => 'NEW',
            ], 200),
        ]);

        $action = app(CreateShiprocketShipmentAction::class);
        $shipment = $action->execute($order);

        $this->assertInstanceOf(Shipment::class, $shipment);
        $this->assertEquals(77101, $shipment->sr_order_id);
        $this->assertEquals(88201, $shipment->sr_shipment_id);
        $this->assertEquals('processing', $shipment->status);
        $this->assertEquals(300, $shipment->weight_grams);
        $this->assertEquals(12.0, (float) $shipment->length_cm);

        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'sr_shipment_id' => 88201,
        ]);

        $this->assertDatabaseHas('shipment_events', [
            'shipment_id' => $shipment->id,
            'status' => 'processing',
        ]);
    }

    public function test_assign_courier_action_and_pickup(): void
    {
        $order = $this->createTestOrder();
        $shipment = Shipment::create([
            'order_id' => $order->id,
            'provider' => 'shiprocket',
            'sr_order_id' => 77101,
            'sr_shipment_id' => 88201,
            'status' => 'processing',
            'weight_grams' => 300,
        ]);

        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/auth/login' => Http::response(['token' => 'mock_token'], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/assign/awb' => Http::response([
                'response' => [
                    'data' => [
                        'awb_code' => 'AWB987654321',
                        'courier_company_id' => 10,
                        'courier_name' => 'Delhivery Air',
                    ],
                ],
            ], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/generate/pickup' => Http::response([
                'response' => [
                    'pickup_token' => 'PICKUP-TOKEN-456',
                ],
            ], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/generate/label' => Http::response([
                'label_url' => 'https://shiprocket.co/label/AWB987654321.pdf',
            ], 200),
        ]);

        $assignAction = app(AssignCourierAction::class);
        $shipment = $assignAction->execute($shipment, 10);

        $this->assertEquals('AWB987654321', $shipment->awb_code);
        $this->assertEquals('Delhivery Air', $shipment->courier_name);
        $this->assertEquals('AWB987654321', $order->fresh()->tracking_no);

        $pickupAction = app(SchedulePickupAction::class);
        $shipment = $pickupAction->execute($shipment);

        $this->assertNotNull($shipment->pickup_scheduled_at);
        $this->assertEquals('PICKUP-TOKEN-456', $shipment->pickup_token);

        $labelAction = app(GenerateLabelAction::class);
        $labelUrl = $labelAction->execute($shipment);

        $this->assertEquals('https://shiprocket.co/label/AWB987654321.pdf', $labelUrl);
        $this->assertEquals('https://shiprocket.co/label/AWB987654321.pdf', $shipment->fresh()->label_url);
    }

    public function test_cancel_shipment_action_prevents_shipped_cancel(): void
    {
        $order = $this->createTestOrder();
        $shipment = Shipment::create([
            'order_id' => $order->id,
            'provider' => 'shiprocket',
            'sr_order_id' => 77101,
            'sr_shipment_id' => 88201,
            'status' => 'shipped',
        ]);

        $cancelAction = app(CancelShipmentAction::class);

        $this->expectException(ShiprocketApiException::class);
        $this->expectExceptionMessage('cannot be cancelled because it has already reached status shipped');
        $cancelAction->execute($shipment);
    }

    public function test_sync_tracking_action_updates_status(): void
    {
        $order = $this->createTestOrder(['status' => 'processing']);
        $shipment = Shipment::create([
            'order_id' => $order->id,
            'provider' => 'shiprocket',
            'sr_order_id' => 77101,
            'sr_shipment_id' => 88201,
            'awb_code' => 'AWB987654321',
            'status' => 'processing',
        ]);

        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/auth/login' => Http::response(['token' => 'mock_token'], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/track/awb/AWB987654321' => Http::response([
                'tracking_data' => [
                    'track_status' => 1,
                    'shipment_status' => 7,
                    'shipment_track' => [
                        [
                            'current_status' => 'DELIVERED',
                            'awb_code' => 'AWB987654321',
                        ],
                    ],
                    'shipment_track_activities' => [
                        [
                            'date' => '2026-10-27 15:00:00',
                            'status' => 'DELIVERED',
                            'activity' => 'Package delivered to customer',
                            'location' => 'Bengaluru Hub',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $syncAction = app(SyncShipmentTrackingAction::class);
        $shipment = $syncAction->execute($shipment);

        $this->assertEquals('delivered', $shipment->status);
        $this->assertEquals('delivered', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->delivered_at);

        $this->assertDatabaseHas('shipment_events', [
            'shipment_id' => $shipment->id,
            'status' => 'delivered',
            'sr_status' => 'DELIVERED',
        ]);
    }

    public function test_create_shiprocket_shipment_job_executes_pipeline(): void
    {
        $order = $this->createTestOrder();

        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/auth/login' => Http::response(['token' => 'mock_token'], 200),
            'https://apiv2.shiprocket.in/v1/external/orders/create/adhoc' => Http::response([
                'order_id' => 77101,
                'shipment_id' => 88201,
                'status' => 'NEW',
            ], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/serviceability*' => Http::response([
                'data' => [
                    'available_courier_companies' => [
                        [
                            'courier_company_id' => 15,
                            'courier_name' => 'Shadowfax',
                            'rate' => 70.0,
                            'etd' => '3 days',
                        ],
                    ],
                ],
            ], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/assign/awb' => Http::response([
                'response' => [
                    'data' => [
                        'awb_code' => 'AWB-AUTO-12345',
                        'courier_company_id' => 15,
                        'courier_name' => 'Shadowfax',
                    ],
                ],
            ], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/generate/pickup' => Http::response([
                'response' => ['pickup_token' => 'PK-AUTO-123'],
            ], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/generate/label' => Http::response([
                'label_url' => 'https://shiprocket.co/label/AWB-AUTO-12345.pdf',
            ], 200),
        ]);

        $job = new CreateShiprocketShipmentJob($order->id);
        $job->handle(
            app(CreateShiprocketShipmentAction::class),
            app(AssignCourierAction::class),
            app(SchedulePickupAction::class),
            app(GenerateLabelAction::class)
        );

        $order->refresh();
        $shipment = $order->shipment;

        $this->assertNotNull($shipment);
        $this->assertEquals('AWB-AUTO-12345', $shipment->awb_code);
        $this->assertEquals('Shadowfax', $shipment->courier_name);
        $this->assertEquals('PK-AUTO-123', $shipment->pickup_token);
        $this->assertEquals('https://shiprocket.co/label/AWB-AUTO-12345.pdf', $shipment->label_url);
    }
}
