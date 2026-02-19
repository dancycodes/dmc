<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\MealSearchRequest;
use App\Services\TenantLandingService;

class MealSearchController extends Controller
{
    public function __construct(
        private TenantLandingService $landingService,
    ) {}

    /**
     * Search and filter meals on the tenant landing page.
     *
     * F-135: Meal Search Bar
     * F-136: Meal Filters
     * BR-214: Search across meal names, descriptions, component names, tag names
     * BR-215: Case-insensitive search
     * BR-217: Results filter the existing meals grid via Gale fragment (no page reload)
     * BR-219: Clearing search restores the full meals grid
     * BR-221: Minimum 2 characters to trigger search
     * BR-223: Tag filter multi-select with OR logic
     * BR-224: Availability filter: "all" or "available_now"
     * BR-226: Price range filter on starting price
     * BR-228: AND logic between filter types
     * BR-231: Filters applied via Gale without page reload
     * BR-232: Filters combinable with search
     */
    public function search(MealSearchRequest $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        $query = $request->searchQuery();
        $page = max(1, (int) $request->query('page', 1));
        $tagIds = $request->tagIds();
        $availability = $request->availabilityFilter();
        $priceMin = $request->priceMin();
        $priceMax = $request->priceMax();

        $hasFilters = $request->hasActiveFilters();
        $hasSearch = mb_strlen($query) >= 2;

        // Use filterMeals when any filter or search is active
        if ($hasFilters || $hasSearch) {
            $meals = $this->landingService->filterMeals(
                $tenant,
                $query,
                $tagIds,
                $availability,
                $priceMin,
                $priceMax,
                $page,
            );
        } else {
            $meals = $this->landingService->getAvailableMeals($tenant, $page);
        }

        $sections = ['schedule' => ['hasData' => $tenant->cookSchedules()->exists()]];
        $activeFilterCount = $request->activeFilterCount();

        // BR-217/BR-231: For Gale navigate requests, return only the meals grid fragment
        if ($request->isGaleNavigate('meal-search')) {
            return gale()->fragment('tenant._meals-grid', 'meals-grid-fragment', [
                'meals' => $meals,
                'sections' => $sections,
                'searchQuery' => $query,
                'activeFilterCount' => $activeFilterCount,
            ]);
        }

        // Full page fallback — redirect to home with query
        return gale()->redirect(url('/').'?'.http_build_query(array_filter(['q' => $query])));
    }
}
