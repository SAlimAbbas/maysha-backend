<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\ShippingWebhookLog;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShiprocketDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_shiprocket_tables_and_columns_exist(): void
    {
        // 1. Product variants columns
        $this->assertTrue(Schema::hasColumn('product_variants', 'weight_grams'));
        $this->assertTrue(Schema::hasColumn('product_variants', 'length_cm'));
        $this->assertTrue(Schema::hasColumn('product_variants', 'breadth_cm'));
        $this->assertTrue(Schema::hasColumn('product_variants', 'height_cm'));

        // 2. Shipments table
        $this->assertTrue(Schema::hasTable('shipments'));
        $this->assertTrue(Schema::hasColumn('shipments', 'order_id'));
        $this->assertTrue(Schema::hasColumn('shipments', 'provider'));
        $this->assertTrue(Schema::hasColumn('shipments', 'sr_order_id'));
        $this->assertTrue(Schema::hasColumn('shipments', 'sr_shipment_id'));
        $this->assertTrue(Schema::hasColumn('shipments', 'awb_code'));
        $this->assertTrue(Schema::hasColumn('shipments', 'courier_name'));
        $this->assertTrue(Schema::hasColumn('shipments', 'status'));

        // 3. Shipment events table
        $this->assertTrue(Schema::hasTable('shipment_events'));
        $this->assertTrue(Schema::hasColumn('shipment_events', 'shipment_id'));
        $this->assertTrue(Schema::hasColumn('shipment_events', 'status'));
        $this->assertTrue(Schema::hasColumn('shipment_events', 'sr_status'));

        // 4. Shipping webhook logs table
        $this->assertTrue(Schema::hasTable('shipping_webhook_logs'));
    }

    public function test_shipment_and_shipment_events_relationships(): void
    {
        $order = Order::create([
            'reference' => 'MAY-SR-TEST01',
            'status' => 'processing',
            'subtotal_paise' => 100000,
            'total_paise' => 100000,
            'currency' => 'INR',
            'shipping_address' => ['name' => 'Logistics Buyer', 'pincode' => '560001'],
        ]);

        $shipment = Shipment::create([
            'order_id' => $order->id,
            'provider' => 'shiprocket',
            'sr_order_id' => 991122,
            'sr_shipment_id' => 881122,
            'awb_code' => 'AWB123456789',
            'courier_name' => 'Blue Dart Express',
            'status' => 'in_transit',
            'weight_grams' => 350,
        ]);

        $event = ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'status' => 'in_transit',
            'sr_status' => 'IN TRANSIT',
            'location' => 'Bengaluru Hub',
            'occurred_at' => now(),
        ]);

        $this->assertCount(1, $order->fresh()->shipments);
        $this->assertEquals($shipment->id, $order->fresh()->shipment->id);
        $this->assertEquals($order->id, $shipment->order->id);
        $this->assertCount(1, $shipment->fresh()->events);
        $this->assertEquals($shipment->id, $event->shipment->id);
    }

    public function test_product_variant_dimension_fallbacks(): void
    {
        $category = \App\Models\Category::create(['name' => 'Oils', 'slug' => 'oils']);
        $product = Product::create(['name' => 'Facial Oil', 'slug' => 'facial-oil', 'category_id' => $category->id]);

        SiteSetting::set('default_weight_grams', 300);
        SiteSetting::set('default_length_cm', 12);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'MAY-FO-30',
            'size_label' => '30ml',
            'price_paise' => 120000,
            'mrp_paise' => 140000,
            'stock' => 10,
        ]);

        // Falls back to site_settings defaults when null
        $this->assertEquals(300, $variant->effective_weight_grams);
        $this->assertEquals(12.0, $variant->effective_length_cm);

        // Uses explicit attributes when set
        $variant->update([
            'weight_grams' => 450,
            'length_cm' => 15.5,
        ]);

        $this->assertEquals(450, $variant->fresh()->effective_weight_grams);
        $this->assertEquals(15.5, $variant->fresh()->effective_length_cm);
    }

    public function test_admin_settings_allows_shiprocket_keys(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $res = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/settings', [
                'settings' => [
                    'shiprocket_enabled' => true,
                    'shiprocket_pickup_location' => 'Bangalore Warehouse',
                    'shiprocket_courier_strategy' => 'fastest',
                    'default_weight_grams' => 280,
                ],
            ]);

        $res->assertStatus(200);

        $this->assertEquals('Bangalore Warehouse', SiteSetting::get('shiprocket_pickup_location'));
        $this->assertEquals('fastest', SiteSetting::get('shiprocket_courier_strategy'));
        $this->assertEquals(280, (int) SiteSetting::get('default_weight_grams'));
    }

    public function test_shiprocket_doctor_runs_cleanly(): void
    {
        $this->artisan('shiprocket:doctor')
            ->assertExitCode(0);
    }
}
