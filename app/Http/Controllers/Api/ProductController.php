<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Traits\ApiResponse;
use App\Models\Product;
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

        // Filter: Concern
        if ($request->filled('concern')) {
            $conSlug = $request->input('concern');
            $query->whereHas('concern', fn ($q) => $q->where('slug', $conSlug));
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

        return $this->success(
            ProductResource::collection($paginated->items()),
            'Products retrieved successfully.',
            [
                'total' => $paginated->total(),
                'currentPage' => $paginated->currentPage(),
                'lastPage' => $paginated->lastPage(),
                'perPage' => $paginated->perPage(),
            ]
        );
    }

    /**
     * Get featured and bestselling products.
     */
    public function bestsellers(): JsonResponse
    {
        $products = Product::active()
            ->where(function ($q) {
                $q->where('is_featured', true)
                    ->orWhere('badge', 'Bestseller');
            })
            ->with(['category', 'concern', 'variants' => fn ($q) => $q->where('is_active', true), 'media'])
            ->orderBy('review_count', 'desc')
            ->take(8)
            ->get();

        return $this->success(
            ProductResource::collection($products),
            'Bestselling products retrieved successfully.'
        );
    }

    /**
     * Get newly launched formulations.
     */
    public function newLaunches(): JsonResponse
    {
        $products = Product::active()
            ->where('badge', 'New Launch')
            ->with(['category', 'concern', 'variants' => fn ($q) => $q->where('is_active', true), 'media'])
            ->orderBy('id', 'desc')
            ->take(8)
            ->get();

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
        $related = Product::active()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with(['category', 'concern', 'variants' => fn ($q) => $q->where('is_active', true), 'media'])
            ->take(4)
            ->get();

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

        $products = Product::active()
            ->where('name', 'LIKE', "%{$term}%")
            ->select('id', 'slug', 'name', 'badge', 'rating_avg')
            ->take(6)
            ->get();

        return $this->success([
            'suggestions' => $products->map(fn ($p) => [
                'id' => (string) $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'badge' => $p->badge,
                'rating' => (float) $p->rating_avg,
            ]),
        ]);
    }
}
