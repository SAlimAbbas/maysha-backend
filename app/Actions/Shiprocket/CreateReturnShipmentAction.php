<?php

namespace App\Actions\Shiprocket;

use App\Exceptions\Shiprocket\ShiprocketApiException;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\SiteSetting;
use App\Services\Shiprocket\ShiprocketClient;
use Illuminate\Support\Facades\DB;

class CreateReturnShipmentAction
{
    public function __construct(
        protected ?ShiprocketClient $client = null
    ) {
        $this->client = $client ?? app(ShiprocketClient::class);
    }

    /**
     * Create reverse pickup / return shipment for an order.
     */
    public function execute(Order|int $orderOrId, ?string $reason = 'Customer Return Request'): Shipment
    {
        $orderId = $orderOrId instanceof Order ? $orderOrId->id : $orderOrId;

        return DB::transaction(function () use ($orderId, $reason) {
            /** @var Order $order */
            $order = Order::with(['items.variant', 'shipment'])->where('id', $orderId)->lockForUpdate()->firstOrFail();

            $parentShipment = $order->shipment;
            if (! $parentShipment) {
                throw new ShiprocketApiException("Order {$order->reference} has no outbound shipment to return.");
            }

            $addr = $order->shipping_address ?? [];
            $fullName = trim($addr['name'] ?? 'Customer');
            $parts = explode(' ', $fullName, 2);

            $payload = [
                'order_id' => $order->reference.'-RET',
                'order_date' => now()->format('Y-m-d H:i'),
                'channel_id' => '',
                'pickup_customer_name' => $parts[0] ?: 'Customer',
                'pickup_last_name' => $parts[1] ?? '.',
                'pickup_address' => $addr['line1'] ?? 'Customer Address',
                'pickup_address_2' => $addr['line2'] ?? '',
                'pickup_city' => $addr['city'] ?? 'Bengaluru',
                'pickup_state' => $addr['state'] ?? 'Karnataka',
                'pickup_country' => 'India',
                'pickup_pincode' => $addr['pincode'] ?? '560001',
                'pickup_email' => $order->guest_email ?: ($order->user?->email ?: 'orders@mayshaindia.com'),
                'pickup_phone' => $addr['phone'] ?? ($order->guest_phone ?: '9999999999'),
                'shipping_customer_name' => 'Maysha Logistics Hub',
                'shipping_address' => SiteSetting::get('pickup_location_address', 'Primary Warehouse'),
                'shipping_city' => 'Bengaluru',
                'shipping_state' => 'Karnataka',
                'shipping_country' => 'India',
                'shipping_pincode' => SiteSetting::get('pickup_pincode', '560001') ?? '560001',
                'shipping_phone' => '9999999999',
                'order_items' => [
                    [
                        'name' => 'Returned items for order '.$order->reference,
                        'sku' => $order->reference.'-RET',
                        'units' => 1,
                        'selling_price' => round($order->total_paise / 100, 2),
                        'discount' => 0,
                        'tax' => 0,
                        'hsn' => '330499',
                    ],
                ],
                'payment_method' => 'Prepaid',
                'sub_total' => round($order->total_paise / 100, 2),
                'length' => (float) ($parentShipment->length_cm ?: 10.0),
                'breadth' => (float) ($parentShipment->breadth_cm ?: 10.0),
                'height' => (float) ($parentShipment->height_cm ?: 5.0),
                'weight' => max(0.1, round(($parentShipment->weight_grams ?: 500) / 1000, 2)),
            ];

            $response = $this->client->createReturnOrder($payload);

            $srOrderId = $response['order_id'] ?? null;
            $srShipmentId = $response['shipment_id'] ?? null;

            $returnShipment = Shipment::create([
                'order_id' => $order->id,
                'provider' => 'shiprocket',
                'sr_order_id' => $srOrderId,
                'sr_shipment_id' => $srShipmentId,
                'status' => 'processing',
                'sr_status' => 'RETURN_INITIATED',
                'weight_grams' => $parentShipment->weight_grams,
                'length_cm' => $parentShipment->length_cm,
                'breadth_cm' => $parentShipment->breadth_cm,
                'height_cm' => $parentShipment->height_cm,
                'is_return' => true,
                'parent_shipment_id' => $parentShipment->id,
                'raw_payload' => $response,
            ]);

            ShipmentEvent::create([
                'shipment_id' => $returnShipment->id,
                'status' => 'processing',
                'sr_status' => 'RETURN_INITIATED',
                'location' => 'Customer Location',
                'note' => "Reverse pickup initiated: {$reason}",
                'occurred_at' => now(),
                'source' => 'admin',
                'payload' => $response,
            ]);

            if ($order->canTransitionTo('return_requested')) {
                $order->update(['status' => 'return_requested']);
            }

            $order->events()->create([
                'status' => $order->status,
                'note' => "Return shipment created (#{$returnShipment->id}). Reason: {$reason}",
                'actor_id' => auth()->id() ?? $order->user_id,
            ]);

            return $returnShipment;
        });
    }
}
