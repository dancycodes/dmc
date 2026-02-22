<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminRevenueAnalyticsRequest;
use App\Services\AdminRevenueAnalyticsService;

/**
 * AdminRevenueAnalyticsController — platform revenue analytics for admin panel.
 *
 * F-205: Admin Platform Revenue Analytics
 * Route: GET /vault-entry/analytics/revenue
 * Permission: can-view-platform-analytics (enforced by admin middleware group)
 *
 * BR-417: Only admin and super-admin roles can access this page.
 * BR-418: Platform revenue = sum of completed order totals across all tenants.
 * BR-419: Commission = platform's commission portion from completed orders.
 * BR-420: Active cooks = tenants with at least one completed order in the period.
 * BR-421: Transaction count = number of completed payments in the period.
 * BR-424: Periods: This Month, Last 3 Months, Last 6 Months, This Year, Last Year, Custom.
 * BR-425: Comparison mode shows current vs previous equivalent period.
 */
class AdminRevenueAnalyticsController extends Controller
{
    public function __construct(
        private readonly AdminRevenueAnalyticsService $service
    ) {}

    /**
     * Display the admin platform revenue analytics dashboard.
     *
     * Handles both initial page load (web: true) and Gale period-change
     * requests (returns fragment update for the revenue-content section).
     */
    public function index(AdminRevenueAnalyticsRequest $request): mixed
    {
        $period = $request->getPeriod();
        $customStart = $request->getCustomStart();
        $customEnd = $request->getCustomEnd();
        $compare = $request->getCompare();

        // Ensure custom dates exist for custom period
        if ($period === 'custom' && (! $customStart || ! $customEnd)) {
            $period = 'this_month';
        }

        $range = $this->service->resolveDateRange($period, $customStart, $customEnd);
        $prevRange = $this->service->resolvePreviousDateRange($period, $range);

        $granularity = $this->service->resolveGranularity($range['start'], $range['end']);

        $summaryCards = $this->service->getSummaryCards(
            $range['start'],
            $range['end'],
            $prevRange['start'],
            $prevRange['end']
        );

        $revenueChartData = $this->service->getRevenueChartData(
            $range['start'],
            $range['end'],
            $granularity
        );

        $commissionChartData = $this->service->getCommissionChartData(
            $range['start'],
            $range['end'],
            $granularity
        );

        // Comparison chart data for previous period (only when compare mode enabled)
        $revenueComparisonData = $compare
            ? $this->service->getRevenueChartData($prevRange['start'], $prevRange['end'], $granularity)
            : collect();

        $commissionComparisonData = $compare
            ? $this->service->getCommissionChartData($prevRange['start'], $prevRange['end'], $granularity)
            : collect();

        $revenueByCook = $this->service->getRevenueByCoook(
            $range['start'],
            $range['end']
        );

        $revenueByRegion = $this->service->getRevenueByRegion(
            $range['start'],
            $range['end']
        );

        $data = [
            'period' => $period,
            'customStart' => $customStart,
            'customEnd' => $customEnd,
            'compare' => $compare,
            'granularity' => $granularity,
            'rangeStart' => $range['start'],
            'rangeEnd' => $range['end'],
            'prevRangeStart' => $prevRange['start'],
            'prevRangeEnd' => $prevRange['end'],
            'summaryCards' => $summaryCards,
            'revenueChartData' => $revenueChartData,
            'commissionChartData' => $commissionChartData,
            'revenueComparisonData' => $revenueComparisonData,
            'commissionComparisonData' => $commissionComparisonData,
            'revenueByCook' => $revenueByCook,
            'revenueByRegion' => $revenueByRegion,
            'periods' => AdminRevenueAnalyticsService::PERIODS,
        ];

        // Gale navigate fragment — only refresh the content area on period/compare changes
        if ($request->isGaleNavigate('revenue-analytics')) {
            return gale()->fragment('admin.analytics.revenue', 'revenue-analytics-content', $data);
        }

        return gale()->view('admin.analytics.revenue', $data, web: true);
    }
}
