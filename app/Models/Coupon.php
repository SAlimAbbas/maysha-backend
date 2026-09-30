<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_subtotal_paise',
        'max_discount_paise',
        'usage_limit',
        'per_user_limit',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'value' => 'integer',
        'min_subtotal_paise' => 'integer',
        'max_discount_paise' => 'integer',
        'usage_limit' => 'integer',
        'per_user_limit' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function redemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * Check if coupon is currently valid for a given subtotal in paise and user.
     */
    public function isValidFor(int $subtotalPaise, ?User $user = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        if ($subtotalPaise < $this->min_subtotal_paise) {
            return false;
        }

        // Global usage limit check
        if ($this->usage_limit && $this->redemptions()->count() >= $this->usage_limit) {
            return false;
        }

        // Per-user usage limit check
        if ($user && $this->per_user_limit) {
            $userRedemptions = $this->redemptions()->where('user_id', $user->id)->count();
            if ($userRedemptions >= $this->per_user_limit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate discount in paise for a given subtotal in paise.
     */
    public function calculateDiscountPaise(int $subtotalPaise): int
    {
        if ($this->type === 'percent') {
            $discount = (int) round(($subtotalPaise * $this->value) / 100);
        } else {
            // Flat discount (value stored in paise or converted)
            $discount = $this->value;
        }

        if ($this->max_discount_paise && $discount > $this->max_discount_paise) {
            $discount = $this->max_discount_paise;
        }

        return min($discount, $subtotalPaise);
    }
}
