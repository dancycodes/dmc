<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OrderCancelledNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * F-162: Order Cancellation Service
 *
 * Handles all business logic for client-initiated order cancellation.
 *
 * BR-236: Cancellation only for Paid or Confirmed orders.
 * BR-237: Window from cook's setting (snapshot at order time), default from CookSettingsService.
 * BR-238: Timer starts from order's created_at.
 * BR-241: Server re-validates status AND time window before processing.
 * BR-242: Order status → Cancelled; set orders.cancelled_at.
 * BR-243: Dispatch refund processing (F-163 will handle).
 * BR-244: Cook + manager(s) notified (push + DB: N-017).
 * BR-245: Log via Spatie Activitylog.
 * BR-246: Client can only cancel their own orders (enforced at controller level).
 */
class OrderCancellationService
{
    public function __construct(
        private readonly ClientOrderService $clientOrderService,
    ) {}

    /**
     * Attempt to cancel an order.
     *
     * Performs server-side re-validation of status and time window (BR-241),
     * updates order to Cancelled status (BR-242), logs the action (BR-245),
     * dispatches refund trigger (BR-243), and notifies cook + managers (BR-244).
     *
     * @return array{success: bool, message: string}
     */
    public function cancelOrder(Order $order, User $client): array
    {
        // BR-241: Server re-validates status
        if (! in_array($order->status, [Order::STATUS_PAID, Order::STATUS_CONFIRMED], true)) {
            return [
                'success' => false,
                'message' => __('This order cannot be cancelled. It may have already been confirmed or prepared.'),
            ];
        }

        // BR-241: Server re-validates time window
        if (! $this->clientOrderService->canCancelOrder($order)) {
            return [
                'success' => false,
                'message' => __('The cancellation window for this order has expired.'),
            ];
        }

        $previousStatus = $order->status;

        $result = DB::transaction(function () use ($order, $previousStatus, $client) {
            // Pessimistic lock to prevent race conditions
            $freshOrder = Order::query()->lockForUpdate()->find($order->id);

            if (! $freshOrder) {
                return ['success' => false, 'message' => __('Order not found.')];
            }

            // Re-check status inside the transaction (BR-241: concurrent status change)
            if (! in_array($freshOrder->status, [Order::STATUS_PAID, Order::STATUS_CONFIRMED], true)) {
                return [
                    'success' => false,
                    'message' => __('This order cannot be cancelled. It may have already been confirmed or prepared.'),
                ];
            }

            // Re-check time window inside the transaction (BR-241)
            if (! $this->clientOrderService->canCancelOrder($freshOrder)) {
                return [
                    'success' => false,
                    'message' => __('The cancellation window for this order has expired.'),
                ];
            }

            // BR-242: Update order status and set cancelled_at
            $freshOrder->disableLogging();
            $freshOrder->status = Order::STATUS_CANCELLED;
            $freshOrder->cancelled_at = now();
            $freshOrder->save();
            $freshOrder->enableLogging();

            // Record status transition (consistent with OrderStatusService pattern)
            OrderStatusTransition::create([
                'order_id' => $freshOrder->id,
                'triggered_by' => $client->id,
                'previous_status' => $previousStatus,
                'new_status' => Order::STATUS_CANCELLED,
                'is_admin_override' => false,
                'override_reason' => null,
            ]);

            // BR-245: Log via Spatie Activitylog
            activity('orders')
                ->performedOn($freshOrder)
                ->causedBy($client)
                ->withProperties([
                    'old' => ['status' => $previousStatus],
                    'attributes' => ['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now()->toISOString()],
                ])
                ->log('order_cancelled_by_client');

            return ['success' => true, 'order' => $freshOrder];
        });

        if (! ($result['success'] ?? false)) {
            return $result;
        }

        $cancelledOrder = $result['order'];

        // BR-243: Dispatch refund processing (F-163 will implement; trigger event here)
        $this->dispatchRefundTrigger($cancelledOrder, $client);

        // BR-244: Notify cook + managers
        $this->notifyCancellation($cancelledOrder);

        return [
            'success' => true,
            'message' => __('Order cancelled. Refund will be credited to your wallet.'),
        ];
    }

    /**
     * BR-243: Trigger refund processing for the cancelled order.
     *
     * F-163 will implement the actual refund logic. For now we dispatch
     * a placeholder that F-163 will hook into.
     */
    private function dispatchRefundTrigger(Order $order, User $client): void
    {
        // F-163: WalletRefundService::creditCancellationRefund() will be called here
        // For now, log that refund is pending so F-163 can integrate cleanly.
        try {
            if (class_exists(\App\Services\WalletRefundService::class)) {
                app(\App\Services\WalletRefundService::class)->creditCancellationRefund($order);
            } else {
                Log::info('F-162: Refund trigger dispatched (F-163 will process)', [
                    'order_id' => $order->id,
                    'grand_total' => $order->grand_total,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('F-162: Refund dispatch failed — will require manual processing', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * BR-244: Notify cook and managers of the cancellation (push + DB, N-017).
     */
    private function notifyCancellation(Order $order): void
    {
        try {
            $order->loadMissing('tenant.cook');
            $tenant = $order->tenant;

            if (! $tenant) {
                return;
            }

            $recipients = $this->resolveNotificationRecipients($tenant);

            foreach ($recipients as $recipient) {
                try {
                    $recipient->notify(new OrderCancelledNotification($order, $tenant));
                } catch (\Throwable $e) {
                    Log::warning('F-162: Cancellation notification failed', [
                        'order_id' => $order->id,
                        'recipient_id' => $recipient->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('F-162: Notification dispatch failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve cook + managers with can-manage-orders permission for notification.
     *
     * Matches the pattern used by OrderNotificationService (F-191).
     *
     * @return array<User>
     */
    private function resolveNotificationRecipients(Tenant $tenant): array
    {
        $recipients = [];
        $seenIds = [];

        $cook = $tenant->cook;
        if ($cook) {
            $recipients[] = $cook;
            $seenIds[] = $cook->id;
        }

        try {
            $managers = User::permission('can-manage-orders')->get();
            foreach ($managers as $manager) {
                if (! in_array($manager->id, $seenIds, true)) {
                    $recipients[] = $manager;
                    $seenIds[] = $manager->id;
                }
            }
        } catch (\Throwable) {
            // Permission may not exist yet — silent fail
        }

        return $recipients;
    }
}
