<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

class BannerFactory extends Factory
{
    protected $model = Banner::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'subtitle' => fake()->sentence(8),
            'desktop_image' => fake()->imageUrl(1200, 500),
            'mobile_image' => fake()->imageUrl(600, 400),
            'link_url' => '/collections/face-serums',
            'badge_text' => 'SPECIAL OFFER',
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
