<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * F-152: Payment Retry with Timeout
 *
 * Handles payment retry logic including retry window management,
 * attempt tracking, auto-cancellation, and new charge initiation.
 *
 * BR-376: On payment failure, order remains in "Pending Payment" status.
 * BR-377: 15-minute retry window from initial payment attempt.
 * BR-379: Maximum 3 retry attempts allowed per order.
 * BR-380: Each retry creates a new Flutterwave charge.
 * BR-381: After 15 minutes without success, order auto-cancels.
 * BR-383: Failure reason from Flutterwave displayed to client.
 */
class PaymentRetryService
{
    public function __construct(
        private FlutterwaveService $flutterwaveService,
        private PaymentService $paymentService,
    ) {}

    /**
     * Get the retry page data for an order.
     *
     * BR-378: A visible countdown timer shows remaining retry time.
     * BR-383: Failure reason from Flutterwave displayed.
     *
     * @return array{order: Order, can_retry: bool, retry_count: int, max_retries: int, remaining_seconds: int, failure_reason: string|null, is_expired: bool, is_retries_exhausted: bool}
     */
    public function getRetryData(Order $order): array
    {
        $latestTransaction = $this->getLatestTransaction($order);
        $failureReason = $this->getFailureReason($latestTransaction);

        return [
            'order' => $order,
            'can_retry' => $order->canRetryPayment(),
            'retry_count' => $order->retry_count,
            'max_retries' => Order::MAX_RETRY_ATTEMPTS,
            'remaining_seconds' => $order->getPaymentTimeoutRemainingSeconds(),
            'failure_reason' => $failureReason,
            'is_expired' => $order->isPaymentTimedOut(),
            'is_retries_exhausted' => $order->hasExhaustedRetries(),
        ];
    }

    /**
     * Initiate a retry payment attempt.
     *
     * BR-379: Maximum 3 retry attempts allowed per order.
     * BR-380: Each retry creates a new Flutterwave charge with same order details.
     *
     * @return array{success: bool, error: string|null}
     */
    public function retryPayment(Order $order, User $client, string $provider, ?string $paymentPhone): array
    {
        // BR-379: Check retry limit
        if (! $order->canRetryPayment()) {
            if ($order->hasExhaustedRetries()) {
                return [
                    'success' => false,
                    'error' => __('Maximum retry attempts reached. Your order has been cancelled.'),
                ];
            }

            if ($order->isPaymentTimedOut()) {
                return [
                    'success' => false,
                    'error' => __('Order expired. Payment was not completed within the allowed time.'),
                ];
            }

            return [
                'success' => false,
                'error' => __('This order cannot be retried.'),
            ];
        }

        // Update order with new payment details if changed
        $order->update([
            'payment_provider' => $provider,
            'payment_phone' => $paymentPhone,
            'retry_count' => $order->retry_count + 1,
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        // BR-380: Create a new Flutterwave charge
        $paymentResult = $this->paymentService->initiatePayment($order->fresh(), $client);

        if (! $paymentResult['success']) {
            Log::warning('Payment retry failed', [
                'order_id' => $order->id,
                'retry_count' => $order->retry_count,
                'error' => $paymentResult['error'],
            ]);

            // Update order status to payment_failed
            $order->update(['status' => Order::STATUS_PAYMENT_FAILED]);

            // Check if this was the last attempt
            if ($order->fresh()->hasExhaustedRetries()) {
                $this->cancelOrderAfterMaxRetries($order->fresh());

                return [
                    'success' => false,
                    'error' => __('Maximum retry attempts reached. Your order has been cancelled.'),
                ];
            }

            return [
                'success' => false,
                'error' => $paymentResult['error'],
            ];
        }

        // Activity log
        activity('orders')
            ->performedOn($order)
            ->causedBy($client)
            ->withProperties([
                'order_number' => $order->order_number,
                'retry_attempt' => $order->retry_count,
                'provider' => $provider,
            ])
            ->log('Payment retry initiated');

        return [
            'success' => true,
            'error' => null,
        ];
    }

    /**
     * Cancel an order after max retries are exhausted.
     *
     * BR-384: After retry limit is reached, the Retry button is disabled.
     * BR-385: Cancelled orders release any held stock/availability.
     */
    public function cancelOrderAfterMaxRetries(Order $order): void
    {
        $order->update([
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        activity('orders')
            ->performedOn($order)
            ->withProperties([
                'order_number' => $order->order_number,
                'reason' => 'Maximum retry attempts exhausted',
                'retry_count' => $order->retry_count,
            ])
            ->log('Order cancelled: max retry attempts exhausted');

        Log::info('Order cancelled after max retries', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'retry_count' => $order->retry_count,
        ]);
    }

    /**
     * Cancel expired orders that have exceeded the retry window.
     *
     * BR-381: After 15 minutes without successful payment, order auto-cancels.
     * BR-382: Auto-cancellation handled by scheduled job.
     */
    public function cancelExpiredOrders(): int
    {
        $expiredOrders = Order::query()
            ->whereIn('status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED])
            ->where(function ($query) {
                $query->where('payment_retry_expires_at', '<', now())
                    ->orWhere(function ($q) {
                        $q->whereNull('payment_retry_expires_at')
                            ->where('created_at', '<', now()->subMinutes(Order::PAYMENT_TIMEOUT_MINUTES));
                    });
            })
            ->get();

        $cancelled = 0;

        foreach ($expiredOrders as $order) {
            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            activity('orders')
                ->performedOn($order)
                ->withProperties([
                    'order_number' => $order->order_number,
                    'reason' => 'Payment retry window expired',
                ])
                ->log('Order auto-cancelled: payment retry window expired');

            $cancelled++;
        }

        if ($cancelled > 0) {
            Log::info('Auto-cancelled expired orders', ['count' => $cancelled]);
        }

        return $cancelled;
    }

    /**
     * Set the retry window expiry on an order.
     *
     * BR-377: The retry window is 15 minutes from the initial payment attempt.
     */
    public function initRetryWindow(Order $order): void
    {
        if (! $order->payment_retry_expires_at) {
            $order->update([
                'payment_retry_expires_at' => $order->created_at
                    ? $order->created_at->addMinutes(Order::PAYMENT_TIMEOUT_MINUTES)
                    : now()->addMinutes(Order::PAYMENT_TIMEOUT_MINUTES),
            ]);
        }
    }

    /**
     * Get the latest payment transaction for an order.
     */
    public function getLatestTransaction(Order $order): ?PaymentTransaction
    {
        return PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Get the failure reason from the latest transaction.
     *
     * BR-383: The failure reason from Flutterwave is displayed to the client.
     */
    public function getFailureReason(?PaymentTransaction $transaction): ?string
    {
        if (! $transaction) {
            return null;
        }

        if ($transaction->status !== 'failed') {
            return null;
        }

        $responseMessage = $transaction->response_message;

        if (! $responseMessage || $responseMessage === 'Payment failed') {
            return __('Payment could not be completed. Please try again.');
        }

        return $responseMessage;
    }
}
