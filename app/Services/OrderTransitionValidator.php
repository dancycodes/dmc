<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;

/**
 * F-159: Order Status Transition Validation Service.
 *
 * Enforces the valid order status transition rules across the entire platform.
 * Defines which transitions are allowed, prevents skipping states, prevents
 * backward transitions (except admin override), and controls when Cancelled
 * and Refunded statuses can be applied.
 *
 * This is the single source of truth for transition validation, consumed by:
 * - F-157: Single Order Status Update (OrderStatusService)
 * - F-158: Mass Order Status Update (MassOrderStatusService)
 * - F-162: Order Cancellation
 * - F-163: Order Cancellation Refund Processing
 */
class OrderTransitionValidator
{
    /**
     * The ordered forward chain for status transitions.
     * BR-200: Defines the valid forward progression.
     *
     * @var array<int, string>
     */
    public const FORWARD_CHAIN = [
        Order::STATUS_PENDING_PAYMENT,
        Order::STATUS_PAID,
        Order::STATUS_CONFIRMED,
        Order::STATUS_PREPARING,
        Order::STATUS_READY,
        // After ready, the chain branches by delivery_method
        // Delivery: out_for_delivery > delivered > completed
        // Pickup: ready_for_pickup > picked_up > completed
    ];

    /**
     * Delivery path after Ready status.
     * BR-206: Delivery orders follow: Ready > Out for Delivery > Delivered > Completed
     *
     * @var array<int, string>
     */
    public const DELIVERY_PATH = [
        Order::STATUS_READY,
        Order::STATUS_OUT_FOR_DELIVERY,
        Order::STATUS_DELIVERED,
        Order::STATUS_COMPLETED,
    ];

    /**
     * Pickup path after Ready status.
     * BR-207: Pickup orders follow: Ready > Ready for Pickup > Picked Up > Completed
     *
     * @var array<int, string>
     */
    public const PICKUP_PATH = [
        Order::STATUS_READY,
        Order::STATUS_READY_FOR_PICKUP,
        Order::STATUS_PICKED_UP,
        Order::STATUS_COMPLETED,
    ];

    /**
     * Statuses from which cancellation is allowed.
     * BR-204: Before cook starts preparing.
     *
     * @var array<string>
     */
    public const CANCELLABLE_STATUSES = [
        Order::STATUS_PENDING_PAYMENT,
        Order::STATUS_PAID,
        Order::STATUS_CONFIRMED,
    ];

    /**
     * Validate a status transition for an order.
     *
     * BR-200 through BR-211: Complete transition validation.
     *
     * @param  array{admin_override?: bool, override_reason?: string, user?: User}  $options
     * @return array{valid: bool, message: string, current_status?: string, attempted_status?: string, next_valid_status?: string|null}
     */
    public function validate(Order $order, string $targetStatus, array $options = []): array
    {
        $isAdminOverride = $options['admin_override'] ?? false;
        $overrideReason = $options['override_reason'] ?? null;

        // Validate order has a valid current status
        if (! $order->status || ! in_array($order->status, Order::STATUSES, true)) {
            return $this->fail(
                __('Invalid order status.'),
                $order->status ?? '',
                $targetStatus
            );
        }

        // Validate target status is a known status
        if (! in_array($targetStatus, Order::STATUSES, true) && $targetStatus !== Order::STATUS_REFUNDED) {
            return $this->fail(
                __('Invalid target status.'),
                $order->status,
                $targetStatus
            );
        }

        // No-op: already at target status
        if ($order->status === $targetStatus) {
            return $this->pass();
        }

        // BR-205: Refunded status validation
        if ($targetStatus === Order::STATUS_REFUNDED) {
            return $this->validateRefundedTransition($order, $isAdminOverride);
        }

        // BR-204: Cancellation validation
        if ($targetStatus === Order::STATUS_CANCELLED) {
            return $this->validateCancellationTransition($order, $isAdminOverride);
        }

        // BR-209: Terminal states cannot transition (except admin override)
        if ($order->isTerminal()) {
            if ($isAdminOverride) {
                return $this->validateAdminOverride($overrideReason);
            }

            return $this->fail(
                __('This order is in a terminal state and cannot be updated.'),
                $order->status,
                $targetStatus
            );
        }

        // BR-202: Check for backward transition
        if ($this->isBackwardTransition($order, $targetStatus)) {
            if ($isAdminOverride) {
                return $this->validateAdminOverride($overrideReason);
            }

            return $this->fail(
                __('Cannot move order backward from :current to :target.', [
                    'current' => Order::getStatusLabel($order->status),
                    'target' => Order::getStatusLabel($targetStatus),
                ]),
                $order->status,
                $targetStatus
            );
        }

        // BR-208: Cross-path validation (delivery order can't use pickup transitions and vice versa)
        $crossPathResult = $this->validatePathConsistency($order, $targetStatus);
        if (! $crossPathResult['valid']) {
            return $crossPathResult;
        }

        // BR-201: Check for state skipping
        $nextValidStatus = $order->getNextStatus();

        if (! $nextValidStatus) {
            return $this->fail(
                __('No valid transition available for this order.'),
                $order->status,
                $targetStatus
            );
        }

        if ($targetStatus !== $nextValidStatus) {
            return $this->fail(
                __('Cannot transition from :current to :target. Next valid status: :next.', [
                    'current' => Order::getStatusLabel($order->status),
                    'target' => Order::getStatusLabel($targetStatus),
                    'next' => Order::getStatusLabel($nextValidStatus),
                ]),
                $order->status,
                $targetStatus,
                $nextValidStatus
            );
        }

        return $this->pass();
    }

