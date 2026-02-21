<?php

namespace App\Jobs;

use App\Models\CookWallet;
use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Models\User;
use App\Services\CookWalletService;
use App\Services\WalletRefundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * F-163: Order Cancellation Refund Processing
 *
 * Processes the full refund flow when a client cancels an order.
 * Dispatched by OrderCancellationService after order is marked Cancelled.
 *
 * BR-248: Full order amount (subtotal + delivery fee) credited to client wallet.
 * BR-249: Refunds always go to wallet, never back to mobile money.
 * BR-250: Cook's unwithdrawable amount decremented by order total.
 * BR-251: No commission on cancelled orders.
 * BR-252: Client wallet transaction created (type: refund, credit).
 * BR-253: Cook wallet transaction created (type: order_cancelled, debit from unwithdrawable).
 * BR-254: Order status transitions from Cancelled → Refunded; sets refunded_at.
 * BR-255: Client notified (push + DB + email via N-008).
 * BR-256: Automatic and immediate upon cancellation.
 * BR-257: Wallet balance cannot go negative (refunds always add).
 * BR-258: All amounts in XAF.
 * BR-259: Logged via Spatie Activitylog.
 */
class ProcessOrderRefund implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times this job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     *
     * @var array<int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $orderId,
        public readonly int $clientId,
    ) {}

    /**
     * Execute the refund processing job.
     *
     * Idempotent: if order is already Refunded, skip (BR idempotency from directive).
     * Full DB transaction for atomicity — rolls back all on mid-way failure.
     */
    public function handle(WalletRefundService $walletRefundService, CookWalletService $cookWalletService): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order) {
            Log::error('F-163: ProcessOrderRefund — order not found', ['order_id' => $this->orderId]);

            return;
        }

        // BR idempotency: If already Refunded, do nothing
        if ($order->status === Order::STATUS_REFUNDED) {
            Log::info('F-163: ProcessOrderRefund — order already refunded, skipping', [
                'order_id' => $this->orderId,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        // Order must be in Cancelled status to be refunded
        if ($order->status !== Order::STATUS_CANCELLED) {
            Log::warning('F-163: ProcessOrderRefund — order is not in Cancelled status', [
                'order_id' => $this->orderId,
                'status' => $order->status,
            ]);

            return;
        }

        $client = User::query()->find($this->clientId);

        if (! $client) {
            Log::error('F-163: ProcessOrderRefund — client not found', ['client_id' => $this->clientId]);

            return;
        }

        // BR-248: Full amount = grand_total (subtotal + delivery fee)
        // BR-258: All amounts in XAF (integer-based on orders table)
        $refundAmount = (float) $order->grand_total;

        DB::transaction(function () use ($order, $client, $refundAmount, $walletRefundService, $cookWalletService) {
            // Lock order row to prevent duplicate processing
            $freshOrder = Order::query()->lockForUpdate()->find($order->id);

            if (! $freshOrder) {
                return;
            }

            // Double-check idempotency inside transaction
            if ($freshOrder->status === Order::STATUS_REFUNDED) {
                return;
            }

            if ($freshOrder->status !== Order::STATUS_CANCELLED) {
                return;
            }

            // BR-252: Credit client wallet (type: refund) + BR-255: Client notification
            // WalletRefundService handles: lazy wallet creation, balance increment,
            // transaction record, Spatie Activitylog, push+DB+email notification.
            $walletRefundService->creditCancellationRefund($client, $refundAmount, $freshOrder);

            // BR-250/BR-253: Decrement cook's unwithdrawable balance
            $this->decrementCookWallet($freshOrder, $refundAmount, $cookWalletService);

            // BR-254: Transition order status from Cancelled → Refunded
            $previousStatus = $freshOrder->status;

            $freshOrder->disableLogging();
            $freshOrder->status = Order::STATUS_REFUNDED;
            $freshOrder->refunded_at = now();
            $freshOrder->save();
            $freshOrder->enableLogging();

            // Record status transition for timeline tracking
            OrderStatusTransition::create([
                'order_id' => $freshOrder->id,
                'triggered_by' => null,
                'previous_status' => $previousStatus,
                'new_status' => Order::STATUS_REFUNDED,
                'is_admin_override' => false,
                'override_reason' => null,
            ]);

            // BR-259: Log via Spatie Activitylog
            activity('orders')
                ->performedOn($freshOrder)
                ->causedBy($client)
                ->withProperties([
                    'old' => ['status' => $previousStatus],
                    'attributes' => [
                        'status' => Order::STATUS_REFUNDED,
                        'refunded_at' => now()->toISOString(),
                    ],
                    'refund_amount' => $refundAmount,
                    'currency' => 'XAF',
                ])
                ->log('refund_processed');
        });

        Log::info('F-163: Refund processed successfully', [
            'order_id' => $this->orderId,
            'order_number' => $order->order_number,
            'refund_amount' => $refundAmount,
            'client_id' => $this->clientId,
        ]);
    }

    /**
     * Decrement the cook's unwithdrawable wallet balance for the cancelled order.
     *
     * BR-250: Cook's unwithdrawable amount for this order is decremented.
     * BR-253: Cook wallet transaction created (type: order_cancelled).
     * Edge case: if cook wallet doesn't exist, it's created with 0 and the debit is recorded.
     * Edge case: if unwithdrawable < amount, log error + continue (do not throw).
     */
    private function decrementCookWallet(Order $order, float $amount, CookWalletService $cookWalletService): void
    {
        if ($amount <= 0) {
            // BR-248 edge case: free order (0 XAF) — still move to Refunded, no wallet changes needed
            Log::info('F-163: Free order — no cook wallet decrement needed', ['order_id' => $order->id]);

            return;
        }

        $order->loadMissing('tenant');
        $tenant = $order->tenant;

        if (! $tenant) {
            Log::warning('F-163: Cannot decrement cook wallet — tenant not found', [
                'order_id' => $order->id,
                'tenant_id' => $order->tenant_id,
            ]);

            return;
        }

        // Resolve the cook: use order's cook_id if set, fall back to tenant's cook
        $cookId = $order->cook_id ?? $tenant->cook_id;

        if (! $cookId) {
            Log::warning('F-163: Cannot decrement cook wallet — cook not resolved', [
                'order_id' => $order->id,
            ]);

            return;
        }

        $cook = User::query()->find($cookId);

        if (! $cook) {
            Log::warning('F-163: Cannot decrement cook wallet — cook user not found', [
                'cook_id' => $cookId,
                'order_id' => $order->id,
            ]);

            return;
        }

        $wallet = CookWallet::getOrCreateForTenant($tenant, $cook);

        try {
            $cookWalletService->decrementUnwithdrawableForCancellation($wallet, $order, $amount);
        } catch (\Throwable $e) {
            // Log but do not rethrow — client refund is more critical
            Log::error('F-163: Failed to decrement cook wallet — client refund was still processed', [
                'order_id' => $order->id,
                'cook_id' => $cookId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('F-163: ProcessOrderRefund job failed', [
            'order_id' => $this->orderId,
            'client_id' => $this->clientId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
