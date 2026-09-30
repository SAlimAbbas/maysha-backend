<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'idempotency_key',
        'user_id',
        'guest_email',
        'guest_phone',
        'status',
        'subtotal_paise',
        'discount_paise',
        'shipping_paise',
        'total_paise',
        'currency',
        'coupon_code',
        'shipping_address',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'subtotal_paise' => 'integer',
        'discount_paise' => 'integer',
        'shipping_paise' => 'integer',
        'total_paise' => 'integer',
        'shipping_address' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getSubtotalRupeesAttribute(): int
    {
        return (int) round($this->subtotal_paise / 100);
    }

    public function getDiscountRupeesAttribute(): int
    {
        return (int) round($this->discount_paise / 100);
    }

    public function getShippingRupeesAttribute(): int
    {
        return (int) round($this->shipping_paise / 100);
    }

    public function getTotalRupeesAttribute(): int
    {
        return (int) round($this->total_paise / 100);
    }

    /**
     * Generate unique reference: MAY-XXXXXX
     */
    public static function generateReference(): string
    {
        do {
            $ref = 'MAY-'.strtoupper(Str::random(6));
        } while (self::where('reference', $ref)->exists());

        return $ref;
    }
}
