<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CookDeliveryAnalyticsService — tenant-scoped delivery analytics for the cook dashboard.
 *
 * F-203: Cook Delivery Performance Analytics
 *
 * BR-401: Delivery data is tenant-scoped.
 * BR-402: Only completed/delivered orders with delivery addresses are included.
 * BR-403: Delivery area derived from order's delivery address (town/quarter).
 * BR-404: Pickup orders counted separately for delivery vs pickup ratio.
 * BR-405: Top delivery areas shows the top 10 locations by order count.
 * BR-406: Date range selector applies to all charts and metrics.
 * BR-407: All user-facing text must use __() localization.
 */
class CookDeliveryAnalyticsService
{
    /** @var array<int, string> Status values that count as completed */
    public const COMPLETED_STATUSES = [
        Order::STATUS_COMPLETED,
        Order::STATUS_DELIVERED,
        Order::STATUS_PICKED_UP,
    ];

    /** @var string Delivery method value */
    public const DELIVERY_METHOD = 'delivery';

    /** @var string Pickup method value */
    public const PICKUP_METHOD = 'pickup';

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
     * Get delivery vs pickup ratio for the given date range.
     *
     * BR-402: Only completed/delivered orders included.
     * BR-404: Pickup orders counted separately.
     *
     * @return array{delivery: int, pickup: int, delivery_pct: float, pickup_pct: float}
     */
    public function getDeliveryVsPickupRatio(int $tenantId, Carbon $start, Carbon $end): array
    {
        $result = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('
                COUNT(*) FILTER (WHERE delivery_method = ?) AS delivery_count,
                COUNT(*) FILTER (WHERE delivery_method = ?) AS pickup_count
            ', [self::DELIVERY_METHOD, self::PICKUP_METHOD])
            ->first();

        $delivery = (int) ($result->delivery_count ?? 0);
        $pickup = (int) ($result->pickup_count ?? 0);
        $total = $delivery + $pickup;

        return [
            'delivery' => $delivery,
            'pickup' => $pickup,
            'delivery_pct' => $total > 0 ? round(($delivery / $total) * 100, 1) : 0.0,
            'pickup_pct' => $total > 0 ? round(($pickup / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * Get top delivery areas ordered by order count.
     *
     * BR-403: Area derived from order's town/quarter.
     * BR-405: Returns top 10 areas; rest grouped as "Other Areas".
     *
     * @return Collection<int, array{area_label: string, order_count: int, percentage: float}>
     */
    public function getTopDeliveryAreas(int $tenantId, Carbon $start, Carbon $end): Collection
    {
        // Build area label as "Town - Quarter" or "Town" or neighbourhood or "Unknown Area"
        // orders.town_id → towns.id, orders.quarter_id → quarters.id (direct FK, not delivery_area_quarters)
        // towns uses name_en/name_fr columns (not name)
        $rows = DB::select(
            "
            SELECT
                CASE
                    WHEN t.name_en IS NOT NULL AND q.name_en IS NOT NULL
                        THEN t.name_en || ' - ' || q.name_en
                    WHEN t.name_en IS NOT NULL
                        THEN t.name_en
                    WHEN o.neighbourhood IS NOT NULL AND o.neighbourhood <> ''
                        THEN o.neighbourhood
                    ELSE :unknown_label
                END AS area_label,
                COUNT(*) AS order_count
            FROM orders o
            LEFT JOIN towns t ON t.id = o.town_id
            LEFT JOIN quarters q ON q.id = o.quarter_id
            WHERE o.tenant_id = :tenant_id
              AND o.status IN (:s1, :s2, :s3)
              AND o.delivery_method = :delivery_method
              AND o.completed_at BETWEEN :start AND :end
            GROUP BY area_label
            ORDER BY order_count DESC
        ",
            [
                'unknown_label' => __('Unknown Area'),
                'tenant_id' => $tenantId,
                's1' => Order::STATUS_COMPLETED,
                's2' => Order::STATUS_DELIVERED,
                's3' => Order::STATUS_PICKED_UP,
                'delivery_method' => self::DELIVERY_METHOD,
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
            ]
        );

        if (empty($rows)) {
            return collect();
        }

        $collection = collect($rows)->map(fn ($row) => [
            'area_label' => $row->area_label,
            'order_count' => (int) $row->order_count,
        ]);

        $total = $collection->sum('order_count');

        // Top 10 + "Other Areas" grouping
        $top10 = $collection->take(10);
        $others = $collection->skip(10);

        if ($others->isNotEmpty()) {
            $othersCount = $others->sum('order_count');
            $top10->push([
                'area_label' => __('Other Areas'),
                'order_count' => $othersCount,
            ]);
        }

        // Add percentage
        return $top10->map(function (array $item) use ($total) {
            $item['percentage'] = $total > 0 ? round(($item['order_count'] / $total) * 100, 1) : 0.0;

            return $item;
        })->values();
    }

    /**
     * Get summary metrics for the given date range.
     *
     * @return array{total_deliveries: int, total_pickups: int, most_popular_area: string|null}
     */
    public function getSummaryMetrics(int $tenantId, Carbon $start, Carbon $end): array
    {
        $ratio = $this->getDeliveryVsPickupRatio($tenantId, $start, $end);

        $mostPopular = $this->getTopDeliveryAreas($tenantId, $start, $end)->first();

        return [
            'total_deliveries' => $ratio['delivery'],
            'total_pickups' => $ratio['pickup'],
            'most_popular_area' => $mostPopular ? $mostPopular['area_label'] : null,
        ];
    }

    /**
     * Check whether any delivery data exists for this tenant.
     *
     * BR-402: Only completed delivery orders.
     */
    public function hasDeliveryData(int $tenantId): bool
    {
        return Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->where('delivery_method', self::DELIVERY_METHOD)
            ->whereNotNull('completed_at')
            ->exists();
    }

    /**
     * Check whether any completed orders (delivery OR pickup) exist for this tenant.
     *
     * Used to distinguish "no orders at all" from "orders but all pickup".
     */
    public function hasAnyCompletedOrders(int $tenantId): bool
    {
        return Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('completed_at')
            ->exists();
    }
}
