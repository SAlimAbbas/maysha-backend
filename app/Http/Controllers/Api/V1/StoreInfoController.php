<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class StoreInfoController extends Controller
{
    use ApiResponse;

    /**
     * Get storefront configuration and operational parameters.
     */
    public function show(): JsonResponse
    {
        return $this->success([
            'brand' => 'Maysha Skincare',
            'tagline' => 'Clean Beauty for Real Skin',
            'currency' => 'INR',
            'symbol' => '₹',
            'freeShippingThreshold' => (int) env('FREE_SHIPPING_THRESHOLD', 999),
            'shippingFee' => (int) env('SHIPPING_FEE', 99),
            'status' => 'operational',
            'supportEmail' => 'care@maysha.com',
        ], 'Store info retrieved successfully.');
    }
}
