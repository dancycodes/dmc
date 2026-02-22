<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AdminGrowthMetricsService — platform growth analytics for admin panel.
 *
 * F-207: Admin Growth Metrics
 * BR-440: Only admin and super-admin roles can access growth metrics
 * BR-441: New users = users created within the selected period
 * BR-442: New cooks = tenants created within the selected period
 * BR-443: Order volume = total orders placed within the selected period
 * BR-444: Active users = distinct users who placed at least one order in the last 30 days
 * BR-445: Date range options: Last 3 Months, Last 6 Months, This Year, Last Year, All Time
 * BR-446: Comparison shows growth percentage vs previous period
 * BR-447: Milestone thresholds predefined for users, cooks, orders
 * BR-448: All user-facing text must use __() localization
 */
class AdminGrowthMetricsService
{
    /**
     * Supported period keys mapped to display labels.
     *
     * BR-445: Last 3 Months, Last 6 Months, This Year, Last Year, All Time
     *
     * @var array<string, string>
     */
    public const PERIODS = [
        'last_3_months' => 'Last 3 Months',
        'last_6_months' => 'Last 6 Months',
        'this_year' => 'This Year',
        'last_year' => 'Last Year',
        'all_time' => 'All Time',
    ];

    /**
     * Milestone thresholds for users.
     *
     * BR-447: 100, 500, 1000, 5000, 10000 for users
     *
     * @var array<int>
     */
    public const USER_MILESTONES = [100, 500, 1000, 5000, 10000];

    /**
     * Milestone thresholds for cooks.
     *
     * BR-447: 10, 50, 100 for cooks
     *
     * @var array<int>
     */
    public const COOK_MILESTONES = [10, 50, 100];

    /**
     * Milestone thresholds for orders.
     *
     * BR-447: 1000, 10000, 100000 for orders
     *
     * @var array<int>
     */
    public const ORDER_MILESTONES = [1000, 10000, 100000];

