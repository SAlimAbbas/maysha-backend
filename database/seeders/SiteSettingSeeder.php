<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            'free_shipping_threshold' => 999,
            'shipping_fee' => 99,
            'cod_enabled' => true,
            'cod_max_rupees' => 5000,
            'announcement_messages' => [
                'Free Express Shipping on Orders Above ₹999',
                'Get 10% Off on Your First Order with Code MAYSHA10',
                'Dermatologically Tested • Clean Formulations • Cruelty Free',
            ],
            'support_email' => 'care@maysha.com',
            'support_phone' => '+91 98765 43210',
            'social_links' => [
                'instagram' => 'https://instagram.com/mayshaskincare',
                'facebook' => 'https://facebook.com/mayshaskincare',
                'twitter' => 'https://twitter.com/mayshaskincare',
            ],
            'gstin' => '29ABCDE1234F1Z5',
        ];

        foreach ($defaults as $key => $val) {
            SiteSetting::firstOrCreate(
                ['key' => $key],
                ['value' => $val]
            );
        }
    }
}
