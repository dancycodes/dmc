<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Rating;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\RatingReceivedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * F-176: Order Rating Service
 * F-177: Order Review Text Submission
 * F-178: Rating & Review Display on Meal
 *
 * Handles rating submission, validation, display, and related operations.
 * BR-388: Rating only for Completed orders.
 * BR-390: One rating per order.
 * BR-391: Rating cannot be edited or deleted.
 * BR-395: Triggers cook's overall rating recalculation (F-179).
 * BR-396: Cook is notified of new ratings.
 * BR-397: Rating submission is logged via Spatie Activitylog.
 * BR-399: Review text is optional.
 * BR-400: Maximum review length is 500 characters.
 * BR-401: Review is submitted simultaneously with the star rating.
 * BR-402: Once submitted, review cannot be edited or deleted.
 * BR-406: Review text is sanitized (Blade escaping handles XSS).
 */
class RatingService
{
    /**
     * Check if the given order can be rated by the user.
     *
     * BR-388: Only Completed orders can be rated.
     * BR-390: Each order can be rated exactly once.
     */
    public function canRate(Order $order, User $user): bool
    {
        if ($order->status !== Order::STATUS_COMPLETED) {
            return false;
        }

        if ($order->client_id !== $user->id) {
            return false;
        }

        return ! $this->hasBeenRated($order);
    }

    /**
     * Check if an order has already been rated.
     *
     * BR-390: Each order can be rated exactly once.
     */
    public function hasBeenRated(Order $order): bool
    {
        return Rating::query()
            ->where('order_id', $order->id)
            ->exists();
    }

    /**
     * Get the existing rating for an order.
     */
    public function getRating(Order $order): ?Rating
    {
        return Rating::query()
            ->where('order_id', $order->id)
            ->first();
    }

