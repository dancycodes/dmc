<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

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
     * F-156: Get order detail data for the cook order detail view.
     *
     * BR-166: Order detail is tenant-scoped.
     * BR-167: Client phone number displayed.
     * BR-168: Items with components, quantities, prices.
     * BR-169/BR-170: Delivery/pickup details.
     * BR-171: Status timeline from activity log.
     * BR-173: Payment information.
     * BR-174: Client notes.
     *
     * @return array{
     *     order: Order,
     *     items: array<array{meal_name: string, component_name: string, quantity: int, unit_price: int, subtotal: int}>,
     *     statusTimeline: array<array{status: string, label: string, timestamp: string, user: string}>,
     *     nextStatus: ?string,
     *     nextStatusLabel: ?string,
     *     paymentTransaction: ?\App\Models\PaymentTransaction
     * }
     */
    public function getOrderDetail(Order $order): array
    {
        // Eager load all needed relationships
        $order->load([
            'client:id,name,phone,email',
            'tenant:id,name_en,name_fr,slug,whatsapp',
            'town',
            'quarter',
            'pickupLocation.town',
            'pickupLocation.quarter',
        ]);

        $items = $this->parseOrderItems($order);
        $statusTimeline = $this->getStatusTimeline($order);
        $nextStatus = $order->getNextStatus();
        $nextStatusLabel = $nextStatus ? Order::getStatusLabel($nextStatus) : null;
        $paymentTransaction = $this->getLatestPaymentTransaction($order);

        return [
            'order' => $order,
            'items' => $items,
            'statusTimeline' => $statusTimeline,
            'nextStatus' => $nextStatus,
            'nextStatusLabel' => $nextStatusLabel,
            'paymentTransaction' => $paymentTransaction,
        ];
    }

    /**
     * F-156: Parse order items from the items_snapshot column.
     *
     * BR-168: Items section shows meal name, selected components with quantities,
     * unit prices, and line totals.
     *
     * @return array<int, array{meal_name: string, component_name: string, quantity: int, unit_price: int, subtotal: int}>
     */
    public function parseOrderItems(Order $order): array
    {
        $snapshot = $order->items_snapshot;

        if (empty($snapshot)) {
            return [];
        }

        // Handle double-encoded JSON (lesson from F-154)
        if (is_string($snapshot)) {
            $decoded = json_decode($snapshot, true);
            $snapshot = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($snapshot)) {
            return [];
        }

        $items = [];
        foreach ($snapshot as $item) {
            $items[] = [
                'meal_name' => $item['meal_name'] ?? $item['meal'] ?? __('Unknown'),
                'component_name' => $item['component_name'] ?? $item['component'] ?? '',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'unit_price' => (int) ($item['unit_price'] ?? $item['price'] ?? 0),
                'subtotal' => (int) ($item['subtotal'] ?? (($item['unit_price'] ?? $item['price'] ?? 0) * ($item['quantity'] ?? 1))),
            ];
        }

        return $items;
    }

    /**
     * F-156: Get the status timeline from activity log.
     *
     * BR-171: Status timeline shows all transitions with timestamps
     * and the user who triggered each change.
     *
     * @return array<int, array{status: string, label: string, timestamp: string, relative_time: string, user: string}>
     */
    public function getStatusTimeline(Order $order): array
    {
        $timeline = [];

        // Always include order creation as first entry
        $timeline[] = [
            'status' => Order::STATUS_PENDING_PAYMENT,
            'label' => Order::getStatusLabel(Order::STATUS_PENDING_PAYMENT),
            'timestamp' => $order->created_at?->format('M d, Y H:i') ?? '',
            'relative_time' => $order->created_at?->diffForHumans() ?? '',
            'user' => $order->client?->name ?? __('Customer'),
        ];

        // Check for paid_at timestamp
        if ($order->paid_at) {
            $timeline[] = [
                'status' => Order::STATUS_PAID,
                'label' => Order::getStatusLabel(Order::STATUS_PAID),
                'timestamp' => $order->paid_at->format('M d, Y H:i'),
                'relative_time' => $order->paid_at->diffForHumans(),
                'user' => __('System'),
            ];
        }

        // Get status change entries from activity log
        if (Schema::hasTable('activity_log')) {
            $activities = Activity::query()
                ->where('subject_type', Order::class)
                ->where('subject_id', $order->id)
                ->where('event', 'updated')
                ->whereJsonContains('properties->attributes', ['status' => ''])
                ->orWhere(function ($q) use ($order) {
                    $q->where('subject_type', Order::class)
                        ->where('subject_id', $order->id)
                        ->where('description', 'like', '%status%');
                })
                ->with('causer:id,name')
                ->orderBy('created_at')
                ->get();

            foreach ($activities as $activity) {
                $properties = $activity->properties ?? collect();
                $newAttributes = $properties->get('attributes', []);
                $oldAttributes = $properties->get('old', []);

                if (isset($newAttributes['status']) && isset($oldAttributes['status'])) {
                    $newStatus = $newAttributes['status'];
                    // Avoid duplicating paid status already added from paid_at
                    if ($newStatus === Order::STATUS_PAID && $order->paid_at) {
                        continue;
                    }

                    $timeline[] = [
                        'status' => $newStatus,
                        'label' => Order::getStatusLabel($newStatus),
                        'timestamp' => $activity->created_at->format('M d, Y H:i'),
                        'relative_time' => $activity->created_at->diffForHumans(),
                        'user' => $activity->causer?->name ?? __('System'),
                    ];
                }
            }
        }

        // Add confirmed_at if not already in timeline
        if ($order->confirmed_at && ! collect($timeline)->contains('status', Order::STATUS_CONFIRMED)) {
            $timeline[] = [
                'status' => Order::STATUS_CONFIRMED,
                'label' => Order::getStatusLabel(Order::STATUS_CONFIRMED),
                'timestamp' => $order->confirmed_at->format('M d, Y H:i'),
                'relative_time' => $order->confirmed_at->diffForHumans(),
                'user' => __('Cook'),
            ];
        }

        // Add completed_at if not already in timeline
        if ($order->completed_at && ! collect($timeline)->contains('status', Order::STATUS_COMPLETED)) {
            $timeline[] = [
                'status' => Order::STATUS_COMPLETED,
                'label' => Order::getStatusLabel(Order::STATUS_COMPLETED),
                'timestamp' => $order->completed_at->format('M d, Y H:i'),
                'relative_time' => $order->completed_at->diffForHumans(),
                'user' => __('System'),
            ];
        }

        // Add cancelled_at if not already in timeline
        if ($order->cancelled_at && ! collect($timeline)->contains('status', Order::STATUS_CANCELLED)) {
            $timeline[] = [
                'status' => Order::STATUS_CANCELLED,
                'label' => Order::getStatusLabel(Order::STATUS_CANCELLED),
                'timestamp' => $order->cancelled_at->format('M d, Y H:i'),
                'relative_time' => $order->cancelled_at->diffForHumans(),
                'user' => __('Customer'),
            ];
        }

        // Sort by timestamp
        usort($timeline, function ($a, $b) {
            return strcmp($a['timestamp'], $b['timestamp']);
        });

        return $timeline;
    }

    /**
     * F-156: Get the latest payment transaction for an order.
     *
     * BR-173: Payment info shows method, amount, status, Flutterwave reference.
     */
    public function getLatestPaymentTransaction(Order $order): ?\App\Models\PaymentTransaction
    {
        if (! Schema::hasTable('payment_transactions')) {
            return null;
        }

        return $order->paymentTransactions()
            ->orderByDesc('created_at')
            ->first();
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
