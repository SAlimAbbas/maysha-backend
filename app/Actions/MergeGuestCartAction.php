<?php

namespace App\Actions;

use App\Models\Cart;
use App\Models\User;

class MergeGuestCartAction
{
    /**
     * Merge a guest token cart into the authenticated user's cart.
     */
    public function execute(string $guestToken, User $user): Cart
    {
        $guestCart = Cart::where('token', $guestToken)->whereNull('user_id')->first();
        $userCart = Cart::firstOrCreate(['user_id' => $user->id], [
            'token' => 'usr_'.bin2hex(random_bytes(16)),
            'expires_at' => now()->addDays(30),
        ]);

        if ($guestCart) {
            foreach ($guestCart->items as $guestItem) {
                $variant = $guestItem->variant;
                $maxStock = $variant ? min(10, (int) $variant->stock) : 10;
                $existingItem = $userCart->items()->where('variant_id', $guestItem->variant_id)->first();
                if ($existingItem) {
                    $newQty = min($maxStock, $existingItem->quantity + $guestItem->quantity);
                    $existingItem->update(['quantity' => $newQty]);
                } else {
                    $qty = min($maxStock, $guestItem->quantity);
                    $userCart->items()->create([
                        'variant_id' => $guestItem->variant_id,
                        'quantity' => max(1, $qty),
                    ]);
                }
            }

            // Transfer coupon if user cart doesn't have one
            if ($guestCart->coupon_id && ! $userCart->coupon_id) {
                $userCart->update(['coupon_id' => $guestCart->coupon_id]);
            }

            $guestCart->delete();
        }

        return $userCart;
    }
}
