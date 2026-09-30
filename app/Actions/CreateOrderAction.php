<?php

namespace App\Actions;

use App\Mail\OrderConfirmationMail;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CreateOrderAction
{
    /**
     * Execute transactional order creation with row locking and idempotency protection.
     */
    public function execute(
        array $itemsInput,
        array $shippingAddress,
        string $paymentMethod = 'razorpay',
        ?string $couponCode = null,
        ?string $idempotencyKey = null,
        ?User $user = null,
        ?string $guestEmail = null,
        ?string $guestPhone = null,
        ?Cart $cartToClear = null
    ): Order {
        // 1. Idempotency Check
        if ($idempotencyKey) {
            $existingOrder = Order::where('idempotency_key', $idempotencyKey)->first();
            if ($existingOrder) {
                return $existingOrder;
            }
        }

        if (empty($itemsInput)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Cannot create order: no items provided.',
            ], 422));
        }

        // 2. Transactional execution with row-level locking
        $order = DB::transaction(function () use (
            $itemsInput,
            $shippingAddress,
            $paymentMethod,
            $couponCode,
            $idempotencyKey,
            $user,
            $guestEmail,
            $guestPhone,
            $cartToClear
        ) {
            $subtotalPaise = 0;
            $itemsToCreate = [];

            foreach ($itemsInput as $input) {
                $variantId = $input['variant_id'] ?? $input['variantId'] ?? null;
                $quantity = max(1, (int) ($input['quantity'] ?? 1));

                // Lock the variant record for update to prevent overselling
                $variant = ProductVariant::where('id', $variantId)
                    ->with('product.media')
                    ->lockForUpdate()
                    ->first();

                if (! $variant || ! $variant->is_active || ! $variant->product || ! $variant->product->is_active) {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'A selected product variant is currently unavailable.',
                    ], 422));
                }

                if ($variant->stock < $quantity) {
                    abort(response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for {$variant->product->name} ({$variant->size_label}). Available stock: {$variant->stock}.",
                    ], 422));
                }

                // Decrement stock immediately inside transaction
                $variant->decrement('stock', $quantity);

                $lineTotalPaise = $variant->price_paise * $quantity;
                $subtotalPaise += $lineTotalPaise;

                $image = 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80';
                if ($variant->product->media && $variant->product->media->isNotEmpty()) {
                    $image = $variant->product->media->first()->getDeliveryUrl('card');
                }

                $itemsToCreate[] = [
                    'variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'product_slug' => $variant->product->slug,
                    'size_label' => $variant->size_label,
                    'unit_price_paise' => $variant->price_paise,
                    'unit_mrp_paise' => $variant->mrp_paise,
                    'quantity' => $quantity,
                    'total_paise' => $lineTotalPaise,
                    'product_image' => $image,
                ];
            }

            // Coupon calculation & redemption
            $discountPaise = 0;
            $validCoupon = null;
            if ($couponCode) {
                $validCoupon = Coupon::where('code', strtoupper(trim($couponCode)))->first();
                if ($validCoupon && $validCoupon->isValidFor($subtotalPaise, $user)) {
                    $discountPaise = $validCoupon->calculateDiscountPaise($subtotalPaise);
                } else {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'The applied coupon code is invalid, expired, or does not meet minimum subtotal requirements.',
                    ], 422));
                }
            }

            // Shipping fee calculation
            $thresholdPaise = (int) env('FREE_SHIPPING_THRESHOLD', 999) * 100;
            $shippingFeePaise = (int) env('SHIPPING_FEE', 99) * 100;
            $shippingPaise = ($subtotalPaise >= $thresholdPaise) ? 0 : $shippingFeePaise;

            $totalPaise = max(0, $subtotalPaise - $discountPaise + $shippingPaise);

            // Create Order
            $order = Order::create([
                'reference' => Order::generateReference(),
                'idempotency_key' => $idempotencyKey,
                'user_id' => $user?->id,
                'guest_email' => $guestEmail ?: $user?->email,
                'guest_phone' => $guestPhone ?: ($shippingAddress['phone'] ?? null),
                'status' => $paymentMethod === 'cod' ? 'processing' : 'pending_payment',
                'subtotal_paise' => $subtotalPaise,
                'discount_paise' => $discountPaise,
                'shipping_paise' => $shippingPaise,
                'total_paise' => $totalPaise,
                'currency' => 'INR',
                'coupon_code' => $validCoupon?->code,
                'shipping_address' => $shippingAddress,
                'payment_method' => $paymentMethod,
            ]);

            // Create Order Items snapshot
            foreach ($itemsToCreate as $itemData) {
                $order->items()->create($itemData);
            }

            // Record Coupon Redemption
            if ($validCoupon) {
                CouponRedemption::create([
                    'coupon_id' => $validCoupon->id,
                    'user_id' => $user?->id,
                    'order_id' => $order->id,
                ]);
            }

            // Clear Cart if provided
            if ($cartToClear) {
                $cartToClear->items()->delete();
                $cartToClear->update(['coupon_id' => null]);
            }

            return $order;
        });

        // 3. Queue Order Confirmation Email
        $recipient = $order->guest_email ?: $user?->email;
        if ($recipient) {
            try {
                Mail::to($recipient)->queue(new OrderConfirmationMail($order));
            } catch (\Exception) {
                // Background email queuing handled safely
            }
        }

        return $order->load('items');
    }
}
