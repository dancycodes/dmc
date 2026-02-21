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
 * F-168: Client Wallet Payment for Orders (partial wallet support)
 *
 * Handles the wallet payment flow: validates balance, deducts from client wallet,
 * creates the order, credits cook wallet with commission split, and records all
 * transaction records.
 *
 * BR-299: Wallet payment only when admin has enabled it globally.
 * BR-301: Client can pay fully from wallet if balance is sufficient.
 * BR-302: Client can pay partially from wallet + remainder via mobile money.
 * BR-303: Wallet deduction happens atomically as part of the payment process.
 * BR-304: For full wallet payments, no Flutterwave redirect needed.
 * BR-305: For partial wallet payments, the mobile money portion goes through Flutterwave.
 * BR-306: Wallet balance cannot go negative.
 * BR-307: A wallet transaction record is created (type: wallet_payment, debit).
 * BR-308: If the mobile money portion of a partial payment fails, the wallet deduction is reversed.
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
     * Process a full wallet payment for an order.
     *
     * BR-301: Full wallet payment when balance >= order total.
     * BR-304: No Flutterwave redirect needed; order moves to Paid immediately.
     *
     * @return array{success: bool, order: Order|null, error: string|null}
     */
    public function processWalletPayment(Tenant $tenant, User $client): array
    {
        // BR-299: Check if wallet payments are enabled globally
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

                // BR-301: Verify sufficient balance (with lock held)
                if ($currentBalance < $orderTotal) {
                    throw new \RuntimeException(__('Insufficient wallet balance.'));
                }

                // Edge case: Re-check admin setting within transaction
                if (! $this->platformSettingService->isWalletEnabled()) {
                    throw new \RuntimeException(__('Wallet payments have been disabled.'));
                }

                $newBalance = round($currentBalance - $orderTotal, 2);

                // BR-303: Deduct from client wallet atomically
                $wallet->update(['balance' => $newBalance]);

                // BR-307: Create client wallet deduction transaction
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

                // BR-304: Order status immediately changes to "Paid"
                $order->update([
                    'status' => Order::STATUS_PAID,
                    'paid_at' => now(),
                    'payment_provider' => 'wallet',
                    'wallet_amount' => $orderTotal,
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

                // Credit cook wallet and record commission
                $this->creditCookWallet($order, $tenant);
            });

            // Log the wallet payment via Spatie Activitylog
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
     * F-168: Deduct the wallet portion for a partial wallet + mobile money payment.
     *
     * BR-302: Partial wallet deduction before Flutterwave initiation.
     * BR-303: Wallet deduction happens atomically.
     * BR-306: Wallet balance cannot go negative.
     * BR-307: Wallet transaction record created.
     *
     * Called before Flutterwave payment initiation. If Flutterwave fails,
     * reverseWalletDeduction() is called to restore the balance (BR-308).
     *
     * @return array{success: bool, wallet_amount: float, remainder: float, error: string|null}
     */
    public function deductWalletForPartialPayment(Order $order, User $client, Tenant $tenant): array
    {
        // BR-299: Check if wallet payments are enabled
        if (! $this->platformSettingService->isWalletEnabled()) {
            return [
                'success' => false,
                'wallet_amount' => 0,
                'remainder' => (float) $order->grand_total,
                'error' => __('Wallet payments are currently disabled.'),
            ];
        }

        try {
            $result = DB::transaction(function () use ($order, $client, $tenant) {
                // Lock the client wallet row
                $wallet = ClientWallet::query()
                    ->where('user_id', $client->id)
                    ->lockForUpdate()
                    ->first();

                if (! $wallet || (float) $wallet->balance <= 0) {
                    throw new \RuntimeException(__('No wallet balance available.'));
                }

                // Re-check admin setting within transaction
                if (! $this->platformSettingService->isWalletEnabled()) {
                    throw new \RuntimeException(__('Wallet payments have been disabled.'));
                }

                $orderTotal = (float) $order->grand_total;
                $currentBalance = (float) $wallet->balance;

                // BR-302: Deduct entire wallet balance or order total, whichever is smaller
                $walletAmount = min($currentBalance, $orderTotal);
                $remainder = round($orderTotal - $walletAmount, 2);
                $newBalance = round($currentBalance - $walletAmount, 2);

                // BR-306: Ensure balance does not go negative
                if ($newBalance < 0) {
                    $newBalance = 0;
                    $walletAmount = $currentBalance;
                    $remainder = round($orderTotal - $walletAmount, 2);
                }

                // BR-303: Deduct from client wallet atomically
                $wallet->update(['balance' => $newBalance]);

                // BR-307: Create wallet transaction record
                WalletTransaction::create([
                    'user_id' => $client->id,
                    'tenant_id' => $tenant->id,
                    'order_id' => $order->id,
                    'payment_transaction_id' => null,
                    'type' => WalletTransaction::TYPE_WALLET_PAYMENT,
                    'amount' => $walletAmount,
                    'currency' => 'XAF',
                    'balance_before' => $currentBalance,
                    'balance_after' => $newBalance,
                    'is_withdrawable' => false,
                    'withdrawable_at' => null,
                    'status' => 'completed',
                    'description' => __('Partial wallet payment for order :number', ['number' => $order->order_number]),
                    'metadata' => [
                        'order_number' => $order->order_number,
                        'order_total' => $orderTotal,
                        'wallet_amount' => $walletAmount,
                        'remainder' => $remainder,
                        'payment_method' => 'wallet_partial',
                    ],
                ]);

                // Update order with wallet amount
                $order->update(['wallet_amount' => $walletAmount]);

                return [
                    'wallet_amount' => $walletAmount,
                    'remainder' => $remainder,
                ];
            });

            // Log the partial wallet deduction
            activity('payments')
                ->performedOn($order)
                ->causedBy($client)
                ->withProperties([
                    'payment_method' => 'wallet_partial',
                    'order_number' => $order->order_number,
                    'wallet_amount' => $result['wallet_amount'],
                    'remainder' => $result['remainder'],
                    'tenant_id' => $tenant->id,
                ])
                ->log('Partial wallet deduction for order');

            Log::info('F-168: Partial wallet deduction successful', [
                'order_id' => $order->id,
                'wallet_amount' => $result['wallet_amount'],
                'remainder' => $result['remainder'],
            ]);

            return [
                'success' => true,
                'wallet_amount' => $result['wallet_amount'],
                'remainder' => $result['remainder'],
                'error' => null,
            ];
        } catch (\RuntimeException $e) {
            Log::warning('F-168: Partial wallet deduction business error', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'wallet_amount' => 0,
                'remainder' => (float) $order->grand_total,
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('F-168: Partial wallet deduction unexpected error', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'wallet_amount' => 0,
                'remainder' => (float) $order->grand_total,
                'error' => __('Failed to process wallet payment. Please try again.'),
            ];
        }
    }

    /**
     * F-168: Reverse a wallet deduction when mobile money payment fails.
     *
     * BR-308: If the mobile money portion of a partial payment fails,
     * the wallet deduction is reversed.
     */
    public function reverseWalletDeduction(Order $order, User $client): bool
    {
        $walletAmount = (float) $order->wallet_amount;

        if ($walletAmount <= 0) {
            return true;
        }

        try {
            DB::transaction(function () use ($order, $client, $walletAmount) {
                // Lock the client wallet row
                $wallet = ClientWallet::query()
                    ->where('user_id', $client->id)
                    ->lockForUpdate()
                    ->first();

                if (! $wallet) {
                    throw new \RuntimeException('Wallet not found for reversal');
                }

                $currentBalance = (float) $wallet->balance;
                $newBalance = round($currentBalance + $walletAmount, 2);

                // Restore wallet balance
                $wallet->update(['balance' => $newBalance]);

                // Create reversal transaction record
                WalletTransaction::create([
                    'user_id' => $client->id,
                    'tenant_id' => $order->tenant_id,
                    'order_id' => $order->id,
                    'payment_transaction_id' => null,
                    'type' => WalletTransaction::TYPE_REFUND,
                    'amount' => $walletAmount,
                    'currency' => 'XAF',
                    'balance_before' => $currentBalance,
                    'balance_after' => $newBalance,
                    'is_withdrawable' => true,
                    'withdrawable_at' => null,
                    'status' => 'completed',
                    'description' => __('Wallet payment reversed for order :number', ['number' => $order->order_number]),
                    'metadata' => [
                        'order_number' => $order->order_number,
                        'reversal_reason' => 'mobile_money_failure',
                        'reversed_amount' => $walletAmount,
                    ],
                ]);

                // Clear wallet amount on order
                $order->update(['wallet_amount' => 0]);
            });

            // Log the reversal
            activity('payments')
                ->performedOn($order)
                ->causedBy($client)
                ->withProperties([
                    'order_number' => $order->order_number,
                    'reversed_amount' => $walletAmount,
                    'reason' => 'mobile_money_failure',
                ])
                ->log('Wallet deduction reversed due to mobile money payment failure');

            Log::info('F-168: Wallet deduction reversed', [
                'order_id' => $order->id,
                'reversed_amount' => $walletAmount,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('F-168: Wallet reversal failed', [
                'order_id' => $order->id,
                'wallet_amount' => $walletAmount,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Credit the cook's wallet with order amount minus commission.
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

        $commissionRate = $tenant->getCommissionRate();
        $orderAmount = (float) $order->grand_total;
        $commissionAmount = round($orderAmount * ($commissionRate / 100), 2);
        $cookShare = round($orderAmount - $commissionAmount, 2);

        $currentBalance = $this->webhookService->getCookWalletBalance($cook->id);

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
