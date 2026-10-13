<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Traits\ApiResponse;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminArticleController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 50);
        $articles = Article::orderByDesc('created_at')->paginate($perPage);

        return $this->success(
            ArticleResource::collection($articles),
            'Articles retrieved for admin.',
            [
                'total' => $articles->total(),
                'currentPage' => $articles->currentPage(),
                'lastPage' => $articles->lastPage(),
            ]
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:articles,slug',
            'subtitle' => 'nullable|string|max:255',
            'excerpt' => 'required|string',
            'content' => 'required|string',
            'hero_image' => 'required|string',
            'category' => 'required|string',
            'reading_time' => 'nullable|string',
            'author' => 'nullable|string',
            'author_role' => 'nullable|string',
            'author_avatar' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'recommended_product_slugs' => 'nullable|array',
            'published_at' => 'nullable|date',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $validated['published_at'] = $validated['published_at'] ?? now();

        $article = Article::create($validated);

        return $this->success(new ArticleResource($article), 'Article created successfully.', [], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $article = Article::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'slug' => "sometimes|string|max:255|unique:articles,slug,{$id}",
            'subtitle' => 'nullable|string|max:255',
            'excerpt' => 'sometimes|string',
            'content' => 'sometimes|string',
            'hero_image' => 'sometimes|string',
            'category' => 'sometimes|string',
            'reading_time' => 'nullable|string',
            'author' => 'nullable|string',
            'author_role' => 'nullable|string',
            'author_avatar' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'recommended_product_slugs' => 'nullable|array',
            'published_at' => 'nullable|date',
        ]);

        $article->update($validated);

        return $this->success(new ArticleResource($article), 'Article updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $article = Article::findOrFail($id);
        $article->delete();

        return $this->success(null, 'Article deleted successfully.');
    }
}
