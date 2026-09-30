<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the category model into the exact frontend contract shape.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'subtitle' => $this->subtitle ?? '',
            'image' => $this->image ?? '',
            'itemCount' => (int) ($this->products_count ?? $this->products()->count()),
        ];
    }
}
