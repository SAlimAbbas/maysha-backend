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
        $paymentStatus = match ($this->status) {
            'paid', 'processing', 'shipped', 'delivered' => 'confirmed',
            'cancelled', 'failed' => 'cancelled',
            default => ($this->payment_method === 'cod' ? 'confirmed' : 'pending'),
        };

        $trackingUrl = null;
        if ($this->tracking_no) {
            if ($this->courier === 'Delhivery') {
                $trackingUrl = 'https://www.delhivery.com/track/package/'.$this->tracking_no;
            } elseif ($this->courier === 'BlueDart') {
                $trackingUrl = 'https://www.bluedart.com/tracking/'.$this->tracking_no;
            } else {
                $trackingUrl = 'https://shiprocket.co/tracking/'.$this->tracking_no;
            }
        }

        return [
            'id' => (string) $this->id,
            'reference' => $this->reference,
            'invoiceNo' => $this->invoice_no,
            'status' => $this->status,
            'paymentStatus' => $paymentStatus,
            'subtotal' => $this->subtotal_rupees,
            'discount' => $this->discount_rupees,
            'shipping' => $this->shipping_rupees,
            'codFee' => $this->cod_fee_rupees,
            'tax' => $this->tax_rupees,
            'total' => $this->total_rupees,
            'currency' => $this->currency,
            'couponCode' => $this->coupon_code,
            'paymentMethod' => $this->payment_method,
            'shippingAddress' => $this->shipping_address,
            'tracking' => [
                'courier' => $this->courier,
                'trackingNo' => $this->tracking_no,
                'url' => $trackingUrl,
            ],
            'items' => $this->items->map(function ($item) {
                return [
                    'id' => (string) $item->id,
                    'productId' => (string) ($item->variant?->product_id ?? ''),
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
            'events' => $this->relationLoaded('events') ? $this->events->map(function ($event) {
                return [
                    'id' => (string) $event->id,
                    'status' => $event->status,
                    'note' => $event->note,
                    'createdAt' => $event->created_at?->toIso8601String(),
                ];
            }) : [],
            'shipment' => $this->shipment ? [
                'id' => (string) $this->shipment->id,
                'courierName' => $this->shipment->courier_name,
                'courierId' => $this->shipment->courier_id,
                'awb' => $this->shipment->awb_code,
                'trackingUrl' => $this->shipment->tracking_url ?: $trackingUrl,
                'status' => $this->shipment->status,
                'srStatus' => $this->shipment->sr_status,
                'labelUrl' => ($request->user() && method_exists($request->user(), 'isAdmin') && $request->user()->isAdmin()) ? $this->shipment->label_url : null,
                'manifestUrl' => ($request->user() && method_exists($request->user(), 'isAdmin') && $request->user()->isAdmin()) ? $this->shipment->manifest_url : null,
                'invoiceUrl' => $this->shipment->invoice_url,
                'weightGrams' => $this->shipment->weight_grams,
                'lengthCm' => $this->shipment->length_cm,
                'breadthCm' => $this->shipment->breadth_cm,
                'heightCm' => $this->shipment->height_cm,
                'pickupScheduledAt' => $this->shipment->pickup_scheduled_at?->toIso8601String(),
                'lastSyncedAt' => $this->shipment->last_synced_at?->toIso8601String(),
                'events' => $this->shipment->relationLoaded('events') ? $this->shipment->events->map(function ($ev) {
                    return [
                        'status' => $ev->status,
                        'srStatus' => $ev->sr_status,
                        'location' => $ev->location,
                        'note' => $ev->note,
                        'occurredAt' => $ev->occurred_at?->toIso8601String(),
                    ];
                }) : [],
            ] : null,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
