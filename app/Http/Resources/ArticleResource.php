<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    /**
     * Transform the article into camelCase JSON for the Next.js storefront.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle ?? '',
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'heroImage' => $this->hero_image,
            'category' => $this->category,
            'readingTime' => $this->reading_time,
            'author' => $this->author,
            'authorRole' => $this->author_role,
            'authorAvatar' => $this->author_avatar,
            'isFeatured' => (bool) $this->is_featured,
            'recommendedProductSlugs' => $this->recommended_product_slugs ?? [],
            'publishedAt' => $this->published_at?->toISOString() ?? $this->created_at->toISOString(),
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
