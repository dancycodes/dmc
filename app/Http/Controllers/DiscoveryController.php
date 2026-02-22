<?php

namespace App\Http\Controllers;

use App\Http\Requests\DiscoveryRequest;
use App\Services\DiscoveryService;
use Illuminate\Support\Facades\Auth;

class DiscoveryController extends Controller
{
    /**
     * Display the discovery page with cook card grid.
     *
     * BR-066: Discovery page accessible ONLY on main domain.
     * BR-067: Only active tenants with assigned cooks displayed.
     * BR-068: No authentication required.
     * BR-069: Paginated in pages of 12.
     * BR-073: Grid updates via Gale without full page reload.
     * BR-090: Filter categories: town, availability, tags, min_rating.
     * BR-091: Filters between categories combine with AND logic.
     * BR-095: Filter changes update the grid via Gale without page reload.
     * BR-096: Filters combine with any active search query (F-068).
     * BR-327 (F-196): Heart icon visually reflects current favorite state on page load.
     */
    public function index(DiscoveryRequest $request, DiscoveryService $discoveryService): mixed
    {
        // Extract validated filter parameters
        $search = $request->validated('search');
        $sort = $request->validated('sort');
        $direction = $request->validated('direction');
        $town = $request->validated('town') ? (int) $request->validated('town') : null;
        $availability = $request->validated('availability');
        $tags = $request->validated('tags');
        $minRating = $request->validated('min_rating') ? (int) $request->validated('min_rating') : null;

        $cooks = $discoveryService->getDiscoverableCooks(
            search: $search,
            sort: $sort,
            direction: $direction,
            town: $town,
            availability: $availability,
            tags: $tags,
            minRating: $minRating,
        );

        $totalCooks = $discoveryService->getDiscoverableCookCount();
        $filterTowns = $discoveryService->getFilterTowns();
        $filterTags = $discoveryService->getFilterTags();
        $activeFilterCount = $discoveryService->countActiveFilters(
            town: $town,
            availability: $availability,
            tags: $tags,
            minRating: $minRating,
        );

        // F-196: Resolve favorite cook IDs for the current user (keyed by cook_user_id).
        // BR-327: Heart icon visually reflects current favorite state on page load.
        $userFavoriteCookIds = [];
        if (Auth::check()) {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $userFavoriteCookIds = $user->favoriteCooks()->pluck('cook_user_id')->toArray();
        }

        $data = [
            'cooks' => $cooks,
            'totalCooks' => $totalCooks,
            'search' => $search ?? '',
            'sort' => $sort ?? 'popularity',
            'direction' => $direction ?? 'desc',
            'filterTowns' => $filterTowns,
            'filterTags' => $filterTags,
            'selectedTown' => $town,
            'selectedAvailability' => $availability ?? 'all',
            'selectedTags' => $tags ?? [],
            'selectedMinRating' => $minRating,
            'activeFilterCount' => $activeFilterCount,
            'hasActiveFilters' => $activeFilterCount > 0,
            'userFavoriteCookIds' => $userFavoriteCookIds,
            'isAuthenticated' => Auth::check(),
        ];

        // BR-073/BR-088/BR-095: Fragment-based partial update for Gale navigate requests
        // Returns both the result count and cook grid fragments
        if ($request->isGaleNavigate('discovery')) {
            return gale()
                ->fragment('discovery.index', 'result-count', $data)
                ->fragment('discovery.index', 'cook-grid', $data);
        }

        return gale()->view('discovery.index', $data, web: true);
    }
}
