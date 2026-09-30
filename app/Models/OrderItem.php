<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'variant_id',
        'product_name',
        'product_slug',
        'size_label',
        'unit_price_paise',
        'unit_mrp_paise',
        'quantity',
        'total_paise',
        'product_image',
    ];

    protected $casts = [
        'unit_price_paise' => 'integer',
        'unit_mrp_paise' => 'integer',
        'quantity' => 'integer',
        'total_paise' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function getUnitPriceRupeesAttribute(): int
    {
        return (int) round($this->unit_price_paise / 100);
    }

    public function getUnitMrpRupeesAttribute(): int
    {
        return (int) round($this->unit_mrp_paise / 100);
    }

    public function getTotalRupeesAttribute(): int
    {
        return (int) round($this->total_paise / 100);
    }
}
