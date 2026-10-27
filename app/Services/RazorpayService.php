<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RazorpayService
{
    protected ?string $keyId;

    protected ?string $keySecret;

    protected ?string $webhookSecret;

    public function __construct()
    {
        $this->keyId = config('services.razorpay.key');
        $this->keySecret = config('services.razorpay.secret');
        $this->webhookSecret = config('services.razorpay.webhook_secret');
    }

    /**
     * Create Razorpay order on gateway server-side.
     */
    public function createRazorpayOrder(Order $order): array
    {
        $amountPaise = $order->total_paise;
        $receipt = $order->reference;

        $razorpayOrderId = null;

        // If live/test credentials exist, communicate with Razorpay API
        if ($this->keyId && $this->keySecret) {
            $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => $amountPaise,
                    'currency' => 'INR',
                    'receipt' => $receipt,
                    'notes' => [
                        'order_id' => $order->id,
                        'reference' => $order->reference,
                    ],
                ]);

            if ($response->successful()) {
                $razorpayOrderId = $response->json('id');
            } else {
                $errDesc = $response->json('error.description') ?: $response->json('error.message') ?: 'Payment gateway communication failure';
                \Illuminate\Support\Facades\Log::error('Razorpay order creation failed', [
                    'status' => $response->status(),
                    'error' => $response->json('error'),
                    'order' => $order->reference,
                ]);
                throw new \RuntimeException("Razorpay payment gateway error: {$errDesc}");
            }
        }

        // Mock gateway order ID strictly for testing when no keys are provided
        if (! $razorpayOrderId) {
            if (! app()->environment('testing')) {
                throw new \RuntimeException('Razorpay payment gateway credentials (RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET) are not configured.');
            }
            $razorpayOrderId = 'order_'.Str::random(14);
        }

        // Create Payment record
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => $razorpayOrderId,
            'status' => 'created',
            'amount_paise' => $amountPaise,
            'currency' => 'INR',
        ]);

        return [
            'paymentId' => (string) $payment->id,
            'keyId' => $this->keyId ?: 'rzp_test_maysha_dummy_key',
            'razorpayOrderId' => $razorpayOrderId,
            'amount' => $order->total_rupees,
            'amountPaise' => $amountPaise,
            'currency' => 'INR',
            'orderReference' => $order->reference,
            'customerName' => $order->shipping_address['name'] ?? '',
            'customerEmail' => $order->guest_email ?: ($order->user?->email ?? ''),
            'customerPhone' => $order->shipping_address['phone'] ?? '',
        ];
    }

    /**
     * Verify payment signature using HMAC SHA256.
     */
    public function verifySignature(string $razorpayOrderId, string $razorpayPaymentId, string $signature): bool
    {
        $secret = $this->keySecret;
        if (empty($secret)) {
            if (app()->environment('testing')) {
                $secret = 'test_secret_key_123';
            } else {
                throw new \RuntimeException('Razorpay secret key is not configured.');
            }
        }

        $expectedSignature = hash_hmac('sha256', $razorpayOrderId.'|'.$razorpayPaymentId, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = $this->webhookSecret;
        if (empty($secret)) {
            if (app()->environment('testing')) {
                $secret = 'test_webhook_secret_123';
            } else {
                throw new \RuntimeException('Razorpay webhook secret is not configured.');
            }
        }

        $expectedSignature = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process refund via Razorpay API.
     */
    public function refundPayment(string $paymentId, ?int $amountPaise = null, array $notes = []): array
    {
        if (! $this->keyId || ! $this->keySecret) {
            if (app()->environment('testing') || app()->environment('local')) {
                return [
                    'id' => 'rfnd_test_'.Str::random(14),
                    'payment_id' => $paymentId,
                    'amount' => $amountPaise ?? 0,
                    'status' => 'processed',
                ];
            }
            throw new \RuntimeException('Razorpay credentials not configured for refund processing.');
        }

        $payload = [
            'notes' => $notes,
        ];
        if ($amountPaise) {
            $payload['amount'] = $amountPaise;
        }

        $response = Http::withBasicAuth($this->keyId, $this->keySecret)
            ->post("https://api.razorpay.com/v1/payments/{$paymentId}/refund", $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Razorpay refund failed: '.($response->json('error.description') ?? $response->body()));
        }

        return $response->json();
    }

    /**
     * Fetch payment details from Razorpay API.
     */
    public function fetchPayment(string $paymentId): ?array
    {
        if ($this->keyId && $this->keySecret) {
            $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->get("https://api.razorpay.com/v1/payments/{$paymentId}");

            if ($response->successful()) {
                return $response->json();
            }
        }

        return null;
    }
}
