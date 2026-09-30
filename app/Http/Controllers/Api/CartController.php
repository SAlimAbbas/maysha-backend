<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    public function getCart(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'items' => [],
                'subtotal' => 0,
                'free_shipping_threshold' => 999,
                'shipping_fee' => 99,
                'coupon' => null,
                'total' => 0,
            ]
        ]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|string',
            'size' => 'required|string',
            'quantity' => 'nullable|integer|min:1',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product added to cart successfully',
            'data' => $validated
        ]);
    }

    public function updateItem(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cart item updated',
            'id' => $id,
            'quantity' => $validated['quantity']
        ]);
    }

    public function removeItem(string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart',
            'id' => $id
        ]);
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $code = strtoupper(trim($request->input('code', '')));

        if ($code === 'MAYSHA10' || $code === 'CLEAN10') {
            return response()->json([
                'success' => true,
                'message' => 'Coupon code applied successfully',
                'data' => [
                    'code' => $code,
                    'discount_percentage' => 10,
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired coupon code.'
        ], 422);
    }
}
