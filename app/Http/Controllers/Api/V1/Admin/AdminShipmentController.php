<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Shiprocket\AssignCourierAction;
use App\Actions\Shiprocket\CancelShipmentAction;
use App\Actions\Shiprocket\CheckServiceabilityAction;
use App\Actions\Shiprocket\CreateShiprocketShipmentAction;
use App\Actions\Shiprocket\GenerateInvoiceAction;
use App\Actions\Shiprocket\GenerateLabelAction;
use App\Actions\Shiprocket\GenerateManifestAction;
use App\Actions\Shiprocket\SchedulePickupAction;
use App\Actions\Shiprocket\SyncShipmentTrackingAction;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Services\Shiprocket\ShiprocketClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminShipmentController extends Controller
{
    use ApiResponse;

    /**
     * List all shipments with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Shipment::with(['order', 'events']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('provider')) {
            $query->where('provider', $request->input('provider'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('awb_code', 'like', "%{$search}%")
                    ->orWhere('sr_shipment_id', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($oq) use ($search) {
                        $oq->where('reference', 'like', "%{$search}%");
                    });
            });
        }

        $shipments = $query->orderByDesc('id')->paginate(20);

        return $this->success(
            $shipments->items(),
            'Shipments retrieved successfully.',
            [
                'total' => $shipments->total(),
                'currentPage' => $shipments->currentPage(),
                'lastPage' => $shipments->lastPage(),
            ]
        );
    }

    /**
     * Get shipment for a specific order.
     */
    public function showByOrder(string $orderId): JsonResponse
    {
        $order = Order::with(['shipment.events'])->findOrFail($orderId);

        return $this->success($order->shipment, 'Shipment retrieved successfully.');
    }

    /**
     * Manually create a shipment for an order.
     */
    public function createForOrder(Request $request, string $orderId, CreateShiprocketShipmentAction $action): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        $shipment = $action->execute($order);

        AuditLog::record('shipment.created', $shipment, $request->user(), [
            'order_id' => $order->id,
            'sr_shipment_id' => $shipment->sr_shipment_id,
        ]);

        return $this->success($shipment->fresh(['events', 'order']), 'Shipment created successfully.');
    }

    /**
     * Get live courier serviceability rates for a shipment.
     */
    public function getCouriers(string $id, CheckServiceabilityAction $action): JsonResponse
    {
        $shipment = Shipment::with('order')->findOrFail($id);
        $order = $shipment->order;

        if (! $order) {
            return $this->error('Order not found for shipment.', 404);
        }

        $pincode = $order->shipping_address['pincode'] ?? '560001';
        $weightKg = max(0.1, round(($shipment->weight_grams ?: 500) / 1000, 2));
        $isCod = strtolower($order->payment_method ?? '') === 'cod';

        $rates = $action->execute($pincode, $weightKg, $isCod);

        return $this->success($rates, 'Available couriers retrieved successfully.');
    }

    /**
     * Assign courier / generate AWB code.
     */
    public function assign(Request $request, string $id, AssignCourierAction $action): JsonResponse
    {
        $request->validate([
            'courier_id' => 'nullable|integer',
        ]);

        $shipment = Shipment::findOrFail($id);
        $courierId = $request->input('courier_id');

        $updated = $action->execute($shipment, $courierId);

        AuditLog::record('shipment.assigned', $updated, $request->user(), [
            'awb_code' => $updated->awb_code,
            'courier_id' => $updated->courier_id,
            'courier_name' => $updated->courier_name,
        ]);

        return $this->success($updated->fresh(['events', 'order']), 'Courier assigned and AWB generated.');
    }

    /**
     * Schedule courier pickup.
     */
    public function pickup(Request $request, string $id, SchedulePickupAction $action): JsonResponse
    {
        $shipment = Shipment::findOrFail($id);
        $updated = $action->execute($shipment);

        AuditLog::record('shipment.pickup_scheduled', $updated, $request->user(), [
            'pickup_token' => $updated->pickup_token,
            'pickup_scheduled_at' => $updated->pickup_scheduled_at,
        ]);

        return $this->success($updated->fresh(['events', 'order']), 'Pickup scheduled successfully.');
    }

    /**
     * Generate / fetch shipping label.
     */
    public function label(string $id, GenerateLabelAction $action): JsonResponse
    {
        $shipment = Shipment::findOrFail($id);
        $url = $action->execute($shipment);

        return $this->success(['label_url' => $url], 'Label generated successfully.');
    }

    /**
     * Generate / fetch pickup manifest.
     */
    public function manifest(string $id, GenerateManifestAction $action): JsonResponse
    {
        $shipment = Shipment::findOrFail($id);
        $url = $action->execute($shipment);

        return $this->success(['manifest_url' => $url], 'Manifest generated successfully.');
    }

    /**
     * Generate / fetch invoice.
     */
    public function invoice(string $id, GenerateInvoiceAction $action): JsonResponse
    {
        $shipment = Shipment::findOrFail($id);
        $url = $action->execute($shipment);

        return $this->success(['invoice_url' => $url], 'Invoice generated successfully.');
    }

    /**
     * Cancel shipment on Shiprocket.
     */
    public function cancel(Request $request, string $id, CancelShipmentAction $action): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $shipment = Shipment::findOrFail($id);
        $reason = $request->input('reason', 'Cancelled by administrator');

        $updated = $action->execute($shipment, $reason);

        AuditLog::record('shipment.cancelled', $updated, $request->user(), [
            'reason' => $reason,
        ]);

        return $this->success($updated->fresh(['events', 'order']), 'Shipment cancelled successfully.');
    }

    /**
     * Manually sync tracking data from Shiprocket.
     */
    public function sync(string $id, SyncShipmentTrackingAction $action): JsonResponse
    {
        $shipment = Shipment::findOrFail($id);
        $updated = $action->execute($shipment);

        return $this->success($updated->fresh(['events', 'order']), 'Tracking synced successfully.');
    }

    /**
     * Get live wallet balance.
     */
    public function wallet(ShiprocketClient $client): JsonResponse
    {
        $data = $client->getWalletBalance();

        return $this->success($data, 'Shiprocket wallet balance retrieved.');
    }

    /**
     * Re-attempt delivery on NDR shipment.
     */
    public function reattemptNdr(Request $request, string $id, ShiprocketClient $client): JsonResponse
    {
        $request->validate([
            'comments' => 'required|string|max:500',
            'deferred_date' => 'nullable|date_format:Y-m-d',
            'phone' => 'nullable|string|max:20',
            'address1' => 'nullable|string|max:255',
        ]);

        $shipment = Shipment::findOrFail($id);
        if (empty($shipment->awb_code)) {
            return $this->error('Shipment has no AWB code.', 422);
        }

        $res = $client->reattemptNdr($shipment->awb_code, $request->only(['comments', 'deferred_date', 'phone', 'address1']));

        ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'status' => 'ndr',
            'sr_status' => 'NDR_REATTEMPT_REQUESTED',
            'note' => 'NDR re-attempt requested: '.$request->input('comments'),
            'occurred_at' => now(),
            'source' => 'admin',
            'payload' => $res,
        ]);

        AuditLog::record('shipment.ndr_reattempt', $shipment, $request->user(), [
            'comments' => $request->input('comments'),
        ]);

        return $this->success($res, 'NDR re-attempt requested successfully.');
    }
}
