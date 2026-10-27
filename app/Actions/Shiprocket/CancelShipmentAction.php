<?php

namespace App\Actions\Shiprocket;

use App\Exceptions\Shiprocket\ShiprocketApiException;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Services\Shiprocket\ShiprocketClient;
use Illuminate\Support\Facades\DB;

class CancelShipmentAction
{
    public function __construct(
        protected ?ShiprocketClient $client = null
    ) {
        $this->client = $client ?? app(ShiprocketClient::class);
    }

    /**
     * Cancel shipment on Shiprocket before pickup.
     */
    public function execute(Shipment|int $shipmentOrId, string $reason = 'Cancelled by administrator'): Shipment
    {
        $shipmentId = $shipmentOrId instanceof Shipment ? $shipmentOrId->id : $shipmentOrId;

        return DB::transaction(function () use ($shipmentId, $reason) {
            /** @var Shipment $shipment */
            $shipment = Shipment::with('order')->where('id', $shipmentId)->lockForUpdate()->firstOrFail();

            if (in_array($shipment->status, ['shipped', 'delivered', 'rto', 'rto_delivered'], true)) {
                throw new ShiprocketApiException("Shipment {$shipment->id} cannot be cancelled because it has already reached status {$shipment->status}.");
            }

            if ($shipment->status === 'cancelled') {
                return $shipment;
            }

            if ($shipment->sr_order_id) {
                $this->client->cancelOrder([$shipment->sr_order_id]);
            }

            $shipment->update([
                'status' => 'cancelled',
                'sr_status' => 'CANCELED',
                'cancelled_at' => now(),
            ]);

            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'status' => 'cancelled',
                'sr_status' => 'CANCELED',
                'location' => 'Shiprocket Dispatch Hub',
                'note' => "Shipment cancelled: {$reason}",
                'occurred_at' => now(),
                'source' => 'admin',
            ]);

            if ($shipment->order) {
                $shipment->order->events()->create([
                    'status' => $shipment->order->status,
                    'note' => "Shiprocket shipment #{$shipment->id} cancelled. Reason: {$reason}",
                    'actor_id' => auth()->id() ?? $shipment->order->user_id,
                ]);
            }

            return $shipment;
        });
    }
}
