<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ConcernResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use App\Http\Traits\ApiResponse;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Concern;
use App\Models\Product;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    use ApiResponse;

    /**
     * Get aggregate data for the homepage (banners, categories, concerns, bestsellers, new launches, reviews, announcements).
     */
    public function index(): JsonResponse
    {
        $homeData = CatalogCacheService::remember('storefront:home:aggregate', ['home', 'banners', 'products', 'categories'], 3600, function () {
            $banners = Banner::with(['desktopMedia', 'mobileMedia'])
                ->where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->take(7)
                ->get();

            $categories = Category::where('is_active', true)
                ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('sort_order', 'asc')
                ->get();

            $concerns = Concern::where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->get();

            $bestsellers = Product::active()
                ->where(function ($q) {
                    $q->where('is_featured', true)
                        ->orWhere('badge', 'LIKE', '%Bestseller%')
                        ->orWhere('badge', 'LIKE', '%Best Seller%');
                })
                ->with(['category', 'concern', 'variants' => fn ($q) => $q->where('is_active', true), 'media'])
                ->orderBy('review_count', 'desc')
                ->take(8)
                ->get();

            $newLaunches = Product::active()
                ->where(function ($q) {
                    $q->where('badge', 'LIKE', '%New Launch%')
                        ->orWhere('badge', 'LIKE', '%NEW%');
                })
                ->with(['category', 'concern', 'variants' => fn ($q) => $q->where('is_active', true), 'media'])
                ->orderBy('id', 'desc')
                ->take(8)
                ->get();

            if ($newLaunches->count() < 4) {
                $existingIds = $newLaunches->pluck('id')->toArray();
                $more = Product::active()
                    ->whereNotIn('id', $existingIds)
                    ->with(['category', 'concern', 'variants' => fn ($q) => $q->where('is_active', true), 'media'])
                    ->orderBy('id', 'desc')
                    ->take(8 - $newLaunches->count())
                    ->get();
                $newLaunches = $newLaunches->merge($more);
            }

            $reviews = Review::approved()
                ->where('rating', '>=', 4)
                ->with('product')
                ->orderBy('helpful_count', 'desc')
                ->take(6)
                ->get();

            $announcements = SiteSetting::get('announcement_messages', [
                'Free Express Shipping on Orders Above ₹999',
                'Get 10% Off on Your First Order with Code MAYSHA10',
            ]);

            return [
                'banners' => BannerResource::collection($banners),
                'categories' => CategoryResource::collection($categories),
                'concerns' => ConcernResource::collection($concerns),
                'bestsellers' => ProductResource::collection($bestsellers),
                'newLaunches' => ProductResource::collection($newLaunches),
                'featuredReviews' => ReviewResource::collection($reviews),
                'announcements' => $announcements,
            ];
        });

        return $this->success($homeData, 'Home aggregate retrieved successfully.');
    }

    /**
     * Get slugs for static generation and sitemap.
     */
    public function slugs(): JsonResponse
    {
        $slugs = CatalogCacheService::remember('storefront:sitemap:slugs', ['catalog'], 3600, function () {
            $products = Product::active()->select('slug', 'updated_at')->get()->map(fn ($p) => [
                'slug' => $p->slug,
                'type' => 'product',
                'updatedAt' => $p->updated_at?->toIso8601String(),
            ]);

            $categories = Category::where('is_active', true)->select('slug', 'updated_at')->get()->map(fn ($c) => [
                'slug' => $c->slug,
                'type' => 'category',
                'updatedAt' => $c->updated_at?->toIso8601String(),
            ]);

            $concerns = Concern::where('is_active', true)->select('slug', 'updated_at')->get()->map(fn ($c) => [
                'slug' => $c->slug,
                'type' => 'concern',
                'updatedAt' => $c->updated_at?->toIso8601String(),
            ]);

            return [
                'products' => $products,
                'categories' => $categories,
                'concerns' => $concerns,
            ];
        });

        return $this->success($slugs, 'Sitemap slugs retrieved successfully.');
    }
}
