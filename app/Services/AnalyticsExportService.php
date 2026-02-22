<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AnalyticsExportService — shared export data preparation for F-208.
 *
 * Provides CSV and PDF data builders for all analytics export endpoints:
 *  - Cook Revenue Analytics (F-200)
 *  - Cook Order Analytics (F-201)
 *  - Admin Platform Analytics (F-057, F-205, F-206, F-207)
 *  - Client Spending Stats (F-204)
 *
 * BR-451: CSV contains raw tabular data with column headers.
 * BR-453: Immediate download for datasets < 5,000 rows.
 * BR-456: Filename format: dancymeals-{type}-{range}-{timestamp}.{ext}
 * BR-457: CSV uses UTF-8 encoding with BOM for Excel compatibility.
 * BR-459: Export data is permission-scoped.
 */
class AnalyticsExportService
{
    /** Maximum rows before async export is required (BR-453/BR-454). */
    public const IMMEDIATE_ROW_LIMIT = 5000;

    /** UTF-8 BOM for Excel CSV compatibility (BR-457). */
    public const UTF8_BOM = "\xEF\xBB\xBF";

    /** Completed order statuses for revenue/spending calculations. */
    public const COMPLETED_STATUSES = [
        Order::STATUS_COMPLETED,
        Order::STATUS_DELIVERED,
        Order::STATUS_PICKED_UP,
    ];

    // ─── Cook Revenue Export ──────────────────────────────────────────────────

    /**
     * Get CSV rows for cook revenue analytics.
     *
     * Columns: Date, Revenue (XAF), Order Count
     * (Scenario 1 from F-208 spec)
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getCookRevenueRows(int $tenantId, Carbon $start, Carbon $end): Collection
    {
        $rows = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('DATE(completed_at) as period_date, SUM(grand_total - COALESCE(commission_amount, 0)) as net, COUNT(*) as order_count')
            ->groupByRaw('DATE(completed_at)')
            ->orderByRaw('DATE(completed_at)')
            ->get();

        return $rows->map(fn ($row) => [
            'date' => $row->period_date,
            'revenue' => (int) $row->net,
            'order_count' => (int) $row->order_count,
        ]);
    }

    /**
     * Count the total rows for cook revenue export (for BR-453/BR-454 threshold check).
     */
    public function countCookRevenueRows(int $tenantId, Carbon $start, Carbon $end): int
    {
        return (int) Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('COUNT(DISTINCT DATE(completed_at)) as cnt')
            ->value('cnt');
    }

    // ─── Cook Order Export ────────────────────────────────────────────────────

    /**
     * Get CSV rows for cook order analytics.
     *
     * Columns: Date, Total Orders, Confirmed, Completed, Cancelled
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getCookOrderRows(int $tenantId, Carbon $start, Carbon $end): Collection
    {
        $rows = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('
                DATE(created_at) as period_date,
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = ? THEN 1 END) as confirmed,
                COUNT(CASE WHEN status IN (?, ?, ?) THEN 1 END) as completed,
                COUNT(CASE WHEN status = ? THEN 1 END) as cancelled
            ', [
                Order::STATUS_CONFIRMED,
                Order::STATUS_COMPLETED,
                Order::STATUS_DELIVERED,
                Order::STATUS_PICKED_UP,
                Order::STATUS_CANCELLED,
            ])
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get();

        return $rows->map(fn ($row) => [
            'date' => $row->period_date,
            'total_orders' => (int) $row->total_orders,
            'confirmed' => (int) $row->confirmed,
            'completed' => (int) $row->completed,
            'cancelled' => (int) $row->cancelled,
        ]);
    }

    /**
     * Count the total rows for cook order export.
     */
    public function countCookOrderRows(int $tenantId, Carbon $start, Carbon $end): int
    {
        return (int) Order::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(DISTINCT DATE(created_at)) as cnt')
            ->value('cnt');
    }

    // ─── Admin Platform Revenue Export ────────────────────────────────────────

    /**
     * Get CSV rows for admin platform revenue analytics.
     *
     * Columns: Date, Gross Revenue (XAF), Commission (XAF), Net Payout (XAF), Orders
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getAdminRevenueRows(Carbon $start, Carbon $end): Collection
    {
        $rows = Order::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('
                DATE(completed_at) as period_date,
                SUM(grand_total) as gross,
                SUM(COALESCE(commission_amount, 0)) as commission,
                SUM(grand_total - COALESCE(commission_amount, 0)) as net,
                COUNT(*) as order_count
            ')
            ->groupByRaw('DATE(completed_at)')
            ->orderByRaw('DATE(completed_at)')
            ->get();

        return $rows->map(fn ($row) => [
            'date' => $row->period_date,
            'gross' => (int) $row->gross,
            'commission' => (int) $row->commission,
            'net' => (int) $row->net,
            'order_count' => (int) $row->order_count,
        ]);
    }

    /**
     * Count the total rows for admin revenue export.
     */
    public function countAdminRevenueRows(Carbon $start, Carbon $end): int
    {
        return (int) Order::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('COUNT(DISTINCT DATE(completed_at)) as cnt')
            ->value('cnt');
    }

    // ─── Admin Cook Performance Export ────────────────────────────────────────

