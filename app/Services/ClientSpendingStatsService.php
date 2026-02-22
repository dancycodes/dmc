<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ClientSpendingStatsService — personal spending and order statistics for clients.
 *
 * F-204: Client Spending & Order Stats
 *
 * BR-408: Stats are personal to the authenticated client.
 * BR-409: Total spent includes only completed/delivered/picked_up orders.
 * BR-410: "This month" calculated from the 1st of the current month.
 * BR-411: Most-ordered cooks ranked by order count (top 5).
 * BR-412: Most-ordered meals ranked by times ordered (top 5).
 * BR-413: Amounts in XAF format.
 * BR-414: Cook cards link to the cook's tenant landing page.
 * BR-415: Meal cards link to meal detail on cook's tenant domain.
 */
class ClientSpendingStatsService
{
    /**
     * Order statuses that count towards "spent" totals.
     *
     * @var array<int, string>
     */
    public const COMPLETED_STATUSES = [
        Order::STATUS_COMPLETED,
        Order::STATUS_DELIVERED,
        Order::STATUS_PICKED_UP,
    ];

    /**
     * Get all spending stats for a given client user ID.
     *
     * @return array{
     *     totalSpent: int,
     *     thisMonthSpent: int,
     *     totalOrders: int,
     *     topCooks: Collection,
     *     topMeals: Collection,
     *     hasOrders: bool,
     * }
     */
    public function getStats(int $userId): array
    {
        $completedOrders = Order::query()
            ->where('client_id', $userId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->get(['id', 'grand_total', 'tenant_id', 'items_snapshot', 'created_at', 'completed_at']);

        if ($completedOrders->isEmpty()) {
            return [
                'totalSpent' => 0,
                'thisMonthSpent' => 0,
                'totalOrders' => 0,
                'topCooks' => collect(),
                'topMeals' => collect(),
                'hasOrders' => false,
            ];
        }

        $totalSpent = $completedOrders->sum('grand_total');

        $monthStart = Carbon::now()->startOfMonth();
        $thisMonthSpent = $completedOrders
            ->filter(fn ($order) => $order->created_at >= $monthStart)
            ->sum('grand_total');

        $totalOrders = $completedOrders->count();

        $topCooks = $this->getTopCooks($completedOrders);
        $topMeals = $this->getTopMeals($completedOrders);

        return [
            'totalSpent' => (int) $totalSpent,
            'thisMonthSpent' => (int) $thisMonthSpent,
            'totalOrders' => $totalOrders,
            'topCooks' => $topCooks,
            'topMeals' => $topMeals,
            'hasOrders' => true,
        ];
    }

    /**
     * Build the top-5 cooks by order frequency from the given completed orders.
     *
     * Each item has: tenant_id, name, order_count, url, is_active
     *
     * @param  Collection<int, Order>  $completedOrders
     * @return Collection<int, array{tenant_id: int, name: string, order_count: int, url: string, is_active: bool}>
     */
    private function getTopCooks(Collection $completedOrders): Collection
    {
        $countsByTenantId = $completedOrders
            ->groupBy('tenant_id')
            ->map(fn ($orders, $tenantId) => [
                'tenant_id' => (int) $tenantId,
                'order_count' => $orders->count(),
            ])
            ->sortByDesc('order_count')
            ->take(5)
            ->values();

        if ($countsByTenantId->isEmpty()) {
            return collect();
        }

        $tenantIds = $countsByTenantId->pluck('tenant_id')->all();

        $tenants = Tenant::query()
            ->whereIn('id', $tenantIds)
            ->get(['id', 'name_en', 'name_fr', 'is_active', 'slug', 'domain'])
            ->keyBy('id');

        return $countsByTenantId->map(function (array $item) use ($tenants) {
            $tenant = $tenants->get($item['tenant_id']);

            if (! $tenant) {
                return null;
            }

            return [
                'tenant_id' => $item['tenant_id'],
                'name' => $tenant->name,
                'order_count' => $item['order_count'],
                'url' => $tenant->getUrl(),
                'is_active' => (bool) $tenant->is_active,
            ];
        })->filter()->values();
    }

    /**
     * Build the top-5 meals by order frequency from items_snapshot of completed orders.
     *
     * Each item has: meal_id, meal_name, tenant_id, order_count, meal_url, meal_exists
     *
     * @param  Collection<int, Order>  $completedOrders
     * @return Collection<int, array{meal_id: int|null, meal_name: string, tenant_id: int, order_count: int, meal_url: string, meal_exists: bool}>
     */
    private function getTopMeals(Collection $completedOrders): Collection
    {
        // Tally meal appearances across all order item snapshots
        $mealCounts = [];

        foreach ($completedOrders as $order) {
            $snapshot = $order->items_snapshot;

            if (empty($snapshot)) {
                continue;
            }

            if (is_string($snapshot)) {
                $snapshot = json_decode($snapshot, true);
            }

            if (! is_array($snapshot)) {
                continue;
            }

            $seenMealsInOrder = [];

            foreach ($snapshot as $item) {
                $mealId = $item['meal_id'] ?? null;

                if (! $mealId) {
                    continue;
                }

                // Count each meal once per order (not per component/quantity)
                if (isset($seenMealsInOrder[$mealId])) {
                    continue;
                }

                $seenMealsInOrder[$mealId] = true;
                $key = $order->tenant_id.':'.$mealId;

                if (! isset($mealCounts[$key])) {
                    $mealCounts[$key] = [
                        'meal_id' => $mealId,
                        'tenant_id' => $order->tenant_id,
                        'order_count' => 0,
                    ];
                }

                $mealCounts[$key]['order_count']++;
            }
        }

        if (empty($mealCounts)) {
            return collect();
        }

        // Sort by order count descending, take top 5
        $topEntries = collect($mealCounts)
            ->sortByDesc('order_count')
            ->take(5)
            ->values();

        // Batch-load meal data
        $mealIds = $topEntries->pluck('meal_id')->unique()->all();
        $tenantIds = $topEntries->pluck('tenant_id')->unique()->all();

        $meals = DB::table('meals')
            ->whereIn('id', $mealIds)
            ->whereNull('deleted_at')
            ->get(['id', 'name_en', 'name_fr', 'tenant_id'])
            ->keyBy('id');

        $tenants = Tenant::query()
            ->whereIn('id', $tenantIds)
            ->get(['id', 'slug', 'domain'])
            ->keyBy('id');

        return $topEntries->map(function (array $entry) use ($meals, $tenants) {
            $meal = $meals->get($entry['meal_id']);
            $tenant = $tenants->get($entry['tenant_id']);

            $mealExists = $meal !== null;

            if ($mealExists) {
                $mealName = app()->getLocale() === 'fr'
                    ? ($meal->name_fr ?: $meal->name_en)
                    : $meal->name_en;
            } else {
                $mealName = null;
            }

            $mealUrl = '';

            if ($mealExists && $tenant) {
                $mealUrl = $tenant->getUrl().'/meals/'.$entry['meal_id'];
            }

            return [
                'meal_id' => $entry['meal_id'],
                'meal_name' => $mealName,
                'tenant_id' => $entry['tenant_id'],
                'order_count' => $entry['order_count'],
                'meal_url' => $mealUrl,
                'meal_exists' => $mealExists,
            ];
        })->values();
    }

    /**
     * Format an integer amount as XAF currency string.
     */
    public static function formatXAF(int $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }
}
