<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ConcernResource;
use App\Http\Traits\ApiResponse;
use App\Models\Category;
use App\Models\Concern;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ApiResponse;

    /**
     * Get all active categories with product counts.
     */
    public function categories(): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order', 'asc')
            ->get();

        return $this->success(
            CategoryResource::collection($categories),
            'Categories retrieved successfully.'
        );
    }

    /**
     * Get all active skin concerns.
     */
    public function concerns(): JsonResponse
    {
        $concerns = Concern::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        return $this->success(
            ConcernResource::collection($concerns),
            'Concerns retrieved successfully.'
        );
    }
}
