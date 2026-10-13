<?php

namespace App\Observers;

use App\Models\Review;
use App\Services\CatalogCacheService;

class ReviewObserver
{
    public function saved(Review $review): void
    {
        $this->recalculateProductRating($review);
        CatalogCacheService::invalidateProducts();
    }

    public function deleted(Review $review): void
    {
        $this->recalculateProductRating($review);
        CatalogCacheService::invalidateProducts();
    }

    protected function recalculateProductRating(Review $review): void
    {
        $product = $review->product;
        if (! $product) {
            return;
        }

        $approvedReviews = $product->reviews()->where('is_approved', true);
        $count = $approvedReviews->count();
        $avg = $count > 0 ? (float) $approvedReviews->avg('rating') : 5.0;

        $product->updateQuietly([
            'rating_avg' => round($avg, 2),
            'review_count' => $count,
        ]);
    }
}
