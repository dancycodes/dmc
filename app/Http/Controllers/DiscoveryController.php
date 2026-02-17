<?php

namespace App\Http\Controllers;

use App\Http\Requests\DiscoveryRequest;
use App\Services\DiscoveryService;

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
     */
    public function index(DiscoveryRequest $request, DiscoveryService $discoveryService): mixed
    {
        $cooks = $discoveryService->getDiscoverableCooks(
            search: $request->validated('search'),
            sort: $request->validated('sort'),
            direction: $request->validated('direction'),
        );

        $totalCooks = $discoveryService->getDiscoverableCookCount();

        $data = [
            'cooks' => $cooks,
            'totalCooks' => $totalCooks,
            'search' => $request->validated('search', ''),
            'sort' => $request->validated('sort', 'newest'),
            'direction' => $request->validated('direction', 'desc'),
        ];

        // BR-073/BR-088: Fragment-based partial update for Gale navigate requests
        // Returns both the result count and cook grid fragments
        if ($request->isGaleNavigate('discovery')) {
            return gale()
                ->fragment('discovery.index', 'result-count', $data)
                ->fragment('discovery.index', 'cook-grid', $data);
        }

        return gale()->view('discovery.index', $data, web: true);
    }
}
