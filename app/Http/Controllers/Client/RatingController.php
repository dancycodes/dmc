<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreRatingRequest;
use App\Models\Order;
use App\Services\RatingService;
use Illuminate\Http\Request;

/**
 * F-176: Order Rating Prompt Controller
 *
 * Handles star rating submission for completed orders.
 * Rating is submitted inline on the order detail page via Gale $action.
 *
 * BR-388: Rating only for Completed orders.
 * BR-390: Each order can be rated exactly once.
 * BR-391: Once submitted, rating cannot be edited or deleted.
 */
class RatingController extends Controller
{
    /**
     * Submit a rating for a completed order.
     *
     * BR-388: Only Completed orders can be rated.
     * BR-389: 1-5 stars (integer only).
     * BR-390: One rating per order.
     * BR-394: Associated with order, client, and tenant.
     * BR-395: Triggers cook rating recalculation.
     * BR-396: Cook is notified.
     * BR-397: Activity logged.
     */
    public function store(Request $request, Order $order, RatingService $ratingService): mixed
    {
        $user = $request->user();

        // BR-222: Client can only rate their own orders
        if ($order->client_id !== $user->id) {
            abort(403, __('You are not authorized to rate this order.'));
        }

        // Dual Gale/HTTP validation
        if ($request->isGale()) {
            $validated = $request->validateState([
                'stars' => ['required', 'integer', 'min:1', 'max:5'],
            ]);
        } else {
            $validated = app(StoreRatingRequest::class)->validated();
        }

        $result = $ratingService->submitRating(
            order: $order,
            user: $user,
            stars: (int) $validated['stars'],
        );

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'stars' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['stars' => $result['error']]);
        }

        $rating = $result['rating'];

        if ($request->isGale()) {
            return gale()
                ->state('rated', true)
                ->state('submittedStars', $rating->stars)
                ->state('canRate', false)
                ->dispatch('toast', [
                    'type' => 'success',
                    'message' => __('Thank you for your rating!'),
                ]);
        }

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => __('Thank you for your rating!'),
        ]);
    }
}
