<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * F-157: Service layer for single order status updates.
 *
 * Handles validation, transition recording, timestamp updates,
 * activity logging, and notification dispatch.
 *
 * F-159: Delegates transition validation to OrderTransitionValidator.
 */
class OrderStatusService
{
    public function __construct(
        private OrderTransitionValidator $transitionValidator,
    ) {}

    /**
     * Statuses requiring a confirmation dialog before updating.
     *
     * BR-181: Confirmation required for Confirmed and Completed transitions.
     *
     * @var array<string>
     */
    public const CONFIRM_REQUIRED_STATUSES = [
        Order::STATUS_CONFIRMED,
        Order::STATUS_COMPLETED,
    ];

    /**
     * Update the status of an order to the next valid status.
     *
     * BR-178: Only the next valid status is allowed.
     * BR-182: Validated server-side.
     * BR-183: Triggers notification to client.
     * BR-184: Logged via Spatie Activitylog.
     * BR-185: Timeline updates after transition.
     * F-159 BR-203: Admin override support with reason logging.
     *
     * @param  array{admin_override?: bool, override_reason?: string}  $options
     * @return array{success: bool, message: string, order?: Order, new_status?: string}
     */
    public function updateStatus(Order $order, string $targetStatus, User $user, array $options = []): array
    {
        // Handle duplicate/no-op requests (edge case: order already in target status)
        if ($order->status === $targetStatus) {
            return [
                'success' => true,
                'message' => __('Order is already in this status.'),
                'order' => $order,
                'new_status' => $targetStatus,
            ];
        }

        // BR-182/F-159: Validate the transition is allowed
        $validationResult = $this->validateTransition($order, $targetStatus, $options);
        if (! $validationResult['valid']) {
            return [
                'success' => false,
                'message' => $validationResult['message'],
            ];
        }

        $isAdminOverride = $options['admin_override'] ?? false;
        $overrideReason = $options['override_reason'] ?? null;

        return DB::transaction(function () use ($order, $targetStatus, $user, $isAdminOverride, $overrideReason) {
            $previousStatus = $order->status;

            // Optimistic locking: re-fetch the order status to prevent race conditions
            $freshOrder = Order::query()->lockForUpdate()->find($order->id);

            if (! $freshOrder || $freshOrder->status !== $previousStatus) {
                return [
                    'success' => false,
                    'message' => __('This order has been updated by another user. Please refresh and try again.'),
                ];
            }

            // Update order status
            $freshOrder->status = $targetStatus;

            // Update timestamp fields as applicable
            $this->updateTimestampFields($freshOrder, $targetStatus);

            // Disable auto-logging so we can explicitly set the causer via manual log
            $freshOrder->disableLogging();
            $freshOrder->save();
            $freshOrder->enableLogging();

            // BR-185/F-159 BR-203: Create transition record with admin override tracking
            OrderStatusTransition::create([
                'order_id' => $freshOrder->id,
                'triggered_by' => $user->id,
                'previous_status' => $previousStatus,
                'new_status' => $targetStatus,
                'is_admin_override' => $isAdminOverride,
                'override_reason' => $isAdminOverride ? $overrideReason : null,
            ]);

            // BR-184/F-159 BR-203: Activity log entry (elevated for admin overrides)
            $this->logStatusChange($freshOrder, $previousStatus, $targetStatus, $user, $isAdminOverride, $overrideReason);

            // BR-183: Trigger notification to client (forward-compatible for F-192)
            $this->dispatchStatusNotification($freshOrder, $previousStatus, $targetStatus);

            // BR-186: Completing triggers commission deduction + withdrawable timer
            // Forward-compatible stubs for F-175 and F-171
            if ($targetStatus === Order::STATUS_COMPLETED) {
                $this->handleOrderCompletion($freshOrder);
            }

            return [
                'success' => true,
                'message' => __('Order status updated to :status', ['status' => Order::getStatusLabel($targetStatus)]),
                'order' => $freshOrder,
                'new_status' => $targetStatus,
            ];
        });
    }

    /**
     * Validate that the requested transition is allowed.
     *
     * BR-182: Server-side transition validation.
     * F-159: Delegates to OrderTransitionValidator for comprehensive validation.
     *
     * @param  array{admin_override?: bool, override_reason?: string, user?: User}  $options
     * @return array{valid: bool, message: string}
     */
    public function validateTransition(Order $order, string $targetStatus, array $options = []): array
    {
        return $this->transitionValidator->validate($order, $targetStatus, $options);
    }

    /**
     * Check if a status transition requires a confirmation dialog.
     *
     * BR-181: Confirmation dialog required for Confirmed and Completed transitions.
     */
    public function requiresConfirmation(string $targetStatus): bool
    {
        return in_array($targetStatus, self::CONFIRM_REQUIRED_STATUSES, true);
    }

    /**
     * Get the button label for the next status action.
     *
     * BR-178: Button label dynamically reflects the next status.
     */
    public static function getActionLabel(string $targetStatus): string
    {
        return match ($targetStatus) {
            Order::STATUS_CONFIRMED => __('Confirm Order'),
            Order::STATUS_PREPARING => __('Start Preparing'),
            Order::STATUS_READY => __('Mark as Ready'),
            Order::STATUS_OUT_FOR_DELIVERY => __('Out for Delivery'),
            Order::STATUS_READY_FOR_PICKUP => __('Ready for Pickup'),
            Order::STATUS_DELIVERED => __('Mark as Delivered'),
            Order::STATUS_PICKED_UP => __('Mark as Picked Up'),
            Order::STATUS_COMPLETED => __('Mark as Completed'),
            default => __('Update Status'),
        };
    }

