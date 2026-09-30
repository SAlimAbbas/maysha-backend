<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Order;
use App\Models\Payment;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    protected RazorpayService $razorpayService;

    public function __construct(RazorpayService $razorpayService)
    {
        $this->razorpayService = $razorpayService;
    }

    /**
     * Create Razorpay order for an existing pending order.
     */
    public function createRazorpayOrder(Request $request): JsonResponse
    {
        $request->validate([
            'order_reference' => 'required|exists:orders,reference',
        ]);

        $order = Order::where('reference', $request->input('order_reference'))->firstOrFail();

        if ($order->status === 'paid') {
            return $this->error('This order is already marked as paid.', 400);
        }

        $result = $this->razorpayService->createRazorpayOrder($order);

        return $this->success($result, 'Razorpay order created successfully.');
    }

    /**
     * Verify client payment signature.
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $orderId = $request->input('razorpay_order_id');
        $paymentId = $request->input('razorpay_payment_id');
        $signature = $request->input('razorpay_signature');

        $isValid = $this->razorpayService->verifySignature($orderId, $paymentId, $signature);

        $payment = Payment::where('gateway_order_id', $orderId)->first();

        if (! $isValid) {
            if ($payment) {
                $payment->update(['status' => 'failed']);
            }

            return $this->error('Invalid payment signature verification failed.', 400);
        }

        if ($payment) {
            $payment->update([
                'gateway_payment_id' => $paymentId,
                'gateway_signature' => $signature,
                'status' => 'captured',
                'verified_at' => now(),
            ]);

            $payment->order->update(['status' => 'paid']);
        }

        return $this->success(null, 'Payment successfully verified and order marked as paid.');
    }

    /**
     * Handle incoming Razorpay Webhooks.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $signature = $request->header('X-Razorpay-Signature');
        $rawPayload = $request->getContent();

        if (! $signature || ! $this->razorpayService->verifyWebhookSignature($rawPayload, $signature)) {
            return response()->json(['error' => 'Invalid webhook signature.'], 400);
        }

        $event = $request->input('event');
        $payload = $request->input('payload');

        if ($event === 'payment.captured' && isset($payload['payment']['entity'])) {
            $entity = $payload['payment']['entity'];
            $gatewayOrderId = $entity['order_id'] ?? null;
            $gatewayPaymentId = $entity['id'] ?? null;

            if ($gatewayOrderId) {
                $payment = Payment::where('gateway_order_id', $gatewayOrderId)->first();
                if ($payment && $payment->status !== 'captured') {
                    $payment->update([
                        'gateway_payment_id' => $gatewayPaymentId,
                        'status' => 'captured',
                        'raw_response' => $entity,
                        'verified_at' => now(),
                    ]);
                    $payment->order->update(['status' => 'paid']);
                }
            }
        } elseif ($event === 'payment.failed' && isset($payload['payment']['entity'])) {
            $entity = $payload['payment']['entity'];
            $gatewayOrderId = $entity['order_id'] ?? null;

            if ($gatewayOrderId) {
                $payment = Payment::where('gateway_order_id', $gatewayOrderId)->first();
                if ($payment && $payment->status !== 'captured') {
                    $payment->update([
                        'status' => 'failed',
                        'raw_response' => $entity,
                    ]);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
