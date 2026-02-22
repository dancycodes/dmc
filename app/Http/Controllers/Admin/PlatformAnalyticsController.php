<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlatformAnalyticsRequest;
use App\Services\PlatformAnalyticsService;

/**
 * PlatformAnalyticsController — admin analytics dashboard.
 *
 * F-057: Platform Analytics Dashboard
 * Route: GET /vault-entry/analytics
 * Permission: can-view-platform-analytics
 */
class PlatformAnalyticsController extends Controller
{
    public function __construct(
        private readonly PlatformAnalyticsService $analyticsService
    ) {}

    /**
     * Display the platform analytics dashboard.
     *
     * Handles both initial page load (web: true) and Gale period-change
     * requests (returns fragment update for metrics + charts section).
     */
    public function index(PlatformAnalyticsRequest $request): mixed
    {
        $period = $request->getPeriod();
        $customStart = $request->getCustomStart();
        $customEnd = $request->getCustomEnd();

        // Validate custom range dates exist for custom period
        if ($period === 'custom' && (! $customStart || ! $customEnd)) {
            $period = 'today';
        }

        $range = $this->analyticsService->resolveDateRange($period, $customStart, $customEnd);
        $prevRange = $this->analyticsService->resolvePreviousDateRange($period, $range);

        $metrics = $this->analyticsService->getSummaryMetrics(
            $range['start'],
            $range['end'],
            $prevRange['start'],
            $prevRange['end']
        );
        $granularity = $this->analyticsService->resolveChartGranularity($range['start'], $range['end']);
        $revenueData = $this->analyticsService->getRevenueChartData($range['start'], $range['end'], $granularity);
        $orderData = $this->analyticsService->getOrderChartData($range['start'], $range['end'], $granularity);
        $topCooks = $this->analyticsService->getTopCooks($range['start'], $range['end']);
        $topMeals = $this->analyticsService->getTopMeals($range['start'], $range['end']);

        $data = [
            'period' => $period,
            'customStart' => $customStart,
            'customEnd' => $customEnd,
            'metrics' => $metrics,
            'granularity' => $granularity,
            'revenueData' => $revenueData,
            'orderData' => $orderData,
            'topCooks' => $topCooks,
            'topMeals' => $topMeals,
            'rangeStart' => $range['start'],
            'rangeEnd' => $range['end'],
        ];

        // Gale navigate fragment update — only refresh the content area
        if ($request->isGaleNavigate('analytics')) {
            return gale()->fragment('admin.analytics.index', 'analytics-content', $data);
        }

        return gale()->view('admin.analytics.index', $data, web: true);
    }
}
