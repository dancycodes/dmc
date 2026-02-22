<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Services\CookDeliveryAnalyticsService;
use Illuminate\Http\Request;

/**
 * F-203: Cook Delivery Performance Analytics
 *
 * Displays delivery performance analytics in the cook dashboard.
 * Accessible to cooks and managers with can-view-cook-analytics permission.
 *
 * BR-401: Delivery data is tenant-scoped.
 * BR-406: Date range selector applies to all charts and metrics.
 */
class DeliveryAnalyticsController extends Controller
{
    public function __construct(private readonly CookDeliveryAnalyticsService $service) {}

    /**
     * Display the cook delivery performance analytics dashboard.
     *
     * GET /dashboard/analytics/delivery
     */
    public function index(Request $request): mixed
    {
        $tenant = tenant();

        // Authorization: cook role or can-view-cook-analytics permission
        $user = $request->user();
        if (! $user->can('can-view-cook-analytics')) {
            abort(403, __('You do not have permission to view analytics.'));
        }

        // Resolve period and date range
        $period = $this->resolvePeriod($request->input('period', 'this_month'));
        $customStart = $request->input('custom_start');
        $customEnd = $request->input('custom_end');

        $dateRange = $this->service->resolveDateRange($period, $customStart, $customEnd);
        $start = $dateRange['start'];
        $end = $dateRange['end'];

        // Delivery vs pickup ratio (BR-404)
        $deliveryVsPickup = $this->service->getDeliveryVsPickupRatio($tenant->id, $start, $end);

        // Top delivery areas (BR-405)
        $topAreas = $this->service->getTopDeliveryAreas($tenant->id, $start, $end);

        // Summary metrics
        $summaryMetrics = $this->service->getSummaryMetrics($tenant->id, $start, $end);

        // Data state flags for empty states
        $hasAnyOrders = $this->service->hasAnyCompletedOrders($tenant->id);
        $hasDeliveryData = $this->service->hasDeliveryData($tenant->id);

        $viewData = [
            'period' => $period,
            'customStart' => $customStart,
            'customEnd' => $customEnd,
            'rangeStart' => $start,
            'rangeEnd' => $end,
            'deliveryVsPickup' => $deliveryVsPickup,
            'topAreas' => $topAreas,
            'summaryMetrics' => $summaryMetrics,
            'hasAnyOrders' => $hasAnyOrders,
            'hasDeliveryData' => $hasDeliveryData,
            'periods' => CookDeliveryAnalyticsService::PERIODS,
        ];

        // Gale navigate fragment for period switching without full reload (BR-406)
        if ($request->isGaleNavigate('analytics')) {
            return gale()->fragment('cook.analytics.delivery', 'analytics-content', $viewData);
        }

        return gale()->view('cook.analytics.delivery', $viewData, web: true);
    }

    /**
     * Validate and return the period key; fall back to this_month.
     */
    private function resolvePeriod(mixed $period): string
    {
        $validPeriods = array_keys(CookDeliveryAnalyticsService::PERIODS);

        if (! in_array($period, $validPeriods, true)) {
            return 'this_month';
        }

        return $period;
    }
}
