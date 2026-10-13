<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'MAYSHA10',
                'type' => 'percent',
                'value' => 10,
                'min_subtotal_paise' => 0,
                'max_discount_paise' => 50000,
                'is_active' => true,
            ],
            [
                'code' => 'CLEAN10',
                'type' => 'percent',
                'value' => 10,
                'min_subtotal_paise' => 0,
                'max_discount_paise' => 50000,
                'is_active' => true,
            ],
            [
                'code' => 'WELCOME10',
                'type' => 'percent',
                'value' => 10,
                'min_subtotal_paise' => 0,
                'max_discount_paise' => 50000,
                'is_active' => true,
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::updateOrCreate(
                ['code' => $coupon['code']],
                $coupon
            );
        }
    }
}
