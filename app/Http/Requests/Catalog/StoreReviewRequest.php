<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\BaseApiRequest;

class StoreReviewRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'exists:products,id'],
            'product_slug' => ['nullable', 'string', 'exists:products,slug'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:150'],
            'comment' => ['required', 'string', 'max:2000'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string', 'max:500'],
        ];
    }
}
