<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Services\CookCustomerRetentionService;
use Illuminate\Http\Request;

/**
 * F-202: Cook Customer Retention Analytics
 *
 * Displays customer retention analytics in the cook dashboard.
 * Accessible to cooks and managers with can-view-cook-analytics permission.
 *
 * BR-390: Customer data is tenant-scoped.
 * BR-398: Date range selector applies to summary cards and charts.
 */
class CustomerRetentionController extends Controller
{
    public function __construct(private readonly CookCustomerRetentionService $service) {}

    /**
     * Display the cook customer retention analytics dashboard.
     *
     * GET /dashboard/analytics/customers
     */
    public function index(Request $request): mixed
    {
        $tenant = tenant();

        // Authorization: cook role or can-view-cook-analytics permission
        $this->authorizeAnalyticsAccess($request);

        // Resolve period and date range
        $period = $this->resolvePeriod($request->input('period', 'last_6_months'));
        $customStart = $request->input('custom_start');
        $customEnd = $request->input('custom_end');

        $dateRange = $this->service->resolveDateRange($period, $customStart, $customEnd);
        $start = $dateRange['start'];
        $end = $dateRange['end'];

        // Sort for top customers table
        $sortBy = $request->input('sort_by', 'total_spend');
        if (! in_array($sortBy, array_keys(CookCustomerRetentionService::SORT_OPTIONS), true)) {
            $sortBy = 'total_spend';
        }

        // Summary cards
        $summaryCards = $this->service->getSummaryCards($tenant->id, $start, $end);

        // New vs returning chart data
        $chartData = $this->service->getNewVsReturningChartData($tenant->id, $start, $end);

        // Top customers table
        $topCustomers = $this->service->getTopCustomersWithDeletedSupport($tenant->id, $sortBy);

        // CLV distribution
        $clvDistribution = $this->service->getClvDistribution($tenant->id);

        $hasData = $this->service->hasData($tenant->id);

        $viewData = [
            'period' => $period,
            'customStart' => $customStart,
            'customEnd' => $customEnd,
            'sortBy' => $sortBy,
            'rangeStart' => $start,
            'rangeEnd' => $end,
            'summaryCards' => $summaryCards,
            'chartData' => $chartData,
            'topCustomers' => $topCustomers,
            'clvDistribution' => $clvDistribution,
            'hasData' => $hasData,
            'periods' => CookCustomerRetentionService::PERIODS,
            'sortOptions' => CookCustomerRetentionService::SORT_OPTIONS,
        ];

        // Gale navigate fragment for period/sort switching without full reload
        if ($request->isGaleNavigate('analytics')) {
            return gale()->fragment('cook.analytics.customers', 'analytics-content', $viewData);
        }

        return gale()->view('cook.analytics.customers', $viewData, web: true);
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
     * Validate and return the period key; fall back to last_6_months.
     */
    private function resolvePeriod(mixed $period): string
    {
        $validPeriods = array_keys(CookCustomerRetentionService::PERIODS);

        if (! in_array($period, $validPeriods, true)) {
            return 'last_6_months';
        }

        return $period;
    }
}
