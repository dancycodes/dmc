<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * F-151: Payment Webhook Handling
 *
 * Processes Flutterwave webhook events. Handles charge.completed events
 * with both success and failure outcomes. Ensures idempotent processing,
 * commission calculation, and cook wallet crediting.
 *
 * BR-364: Verify webhook signature
 * BR-365: On success: order status → "Paid"
 * BR-366: On success: cook wallet credited (amount - commission)
 * BR-367: Wallet credit initially unwithdrawable
 * BR-368: Commission = order amount * cook's commission rate
 * BR-369: Commission recorded separately
 * BR-370: On failure: order status → "Payment Failed"
 * BR-371: Idempotent — same event twice has no additional effect
 * BR-372: Transaction record created/updated with Flutterwave reference
 * BR-374: All processing logged via Spatie Activitylog
 * BR-375: Return 200 OK promptly
 */
class WebhookService
{
    /**
     * Verify Flutterwave webhook signature.
     *
     * BR-364: Webhook endpoint must verify the Flutterwave webhook signature/hash.
     */
    public function verifySignature(?string $signature): bool
    {
        $secret = config('flutterwave.webhook_secret', '');

        if (empty($secret) || empty($signature)) {
            return false;
        }

        return hash_equals($secret, $signature);
    }

    /**
     * Process a Flutterwave webhook event.
     *
     * BR-371: Idempotent — processing the same event twice has no additional effect.
     * BR-375: Returns promptly to prevent Flutterwave retries.
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string, already_processed: bool}
     */
    public function processWebhook(array $payload): array
    {
        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? [];

        // Edge case: Malformed payload
        if (empty($event) || empty($data)) {
            Log::warning('Flutterwave webhook: malformed payload', [
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'message' => 'Malformed payload',
                'already_processed' => false,
            ];
        }

        // Only handle charge.completed events
        if ($event !== 'charge.completed') {
            Log::info('Flutterwave webhook: unhandled event type', [
                'event' => $event,
            ]);

            return [
                'success' => true,
                'message' => 'Event type not handled',
                'already_processed' => false,
            ];
        }

        return $this->handleChargeCompleted($data, $payload);
    }

    /**
     * Handle the charge.completed event.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $fullPayload
     * @return array{success: bool, message: string, already_processed: bool}
     */
    private function handleChargeCompleted(array $data, array $fullPayload): array
    {
        $txRef = $data['tx_ref'] ?? null;
        $flwRef = $data['flw_ref'] ?? null;
        $status = $data['status'] ?? null;
        $amount = (int) ($data['amount'] ?? 0);

        if (empty($txRef)) {
            Log::warning('Flutterwave webhook: missing tx_ref', ['data' => $data]);

            return [
                'success' => false,
                'message' => 'Missing transaction reference',
                'already_processed' => false,
            ];
        }

        // Find the matching payment transaction by tx_ref
        $transaction = PaymentTransaction::query()
            ->where('flutterwave_tx_ref', $txRef)
            ->first();

        if (! $transaction) {
            // Edge case: Transaction reference not found (orphan webhook)
            Log::warning('Flutterwave webhook: orphan transaction reference', [
                'tx_ref' => $txRef,
                'flw_ref' => $flwRef,
            ]);

            return [
                'success' => true,
                'message' => 'Transaction reference not found',
                'already_processed' => false,
            ];
        }

        // BR-371: Idempotency check — already processed
        if ($this->isAlreadyProcessed($transaction)) {
            Log::info('Flutterwave webhook: duplicate event (idempotent)', [
                'tx_ref' => $txRef,
                'current_status' => $transaction->status,
            ]);

            return [
                'success' => true,
                'message' => 'Already processed',
                'already_processed' => true,
            ];
        }

        // Find the associated order
        $order = $transaction->order;

        if (! $order) {
            // Edge case: Order not found (webhook arrived before order fully created)
            Log::warning('Flutterwave webhook: order not found for transaction', [
                'tx_ref' => $txRef,
                'transaction_id' => $transaction->id,
            ]);

            return [
                'success' => false,
                'message' => 'Order not found',
                'already_processed' => false,
            ];
        }

        // Process based on payment status
        $paymentStatus = strtolower($status ?? '');

        if ($paymentStatus === 'successful') {
            return $this->handleSuccessfulPayment($transaction, $order, $data, $fullPayload);
        }

        return $this->handleFailedPayment($transaction, $order, $data, $fullPayload);
    }

    /**
     * Handle a successful payment.
     *
     * BR-365: Order status changes to "Paid"
     * BR-366: Cook wallet credited with (order amount - platform commission)
     * BR-367: Wallet credit initially "unwithdrawable"
     * BR-368: Commission = order amount * cook's commission rate (default 10%)
     * BR-369: Commission recorded as a separate transaction record
     * BR-372: Transaction record updated with Flutterwave reference
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $fullPayload
     * @return array{success: bool, message: string, already_processed: bool}
     */
    private function handleSuccessfulPayment(
        PaymentTransaction $transaction,
        Order $order,
        array $data,
        array $fullPayload,
    ): array {
        try {
            DB::transaction(function () use ($transaction, $order, $data, $fullPayload) {
                // BR-365: Update order status to "Paid"
                $order->update([
                    'status' => Order::STATUS_PAID,
                    'paid_at' => now(),
                ]);

                // BR-372: Update transaction record
                $transaction->update([
                    'status' => 'successful',
                    'flutterwave_reference' => $data['flw_ref'] ?? $transaction->flutterwave_reference,
                    'flutterwave_fee' => $data['app_fee'] ?? null,
                    'settlement_amount' => $data['amount_settled'] ?? null,
                    'payment_channel' => $data['payment_type'] ?? null,
                    'response_code' => $data['processor_response'] ?? '00',
                    'response_message' => 'Payment successful',
                    'webhook_payload' => $fullPayload,
                    'status_history' => array_merge($transaction->status_history ?? [], [
                        ['status' => 'successful', 'timestamp' => now()->toIso8601String()],
                    ]),
                ]);

                // BR-366, BR-367, BR-368, BR-369: Credit cook wallet and record commission
                $this->creditCookWallet($transaction, $order);
            });

            // BR-374: Log webhook processing
            activity('webhooks')
                ->withProperties([
                    'event' => 'charge.completed',
                    'status' => 'successful',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'amount' => $order->grand_total,
                    'tx_ref' => $transaction->flutterwave_tx_ref,
                ])
                ->log('Payment webhook processed: successful');

            Log::info('Flutterwave webhook: payment successful', [
                'order_id' => $order->id,
                'tx_ref' => $transaction->flutterwave_tx_ref,
                'amount' => $order->grand_total,
            ]);

            return [
                'success' => true,
                'message' => 'Payment processed successfully',
                'already_processed' => false,
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave webhook: processing error', [
                'error' => $e->getMessage(),
                'tx_ref' => $transaction->flutterwave_tx_ref,
                'order_id' => $order->id,
            ]);

            return [
                'success' => false,
                'message' => 'Processing error: '.$e->getMessage(),
                'already_processed' => false,
            ];
        }
    }

