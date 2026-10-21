<?php

namespace Tests\Feature;

use App\Models\Pincode;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontAndAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
        $this->seed(SiteSettingSeeder::class);
    }

    public function test_can_fetch_homepage_aggregate(): void
    {
        $response = $this->getJson('/api/v1/home');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'banners',
                    'categories',
                    'concerns',
                    'bestsellers',
                    'newLaunches',
                    'featuredReviews',
                    'announcements',
                ],
            ]);
    }

    public function test_can_fetch_sitemap_slugs(): void
    {
        $response = $this->getJson('/api/v1/sitemap/slugs');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'products',
                    'categories',
                    'concerns',
                ],
            ]);
    }

    public function test_pincode_verification(): void
    {
        Pincode::create([
            'pincode' => '560001',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'serviceable' => true,
            'cod_available' => true,
            'eta_days' => 2,
        ]);

        $response = $this->getJson('/api/v1/shipping/pincode/560001');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pincode' => '560001',
                    'serviceable' => true,
                    'city' => 'Bengaluru',
                    'state' => 'Karnataka',
                    'codAvailable' => true,
                    'estimatedDeliveryDays' => 2,
                ],
            ]);

        // Invalid pincode
        $invalid = $this->getJson('/api/v1/shipping/pincode/1234');
        $invalid->assertStatus(422);
    }

    public function test_customer_addresses_crud(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        // Create address
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/account/addresses', [
            'name' => 'John Doe',
            'phone' => '9876543210',
            'line1' => 'Flat 101, Palm Grove',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'is_default' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'city' => 'Mumbai',
                    'is_default' => true,
                ],
            ]);

        $addressId = $response->json('data.id');

        // Fetch addresses
        $list = $this->actingAs($user, 'sanctum')->getJson('/api/v1/account/addresses');
        $list->assertStatus(200)->assertJsonCount(1, 'data');

        // Update address
        $update = $this->actingAs($user, 'sanctum')->putJson("/api/v1/account/addresses/{$addressId}", [
            'city' => 'Navi Mumbai',
        ]);
        $update->assertStatus(200)->assertJsonPath('data.city', 'Navi Mumbai');

        // Delete address
        $delete = $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/account/addresses/{$addressId}");
        $delete->assertStatus(200);
        $this->assertDatabaseMissing('addresses', ['id' => $addressId]);
    }

    public function test_customer_wishlist_management(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $product = Product::active()->first();

        // Add to wishlist
        $add = $this->actingAs($user, 'sanctum')->postJson("/api/v1/account/wishlist/{$product->id}");
        $add->assertStatus(200);

        // Fetch wishlist
        $list = $this->actingAs($user, 'sanctum')->getJson('/api/v1/account/wishlist');
        $list->assertStatus(200)->assertJsonCount(1, 'data');

        // Remove from wishlist
        $remove = $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/account/wishlist/{$product->id}");
        $remove->assertStatus(200);

        $emptyList = $this->actingAs($user, 'sanctum')->getJson('/api/v1/account/wishlist');
        $emptyList->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_helpful_review_voting_and_duplicate_prevention(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $review = Review::approved()->first();

        $initialVotes = $review->helpful_count;

        // First vote succeeds
        $res = $this->actingAs($user, 'sanctum')->postJson("/api/v1/reviews/{$review->id}/helpful");
        $res->assertStatus(200)->assertJsonPath('data.helpfulCount', $initialVotes + 1);

        // Second vote rejected (duplicate prevention)
        $dup = $this->actingAs($user, 'sanctum')->postJson("/api/v1/reviews/{$review->id}/helpful");
        $dup->assertStatus(409);
    }

    public function test_store_info_returns_about_and_seo_settings(): void
    {
        $response = $this->getJson('/api/v1/store-info');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'brand',
                    'tagline',
                    'currency',
                    'about' => [
                        'heroTitle',
                        'heroSubtitle',
                        'heroImage',
                        'philosophyImage',
                        'storyTitle',
                        'storyContent',
                    ],
                    'seo' => [
                        'homeTitle',
                        'homeDescription',
                        'aboutTitle',
                        'aboutDescription',
                    ],
                ],
            ]);
    }

    public function test_admin_can_update_about_and_seo_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $payload = [
            'settings' => [
                'about_hero_title' => 'Custom Botanical Title',
                'about_hero_subtitle' => 'Custom subtitle description for our lab',
                'about_hero_image' => 'https://res.cloudinary.com/test/about-hero.jpg',
                'about_philosophy_image' => 'https://res.cloudinary.com/test/about-phil.jpg',
                'about_story_title' => 'Our Unique Story',
                'about_story_content' => 'Paragraph 1 about our formulations.',
                'seo_about_title' => 'Custom SEO About Title',
                'seo_about_description' => 'Custom SEO About Description',
            ],
        ];

        $updateRes = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/settings', $payload);
        $updateRes->assertStatus(200);

        $storeInfo = $this->getJson('/api/v1/store-info');
        $storeInfo->assertStatus(200)
            ->assertJsonPath('data.about.heroTitle', 'Custom Botanical Title')
            ->assertJsonPath('data.about.heroSubtitle', 'Custom subtitle description for our lab')
            ->assertJsonPath('data.about.heroImage', 'https://res.cloudinary.com/test/about-hero.jpg')
            ->assertJsonPath('data.about.storyTitle', 'Our Unique Story')
            ->assertJsonPath('data.about.storyContent', 'Paragraph 1 about our formulations.')
            ->assertJsonPath('data.seo.aboutTitle', 'Custom SEO About Title');
    }
}

