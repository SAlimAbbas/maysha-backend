<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reviews = [
            [
                'id' => 'rev-1',
                'author' => 'Ananya S.',
                'is_verified' => true,
                'rating' => 5,
                'date' => '14 Oct 2025',
                'title' => 'Best gentle cleanser I have ever tried!',
                'comment' => 'Most cleansers leave my skin tight, but Maysha Gentle Face Cleanser leaves it velvety soft.',
                'product_name' => 'Gentle Face Cleanser'
            ],
            [
                'id' => 'rev-2',
                'author' => 'Rohan M.',
                'is_verified' => true,
                'rating' => 5,
                'date' => '02 Nov 2025',
                'title' => 'Visible glow within 2 weeks',
                'comment' => 'The 10% Vitamin C serum is top tier. Doesn\'t irritate my skin and faded old post-acne dark marks.',
                'product_name' => 'Vitamin C Serum'
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'author' => 'required|string|max:100',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'required|string|max:150',
            'comment' => 'required|string',
            'product_id' => 'required|string',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully and queued for moderation.',
            'data' => $validated
        ], 201);
    }
}
