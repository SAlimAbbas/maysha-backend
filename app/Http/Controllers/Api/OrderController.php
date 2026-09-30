<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'pincode' => 'required|string|max:10',
            'items' => 'required|array|min:1',
            'payment_method' => 'required|string|in:razorpay,cod',
        ]);

        $orderReference = 'MAY-' . strtoupper(substr(uniqid(), -6));

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => [
                'order_reference' => $orderReference,
                'status' => 'confirmed',
                'customer' => [
                    'name' => $validated['customer_name'],
                    'email' => $validated['customer_email'],
                ],
                'shipping_address' => [
                    'address' => $validated['address'],
                    'city' => $validated['city'],
                    'pincode' => $validated['pincode'],
                ],
                'payment_method' => $validated['payment_method'],
                'created_at' => now()->toIso8601String()
            ]
        ], 201);
    }

    public function show(string $reference): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'order_reference' => $reference,
                'status' => 'processing',
                'estimated_delivery' => now()->addDays(3)->format('d M Y')
            ]
        ]);
    }
}
