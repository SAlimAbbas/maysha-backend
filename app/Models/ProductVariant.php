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
        'is_active',
    ];

    protected $casts = [
        'price_paise' => 'integer',
        'mrp_paise' => 'integer',
        'stock' => 'integer',
        'is_active' => 'boolean',
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
}
