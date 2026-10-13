<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Production and all environments require system roles and baseline store settings
        $this->call([
            RolesAndPermissionsSeeder::class,
            SiteSettingSeeder::class,
        ]);

        // Ensure default administrator account exists
        \App\Models\User::firstOrCreate(
            ['email' => 'admin@maysha.com'],
            [
                'name' => 'Maysha Administrator',
                'password' => \Illuminate\Support\Facades\Hash::make('Admin123456!'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // 2. Demo catalog and demo reviews should only be seeded in local and staging environments
        if (app()->environment(['local', 'staging'])) {
            $this->call([
                BannerSeeder::class,
                CatalogSeeder::class,
                CouponSeeder::class,
                ArticleSeeder::class,
            ]);
        }
    }
}
