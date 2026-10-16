<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Traits\ApiResponse;
use App\Models\Product;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    /**
     * Get paginated and filtered product catalog.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::active()
            ->with(['category', 'concern', 'variants' => fn ($q) => $q->where('is_active', true), 'media']);

        // Filter: Category
        if ($request->filled('category')) {
            $catSlug = $request->input('category');
            $query->whereHas('category', fn ($q) => $q->where('slug', $catSlug));
        }

        // Filter: Benefit (Target Need)
        if ($request->filled('benefit')) {
            $benefit = $request->input('benefit');
            $query->where('key_benefits', 'LIKE', "%{$benefit}%");
        }

        // Filter: Skin Type / Application Type
        if ($request->filled('skin_type')) {
            $skinType = $request->input('skin_type');
            $query->where('skin_types', 'LIKE', "%{$skinType}%");
        }

        // Filter: Concern
        if ($request->filled('concern')) {
            $conSlug = trim((string) $request->input('concern'));
            $query->whereHas('concern', function ($q) use ($conSlug) {
                $q->where('slug', $conSlug)
                    ->orWhere('slug', 'LIKE', "{$conSlug}-%")
                    ->orWhere('slug', 'LIKE', "%{$conSlug}%");
            });
        }

        // Filter: Min Price (rupees converted to paise)
        if ($request->filled('min_price')) {
            $minPaise = (int) $request->input('min_price') * 100;
            $query->whereHas('variants', fn ($q) => $q->where('price_paise', '>=', $minPaise));
        }

        // Filter: Max Price (rupees converted to paise)
        if ($request->filled('max_price')) {
            $maxPaise = (int) $request->input('max_price') * 100;
            $query->whereHas('variants', fn ($q) => $q->where('price_paise', '<=', $maxPaise));
        }

        // Filter: Size
        if ($request->filled('size')) {
            $size = $request->input('size');
            $query->whereHas('variants', fn ($q) => $q->where('size_label', 'LIKE', "%{$size}%"));
        }

        // Filter: Minimum Rating
        if ($request->filled('rating')) {
            $rating = (float) $request->input('rating');
            $query->where('rating_avg', '>=', $rating);
        }

        // Filter: In Stock Only
        if ($request->boolean('in_stock')) {
            $query->whereHas('variants', fn ($q) => $q->where('stock', '>', 0)->where('is_active', true));
        }

        // Search Query (q)
        if ($request->filled('q')) {
            $term = trim($request->input('q'));
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('subtitle', 'LIKE', "%{$term}%")
                    ->orWhere('description', 'LIKE', "%{$term}%")
                    ->orWhere('ingredients', 'LIKE', "%{$term}%");
            });
        }

        // Sorting
        $sort = $request->input('sort', 'newest');
        switch ($sort) {
            case 'price_asc':
                $query->orderBy(
                    Product::select('price_paise')
                        ->from('product_variants')
                        ->whereColumn('product_variants.product_id', 'products.id')
                        ->orderBy('price_paise', 'asc')
                        ->limit(1),
                    'asc'
                );
                break;
            case 'price_desc':
                $query->orderBy(
                    Product::select('price_paise')
                        ->from('product_variants')
                        ->whereColumn('product_variants.product_id', 'products.id')
                        ->orderBy('price_paise', 'desc')
                        ->limit(1),
                    'desc'
                );
                break;
            case 'rating':
                $query->orderBy('rating_avg', 'desc');
                break;
            case 'popularity':
                $query->orderBy('review_count', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('id', 'desc');
                break;
        }

        $perPage = min((int) $request->input('per_page', 12), 48);
        $paginated = $query->paginate($perPage);

        // Dynamic Facets Computation across active catalog
        $allActive = Product::active()
            ->with(['category', 'variants' => fn ($q) => $q->where('is_active', true)])
            ->get();

        // 1. Categories facet with live product counts
        $catCounts = [];
        foreach ($allActive as $p) {
            if ($p->category) {
                $cId = (string) $p->category->id;
                if (!isset($catCounts[$cId])) {
                    $catCounts[$cId] = [
                        'id' => $cId,
                        'name' => $p->category->name,
                        'slug' => $p->category->slug,
                        'count' => 0,
                    ];
                }
                $catCounts[$cId]['count']++;
            }
        }
        $categoriesFacet = array_values($catCounts);

        // 2. Dynamic Skin / Target Types facet (Normalized canonical labels)
        $canonicalMap = [
            'sensitive skin' => 'Sensitive',
            'sensitive' => 'Sensitive',
            'oily skin' => 'Oily',
            'oily' => 'Oily',
            'dry skin' => 'Dry',
            'dry' => 'Dry',
            'combination skin' => 'Combination',
            'combination' => 'Combination',
            'all skin types' => 'All Skin Types',
            'all' => 'All Skin Types',
            'normal skin' => 'Normal',
            'normal' => 'Normal',
            'acne-prone skin' => 'Acne-Prone',
            'acne prone' => 'Acne-Prone',
            'acne-prone' => 'Acne-Prone',
        ];

        $skinTypesCount = [];
        foreach ($allActive as $p) {
            $types = is_array($p->skin_types) ? $p->skin_types : [];
            $seen = [];
            foreach ($types as $st) {
                $clean = strtolower(trim((string) $st));
                $canonical = $canonicalMap[$clean] ?? ucfirst($clean);
                if (!empty($canonical) && !isset($seen[$canonical])) {
                    $seen[$canonical] = true;
                    $skinTypesCount[$canonical] = ($skinTypesCount[$canonical] ?? 0) + 1;
                }
            }
        }
        arsort($skinTypesCount);
        $skinTypesFacet = [];
        foreach ($skinTypesCount as $name => $count) {
            $skinTypesFacet[] = ['name' => $name, 'count' => $count];
        }

        // 3. Min & Max price in catalog
        $minPrice = 999999;
        $maxPrice = 0;
        foreach ($allActive as $p) {
            foreach ($p->variants as $v) {
                $pr = $v->price_rupees;
                if ($pr < $minPrice) $minPrice = $pr;
                if ($pr > $maxPrice) $maxPrice = $pr;
            }
        }
        if ($minPrice === 999999) $minPrice = 299;
        if ($maxPrice === 0) $maxPrice = 999;

        $facets = [
            'categories' => $categoriesFacet,
            'skinTypes' => array_slice($skinTypesFacet, 0, 8),
            'priceRange' => [
                'min' => $minPrice,
                'max' => $maxPrice,
            ],
        ];

        return $this->success(
            ProductResource::collection($paginated->items()),
            'Products retrieved successfully.',
            [
                'total' => $paginated->total(),
                'currentPage' => $paginated->currentPage(),
                'lastPage' => $paginated->lastPage(),
                'perPage' => $paginated->perPage(),
                'facets' => $facets,
            ]
        );
    }

    /**
     * Get featured and bestselling products.
     */
    public function bestsellers(): JsonResponse
    {
        $products = CatalogCacheService::remember('products:bestsellers', ['products', 'catalog'], 300, function () {
            return Product::active()
                ->where(function ($q) {
                    $q->where('is_featured', true)
                        ->orWhere('badge', 'LIKE', '%Bestseller%')
                        ->orWhere('badge', 'LIKE', '%Best Seller%');
                })
                ->with(['category', 'concern', 'variants' => fn ($q) => $q->where('is_active', true), 'media'])
                ->orderBy('review_count', 'desc')
                ->take(8)
                ->get();
        });

        return $this->success(
            ProductResource::collection($products),
            'Bestselling products retrieved successfully.'
        );
    }

    /**
     * Get newly launched formulations and recently added products.
     */
    public function newLaunches(): JsonResponse
    {
        $products = CatalogCacheService::remember('products:new_launches', ['products', 'catalog'], 300, function () {
            // First get products with New Launch badge
            $list = Product::active()
                ->where(function ($q) {
                    $q->where('badge', 'LIKE', '%New Launch%')
                        ->orWhere('badge', 'LIKE', '%NEW%');
                })
                ->with(['category', 'concern', 'variants' => fn ($q) => $q->where('is_active', true), 'media'])
                ->orderBy('id', 'desc')
                ->take(8)
                ->get();

            // Supplement with the latest newly added products in the catalog
            if ($list->count() < 4) {
                $existingIds = $list->pluck('id')->toArray();
                $more = Product::active()
                    ->whereNotIn('id', $existingIds)
                    ->with(['category', 'concern', 'variants' => fn ($q) => $q->where('is_active', true), 'media'])
                    ->orderBy('id', 'desc')
                    ->take(8 - $list->count())
                    ->get();
                $list = $list->merge($more);
            }

            return $list;
        });

        return $this->success(
            ProductResource::collection($products),
            'New products retrieved successfully.'
        );
    }

    /**
     * Get single product by slug along with related products.
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::active()
            ->where('slug', $slug)
            ->with(['category', 'concern', 'variants' => fn ($q) => $q->where('is_active', true), 'media', 'reviews' => fn ($q) => $q->approved()])
            ->first();

        if (! $product) {
            return $this->error('Product not found.', 404);
        }

        // Related products in the same category
        $related = CatalogCacheService::remember("product:related:{$product->id}", ['products', 'catalog'], 3600, function () use ($product) {
            return Product::active()
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->with(['category', 'concern', 'variants' => fn ($q) => $q->where('is_active', true), 'media'])
                ->take(4)
                ->get();
        });

        return $this->success([
            'product' => new ProductResource($product),
            'related' => ProductResource::collection($related),
        ], 'Product details retrieved successfully.');
    }

    /**
     * Autocomplete search suggestions for header search bar.
     */
    public function suggest(Request $request): JsonResponse
    {
        $term = trim($request->input('q', ''));

        if (strlen($term) < 2) {
            return $this->success(['suggestions' => []]);
        }

        $cacheKey = 'search:suggest:'.md5($term);
        $suggestions = CatalogCacheService::remember($cacheKey, ['products', 'search'], 300, function () use ($term) {
            return Product::active()
                ->where('name', 'LIKE', "%{$term}%")
                ->select('id', 'slug', 'name', 'badge', 'rating_avg')
                ->take(6)
                ->get()
                ->map(fn ($p) => [
                    'id' => (string) $p->id,
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'badge' => $p->badge,
                    'rating' => (float) $p->rating_avg,
                ])
                ->all();
        });

        return $this->success([
            'suggestions' => $suggestions,
        ]);
    }
}