    /**
     * Check if cancellation is allowed for this order.
     *
     * BR-204: Cancellation allowed from pending_payment, paid, or confirmed.
     *
     * @return array{valid: bool, message: string, current_status?: string, attempted_status?: string, next_valid_status?: string|null}
     */
    public function validateCancellationTransition(Order $order, bool $isAdminOverride = false): array
    {
        // Already cancelled is a no-op
        if ($order->status === Order::STATUS_CANCELLED) {
            return $this->pass();
        }

        // Admin can cancel from any non-terminal state
        if ($isAdminOverride) {
            if ($order->isTerminal()) {
                return $this->fail(
                    __('This order is in a terminal state and cannot be cancelled.'),
                    $order->status,
                    Order::STATUS_CANCELLED
                );
            }

            return $this->pass();
        }

        // BR-204: Regular cancellation only from specific statuses
        if (in_array($order->status, self::CANCELLABLE_STATUSES, true)) {
            return $this->pass();
        }

        return $this->fail(
            __('Orders cannot be cancelled after :status status.', [
                'status' => Order::getStatusLabel(Order::STATUS_CONFIRMED),
            ]),
            $order->status,
            Order::STATUS_CANCELLED
        );
    }

    /**
     * Validate transition to Refunded status.
     *
     * BR-205: Refunded can only be reached from Cancelled or via admin complaint resolution.
     *
     * @return array{valid: bool, message: string, current_status?: string, attempted_status?: string, next_valid_status?: string|null}
     */
    public function validateRefundedTransition(Order $order, bool $isAdminOverride = false): array
    {
        // Already refunded is a no-op
        if ($order->status === Order::STATUS_REFUNDED) {
            return $this->pass();
        }

        // BR-205: From cancelled status is always valid
        if ($order->status === Order::STATUS_CANCELLED) {
            return $this->pass();
        }

        // BR-205: Admin complaint resolution can set Refunded from any state
        if ($isAdminOverride) {
            return $this->pass();
        }

        return $this->fail(
            __('Orders can only be refunded from Cancelled status or via admin resolution.'),
            $order->status,
            Order::STATUS_REFUNDED
        );
    }

    /**
     * Check if the transition is a backward move in the chain.
     *
     * BR-202: No backward transitions without admin override.
     */
    public function isBackwardTransition(Order $order, string $targetStatus): bool
    {
        $fullChain = $this->getFullChainForOrder($order);

        $currentIndex = array_search($order->status, $fullChain, true);
        $targetIndex = array_search($targetStatus, $fullChain, true);

        // If either status is not in the chain (e.g., cancelled, refunded), not a backward move
        if ($currentIndex === false || $targetIndex === false) {
            return false;
        }

        return $targetIndex < $currentIndex;
    }

