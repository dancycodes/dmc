<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CookPerformanceService — aggregates per-cook performance metrics for admin panel.
 *
 * F-206: Admin Cook Performance Metrics
 * BR-428: Only admin and super-admin roles can access this page.
 * BR-429: Table columns: Cook Name, Region/Town, Total Orders, Total Revenue (XAF),
 *         Average Rating (stars), Complaint Count, Average Response Time.
 * BR-430: All columns are sortable (ascending/descending toggle).
 * BR-431: Filters: cook status (active/inactive), region/town.
 * BR-432: Search by cook name.
 * BR-433: Average rating from review system (rounded to 1 decimal).
 * BR-434: Complaint count includes all complaint statuses.
 * BR-435: Average response time = average time between complaint filed and cook's first response.
 * BR-436: Table is paginated (25 per page).
 * BR-437: Date range selector applies to orders, revenue, and complaints counted.
 * BR-438: All amounts in XAF format.
 */
class CookPerformanceService
{
    public const PER_PAGE = 25;

    /** @var array<string, string> */
    public const PERIODS = [
        'this_month' => 'This Month',
        'last_3_months' => 'Last 3 Months',
        'last_6_months' => 'Last 6 Months',
        'this_year' => 'This Year',
        'last_year' => 'Last Year',
        'all_time' => 'All Time',
        'custom' => 'Custom',
    ];

    /** @var array<string> Valid sort column keys */
    public const SORT_COLUMNS = [
        'cook_name',
        'region',
        'total_orders',
        'total_revenue',
        'avg_rating',
        'complaint_count',
        'avg_response_hours',
    ];

