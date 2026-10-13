<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'excerpt',
        'content',
        'hero_image',
        'category',
        'reading_time',
        'author',
        'author_role',
        'author_avatar',
        'is_featured',
        'recommended_product_slugs',
        'published_at',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'recommended_product_slugs' => 'array',
        'published_at' => 'datetime',
    ];

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