    /**
     * Validate that the target status is consistent with the order's delivery method.
     *
     * BR-206: Delivery orders use delivery path.
     * BR-207: Pickup orders use pickup path.
     * BR-208: Cross-path transitions are rejected.
     *
     * @return array{valid: bool, message: string, current_status?: string, attempted_status?: string, next_valid_status?: string|null}
     */
    public function validatePathConsistency(Order $order, string $targetStatus): array
    {
        $deliveryOnlyStatuses = [
            Order::STATUS_OUT_FOR_DELIVERY,
            Order::STATUS_DELIVERED,
        ];

        $pickupOnlyStatuses = [
            Order::STATUS_READY_FOR_PICKUP,
            Order::STATUS_PICKED_UP,
        ];

        // BR-208: Delivery order cannot use pickup statuses
        if ($order->delivery_method === Order::METHOD_DELIVERY && in_array($targetStatus, $pickupOnlyStatuses, true)) {
            return $this->fail(
                __('Delivery orders cannot use pickup status transitions.'),
                $order->status,
                $targetStatus,
                Order::STATUS_OUT_FOR_DELIVERY
            );
        }

        // BR-208: Pickup order cannot use delivery statuses
        if ($order->delivery_method === Order::METHOD_PICKUP && in_array($targetStatus, $deliveryOnlyStatuses, true)) {
            return $this->fail(
                __('Pickup orders cannot use delivery status transitions.'),
                $order->status,
                $targetStatus,
                Order::STATUS_READY_FOR_PICKUP
            );
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * Get all valid next statuses for an order (including cancellation if applicable).
     *
     * Useful for UI to display available actions.
     *
     * @return array<string>
     */
    public function getValidNextStatuses(Order $order, bool $isAdmin = false): array
    {
        $statuses = [];

        // Forward transition
        $nextStatus = $order->getNextStatus();
        if ($nextStatus) {
            $statuses[] = $nextStatus;
        }

        // Cancellation if allowed
        if ($this->canCancel($order, $isAdmin)) {
            $statuses[] = Order::STATUS_CANCELLED;
        }

        // Admin: Refunded from cancelled
        if ($isAdmin && $order->status === Order::STATUS_CANCELLED) {
            $statuses[] = Order::STATUS_REFUNDED;
        }

        return $statuses;
    }

    /**
     * Check if the order can be cancelled by the given user context.
     */
    public function canCancel(Order $order, bool $isAdmin = false): bool
    {
        if ($order->status === Order::STATUS_CANCELLED) {
            return false;
        }

        if ($isAdmin && ! $order->isTerminal()) {
            return true;
        }

        return in_array($order->status, self::CANCELLABLE_STATUSES, true);
    }

    /**
     * Get the full ordered chain for a specific order based on its delivery method.
     *
     * @return array<int, string>
     */
    public function getFullChainForOrder(Order $order): array
    {
        $basePath = self::FORWARD_CHAIN;

        if ($order->delivery_method === Order::METHOD_PICKUP) {
            return array_merge($basePath, array_slice(self::PICKUP_PATH, 1));
        }

        // Default to delivery path
        return array_merge($basePath, array_slice(self::DELIVERY_PATH, 1));
    }

    /**
     * Validate admin override requirements.
     *
     * BR-203: Admin override requires a reason.
     *
     * @return array{valid: bool, message: string}
     */
    private function validateAdminOverride(?string $reason): array
    {
        if (empty($reason)) {
            return $this->fail(
                __('A reason is required for admin overrides.'),
                '',
                ''
            );
        }

        return $this->pass();
    }

    /**
     * Build a passing validation result.
     *
     * @return array{valid: bool, message: string}
     */
    private function pass(): array
    {
        return [
            'valid' => true,
            'message' => '',
        ];
    }

    /**
     * Build a failing validation result.
     *
     * BR-210: Returns current status, attempted status, and next valid status.
     *
     * @return array{valid: bool, message: string, current_status: string, attempted_status: string, next_valid_status: string|null}
     */
    private function fail(string $message, string $currentStatus, string $attemptedStatus, ?string $nextValidStatus = null): array
    {
        return [
            'valid' => false,
            'message' => $message,
            'current_status' => $currentStatus,
            'attempted_status' => $attemptedStatus,
            'next_valid_status' => $nextValidStatus,
        ];
    }
}
