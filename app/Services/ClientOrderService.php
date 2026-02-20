<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClientOrderService
{
    /**
     * Default pagination size.
     *
     * BR-216: Orders are paginated with 15 orders per page.
     */
    public const DEFAULT_PER_PAGE = 15;

    /**
     * Active order statuses (pinned to top).
     *
     * BR-213: Active orders are pinned to the top.
     * These are all statuses except Completed, Cancelled, and Refunded.
     *
     * @var array<string>
     */
    public const ACTIVE_STATUSES = [
        Order::STATUS_PENDING_PAYMENT,
        Order::STATUS_PAID,
        Order::STATUS_CONFIRMED,
        Order::STATUS_PREPARING,
        Order::STATUS_READY,
        Order::STATUS_OUT_FOR_DELIVERY,
        Order::STATUS_READY_FOR_PICKUP,
        Order::STATUS_DELIVERED,
        Order::STATUS_PICKED_UP,
    ];

    /**
     * Past order statuses.
     *
     * BR-214: Past orders appear below active orders.
     *
     * @var array<string>
     */
    public const PAST_STATUSES = [
        Order::STATUS_COMPLETED,
        Order::STATUS_CANCELLED,
        Order::STATUS_REFUNDED,
    ];

    /**
     * Get active orders for the client (pinned section).
     *
     * BR-213: Active orders are pinned to the top.
     * BR-215: Sorted by date descending (newest first).
     * BR-212: All orders across all tenants.
     *
     * @return Collection<int, Order>
     */
    public function getActiveOrders(User $user): Collection
    {
        return Order::query()
            ->forClient($user->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->with(['tenant:id,name_en,name_fr,slug,custom_domain'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get paginated past orders for the client (main list).
     *
     * BR-214: Past orders appear below active orders.
     * BR-215: Sorted by date descending (newest first).
     * BR-216: Paginated with 15 orders per page.
     * BR-212: All orders across all tenants.
     *
     * @param array{
     *     status?: string,
     *     sort?: string,
     *     direction?: string
     * } $filters
     */
    public function getPastOrders(User $user, array $filters): LengthAwarePaginator
    {
        $query = Order::query()
            ->forClient($user->id)
            ->whereIn('status', self::PAST_STATUSES)
            ->with(['tenant:id,name_en,name_fr,slug,custom_domain']);

        // Status filter within past statuses
        if (! empty($filters['status']) && in_array($filters['status'], self::PAST_STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        // BR-215: Default sort by date descending
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $allowedSorts = ['created_at', 'grand_total'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $query->orderBy($sort, $direction);

        return $query->paginate(self::DEFAULT_PER_PAGE)->withQueryString();
    }

    /**
     * Get all orders for the client when a status filter is applied.
     *
     * When filtering, active order pinning is disabled (Scenario 3).
     * Returns paginated results across all statuses matching the filter.
     *
     * @param array{
     *     status?: string,
     *     sort?: string,
     *     direction?: string
     * } $filters
     */
    public function getFilteredOrders(User $user, array $filters): LengthAwarePaginator
    {
        $query = Order::query()
            ->forClient($user->id)
            ->with(['tenant:id,name_en,name_fr,slug,custom_domain']);

        // Apply status filter
        if (! empty($filters['status']) && in_array($filters['status'], Order::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        // Sort
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $allowedSorts = ['created_at', 'grand_total'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $query->orderBy($sort, $direction);

        return $query->paginate(self::DEFAULT_PER_PAGE)->withQueryString();
    }

    /**
     * Get the count of active orders for navigation badge.
     *
     * UI/UX: Active order count badge in navigation.
     */
    public function getActiveOrderCount(User $user): int
    {
        return Order::query()
            ->forClient($user->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->count();
    }

    /**
     * Get status filter options for client order list.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function getStatusFilterOptions(): array
    {
        return [
            ['value' => '', 'label' => __('All Orders')],
            ['value' => Order::STATUS_PENDING_PAYMENT, 'label' => __('Pending Payment')],
            ['value' => Order::STATUS_PAID, 'label' => __('Paid')],
            ['value' => Order::STATUS_CONFIRMED, 'label' => __('Confirmed')],
            ['value' => Order::STATUS_PREPARING, 'label' => __('Preparing')],
            ['value' => Order::STATUS_READY, 'label' => __('Ready')],
            ['value' => Order::STATUS_OUT_FOR_DELIVERY, 'label' => __('Out for Delivery')],
            ['value' => Order::STATUS_READY_FOR_PICKUP, 'label' => __('Ready for Pickup')],
            ['value' => Order::STATUS_DELIVERED, 'label' => __('Delivered')],
            ['value' => Order::STATUS_PICKED_UP, 'label' => __('Picked Up')],
            ['value' => Order::STATUS_COMPLETED, 'label' => __('Completed')],
            ['value' => Order::STATUS_CANCELLED, 'label' => __('Cancelled')],
            ['value' => Order::STATUS_REFUNDED, 'label' => __('Refunded')],
        ];
    }

    /**
     * Get the tenant URL for an order's cook.
     *
     * BR-217: Cook name links to the cook's tenant landing page.
     */
    public static function getTenantUrl(?\App\Models\Tenant $tenant): string
    {
        if (! $tenant) {
            return '#';
        }

        return $tenant->getUrl();
    }

    /**
     * Get items summary from order snapshot.
     *
     * Delegates to CookOrderService for consistent formatting.
     */
    public static function getItemsSummary(Order $order, int $maxLength = 50): string
    {
        return CookOrderService::getItemsSummary($order, $maxLength);
    }

    /**
     * Format amount in XAF.
     */
    public static function formatXAF(int $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }
}
