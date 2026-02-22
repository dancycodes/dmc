<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CookRevenueAnalyticsService — tenant-scoped revenue analytics for the cook dashboard.
 *
 * F-200: Cook Revenue Analytics
 *
 * BR-368: Revenue data is tenant-scoped — only the cook's own orders.
 * BR-369: Revenue is from completed/delivered/picked_up orders only.
 * BR-370: Revenue amounts are the cook's portion (after platform commission deduction).
 * BR-371: Default view shows the current month with daily granularity.
 * BR-372: Date range options: Today, This Week, This Month, Last 3 Months, Last 6 Months, This Year, Custom.
 * BR-373: Granularity auto-adjusts: daily for <= 31 days, weekly for <= 6 months, monthly for > 6 months.
 * BR-374: Revenue by meal chart shows top 10 meals; remaining grouped as "Others".
 * BR-375: Comparison shows the equivalent previous period.
 * BR-376: All amounts displayed in XAF format.
 * BR-377: Charts update via Gale when date range changes.
 * BR-378: All user-facing text uses __() localization.
 */
class CookRevenueAnalyticsService
{
    /** @var array<int, string> Status values that count as completed revenue */
    public const COMPLETED_STATUSES = [
        Order::STATUS_COMPLETED,
        Order::STATUS_DELIVERED,
        Order::STATUS_PICKED_UP,
    ];

    /** @var array<string, string> Supported period keys */
    public const PERIODS = [
        'today' => 'Today',
        'this_week' => 'This Week',
        'this_month' => 'This Month',
        'last_3_months' => 'Last 3 Months',
        'last_6_months' => 'Last 6 Months',
        'this_year' => 'This Year',
        'custom' => 'Custom',
    ];

    /**
     * Resolve the date range for the given period key.
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
            'this_week' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
            ],
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
     * Resolve the equivalent previous period for comparison.
     *
     * BR-375: Comparison shows the equivalent previous period.
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
            'this_week' => [
                'start' => $current['start']->copy()->subWeek(),
                'end' => $current['end']->copy()->subWeek(),
            ],
            'this_month' => [
                'start' => $current['start']->copy()->subMonth()->startOfMonth(),
                'end' => $current['start']->copy()->subMonth()->endOfMonth(),
            ],
            'last_3_months' => [
                'start' => $current['start']->copy()->subMonths(3),
                'end' => $current['start']->copy()->subDay(),
            ],
            'last_6_months' => [
                'start' => $current['start']->copy()->subMonths(6),
                'end' => $current['start']->copy()->subDay(),
            ],
            'this_year' => [
                'start' => $current['start']->copy()->subYear()->startOfYear(),
                'end' => $current['start']->copy()->subYear()->endOfYear(),
            ],
            default => [
                // Custom: shift backwards by the same span
                'start' => $current['start']->copy()->subDays($current['start']->diffInDays($current['end']) + 1),
                'end' => $current['start']->copy()->subDay(),
            ],
        };
    }

    /**
     * Determine chart granularity based on date range span.
     *
     * BR-373: Daily for <= 31 days, weekly for <= 6 months, monthly for > 6 months.
     */
    public function resolveGranularity(Carbon $start, Carbon $end): string
    {
        $days = $start->diffInDays($end);

        if ($days <= 31) {
            return 'daily';
        }

        if ($days <= 183) {
            return 'weekly';
        }

        return 'monthly';
    }

    /**
     * Get total net revenue for the tenant in the given date range.
     *
     * BR-369: Completed/delivered/picked_up orders only.
     * BR-370: Net revenue = grand_total - commission_amount.
     */
    public function getTotalRevenue(int $tenantId, Carbon $start, Carbon $end): int
    {
        $result = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('SUM(grand_total - COALESCE(commission_amount, 0)) as net')
            ->value('net');

        return (int) $result;
    }

    /**
     * Get summary cards: total (all time), this month, this week, today.
     *
     * @return array{total: int, this_month: int, this_week: int, today: int}
     */
    public function getSummaryCards(int $tenantId): array
    {
        $now = Carbon::now();

        $base = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES);

        $total = (int) (clone $base)
            ->selectRaw('SUM(grand_total - COALESCE(commission_amount, 0)) as net')
            ->value('net');

