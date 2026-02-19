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
     * Search meals on the tenant landing page.
     *
     * F-135: Meal Search Bar
     * BR-214: Search across meal names, descriptions, component names, tag names
     * BR-215: Case-insensitive search
     * BR-217: Results filter the existing meals grid via Gale fragment (no page reload)
     * BR-219: Clearing search restores the full meals grid
     * BR-221: Minimum 2 characters to trigger search
     */
    public function search(MealSearchRequest $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        $query = $request->searchQuery();
        $page = max(1, (int) $request->query('page', 1));

        // BR-221: Only search with 2+ characters; empty/short query returns full grid
        if (mb_strlen($query) >= 2) {
            $meals = $this->landingService->searchMeals($tenant, $query, $page);
        } else {
            $meals = $this->landingService->getAvailableMeals($tenant, $page);
        }

        $sections = ['schedule' => ['hasData' => $tenant->cookSchedules()->exists()]];

        // BR-217: For Gale navigate requests, return only the meals grid fragment
        if ($request->isGaleNavigate('meal-search')) {
            return gale()->fragment('tenant._meals-grid', 'meals-grid-fragment', [
                'meals' => $meals,
                'sections' => $sections,
                'searchQuery' => $query,
            ]);
        }

        // Full page fallback — redirect to home with query
        return gale()->redirect(url('/').'?'.http_build_query(array_filter(['q' => $query])));
    }
}
