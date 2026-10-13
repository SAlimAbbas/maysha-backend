<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_published_articles(): void
    {
        Article::create([
            'slug' => 'test-article-1',
            'title' => 'Test Article 1',
            'excerpt' => 'Test excerpt',
            'content' => 'Test content body',
            'hero_image' => 'https://example.com/image.jpg',
            'category' => 'Skincare Science',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/articles');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'title',
                        'excerpt',
                        'content',
                        'heroImage',
                        'category',
                        'readingTime',
                        'author',
                    ],
                ],
                'meta' => ['total', 'currentPage', 'lastPage', 'perPage'],
            ]);
    }

    public function test_can_fetch_single_article_by_slug(): void
    {
        $article = Article::create([
            'slug' => 'test-article-single',
            'title' => 'Single Article Test',
            'excerpt' => 'Test excerpt',
            'content' => 'Test content body',
            'hero_image' => 'https://example.com/image.jpg',
            'category' => 'Skincare Science',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/articles/test-article-single');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.article.slug', 'test-article-single')
            ->assertJsonStructure([
                'data' => [
                    'article' => ['id', 'slug', 'title', 'content'],
                    'related',
                ],
            ]);
    }
}
