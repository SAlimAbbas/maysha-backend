<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
{
    use ApiResponse;

    /**
     * List all orders for administrative management.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with('items');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $orders = $query->orderBy('id', 'desc')->paginate(20);

        return $this->success(
            OrderResource::collection($orders->items()),
            'Admin orders retrieved successfully.',
            [
                'total' => $orders->total(),
                'currentPage' => $orders->currentPage(),
                'lastPage' => $orders->lastPage(),
            ]
        );
    }

    /**
     * Update order fulfillment status.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending_payment,paid,processing,shipped,delivered,cancelled,refunded',
            'tracking_no' => 'nullable|string|max:100',
            'courier' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
        ]);

        $order = Order::with('items.variant')->findOrFail($id);
        $oldStatus = $order->status;
        $newStatus = $request->input('status');

        DB::transaction(function () use ($order, $oldStatus, $newStatus, $request) {
            $updates = ['status' => $newStatus];
            if ($request->filled('tracking_no')) {
                $updates['tracking_no'] = $request->input('tracking_no');
            }
            if ($request->filled('courier')) {
                $updates['courier'] = $request->input('courier');
            }
            if ($newStatus === 'shipped' && ! $order->shipped_at) {
                $updates['shipped_at'] = now();
            }
            if ($newStatus === 'delivered' && ! $order->delivered_at) {
                $updates['delivered_at'] = now();
            }
            if ($newStatus === 'paid') {
                $updates['paid_at'] = now();
                $updates['reserved_until'] = null;
                if (! $order->invoice_no) {
                    $updates['invoice_no'] = Order::generateInvoiceNo();
                }
            }

            $order->update($updates);

            // Record timeline event
            \App\Models\OrderEvent::create([
                'order_id' => $order->id,
                'status' => $newStatus,
                'note' => $request->input('note') ?: "Order status updated to '{$newStatus}' by administrator.",
                'actor_id' => $request->user()?->id,
            ]);

            // If cancelled or refunded from an active status, restore variant stock
            if (in_array($newStatus, ['cancelled', 'refunded']) && ! in_array($oldStatus, ['cancelled', 'refunded'])) {
                foreach ($order->items as $item) {
                    if ($item->variant) {
                        $item->variant->increment('stock', $item->quantity);
                    }
                }
            }

            AuditLog::record('order.status_updated', $order, $request->user(), [
                'from' => $oldStatus,
                'to' => $newStatus,
            ]);
        });

        return $this->success(
            new OrderResource($order->fresh(['items', 'events'])),
            "Order status updated to '{$newStatus}'."
        );
    }

    /**
     * Process an administrative refund.
     */
    public function refund(Request $request, string $id): JsonResponse
    {
        $order = Order::with('items.variant')->findOrFail($id);

        if ($order->status === 'refunded') {
            return $this->error('Order has already been refunded.', 400);
        }

        DB::transaction(function () use ($order, $request) {
            $order->update(['status' => 'refunded']);

            // Restore stock
            foreach ($order->items as $item) {
                if ($item->variant) {
                    $item->variant->increment('stock', $item->quantity);
                }
            }

            AuditLog::record('order.refunded', $order, $request->user(), [
                'totalPaise' => $order->total_paise,
            ]);
        });

        return $this->success(
            new OrderResource($order->fresh(['items'])),
            'Order successfully refunded and inventory restored.'
        );
    }
}
