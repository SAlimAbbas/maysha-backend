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
     * Get descriptive ineligibility error message if coupon cannot be applied, or null if valid.
     */
    public function getIneligibilityReason(int $subtotalPaise, ?User $user = null, ?string $guestEmail = null, ?string $guestPhone = null): ?string
    {
        if (! $this->is_active) {
            return "Coupon '{$this->code}' is currently inactive or disabled.";
        }

        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return "Coupon '{$this->code}' will become active on {$this->starts_at->format('d M Y')}.";
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return "Coupon '{$this->code}' expired on {$this->ends_at->format('d M Y')}.";
        }

        if ($subtotalPaise < $this->min_subtotal_paise) {
            $minRupees = (int) round($this->min_subtotal_paise / 100);
            $shortfall = (int) ceil(($this->min_subtotal_paise - $subtotalPaise) / 100);
            return "Minimum order value of ₹{$minRupees} required for '{$this->code}'. Add items worth ₹{$shortfall} more to your cart to use this coupon.";
        }

        // First order only check (prevents both customer & guest coupon re-use)
        if ($this->first_order_only) {
            if ($user) {
                if ($user->orders()->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])->exists()) {
                    return "Coupon '{$this->code}' is reserved for first-time customers only.";
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
                    return "Coupon '{$this->code}' is reserved for first-time customers only.";
                }
            }
        }

        // Global usage limit check
        if ($this->usage_limit && $this->redemptions()->count() >= $this->usage_limit) {
            return "Coupon '{$this->code}' has reached its maximum redemption limit.";
        }

        // Per-user / per-guest usage limit check
        if ($this->per_user_limit) {
            if ($user) {
                $userRedemptions = $this->redemptions()->where('user_id', $user->id)->count();
                if ($userRedemptions >= $this->per_user_limit) {
                    return "You have already redeemed coupon '{$this->code}' the maximum allowed number of times.";
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
                    return "You have already redeemed coupon '{$this->code}' the maximum allowed number of times.";
                }
            }
        }

        return null;
    }

    /**
     * Check if coupon is currently valid for a given subtotal in paise, user, or guest credentials.
     */
    public function isValidFor(int $subtotalPaise, ?User $user = null, ?string $guestEmail = null, ?string $guestPhone = null): bool
    {
        return $this->getIneligibilityReason($subtotalPaise, $user, $guestEmail, $guestPhone) === null;
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
