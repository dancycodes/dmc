<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CookCustomerRetentionService — tenant-scoped customer retention analytics for the cook dashboard.
 *
 * F-202: Cook Customer Retention Analytics
 *
 * BR-390: Customer data is tenant-scoped — only customers who ordered from this cook.
 * BR-391: Unique customers counted by distinct user_id on completed orders.
 * BR-392: Repeat customer: a customer with 2+ completed orders from this tenant.
 * BR-393: Repeat rate = (repeat customers / unique customers) * 100.
 * BR-394: New vs returning is calculated per month.
 * BR-395: Customer lifetime value = total amount spent by a customer at this tenant (completed orders).
 * BR-396: Top customers table sortable by order count or total spend.
 * BR-397: Customer names shown but contact details (email/phone) not exposed.
 * BR-398: Date range selector applies to summary cards and charts.
 * BR-399: All amounts in XAF format.
 * BR-400: All user-facing text uses __() localization.
 */
class CookCustomerRetentionService
{
    /** @var array<int, string> Status values that count as completed revenue */
    public const COMPLETED_STATUSES = [
        Order::STATUS_COMPLETED,
        Order::STATUS_DELIVERED,
        Order::STATUS_PICKED_UP,
    ];

    /** @var array<string, string> Supported period keys */
    public const PERIODS = [
        'this_month' => 'This Month',
        'last_3_months' => 'Last 3 Months',
        'last_6_months' => 'Last 6 Months',
        'this_year' => 'This Year',
        'custom' => 'Custom',
    ];

    /** @var array<string, string> Sort options for top customers table */
    public const SORT_OPTIONS = [
        'total_spend' => 'Total Spend',
        'order_count' => 'Order Count',
    ];