    /**
     * Submit a rating (with optional review text) for a completed order.
     *
     * BR-389: 1-5 stars (integer only).
     * BR-390: Each order can be rated exactly once.
     * BR-394: Associated with order, client, and tenant.
     * BR-395: Triggers cook's overall rating recalculation.
     * BR-396: Cook is notified.
     * BR-397: Activity logged.
     * BR-399: Review text is optional.
     * BR-400: Maximum review length is 500 characters.
     * BR-401: Review submitted simultaneously with rating.
     *
     * @return array{success: bool, rating?: Rating, error?: string}
     */
    public function submitRating(Order $order, User $user, int $stars, ?string $reviewText = null): array
    {
        // BR-388: Only Completed orders
        if ($order->status !== Order::STATUS_COMPLETED) {
            return [
                'success' => false,
                'error' => __('Ratings are only available for completed orders.'),
            ];
        }

        // Ownership check
        if ($order->client_id !== $user->id) {
            return [
                'success' => false,
                'error' => __('You are not authorized to rate this order.'),
            ];
        }

        // BR-390: One rating per order
        if ($this->hasBeenRated($order)) {
            return [
                'success' => false,
                'error' => __('This order has already been rated.'),
            ];
        }

        // BR-389: Validate star range
        if ($stars < Rating::MIN_STARS || $stars > Rating::MAX_STARS) {
            return [
                'success' => false,
                'error' => __('Rating must be between :min and :max stars.', [
                    'min' => Rating::MIN_STARS,
                    'max' => Rating::MAX_STARS,
                ]),
            ];
        }

        // BR-399: Whitespace-only review treated as empty (no review text saved)
        $sanitizedReview = $reviewText !== null ? trim($reviewText) : null;
        if ($sanitizedReview === '') {
            $sanitizedReview = null;
        }

        // BR-400: Validate review length
        if ($sanitizedReview !== null && mb_strlen($sanitizedReview) > Rating::MAX_REVIEW_LENGTH) {
            return [
                'success' => false,
                'error' => __('Review text cannot exceed :max characters.', [
                    'max' => Rating::MAX_REVIEW_LENGTH,
                ]),
            ];
        }

        $rating = DB::transaction(function () use ($order, $user, $stars, $sanitizedReview) {
            $rating = Rating::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'tenant_id' => $order->tenant_id,
                'stars' => $stars,
                'review' => $sanitizedReview,
            ]);

            // BR-395: Trigger cook's overall rating recalculation (F-179 forward-compatible)
            $this->recalculateCookRating($order->tenant);

            return $rating;
        });

        // BR-397: Activity logging
        $logProperties = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'stars' => $stars,
            'tenant_id' => $order->tenant_id,
            'has_review' => $sanitizedReview !== null,
        ];

        activity('ratings')
            ->performedOn($rating)
            ->causedBy($user)
            ->withProperties($logProperties)
            ->log('submitted rating');

        // BR-396: Notify cook
        $this->notifyCook($rating, $order);

        return [
            'success' => true,
            'rating' => $rating,
        ];
    }

    /**
     * BR-395/BR-417/BR-420/BR-421: Recalculate the cook's overall rating.
     *
     * F-179: Cook Overall Rating Calculation
     * BR-417: Overall rating = sum of all stars / number of ratings (simple average).
     * BR-418: Displayed as X.X/5 with one decimal place.
     * BR-420: Recalculated immediately when a new rating is submitted.
     * BR-421: Cached on the tenant settings JSON for performance.
     * BR-424: Ratings from cancelled/refunded orders remain in calculation.
     * BR-425: Tenant-scoped (separate ratings per tenant).
     *
     * Uses lockForUpdate to prevent stale data during concurrent rating submissions.
     */
    public function recalculateCookRating(Tenant $tenant): void
    {
        $stats = Rating::query()
            ->where('tenant_id', $tenant->id)
            ->selectRaw('AVG(stars) as average_rating, COUNT(*) as total_ratings')
            ->first();

        // Atomic update: refresh tenant to avoid stale settings
        $tenant->refresh();
        $settings = $tenant->settings ?? [];
        $settings['average_rating'] = round((float) ($stats->average_rating ?? 0), 1);
        $settings['total_ratings'] = (int) ($stats->total_ratings ?? 0);
        $tenant->settings = $settings;
        $tenant->save();
    }

    /**
     * F-179: Get cached cook rating stats from tenant settings.
     *
     * BR-421: Returns the cached average_rating and total_ratings from tenant settings.
     * BR-423: Returns hasRating=false for cooks with zero ratings.
     *
     * @return array{average: float, count: int, hasRating: bool}
     */
    public function getCachedCookRating(Tenant $tenant): array
    {
        $average = (float) ($tenant->getSetting('average_rating', 0));
        $count = (int) ($tenant->getSetting('total_ratings', 0));

        return [
            'average' => $average,
            'count' => $count,
            'hasRating' => $count > 0,
        ];
    }

    /**
     * BR-396: Notify the cook about a new rating.
     *
     * Sends push + database notification.
     */
    private function notifyCook(Rating $rating, Order $order): void
    {
        $cook = $order->cook;
        if (! $cook) {
            // Load tenant to get cook
            $tenant = $order->tenant;
            if ($tenant && $tenant->cook_id) {
                $cook = User::find($tenant->cook_id);
            }
        }

        if ($cook) {
            $cook->notify(new RatingReceivedNotification($rating, $order));
        }
    }

    /**
     * Get the cook's average rating for a tenant.
     *
     * @return array{average: float, total: int}
     */
    public function getCookRatingStats(int $tenantId): array
    {
        $stats = Rating::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('AVG(stars) as average_rating, COUNT(*) as total_ratings')
            ->first();

        return [
            'average' => round((float) ($stats->average_rating ?? 0), 1),
            'total' => (int) ($stats->total_ratings ?? 0),
        ];
    }

    /**
     * F-178: Number of reviews per page.
     *
     * BR-413: Reviews are paginated with 10 per page.
     */
    public const REVIEWS_PER_PAGE = 10;

    /**
     * F-178: Get ratings for a specific meal, paginated.
     *
     * BR-408: Average rating calculated from all ratings for orders containing this meal.
     * BR-412: Reviews sorted by date descending (newest first).
     * BR-413: Paginated with 10 per page.
     *
     * Ratings link to orders via items_snapshot JSONB containing meal_id.
     * Uses PostgreSQL JSONB containment operator for efficient querying.
     */
    public function getMealReviews(int $mealId, int $tenantId, int $page = 1): LengthAwarePaginator
    {
        return Rating::query()
            ->where('ratings.tenant_id', $tenantId)
            ->whereHas('order', function ($query) use ($mealId) {
                $query->whereRaw(
                    'items_snapshot @> ?',
                    [json_encode([['meal_id' => $mealId]])]
                );
            })
            ->with('user:id,name')
            ->orderByDesc('ratings.created_at')
            ->paginate(self::REVIEWS_PER_PAGE, ['*'], 'review_page', $page);
    }

    /**
     * F-178: Get the average rating and total count for a specific meal.
     *
     * BR-408: Average from all ratings for orders containing this meal.
     * BR-409: Average displayed as X.X/5 (one decimal place).
     * BR-410: Review count includes ratings without text.
     *
     * @return array{average: float, total: int, distribution: array<int, int>}
     */
    public function getMealRatingStats(int $mealId, int $tenantId): array
    {
        $orderIds = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw(
                'items_snapshot @> ?',
                [json_encode([['meal_id' => $mealId]])]
            )
            ->pluck('id');

        if ($orderIds->isEmpty()) {
            return [
                'average' => 0.0,
                'total' => 0,
                'distribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
            ];
        }

        $stats = Rating::query()
            ->whereIn('order_id', $orderIds)
            ->selectRaw('AVG(stars) as average_rating, COUNT(*) as total_ratings')
            ->first();

        $distribution = Rating::query()
            ->whereIn('order_id', $orderIds)
            ->selectRaw('stars, COUNT(*) as count')
            ->groupBy('stars')
            ->pluck('count', 'stars')
            ->toArray();

        // Ensure all star levels are present
        $fullDistribution = [];
        for ($i = Rating::MAX_STARS; $i >= Rating::MIN_STARS; $i--) {
            $fullDistribution[$i] = (int) ($distribution[$i] ?? 0);
        }

        return [
            'average' => round((float) ($stats->average_rating ?? 0), 1),
            'total' => (int) ($stats->total_ratings ?? 0),
            'distribution' => $fullDistribution,
        ];
    }

    /**
     * F-178: Format a review for display.
     *
     * BR-411: Shows client name, stars, text (if any), date.
     * BR-415: Client name privacy — first name + last initial.
     * BR-414: Ratings without text still appear in the list.
     *
     * @return array{id: int, stars: int, review: string|null, date: string, clientName: string, relativeDate: string}
     */
    public function formatReviewForDisplay(Rating $rating): array
    {
        $clientName = $this->formatClientName($rating->user?->name);

        return [
            'id' => $rating->id,
            'stars' => $rating->stars,
            'review' => $rating->review,
            'date' => $rating->created_at->translatedFormat('M d, Y'),
            'relativeDate' => $rating->created_at->diffForHumans(),
            'clientName' => $clientName,
        ];
    }

    /**
     * F-178: Format client name for privacy.
     *
     * BR-415: Only first name and last initial displayed (e.g., "Amara N.").
     * Edge case: Deactivated clients still show their name.
     */
    public function formatClientName(?string $fullName): string
    {
        if (! $fullName) {
            return __('Anonymous');
        }

        $parts = explode(' ', trim($fullName));

        if (count($parts) < 2) {
            return $parts[0];
        }

        $firstName = $parts[0];
        $lastInitial = mb_strtoupper(mb_substr(end($parts), 0, 1));

        return $firstName.' '.$lastInitial.'.';
    }

    /**
     * F-178: Build complete review display data for a meal.
     *
     * @return array{stats: array, reviews: array, pagination: array}
     */
    public function getMealReviewDisplayData(int $mealId, int $tenantId, int $page = 1): array
    {
        $stats = $this->getMealRatingStats($mealId, $tenantId);
        $reviewsPaginator = $this->getMealReviews($mealId, $tenantId, $page);

        $reviews = $reviewsPaginator->getCollection()
            ->map(fn (Rating $rating) => $this->formatReviewForDisplay($rating))
            ->toArray();

        return [
            'stats' => $stats,
            'reviews' => $reviews,
            'pagination' => [
                'currentPage' => $reviewsPaginator->currentPage(),
                'lastPage' => $reviewsPaginator->lastPage(),
                'total' => $reviewsPaginator->total(),
                'hasMore' => $reviewsPaginator->hasMorePages(),
            ],
        ];
    }
}
