<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CookOrderAnalyticsService — tenant-scoped order analytics for the cook dashboard.
 *
 * F-201: Cook Order Analytics
 *
 * BR-379: Order data is tenant-scoped.
 * BR-380: All order statuses are included in the count (not just completed).
 * BR-381: Default view shows the current month.
 * BR-382: Date range options mirror F-200 (Today, This Week, This Month, etc.).
 * BR-383: Popular meals chart shows top 10 by order count.
 * BR-384: Average order value calculated from completed orders only.
 * BR-385: Peak times heatmap uses order placement time (when paid).
 * BR-386: Heatmap uses the Africa/Douala timezone for hour-of-day.
 * BR-387: Charts update via Gale when date range changes.
 * BR-388: All amounts in XAF format.
 * BR-389: All user-facing text must use __() localization.
 */
class CookOrderAnalyticsService
{
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

    /** @var array<int, string> Statuses that count as completed for average order value */
    public const COMPLETED_STATUSES = [
        Order::STATUS_COMPLETED,
        Order::STATUS_DELIVERED,
        Order::STATUS_PICKED_UP,
    ];

    /** @var array<int, string> Days of the week labels */
    public const DAY_LABELS = [
        0 => 'Sun',
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
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
     * Determine chart granularity based on date range span.
     *
     * Daily for <= 31 days, weekly for <= 183 days, monthly for > 183 days.
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
     * Get summary cards: total orders, average order value, most popular meal.
     *
     * BR-380: All statuses count for total (except pending_payment and payment_failed).
     * BR-384: Average order value from completed orders only.
     *
     * @return array{total_orders: int, avg_order_value: int, most_popular_meal: string|null, period_orders: int}
     */
    public function getSummaryCards(int $tenantId, Carbon $start, Carbon $end): array
    {
        // Total orders for the period (all statuses except payment-related pending)
        $periodOrders = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED])
            ->whereBetween('created_at', [$start, $end])
            ->count();

