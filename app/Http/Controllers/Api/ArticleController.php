<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Traits\ApiResponse;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    use ApiResponse;

    /**
     * List published articles with optional category filtering and search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Article::published();

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $perPage = min((int) $request->input('per_page', 9), 30);
        $articles = $query->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->paginate($perPage);

        return $this->success(
            ArticleResource::collection($articles),
            'Articles retrieved successfully.',
            [
                'total' => $articles->total(),
                'currentPage' => $articles->currentPage(),
                'lastPage' => $articles->lastPage(),
                'perPage' => $articles->perPage(),
            ]
        );
    }

    /**
     * Retrieve a single article by slug along with related articles.
     */
    public function show(string $slug): JsonResponse
    {
        $article = Article::published()->where('slug', $slug)->first();

        if (! $article) {
            return $this->error('Article not found.', 404);
        }

        $related = Article::published()
            ->where('id', '!=', $article->id)
            ->where('category', $article->category)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        if ($related->count() < 3) {
            $additional = Article::published()
                ->where('id', '!=', $article->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->orderByDesc('published_at')
                ->limit(3 - $related->count())
                ->get();
            $related = $related->concat($additional);
        }

        return $this->success([
            'article' => new ArticleResource($article),
            'related' => ArticleResource::collection($related),
        ], 'Article details retrieved successfully.');
    }
}
