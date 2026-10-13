<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\Category;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    use ApiResponse;

    /**
     * List all categories including product count and admin attributes.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::withCount('products')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $transformed = $categories->map(function ($c) {
            return [
                'id' => (string) $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'subtitle' => $c->subtitle ?? '',
                'description' => $c->subtitle ?? '',
                'image' => $c->image ?? '',
                'sortOrder' => (int) $c->sort_order,
                'isActive' => (bool) $c->is_active,
                'itemCount' => (int) $c->products_count,
                'seoTitle' => $c->seo_title ?? '',
                'seoDescription' => $c->seo_description ?? '',
            ];
        });

        return $this->success($transformed, 'Categories retrieved successfully.');
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:categories,slug',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'image' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
        ]);

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);
        if (empty($slug)) {
            $slug = 'category-' . time();
        }

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'subtitle' => $validated['description'] ?? $validated['subtitle'] ?? null,
            'image' => $validated['image'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
        ]);

        CatalogCacheService::flush(['categories', 'catalog']);
        AuditLog::record('category.created', $category, $request->user(), ['name' => $category->name]);

        return $this->success($category, "Category '{$category->name}' created successfully.", [], 201);
    }

    /**
     * Update an existing category.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'slug' => "sometimes|nullable|string|max:100|unique:categories,slug,{$id}",
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'image' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
        ]);

        if (isset($validated['slug']) && !empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['slug']);
        } elseif (isset($validated['name']) && empty($category->slug)) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        if (isset($validated['description'])) {
            $validated['subtitle'] = $validated['description'];
        }

        $category->update($validated);

        CatalogCacheService::flush(['categories', 'catalog']);
        AuditLog::record('category.updated', $category, $request->user(), ['name' => $category->name]);

        return $this->success($category, "Category '{$category->name}' updated successfully.");
    }

    /**
     * Delete a category.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $category = Category::withCount('products')->findOrFail($id);

        if ($category->products_count > 0) {
            return $this->error(
                "Cannot delete category '{$category->name}' because it contains {$category->products_count} active product(s). Please reassign or delete the products first.",
                422
            );
        }

        $name = $category->name;
        $category->delete();

        CatalogCacheService::flush(['categories', 'catalog']);
        AuditLog::record('category.deleted', null, $request->user(), ['name' => $name]);

        return $this->success(null, "Category '{$name}' deleted successfully.");
    }
}
