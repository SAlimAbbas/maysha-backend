<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Traits\ApiResponse;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Pincode;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
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
    /**
     * Compute checkout quote with verified shipping, taxes, and COD availability.
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

         $user = $request->user('sanctum') ?? $request->user();

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
             if ($coupon && $coupon->isValidFor($subtotalPaise, $user)) {
                 $discountPaise = $coupon->calculateDiscountPaise($subtotalPaise);
             }
         }

         $thresholdPaise = (int) SiteSetting::get('free_shipping_threshold', env('FREE_SHIPPING_THRESHOLD', 999)) * 100;
         $shippingFeePaise = (int) SiteSetting::get('shipping_fee', env('SHIPPING_FEE', 99)) * 100;
         $shippingPaise = ($subtotalPaise >= $thresholdPaise || $subtotalPaise === 0) ? 0 : $shippingFeePaise;
         $totalPaise = max(0, $subtotalPaise - $discountPaise + $shippingPaise);

         // COD Availability Heuristic
         $codEnabled = (bool) SiteSetting::get('cod_enabled', true);
         $codMaxRupees = (int) SiteSetting::get('cod_max_rupees', 5000);
         $codAvailable = $codEnabled && ($totalPaise <= $codMaxRupees * 100);
         $codReason = null;

         if (! $codEnabled) {
             $codReason = 'Cash on Delivery is currently disabled.';
         } elseif ($totalPaise > ($codMaxRupees * 100)) {
             $codReason = 'Cash on Delivery is only available for orders up to ₹'.number_format($codMaxRupees);
         } elseif ($request->filled('pincode')) {
             $pin = trim($request->input('pincode'));
             $pincodeRecord = Pincode::where('pincode', $pin)->first();
             if ($pincodeRecord && ! $pincodeRecord->cod_available) {
                 $codAvailable = false;
                 $codReason = "Cash on Delivery is not available for pincode {$pin}.";
             }
         }

         $taxPaise = (int) round($subtotalPaise * (18 / 118)); // 18% inclusive GST estimate
         $codFeeRupees = (int) SiteSetting::get('cod_fee', 50);

         return $this->success([
             'subtotal' => (int) round($subtotalPaise / 100),
             'discount' => (int) round($discountPaise / 100),
             'shipping' => (int) round($shippingPaise / 100),
             'tax' => (int) round($taxPaise / 100),
             'total' => (int) round($totalPaise / 100),
             'currency' => 'INR',
             'freeShippingQualified' => $subtotalPaise >= $thresholdPaise,
             'codAvailable' => $codAvailable,
             'codMaxRupees' => $codMaxRupees,
             'codFee' => $codFeeRupees,
             'codReason' => $codReason,
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
            'shipping_address.pincode' => ['required', 'string', 'regex:/^[1-9][0-9]{5}$/'],
            'payment_method' => 'required|in:razorpay,cod',
            'coupon_code' => 'nullable|string|max:50',
            'guest_email' => 'nullable|email|max:255',
            'cart_token' => 'nullable|string',
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');
        $user = $request->user('sanctum') ?? $request->user();

        // Guest email is required only if not logged in
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
            guestEmail: $request->input('guest_email') ?: $user?->email,
            guestPhone: $request->input('shipping_address.phone'),
            cartToClear: $cartToClear
        );

        $statusCode = $order->wasRecentlyCreated ? 201 : 200;
        $message = $order->wasRecentlyCreated ? 'Order created successfully.' : 'Order retrieved successfully (idempotent duplicate request).';

        return $this->success(
            new OrderResource($order->load(['items', 'events'])),
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
        $order = Order::where('reference', $reference)->with(['items', 'events', 'payments'])->first();

        if (! $order) {
            return $this->error('Order not found.', 404);
        }

        $user = $request->user('sanctum') ?? $request->user();

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

    /**
     * Generate / Retrieve GST compliant tax invoice.
     */
    public function invoice(Request $request, string $reference): JsonResponse
    {
        $order = Order::where('reference', $reference)->with(['items.variant.product', 'events'])->first();

        if (! $order) {
            return $this->error('Order not found.', 404);
        }

        $user = $request->user('sanctum') ?? $request->user();
        $isAuthorized = false;

        if ($user) {
            if ($order->user_id === $user->id || $user->isAdmin()) {
                $isAuthorized = true;
            }
        } else {
            $queryEmail = strtolower((string) $request->input('email'));
            if ($queryEmail && strtolower((string) $order->guest_email) === $queryEmail) {
                $isAuthorized = true;
            }
        }

        if (! $isAuthorized) {
            return $this->error('Access denied. You do not have permission to view this invoice.', 403);
        }

        if (! $order->invoice_no) {
            $order->update(['invoice_no' => Order::generateInvoiceNo()]);
        }

        $placedDate = $order->placed_at ?? $order->created_at ?? now();
        $year = (int) $placedDate->format('Y');
        $month = (int) $placedDate->format('n');
        $financialYear = $month >= 4
            ? sprintf('%d-%d', $year, $year + 1)
            : sprintf('%d-%d', $year - 1, $year);

        return $this->success([
            'invoiceNo' => $order->invoice_no,
            'orderReference' => $order->reference,
            'status' => $order->status,
            'placedAt' => $order->placed_at?->toIso8601String() ?? $order->created_at->toIso8601String(),
            'customer' => [
                'name' => $order->shipping_address['name'] ?? 'Customer',
                'email' => $order->guest_email ?: $user?->email,
                'phone' => $order->guest_phone ?: ($order->shipping_address['phone'] ?? null),
                'address' => $order->shipping_address,
            ],
            'gstin' => SiteSetting::get('gstin', ''),
            'financialYear' => $financialYear,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'size' => $item->size_label,
                'hsn' => $item->variant?->product?->hsn_code ?? '33049990',
                'gstRate' => $item->variant?->product?->gst_rate ?? 18,
                'quantity' => $item->quantity,
                'unitPrice' => $item->unit_price_rupees,
                'total' => $item->total_rupees,
            ]),
            'pricing' => [
                'subtotal' => $order->subtotal_rupees,
                'discount' => $order->discount_rupees,
                'shipping' => $order->shipping_rupees,
                'codFee' => $order->cod_fee_rupees,
                'tax' => $order->tax_rupees,
                'total' => $order->total_rupees,
            ],
        ], 'Invoice data retrieved successfully.');
    }

    /**
     * Get tracking details and event timeline for an order.
     */
    public function tracking(Request $request, string $reference): JsonResponse
    {
        $order = Order::where('reference', $reference)->with(['shipment.events'])->first();

        if (! $order) {
            return $this->error('Order not found.', 404);
        }

        $user = $request->user('sanctum') ?? $request->user();
        $isAuthorized = false;

        if ($user) {
            if ($order->user_id === $user->id || (method_exists($user, 'isAdmin') && $user->isAdmin())) {
                $isAuthorized = true;
            }
        } else {
            $queryEmail = strtolower((string) $request->input('email'));
            $queryPhone = (string) $request->input('phone');
            if ($queryEmail && strtolower((string) $order->guest_email) === $queryEmail) {
                $isAuthorized = true;
            } elseif ($queryPhone && (string) $order->guest_phone === $queryPhone) {
                $isAuthorized = true;
            } elseif ($request->cookie('maysha_order_guest_'.$order->reference)) {
                $isAuthorized = true;
            }
        }

        if (! $isAuthorized) {
            return $this->error('Access denied. Please provide email or phone to view tracking.', 403);
        }

        $shipment = $order->shipment;

        return $this->success([
            'orderReference' => $order->reference,
            'status' => $order->status,
            'courier' => $shipment?->courier_name ?: $order->courier,
            'awb' => $shipment?->awb_code ?: $order->tracking_no,
            'trackingUrl' => $shipment?->tracking_url ?: ($order->tracking_no ? "https://shiprocket.co/tracking/{$order->tracking_no}" : null),
            'events' => $shipment ? $shipment->events->map(fn ($ev) => [
                'status' => $ev->status,
                'location' => $ev->location,
                'note' => $ev->note,
                'occurredAt' => $ev->occurred_at?->toIso8601String(),
            ]) : [],
        ], 'Tracking details retrieved successfully.');
    }
}
