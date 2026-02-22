<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Services\CookOrderAnalyticsService;
use Illuminate\Http\Request;

/**
 * F-201: Cook Order Analytics
 *
 * Displays order analytics in the cook dashboard.
 * Accessible to cooks and managers with can-view-cook-analytics permission.
 *
 * BR-379: Order data is tenant-scoped.
 * BR-387: Charts update via Gale when date range changes (no page reload).
 */
class OrderAnalyticsController extends Controller
{
    public function __construct(private readonly CookOrderAnalyticsService $service) {}

    /**
     * Display the cook order analytics dashboard.
     *
     * GET /dashboard/analytics/orders
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

        $granularity = $this->service->resolveGranularity($start, $end);

        // Summary cards
        $summaryCards = $this->service->getSummaryCards($tenant->id, $start, $end);

        // Order count chart data
        $chartData = $this->service->getOrderChartData($tenant->id, $start, $end, $granularity);

        // Orders by status distribution
        $ordersByStatus = $this->service->getOrdersByStatus($tenant->id, $start, $end);

        // Popular meals by order count
        $popularMeals = $this->service->getPopularMeals($tenant->id, $start, $end);

        // Peak times heatmap
        $peakTimes = $this->service->getPeakTimesHeatmap($tenant->id, $start, $end);

        $hasData = $this->service->hasOrderData($tenant->id);

        $viewData = [
            'period' => $period,
            'customStart' => $customStart,
            'customEnd' => $customEnd,
            'granularity' => $granularity,
            'rangeStart' => $start,
            'rangeEnd' => $end,
            'summaryCards' => $summaryCards,
            'chartData' => $chartData,
            'ordersByStatus' => $ordersByStatus,
            'popularMeals' => $popularMeals,
            'peakTimes' => $peakTimes,
            'hasData' => $hasData,
            'periods' => CookOrderAnalyticsService::PERIODS,
        ];

        // Gale navigate fragment for period switching without full reload
        if ($request->isGaleNavigate('analytics')) {
            return gale()->fragment('cook.analytics.orders', 'analytics-content', $viewData);
        }

        return gale()->view('cook.analytics.orders', $viewData, web: true);
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
        $validPeriods = array_keys(CookOrderAnalyticsService::PERIODS);

        if (! in_array($period, $validPeriods, true)) {
            return 'this_month';
        }

        return $period;
    }
}
