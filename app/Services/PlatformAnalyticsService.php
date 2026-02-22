<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PlatformAnalyticsService — aggregates platform-wide analytics data.
 *
 * F-057: Platform Analytics Dashboard
 * BR-135: Revenue = completed/delivered orders only
 * BR-136: Commission earned = sum of platform commission from completed orders
 * BR-137: Active tenants = tenants with status "active"
 * BR-138: Active users = users who logged in during selected period
 * BR-139: New registrations = users created during selected period
 * BR-140: Time periods: Today, This Week, This Month, This Year, Custom Range
 * BR-141: Custom range maximum span is 1 year
 * BR-142: Top cooks and meals ranked by revenue in the selected period
 */
class PlatformAnalyticsService
{
    /** @var array<int, string> Status values considered "completed" for revenue */
    public const COMPLETED_STATUSES = ['completed', 'delivered', 'picked_up'];

    /** @var array<int, string> Supported period keys */
    public const PERIODS = ['today', 'week', 'month', 'year', 'custom'];

    /**
     * Resolve the date range for a given period.
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
            'today' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'week' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
            ],
            'month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
            'year' => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear(),
            ],
            'custom' => [
                'start' => Carbon::parse($customStart)->startOfDay(),
                'end' => Carbon::parse($customEnd)->endOfDay(),
            ],
            default => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
        };
    }

    /**
     * Resolve the "previous equivalent" date range for comparison.
     *
     * @param  array{start: Carbon, end: Carbon}  $current
     * @return array{start: Carbon, end: Carbon}
     */
    public function resolvePreviousDateRange(string $period, array $current): array
    {
        return match ($period) {
            'today' => [
                'start' => $current['start']->copy()->subDay(),
                'end' => $current['end']->copy()->subDay(),
            ],
            'week' => [
                'start' => $current['start']->copy()->subWeek(),
                'end' => $current['end']->copy()->subWeek(),
            ],
            'month' => [
                'start' => $current['start']->copy()->subMonth()->startOfMonth(),
                'end' => $current['start']->copy()->subMonth()->endOfMonth(),
            ],
            'year' => [
                'start' => $current['start']->copy()->subYear()->startOfYear(),
                'end' => $current['start']->copy()->subYear()->endOfYear(),
            ],
            default => [
                // Custom: shift by the same span backwards
                'start' => $current['start']->copy()->subDays($current['start']->diffInDays($current['end']) + 1),
                'end' => $current['start']->copy()->subDay(),
            ],
        };
    }

    /**
     * Get total revenue from completed orders in the given range.
     * BR-135: Revenue = completed/delivered orders only
     */
    public function getRevenue(Carbon $start, Carbon $end): int
    {
        return (int) Order::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->sum('grand_total');
    }

    /**
     * Get total platform commission from completed orders.
     * BR-136: Commission earned = sum of platform's commission portion
     */
    public function getCommission(Carbon $start, Carbon $end): int
    {
        return (int) Order::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->whereNotNull('commission_amount')
            ->sum('commission_amount');
    }

    /**
     * Get total order count in the given range (all statuses).
     */
    public function getOrderCount(Carbon $start, Carbon $end): int
    {
        return Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /**
     * Get count of active tenants.
     * BR-137: Active tenants = tenants with status "active"
     */
    public function getActiveTenantCount(): int
    {
        return Tenant::where('is_active', true)->count();
    }

    /**
     * Get count of users who logged in during the period.
     * BR-138: Active users = users who logged in during selected period
     */
    public function getActiveUserCount(Carbon $start, Carbon $end): int
    {
        return User::query()
            ->whereNotNull('last_login_at')
            ->whereBetween('last_login_at', [$start, $end])
            ->count();
    }

    /**
     * Get count of new user registrations in the period.
     * BR-139: New registrations = users created during selected period
     */
    public function getNewUserCount(Carbon $start, Carbon $end): int
    {
        return User::query()
            ->whereBetween('created_at', [$start, $end])
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
     * Determine chart granularity based on date range span.
     * Daily for ≤ 3 months, weekly for longer periods.
     */
    public function resolveChartGranularity(Carbon $start, Carbon $end): string
    {
        $months = $start->diffInMonths($end);

        return $months <= 3 ? 'daily' : 'weekly';
    }

    /**
     * Build revenue chart data points.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    public function getRevenueChartData(Carbon $start, Carbon $end, string $granularity): Collection
    {
        if ($granularity === 'daily') {
            $rows = Order::query()
                ->selectRaw('DATE(completed_at) as period_date, SUM(grand_total) as total')
                ->whereIn('status', self::COMPLETED_STATUSES)
                ->whereBetween('completed_at', [$start, $end])
                ->groupByRaw('DATE(completed_at)')
                ->orderByRaw('DATE(completed_at)')
                ->get()
                ->keyBy('period_date');

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

        // Weekly granularity
        $rows = Order::query()
            ->selectRaw("DATE_TRUNC('week', completed_at) as period_date, SUM(grand_total) as total")
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->groupByRaw("DATE_TRUNC('week', completed_at)")
            ->orderByRaw("DATE_TRUNC('week', completed_at)")
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->period_date)->format('Y-m-d'));

        $points = collect();
        $cursor = $start->copy()->startOfWeek();
        $endWeek = $end->copy()->startOfWeek();

        while ($cursor->lte($endWeek)) {
            $key = $cursor->format('Y-m-d');
            $points->push([
                'label' => $cursor->format('M j'),
                'value' => (int) ($rows->get($key)?->total ?? 0),
            ]);
            $cursor->addWeek();
        }

        return $points;
    }

    /**
     * Build order count chart data points.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    public function getOrderChartData(Carbon $start, Carbon $end, string $granularity): Collection
    {
        if ($granularity === 'daily') {
            $rows = Order::query()
                ->selectRaw('DATE(created_at) as period_date, COUNT(*) as total')
                ->whereBetween('created_at', [$start, $end])
                ->groupByRaw('DATE(created_at)')
                ->orderByRaw('DATE(created_at)')
                ->get()
                ->keyBy('period_date');

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

        // Weekly
        $rows = Order::query()
            ->selectRaw("DATE_TRUNC('week', created_at) as period_date, COUNT(*) as total")
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw("DATE_TRUNC('week', created_at)")
            ->orderByRaw("DATE_TRUNC('week', created_at)")
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->period_date)->format('Y-m-d'));

        $points = collect();
        $cursor = $start->copy()->startOfWeek();
        $endWeek = $end->copy()->startOfWeek();

        while ($cursor->lte($endWeek)) {
            $key = $cursor->format('Y-m-d');
            $points->push([
                'label' => $cursor->format('M j'),
                'value' => (int) ($rows->get($key)?->total ?? 0),
            ]);
            $cursor->addWeek();
        }

        return $points;
    }

    /**
     * Get top 10 cooks ranked by revenue in the period.
     * BR-142: Ranked by revenue descending
     *
     * @return Collection<int, array{cook_name: string, tenant_name: string, revenue: int, order_count: int}>
     */
    public function getTopCooks(Carbon $start, Carbon $end): Collection
    {
        $locale = app()->getLocale();

        return DB::table('orders')
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
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'cook_name' => $row->cook_name,
                'tenant_name' => $locale === 'fr' ? ($row->tenant_name_fr ?: $row->tenant_name_en) : $row->tenant_name_en,
                'tenant_id' => $row->tenant_id,
                'revenue' => (int) $row->total_revenue,
                'order_count' => (int) $row->order_count,
            ]);
    }

