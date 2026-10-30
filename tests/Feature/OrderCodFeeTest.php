<?php

namespace Tests\Feature;

use App\Actions\CreateOrderAction;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCodFeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CatalogSeeder::class);
        SiteSetting::set('cod_enabled', true);
        SiteSetting::set('cod_fee', 60);
        SiteSetting::set('cod_max_rupees', 5000);
        SiteSetting::set('free_shipping_threshold', 1000);
        SiteSetting::set('shipping_fee', 50);
    }

    public function test_store_info_returns_cod_fee(): void
    {
        $response = $this->getJson('/api/v1/store-info');
        $response->assertStatus(200)
            ->assertJsonPath('data.codFee', 60)
            ->assertJsonPath('data.codEnabled', true);
    }

    public function test_quote_endpoint_returns_cod_fee(): void
    {
        $variant = ProductVariant::first();

        $response = $this->postJson('/api/v1/checkout/quote', [
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.codFee', 60)
            ->assertJsonPath('data.codAvailable', true);
    }

    public function test_creating_cod_order_adds_cod_fee_to_total(): void
    {
        $variant = ProductVariant::first();
        $variant->update(['price_paise' => 40000, 'stock' => 10]);

        $action = app(CreateOrderAction::class);
        $order = $action->execute(
            itemsInput: [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
            shippingAddress: [
                'name' => 'Test Customer',
                'phone' => '9876543210',
                'line1' => '123 Marine Drive',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400001',
            ],
            paymentMethod: 'cod',
            guestEmail: 'testcustomer@example.com'
        );

        // Subtotal: 400 INR (40000 paise)
        // Shipping: 50 INR (5000 paise) since subtotal < 1000
        // COD Fee: 60 INR (6000 paise)
        // Expected Total: 510 INR (51000 paise)
        $this->assertEquals(40000, $order->subtotal_paise);
        $this->assertEquals(5000, $order->shipping_paise);
        $this->assertEquals(6000, $order->cod_fee_paise);
        $this->assertEquals(60, $order->cod_fee_rupees);
        $this->assertEquals(51000, $order->total_paise);
        $this->assertEquals(510, $order->total_rupees);

        // Verify OrderResource format
        $resource = (new \App\Http\Resources\OrderResource($order))->toArray(request());
        $this->assertEquals(60, $resource['codFee']);
        $this->assertEquals(510, $resource['total']);

        // Verify Email view renders with COD handling fee
        $view = view('emails.order_confirmation', ['order' => $order])->render();
        $this->assertStringContainsString('Cash on Delivery Handling', $view);
        $this->assertStringContainsString('+₹60', $view);
        $this->assertStringContainsString('₹510', $view);
    }
}