    /**
     * Resolve the date range for the given period.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function resolveDateRange(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
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
            'all_time' => [
                'start' => Carbon::parse('2020-01-01')->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            default => [
                'start' => $now->copy()->subMonths(6)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
        };
    }

    /**
     * Resolve the "previous equivalent" date range for comparison mode.
     *
     * BR-446: Comparison shows current vs previous equivalent period.
     *
     * @param  array{start: Carbon, end: Carbon}  $current
     * @return array{start: Carbon, end: Carbon}
     */
    public function resolvePreviousDateRange(string $period, array $current): array
    {
        return match ($period) {
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
                // All time: no meaningful previous — return same start/end
                'start' => $current['start']->copy(),
                'end' => $current['end']->copy(),
            ],
        };
    }

    /**
     * Get summary card data for the overview section.
     *
     * @return array{
     *   total_users: int,
     *   total_cooks: int,
     *   orders_this_month: int,
     *   active_users_30d: int,
     *   new_users: int,
     *   new_cooks: int,
     *   prev_new_users: int,
     *   prev_new_cooks: int,
     *   changes: array{new_users: float|null, new_cooks: float|null}
     * }
     */
    public function getSummaryCards(
        Carbon $start,
        Carbon $end,
        Carbon $prevStart,
        Carbon $prevEnd
    ): array {
        $now = Carbon::now();

        $totalUsers = User::query()->count();
        $totalCooks = Tenant::query()->whereNotNull('cook_id')->count();

        $ordersThisMonth = Order::query()
            ->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->count();

        // BR-444: Active users = distinct users who placed at least one order in last 30 days
        $activeUsers30d = Order::query()
            ->whereBetween('created_at', [$now->copy()->subDays(30), $now])
            ->distinct('client_id')
            ->count('client_id');

        $newUsers = User::query()->whereBetween('created_at', [$start, $end])->count();
        $newCooks = Tenant::query()
            ->whereNotNull('cook_id')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $prevNewUsers = User::query()->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $prevNewCooks = Tenant::query()
            ->whereNotNull('cook_id')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        return [
            'total_users' => $totalUsers,
            'total_cooks' => $totalCooks,
            'orders_this_month' => $ordersThisMonth,
            'active_users_30d' => $activeUsers30d,
            'new_users' => $newUsers,
            'new_cooks' => $newCooks,
            'prev_new_users' => $prevNewUsers,
            'prev_new_cooks' => $prevNewCooks,
            'changes' => [
                'new_users' => $this->calculatePercentageChange($newUsers, $prevNewUsers),
                'new_cooks' => $this->calculatePercentageChange($newCooks, $prevNewCooks),
            ],
        ];
    }

    /**
     * Build monthly new user registration chart data.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    public function getNewUsersChartData(Carbon $start, Carbon $end): Collection
    {
        return $this->buildMonthlyCountData($start, $end, 'users', 'created_at');
    }

    /**
     * Build monthly new cook/tenant chart data.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    public function getNewCooksChartData(Carbon $start, Carbon $end): Collection
    {
        $rows = DB::table('tenants')
            ->selectRaw("DATE_TRUNC('month', created_at) as period_date, COUNT(*) as total")
            ->whereNotNull('cook_id')
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw("DATE_TRUNC('month', created_at)")
            ->orderByRaw("DATE_TRUNC('month', created_at)")
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->period_date)->format('Y-m-01'));

        return $this->buildMonthlyPointsFromRows($rows, $start, $end);
    }

    /**
     * Build monthly order volume chart data.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    public function getOrderVolumeChartData(Carbon $start, Carbon $end): Collection
    {
        return $this->buildMonthlyCountData($start, $end, 'orders', 'created_at');
    }

    /**
     * Build monthly active users chart data.
     * Active users per month = distinct users who placed at least one order that month.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    public function getActiveUsersChartData(Carbon $start, Carbon $end): Collection
    {
        $rows = DB::table('orders')
            ->selectRaw("DATE_TRUNC('month', created_at) as period_date, COUNT(DISTINCT client_id) as total")
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw("DATE_TRUNC('month', created_at)")
            ->orderByRaw("DATE_TRUNC('month', created_at)")
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->period_date)->format('Y-m-01'));

        return $this->buildMonthlyPointsFromRows($rows, $start, $end);
    }

    /**
     * Get milestones that have been achieved.
     *
     * BR-447: Milestone thresholds for users, cooks, orders.
     *
     * @return Collection<int, array{type: string, threshold: int, achieved_at: string|null, label: string, icon: string}>
     */
    public function getMilestones(): Collection
    {
        $totalUsers = User::query()->count();
        $totalCooks = Tenant::query()->whereNotNull('cook_id')->count();
        $totalOrders = Order::query()->count();

        $milestones = collect();

        foreach (self::USER_MILESTONES as $threshold) {
            if ($totalUsers >= $threshold) {
                $achievedAt = $this->findMilestoneDate('users', 'created_at', $threshold);
                $milestones->push([
                    'type' => 'users',
                    'threshold' => $threshold,
                    'achieved_at' => $achievedAt,
                    'label' => __(':count users', ['count' => number_format($threshold)]),
                    'icon' => 'users',
                ]);
            }
        }

        foreach (self::COOK_MILESTONES as $threshold) {
            if ($totalCooks >= $threshold) {
                $achievedAt = $this->findMilestoneDateForCooks($threshold);
                $milestones->push([
                    'type' => 'cooks',
                    'threshold' => $threshold,
                    'achieved_at' => $achievedAt,
                    'label' => __(':count cooks', ['count' => number_format($threshold)]),
                    'icon' => 'chef-hat',
                ]);
            }
        }

        foreach (self::ORDER_MILESTONES as $threshold) {
            if ($totalOrders >= $threshold) {
                $achievedAt = $this->findMilestoneDate('orders', 'created_at', $threshold);
                $milestones->push([
                    'type' => 'orders',
                    'threshold' => $threshold,
                    'achieved_at' => $achievedAt,
                    'label' => __(':count orders', ['count' => number_format($threshold)]),
                    'icon' => 'shopping-bag',
                ]);
            }
        }

        return $milestones->sortByDesc('achieved_at')->values();
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
     * Build monthly count data for a given table.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    private function buildMonthlyCountData(
        Carbon $start,
        Carbon $end,
        string $table,
        string $dateColumn
    ): Collection {
        $rows = DB::table($table)
            ->selectRaw("DATE_TRUNC('month', {$dateColumn}) as period_date, COUNT(*) as total")
            ->whereBetween($dateColumn, [$start, $end])
            ->groupByRaw("DATE_TRUNC('month', {$dateColumn})")
            ->orderByRaw("DATE_TRUNC('month', {$dateColumn})")
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->period_date)->format('Y-m-01'));

        return $this->buildMonthlyPointsFromRows($rows, $start, $end);
    }

    /**
     * Build a complete monthly point series filling gaps with zeros.
     *
     * @param  \Illuminate\Support\Collection<string, object>  $rows
     * @return Collection<int, array{label: string, value: int}>
     */
    private function buildMonthlyPointsFromRows(Collection $rows, Carbon $start, Carbon $end): Collection
    {
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

    /**
     * Find the approximate date when a cumulative milestone was first reached for a given table.
     * Uses offset to find the Nth record's created_at date.
     */
    private function findMilestoneDate(string $table, string $dateColumn, int $threshold): ?string
    {
        $row = DB::table($table)
            ->orderBy($dateColumn)
            ->offset($threshold - 1)
            ->limit(1)
            ->value($dateColumn);

        if ($row === null) {
            return null;
        }

        return Carbon::parse($row)->format('M j, Y');
    }

    /**
     * Find the date when the Nth cook (tenant with cook_id) joined.
     */
    private function findMilestoneDateForCooks(int $threshold): ?string
    {
        $row = DB::table('tenants')
            ->whereNotNull('cook_id')
            ->orderBy('created_at')
            ->offset($threshold - 1)
            ->limit(1)
            ->value('created_at');

        if ($row === null) {
            return null;
        }

        return Carbon::parse($row)->format('M j, Y');
    }
}
