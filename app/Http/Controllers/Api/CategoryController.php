<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = [
            ['id' => 'cat-1', 'slug' => 'cleansers', 'name' => 'Cleansers', 'item_count' => 4],
            ['id' => 'cat-2', 'slug' => 'toners', 'name' => 'Toners', 'item_count' => 3],
            ['id' => 'cat-3', 'slug' => 'serums', 'name' => 'Serums', 'item_count' => 6],
            ['id' => 'cat-4', 'slug' => 'moisturizers', 'name' => 'Moisturizers', 'item_count' => 5],
            ['id' => 'cat-5', 'slug' => 'sunscreen', 'name' => 'Sunscreen', 'item_count' => 2],
        ];

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    public function concerns(): JsonResponse
    {
        $concerns = [
            ['id' => 'con-1', 'slug' => 'uneven-tone', 'name' => 'Uneven Tone'],
            ['id' => 'con-2', 'slug' => 'acne-control', 'name' => 'Acne Control'],
            ['id' => 'con-3', 'slug' => 'barrier-repair', 'name' => 'Barrier Repair'],
            ['id' => 'con-4', 'slug' => 'dehydration-dryness', 'name' => 'Dehydration & Dryness'],
        ];

        return response()->json([
            'success' => true,
            'data' => $concerns
        ]);
    }
}
