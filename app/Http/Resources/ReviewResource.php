<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * Transform the review model into the exact frontend contract shape.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'author' => $this->author_name,
            'rating' => (int) $this->rating,
            'date' => $this->created_at ? $this->created_at->format('M d, Y') : '',
            'isVerified' => (bool) $this->is_verified,
            'title' => $this->title ?? '',
            'comment' => $this->comment,
            'productId' => (string) $this->product_id,
            'productName' => $this->product?->name ?? '',
            'helpfulCount' => (int) $this->helpful_count,
        ];
    }
}
