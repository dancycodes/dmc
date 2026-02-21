<?php

namespace App\Services;

use App\Models\CookWallet;
use App\Models\Order;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * F-175: Commission Deduction on Completion
 *
 * Handles commission calculation and deduction when an order is completed.
 * Credits the cook's wallet with the net amount (subtotal - commission + delivery_fee).
 * Creates commission and payment_credit wallet transaction records.
 * Stores commission_amount and commission_rate on the order.
 *
 * BR-377: Commission calculated when order status changes to Completed.
 * BR-378: Commission rate is per-cook, configured by admin (F-062); default 10%.
 * BR-379: Commission = order_subtotal * commission_rate.
 * BR-380: Delivery fee excluded from commission calculation.
 * BR-381: Commission never charged on non-completed orders.
 * BR-382: Cook wallet receives: (order_subtotal - commission) + delivery_fee.
 * BR-383: Commission transaction record created (type: commission_deducted -> TYPE_COMMISSION).
 * BR-384: Commission transaction references order and shows the rate used.
 * BR-385: Platform revenue tracked separately for admin reporting.
 * BR-386: Commission deduction logged via Spatie Activitylog.
 * BR-387: If commission rate is 0%, a 0 XAF record is created for transparency.
 */
class CommissionDeductionService
{
    public function __construct(
        private CookWalletService $cookWalletService,
        private OrderClearanceService $orderClearanceService,
        private AutoDeductionService $autoDeductionService,
    ) {}