    /**
     * Resolve the date range for the given period.
     * Returns null start/end for "all_time" (no date filtering).
     *
     * @return array{start: Carbon|null, end: Carbon|null}
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
                // all_time: no date filtering
                'start' => null,
                'end' => null,
            ],
        };
    }

    /**
     * Get the paginated cook performance metrics.
     *
     * BR-430: All columns sortable.
     * BR-431: Status and region filters.
     * BR-432: Search by cook name.
     * BR-436: Paginated at 25 per page.
     * BR-437: Date range applies to orders, revenue, complaints.
     *
     * @param  array{start: Carbon|null, end: Carbon|null}  $dateRange
     */
    public function getPerformanceTable(
        array $dateRange,
        string $sortBy = 'total_revenue',
        string $sortDir = 'desc',
        ?string $search = null,
        ?string $status = null,
        ?int $regionId = null,
        int $page = 1
    ): LengthAwarePaginator {
        $sortBy = in_array($sortBy, self::SORT_COLUMNS, true) ? $sortBy : 'total_revenue';
        $sortDir = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';

        $dateStart = $dateRange['start'];
        $dateEnd = $dateRange['end'];

        // Orders subquery: total orders and revenue per tenant (completed only)
        $orderSubQuery = DB::table('orders')
            ->selectRaw('tenant_id, COUNT(*) AS total_orders, SUM(grand_total) AS total_revenue')
            ->whereIn('status', [
                Order::STATUS_COMPLETED,
                Order::STATUS_DELIVERED,
                Order::STATUS_PICKED_UP,
            ]);

        if ($dateStart && $dateEnd) {
            $orderSubQuery->whereBetween('completed_at', [$dateStart, $dateEnd]);
        }

        $orderSubQuery->groupBy('tenant_id');

        // Complaints subquery: count all statuses per tenant (BR-434)
        $complaintSubQuery = DB::table('complaints')
            ->selectRaw('tenant_id, COUNT(*) AS complaint_count');

        if ($dateStart && $dateEnd) {
            $complaintSubQuery->whereBetween('submitted_at', [$dateStart, $dateEnd]);
        }

        $complaintSubQuery->groupBy('tenant_id');

        // Response time subquery: first response per complaint, avg per tenant (BR-435)
        $responseSubQuery = DB::table('complaints AS c')
            ->join(
                DB::raw('(SELECT complaint_id, MIN(created_at) AS first_response_at FROM complaint_responses GROUP BY complaint_id) AS fr'),
                'c.id',
                '=',
                'fr.complaint_id'
            )
            ->selectRaw(
                'c.tenant_id, AVG(EXTRACT(EPOCH FROM (fr.first_response_at - c.submitted_at)) / 3600) AS avg_response_hours'
            );

        if ($dateStart && $dateEnd) {
            $responseSubQuery->whereBetween('c.submitted_at', [$dateStart, $dateEnd]);
        }

        $responseSubQuery->groupBy('c.tenant_id');

        // Ratings subquery: average rating per tenant (BR-433)
        $ratingSubQuery = DB::table('ratings')
            ->selectRaw('tenant_id, ROUND(AVG(stars)::numeric, 1) AS avg_rating');

        if ($dateStart && $dateEnd) {
            $ratingSubQuery->whereBetween('created_at', [$dateStart, $dateEnd]);
        }

        $ratingSubQuery->groupBy('tenant_id');

        // Most common delivery town for this tenant (derived region)
        // We use a lateral-style approach: top town by order count per tenant
        $regionSubQuery = DB::table('orders AS ro')
            ->join('towns AS rt', 'ro.town_id', '=', 'rt.id')
            ->selectRaw('ro.tenant_id, rt.id AS town_id, rt.name_en, rt.name_fr, COUNT(*) AS ord_count')
            ->whereNotNull('ro.town_id');

        if ($dateStart && $dateEnd) {
            $regionSubQuery->whereBetween('ro.completed_at', [$dateStart, $dateEnd]);
        }

        $regionSubQuery->groupBy('ro.tenant_id', 'rt.id', 'rt.name_en', 'rt.name_fr');

        // Use DISTINCT ON to get the top region per tenant
        $topRegionSubQuery = DB::table(
            DB::raw('(SELECT tenant_id, town_id, name_en, name_fr, ord_count FROM ('
                .$regionSubQuery->toSql()
                .') AS rq ORDER BY tenant_id, ord_count DESC) AS rqsorted')
        )
            ->mergeBindings($regionSubQuery)
            ->selectRaw('DISTINCT ON (tenant_id) tenant_id, town_id, name_en, name_fr');

        $locale = app()->getLocale();
        $regionNameExpr = $locale === 'fr'
            ? "COALESCE(treg.name_fr, treg.name_en, '')"
            : "COALESCE(treg.name_en, '')";

        $query = DB::table('tenants')
            ->join('users', 'tenants.cook_id', '=', 'users.id')
            ->leftJoinSub($orderSubQuery, 'ord', 'ord.tenant_id', '=', 'tenants.id')
            ->leftJoinSub($complaintSubQuery, 'comp', 'comp.tenant_id', '=', 'tenants.id')
            ->leftJoinSub($responseSubQuery, 'resp', 'resp.tenant_id', '=', 'tenants.id')
            ->leftJoinSub($ratingSubQuery, 'rat', 'rat.tenant_id', '=', 'tenants.id')
            ->leftJoinSub($topRegionSubQuery, 'treg', 'treg.tenant_id', '=', 'tenants.id')
            ->selectRaw("
                tenants.id AS tenant_id,
                tenants.slug,
                tenants.is_active,
                users.name AS cook_name,
                {$regionNameExpr} AS region,
                treg.town_id AS region_town_id,
                COALESCE(ord.total_orders, 0) AS total_orders,
                COALESCE(ord.total_revenue, 0) AS total_revenue,
                rat.avg_rating,
                COALESCE(comp.complaint_count, 0) AS complaint_count,
                resp.avg_response_hours
            ")
            ->whereNotNull('tenants.cook_id');

        // BR-432: Search by cook name or brand name
        if ($search) {
            $searchLower = mb_strtolower($search);
            $query->where(function ($q) use ($searchLower): void {
                $q->whereRaw('LOWER(users.name) LIKE ?', ['%'.$searchLower.'%'])
                    ->orWhereRaw('LOWER(tenants.name_en) LIKE ?', ['%'.$searchLower.'%'])
                    ->orWhereRaw('LOWER(tenants.name_fr) LIKE ?', ['%'.$searchLower.'%']);
            });
        }

        // BR-431: Filter by status
        if ($status === 'active') {
            $query->where('tenants.is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('tenants.is_active', false);
        }

        // BR-431: Filter by region
        if ($regionId) {
            $query->where('treg.town_id', $regionId);
        }

        // Apply sort — NULL last for nullable metrics so N/A rows go to the bottom
        $nullableColumns = ['avg_rating', 'avg_response_hours'];
        if (in_array($sortBy, $nullableColumns, true)) {
            $query->orderByRaw("{$sortBy} {$sortDir} NULLS LAST");
        } else {
            $query->orderByRaw("{$sortBy} {$sortDir}");
        }

        // Stable secondary sort
        $query->orderBy('tenants.id');

        $total = (clone $query)->count(DB::raw('tenants.id'));

        $rows = $query
            ->offset(($page - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE)
            ->get();

        return new LengthAwarePaginator(
            $rows,
            $total,
            self::PER_PAGE,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Get distinct regions (towns) that appear in any tenant's order history.
     * Used for the region filter dropdown. BR-431.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function getRegionsForFilter(): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('towns')) {
            return collect();
        }

        $locale = app()->getLocale();
        $nameColumn = $locale === 'fr' ? 'towns.name_fr' : 'towns.name_en';

        return DB::table('towns')
            ->join('orders', 'orders.town_id', '=', 'towns.id')
            ->join('tenants', 'orders.tenant_id', '=', 'tenants.id')
            ->whereNotNull('tenants.cook_id')
            ->selectRaw("towns.id, {$nameColumn} AS name")
            ->distinct()
            ->orderByRaw("{$nameColumn} ASC")
            ->get();
    }

    /**
     * Format an integer/float amount as XAF currency string.
     * BR-438: All amounts in XAF format.
     */
    public static function formatXAF(int|float $amount): string
    {
        return number_format((int) $amount, 0, '.', ',').' XAF';
    }

    /**
     * Format average response time in hours to a human-readable string.
     * Returns a locale-independent numeric string so blade can wrap with __().
     * BR-435.
     */
    public static function formatResponseTime(?float $hours): string
    {
        if ($hours === null) {
            return 'N/A';
        }

        if ($hours < 1) {
            $minutes = max(1, (int) round($hours * 60));

            return $minutes === 1 ? "{$minutes} min" : "{$minutes} mins";
        }

        if ($hours < 24) {
            $h = (int) round($hours);

            return $h === 1 ? "{$h} hr" : "{$h} hrs";
        }

        $days = (int) ceil($hours / 24);

        return $days === 1 ? "{$days} day" : "{$days} days";
    }

    /**
     * Get Tailwind color classes for average rating display.
     * Green for >= 4.0, Yellow/Warning for >= 3.0, Red/Danger for < 3.0.
     */
    public static function ratingColorClass(?float $rating): string
    {
        if ($rating === null) {
            return 'text-on-surface/50';
        }

        if ($rating >= 4.0) {
            return 'text-success font-semibold';
        }

        if ($rating >= 3.0) {
            return 'text-warning font-semibold';
        }

        return 'text-danger font-semibold';
    }

    /**
     * Get Tailwind color classes for complaint count display.
     * Green for 0, Warning for 1–4, Danger for 5+.
     */
    public static function complaintColorClass(int $count): string
    {
        if ($count === 0) {
            return 'text-success font-semibold';
        }

        if ($count < 5) {
            return 'text-warning font-semibold';
        }

        return 'text-danger font-semibold';
    }
}