    /**
     * Get the confirmation message for a status transition.
     *
     * BR-181: Confirmation dialog with clear messaging about consequences.
     */
    public static function getConfirmationMessage(string $targetStatus): string
    {
        return match ($targetStatus) {
            Order::STATUS_CONFIRMED => __('Confirm this order? The client will be notified.'),
            Order::STATUS_COMPLETED => __('Completing this order will start the payment clearance timer. Continue?'),
            default => __('Are you sure you want to update this order status?'),
        };
    }

    /**
     * Update timestamp fields based on the new status.
     */
    private function updateTimestampFields(Order $order, string $newStatus): void
    {
        match ($newStatus) {
            Order::STATUS_CONFIRMED => $order->confirmed_at = now(),
            Order::STATUS_COMPLETED => $order->completed_at = now(),
            Order::STATUS_CANCELLED => $order->cancelled_at = now(),
            default => null,
        };
    }

    /**
     * Log the status change via Spatie Activitylog.
     *
     * BR-184: Each status change is logged with the user who triggered it.
     * F-159 BR-203: Admin overrides are logged with elevated audit trail.
     */
    private function logStatusChange(
        Order $order,
        string $previousStatus,
        string $newStatus,
        User $user,
        bool $isAdminOverride = false,
        ?string $overrideReason = null,
    ): void {
        $properties = [
            'attributes' => ['status' => $newStatus],
            'old' => ['status' => $previousStatus],
        ];

        // F-159 BR-203: Add admin override details to audit trail
        if ($isAdminOverride) {
            $properties['admin_override'] = true;
            $properties['override_reason'] = $overrideReason;
        }

        $logMessage = $isAdminOverride
            ? "Admin override: Order status changed from {$previousStatus} to {$newStatus} (Reason: {$overrideReason})"
            : "Order status changed from {$previousStatus} to {$newStatus}";

        activity('orders')
            ->causedBy($user)
            ->performedOn($order)
            ->withProperties($properties)
            ->event('updated')
            ->log($logMessage);
    }

    /**
     * Dispatch notification to the client about the status change.
     *
     * BR-183: Each status change triggers a notification to the client (push + DB).
     * Forward-compatible: F-192 will implement the actual notification class.
     */
    private function dispatchStatusNotification(Order $order, string $previousStatus, string $newStatus): void
    {
        // F-192: Order Status Update Notifications will implement this
        // For now, we just load the client relationship if needed
        if (! $order->relationLoaded('client')) {
            $order->load('client');
        }

        // Stub: notification dispatch will be added by F-192
        // $order->client->notify(new OrderStatusUpdateNotification($order, $previousStatus, $newStatus));
    }

    /**
     * Handle order completion side effects.
     *
     * BR-186: Completing triggers commission deduction and withdrawable timer.
     * Forward-compatible stubs for F-175 and F-171.
     */
    private function handleOrderCompletion(Order $order): void
    {
        // F-175: Commission Deduction on Completion — will deduct commission here
        // F-171: Withdrawable Timer Logic — will start timer here
    }

    /**
     * Get the status timeline from the transitions table.
     *
     * More reliable than parsing activity log since transitions are explicitly recorded.
     *
     * @return array<int, array{status: string, label: string, timestamp: string, relative_time: string, user: string}>
     */
    public function getTransitionTimeline(Order $order): array
    {
        $transitions = OrderStatusTransition::query()
            ->where('order_id', $order->id)
            ->with('triggeredBy:id,name')
            ->orderBy('created_at')
            ->get();

        $timeline = [];

        // Always include order creation as first entry
        $timeline[] = [
            'status' => Order::STATUS_PENDING_PAYMENT,
            'label' => Order::getStatusLabel(Order::STATUS_PENDING_PAYMENT),
            'timestamp' => $order->created_at?->format('M d, Y H:i') ?? '',
            'relative_time' => $order->created_at?->diffForHumans() ?? '',
            'user' => $order->client?->name ?? __('Customer'),
        ];

        // Add paid_at timestamp if available
        if ($order->paid_at) {
            $timeline[] = [
                'status' => Order::STATUS_PAID,
                'label' => Order::getStatusLabel(Order::STATUS_PAID),
                'timestamp' => $order->paid_at->format('M d, Y H:i'),
                'relative_time' => $order->paid_at->diffForHumans(),
                'user' => __('System'),
            ];
        }

        // Add explicit transitions
        foreach ($transitions as $transition) {
            // Skip paid status if already added from paid_at
            if ($transition->new_status === Order::STATUS_PAID && $order->paid_at) {
                continue;
            }

            $timeline[] = [
                'status' => $transition->new_status,
                'label' => Order::getStatusLabel($transition->new_status),
                'timestamp' => $transition->created_at->format('M d, Y H:i'),
                'relative_time' => $transition->created_at->diffForHumans(),
                'user' => $transition->triggeredBy?->name ?? __('System'),
            ];
        }

        // Sort by timestamp
        usort($timeline, function ($a, $b) {
            return strcmp($a['timestamp'], $b['timestamp']);
        });

        return $timeline;
    }
}
