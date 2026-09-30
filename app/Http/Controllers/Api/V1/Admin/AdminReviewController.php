<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReviewController extends Controller
{
    use ApiResponse;

    /**
     * List all reviews for moderation.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Review::with('product');

        if ($request->has('approved')) {
            $query->where('is_approved', $request->boolean('approved'));
        }

        $reviews = $query->orderBy('id', 'desc')->paginate(20);

        return $this->success(
            ReviewResource::collection($reviews->items()),
            'Reviews retrieved for moderation.',
            [
                'total' => $reviews->total(),
                'currentPage' => $reviews->currentPage(),
                'lastPage' => $reviews->lastPage(),
            ]
        );
    }

    /**
     * Approve a review and recalculate product rating & count.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $review = Review::findOrFail($id);

        DB::transaction(function () use ($review, $request) {
            $review->update(['is_approved' => true]);

            // Recalculate product rating statistics
            $product = $review->product;
            if ($product) {
                $approvedReviews = $product->reviews()->where('is_approved', true);
                $avg = $approvedReviews->avg('rating') ?: 5.0;
                $count = $approvedReviews->count();

                $product->update([
                    'rating_avg' => round($avg, 2),
                    'review_count' => $count,
                ]);
            }

            AuditLog::record('review.approved', $review, $request->user());
        });

        return $this->success(
            new ReviewResource($review->fresh('product')),
            'Review approved and product ratings updated.'
        );
    }

    /**
     * Delete an inappropriate review.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $review = Review::findOrFail($id);

        DB::transaction(function () use ($review, $request) {
            $product = $review->product;
            $review->delete();

            if ($product) {
                $approvedReviews = $product->reviews()->where('is_approved', true);
                $avg = $approvedReviews->avg('rating') ?: 5.0;
                $count = $approvedReviews->count();

                $product->update([
                    'rating_avg' => round($avg, 2),
                    'review_count' => $count,
                ]);
            }

            AuditLog::record('review.deleted', null, $request->user(), ['review_id' => $id]);
        });

        return $this->success(null, 'Review deleted successfully.');
    }
}