    /**
     * Get CSV rows for admin cook performance analytics.
     *
     * Columns: Cook Name, Region, Orders, Revenue (XAF), Rating, Response Rate (%)
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getAdminPerformanceRows(Carbon $start, Carbon $end, ?int $regionId = null): Collection
    {
        $query = Order::query()
            ->join('tenants', 'orders.tenant_id', '=', 'tenants.id')
            ->join('users', 'tenants.cook_id', '=', 'users.id')
            ->leftJoin('regions', 'tenants.region_id', '=', 'regions.id')
            ->whereIn('orders.status', self::COMPLETED_STATUSES)
            ->whereBetween('orders.completed_at', [$start, $end])
            ->selectRaw("
                COALESCE(tenants.name_en, tenants.name_fr) as cook_name,
                COALESCE(regions.name_en, 'N/A') as region,
                COUNT(orders.id) as order_count,
                SUM(orders.grand_total - COALESCE(orders.commission_amount, 0)) as net_revenue
            ")
            ->groupBy('tenants.id', 'tenants.name_en', 'tenants.name_fr', 'regions.name_en');

        if ($regionId) {
            $query->where('tenants.region_id', $regionId);
        }

        $rows = $query->orderByRaw('net_revenue DESC')->get();

        return $rows->map(fn ($row) => [
            'cook_name' => $row->cook_name,
            'region' => $row->region,
            'order_count' => (int) $row->order_count,
            'net_revenue' => (int) $row->net_revenue,
        ]);
    }

    /**
     * Count rows for admin performance export.
     */
    public function countAdminPerformanceRows(Carbon $start, Carbon $end, ?int $regionId = null): int
    {
        $query = Order::query()
            ->join('tenants', 'orders.tenant_id', '=', 'tenants.id')
            ->whereIn('orders.status', self::COMPLETED_STATUSES)
            ->whereBetween('orders.completed_at', [$start, $end])
            ->selectRaw('COUNT(DISTINCT orders.tenant_id) as cnt');

        if ($regionId) {
            $query->where('tenants.region_id', $regionId);
        }

        return (int) $query->value('cnt');
    }

    // ─── Admin Growth Metrics Export ──────────────────────────────────────────

    /**
     * Get CSV rows for admin growth metrics analytics.
     *
     * Columns: Month, New Users, New Tenants, Total Orders, Gross Revenue (XAF)
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getAdminGrowthRows(Carbon $start, Carbon $end): Collection
    {
        // New users per month
        $newUsers = DB::table('users')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_TRUNC('month', created_at) as month, COUNT(*) as new_users")
            ->groupByRaw("DATE_TRUNC('month', created_at)")
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->month)->format('Y-m'));

        // New tenants per month
        $newTenants = DB::table('tenants')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_TRUNC('month', created_at) as month, COUNT(*) as new_tenants")
            ->groupByRaw("DATE_TRUNC('month', created_at)")
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->month)->format('Y-m'));

        // Orders + revenue per month
        $ordersRevenue = Order::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw("DATE_TRUNC('month', completed_at) as month, COUNT(*) as order_count, SUM(grand_total) as gross")
            ->groupByRaw("DATE_TRUNC('month', completed_at)")
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->month)->format('Y-m'));

        // Merge across all months in range
        $cursor = $start->copy()->startOfMonth();
        $endMonth = $end->copy()->startOfMonth();
        $rows = collect();

        while ($cursor->lte($endMonth)) {
            $key = $cursor->format('Y-m');
            $rows->push([
                'month' => $cursor->format('M Y'),
                'new_users' => (int) ($newUsers->get($key)?->new_users ?? 0),
                'new_tenants' => (int) ($newTenants->get($key)?->new_tenants ?? 0),
                'order_count' => (int) ($ordersRevenue->get($key)?->order_count ?? 0),
                'gross_revenue' => (int) ($ordersRevenue->get($key)?->gross ?? 0),
            ]);
            $cursor->addMonth();
        }

        return $rows;
    }

    // ─── Client Spending Export ────────────────────────────────────────────────

    /**
     * Get CSV rows for client spending stats.
     *
     * Columns: Order Number, Cook Name, Amount (XAF), Date
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getClientSpendingRows(int $userId): Collection
    {
        $rows = Order::query()
            ->where('client_id', $userId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->join('tenants', 'orders.tenant_id', '=', 'tenants.id')
            ->select([
                'orders.order_number',
                'tenants.name_en as cook_name_en',
                'tenants.name_fr as cook_name_fr',
                'orders.grand_total',
                'orders.status',
                'orders.completed_at',
            ])
            ->orderByDesc('orders.completed_at')
            ->get();

        $locale = app()->getLocale();

        return $rows->map(fn ($row) => [
            'order_number' => $row->order_number,
            'cook_name' => $locale === 'fr'
                ? ($row->cook_name_fr ?: $row->cook_name_en)
                : $row->cook_name_en,
            'amount' => (int) $row->grand_total,
            'status' => ucfirst(str_replace('_', ' ', $row->status)),
            'date' => $row->completed_at ? Carbon::parse($row->completed_at)->format('Y-m-d') : '',
        ]);
    }

    /**
     * Count total rows for client spending export.
     */
    public function countClientSpendingRows(int $userId): int
    {
        return Order::query()
            ->where('client_id', $userId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->count();
    }

    // ─── Filename Helper ──────────────────────────────────────────────────────

    /**
     * Generate export filename following BR-456 convention.
     *
     * Format: dancymeals-{type}-{range}-{timestamp}.{ext}
     */
    public function buildFilename(string $analyticsType, Carbon $start, Carbon $end, string $extension): string
    {
        $range = $start->format('Ymd').'-'.$end->format('Ymd');
        $timestamp = now()->format('YmdHis');

        return 'dancymeals-'.$analyticsType.'-'.$range.'-'.$timestamp.'.'.$extension;
    }

    /**
     * Format an integer amount as XAF string.
     */
    public static function formatXAF(int $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }
}