    /**
     * Handle a failed payment.
     *
     * BR-370: Order status changes to "Payment Failed"
     * F-168 BR-308: If order has wallet_amount > 0, reverse the wallet deduction.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $fullPayload
     * @return array{success: bool, message: string, already_processed: bool}
     */
    private function handleFailedPayment(
        PaymentTransaction $transaction,
        Order $order,
        array $data,
        array $fullPayload,
    ): array {
        try {
            DB::transaction(function () use ($transaction, $order, $data, $fullPayload) {
                // BR-370: Update order status to "Payment Failed"
                $order->update([
                    'status' => Order::STATUS_PAYMENT_FAILED,
                ]);

                $transaction->update([
                    'status' => 'failed',
                    'flutterwave_reference' => $data['flw_ref'] ?? $transaction->flutterwave_reference,
                    'response_code' => $data['processor_response'] ?? '99',
                    'response_message' => $data['status'] ?? 'Payment failed',
                    'webhook_payload' => $fullPayload,
                    'status_history' => array_merge($transaction->status_history ?? [], [
                        ['status' => 'failed', 'timestamp' => now()->toIso8601String()],
                    ]),
                ]);
            });

            // F-168 BR-308: Reverse wallet deduction if order had partial wallet payment
            $walletAmount = (float) ($order->wallet_amount ?? 0);
            if ($walletAmount > 0) {
                $client = $order->client;
                if ($client) {
                    $walletPaymentService = app(WalletPaymentService::class);
                    $reversed = $walletPaymentService->reverseWalletDeduction($order, $client);

                    Log::info('F-168: Wallet reversal on payment failure', [
                        'order_id' => $order->id,
                        'wallet_amount' => $walletAmount,
                        'reversed' => $reversed,
                    ]);
                }
            }

            // BR-374: Log webhook processing
            activity('webhooks')
                ->withProperties([
                    'event' => 'charge.completed',
                    'status' => 'failed',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'tx_ref' => $transaction->flutterwave_tx_ref,
                    'wallet_reversed' => $walletAmount > 0,
                ])
                ->log('Payment webhook processed: failed');

            Log::info('Flutterwave webhook: payment failed', [
                'order_id' => $order->id,
                'tx_ref' => $transaction->flutterwave_tx_ref,
            ]);

            return [
                'success' => true,
                'message' => 'Payment failure recorded',
                'already_processed' => false,
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave webhook: failed payment processing error', [
                'error' => $e->getMessage(),
                'tx_ref' => $transaction->flutterwave_tx_ref,
            ]);

            return [
                'success' => false,
                'message' => 'Processing error: '.$e->getMessage(),
                'already_processed' => false,
            ];
        }
    }

    /**
     * Credit the cook's wallet with the cook's share and record commission.
     *
     * BR-366: Cook wallet credited with (order amount - platform commission)
     * BR-367: Credit is initially "unwithdrawable" (3-hour delay)
     * BR-368: Commission = order amount * cook's commission rate
     * BR-369: Commission recorded as a separate transaction record
     */
    private function creditCookWallet(PaymentTransaction $transaction, Order $order): void
    {
        $cook = $order->cook;
        $tenant = $order->tenant;

        if (! $cook || ! $tenant) {
            Log::warning('Flutterwave webhook: cook or tenant missing for wallet credit', [
                'order_id' => $order->id,
                'cook_id' => $order->cook_id,
                'tenant_id' => $order->tenant_id,
            ]);

            return;
        }

        // BR-368: Calculate commission
        $commissionRate = $tenant->getCommissionRate();
        $orderAmount = (float) $order->grand_total;
        $commissionAmount = round($orderAmount * ($commissionRate / 100), 2);
        $cookShare = round($orderAmount - $commissionAmount, 2);

        // F-174 BR-367/BR-368: Apply pending deductions BEFORE crediting cook's wallet
        $autoDeductionService = app(AutoDeductionService::class);
        $deductionResult = $autoDeductionService->applyDeductions(
            $cook->id,
            $tenant->id,
            $cookShare,
            $order->id
        );

        $effectiveCookShare = $deductionResult['remaining_payment'];

        // Get cook's current wallet balance
        $currentBalance = $this->getCookWalletBalance($cook->id);

        // BR-366: Credit cook wallet with cook's share (after deductions)
        WalletTransaction::create([
            'user_id' => $cook->id,
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'payment_transaction_id' => $transaction->id,
            'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
            'amount' => $effectiveCookShare,
            'currency' => 'XAF',
            'balance_before' => $currentBalance,
            'balance_after' => $currentBalance + $effectiveCookShare,
            'is_withdrawable' => false,
            'withdrawable_at' => now()->addHours(WalletTransaction::DEFAULT_WITHDRAWABLE_DELAY_HOURS),
            'status' => 'completed',
            'description' => __('Payment credit for order :number', ['number' => $order->order_number]),
            'metadata' => [
                'order_number' => $order->order_number,
                'order_total' => $orderAmount,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'cook_share' => $cookShare,
                'deductions_applied' => $deductionResult['deducted'],
                'effective_cook_share' => $effectiveCookShare,
            ],
        ]);

        // BR-369: Record commission as a separate transaction (if commission > 0)
        if ($commissionAmount > 0) {
            WalletTransaction::create([
                'user_id' => $cook->id,
                'tenant_id' => $tenant->id,
                'order_id' => $order->id,
                'payment_transaction_id' => $transaction->id,
                'type' => WalletTransaction::TYPE_COMMISSION,
                'amount' => $commissionAmount,
                'currency' => 'XAF',
                'balance_before' => $currentBalance + $effectiveCookShare,
                'balance_after' => $currentBalance + $effectiveCookShare,
                'is_withdrawable' => false,
                'withdrawable_at' => null,
                'status' => 'completed',
                'description' => __('Platform commission (:rate%) for order :number', [
                    'rate' => $commissionRate,
                    'number' => $order->order_number,
                ]),
                'metadata' => [
                    'order_number' => $order->order_number,
                    'order_total' => $orderAmount,
                    'commission_rate' => $commissionRate,
                ],
            ]);
        }
    }

    /**
     * Check if a transaction has already been processed.
     *
     * BR-371: Idempotency check.
     */
    private function isAlreadyProcessed(PaymentTransaction $transaction): bool
    {
        return in_array($transaction->status, ['successful', 'failed'], true);
    }

    /**
     * Get the current wallet balance for a cook.
     */
    public function getCookWalletBalance(int $cookId): float
    {
        $credits = (float) WalletTransaction::query()
            ->where('user_id', $cookId)
            ->whereIn('type', [WalletTransaction::TYPE_PAYMENT_CREDIT, WalletTransaction::TYPE_REFUND])
            ->where('status', 'completed')
            ->sum('amount');

        $debits = (float) WalletTransaction::query()
            ->where('user_id', $cookId)
            ->whereIn('type', [WalletTransaction::TYPE_WITHDRAWAL, WalletTransaction::TYPE_REFUND_DEDUCTION])
            ->where('status', 'completed')
            ->sum('amount');

        return round($credits - $debits, 2);
    }
}
