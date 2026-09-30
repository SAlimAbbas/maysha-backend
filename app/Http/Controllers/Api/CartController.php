<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartController extends Controller
{
    use ApiResponse;

    /**
     * Resolve the current cart based on auth user or guest token header/cookie.
     */
    protected function resolveCart(Request $request): Cart
    {
        $user = $request->user();
        $token = $request->header('X-Cart-Token') ?: $request->cookie('maysha_cart_token');

        if (! $token) {
            $token = 'cart_'.Str::random(32);
        }

        return Cart::findOrCreateCurrent($token, $user);
    }

    /**
     * Get the current cart contents and server-calculated pricing.
     */
    public function getCart(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $summary = $cart->computeSummary($request->user());

        return $this->success($summary, 'Cart retrieved successfully.', [
            'cartToken' => $cart->token,
        ]);
    }

    /**
     * Add an item to the cart.
     */
    public function addItem(Request $request): JsonResponse
    {
        $request->validate([
            'variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'nullable|integer|min:1|max:10',
        ]);

        $cart = $this->resolveCart($request);
        $variantId = $request->input('variant_id');
        $quantity = (int) $request->input('quantity', 1);

        $variant = ProductVariant::findOrFail($variantId);
        if ($variant->stock < $quantity) {
            return $this->error("Only {$variant->stock} units available in stock.", 422);
        }

        $item = $cart->items()->where('variant_id', $variantId)->first();
        if ($item) {
            $newQty = $item->quantity + $quantity;
            if ($variant->stock < $newQty) {
                return $this->error("Cannot add {$quantity} more. Only {$variant->stock} units available in stock.", 422);
            }
            $item->update(['quantity' => $newQty]);
        } else {
            $cart->items()->create([
                'variant_id' => $variantId,
                'quantity' => $quantity,
            ]);
        }

        $summary = $cart->fresh()->computeSummary($request->user());

        return $this->success($summary, 'Item added to cart successfully.', [
            'cartToken' => $cart->token,
        ]);
    }

    /**
     * Update an item quantity in the cart.
     */
    public function updateItem(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:0|max:10',
        ]);

        $cart = $this->resolveCart($request);
        $quantity = (int) $request->input('quantity');

        $item = $cart->items()->where('id', $id)->first();
        if (! $item) {
            return $this->error('Item not found in your cart.', 404);
        }

        if ($quantity === 0) {
            $item->delete();
        } else {
            if ($item->variant && $item->variant->stock < $quantity) {
                return $this->error("Only {$item->variant->stock} units available in stock.", 422);
            }
            $item->update(['quantity' => $quantity]);
        }

        $summary = $cart->fresh()->computeSummary($request->user());

        return $this->success($summary, 'Cart updated successfully.');
    }

    /**
     * Remove an item from the cart.
     */
    public function removeItem(Request $request, string $id): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $cart->items()->where('id', $id)->delete();

        $summary = $cart->fresh()->computeSummary($request->user());

        return $this->success($summary, 'Item removed from cart.');
    }

    /**
     * Apply a promotional coupon code.
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $cart = $this->resolveCart($request);
        $code = strtoupper(trim($request->input('code')));

        $coupon = Coupon::where('code', $code)->first();
        $summary = $cart->computeSummary($request->user());

        if (! $coupon || ! $coupon->isValidFor($summary['subtotalPaise'], $request->user())) {
            return $this->error('Invalid, expired, or inapplicable coupon code.', 422);
        }

        $cart->update(['coupon_id' => $coupon->id]);

        $newSummary = $cart->fresh()->computeSummary($request->user());

        return $this->success($newSummary, "Coupon '{$coupon->code}' applied successfully!");
    }

    /**
     * Remove applied coupon.
     */
    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $cart->update(['coupon_id' => null]);

        $summary = $cart->fresh()->computeSummary($request->user());

        return $this->success($summary, 'Coupon removed successfully.');
    }
}
