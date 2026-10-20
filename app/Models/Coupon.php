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
        'first_order_only',
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
        'first_order_only' => 'boolean',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function redemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * Check if coupon is currently valid for a given subtotal in paise, user, or guest credentials.
     */
    public function isValidFor(int $subtotalPaise, ?User $user = null, ?string $guestEmail = null, ?string $guestPhone = null): bool
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

        // First order only check (prevents both customer & guest coupon re-use)
        if ($this->first_order_only) {
            if ($user) {
                if ($user->orders()->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])->exists()) {
                    return false;
                }
            } elseif ($guestEmail || $guestPhone) {
                $hasPriorOrders = \App\Models\Order::query()
                    ->where(function ($q) use ($guestEmail, $guestPhone) {
                        if ($guestEmail) {
                            $q->where('guest_email', $guestEmail);
                        }
                        if ($guestPhone) {
                            $q->orWhere('shipping_address->phone', $guestPhone);
                        }
                    })
                    ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
                    ->exists();

                if ($hasPriorOrders) {
                    return false;
                }
            }
        }

        // Global usage limit check
        if ($this->usage_limit && $this->redemptions()->count() >= $this->usage_limit) {
            return false;
        }

        // Per-user / per-guest usage limit check
        if ($this->per_user_limit) {
            if ($user) {
                $userRedemptions = $this->redemptions()->where('user_id', $user->id)->count();
                if ($userRedemptions >= $this->per_user_limit) {
                    return false;
                }
            } elseif ($guestEmail || $guestPhone) {
                $guestRedemptions = $this->redemptions()
                    ->whereHas('order', function ($q) use ($guestEmail, $guestPhone) {
                        $q->where(function ($inner) use ($guestEmail, $guestPhone) {
                            if ($guestEmail) {
                                $inner->where('guest_email', $guestEmail);
                            }
                            if ($guestPhone) {
                                $inner->orWhere('shipping_address->phone', $guestPhone);
                            }
                        })->whereIn('status', ['paid', 'processing', 'shipped', 'delivered']);
                    })
                    ->count();

                if ($guestRedemptions >= $this->per_user_limit) {
                    return false;
                }
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
