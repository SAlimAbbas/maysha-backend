<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    public function test_can_fetch_products_catalog_with_frontend_shape(): void
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'name',
                        'subtitle',
                        'category',
                        'categorySlug',
                        'concern',
                        'concernSlug',
                        'price',
                        'mrp',
                        'discountPercentage',
                        'size',
                        'rating',
                        'reviewCount',
                        'inStock',
                        'images',
                        'hoverImage',
                        'variants' => [
                            '*' => ['id', 'size', 'price', 'mrp', 'inStock'],
                        ],
                        'keyBenefits',
                        'skinTypes',
                    ],
                ],
                'meta' => [
                    'total',
                    'currentPage',
                    'lastPage',
                    'perPage',
                ],
            ]);
    }

    public function test_can_filter_products_by_category(): void
    {
        $response = $this->getJson('/api/v1/products?category=cleansers');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        foreach ($data as $product) {
            $this->assertEquals('cleansers', $product['categorySlug']);
        }
    }

    public function test_can_fetch_single_product_by_slug(): void
    {
        $response = $this->getJson('/api/v1/products/gentle-face-cleanser');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'product' => [
                        'slug' => 'gentle-face-cleanser',
                        'name' => 'Gentle Face Cleanser',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'product' => ['id', 'name', 'variants', 'description', 'ingredients', 'usageInstructions'],
                    'related',
                ],
            ]);
    }

    public function test_returns_404_for_non_existent_product(): void
    {
        $response = $this->getJson('/api/v1/products/non-existent-product-slug');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Product not found.',
            ]);
    }

    public function test_can_fetch_categories_with_item_count(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'slug', 'name', 'subtitle', 'image', 'itemCount'],
                ],
            ]);
    }

    public function test_can_fetch_concerns(): void
    {
        $response = $this->getJson('/api/v1/concerns');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'slug', 'name', 'image', 'description'],
                ],
            ]);
    }

    public function test_search_suggest_returns_matching_suggestions(): void
    {
        $response = $this->getJson('/api/v1/search/suggest?q=cleanser');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $suggestions = $response->json('data.suggestions');
        $this->assertNotEmpty($suggestions);
    }

    public function test_unauthenticated_user_cannot_post_review(): void
    {
        $product = Product::first();

        $response = $this->postJson('/api/v1/reviews', [
            'product_id' => $product->id,
            'rating' => 5,
            'comment' => 'Amazing product!',
        ]);

        $response->assertStatus(401);
    }
}