    /**
     * Process commission deduction for a completed order.
     *
     * BR-377: Called when order status changes to Completed.
     * BR-382: Cook wallet receives: (subtotal - commission) + delivery_fee.
     *
     * @return array{success: bool, commission_amount: float, commission_rate: float, cook_credit: float, message: string}
     */
    public function processOrderCompletion(Order $order): array
    {
        // BR-381: Only process completed orders
        if ($order->status !== Order::STATUS_COMPLETED) {
            return [
                'success' => false,
                'commission_amount' => 0.0,
                'commission_rate' => 0.0,
                'cook_credit' => 0.0,
                'message' => 'Order is not in completed status.',
            ];
        }

        $tenant = $order->tenant;
        $cook = $order->cook;

        if (! $tenant || ! $cook) {
            Log::warning('F-175: Cannot process commission — missing tenant or cook', [
                'order_id' => $order->id,
                'tenant_id' => $order->tenant_id,
                'cook_id' => $order->cook_id,
            ]);

            return [
                'success' => false,
                'commission_amount' => 0.0,
                'commission_rate' => 0.0,
                'cook_credit' => 0.0,
                'message' => 'Missing tenant or cook.',
            ];
        }

        // BR-378: Get cook's current commission rate (rate at time of completion)
        $commissionRate = $tenant->getCommissionRate();

        // BR-379: Commission = subtotal * rate
        // BR-380: Delivery fee excluded
        $subtotal = (float) $order->subtotal;
        $deliveryFee = (float) $order->delivery_fee;

        // Edge case: Round down to nearest whole XAF in cook's favor
        $commissionAmount = (float) floor($subtotal * ($commissionRate / 100));

        // BR-382: Cook receives (subtotal - commission) + delivery_fee
        $cookNetFromSubtotal = $subtotal - $commissionAmount;
        $totalCookCredit = $cookNetFromSubtotal + $deliveryFee;

        return DB::transaction(function () use ($order, $tenant, $cook, $commissionRate, $commissionAmount, $totalCookCredit, $subtotal, $deliveryFee) {
            // Update order with commission data (BR-384)
            $order->update([
                'commission_amount' => $commissionAmount,
                'commission_rate' => $commissionRate,
            ]);

            // Get or create cook wallet
            $wallet = CookWallet::getOrCreateForTenant($tenant, $cook);

            // F-174: Apply pending deductions BEFORE crediting cook's wallet
            $deductionResult = $this->autoDeductionService->applyDeductions(
                $cook->id,
                $tenant->id,
                $totalCookCredit,
                $order->id
            );

            $effectiveCookCredit = $deductionResult['remaining_payment'];
            $currentBalance = $this->getCookWalletBalance($cook->id);

            // BR-382: Create payment credit for cook (net amount after deductions)
            WalletTransaction::create([
                'user_id' => $cook->id,
                'tenant_id' => $tenant->id,
                'order_id' => $order->id,
                'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
                'amount' => $effectiveCookCredit,
                'currency' => 'XAF',
                'balance_before' => $currentBalance,
                'balance_after' => $currentBalance + $effectiveCookCredit,
                'is_withdrawable' => false,
                'withdrawable_at' => now()->addHours(WalletTransaction::DEFAULT_WITHDRAWABLE_DELAY_HOURS),
                'status' => 'completed',
                'description' => __('Earnings from order :number', ['number' => $order->order_number]),
                'metadata' => [
                    'order_number' => $order->order_number,
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'commission_rate' => $commissionRate,
                    'commission_amount' => $commissionAmount,
                    'gross_cook_credit' => $totalCookCredit,
                    'deductions_applied' => $deductionResult['deducted'],
                    'effective_cook_credit' => $effectiveCookCredit,
                ],
            ]);

            // BR-383/BR-384: Create commission transaction record
            // BR-387: Even if commission is 0%, create a record for transparency
            WalletTransaction::create([
                'user_id' => $cook->id,
                'tenant_id' => $tenant->id,
                'order_id' => $order->id,
                'type' => WalletTransaction::TYPE_COMMISSION,
                'amount' => $commissionAmount,
                'currency' => 'XAF',
                'balance_before' => $currentBalance + $effectiveCookCredit,
                'balance_after' => $currentBalance + $effectiveCookCredit,
                'is_withdrawable' => false,
                'withdrawable_at' => null,
                'status' => 'completed',
                'description' => __('Platform commission (:rate%) on order :number', [
                    'rate' => $commissionRate,
                    'number' => $order->order_number,
                ]),
                'metadata' => [
                    'order_number' => $order->order_number,
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'commission_rate' => $commissionRate,
                    'gross_cook_credit' => $totalCookCredit,
                ],
            ]);

            // Recalculate wallet balances
            $this->cookWalletService->recalculateBalances($wallet);

            // BR-386: Log commission deduction via Spatie Activitylog
            activity('commissions')
                ->performedOn($order)
                ->causedByAnonymous()
                ->withProperties([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'commission_rate' => $commissionRate,
                    'commission_amount' => $commissionAmount,
                    'cook_credit' => $totalCookCredit,
                    'deductions_applied' => $deductionResult['deducted'],
                    'effective_cook_credit' => $effectiveCookCredit,
                    'cook_id' => $cook->id,
                    'tenant_id' => $tenant->id,
                ])
                ->log('commission_deducted');

            // F-171: Start withdrawable timer on the net amount
            $this->orderClearanceService->createClearance($order, $effectiveCookCredit);

            Log::info('F-175: Commission deducted on order completion', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'cook_credit' => $totalCookCredit,
                'effective_cook_credit' => $effectiveCookCredit,
            ]);

            return [
                'success' => true,
                'commission_amount' => $commissionAmount,
                'commission_rate' => $commissionRate,
                'cook_credit' => $totalCookCredit,
                'message' => 'Commission deducted successfully.',
            ];
        });
    }

    /**
     * Calculate the commission amount for a given subtotal and rate.
     *
     * BR-379: Commission = subtotal * rate.
     * Edge case: Round down to nearest whole XAF in cook's favor.
     */
    public static function calculateCommission(float $subtotal, float $rate): float
    {
        return (float) floor($subtotal * ($rate / 100));
    }

    /**
     * Calculate the cook's net credit for an order.
     *
     * BR-382: Cook receives (subtotal - commission) + delivery_fee.
     */
    public static function calculateCookCredit(float $subtotal, float $deliveryFee, float $commissionRate): float
    {
        $commission = self::calculateCommission($subtotal, $commissionRate);

        return ($subtotal - $commission) + $deliveryFee;
    }

    /**
     * Get the current wallet balance for a cook.
     */
    private function getCookWalletBalance(int $cookId): float
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
