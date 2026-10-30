<?php

namespace App\Actions\Shiprocket;

use App\Exceptions\Shiprocket\ShiprocketApiException;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\SiteSetting;
use App\Services\Shiprocket\ShiprocketClient;
use Illuminate\Support\Facades\DB;

class AssignCourierAction
{
    public function __construct(
        protected ?ShiprocketClient $client = null,
        protected ?CheckServiceabilityAction $serviceabilityAction = null
    ) {
        $this->client = $client ?? app(ShiprocketClient::class);
        $this->serviceabilityAction = $serviceabilityAction ?? app(CheckServiceabilityAction::class);
    }

    /**
     * Assign courier, generate AWB code, update shipment and order.
     */
    public function execute(Shipment|int $shipmentOrId, ?int $courierId = null): Shipment
    {
        $shipmentId = $shipmentOrId instanceof Shipment ? $shipmentOrId->id : $shipmentOrId;

        return DB::transaction(function () use ($shipmentId, $courierId) {
            /** @var Shipment $shipment */
            $shipment = Shipment::with('order')->where('id', $shipmentId)->lockForUpdate()->firstOrFail();
            $order = $shipment->order;

            if (empty($shipment->sr_shipment_id)) {
                throw new ShiprocketApiException("Shipment {$shipment->id} has no valid sr_shipment_id.");
            }

            // If no courierId provided, resolve by strategy
            if (! $courierId) {
                $courierId = $this->resolveCourierByStrategy($shipment, $order);
            }

            // Call Shiprocket API with automatic fallback
            try {
                $response = $this->client->assignCourier($shipment->sr_shipment_id, $courierId);
            } catch (\App\Exceptions\Shiprocket\ShiprocketValidationException $e) {
                // If a specific courier was rejected as unserviceable, fall back to Shiprocket's own auto-allocation engine
                if ($courierId) {
                    \Illuminate\Support\Facades\Log::warning("Courier {$courierId} rejected for shipment {$shipment->id}. Retrying with auto-allocation.", [
                        'error' => $e->getMessage(),
                    ]);
                    $response = $this->client->assignCourier($shipment->sr_shipment_id, null);
                } else {
                    throw $e;
                }
            }

            $awbCode = $response['response']['data']['awb_code'] ?? ($response['awb_code'] ?? null);
            $courierName = $response['response']['data']['courier_name'] ?? ($response['courier_name'] ?? 'Shiprocket Courier');
            $resolvedCourierId = $response['response']['data']['courier_company_id'] ?? ($response['courier_id'] ?? $courierId);

            if (empty($awbCode)) {
                throw new ShiprocketApiException("Shiprocket failed to assign AWB for shipment {$shipment->id}: ".json_encode($response));
            }

            $trackingUrl = "https://shiprocket.co/tracking/{$awbCode}";

            // Update Shipment
            $shipment->update([
                'awb_code' => $awbCode,
                'courier_name' => $courierName,
                'courier_id' => $resolvedCourierId,
                'tracking_url' => $trackingUrl,
                'status' => 'processing',
                'sr_status' => 'AWB ASSIGNED',
            ]);

            // Record Shipment Event
            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'status' => 'processing',
                'sr_status' => 'AWB ASSIGNED',
                'location' => 'Shiprocket Dispatch Hub',
                'note' => "Courier assigned: {$courierName} (AWB: {$awbCode})",
                'occurred_at' => now(),
                'source' => 'admin',
                'payload' => $response,
            ]);

            // Update Order tracking and courier
            if ($order) {
                $orderUpdates = [
                    'tracking_no' => $awbCode,
                    'courier' => $courierName,
                ];
                if ($order->canTransitionTo('processing')) {
                    $orderUpdates['status'] = 'processing';
                }
                $order->update($orderUpdates);

                $order->events()->create([
                    'status' => $order->status,
                    'note' => "Courier {$courierName} assigned with AWB: {$awbCode}.",
                    'actor_id' => auth()->id() ?? $order->user_id,
                ]);
            }

            return $shipment;
        });
    }

    protected function resolveCourierByStrategy(Shipment $shipment, Order $order): ?int
    {
        $strategy = SiteSetting::get('shiprocket_courier_strategy', 'cheapest') ?? 'cheapest';
        $pincode = $order->shipping_address['pincode'] ?? '560001';
        $weightKg = max(0.1, round(($shipment->weight_grams ?: 500) / 1000, 2));
        $isCod = strtolower($order->payment_method ?? '') === 'cod';

        $check = $this->serviceabilityAction->execute($pincode, $weightKg, $isCod);
        $couriers = $check['couriers'] ?? [];

        if (empty($couriers)) {
            return null;
        }

        if ($strategy === 'fastest' && ! empty($check['fastest_courier']['courier_company_id'])) {
            return (int) $check['fastest_courier']['courier_company_id'];
        }

        if ($strategy === 'preferred') {
            $preferredIds = SiteSetting::get('shiprocket_preferred_courier_ids', []);
            if (is_string($preferredIds)) {
                $preferredIds = array_filter(array_map('trim', explode(',', $preferredIds)));
            }
            if (is_array($preferredIds) && ! empty($preferredIds)) {
                foreach ($couriers as $c) {
                    if (in_array((string) $c['courier_company_id'], array_map('strval', $preferredIds), true)) {
                        return (int) $c['courier_company_id'];
                    }
                }
            }
        }

        // Default to cheapest
        if (! empty($check['cheapest_courier']['courier_company_id'])) {
            return (int) $check['cheapest_courier']['courier_company_id'];
        }

        return (int) ($couriers[0]['courier_company_id'] ?? null);
    }
}
