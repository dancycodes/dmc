<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Services\CookRevenueAnalyticsService;
use Illuminate\Http\Request;

/**
 * F-200: Cook Revenue Analytics
 *
 * Displays revenue analytics in the cook dashboard.
 * Accessible to cooks and managers with can-view-cook-analytics permission.
 *
 * BR-368: Revenue data is tenant-scoped.
 * BR-377: Charts update via Gale when date range changes (no page reload).
 */
class RevenueAnalyticsController extends Controller
{
    public function __construct(private readonly CookRevenueAnalyticsService $service) {}

    /**
     * Display the cook revenue analytics dashboard.
     *
     * GET /dashboard/analytics
     */
    public function index(Request $request): mixed
    {
        $tenant = tenant();

        // Authorization: cook role or can-view-cook-analytics permission
        $this->authorizeAnalyticsAccess($request);

        // Resolve period and date range
        $period = $this->resolvePeriod($request->input('period', 'this_month'));
        $customStart = $request->input('custom_start');
        $customEnd = $request->input('custom_end');

        $dateRange = $this->service->resolveDateRange($period, $customStart, $customEnd);
        $start = $dateRange['start'];
        $end = $dateRange['end'];

        $previousRange = $this->service->resolvePreviousDateRange($period, $dateRange);
        $prevStart = $previousRange['start'];
        $prevEnd = $previousRange['end'];

        $granularity = $this->service->resolveGranularity($start, $end);
        $compare = (bool) $request->input('compare', false);

        // Always show summary cards (all-time stats)
        $summaryCards = $this->service->getSummaryCards($tenant->id);

        // Period-specific revenue
        $periodRevenue = $this->service->getTotalRevenue($tenant->id, $start, $end);
        $prevPeriodRevenue = $this->service->getTotalRevenue($tenant->id, $prevStart, $prevEnd);
        $revenueChange = $this->service->calculatePercentageChange($periodRevenue, $prevPeriodRevenue);

        // Chart data
        $chartData = $this->service->getRevenueChartData($tenant->id, $start, $end, $granularity);

        // Comparison chart data (previous period, same granularity)
        $comparisonData = $compare
            ? $this->service->getRevenueChartData($tenant->id, $prevStart, $prevEnd, $granularity)
            : collect();

        // Revenue by meal
        $mealBreakdown = $this->service->getRevenueByMeal($tenant->id, $start, $end);

        $hasData = $this->service->hasRevenueData($tenant->id);

        $viewData = [
            'period' => $period,
            'customStart' => $customStart,
            'customEnd' => $customEnd,
            'granularity' => $granularity,
            'compare' => $compare,
            'rangeStart' => $start,
            'rangeEnd' => $end,
            'prevRangeStart' => $prevStart,
            'prevRangeEnd' => $prevEnd,
            'summaryCards' => $summaryCards,
            'periodRevenue' => $periodRevenue,
            'prevPeriodRevenue' => $prevPeriodRevenue,
            'revenueChange' => $revenueChange,
            'chartData' => $chartData,
            'comparisonData' => $comparisonData,
            'mealBreakdown' => $mealBreakdown,
            'hasData' => $hasData,
            'periods' => CookRevenueAnalyticsService::PERIODS,
        ];

        // Gale navigate fragment for period switching without full reload
        if ($request->isGaleNavigate('analytics')) {
            return gale()->fragment('cook.analytics.revenue', 'analytics-content', $viewData);
        }

        return gale()->view('cook.analytics.revenue', $viewData, web: true);
    }

    /**
     * Verify the user has the right to view cook analytics.
     */
    private function authorizeAnalyticsAccess(Request $request): void
    {
        $user = $request->user();

        if (! $user->can('can-view-cook-analytics')) {
            abort(403, __('You do not have permission to view analytics.'));
        }
    }

    /**
     * Validate and return the period key; fall back to this_month.
     */
    private function resolvePeriod(mixed $period): string
    {
        $validPeriods = array_keys(CookRevenueAnalyticsService::PERIODS);

        if (! in_array($period, $validPeriods, true)) {
            return 'this_month';
        }

        return $period;
    }
}