    /** @var array<array{min: int, max: int|null, label: string}> CLV distribution buckets */
    public const CLV_BUCKETS = [
        ['min' => 0, 'max' => 5000, 'label' => '0–5,000 XAF'],
        ['min' => 5001, 'max' => 20000, 'label' => '5,001–20,000 XAF'],
        ['min' => 20001, 'max' => null, 'label' => '20,001+ XAF'],
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
     * Get summary cards: unique customers, repeat rate, new this period, returning this period.
     *
     * BR-391: Unique customers counted by distinct user_id.
     * BR-392: Repeat customer = 2+ completed orders from this tenant.
     * BR-393: Repeat rate = (repeat / unique) * 100.
     *
     * @return array{uniqueCustomers: int, repeatRate: float, newThisPeriod: int, returningThisPeriod: int}
     */
    public function getSummaryCards(int $tenantId, Carbon $start, Carbon $end): array
    {
        // All-time unique customers (for repeat rate calculation)
        $allTimeCustomers = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('client_id')
            ->distinct('client_id')
            ->count('client_id');

        // All-time repeat customers (2+ completed orders)
        $repeatCustomers = (int) DB::table('orders')
            ->select('client_id', DB::raw('COUNT(*) as order_count'))
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('client_id')
            ->groupBy('client_id')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->count();

        // Repeat rate: (repeat / unique) * 100
        $repeatRate = $allTimeCustomers > 0
            ? round(($repeatCustomers / $allTimeCustomers) * 100, 1)
            : 0.0;

        // New customers in this period: first order within the range
        $newThisPeriod = $this->getNewCustomersCount($tenantId, $start, $end);

        // Returning customers in this period: ordered in range AND had an order before the range
        $returningThisPeriod = $this->getReturningCustomersCount($tenantId, $start, $end);

        return [
            'uniqueCustomers' => $allTimeCustomers,
            'repeatRate' => $repeatRate,
            'newThisPeriod' => $newThisPeriod,
            'returningThisPeriod' => $returningThisPeriod,
        ];
    }

    /**
     * Count new customers in the given period (first completed order is within the range).
     *
     * BR-394: New vs returning calculated per month context.
     */
    private function getNewCustomersCount(int $tenantId, Carbon $start, Carbon $end): int
    {
        // Subquery: first completed order date per customer at this tenant
        $firstOrderSubquery = DB::table('orders')
            ->select('client_id', DB::raw('MIN(completed_at) as first_order_date'))
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('client_id')
            ->groupBy('client_id');

        return DB::table(DB::raw('('.$firstOrderSubquery->toSql().') as first_orders'))
            ->mergeBindings($firstOrderSubquery)
            ->whereBetween('first_order_date', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->count();
    }

    /**
     * Count returning customers in the given period (ordered in range AND ordered before the range).
     */
    private function getReturningCustomersCount(int $tenantId, Carbon $start, Carbon $end): int
    {
        // Customers who ordered in this period
        $orderedInPeriod = DB::table('orders')
            ->select('client_id')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('client_id')
            ->whereBetween('completed_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->distinct();

        // Among those, who also had an order before the period
        $returningIds = DB::table(DB::raw('('.$orderedInPeriod->toSql().') as in_period'))
            ->mergeBindings($orderedInPeriod)
            ->whereExists(function ($query) use ($tenantId, $start) {
                $query->select(DB::raw(1))
                    ->from('orders')
                    ->whereColumn('orders.client_id', 'in_period.client_id')
                    ->where('orders.tenant_id', $tenantId)
                    ->whereIn('orders.status', self::COMPLETED_STATUSES)
                    ->where('orders.completed_at', '<', $start->toDateTimeString());
            })
            ->count();

        return (int) $returningIds;
    }

    /**
     * Get new vs returning customer counts per month for the last 6 months.
     *
     * BR-394: New vs returning is calculated per month.
     *
     * @return Collection<int, array{label: string, new: int, returning: int}>
     */
    public function getNewVsReturningChartData(int $tenantId, Carbon $start, Carbon $end): Collection
    {
        $points = collect();
        $cursor = $start->copy()->startOfMonth();
        $endMonth = $end->copy()->startOfMonth();

        while ($cursor->lte($endMonth)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $newCount = $this->getNewCustomersCount($tenantId, $monthStart, $monthEnd);
            $returningCount = $this->getReturningCustomersCount($tenantId, $monthStart, $monthEnd);

            $points->push([
                'label' => $cursor->format('M Y'),
                'new' => $newCount,
                'returning' => $returningCount,
            ]);

            $cursor->addMonth();
        }

        return $points;
    }

    /**
     * Get top 20 customers sorted by order count or total spend.
     * Uses LEFT JOIN to handle deleted accounts gracefully.
     *
     * BR-396: Top customers table sortable by order count or total spend.
     * BR-397: Customer names shown but contact details not exposed.
     *         Deleted accounts shown as "Former Customer".
     *
     * @return Collection<int, array{name: string, order_count: int, total_spent: int, last_order_date: string}>
     */
    public function getTopCustomersWithDeletedSupport(int $tenantId, string $sortBy = 'total_spend', int $limit = 20): Collection
    {
        $sortColumn = $sortBy === 'order_count' ? 'order_count' : 'total_spent';

        $rows = DB::table('orders')
            ->leftJoin('users', 'orders.client_id', '=', 'users.id')
            ->select([
                'orders.client_id',
                DB::raw('COALESCE(users.name, \'Former Customer\') as customer_name'),
                DB::raw('COUNT(orders.id) as order_count'),
                DB::raw('SUM(orders.grand_total) as total_spent'),
                DB::raw('MAX(orders.completed_at) as last_order_at'),
            ])
            ->where('orders.tenant_id', $tenantId)
            ->whereIn('orders.status', self::COMPLETED_STATUSES)
            ->whereNotNull('orders.client_id')
            ->groupBy('orders.client_id', 'users.name')
            ->orderByDesc($sortColumn)
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            return [
                'name' => $row->customer_name,
                'order_count' => (int) $row->order_count,
                'total_spent' => (int) $row->total_spent,
                'last_order_date' => $row->last_order_at
                    ? Carbon::parse($row->last_order_at)->format('M j, Y')
                    : '—',
            ];
        });
    }

    /**
     * Get CLV (Customer Lifetime Value) distribution across buckets.
     *
     * BR-395: CLV = total amount spent by a customer at this tenant (completed orders).
     *
     * @return Collection<int, array{label: string, count: int, min: int, max: int|null}>
     */
    public function getClvDistribution(int $tenantId): Collection
    {
        // Get CLV per customer (all-time)
        $customerClv = DB::table('orders')
            ->select('client_id', DB::raw('SUM(grand_total) as lifetime_value'))
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('client_id')
            ->groupBy('client_id')
            ->get();

        return collect(self::CLV_BUCKETS)->map(function (array $bucket) use ($customerClv) {
            $count = $customerClv->filter(function ($row) use ($bucket) {
                $clv = (int) $row->lifetime_value;
                $inMin = $clv >= $bucket['min'];
                $inMax = $bucket['max'] === null || $clv <= $bucket['max'];

                return $inMin && $inMax;
            })->count();

            return [
                'label' => $bucket['label'],
                'count' => $count,
                'min' => $bucket['min'],
                'max' => $bucket['max'],
            ];
        });
    }

    /**
     * Check if a tenant has any completed order data.
     */
    public function hasData(int $tenantId): bool
    {
        return Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereNotNull('client_id')
            ->exists();
    }

    /**
     * Format an integer amount as XAF currency string.
     *
     * BR-399: All amounts in XAF format.
     */
    public static function formatXAF(int $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }
}
