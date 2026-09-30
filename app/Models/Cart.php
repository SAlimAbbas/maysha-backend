<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'user_id',
        'coupon_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Compute current cart summary (strictly server-side using current DB prices and stock).
     */
    public function computeSummary(?User $user = null): array
    {
        $this->loadMissing(['items.variant.product', 'coupon']);

        $user = $user ?: $this->user;
        $itemsData = [];
        $subtotalPaise = 0;
        $warnings = [];

        foreach ($this->items as $item) {
            $variant = $item->variant;
            if (! $variant || ! $variant->is_active || ! $variant->product || ! $variant->product->is_active) {
                $warnings[] = 'An item in your cart is no longer available.';

                continue;
            }

            // Adjust quantity if stock is lower
            $qty = $item->quantity;
            if ($qty > $variant->stock) {
                $qty = max(1, $variant->stock);
                $warnings[] = "Quantity for {$variant->product->name} ({$variant->size_label}) was adjusted to available stock ({$variant->stock}).";
            }

            $lineTotalPaise = $variant->price_paise * $qty;
            $subtotalPaise += $lineTotalPaise;

            // Product image resolution
            $image = 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80';
            if ($variant->product->media && $variant->product->media->isNotEmpty()) {
                $image = $variant->product->media->first()->getDeliveryUrl('card');
            }

            $itemsData[] = [
                'id' => (string) $item->id,
                'variantId' => (string) $variant->id,
                'productId' => (string) $variant->product->id,
                'productSlug' => $variant->product->slug,
                'name' => $variant->product->name,
                'size' => $variant->size_label,
                'price' => $variant->price_rupees,
                'mrp' => $variant->mrp_rupees,
                'quantity' => $qty,
                'image' => $image,
                'lineTotal' => (int) round($lineTotalPaise / 100),
                'stock' => $variant->stock,
            ];
        }

        // Coupon calculation
        $discountPaise = 0;
        $couponCode = null;
        if ($this->coupon) {
            if ($this->coupon->isValidFor($subtotalPaise, $user)) {
                $discountPaise = $this->coupon->calculateDiscountPaise($subtotalPaise);
                $couponCode = $this->coupon->code;
            } else {
                $warnings[] = "Coupon {$this->coupon->code} is no longer applicable to your cart.";
                $this->update(['coupon_id' => null]);
            }
        }

        // Free shipping threshold check (e.g. ₹999 = 99900 paise)
        $thresholdPaise = (int) env('FREE_SHIPPING_THRESHOLD', 999) * 100;
        $shippingFeePaise = (int) env('SHIPPING_FEE', 99) * 100;

        $shippingPaise = ($subtotalPaise >= $thresholdPaise || $subtotalPaise === 0) ? 0 : $shippingFeePaise;
        $totalPaise = max(0, $subtotalPaise - $discountPaise + $shippingPaise);

        return [
            'items' => $itemsData,
            'subtotal' => (int) round($subtotalPaise / 100),
            'discount' => (int) round($discountPaise / 100),
            'shipping' => (int) round($shippingPaise / 100),
            'total' => (int) round($totalPaise / 100),
            'subtotalPaise' => $subtotalPaise,
            'discountPaise' => $discountPaise,
            'shippingPaise' => $shippingPaise,
            'totalPaise' => $totalPaise,
            'couponCode' => $couponCode,
            'warnings' => $warnings,
            'itemCount' => array_sum(array_column($itemsData, 'quantity')),
        ];
    }

    /**
     * Locate or create a cart by token/user.
     */
    public static function findOrCreateCurrent(string $token, ?User $user = null): self
    {
        if ($user) {
            $cart = self::where('user_id', $user->id)->first();
            if ($cart) {
                return $cart;
            }
        }

        return self::firstOrCreate(
            ['token' => $token],
            [
                'user_id' => $user?->id,
                'expires_at' => now()->addDays(14),
            ]
        );
    }
}
