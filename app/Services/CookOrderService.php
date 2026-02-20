<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CookOrderService
{
    /**
     * Default pagination size.
     *
     * BR-156: Orders are paginated with a configurable page size (default 20).
     */
    public const DEFAULT_PER_PAGE = 20;

    /**
     * Get paginated orders for the cook's order list view.
     *
     * F-155: Cook Order List View
     * BR-155: Orders are tenant-scoped.
     * BR-157: Default sort by date descending (newest first).
     * BR-159: Search is case-insensitive and matches partial strings.
     * BR-160: Filters and search can be combined simultaneously.
     *
     * @param array{
     *     search?: string,
     *     status?: string,
     *     date_from?: string,
     *     date_to?: string,
     *     sort?: string,
     *     direction?: string
     * } $filters
     */
    public function getOrderList(Tenant $tenant, array $filters): LengthAwarePaginator
    {
        $query = Order::query()
            ->forTenant($tenant->id)
            ->with('client:id,name');

        // BR-159: Search by order ID or client name (case-insensitive, partial match)
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('order_number', 'ilike', '%'.$search.'%')
                    ->orWhereHas('client', function (Builder $clientQuery) use ($search) {
                        $clientQuery->where('name', 'ilike', '%'.$search.'%');
                    });
            });
        }

        // BR-160: Status filter
        if (! empty($filters['status']) && in_array($filters['status'], Order::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        // BR-160: Date range filter
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // BR-157: Default sort by date descending
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $allowedSorts = ['created_at', 'status', 'grand_total'];
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
     * Get summary counts for the order list page.
     *
     * @return array{
     *     total: int,
     *     paid: int,
     *     confirmed: int,
     *     preparing: int,
     *     ready: int,
     *     completed: int,
     *     cancelled: int
     * }
     */
    public function getOrderSummary(Tenant $tenant): array
    {
        $counts = Order::query()
            ->forTenant($tenant->id)
            ->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status = ? THEN 1 END) as paid,
                COUNT(CASE WHEN status = ? THEN 1 END) as confirmed,
                COUNT(CASE WHEN status = ? THEN 1 END) as preparing,
                COUNT(CASE WHEN status IN (?, ?, ?) THEN 1 END) as ready,
                COUNT(CASE WHEN status IN (?, ?, ?) THEN 1 END) as completed,
                COUNT(CASE WHEN status = ? THEN 1 END) as cancelled
            ', [
                Order::STATUS_PAID,
                Order::STATUS_CONFIRMED,
                Order::STATUS_PREPARING,
                Order::STATUS_READY, Order::STATUS_OUT_FOR_DELIVERY, Order::STATUS_READY_FOR_PICKUP,
                Order::STATUS_DELIVERED, Order::STATUS_PICKED_UP, Order::STATUS_COMPLETED,
                Order::STATUS_CANCELLED,
            ])
            ->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'paid' => (int) ($counts->paid ?? 0),
            'confirmed' => (int) ($counts->confirmed ?? 0),
            'preparing' => (int) ($counts->preparing ?? 0),
            'ready' => (int) ($counts->ready ?? 0),
            'completed' => (int) ($counts->completed ?? 0),
            'cancelled' => (int) ($counts->cancelled ?? 0),
        ];
    }

    /**
     * Get a truncated items summary from the order's items_snapshot.
     *
     * BR-163: Shows truncated list of meal names with quantities.
     * e.g., "Ndole x2, Eru x1..."
     */
    public static function getItemsSummary(Order $order, int $maxLength = 60): string
    {
        $snapshot = $order->items_snapshot;

        if (empty($snapshot)) {
            return __('No items');
        }

        // Handle double-encoded JSON (lesson from F-154)
        if (is_string($snapshot)) {
            $decoded = json_decode($snapshot, true);
            $snapshot = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($snapshot)) {
            return __('No items');
        }

        // Group items by meal name and sum quantities
        $mealQuantities = [];
        foreach ($snapshot as $item) {
            $mealName = $item['meal_name'] ?? $item['meal'] ?? __('Unknown');
            $quantity = (int) ($item['quantity'] ?? 1);

            if (isset($mealQuantities[$mealName])) {
                $mealQuantities[$mealName] += $quantity;
            } else {
                $mealQuantities[$mealName] = $quantity;
            }
        }

        // Build summary string
        $parts = [];
        foreach ($mealQuantities as $name => $qty) {
            $parts[] = $name.' x'.$qty;
        }

        $summary = implode(', ', $parts);

        if (mb_strlen($summary) > $maxLength) {
            return mb_substr($summary, 0, $maxLength).'...';
        }

        return $summary;
    }

    /**
     * Format amount in XAF.
     *
     * BR-164: All amounts displayed in XAF format.
     */
    public static function formatXAF(int $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }

    /**
     * Get status filter options for the dropdown.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function getStatusFilterOptions(): array
    {
        return [
            ['value' => '', 'label' => __('All Statuses')],
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
        ];
    }
}
