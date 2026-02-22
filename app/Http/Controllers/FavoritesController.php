<?php

namespace App\Http\Controllers;

use App\Services\FavoritesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * F-198: Favorites List View
 *
 * Displays the authenticated user's favorites: two tabs for cooks and meals.
 * Handles tab switching and item removal via Gale.
 *
 * BR-344: Authentication required.
 * BR-345: Default tab is "Favorite Cooks".
 * BR-353: Tab switching via Gale without page reload.
 * BR-349: Removal via Gale without page reload.
 */
class FavoritesController extends Controller
{
    public function __construct(private readonly FavoritesService $favoritesService) {}

    /**
     * Display the favorites page.
     *
     * GET /my-favorites
     *
     * BR-345: Default tab is cooks.
     * BR-353: Tab switch via Gale navigate fragment.
     * BR-354: 12 per page.
     */
    public function index(Request $request): mixed
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $tab = in_array($request->query('tab'), ['cooks', 'meals']) ? $request->query('tab') : 'cooks';
        $page = max(1, (int) $request->query('page', 1));

        if ($tab === 'meals') {
            $favorites = $this->favoritesService->getFavoriteMeals($user, $page);
        } else {
            $favorites = $this->favoritesService->getFavoriteCooks($user, $page);
        }

        $data = compact('favorites', 'tab');

        // BR-353: Tab switch + pagination use Gale navigate fragment for partial updates.
        if ($request->isGaleNavigate('favorites')) {
            return gale()->fragment('favorites.index', 'favorites-content', $data);
        }

        return gale()->view('favorites.index', $data, web: true);
    }

    /**
     * Remove a cook from favorites.
     *
     * POST /my-favorites/cooks/{cookUserId}/remove
     *
     * BR-348: Remove action on each card.
     * BR-349: Via Gale without page reload; card animates out client-side.
     */
    public function removeCook(Request $request, int $cookUserId): mixed
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $result = $this->favoritesService->removeFavoriteCook($user, $cookUserId);

        if (! $result['success']) {
            return gale()->dispatch('toast', [
                'type' => 'error',
                'message' => $result['message'],
            ]);
        }

        // BR-349: Return state to trigger client-side card removal animation.
        return gale()
            ->state('removedCookId', $cookUserId)
            ->dispatch('toast', [
                'type' => 'success',
                'message' => $result['message'],
            ]);
    }

    /**
     * Remove a meal from favorites.
     *
     * POST /my-favorites/meals/{mealId}/remove
     *
     * BR-348: Remove action on each card.
     * BR-349: Via Gale without page reload; card animates out client-side.
     */
    public function removeMeal(Request $request, int $mealId): mixed
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $result = $this->favoritesService->removeFavoriteMeal($user, $mealId);

        if (! $result['success']) {
            return gale()->dispatch('toast', [
                'type' => 'error',
                'message' => $result['message'],
            ]);
        }

        // BR-349: Return state to trigger client-side card removal animation.
        return gale()
            ->state('removedMealId', $mealId)
            ->dispatch('toast', [
                'type' => 'success',
                'message' => $result['message'],
            ]);
    }
}
