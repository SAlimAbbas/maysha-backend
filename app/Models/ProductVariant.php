<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'size_label',
        'price_paise',
        'mrp_paise',
        'stock',
        'low_stock_threshold',
        'is_active',
        'weight_grams',
        'length_cm',
        'breadth_cm',
        'height_cm',
    ];

    protected $casts = [
        'price_paise' => 'integer',
        'mrp_paise' => 'integer',
        'stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'is_active' => 'boolean',
        'weight_grams' => 'integer',
        'length_cm' => 'decimal:2',
        'breadth_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getPriceRupeesAttribute(): int
    {
        return (int) round($this->price_paise / 100);
    }

    public function getMrpRupeesAttribute(): int
    {
        return (int) round($this->mrp_paise / 100);
    }

    public function getInStockAttribute(): bool
    {
        return $this->is_active && $this->stock > 0;
    }

    public function getEffectiveWeightGramsAttribute(): int
    {
        return (int) ($this->weight_grams ?? SiteSetting::get('default_weight_grams', 250));
    }

    public function getEffectiveLengthCmAttribute(): float
    {
        return (float) ($this->length_cm ?? SiteSetting::get('default_length_cm', 10));
    }

    public function getEffectiveBreadthCmAttribute(): float
    {
        return (float) ($this->breadth_cm ?? SiteSetting::get('default_breadth_cm', 10));
    }

    public function getEffectiveHeightCmAttribute(): float
    {
        return (float) ($this->height_cm ?? SiteSetting::get('default_height_cm', 10));
    }
}
