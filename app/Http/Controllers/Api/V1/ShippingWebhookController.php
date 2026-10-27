<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\Logistics\ShipmentDeliveredMail;
use App\Mail\Logistics\ShipmentNdrMail;
use App\Mail\Logistics\ShipmentOutForDeliveryMail;
use App\Mail\Logistics\ShipmentShippedMail;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\ShippingWebhookLog;
use App\Services\Shiprocket\ShiprocketStatusMapper;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ShippingWebhookController extends Controller
{
    /**
     * Handle incoming neutral logistics webhook from Shiprocket.
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Constant-time token verification
        $receivedToken = $request->header('x-api-key')
            ?: ($request->header('X-Api-Key')
            ?: ($request->bearerToken()
            ?: $request->header('X-Shiprocket-Token')));

        $expectedToken = (string) config('services.shiprocket.webhook_token');

        if (empty($expectedToken) || empty($receivedToken) || ! hash_equals($expectedToken, (string) $receivedToken)) {
            Log::warning('Rejected unauthorized Shiprocket webhook request');
            return response()->json(['error' => 'Unauthorized webhook token.'], 401);
        }

        $rawBody = $request->getContent();
        $payload = $request->all();

        // 2. Log webhook payload for audit and debugging
        $webhookLog = null;
        try {
            $webhookLog = ShippingWebhookLog::create([
                'provider' => 'shiprocket',
                'event_type' => $payload['current_status'] ?? ($payload['status'] ?? 'STATUS_UPDATE'),
                'body_hash' => hash('sha256', $rawBody),
                'headers' => $request->headers->all(),
                'payload' => $payload,
                'ip_address' => $request->ip(),
                'processed' => false,
            ]);
        } catch (\Throwable $logEx) {
            Log::warning('Failed to write ShippingWebhookLog: '.$logEx->getMessage());
        }

        // 3. Extract tracking identifiers
        $awb = $payload['awb'] ?? ($payload['awb_code'] ?? null);
        $srShipmentId = $payload['shipment_id'] ?? ($payload['sr_shipment_id'] ?? null);
        $srOrderId = $payload['order_id'] ?? ($payload['sr_order_id'] ?? null);

        $shipment = null;
        if (! empty($awb)) {
            $shipment = Shipment::where('awb_code', $awb)->first();
        }
        if (! $shipment && ! empty($srShipmentId)) {
            $shipment = Shipment::where('sr_shipment_id', $srShipmentId)->first();
        }
        if (! $shipment && ! empty($srOrderId)) {
            $shipment = Shipment::where('sr_order_id', $srOrderId)->first();
            if (! $shipment) {
                $order = Order::where('reference', $srOrderId)->first();
                if ($order) {
                    $shipment = $order->shipment;
                }
            }
        }

        if (! $shipment) {
            Log::info('Shiprocket webhook received for unknown shipment', [
                'awb' => $awb,
                'shipment_id' => $srShipmentId,
                'order_id' => $srOrderId,
            ]);
            return response()->json(['status' => 'ignored', 'message' => 'Shipment not found'], 200);
        }

        // 4. Extract event details
        $rawStatus = $payload['current_status'] ?? ($payload['status'] ?? ($payload['shipment_status'] ?? 'STATUS_UPDATE'));
        $dateStr = $payload['timestamp'] ?? ($payload['date'] ?? null);
        $occurredAt = $dateStr ? Carbon::parse($dateStr) : now();

        $mappedShipmentStatus = ShiprocketStatusMapper::map($rawStatus);
        $mappedOrderStatus = ShiprocketStatusMapper::toOrderStatus($mappedShipmentStatus);

        // 5. Transactional row lock & idempotent upsert
        DB::transaction(function () use (
            $shipment,
            $rawStatus,
            $occurredAt,
            $payload,
            $mappedShipmentStatus,
            $mappedOrderStatus
        ) {
            /** @var Shipment $lockedShipment */
            $lockedShipment = Shipment::where('id', $shipment->id)->lockForUpdate()->firstOrFail();
            /** @var Order|null $order */
            $order = $lockedShipment->order_id ? Order::where('id', $lockedShipment->order_id)->lockForUpdate()->first() : null;

            // Idempotently upsert shipment event
            ShipmentEvent::updateOrCreate(
                [
                    'shipment_id' => $lockedShipment->id,
                    'sr_status' => (string) $rawStatus,
                    'occurred_at' => $occurredAt,
                ],
                [
                    'status' => $mappedShipmentStatus,
                    'location' => $payload['location'] ?? ($payload['current_location'] ?? null),
                    'note' => $payload['activity'] ?? ($payload['notes'] ?? $rawStatus),
                    'source' => 'webhook',
                    'payload' => $payload,
                ]
            );

            // Out-of-order check: do not regress terminal shipments
            $isCurrentlyTerminal = ShiprocketStatusMapper::isTerminal($lockedShipment->status);
            $isIncomingTerminal = ShiprocketStatusMapper::isTerminal($mappedShipmentStatus);

            $shouldUpdateStatus = true;
            if ($isCurrentlyTerminal && ! $isIncomingTerminal) {
                $shouldUpdateStatus = false;
            } elseif ($lockedShipment->last_synced_at && $occurredAt->lt($lockedShipment->last_synced_at)) {
                $shouldUpdateStatus = false;
            }

            if ($shouldUpdateStatus) {
                $lockedShipment->update([
                    'status' => $mappedShipmentStatus,
                    'sr_status' => (string) $rawStatus,
                    'last_synced_at' => now(),
                ]);

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
                            'note' => "Logistics webhook update: {$mappedOrderStatus} (Shiprocket: {$rawStatus}).",
                        ]);
                    }
                }
            }
        });

        $webhookLog?->update(['processed' => true]);

        // 6. Queue customer notification email in background
        $freshShipment = $shipment->fresh(['order']);
        $order = $freshShipment?->order;
        if ($order) {
            $recipient = $order->guest_email ?: ($order->user?->email ?? null);
            if ($recipient) {
                try {
                    if ($mappedShipmentStatus === 'shipped') {
                        Mail::to($recipient)->queue(new ShipmentShippedMail($order, $freshShipment));
                    } elseif ($mappedShipmentStatus === 'out_for_delivery') {
                        Mail::to($recipient)->queue(new ShipmentOutForDeliveryMail($order, $freshShipment));
                    } elseif ($mappedShipmentStatus === 'delivered') {
                        Mail::to($recipient)->queue(new ShipmentDeliveredMail($order, $freshShipment));
                    } elseif ($mappedShipmentStatus === 'ndr') {
                        Mail::to($recipient)->queue(new ShipmentNdrMail($order, $freshShipment, $payload['activity'] ?? null));
                    }
                } catch (\Throwable $mailEx) {
                    Log::warning('Failed to queue shipping notification email: '.$mailEx->getMessage());
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
