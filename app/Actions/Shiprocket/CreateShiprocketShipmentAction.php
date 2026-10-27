<?php

namespace App\Actions\Shiprocket;

use App\Exceptions\Shiprocket\ShiprocketApiException;
use App\Exceptions\Shiprocket\ShiprocketValidationException;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\SiteSetting;
use App\Services\Shiprocket\ShiprocketClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateShiprocketShipmentAction
{
    public function __construct(
        protected ?ShiprocketClient $client = null
    ) {
        $this->client = $client ?? app(ShiprocketClient::class);
    }

    /**
     * Create a Shiprocket shipment for an eligible order inside a row-level transaction.
     */
    public function execute(Order|int $orderOrId): Shipment
    {
        $orderId = $orderOrId instanceof Order ? $orderOrId->id : $orderOrId;

        return DB::transaction(function () use ($orderId) {
            /** @var Order $order */
            $order = Order::with(['items.variant.product', 'shipment'])->where('id', $orderId)->lockForUpdate()->firstOrFail();

            // Precondition 1: Check payment/COD eligibility
            $isCod = strtolower($order->payment_method ?? '') === 'cod';
            $isPaid = ! empty($order->paid_at) || in_array($order->status, ['paid', 'processing'], true);

            if (! $isCod && ! $isPaid) {
                throw new ShiprocketValidationException("Order {$order->reference} is neither paid nor a valid COD order.");
            }

            // Precondition 2: Not cancelled or already terminal
            if (in_array($order->status, ['cancelled', 'delivered', 'refunded'], true)) {
                throw new ShiprocketValidationException("Order {$order->reference} has status {$order->status} and cannot be shipped.");
            }

            // Precondition 3: Existing active shipment with AWB
            $existing = $order->shipment;
            if ($existing && $existing->awb_code && ! in_array($existing->status, ['cancelled', 'exception'], true)) {
                return $existing;
            }

            // Compute package dimensions and weight
            $totalWeightGrams = 0;
            $maxLength = 0.0;
            $maxBreadth = 0.0;
            $sumHeight = 0.0;

            $defaultWeight = (int) (SiteSetting::get('default_weight_grams', 250) ?? 250);
            $defaultLength = (float) (SiteSetting::get('default_length_cm', 10.0) ?? 10.0);
            $defaultBreadth = (float) (SiteSetting::get('default_breadth_cm', 10.0) ?? 10.0);
            $defaultHeight = (float) (SiteSetting::get('default_height_cm', 5.0) ?? 5.0);

            foreach ($order->items as $item) {
                $variant = $item->variant;
                $w = $variant ? $variant->effective_weight_grams : $defaultWeight;
                $l = $variant ? $variant->effective_length_cm : $defaultLength;
                $b = $variant ? $variant->effective_breadth_cm : $defaultBreadth;
                $h = $variant ? $variant->effective_height_cm : $defaultHeight;

                $totalWeightGrams += ($w * $item->quantity);
                $maxLength = max($maxLength, $l);
                $maxBreadth = max($maxBreadth, $b);
                $sumHeight += ($h * $item->quantity);
            }

            $weightKg = max(0.1, round($totalWeightGrams / 1000, 2));
            $length = max(0.5, round($maxLength, 1));
            $breadth = max(0.5, round($maxBreadth, 1));
            $height = max(0.5, round($sumHeight, 1));

            // Address mapping
            $addr = $order->shipping_address ?? [];
            $fullName = trim($addr['name'] ?? 'Valued Customer');
            $nameParts = explode(' ', $fullName, 2);
            $firstName = $nameParts[0] ?: 'Valued';
            $lastName = ! empty($nameParts[1]) ? $nameParts[1] : '.';

            $line1 = trim($addr['line1'] ?? '');
            $line2 = trim($addr['line2'] ?? '');
            $address = trim($line1.($line2 ? ' '.$line2 : ''));
            if (empty($address)) {
                $address = 'Delivery Address';
            }

            $city = trim($addr['city'] ?? 'Bengaluru');
            $state = trim($addr['state'] ?? 'Karnataka');
            $pincode = trim($addr['pincode'] ?? '560001');
            $phone = trim($addr['phone'] ?? ($order->guest_phone ?: '9999999999'));
            $email = trim($order->guest_email ?: ($order->user?->email ?: 'orders@mayshaindia.com'));

            // Items mapping
            $itemCount = max(1, $order->items->count());
            $discountPerItem = ($order->discount_paise > 0) ? round(($order->discount_paise / 100) / $itemCount, 2) : 0.0;

            $orderItems = [];
            foreach ($order->items as $item) {
                $sellingPrice = round($item->unit_price_paise / 100, 2);
                $tax = round(($item->unit_price_paise * 0.18) / 100, 2);
                $hsn = $item->variant?->hsn_code ?: ($item->variant?->product?->hsn_code ?: '330499');

                $orderItems[] = [
                    'name' => $item->product_name ?: 'Maysha Luxury Beauty',
                    'sku' => $item->variant?->sku ?: ($item->product_slug.'-'.($item->size_label ?: 'default')),
                    'units' => $item->quantity,
                    'selling_price' => $sellingPrice,
                    'discount' => $discountPerItem,
                    'tax' => $tax,
                    'hsn' => $hsn,
                ];
            }

            $pickupLocation = SiteSetting::get('shiprocket_pickup_location', 'Primary') ?? 'Primary';
            $paymentMethod = $isCod ? 'COD' : 'Prepaid';
            $subTotal = round($order->total_paise / 100, 2);

            $payload = [
                'order_id' => $order->reference,
                'order_date' => $order->placed_at ? $order->placed_at->format('Y-m-d H:i') : now()->format('Y-m-d H:i'),
                'pickup_location' => $pickupLocation,
                'billing_customer_name' => $firstName,
                'billing_last_name' => $lastName,
                'billing_address' => $address,
                'billing_city' => $city,
                'billing_pincode' => $pincode,
                'billing_state' => $state,
                'billing_country' => 'India',
                'billing_email' => $email,
                'billing_phone' => $phone,
                'shipping_is_billing' => 1,
                'order_items' => $orderItems,
                'payment_method' => $paymentMethod,
                'sub_total' => $subTotal,
                'length' => $length,
                'breadth' => $breadth,
                'height' => $height,
                'weight' => $weightKg,
            ];

            // Send to Shiprocket API
            $srOrderId = null;
            $srShipmentId = null;
            $rawResponse = [];

            try {
                $rawResponse = $this->client->createOrder($payload);
                $srOrderId = $rawResponse['order_id'] ?? null;
                $srShipmentId = $rawResponse['shipment_id'] ?? null;
            } catch (ShiprocketApiException|ShiprocketValidationException $e) {
                // Check if already exists on Shiprocket
                if (str_contains(strtolower($e->getMessage()), 'already exists') || str_contains(strtolower($e->getMessage()), 'duplicate')) {
                    Log::info("Shiprocket order {$order->reference} already exists, reconciling...", ['ref' => $order->reference]);
                    try {
                        $details = $this->client->getOrderDetails($order->reference);
                        $srOrderId = $details['data']['id'] ?? ($details['data']['order_id'] ?? null);
                        $srShipmentId = $details['data']['shipments'][0]['id'] ?? ($details['data']['shipment_id'] ?? null);
                        $rawResponse = $details;
                    } catch (\Throwable $detailEx) {
                        Log::error("Failed to reconcile existing Shiprocket order {$order->reference}", ['error' => $detailEx->getMessage()]);
                        throw $e;
                    }
                } else {
                    throw $e;
                }
            }

            if (! $srShipmentId) {
                throw new ShiprocketApiException("Shiprocket order creation returned no shipment_id for order {$order->reference}.");
            }

            // Create or update Shipment
            $shipment = Shipment::updateOrCreate(
                ['order_id' => $order->id, 'provider' => 'shiprocket'],
                [
                    'sr_order_id' => $srOrderId,
                    'sr_shipment_id' => $srShipmentId,
                    'status' => 'processing',
                    'sr_status' => 'READY TO SHIP',
                    'weight_grams' => $totalWeightGrams,
                    'length_cm' => $length,
                    'breadth_cm' => $breadth,
                    'height_cm' => $height,
                    'raw_payload' => $rawResponse,
                ]
            );

            // Record shipment event
            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'status' => 'processing',
                'sr_status' => 'READY TO SHIP',
                'location' => $pickupLocation,
                'note' => "Shipment created on Shiprocket (Shipment ID: {$srShipmentId})",
                'occurred_at' => now(),
                'source' => 'admin',
                'payload' => $rawResponse,
            ]);

            // Update order status if allowed
            if ($order->canTransitionTo('processing')) {
                $order->update(['status' => 'processing']);
            }

            $order->events()->create([
                'status' => $order->status,
                'note' => "Shiprocket shipment created (Shipment ID: {$srShipmentId}).",
                'actor_id' => auth()->id() ?? $order->user_id,
            ]);

            return $shipment;
        });
    }
}
