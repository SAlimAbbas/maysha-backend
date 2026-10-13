<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\Media;
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
            ->paginate(50);

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
     * Create a new product with full rich attributes and variants.
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
            'badge' => 'nullable|string|max:255',
            'key_benefits' => 'nullable|array',
            'skin_types' => 'nullable|array',
            'ingredients' => 'nullable|string',
            'usage_instructions' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'images' => 'nullable|array',
            'variants' => 'nullable|array',
            'price' => 'nullable|numeric|min:1',
            'mrp' => 'nullable|numeric|min:1',
            'stock' => 'nullable|integer|min:0',
            'size' => 'nullable|string|max:50',
        ]);

        $product = DB::transaction(function () use ($validated, $request) {
            $slug = $validated['slug'] ?? Str::slug($validated['name']);
            if (empty($slug)) {
                $slug = 'product-' . time();
            }

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

            // Create Variants
            $variants = $validated['variants'] ?? [];
            if (empty($variants)) {
                $priceRupees = (int) ($validated['price'] ?? 699);
                $mrpRupees = (int) ($validated['mrp'] ?? 899);
                $stock = (int) ($validated['stock'] ?? 50);
                $size = $validated['size'] ?? '50ml';

                $variants = [
                    [
                        'sku' => Str::upper(Str::slug($product->name)) . '-' . Str::slug($size) . '-' . rand(100, 999),
                        'size_label' => $size,
                        'price_rupees' => $priceRupees,
                        'mrp_rupees' => $mrpRupees,
                        'stock' => $stock,
                    ]
                ];
            }

            foreach ($variants as $v) {
                $sku = $v['sku'] ?? (Str::upper(Str::slug($product->name)) . '-' . Str::slug($v['size_label'] ?? 'std') . '-' . rand(100, 999));
                $pricePaise = isset($v['price_rupees']) ? (int) $v['price_rupees'] * 100 : (int) ($v['price'] ?? 699) * 100;
                $mrpPaise = isset($v['mrp_rupees']) ? (int) $v['mrp_rupees'] * 100 : (int) ($v['mrp'] ?? 899) * 100;

                ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'size_label' => $v['size_label'] ?? $v['size'] ?? '50ml',
                    'price_paise' => $pricePaise,
                    'mrp_paise' => $mrpPaise,
                    'stock' => (int) ($v['stock'] ?? 50),
                    'is_active' => true,
                ]);
            }

            // Sync Cloudinary Images
            if (!empty($validated['images'])) {
                foreach ($validated['images'] as $idx => $imageUrl) {
                    if (empty($imageUrl)) continue;
                    $media = Media::create([
                        'provider' => 'cloudinary',
                        'provider_id' => $imageUrl,
                        'type' => 'image',
                        'status' => 'ready',
                        'uploaded_by' => $request->user()?->id,
                    ]);

                    DB::table('product_media')->insert([
                        'product_id' => $product->id,
                        'media_id' => $media->id,
                        'role' => $idx === 0 ? 'primary' : 'gallery',
                        'sort_order' => $idx,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
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
     * Update product details, rich attributes, images, and variants.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::with(['variants', 'media'])->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => "sometimes|nullable|string|max:255|unique:products,slug,{$id}",
            'subtitle' => 'nullable|string|max:255',
            'description' => 'sometimes|required|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'concern_id' => 'nullable|exists:concerns,id',
            'badge' => 'nullable|string|max:255',
            'ingredients' => 'nullable|string',
            'usage_instructions' => 'nullable|string',
            'key_benefits' => 'nullable|array',
            'skin_types' => 'nullable|array',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'images' => 'nullable|array',
            'variants' => 'nullable|array',
            'price' => 'nullable|numeric|min:1',
            'mrp' => 'nullable|numeric|min:1',
            'stock' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($product, $validated, $request) {
            $product->update(collect($validated)->except(['images', 'variants', 'price', 'mrp', 'stock'])->toArray());

            // Sync Variants if passed as an array
            if (isset($validated['variants']) && is_array($validated['variants']) && !empty($validated['variants'])) {
                $keptVariantIds = [];
                foreach ($validated['variants'] as $v) {
                    $sizeLabel = $v['size_label'] ?? $v['size'] ?? '50ml';
                    $pricePaise = isset($v['price_rupees']) ? (int) $v['price_rupees'] * 100 : (int) ($v['price'] ?? 699) * 100;
                    $mrpPaise = isset($v['mrp_rupees']) ? (int) $v['mrp_rupees'] * 100 : (int) ($v['mrp'] ?? 899) * 100;
                    $stock = (int) ($v['stock'] ?? 50);

                    if (!empty($v['id']) && $existing = $product->variants()->find($v['id'])) {
                        $existing->update([
                            'size_label' => $sizeLabel,
                            'price_paise' => $pricePaise,
                            'mrp_paise' => $mrpPaise,
                            'stock' => $stock,
                            'is_active' => true,
                        ]);
                        $keptVariantIds[] = $existing->id;
                    } else {
                        $sku = $v['sku'] ?? (Str::upper(Str::slug($product->name)) . '-' . Str::slug($sizeLabel) . '-' . rand(100, 999));
                        $newV = ProductVariant::create([
                            'product_id' => $product->id,
                            'sku' => $sku,
                            'size_label' => $sizeLabel,
                            'price_paise' => $pricePaise,
                            'mrp_paise' => $mrpPaise,
                            'stock' => $stock,
                            'is_active' => true,
                        ]);
                        $keptVariantIds[] = $newV->id;
                    }
                }
                if (!empty($keptVariantIds)) {
                    $product->variants()->whereNotIn('id', $keptVariantIds)->delete();
                }
            } else {
                // Update primary variant price/stock if directly passed
                $primaryVariant = $product->variants->first();
                if ($primaryVariant) {
                    $updates = [];
                    if (isset($validated['price'])) {
                        $updates['price_paise'] = (int) $validated['price'] * 100;
                    }
                    if (isset($validated['mrp'])) {
                        $updates['mrp_paise'] = (int) $validated['mrp'] * 100;
                    }
                    if (isset($validated['stock'])) {
                        $updates['stock'] = (int) $validated['stock'];
                    }
                    if (!empty($updates)) {
                        $primaryVariant->update($updates);
                    }
                }
            }

            // Sync Images if passed
            if (isset($validated['images'])) {
                DB::table('product_media')->where('product_id', $product->id)->delete();
                foreach ($validated['images'] as $idx => $imageUrl) {
                    if (empty($imageUrl)) continue;
                    $media = Media::create([
                        'provider' => 'cloudinary',
                        'provider_id' => $imageUrl,
                        'type' => 'image',
                        'status' => 'ready',
                        'uploaded_by' => $request->user()?->id,
                    ]);

                    DB::table('product_media')->insert([
                        'product_id' => $product->id,
                        'media_id' => $media->id,
                        'role' => $idx === 0 ? 'primary' : 'gallery',
                        'sort_order' => $idx,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            AuditLog::record('product.updated', $product, $request->user(), $validated);
        });

        return $this->success(
            new ProductResource($product->fresh(['category', 'concern', 'variants', 'media'])),
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
