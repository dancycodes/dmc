<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * F-197: Favorite Meal Toggle
 *
 * Handles toggling a meal as a favorite for the authenticated user.
 * BR-333: Requires authentication; guests cannot favorite.
 * BR-334: A user can favorite any active meal from any active tenant.
 * BR-335: Toggle is idempotent — adds when not favorited, removes when favorited.
 * BR-338: Toggle happens via Gale without page reload.
 */
class FavoriteMealController extends Controller
{
    /**
     * Toggle the authenticated user's favorite status for a given meal.
     *
     * POST /favorite-meals/{meal}
     *
     * Returns a Gale state response updating the caller's Alpine state.
     * BR-336: Favorite state stored in the favorite_meals pivot table.
     */
    public function toggle(Request $request, Meal $meal): mixed
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // BR-334: The meal must be live and its tenant must be active.
        if ($meal->status !== Meal::STATUS_LIVE || ! $meal->tenant?->is_active) {
            return gale()->state('favoriteError', __('This meal is not available for favoriting.'));
        }

        $isFavorited = $user->favoriteMeals()->where('meal_id', $meal->id)->exists();

        if ($isFavorited) {
            // BR-335: Remove from favorites when already favorited.
            $user->favoriteMeals()->detach($meal->id);
            $newState = false;
            $toastMessage = __('Removed from favorites');
        } else {
            // BR-335: Add to favorites when not favorited.
            $user->favoriteMeals()->attach($meal->id, ['created_at' => now()]);
            $newState = true;
            $toastMessage = __('Added to favorites');
        }

        // BR-338: Return Gale state response (no page reload).
        // The state key 'isMealFavorited' updates the calling x-data component's reactive state.
        return gale()
            ->state('isMealFavorited', $newState)
            ->dispatch('toast', [
                'type' => 'success',
                'message' => $toastMessage,
            ]);
    }
}
