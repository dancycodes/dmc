<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AdminRevenueAnalyticsService — platform-wide revenue analytics for admin dashboard.
 *
 * F-205: Admin Platform Revenue Analytics
 * BR-417: Admin and super-admin only access
 * BR-418: Platform revenue = sum of all completed order totals across all tenants
 * BR-419: Commission earned = platform's commission portion from all completed orders
 * BR-420: Active cooks = tenants with at least one completed order in the selected period
 * BR-421: Transaction count = number of completed payments in the selected period
 * BR-422: Revenue by cook shows top 10; rest grouped as "Others"
 * BR-423: Revenue by region derived from tenant's primary location (town)
 * BR-424: Periods: This Month, Last 3 Months, Last 6 Months, This Year, Last Year, Custom
 * BR-425: Comparison mode shows current vs previous equivalent period
 * BR-426: All amounts in XAF format
 * BR-427: All user-facing text via __() localization
 */
class AdminRevenueAnalyticsService
{
    /** @var array<int, string> Order statuses considered as completed/revenue-generating */
    public const COMPLETED_STATUSES = [
        Order::STATUS_COMPLETED,
        Order::STATUS_DELIVERED,
        Order::STATUS_PICKED_UP,
    ];

    /**
     * Supported period keys mapped to display labels.
     *
     * BR-424: This Month, Last 3 Months, Last 6 Months, This Year, Last Year, Custom
     *
     * @var array<string, string>
     */
    public const PERIODS = [
        'this_month' => 'This Month',
        'last_3_months' => 'Last 3 Months',
        'last_6_months' => 'Last 6 Months',
        'this_year' => 'This Year',
        'last_year' => 'Last Year',
        'custom' => 'Custom',
    ];

    /**
     * Resolve the date range for the given period.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function resolveDateRange(
        string $period,
        ?string $customStart = null,
        ?string $customEnd = null
    ): array {
        $now = Carbon::now();

        return match ($period) {
            'this_month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
            'last_3_months' => [
                'start' => $now->copy()->subMonths(3)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'last_6_months' => [
                'start' => $now->copy()->subMonths(6)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'this_year' => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear(),
            ],
            'last_year' => [
                'start' => $now->copy()->subYear()->startOfYear(),
                'end' => $now->copy()->subYear()->endOfYear(),
            ],
            'custom' => [
                'start' => Carbon::parse($customStart)->startOfDay(),
                'end' => Carbon::parse($customEnd)->endOfDay(),
            ],
            default => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
        };
    }

    /**
     * Resolve the "previous equivalent" date range for comparison mode.
     *
     * BR-425: Comparison shows current vs previous equivalent period.
     *
     * @param  array{start: Carbon, end: Carbon}  $current
     * @return array{start: Carbon, end: Carbon}
     */
    public function resolvePreviousDateRange(string $period, array $current): array
    {
        return match ($period) {
            'this_month' => [
                'start' => $current['start']->copy()->subMonth()->startOfMonth(),
                'end' => $current['start']->copy()->subMonth()->endOfMonth(),
            ],
            'last_3_months' => [
                'start' => $current['start']->copy()->subMonths(3),
                'end' => $current['start']->copy()->subDay()->endOfDay(),
            ],
            'last_6_months' => [
                'start' => $current['start']->copy()->subMonths(6),
                'end' => $current['start']->copy()->subDay()->endOfDay(),
            ],
            'this_year' => [
                'start' => $current['start']->copy()->subYear()->startOfYear(),
                'end' => $current['start']->copy()->subYear()->endOfYear(),
            ],
            'last_year' => [
                'start' => $current['start']->copy()->subYear()->startOfYear(),
                'end' => $current['start']->copy()->subYear()->endOfYear(),
            ],
            default => [
                // Custom: shift backwards by the same span
                'start' => $current['start']->copy()->subDays(
                    $current['start']->diffInDays($current['end']) + 1
                ),
                'end' => $current['start']->copy()->subDay()->endOfDay(),
            ],
        };
    }

    /**
     * Determine chart granularity based on date range span.
     * Daily for <= 31 days, monthly for longer periods.
     */
    public function resolveGranularity(Carbon $start, Carbon $end): string
    {
        $days = $start->diffInDays($end);

        if ($days <= 31) {
            return 'daily';
        }

        return 'monthly';
    }

    /**
     * Get total platform revenue from completed orders in the given range.
     * BR-418: Revenue = completed/delivered/picked_up orders only.
     */
    public function getTotalRevenue(Carbon $start, Carbon $end): int
    {
        return (int) Order::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->sum('grand_total');
    }

    /**
     * Get total platform commission from completed orders in the given range.
     * BR-419: Commission = platform's commission portion.
     */
    public function getTotalCommission(Carbon $start, Carbon $end): int
    {
        return (int) Order::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->whereNotNull('commission_amount')
            ->sum('commission_amount');
    }

