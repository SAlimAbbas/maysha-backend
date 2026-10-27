<?php

namespace App\Actions\Shiprocket;

use App\Exceptions\Shiprocket\ShiprocketApiException;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Services\Shiprocket\ShiprocketClient;
use Illuminate\Support\Facades\DB;

class SchedulePickupAction
{
    public function __construct(
        protected ?ShiprocketClient $client = null
    ) {
        $this->client = $client ?? app(ShiprocketClient::class);
    }

    /**
     * Schedule courier pickup on Shiprocket for the given shipment.
     */
    public function execute(Shipment|int $shipmentOrId): Shipment
    {
        $shipmentId = $shipmentOrId instanceof Shipment ? $shipmentOrId->id : $shipmentOrId;

        return DB::transaction(function () use ($shipmentId) {
            /** @var Shipment $shipment */
            $shipment = Shipment::where('id', $shipmentId)->lockForUpdate()->firstOrFail();

            if (empty($shipment->sr_shipment_id)) {
                throw new ShiprocketApiException("Shipment {$shipment->id} has no valid sr_shipment_id.");
            }

            $response = $this->client->schedulePickup([$shipment->sr_shipment_id]);

            $pickupToken = $response['response']['pickup_token'] ?? ($response['pickup_token'] ?? null);

            $shipment->update([
                'pickup_scheduled_at' => now(),
                'pickup_token' => $pickupToken,
                'status' => 'processing',
                'sr_status' => 'PICKUP SCHEDULED',
            ]);

            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'status' => 'processing',
                'sr_status' => 'PICKUP SCHEDULED',
                'location' => 'Pickup Address',
                'note' => 'Courier pickup scheduled with Shiprocket'.($pickupToken ? " (Token: {$pickupToken})" : ''),
                'occurred_at' => now(),
                'source' => 'admin',
                'payload' => $response,
            ]);

            return $shipment;
        });
    }
}
