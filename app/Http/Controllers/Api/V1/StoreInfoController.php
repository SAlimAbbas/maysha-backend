<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\SiteSetting;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;

class StoreInfoController extends Controller
{
    use ApiResponse;

    /**
     * Get storefront configuration and operational parameters.
     */
    public function show(): JsonResponse
    {
        $info = CatalogCacheService::remember('store:info', ['settings', 'home'], 3600, function () {
            return [
                'brand' => 'Maysha Skincare',
                'tagline' => 'Clean Beauty for Real Skin',
                'currency' => 'INR',
                'symbol' => '₹',
                'freeShippingThreshold' => (int) SiteSetting::get('free_shipping_threshold', env('FREE_SHIPPING_THRESHOLD', 999)),
                'shippingFee' => (int) SiteSetting::get('shipping_fee', env('SHIPPING_FEE', 99)),
                'codEnabled' => (bool) SiteSetting::get('cod_enabled', true),
                'codMaxRupees' => (int) SiteSetting::get('cod_max_rupees', 5000),
                'announcementMessages' => SiteSetting::get('announcement_messages', [
                    'Free Express Shipping on Orders Above ₹999',
                    'Get 10% Off on Your First Order with Code MAYSHA10',
                ]),
                'supportEmail' => (string) SiteSetting::get('support_email', 'care@maysha.com'),
                'supportPhone' => (string) SiteSetting::get('support_phone', '+91 98765 43210'),
                'socialLinks' => SiteSetting::get('social_links', [
                    'instagram' => 'https://instagram.com/mayshaskincare',
                ]),
                'gstin' => (string) SiteSetting::get('gstin', '29ABCDE1234F1Z5'),
                'admin2faEnabled' => (bool) filter_var(SiteSetting::get('admin_2fa_enabled', false), FILTER_VALIDATE_BOOLEAN),
                'status' => 'operational',
            ];
        });

        return $this->success($info, 'Store info retrieved successfully.');
    }
}
