<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CookPerformanceRequest;
use App\Services\CookPerformanceService;

/**
 * CookPerformanceController — admin cook performance metrics table.
 *
 * F-206: Admin Cook Performance Metrics
 * Route: GET /vault-entry/analytics/performance
 * Permission: can-view-platform-analytics (enforced by admin middleware group)
 *
 * BR-428: Only admin and super-admin roles can access this page.
 * BR-429: Table columns: Cook Name, Region/Town, Total Orders, Total Revenue (XAF),
 *         Average Rating, Complaint Count, Average Response Time.
 * BR-430: All columns are sortable.
 * BR-431: Filters: cook status (active/inactive), region/town.
 * BR-432: Search by cook name.
 * BR-436: Paginated at 25 per page.
 * BR-437: Date range applies to orders, revenue, and complaints counted.
 */
class CookPerformanceController extends Controller
{
    public function __construct(
        private readonly CookPerformanceService $service
    ) {}

    /**
     * Display the cook performance metrics table.
     *
     * Handles both the initial page load (web: true) and Gale navigate fragment
     * requests for filter/sort/search changes.
     */
    public function index(CookPerformanceRequest $request): mixed
    {
        $period = $request->getPeriod();
        $customStart = $request->getCustomStart();
        $customEnd = $request->getCustomEnd();

        // Fall back to this_month if custom is selected but dates are missing
        if ($period === 'custom' && (! $customStart || ! $customEnd)) {
            $period = 'this_month';
        }

        $dateRange = $this->service->resolveDateRange($period, $customStart, $customEnd);

        $sortBy = $request->getSortBy();
        $sortDir = $request->getSortDirection();
        $search = $request->getSearch();
        $status = $request->getStatus();
        $regionId = $request->getRegionId();
        $page = $request->getPage();

        $cooks = $this->service->getPerformanceTable(
            $dateRange,
            $sortBy,
            $sortDir,
            $search,
            $status,
            $regionId,
            $page
        );

        $regions = $this->service->getRegionsForFilter();

        $data = [
            'period' => $period,
            'customStart' => $customStart,
            'customEnd' => $customEnd,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'search' => $search ?? '',
            'status' => $status ?? '',
            'regionId' => $regionId ?? 0,
            'cooks' => $cooks,
            'regions' => $regions,
            'periods' => CookPerformanceService::PERIODS,
            'rangeStart' => $dateRange['start'],
            'rangeEnd' => $dateRange['end'],
        ];

        // Gale navigate fragment — only refresh the content area on filter/sort changes
        if ($request->isGaleNavigate('cook-performance')) {
            return gale()->fragment('admin.analytics.performance', 'cook-performance-content', $data);
        }

        return gale()->view('admin.analytics.performance', $data, web: true);
    }
}
