<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

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

    /**
     * F-161: Get order detail data for the client order detail view.
     *
     * BR-222: Client can only view their own orders.
     * BR-224: Visual timeline shows all status transitions with timestamps.
     * BR-225: Cancel button visible when order is Paid/Confirmed AND within cancellation window.
     * BR-229: Cook's WhatsApp number displayed for urgent contact.
     * BR-230: Payment details show method, amount, reference, status.
     * BR-231: Items section shows meal name, components, quantities, unit prices, line totals.
     * BR-232: Delivery orders show town, quarter, landmark, delivery fee.
     * BR-233: Pickup orders show pickup location name and address.
     *
     * @return array{
     *     order: Order,
     *     items: array<array{meal_name: string, component_name: string, quantity: int, unit_price: int, subtotal: int}>,
     *     statusTimeline: array<array{status: string, label: string, timestamp: string, relative_time: string, user: string}>,
     *     paymentTransaction: ?PaymentTransaction,
     *     canCancel: bool,
     *     cancellationSecondsRemaining: int,
     *     canReport: bool,
     *     canRate: bool,
     *     cookWhatsapp: ?string,
     *     cookName: string,
     *     tenantUrl: string,
     *     tenantActive: bool
     * }
     */
    public function getOrderDetail(Order $order): array
    {
        // Eager load all needed relationships
        $order->load([
            'client:id,name,phone,email',
            'tenant:id,name_en,name_fr,slug,custom_domain,whatsapp,phone,is_active,cook_id,settings',
            'town',
            'quarter',
            'pickupLocation.town',
            'pickupLocation.quarter',
            'statusTransitions.triggeredBy:id,name',
        ]);

        $cookOrderService = new CookOrderService;
        $items = $cookOrderService->parseOrderItems($order);
        $statusTimeline = $cookOrderService->getStatusTimeline($order);
        $paymentTransaction = $cookOrderService->getLatestPaymentTransaction($order);

        // BR-225: Cancel button logic
        $canCancel = $this->canCancelOrder($order);
        $cancellationSecondsRemaining = $canCancel ? $this->getCancellationSecondsRemaining($order) : 0;

        // BR-227: Report a Problem available for delivered/picked up/completed statuses
        $canReport = in_array($order->status, [
            Order::STATUS_DELIVERED,
            Order::STATUS_PICKED_UP,
            Order::STATUS_COMPLETED,
        ], true);

        // BR-228: Rating prompt for completed, unrated orders
        $canRate = $order->status === Order::STATUS_COMPLETED && ! $this->hasBeenRated($order);

        // BR-229: Cook's WhatsApp number
        $cookWhatsapp = $order->tenant?->whatsapp;

        return [
            'order' => $order,
            'items' => $items,
            'statusTimeline' => $statusTimeline,
            'paymentTransaction' => $paymentTransaction,
            'canCancel' => $canCancel,
            'cancellationSecondsRemaining' => $cancellationSecondsRemaining,
            'canReport' => $canReport,
            'canRate' => $canRate,
            'cookWhatsapp' => $cookWhatsapp,
            'cookName' => $order->tenant?->name ?? __('Unknown Cook'),
            'tenantUrl' => self::getTenantUrl($order->tenant),
            'tenantActive' => $order->tenant?->is_active ?? false,
        ];
    }

    /**
     * F-161 BR-225: Check if the order can be cancelled by the client.
     *
     * Order must be in Paid or Confirmed status AND within the cancellation window.
     * Uses platform default cancellation window (F-212 will add per-cook override).
     */
    public function canCancelOrder(Order $order): bool
    {
        if (! in_array($order->status, [Order::STATUS_PAID, Order::STATUS_CONFIRMED], true)) {
            return false;
        }

        $cancellationWindowMinutes = $this->getCancellationWindowMinutes($order);

        if ($cancellationWindowMinutes <= 0) {
            return false;
        }

        // Window starts from when the order was paid
        $referenceTime = $order->paid_at ?? $order->created_at;
        if (! $referenceTime) {
            return false;
        }

        $windowExpiresAt = $referenceTime->copy()->addMinutes($cancellationWindowMinutes);

        return now()->lessThan($windowExpiresAt);
    }

    /**
     * F-161 BR-226: Get the remaining seconds in the cancellation window.
     */
    public function getCancellationSecondsRemaining(Order $order): int
    {
        $cancellationWindowMinutes = $this->getCancellationWindowMinutes($order);

        if ($cancellationWindowMinutes <= 0) {
            return 0;
        }

        $referenceTime = $order->paid_at ?? $order->created_at;
        if (! $referenceTime) {
            return 0;
        }

        $windowExpiresAt = $referenceTime->copy()->addMinutes($cancellationWindowMinutes);
        $remaining = now()->diffInSeconds($windowExpiresAt, false);

        return max(0, (int) $remaining);
    }

    /**
     * Get the cancellation window in minutes for the order's cook.
     *
     * Forward-compatible: F-212 will add per-cook override in tenant settings.
     * Falls back to platform default.
     */
    private function getCancellationWindowMinutes(Order $order): int
    {
        // Check tenant-level override (F-212 will populate this)
        $tenantOverride = $order->tenant?->getSetting('cancellation_window');
        if ($tenantOverride !== null) {
            return (int) $tenantOverride;
        }

        // Platform default
        if (Schema::hasTable('platform_settings')) {
            return app(PlatformSettingService::class)->getDefaultCancellationWindow();
        }

        return 30; // Fallback 30 minutes
    }

    /**
     * F-161 BR-228: Check if the order has been rated.
     *
     * Forward-compatible: F-176 will create the ratings table.
     */
    private function hasBeenRated(Order $order): bool
    {
        if (Schema::hasTable('ratings')) {
            return \DB::table('ratings')
                ->where('order_id', $order->id)
                ->exists();
        }

        return false;
    }

    /**
     * F-161: Refresh status timeline data for polling updates.
     *
     * BR-223: Status updates pushed to client in real-time via Gale SSE.
     *
     * @return array{
     *     status: string,
     *     statusLabel: string,
     *     statusTimeline: array,
     *     canCancel: bool,
     *     cancellationSecondsRemaining: int,
     *     canReport: bool,
     *     canRate: bool
     * }
     */
    public function getOrderStatusRefresh(Order $order): array
    {
        $order->refresh();
        $order->load(['statusTransitions.triggeredBy:id,name', 'client:id,name']);

        $cookOrderService = new CookOrderService;
        $statusTimeline = $cookOrderService->getStatusTimeline($order);

        return [
            'status' => $order->status,
            'statusLabel' => Order::getStatusLabel($order->status),
            'statusTimeline' => $statusTimeline,
            'canCancel' => $this->canCancelOrder($order),
            'cancellationSecondsRemaining' => $this->canCancelOrder($order) ? $this->getCancellationSecondsRemaining($order) : 0,
            'canReport' => in_array($order->status, [
                Order::STATUS_DELIVERED,
                Order::STATUS_PICKED_UP,
                Order::STATUS_COMPLETED,
            ], true),
            'canRate' => $order->status === Order::STATUS_COMPLETED && ! $this->hasBeenRated($order),
        ];
    }
}
