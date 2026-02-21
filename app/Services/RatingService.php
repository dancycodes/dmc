<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Rating;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\RatingReceivedNotification;
use Illuminate\Support\Facades\DB;

/**
 * F-176: Order Rating Service
 *
 * Handles rating submission, validation, and related operations.
 * BR-388: Rating only for Completed orders.
 * BR-390: One rating per order.
 * BR-391: Rating cannot be edited or deleted.
 * BR-395: Triggers cook's overall rating recalculation (F-179).
 * BR-396: Cook is notified of new ratings.
 * BR-397: Rating submission is logged via Spatie Activitylog.
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
     * Submit a rating for a completed order.
     *
     * BR-389: 1-5 stars (integer only).
     * BR-390: Each order can be rated exactly once.
     * BR-394: Associated with order, client, and tenant.
     * BR-395: Triggers cook's overall rating recalculation.
     * BR-396: Cook is notified.
     * BR-397: Activity logged.
     *
     * @return array{success: bool, rating?: Rating, error?: string}
     */
    public function submitRating(Order $order, User $user, int $stars): array
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

        $rating = DB::transaction(function () use ($order, $user, $stars) {
            $rating = Rating::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'tenant_id' => $order->tenant_id,
                'stars' => $stars,
            ]);

            // BR-395: Trigger cook's overall rating recalculation (F-179 forward-compatible)
            $this->recalculateCookRating($order->tenant);

            return $rating;
        });

        // BR-397: Activity logging
        activity('ratings')
            ->performedOn($rating)
            ->causedBy($user)
            ->withProperties([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'stars' => $stars,
                'tenant_id' => $order->tenant_id,
            ])
            ->log('submitted rating');

        // BR-396: Notify cook
        $this->notifyCook($rating, $order);

        return [
            'success' => true,
            'rating' => $rating,
        ];
    }

    /**
     * BR-395: Recalculate the cook's overall rating.
     *
     * Forward-compatible for F-179. Currently stores the average
     * rating in the tenant's settings JSON.
     */
    private function recalculateCookRating(Tenant $tenant): void
    {
        $stats = Rating::query()
            ->where('tenant_id', $tenant->id)
            ->selectRaw('AVG(stars) as average_rating, COUNT(*) as total_ratings')
            ->first();

        $settings = $tenant->settings ?? [];
        $settings['average_rating'] = round((float) $stats->average_rating, 1);
        $settings['total_ratings'] = (int) $stats->total_ratings;
        $tenant->settings = $settings;
        $tenant->save();
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
}