    /**
     * Get the count of active cooks (tenants with at least one completed order) in the period.
     * BR-420: Active cooks = tenants with at least one completed order in the selected period.
     */
    public function getActiveCookCount(Carbon $start, Carbon $end): int
    {
        return (int) Order::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->whereNotNull('tenant_id')
            ->distinct('tenant_id')
            ->count('tenant_id');
    }

    /**
     * Get the count of completed transactions (payments) in the period.
     * BR-421: Transaction count = number of completed payments in the selected period.
     */
    public function getTransactionCount(Carbon $start, Carbon $end): int
    {
        return Order::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->count();
    }

    /**
     * Calculate percentage change between current and previous values.
     */
    public function calculatePercentageChange(int|float $current, int|float $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Build revenue chart data points grouped by granularity.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    public function getRevenueChartData(Carbon $start, Carbon $end, string $granularity): Collection
    {
        if ($granularity === 'daily') {
            return $this->buildDailyChartData($start, $end, 'grand_total', 'completed_at');
        }

        return $this->buildMonthlyChartData($start, $end, 'grand_total', 'completed_at');
    }

    /**
     * Build commission chart data points grouped by granularity.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    public function getCommissionChartData(Carbon $start, Carbon $end, string $granularity): Collection
    {
        if ($granularity === 'daily') {
            return $this->buildDailyChartData($start, $end, 'commission_amount', 'completed_at', withNotNull: true);
        }

        return $this->buildMonthlyChartData($start, $end, 'commission_amount', 'completed_at', withNotNull: true);
    }

    /**
     * Get revenue broken down by cook (top 10, rest as "Others").
     * BR-422: Top 10; rest grouped as "Others".
     *
     * @return Collection<int, array{cook_name: string, tenant_name: string, revenue: int, is_others: bool}>
     */
    public function getRevenueByCoook(Carbon $start, Carbon $end): Collection
    {
        $locale = app()->getLocale();

        $rows = DB::table('orders')
            ->join('tenants', 'orders.tenant_id', '=', 'tenants.id')
            ->join('users', 'tenants.cook_id', '=', 'users.id')
            ->selectRaw('
                users.name as cook_name,
                tenants.name_en as tenant_name_en,
                tenants.name_fr as tenant_name_fr,
                tenants.id as tenant_id,
                SUM(orders.grand_total) as total_revenue,
                COUNT(orders.id) as order_count
            ')
            ->whereIn('orders.status', self::COMPLETED_STATUSES)
            ->whereBetween('orders.completed_at', [$start, $end])
            ->whereNotNull('tenants.cook_id')
            ->groupBy('users.name', 'tenants.name_en', 'tenants.name_fr', 'tenants.id')
            ->orderByDesc('total_revenue')
            ->get();

        $top10 = $rows->take(10);
        $others = $rows->skip(10);

        $result = $top10->map(fn ($row) => [
            'cook_name' => $row->cook_name,
            'tenant_name' => $locale === 'fr'
                ? ($row->tenant_name_fr ?: $row->tenant_name_en)
                : $row->tenant_name_en,
            'tenant_id' => $row->tenant_id,
            'revenue' => (int) $row->total_revenue,
            'order_count' => (int) $row->order_count,
            'is_others' => false,
        ]);

        if ($others->isNotEmpty()) {
            $result->push([
                'cook_name' => __('Others'),
                'tenant_name' => '',
                'tenant_id' => null,
                'revenue' => (int) $others->sum('total_revenue'),
                'order_count' => (int) $others->sum('order_count'),
                'is_others' => true,
            ]);
        }

        return $result;
    }

    /**
     * Get revenue broken down by region/town.
     * BR-423: Revenue by region derived from tenant's primary location (town from orders).
     *
     * @return Collection<int, array{region: string, revenue: int, order_count: int}>
     */
    public function getRevenueByRegion(Carbon $start, Carbon $end): Collection
    {
        $locale = app()->getLocale();

        $withTown = DB::table('orders')
            ->join('towns', 'orders.town_id', '=', 'towns.id')
            ->selectRaw('
                towns.name_en as region_en,
                towns.name_fr as region_fr,
                SUM(orders.grand_total) as total_revenue,
                COUNT(orders.id) as order_count
            ')
            ->whereIn('orders.status', self::COMPLETED_STATUSES)
            ->whereBetween('orders.completed_at', [$start, $end])
            ->whereNotNull('orders.town_id')
            ->groupBy('towns.name_en', 'towns.name_fr')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(fn ($row) => [
                'region' => $locale === 'fr'
                    ? ($row->region_fr ?: $row->region_en)
                    : $row->region_en,
                'revenue' => (int) $row->total_revenue,
                'order_count' => (int) $row->order_count,
                'is_unknown' => false,
            ]);

        // Orders with no town (pickup orders or missing town data) → "Unknown Region"
        $unknownRevenue = (int) Order::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->whereNull('town_id')
            ->sum('grand_total');

        $unknownCount = Order::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->whereNull('town_id')
            ->count();

        if ($unknownRevenue > 0 || $unknownCount > 0) {
            $withTown->push([
                'region' => __('Unknown Region'),
                'revenue' => $unknownRevenue,
                'order_count' => $unknownCount,
                'is_unknown' => true,
            ]);
        }

        return $withTown;
    }

    /**
     * Get all summary metrics for the revenue analytics page.
     *
     * @return array{
     *   revenue: int,
     *   commission: int,
     *   active_cooks: int,
     *   transaction_count: int,
     *   prev_revenue: int,
     *   prev_commission: int,
     *   prev_active_cooks: int,
     *   prev_transaction_count: int,
     *   changes: array{
     *     revenue: float|null,
     *     commission: float|null,
     *     active_cooks: float|null,
     *     transaction_count: float|null,
     *   }
     * }
     */
    public function getSummaryCards(
        Carbon $start,
        Carbon $end,
        Carbon $prevStart,
        Carbon $prevEnd
    ): array {
        $revenue = $this->getTotalRevenue($start, $end);
        $commission = $this->getTotalCommission($start, $end);
        $activeCooks = $this->getActiveCookCount($start, $end);
        $transactionCount = $this->getTransactionCount($start, $end);

        $prevRevenue = $this->getTotalRevenue($prevStart, $prevEnd);
        $prevCommission = $this->getTotalCommission($prevStart, $prevEnd);
        $prevActiveCooks = $this->getActiveCookCount($prevStart, $prevEnd);
        $prevTransactionCount = $this->getTransactionCount($prevStart, $prevEnd);

        return [
            'revenue' => $revenue,
            'commission' => $commission,
            'active_cooks' => $activeCooks,
            'transaction_count' => $transactionCount,
            'prev_revenue' => $prevRevenue,
            'prev_commission' => $prevCommission,
            'prev_active_cooks' => $prevActiveCooks,
            'prev_transaction_count' => $prevTransactionCount,
            'changes' => [
                'revenue' => $this->calculatePercentageChange($revenue, $prevRevenue),
                'commission' => $this->calculatePercentageChange($commission, $prevCommission),
                'active_cooks' => $this->calculatePercentageChange($activeCooks, $prevActiveCooks),
                'transaction_count' => $this->calculatePercentageChange($transactionCount, $prevTransactionCount),
            ],
        ];
    }

    /**
     * Format an integer amount as XAF currency string.
     * BR-426: All amounts in XAF format.
     */
    public static function formatXAF(int $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }

    /**
     * Build daily chart data for a given aggregate column.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    private function buildDailyChartData(
        Carbon $start,
        Carbon $end,
        string $column,
        string $dateColumn,
        bool $withNotNull = false
    ): Collection {
        $query = Order::query()
            ->selectRaw("DATE({$dateColumn}) as period_date, SUM({$column}) as total")
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween($dateColumn, [$start, $end])
            ->groupByRaw("DATE({$dateColumn})")
            ->orderByRaw("DATE({$dateColumn})");

        if ($withNotNull) {
            $query->whereNotNull($column);
        }

        $rows = $query->get()->keyBy('period_date');

        $points = collect();
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        while ($cursor->lte($endDay)) {
            $key = $cursor->format('Y-m-d');
            $points->push([
                'label' => $cursor->format('M j'),
                'value' => (int) ($rows->get($key)?->total ?? 0),
            ]);
            $cursor->addDay();
        }

        return $points;
    }

    /**
     * Build monthly chart data for a given aggregate column.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    private function buildMonthlyChartData(
        Carbon $start,
        Carbon $end,
        string $column,
        string $dateColumn,
        bool $withNotNull = false
    ): Collection {
        $query = Order::query()
            ->selectRaw("DATE_TRUNC('month', {$dateColumn}) as period_date, SUM({$column}) as total")
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween($dateColumn, [$start, $end])
            ->groupByRaw("DATE_TRUNC('month', {$dateColumn})")
            ->orderByRaw("DATE_TRUNC('month', {$dateColumn})");

        if ($withNotNull) {
            $query->whereNotNull($column);
        }

        $rows = $query->get()->keyBy(
            fn ($r) => \Carbon\Carbon::parse($r->period_date)->format('Y-m-01')
        );

        $points = collect();
        $cursor = $start->copy()->startOfMonth();
        $endMonth = $end->copy()->startOfMonth();

        while ($cursor->lte($endMonth)) {
            $key = $cursor->format('Y-m-01');
            $points->push([
                'label' => $cursor->format('M Y'),
                'value' => (int) ($rows->get($key)?->total ?? 0),
            ]);
            $cursor->addMonth();
        }

        return $points;
    }
}
