<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'subtitle',
        'description',
        'category_id',
        'concern_id',
        'badge',
        'key_benefits',
        'ingredients',
        'usage_instructions',
        'skin_types',
        'is_featured',
        'is_active',
        'rating_avg',
        'review_count',
    ];

    protected $casts = [
        'key_benefits' => 'array',
        'skin_types' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'rating_avg' => 'float',
        'review_count' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function concern()
    {
        return $this->belongsTo(Concern::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function media()
    {
        return $this->belongsToMany(Media::class, 'product_media')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
