<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
{
    use ApiResponse;

    protected RazorpayService $razorpayService;

    public function __construct(RazorpayService $razorpayService)
    {
        $this->razorpayService = $razorpayService;
    }

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
     * Show single order details.
     */
    public function show(string $id): JsonResponse
    {
        $order = Order::with(['items.variant.product.media', 'events', 'user', 'payments'])->findOrFail($id);

        return $this->success(
            new OrderResource($order),
            'Order details retrieved successfully.'
        );
    }

    /**
     * Update order fulfillment or payment status.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending_payment,processing,shipped,delivered,cancelled,refunded',
            'note' => 'nullable|string|max:500',
        ]);

        $order = Order::with('items.variant')->findOrFail($id);
        $oldStatus = $order->status;
        $newStatus = $request->input('status');

        if ($oldStatus === $newStatus) {
            return $this->success(new OrderResource($order), 'Status is already '.$newStatus);
        }

        DB::transaction(function () use ($order, $oldStatus, $newStatus, $request) {
            $updates = ['status' => $newStatus];

            if ($newStatus === 'paid' && ! $order->paid_at) {
                $updates['paid_at'] = now();
                $updates['reserved_until'] = null;
                if (! $order->invoice_no) {
                    $updates['invoice_no'] = Order::generateInvoiceNo();
                }
            }

            $order->update($updates);

            OrderEvent::create([
                'order_id' => $order->id,
                'status' => $newStatus,
                'note' => $request->input('note') ?? "Status updated from '{$oldStatus}' to '{$newStatus}' by administrator.",
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
     * Process an administrative refund (with Razorpay gateway API integration).
     */
    public function refund(Request $request, string $id): JsonResponse
    {
        $order = Order::with(['items.variant', 'payments'])->findOrFail($id);

        if ($order->status === 'refunded') {
            return $this->error('Order has already been refunded.', 400);
        }

        // If paid via Razorpay, trigger gateway refund API
        $capturedPayment = $order->payments()->where('status', 'captured')->whereNotNull('gateway_payment_id')->first();
        $refundData = null;

        if ($capturedPayment) {
            try {
                $refundData = $this->razorpayService->refundPayment(
                    $capturedPayment->gateway_payment_id,
                    $order->total_paise,
                    ['order_reference' => $order->reference, 'refunded_by' => $request->user()?->email ?? 'admin']
                );
            } catch (\Exception $e) {
                return $this->error('Gateway refund failed: '.$e->getMessage(), 500);
            }
        }

        DB::transaction(function () use ($order, $request, $refundData) {
            $order->update(['status' => 'refunded']);

            // Restore stock
            foreach ($order->items as $item) {
                if ($item->variant) {
                    $item->variant->increment('stock', $item->quantity);
                }
            }

            OrderEvent::create([
                'order_id' => $order->id,
                'status' => 'refunded',
                'note' => $refundData
                    ? 'Order refunded via Razorpay (Refund ID: '.($refundData['id'] ?? 'N/A').'). Inventory restored.'
                    : 'Order marked as refunded by administrator. Inventory restored.',
            ]);

            AuditLog::record('order.refunded', $order, $request->user(), [
                'totalPaise' => $order->total_paise,
                'gatewayRefund' => $refundData,
            ]);
        });

        return $this->success(
            new OrderResource($order->fresh(['items', 'events'])),
            'Order successfully refunded and inventory restored.'
        );
    }
}
