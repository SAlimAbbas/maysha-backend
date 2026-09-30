<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminProductController extends Controller
{
    use ApiResponse;

    /**
     * List all products for admin management.
     */
    public function index(Request $request): JsonResponse
    {
        $products = Product::with(['category', 'concern', 'variants', 'media'])
            ->orderBy('id', 'desc')
            ->paginate(20);

        return $this->success(
            ProductResource::collection($products->items()),
            'Admin products retrieved successfully.',
            [
                'total' => $products->total(),
                'currentPage' => $products->currentPage(),
                'lastPage' => $products->lastPage(),
            ]
        );
    }

    /**
     * Create a new product with variants.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'concern_id' => 'nullable|exists:concerns,id',
            'badge' => 'nullable|string|max:50',
            'key_benefits' => 'nullable|array',
            'skin_types' => 'nullable|array',
            'ingredients' => 'nullable|string',
            'usage_instructions' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'variants' => 'required|array|min:1',
            'variants.*.sku' => 'required|string|max:100|unique:product_variants,sku',
            'variants.*.size_label' => 'required|string|max:50',
            'variants.*.price_rupees' => 'required|integer|min:1',
            'variants.*.mrp_rupees' => 'required|integer|min:1',
            'variants.*.stock' => 'required|integer|min:0',
        ]);

        $product = DB::transaction(function () use ($validated, $request) {
            $slug = $validated['slug'] ?? Str::slug($validated['name']);

            $product = Product::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'subtitle' => $validated['subtitle'] ?? null,
                'description' => $validated['description'],
                'category_id' => $validated['category_id'],
                'concern_id' => $validated['concern_id'] ?? null,
                'badge' => $validated['badge'] ?? null,
                'key_benefits' => $validated['key_benefits'] ?? [],
                'skin_types' => $validated['skin_types'] ?? [],
                'ingredients' => $validated['ingredients'] ?? null,
                'usage_instructions' => $validated['usage_instructions'] ?? null,
                'is_featured' => $validated['is_featured'] ?? false,
                'is_active' => true,
            ]);

            foreach ($validated['variants'] as $v) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $v['sku'],
                    'size_label' => $v['size_label'],
                    'price_paise' => (int) $v['price_rupees'] * 100,
                    'mrp_paise' => (int) $v['mrp_rupees'] * 100,
                    'stock' => (int) $v['stock'],
                    'is_active' => true,
                ]);
            }

            AuditLog::record('product.created', $product, $request->user(), ['name' => $product->name]);

            return $product;
        });

        return $this->success(
            new ProductResource($product->load(['category', 'concern', 'variants', 'media'])),
            'Product created successfully.',
            [],
            201
        );
    }

    /**
     * Update product details and stock.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'sometimes|required|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'concern_id' => 'nullable|exists:concerns,id',
            'badge' => 'nullable|string|max:50',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $product->update($validated);

        AuditLog::record('product.updated', $product, $request->user(), $validated);

        return $this->success(
            new ProductResource($product->load(['category', 'concern', 'variants', 'media'])),
            'Product updated successfully.'
        );
    }

    /**
     * Soft-delete product.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        AuditLog::record('product.deleted', $product, $request->user());

        return $this->success(null, 'Product successfully removed.');
    }
}
