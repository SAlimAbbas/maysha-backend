<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Traits\ApiResponse;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    protected CreateOrderAction $createOrderAction;

    public function __construct(CreateOrderAction $createOrderAction)
    {
        $this->createOrderAction = $createOrderAction;
    }

    /**
     * Compute checkout quote with verified shipping and taxes.
     */
    public function quote(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'coupon_code' => 'nullable|string',
            'pincode' => 'nullable|string|max:10',
        ]);

        $subtotalPaise = 0;
        foreach ($request->input('items') as $item) {
            $variant = ProductVariant::find($item['variant_id']);
            if ($variant) {
                $subtotalPaise += $variant->price_paise * (int) $item['quantity'];
            }
        }

        $discountPaise = 0;
        $couponCode = $request->input('coupon_code');
        if ($couponCode) {
            $coupon = Coupon::where('code', strtoupper(trim($couponCode)))->first();
            if ($coupon && $coupon->isValidFor($subtotalPaise, $request->user())) {
                $discountPaise = $coupon->calculateDiscountPaise($subtotalPaise);
            }
        }

        $thresholdPaise = (int) env('FREE_SHIPPING_THRESHOLD', 999) * 100;
        $shippingFeePaise = (int) env('SHIPPING_FEE', 99) * 100;
        $shippingPaise = ($subtotalPaise >= $thresholdPaise || $subtotalPaise === 0) ? 0 : $shippingFeePaise;
        $totalPaise = max(0, $subtotalPaise - $discountPaise + $shippingPaise);

        return $this->success([
            'subtotal' => (int) round($subtotalPaise / 100),
            'discount' => (int) round($discountPaise / 100),
            'shipping' => (int) round($shippingPaise / 100),
            'total' => (int) round($totalPaise / 100),
            'currency' => 'INR',
            'freeShippingQualified' => $subtotalPaise >= $thresholdPaise,
        ], 'Checkout quote computed.');
    }

    /**
     * Place an order with transactional stock locking and idempotency protection.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1|max:10',
            'shipping_address' => 'required|array',
            'shipping_address.name' => 'required|string|max:255',
            'shipping_address.phone' => 'required|string|max:20',
            'shipping_address.line1' => 'required|string|max:255',
            'shipping_address.city' => 'required|string|max:100',
            'shipping_address.state' => 'required|string|max:100',
            'shipping_address.pincode' => 'required|string|max:10',
            'payment_method' => 'required|in:razorpay,cod',
            'coupon_code' => 'nullable|string|max:50',
            'guest_email' => 'nullable|email|max:255',
            'cart_token' => 'nullable|string',
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');
        $user = $request->user();

        // Guest email is required if not logged in
        if (! $user && ! $request->filled('guest_email')) {
            return $this->error('A valid guest email address is required for checkout.', 422, [
                'guest_email' => ['Guest email is required when not logged in.'],
            ]);
        }

        $cartToClear = null;
        if ($request->filled('cart_token')) {
            $cartToClear = Cart::where('token', $request->input('cart_token'))->first();
        }

        $order = $this->createOrderAction->execute(
            itemsInput: $request->input('items'),
            shippingAddress: $request->input('shipping_address'),
            paymentMethod: $request->input('payment_method'),
            couponCode: $request->input('coupon_code'),
            idempotencyKey: $idempotencyKey,
            user: $user,
            guestEmail: $request->input('guest_email'),
            guestPhone: $request->input('shipping_address.phone'),
            cartToClear: $cartToClear
        );

        $statusCode = $order->wasRecentlyCreated ? 201 : 200;
        $message = $order->wasRecentlyCreated ? 'Order created successfully.' : 'Order retrieved successfully (idempotent duplicate request).';

        return $this->success(
            new OrderResource($order),
            $message,
            [],
            $statusCode
        );
    }

    /**
     * View an order by reference (Strict IDOR authorization).
     */
    public function show(Request $request, string $reference): JsonResponse
    {
        $order = Order::where('reference', $reference)->with('items')->first();

        if (! $order) {
            return $this->error('Order not found.', 404);
        }

        $user = $request->user();

        // IDOR Protection: Check owner or admin
        $isAuthorized = false;

        if ($user) {
            if ($order->user_id === $user->id || $user->isAdmin()) {
                $isAuthorized = true;
            }
        } else {
            // For guest lookup, require matching guest_email in request
            $queryEmail = strtolower((string) $request->input('email'));
            if ($queryEmail && strtolower((string) $order->guest_email) === $queryEmail) {
                $isAuthorized = true;
            }
        }

        if (! $isAuthorized) {
            return $this->error('Access denied. You do not have permission to view this order.', 403);
        }

        return $this->success(
            new OrderResource($order),
            'Order details retrieved successfully.'
        );
    }
}
