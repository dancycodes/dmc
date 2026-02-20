<?php

namespace App\Services;

use App\Models\ClientWallet;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * F-153: Wallet Balance Payment
 *
 * Handles the wallet payment flow: validates balance, deducts from client wallet,
 * creates the order, credits cook wallet with commission split, and records all
 * transaction records.
 *
 * BR-387: Wallet payment only when admin has enabled it globally.
 * BR-388: Wallet balance must be >= order total.
 * BR-389: Deduct order total from wallet balance.
 * BR-390: Instant deduction; no external payment gateway.
 * BR-391: Order status immediately changes to "Paid".
 * BR-392: Wallet transaction record created (type: payment, negative amount).
 * BR-393: Cook wallet credited with (order amount - commission).
 * BR-394: Commission calculated identically to Flutterwave payments.
 * BR-395: Logged via Spatie Activitylog.
 * BR-396: No partial wallet payment supported.
 */
class WalletPaymentService
{
    public function __construct(
        private PlatformSettingService $platformSettingService,
        private PaymentService $paymentService,
        private CartService $cartService,
        private CheckoutService $checkoutService,
        private WebhookService $webhookService,
    ) {}

    /**
     * Process a wallet payment for an order.
     *
     * Creates the order, deducts from client wallet, credits cook wallet,
     * records commission — all within a single DB transaction using
     * pessimistic locking on the client wallet.
     *
     * @return array{success: bool, order: Order|null, error: string|null}
     */
    public function processWalletPayment(Tenant $tenant, User $client): array
    {
        // BR-387: Check if wallet payments are enabled globally
        if (! $this->platformSettingService->isWalletEnabled()) {
            return [
                'success' => false,
                'order' => null,
                'error' => __('Wallet payments are currently disabled.'),
            ];
        }

        // Create the order first via PaymentService
        $orderResult = $this->paymentService->createOrder($tenant, $client);

        if (! $orderResult['success']) {
            return [
                'success' => false,
                'order' => null,
                'error' => $orderResult['error'],
            ];
        }

        $order = $orderResult['order'];

        try {
            DB::transaction(function () use ($order, $tenant, $client) {
                // Lock the client wallet row for update (prevents race conditions)
                $wallet = ClientWallet::query()
                    ->where('user_id', $client->id)
                    ->lockForUpdate()
                    ->first();

                if (! $wallet) {
                    throw new \RuntimeException(__('Wallet not found.'));
                }

                $orderTotal = (float) $order->grand_total;
                $currentBalance = (float) $wallet->balance;

                // BR-388: Verify sufficient balance (with lock held)
                if ($currentBalance < $orderTotal) {
                    throw new \RuntimeException(__('Insufficient wallet balance.'));
                }

                // Edge case: Re-check admin setting within transaction
                if (! $this->platformSettingService->isWalletEnabled()) {
                    throw new \RuntimeException(__('Wallet payments have been disabled.'));
                }

                $newBalance = round($currentBalance - $orderTotal, 2);

                // BR-389: Deduct from client wallet
                $wallet->update(['balance' => $newBalance]);

                // BR-392: Create client wallet deduction transaction
                WalletTransaction::create([
                    'user_id' => $client->id,
                    'tenant_id' => $tenant->id,
                    'order_id' => $order->id,
                    'payment_transaction_id' => null,
                    'type' => WalletTransaction::TYPE_WALLET_PAYMENT,
                    'amount' => $orderTotal,
                    'currency' => 'XAF',
                    'balance_before' => $currentBalance,
                    'balance_after' => $newBalance,
                    'is_withdrawable' => false,
                    'withdrawable_at' => null,
                    'status' => 'completed',
                    'description' => __('Wallet payment for order :number', ['number' => $order->order_number]),
                    'metadata' => [
                        'order_number' => $order->order_number,
                        'order_total' => $orderTotal,
                        'payment_method' => 'wallet',
                    ],
                ]);

                // BR-391: Order status immediately changes to "Paid"
                $order->update([
                    'status' => Order::STATUS_PAID,
                    'paid_at' => now(),
                    'payment_provider' => 'wallet',
                ]);

                // Create a payment transaction record for consistency with Flutterwave flow
                PaymentTransaction::create([
                    'order_id' => $order->id,
                    'client_id' => $client->id,
                    'cook_id' => $order->cook_id,
                    'tenant_id' => $tenant->id,
                    'amount' => $order->grand_total,
                    'currency' => 'XAF',
                    'payment_method' => 'wallet',
                    'status' => 'successful',
                    'flutterwave_tx_ref' => 'WALLET-'.$order->order_number,
                    'customer_name' => $client->name,
                    'customer_email' => $client->email,
                    'customer_phone' => $order->phone,
                    'response_message' => 'Wallet payment successful',
                    'status_history' => [
                        ['status' => 'successful', 'timestamp' => now()->toIso8601String(), 'method' => 'wallet'],
                    ],
                ]);

                // BR-393/BR-394: Credit cook wallet and record commission
                // Reuses the same commission logic as the Flutterwave webhook flow
                $this->creditCookWallet($order, $tenant);
            });

            // BR-395: Log the wallet payment via Spatie Activitylog
            activity('payments')
                ->performedOn($order)
                ->causedBy($client)
                ->withProperties([
                    'payment_method' => 'wallet',
                    'order_number' => $order->order_number,
                    'amount' => $order->grand_total,
                    'tenant_id' => $tenant->id,
                ])
                ->log('Wallet payment processed successfully');

            Log::info('F-153: Wallet payment successful', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => $order->grand_total,
                'client_id' => $client->id,
            ]);

            return [
                'success' => true,
                'order' => $order->fresh(),
                'error' => null,
            ];
        } catch (\RuntimeException $e) {
            // Known business logic errors (insufficient balance, wallet disabled)
            // Cancel the order since payment failed
            $order->update(['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now()]);

            Log::warning('F-153: Wallet payment business error', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'client_id' => $client->id,
            ]);

            return [
                'success' => false,
                'order' => null,
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            // Unexpected errors
            $order->update(['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now()]);

            Log::error('F-153: Wallet payment unexpected error', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'client_id' => $client->id,
            ]);

            return [
                'success' => false,
                'order' => null,
                'error' => __('Payment failed. Please try again.'),
            ];
        }
    }

    /**
     * Credit the cook's wallet with order amount minus commission.
     *
     * BR-393: Cook wallet credited with (order total - commission).
     * BR-394: Commission calculated identically to Flutterwave payments.
     *
     * Mirrors WebhookService::creditCookWallet() logic.
     */
    private function creditCookWallet(Order $order, Tenant $tenant): void
    {
        $cook = $order->cook;

        if (! $cook) {
            Log::warning('F-153: Cook not found for wallet credit', [
                'order_id' => $order->id,
                'cook_id' => $order->cook_id,
            ]);

            return;
        }

        // BR-394: Calculate commission (same as Flutterwave flow)
        $commissionRate = $tenant->getCommissionRate();
        $orderAmount = (float) $order->grand_total;
        $commissionAmount = round($orderAmount * ($commissionRate / 100), 2);
        $cookShare = round($orderAmount - $commissionAmount, 2);

        // Get cook's current wallet balance
        $currentBalance = $this->webhookService->getCookWalletBalance($cook->id);

        // BR-393: Credit cook wallet with cook's share
        WalletTransaction::create([
            'user_id' => $cook->id,
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'payment_transaction_id' => null,
            'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
            'amount' => $cookShare,
            'currency' => 'XAF',
            'balance_before' => $currentBalance,
            'balance_after' => $currentBalance + $cookShare,
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
                'payment_method' => 'wallet',
            ],
        ]);

        // BR-394: Record commission as a separate transaction (if commission > 0)
        if ($commissionAmount > 0) {
            WalletTransaction::create([
                'user_id' => $cook->id,
                'tenant_id' => $tenant->id,
                'order_id' => $order->id,
                'payment_transaction_id' => null,
                'type' => WalletTransaction::TYPE_COMMISSION,
                'amount' => $commissionAmount,
                'currency' => 'XAF',
                'balance_before' => $currentBalance + $cookShare,
                'balance_after' => $currentBalance + $cookShare,
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
                    'payment_method' => 'wallet',
                ],
            ]);
        }
    }
}
