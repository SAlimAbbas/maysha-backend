<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Http\Traits\ApiResponse;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewHelpfulVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ApiResponse;

    /**
     * Get approved reviews for a specific product or storefront.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Review::approved()->with('product');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        } elseif ($request->filled('product_slug')) {
            $slug = $request->input('product_slug');
            $query->whereHas('product', fn ($q) => $q->where('slug', $slug));
        }

        $reviews = $query->orderBy('helpful_count', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(10);

        return $this->success(
            ReviewResource::collection($reviews->items()),
            'Reviews retrieved successfully.',
            [
                'total' => $reviews->total(),
                'currentPage' => $reviews->currentPage(),
                'lastPage' => $reviews->lastPage(),
            ]
        );
    }

    /**
     * Submit a customer review (requires verified email, 1 review per product per user).
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            return $this->error('Please verify your email address before posting a product review.', 403);
        }

        $validated = $request->validated();
        $productId = $validated['product_id'] ?? null;
        if (! $productId && ! empty($validated['product_slug'])) {
            $productId = Product::where('slug', $validated['product_slug'])->value('id');
        }

        if (! $productId) {
            return $this->error('Product could not be identified.', 422);
        }

        // Enforce 1 review per user per product
        $existing = Review::where('product_id', $productId)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return $this->error('You have already submitted a review for this product.', 409);
        }

        // Check if user has purchased this product and received delivery
        $hasPurchased = false;
        if (method_exists($user, 'orders')) {
            $hasPurchased = $user->orders()
                ->where('status', 'delivered')
                ->whereHas('items.variant', fn ($q) => $q->where('product_id', $productId))
                ->exists();
        }

        if (! $hasPurchased && $user->role !== 'admin') {
            return $this->error('Only verified buyers who have received delivery of this product can submit a review.', 403);
        }

        // Sanitize body/title text to prevent XSS
        $rawComment = $validated['comment'] ?? $validated['body'] ?? '';
        $sanitizedComment = strip_tags($rawComment);
        $sanitizedTitle = isset($validated['title']) ? strip_tags($validated['title']) : null;

        $review = Review::create([
            'product_id' => $productId,
            'user_id' => $user->id,
            'author_name' => $user->name,
            'rating' => $validated['rating'],
            'title' => $sanitizedTitle,
            'comment' => $sanitizedComment,
            'images' => $request->input('images', []),
            'is_verified' => $hasPurchased,
            'is_approved' => false, // Queued for admin moderation
            'helpful_count' => 0,
        ]);

        return $this->success(
            new ReviewResource($review),
            'Thank you! Your review has been submitted and queued for moderation.',
            [],
            201
        );
    }

    /**
     * Cast a helpful vote on a review (prevents duplicate voting by user ID or IP).
     */
    public function voteHelpful(Request $request, int $id): JsonResponse
    {
        $review = Review::approved()->findOrFail($id);
        $user = $request->user();
        $ip = $request->ip();

        $alreadyVoted = ReviewHelpfulVote::where('review_id', $review->id)
            ->where(function ($q) use ($user, $ip) {
                if ($user) {
                    $q->where('user_id', $user->id);
                } else {
                    $q->where('ip', $ip);
                }
            })
            ->exists();

        if ($alreadyVoted) {
            return $this->error('You have already voted on this review.', 409);
        }

        ReviewHelpfulVote::create([
            'review_id' => $review->id,
            'user_id' => $user?->id,
            'ip' => $ip,
        ]);

        $review->increment('helpful_count');

        return $this->success([
            'helpfulCount' => $review->fresh()->helpful_count,
        ], 'Thank you for your feedback!');
    }
}
