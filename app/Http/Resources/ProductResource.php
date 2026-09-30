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

        // Resolve images from product_media or fallback attributes
        $images = [];
        if ($this->relationLoaded('media') && $this->media->isNotEmpty()) {
            foreach ($this->media as $mediaItem) {
                $images[] = $mediaItem->getDeliveryUrl('gallery');
            }
        }

        // If no media pivot records exist, provide default clean product images
        if (empty($images)) {
            $images = [
                'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=800&q=85',
                'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=800&q=85',
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