        // Total orders all time
        $totalOrders = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED])
            ->count();

        // Average order value from completed orders (all time)
        $avgOrderValue = (int) Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->avg('grand_total');

        // Most popular meal by order count (all time)
        $mostPopularMeal = $this->getMostPopularMealName($tenantId);

        return compact('totalOrders', 'periodOrders', 'avgOrderValue', 'mostPopularMeal');
    }

    /**
     * Build order count chart data points for the given period and granularity.
     *
     * BR-380: All statuses included (except payment-related pending).
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    public function getOrderChartData(int $tenantId, Carbon $start, Carbon $end, string $granularity): Collection
    {
        return match ($granularity) {
            'daily' => $this->getDailyOrderData($tenantId, $start, $end),
            'weekly' => $this->getWeeklyOrderData($tenantId, $start, $end),
            default => $this->getMonthlyOrderData($tenantId, $start, $end),
        };
    }

    /**
     * Get orders by status distribution for the selected period.
     *
     * BR-380: All statuses included.
     *
     * @return Collection<int, array{status: string, label: string, count: int, percentage: float, color: string}>
     */
    public function getOrdersByStatus(int $tenantId, Carbon $start, Carbon $end): Collection
    {
        $rows = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED])
            ->whereBetween('created_at', [$start, $end])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $total = $rows->sum('count');

        return $rows->map(function ($row) use ($total) {
            return [
                'status' => $row->status,
                'label' => $this->getStatusLabel($row->status),
                'count' => (int) $row->count,
                'percentage' => $total > 0 ? round(($row->count / $total) * 100, 1) : 0.0,
                'color' => $this->getStatusColor($row->status),
            ];
        })->values();
    }

    /**
     * Get popular meals by order count (top 10) from items_snapshot JSONB.
     *
     * BR-383: Popular meals chart shows top 10 by order count.
     *
     * @return Collection<int, array{meal_name: string, order_count: int, percentage: float}>
     */
    public function getPopularMeals(int $tenantId, Carbon $start, Carbon $end): Collection
    {
        // BR-380: All statuses except payment-related
        $rows = DB::select(
            "
            SELECT
                item->>'meal_name' AS meal_name,
                (item->>'meal_id')::bigint AS meal_id,
                COUNT(DISTINCT o.id) AS order_count
            FROM orders o
            CROSS JOIN LATERAL jsonb_array_elements(
                CASE WHEN jsonb_typeof(o.items_snapshot) = 'array' THEN o.items_snapshot ELSE '[]'::jsonb END
            ) AS item
            WHERE o.tenant_id = :tenant_id
              AND o.status NOT IN (:s1, :s2)
              AND o.created_at BETWEEN :start AND :end
              AND item->>'meal_name' IS NOT NULL
            GROUP BY item->>'meal_name', (item->>'meal_id')::bigint
            ORDER BY order_count DESC
            LIMIT 10
        ",
            [
                'tenant_id' => $tenantId,
                's1' => Order::STATUS_PENDING_PAYMENT,
                's2' => Order::STATUS_PAYMENT_FAILED,
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
            ]
        );

        if (empty($rows)) {
            return collect();
        }

        $collection = collect($rows)->map(fn ($row) => [
            'meal_name' => $row->meal_name,
            'meal_id' => (int) $row->meal_id,
            'order_count' => (int) $row->order_count,
        ]);

        $maxCount = $collection->max('order_count') ?: 1;

        return $collection->map(function (array $item) use ($maxCount) {
            $item['percentage'] = $maxCount > 0 ? round(($item['order_count'] / $maxCount) * 100, 1) : 0.0;

            return $item;
        })->values();
    }

    /**
     * Get peak ordering times heatmap data.
     *
     * Returns a 7x24 matrix indexed by [day_of_week][hour].
     * BR-385: Uses paid_at as the order placement timestamp.
     * BR-386: Africa/Douala timezone for hour-of-day.
     *
     * @return array{matrix: array<int, array<int, int>>, max_value: int, day_labels: array<int, string>, hour_labels: array<int, string>}
     */
    public function getPeakTimesHeatmap(int $tenantId, Carbon $start, Carbon $end): array
    {
        // Use paid_at in Africa/Douala timezone for hour extraction
        $rows = DB::select(
            "
            SELECT
                EXTRACT(DOW FROM (paid_at AT TIME ZONE 'Africa/Douala'))::int AS day_of_week,
                EXTRACT(HOUR FROM (paid_at AT TIME ZONE 'Africa/Douala'))::int AS hour_of_day,
                COUNT(*) AS order_count
            FROM orders
            WHERE tenant_id = :tenant_id
              AND paid_at IS NOT NULL
              AND paid_at BETWEEN :start AND :end
            GROUP BY day_of_week, hour_of_day
            ORDER BY day_of_week, hour_of_day
        ",
            [
                'tenant_id' => $tenantId,
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
            ]
        );

        // Initialize 7x24 matrix with zeros
        $matrix = [];
        for ($d = 0; $d <= 6; $d++) {
            $matrix[$d] = array_fill(0, 24, 0);
        }

        foreach ($rows as $row) {
            $day = (int) $row->day_of_week;
            $hour = (int) $row->hour_of_day;
            if ($day >= 0 && $day <= 6 && $hour >= 0 && $hour <= 23) {
                $matrix[$day][$hour] = (int) $row->order_count;
            }
        }

        $maxValue = max(1, max(array_map('max', $matrix)));

        // Hour labels: every 3 hours
        $hourLabels = [];
        for ($h = 0; $h < 24; $h++) {
            $hourLabels[$h] = $h % 3 === 0 ? sprintf('%02d:00', $h) : '';
        }

        return [
            'matrix' => $matrix,
            'max_value' => $maxValue,
            'day_labels' => self::DAY_LABELS,
            'hour_labels' => $hourLabels,
        ];
    }

    /**
     * Check if a tenant has any order data.
     */
    public function hasOrderData(int $tenantId): bool
    {
        return Order::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED])
            ->exists();
    }

    /**
     * Format an integer amount as XAF currency string.
     *
     * BR-388: All amounts in XAF format.
     */
    public static function formatXAF(int $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }

    /**
     * Get daily order count data points.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    private function getDailyOrderData(int $tenantId, Carbon $start, Carbon $end): Collection
    {
        $rows = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as period_date, COUNT(*) as order_count')
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
                'value' => (int) ($rows->get($key)?->order_count ?? 0),
            ]);
            $cursor->addDay();
        }

        return $points;
    }

    /**
     * Get weekly order count data points.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    private function getWeeklyOrderData(int $tenantId, Carbon $start, Carbon $end): Collection
    {
        $rows = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_TRUNC('week', created_at) as period_date, COUNT(*) as order_count")
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
                'value' => (int) ($rows->get($key)?->order_count ?? 0),
            ]);
            $cursor->addWeek();
        }

        return $points;
    }

    /**
     * Get monthly order count data points.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    private function getMonthlyOrderData(int $tenantId, Carbon $start, Carbon $end): Collection
    {
        $rows = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_TRUNC('month', created_at) as period_date, COUNT(*) as order_count")
            ->groupByRaw("DATE_TRUNC('month', created_at)")
            ->orderByRaw("DATE_TRUNC('month', created_at)")
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->period_date)->format('Y-m'));

        $points = collect();
        $cursor = $start->copy()->startOfMonth();
        $endMonth = $end->copy()->startOfMonth();

        while ($cursor->lte($endMonth)) {
            $key = $cursor->format('Y-m');
            $points->push([
                'label' => $cursor->format('M Y'),
                'value' => (int) ($rows->get($key)?->order_count ?? 0),
            ]);
            $cursor->addMonth();
        }

        return $points;
    }

    /**
     * Get the most popular meal name by order count (all time).
     */
    private function getMostPopularMealName(int $tenantId): ?string
    {
        $rows = DB::select(
            "
            SELECT item->>'meal_name' AS meal_name, COUNT(DISTINCT o.id) AS order_count
            FROM orders o
            CROSS JOIN LATERAL jsonb_array_elements(
                CASE WHEN jsonb_typeof(o.items_snapshot) = 'array' THEN o.items_snapshot ELSE '[]'::jsonb END
            ) AS item
            WHERE o.tenant_id = :tenant_id
              AND o.status NOT IN (:s1, :s2)
              AND item->>'meal_name' IS NOT NULL
            GROUP BY item->>'meal_name'
            ORDER BY order_count DESC
            LIMIT 1
        ",
            [
                'tenant_id' => $tenantId,
                's1' => Order::STATUS_PENDING_PAYMENT,
                's2' => Order::STATUS_PAYMENT_FAILED,
            ]
        );

        return $rows[0]->meal_name ?? null;
    }

    /**
     * Map order status to a human-readable label.
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            Order::STATUS_PAID => __('Paid'),
            Order::STATUS_CONFIRMED => __('Confirmed'),
            Order::STATUS_PREPARING => __('Preparing'),
            Order::STATUS_READY => __('Ready'),
            Order::STATUS_OUT_FOR_DELIVERY => __('Out for Delivery'),
            Order::STATUS_READY_FOR_PICKUP => __('Ready for Pickup'),
            Order::STATUS_DELIVERED => __('Delivered'),
            Order::STATUS_PICKED_UP => __('Picked Up'),
            Order::STATUS_COMPLETED => __('Completed'),
            Order::STATUS_CANCELLED => __('Cancelled'),
            Order::STATUS_REFUNDED => __('Refunded'),
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Map order status to a semantic color token.
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            Order::STATUS_COMPLETED, Order::STATUS_DELIVERED, Order::STATUS_PICKED_UP => 'success',
            Order::STATUS_CANCELLED => 'danger',
            Order::STATUS_REFUNDED => 'warning',
            Order::STATUS_PAID, Order::STATUS_CONFIRMED, Order::STATUS_PREPARING => 'primary',
            Order::STATUS_READY, Order::STATUS_OUT_FOR_DELIVERY, Order::STATUS_READY_FOR_PICKUP => 'secondary',
            default => 'info',
        };
    }
}