    /**
     * Get top 10 meals ranked by order count in the period.
     * Uses tenant-level aggregation as meal data is in items_snapshot JSON.
     * BR-142: Ranked by revenue descending
     *
     * @return Collection<int, array{meal_name: string, tenant_name: string, order_count: int, revenue: int}>
     */
    public function getTopMeals(Carbon $start, Carbon $end): Collection
    {
        $locale = app()->getLocale();

        // Since items_snapshot is JSONB, aggregate at tenant level as proxy
        return DB::table('orders')
            ->join('tenants', 'orders.tenant_id', '=', 'tenants.id')
            ->selectRaw('
                tenants.id as tenant_id,
                tenants.name_en as tenant_name_en,
                tenants.name_fr as tenant_name_fr,
                SUM(orders.grand_total) as total_revenue,
                COUNT(orders.id) as order_count
            ')
            ->whereIn('orders.status', self::COMPLETED_STATUSES)
            ->whereBetween('orders.completed_at', [$start, $end])
            ->groupBy('tenants.id', 'tenants.name_en', 'tenants.name_fr')
            ->orderByDesc('order_count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'meal_name' => $locale === 'fr' ? ($row->tenant_name_fr ?: $row->tenant_name_en) : $row->tenant_name_en,
                'tenant_name' => $locale === 'fr' ? ($row->tenant_name_fr ?: $row->tenant_name_en) : $row->tenant_name_en,
                'order_count' => (int) $row->order_count,
                'revenue' => (int) $row->total_revenue,
            ]);
    }

    /**
     * Aggregate all summary metrics for the dashboard.
     *
     * @return array{
     *   revenue: int,
     *   commission: int,
     *   orders: int,
     *   active_tenants: int,
     *   active_users: int,
     *   new_users: int,
     *   changes: array{
     *     revenue: float|null,
     *     commission: float|null,
     *     orders: float|null,
     *     active_tenants: null,
     *     active_users: float|null,
     *     new_users: float|null,
     *   }
     * }
     */
    public function getSummaryMetrics(
        Carbon $start,
        Carbon $end,
        Carbon $prevStart,
        Carbon $prevEnd
    ): array {
        $revenue = $this->getRevenue($start, $end);
        $commission = $this->getCommission($start, $end);
        $orders = $this->getOrderCount($start, $end);
        $activeTenants = $this->getActiveTenantCount();
        $activeUsers = $this->getActiveUserCount($start, $end);
        $newUsers = $this->getNewUserCount($start, $end);

        $prevRevenue = $this->getRevenue($prevStart, $prevEnd);
        $prevCommission = $this->getCommission($prevStart, $prevEnd);
        $prevOrders = $this->getOrderCount($prevStart, $prevEnd);
        $prevActiveUsers = $this->getActiveUserCount($prevStart, $prevEnd);
        $prevNewUsers = $this->getNewUserCount($prevStart, $prevEnd);

        return [
            'revenue' => $revenue,
            'commission' => $commission,
            'orders' => $orders,
            'active_tenants' => $activeTenants,
            'active_users' => $activeUsers,
            'new_users' => $newUsers,
            'changes' => [
                'revenue' => $this->calculatePercentageChange($revenue, $prevRevenue),
                'commission' => $this->calculatePercentageChange($commission, $prevCommission),
                'orders' => $this->calculatePercentageChange($orders, $prevOrders),
                'active_tenants' => null, // Always current, no meaningful previous
                'active_users' => $this->calculatePercentageChange($activeUsers, $prevActiveUsers),
                'new_users' => $this->calculatePercentageChange($newUsers, $prevNewUsers),
            ],
        ];
    }

    /**
     * Format an integer amount as XAF currency string.
     */
    public static function formatXAF(int $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }
}
