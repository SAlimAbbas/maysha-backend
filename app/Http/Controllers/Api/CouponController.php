<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    use ApiResponse;

    /**
     * Get active offers and coupons available for shoppers.
     */
    public function index(Request $request): JsonResponse
    {
        $now = now();
        $coupons = Coupon::where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('id', 'asc')
            ->get();

        $offers = $coupons->map(function ($c) {
            $minRupees = (int) round($c->min_subtotal_paise / 100);
            $maxDiscountRupees = $c->max_discount_paise ? (int) round($c->max_discount_paise / 100) : null;

            if ($c->code === 'B2G3RDFREE') {
                $desc = "Buy 2, Get the 3rd product free";
            } elseif ($c->type === 'percent') {
                $desc = $minRupees > 0
                    ? "Get Additional {$c->value}% Off on orders above ₹{$minRupees}/-"
                    : "Get {$c->value}% Off on your order";
            } else {
                $rupees = (int) round($c->value / 100);
                $desc = $minRupees > 0
                    ? "Flat ₹{$rupees} Off on orders above ₹{$minRupees}/-"
                    : "Flat ₹{$rupees} Off on your order";
            }

            return [
                'id' => (string) $c->id,
                'code' => $c->code,
                'type' => $c->type,
                'value' => (int) $c->value,
                'minOrderRupees' => $minRupees,
                'maxDiscountRupees' => $maxDiscountRupees,
                'firstOrderOnly' => (bool) $c->first_order_only,
                'description' => $desc,
            ];
        });

        // Ensure we always have default core offers if database is freshly initialized
        if ($offers->isEmpty()) {
            $offers = collect([
                [
                    'id' => 'offer-1',
                    'code' => 'ATC10',
                    'type' => 'percent',
                    'value' => 10,
                    'minOrderRupees' => 599,
                    'maxDiscountRupees' => 500,
                    'firstOrderOnly' => false,
                    'description' => 'Get Additional 10% Off on orders above ₹599/-',
                ],
                [
                    'id' => 'offer-2',
                    'code' => 'B2G3RDFREE',
                    'type' => 'percent',
                    'value' => 33,
                    'minOrderRupees' => 999,
                    'maxDiscountRupees' => 800,
                    'firstOrderOnly' => false,
                    'description' => 'Buy 2, Get the 3rd product FREE',
                ],
                [
                    'id' => 'offer-3',
                    'code' => 'MAYSHA10',
                    'type' => 'percent',
                    'value' => 10,
                    'minOrderRupees' => 0,
                    'maxDiscountRupees' => 500,
                    'firstOrderOnly' => false,
                    'description' => 'Get 10% Off on all clean skincare orders',
                ],
            ]);
        }

        return $this->success($offers, 'Active coupons and offers retrieved successfully.');
    }
}
