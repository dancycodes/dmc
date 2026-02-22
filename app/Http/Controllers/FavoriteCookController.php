<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * F-196: Favorite Cook Toggle
 *
 * Handles toggling a cook as a favorite for the authenticated user.
 * BR-323: Requires authentication; guests cannot favorite.
 * BR-324: A user can favorite any active cook.
 * BR-325: Toggle is idempotent — adds when not favorited, removes when favorited.
 * BR-328: Toggle happens via Gale without page reload.
 */
class FavoriteCookController extends Controller
{
    /**
     * Toggle the authenticated user's favorite status for a given cook (via Tenant).
     *
     * POST /favorite-cooks/{tenant}
     *
     * Returns a Gale state response updating the caller's Alpine state.
     * The cook_user_id is resolved from the Tenant model's cook_id.
     */
    public function toggle(Request $request, Tenant $tenant): mixed
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // BR-324: The tenant must be active and have a cook assigned.
        if (! $tenant->is_active || ! $tenant->cook_id) {
            return gale()->state('favoriteError', __('This cook is not available for favoriting.'));
        }

        $cookUserId = (int) $tenant->cook_id;

        $isFavorited = $user->favoriteCooks()->where('cook_user_id', $cookUserId)->exists();

        if ($isFavorited) {
            // BR-325: Remove from favorites when already favorited.
            $user->favoriteCooks()->detach($cookUserId);
            $newState = false;
            $toastMessage = __('Removed from favorites');
        } else {
            // BR-325: Add to favorites when not favorited.
            $user->favoriteCooks()->attach($cookUserId, ['created_at' => now()]);
            $newState = true;
            $toastMessage = __('Added to favorites');
        }

        // BR-328: Return Gale state response (no page reload).
        // The state key 'isFavorited' updates the calling x-data component's reactive state.
        return gale()
            ->state('isFavorited', $newState)
            ->dispatch('toast', [
                'type' => 'success',
                'message' => $toastMessage,
            ]);
    }
}
