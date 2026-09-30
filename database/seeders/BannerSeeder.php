<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $banners = [
            [
                'title' => 'Buy 1 Get 1 Free - Vitamin C Face Wash',
                'subtitle' => 'Brighten & cleanse with potent Vitamin C + Niacinamide',
                'desktop_image' => '/banners/bogo-hero-banner.jpg',
                'mobile_image' => '/banners/bogo-hero-banner.jpg',
                'link_url' => '/collections/face-cleansers',
                'badge_text' => 'SPECIAL OFFER • BOGO',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'Multi-Peptide Hydration Serum',
                'subtitle' => 'Dewy Skin Elevated with Botanical Peptides & Hyaluronic Acid',
                'desktop_image' => '/banners/serum-hero-banner.jpg',
                'mobile_image' => '/banners/serum-hero-banner.jpg',
                'link_url' => '/collections/face-serums',
                'badge_text' => 'NEW LAUNCH',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'Mineral Invisible Sunscreen SPF 50+',
                'subtitle' => 'Broad spectrum PA++++ with zero white cast and silky matte finish',
                'desktop_image' => '/banners/sunscreen-hero-banner.jpg',
                'mobile_image' => '/banners/sunscreen-hero-banner.jpg',
                'link_url' => '/collections/sunscreens',
                'badge_text' => 'SUMMER ESSENTIAL',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'title' => 'Ceramide Barrier Moisture Cream',
                'subtitle' => 'Repair & restore skin barrier with deep nourishment and active ceramides',
                'desktop_image' => '/banners/ceramide-hero-banner.jpg',
                'mobile_image' => '/banners/ceramide-hero-banner.jpg',
                'link_url' => '/collections/moisturizers',
                'badge_text' => 'DERMATOLOGIST FAVOURITE',
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($banners as $data) {
            Banner::updateOrCreate(
                ['title' => $data['title']],
                $data
            );
        }
    }
}