        $thisMonth = (int) (clone $base)
            ->whereBetween('completed_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->selectRaw('SUM(grand_total - COALESCE(commission_amount, 0)) as net')
            ->value('net');

        $thisWeek = (int) (clone $base)
            ->whereBetween('completed_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])
            ->selectRaw('SUM(grand_total - COALESCE(commission_amount, 0)) as net')
            ->value('net');

        $today = (int) (clone $base)
            ->whereBetween('completed_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->selectRaw('SUM(grand_total - COALESCE(commission_amount, 0)) as net')
            ->value('net');

        return compact('total', 'thisMonth', 'thisWeek', 'today');
    }

    /**
     * Build revenue chart data points for the given period and granularity.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    public function getRevenueChartData(int $tenantId, Carbon $start, Carbon $end, string $granularity): Collection
    {
        return match ($granularity) {
            'daily' => $this->getDailyChartData($tenantId, $start, $end),
            'weekly' => $this->getWeeklyChartData($tenantId, $start, $end),
            default => $this->getMonthlyChartData($tenantId, $start, $end),
        };
    }

    /**
     * Get daily revenue data points.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    private function getDailyChartData(int $tenantId, Carbon $start, Carbon $end): Collection
    {
        $rows = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('DATE(completed_at) as period_date, SUM(grand_total - COALESCE(commission_amount, 0)) as net')
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
                'value' => (int) ($rows->get($key)?->net ?? 0),
            ]);
            $cursor->addDay();
        }

        return $points;
    }

    /**
     * Get weekly revenue data points.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    private function getWeeklyChartData(int $tenantId, Carbon $start, Carbon $end): Collection
    {
        $rows = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw("DATE_TRUNC('week', completed_at) as period_date, SUM(grand_total - COALESCE(commission_amount, 0)) as net")
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
                'value' => (int) ($rows->get($key)?->net ?? 0),
            ]);
            $cursor->addWeek();
        }

        return $points;
    }

    /**
     * Get monthly revenue data points.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    private function getMonthlyChartData(int $tenantId, Carbon $start, Carbon $end): Collection
    {
        $rows = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw("DATE_TRUNC('month', completed_at) as period_date, SUM(grand_total - COALESCE(commission_amount, 0)) as net")
            ->groupByRaw("DATE_TRUNC('month', completed_at)")
            ->orderByRaw("DATE_TRUNC('month', completed_at)")
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->period_date)->format('Y-m'));

        $points = collect();
        $cursor = $start->copy()->startOfMonth();
        $endMonth = $end->copy()->startOfMonth();

        while ($cursor->lte($endMonth)) {
            $key = $cursor->format('Y-m');
            $points->push([
                'label' => $cursor->format('M Y'),
                'value' => (int) ($rows->get($key)?->net ?? 0),
            ]);
            $cursor->addMonth();
        }

        return $points;
    }

    /**
     * Get revenue by meal (top 10 + "Others") from items_snapshot JSONB.
     *
     * BR-374: Top 10 meals shown; rest grouped as "Others".
     * Uses PostgreSQL jsonb_array_elements to extract meal data from items_snapshot.
     *
     * @return Collection<int, array{meal_name: string, revenue: int, order_count: int, percentage: float}>
     */
    public function getRevenueByMeal(int $tenantId, Carbon $start, Carbon $end): Collection
    {
        // Extract per-meal revenue from items_snapshot JSONB
        // items_snapshot is an array of {meal_id, meal_name, component_name, quantity, unit_price, subtotal}
        $rows = DB::select(
            "
            SELECT
                item->>'meal_name' AS meal_name,
                (item->>'meal_id')::bigint AS meal_id,
                COUNT(DISTINCT o.id) AS order_count,
                SUM((item->>'subtotal')::bigint) AS subtotal
            FROM orders o
            CROSS JOIN LATERAL jsonb_array_elements(
                CASE WHEN jsonb_typeof(o.items_snapshot) = 'array' THEN o.items_snapshot ELSE '[]'::jsonb END
            ) AS item
            WHERE o.tenant_id = :tenant_id
              AND o.status IN (:s1, :s2, :s3)
              AND o.completed_at BETWEEN :start AND :end
              AND item->>'meal_name' IS NOT NULL
            GROUP BY item->>'meal_name', (item->>'meal_id')::bigint
            ORDER BY subtotal DESC
        ",
            [
                'tenant_id' => $tenantId,
                's1' => Order::STATUS_COMPLETED,
                's2' => Order::STATUS_DELIVERED,
                's3' => Order::STATUS_PICKED_UP,
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
            ]
        );

        if (empty($rows)) {
            return collect();
        }

        // Apply commission ratio to get net per meal
        // We approximate the commission ratio from the tenant's orders
        $commissionRatio = $this->resolveCommissionRatio($tenantId, $start, $end);

        $collection = collect($rows)->map(fn ($row) => [
            'meal_name' => $row->meal_name,
            'meal_id' => (int) $row->meal_id,
            'revenue' => (int) round($row->subtotal * (1 - $commissionRatio)),
            'order_count' => (int) $row->order_count,
        ]);

        $total = $collection->sum('revenue');

        // Take top 10, group rest as "Others"
        $top10 = $collection->take(10);
        $others = $collection->skip(10);

        if ($others->isNotEmpty()) {
            $othersRevenue = $others->sum('revenue');
            $othersCount = $others->sum('order_count');
            $top10->push([
                'meal_name' => __('Others'),
                'meal_id' => 0,
                'revenue' => $othersRevenue,
                'order_count' => $othersCount,
            ]);
        }

        // Add percentage
        return $top10->map(function (array $item) use ($total) {
            $item['percentage'] = $total > 0 ? round(($item['revenue'] / $total) * 100, 1) : 0;

            return $item;
        })->values();
    }

    /**
     * Resolve the commission ratio for approximate net calculation in per-meal breakdown.
     *
     * Returns a float 0.0–1.0 representing the average commission rate.
     */
    private function resolveCommissionRatio(int $tenantId, Carbon $start, Carbon $end): float
    {
        $result = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->whereNotNull('commission_rate')
            ->avg('commission_rate');

        if ($result === null) {
            return 0.10; // Default 10% commission
        }

        return (float) $result / 100;
    }

    /**
     * Calculate percentage change between two values.
     *
     * Returns null if no meaningful comparison is possible.
     */
    public function calculatePercentageChange(int|float $current, int|float $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Format an integer amount as XAF currency string.
     *
     * BR-376: All amounts displayed in XAF format.
     */
    public static function formatXAF(int $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }

    /**
     * Check if a tenant has any completed revenue data.
     */
    public function hasRevenueData(int $tenantId): bool
    {
        return Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('completed_at')
            ->exists();
    }
}
