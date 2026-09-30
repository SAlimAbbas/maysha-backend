<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\BaseApiRequest;

class StoreReviewRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:150'],
            'comment' => ['required', 'string', 'max:2000'],
        ];
    }
}
