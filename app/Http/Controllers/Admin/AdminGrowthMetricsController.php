<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminGrowthMetricsRequest;
use App\Services\AdminGrowthMetricsService;

/**
 * AdminGrowthMetricsController — platform growth metrics dashboard for admin panel.
 *
 * F-207: Admin Growth Metrics
 * Route: GET /vault-entry/analytics/growth
 * Permission: can-view-platform-analytics (enforced by admin middleware group)
 *
 * BR-440: Only admin and super-admin roles can access growth metrics.
 * BR-441: New users = users created within the selected period.
 * BR-442: New cooks = tenants created within the selected period.
 * BR-443: Order volume = total orders placed within the selected period.
 * BR-444: Active users = distinct users who placed at least one order in the last 30 days.
 * BR-445: Date range options: Last 3 Months, Last 6 Months, This Year, Last Year, All Time.
 * BR-446: Comparison shows growth percentage vs previous period.
 * BR-447: Milestones are predefined thresholds for users, cooks, orders.
 */
class AdminGrowthMetricsController extends Controller
{
    public function __construct(
        private readonly AdminGrowthMetricsService $service
    ) {}

    /**
     * Display the admin growth metrics dashboard.
     *
     * Handles both the initial page load (web: true) and Gale navigate fragment
     * requests for period changes.
     */
    public function index(AdminGrowthMetricsRequest $request): mixed
    {
        $period = $request->getPeriod();

        $range = $this->service->resolveDateRange($period);
        $prevRange = $this->service->resolvePreviousDateRange($period, $range);

        $summaryCards = $this->service->getSummaryCards(
            $range['start'],
            $range['end'],
            $prevRange['start'],
            $prevRange['end']
        );

        $newUsersChartData = $this->service->getNewUsersChartData($range['start'], $range['end']);
        $newCooksChartData = $this->service->getNewCooksChartData($range['start'], $range['end']);
        $orderVolumeChartData = $this->service->getOrderVolumeChartData($range['start'], $range['end']);
        $activeUsersChartData = $this->service->getActiveUsersChartData($range['start'], $range['end']);

        $milestones = $this->service->getMilestones();

        $data = [
            'period' => $period,
            'periods' => AdminGrowthMetricsService::PERIODS,
            'rangeStart' => $range['start'],
            'rangeEnd' => $range['end'],
            'summaryCards' => $summaryCards,
            'newUsersChartData' => $newUsersChartData,
            'newCooksChartData' => $newCooksChartData,
            'orderVolumeChartData' => $orderVolumeChartData,
            'activeUsersChartData' => $activeUsersChartData,
            'milestones' => $milestones,
        ];

        // Gale navigate fragment — only refresh the content area on period changes
        if ($request->isGaleNavigate('growth-metrics')) {
            return gale()->fragment('admin.analytics.growth', 'growth-metrics-content', $data);
        }

        return gale()->view('admin.analytics.growth', $data, web: true);
    }
}
