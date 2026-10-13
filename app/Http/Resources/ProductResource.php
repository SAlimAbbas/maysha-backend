<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the product model into the exact camelCase contract expected by the Next.js frontend.
     */
    public function toArray(Request $request): array
    {
        $primaryVariant = $this->variants->first();

        $price = $primaryVariant ? $primaryVariant->price_rupees : 0;
        $mrp = $primaryVariant ? $primaryVariant->mrp_rupees : 0;
        $discountPercentage = ($mrp > $price && $mrp > 0)
            ? (int) round((($mrp - $price) / $mrp) * 100)
            : 0;

        // Resolve images: Cloudinary first, then local client product images, then safe fallbacks
        $images = [];
        if ($this->relationLoaded('media') && $this->media->isNotEmpty()) {
            foreach ($this->media as $mediaItem) {
                $deliveryUrl = $mediaItem->getDeliveryUrl('gallery');
                if (!empty($deliveryUrl)) {
                    $images[] = $deliveryUrl;
                }
            }
        }

        // If no media pivot records exist, check for known local client product assets
        if (empty($images)) {
            $slug = $this->slug ?? '';
            if (str_contains($slug, 'face-wash')) {
                $images = [
                    '/images/products/face-wash/face-wash-1.png',
                    '/images/products/face-wash/face-wash-2.png',
                    '/images/products/face-wash/face-wash-3.png',
                    '/images/products/face-wash/face-wash-4.png',
                ];
            } elseif (str_contains($slug, 'hair-oil') || str_contains($slug, 'rosemary')) {
                $images = [
                    '/images/products/rosemary-hair-oil/hair-oil-1.png',
                    '/images/products/rosemary-hair-oil/hair-oil-2.png',
                    '/images/products/rosemary-hair-oil/hair-oil-4.png',
                    '/images/products/rosemary-hair-oil/hair-oil-3.png',
                ];
            } elseif (str_contains($slug, 'air-freshener') || str_contains($slug, 'freshener')) {
                $images = [
                    '/images/products/rose-air-freshener/air-freshener-1.png',
                    '/images/products/rose-air-freshener/air-freshener-2.png',
                ];
            }
        }

        // Final fallback if no images found
        if (empty($images)) {
            $images = [
                '/logo.png',
            ];
        }

        $hoverImage = count($images) > 1 ? $images[1] : $images[0];

        $variants = $this->variants->map(function ($v) {
            return [
                'id' => (string) $v->id,
                'size' => $v->size_label,
                'price' => $v->price_rupees,
                'mrp' => $v->mrp_rupees,
                'inStock' => (bool) $v->in_stock,
            ];
        })->values()->toArray();

        return [
            'id' => (string) $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'subtitle' => $this->subtitle ?? '',
            'category' => $this->category?->name ?? '',
            'categorySlug' => $this->category?->slug ?? '',
            'concern' => $this->concern?->name ?? '',
            'concernSlug' => $this->concern?->slug ?? '',
            'price' => $price,
            'mrp' => $mrp,
            'discountPercentage' => $discountPercentage,
            'size' => $primaryVariant ? $primaryVariant->size_label : '',
            'rating' => (float) $this->rating_avg,
            'reviewCount' => (int) $this->review_count,
            'badge' => $this->badge,
            'inStock' => $primaryVariant ? (bool) $primaryVariant->in_stock : false,
            'images' => $images,
            'hoverImage' => $hoverImage,
            'variants' => $variants,
            'keyBenefits' => $this->key_benefits ?? [],
            'ingredients' => $this->ingredients ?? '',
            'usageInstructions' => $this->usage_instructions ?? '',
            'description' => $this->description ?? '',
            'skinTypes' => $this->skin_types ?? ['All Skin Types'],
            'isFeatured' => (bool) $this->is_featured,
        ];
    }
}
