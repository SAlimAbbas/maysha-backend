<?php

namespace App\Actions\Shiprocket;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Services\Shiprocket\ShiprocketClient;
use App\Services\Shiprocket\ShiprocketStatusMapper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncShipmentTrackingAction
{
    public function __construct(
        protected ?ShiprocketClient $client = null
    ) {
        $this->client = $client ?? app(ShiprocketClient::class);
    }

    /**
     * Poll live tracking data from Shiprocket, upsert events, and update shipment + order status.
     */
    public function execute(Shipment|int $shipmentOrId): Shipment
    {
        $shipmentId = $shipmentOrId instanceof Shipment ? $shipmentOrId->id : $shipmentOrId;

        return DB::transaction(function () use ($shipmentId) {
            /** @var Shipment $shipment */
            $shipment = Shipment::with('order')->where('id', $shipmentId)->lockForUpdate()->firstOrFail();

            if (empty($shipment->awb_code)) {
                return $shipment;
            }

            try {
                $response = $this->client->trackByAwb($shipment->awb_code);
            } catch (\Throwable $e) {
                Log::warning("Failed to sync tracking for shipment {$shipment->id} (AWB: {$shipment->awb_code})", [
                    'error' => $e->getMessage(),
                ]);
                return $shipment;
            }

            $trackingData = $response['tracking_data'] ?? [];
            $trackList = $trackingData['shipment_track'] ?? [];
            $primaryTrack = $trackList[0] ?? [];

            $rawStatus = $primaryTrack['current_status'] ?? ($trackingData['shipment_status'] ?? $shipment->sr_status);
            $mappedShipmentStatus = ShiprocketStatusMapper::map($rawStatus);
            $mappedOrderStatus = ShiprocketStatusMapper::toOrderStatus($mappedShipmentStatus);

            // Upsert historical activities idempotently
            $activities = $trackingData['shipment_track_activities'] ?? [];
            foreach ($activities as $act) {
                $srStatus = $act['status'] ?? ($act['activity'] ?? 'STATUS_UPDATE');
                $dateStr = $act['date'] ?? null;
                $occurredAt = $dateStr ? Carbon::parse($dateStr) : now();

                ShipmentEvent::updateOrCreate(
                    [
                        'shipment_id' => $shipment->id,
                        'sr_status' => $srStatus,
                        'occurred_at' => $occurredAt,
                    ],
                    [
                        'status' => ShiprocketStatusMapper::map($srStatus),
                        'location' => $act['location'] ?? null,
                        'note' => $act['activity'] ?? ($act['status'] ?? null),
                        'source' => 'poll',
                        'payload' => $act,
                    ]
                );
            }

            // Update shipment
            $shipment->update([
                'status' => $mappedShipmentStatus,
                'sr_status' => (string) $rawStatus,
                'last_synced_at' => now(),
            ]);

            // Update order
            $order = $shipment->order;
            if ($order) {
                $orderUpdates = [];
                if ($mappedOrderStatus === 'shipped' && empty($order->shipped_at)) {
                    $orderUpdates['shipped_at'] = now();
                }
                if ($mappedOrderStatus === 'delivered' && empty($order->delivered_at)) {
                    $orderUpdates['delivered_at'] = now();
                }

                if ($order->canTransitionTo($mappedOrderStatus)) {
                    $orderUpdates['status'] = $mappedOrderStatus;
                }

                if (! empty($orderUpdates)) {
                    $order->update($orderUpdates);
                    $order->events()->create([
                        'status' => $order->status,
                        'note' => "Tracking status updated to {$mappedOrderStatus} (Shiprocket: {$rawStatus}).",
                    ]);
                }
            }

            return $shipment;
        });
    }
}
