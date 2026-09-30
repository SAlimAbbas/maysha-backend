<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the order into the frontend contract shape.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'subtotal' => $this->subtotal_rupees,
            'discount' => $this->discount_rupees,
            'shipping' => $this->shipping_rupees,
            'total' => $this->total_rupees,
            'currency' => $this->currency,
            'couponCode' => $this->coupon_code,
            'paymentMethod' => $this->payment_method,
            'shippingAddress' => $this->shipping_address,
            'items' => $this->items->map(function ($item) {
                return [
                    'id' => (string) $item->id,
                    'productName' => $item->product_name,
                    'productSlug' => $item->product_slug,
                    'size' => $item->size_label,
                    'unitPrice' => $item->unit_price_rupees,
                    'unitMrp' => $item->unit_mrp_rupees,
                    'quantity' => $item->quantity,
                    'total' => $item->total_rupees,
                    'image' => $item->product_image,
                ];
            }),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
