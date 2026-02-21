<?php

namespace App\Services;

use App\Models\CookWallet;
use App\Models\Order;
use App\Models\PendingDeduction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * F-174: Cook Auto-Deduction for Refunds
 *
 * Handles creation of pending deductions when refunds are issued
 * after the cook has withdrawn funds, and automatic settlement
 * from future order payments.
 *
 * BR-366: Pending deduction created on post-withdrawal refund.
 * BR-367: Auto-applied against future order earnings.
 * BR-368: Applied BEFORE payment enters cook's wallet.
 * BR-369: Partial settlement when payment < deduction.
 * BR-370: Only owed amount deducted when payment > deduction.
 * BR-371: FIFO order for multiple deductions.
 * BR-372: Wallet shows total pending deduction amount.
 * BR-373: Each deduction creates a wallet transaction (type: refund_deduction).
 * BR-374: References original order and refund reason.
 * BR-375: Transparent settlement history.
 * BR-376: All deductions logged via Spatie Activitylog.
 */
class AutoDeductionService
{
    /**
     * Create a pending deduction against the cook's wallet.
     *
     * BR-366: Called when a refund is issued after the cook has withdrawn.
     * Edge case: 0 XAF deduction is silently skipped.
     *
     * @param  array{complaint_id?: int, order_number?: string}  $metadata
     */
    public function createDeduction(
        CookWallet $wallet,
        Order $order,
        float $amount,
        string $reason,
        string $source = PendingDeduction::SOURCE_COMPLAINT_REFUND,
        array $metadata = []
    ): ?PendingDeduction {
        // Edge case: No deduction for 0 XAF
        if ($amount <= 0) {
            return null;
        }

        $deduction = PendingDeduction::create([
            'cook_wallet_id' => $wallet->id,
            'tenant_id' => $wallet->tenant_id,
            'user_id' => $wallet->user_id,
            'order_id' => $order->id,
            'original_amount' => $amount,
            'remaining_amount' => $amount,
            'reason' => $reason,
            'source' => $source,
            'metadata' => array_merge([
                'order_number' => $order->order_number,
            ], $metadata),
        ]);

        // BR-376: Log deduction creation
        activity('pending_deductions')
            ->performedOn($deduction)
            ->causedByAnonymous()
            ->withProperties([
                'amount' => $amount,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'source' => $source,
                'reason' => $reason,
            ])
            ->log('pending_deduction_created');

        Log::info('F-174: Pending deduction created', [
            'deduction_id' => $deduction->id,
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'order_id' => $order->id,
        ]);

        return $deduction;
    }

    /**
     * Apply pending deductions against a new order payment.
     *
     * BR-367: Automatically applied against future order earnings.
     * BR-368: Applied BEFORE payment enters cook's wallet.
     * BR-371: FIFO order (oldest deduction settled first).
     *
     * @return array{deducted: float, remaining_payment: float, deductions_applied: array<int, array{deduction_id: int, amount_deducted: float, fully_settled: bool}>}
     */
    public function applyDeductions(
        int $cookUserId,
        int $tenantId,
        float $paymentAmount,
        ?int $orderId = null
    ): array {
        $result = [
            'deducted' => 0.0,
            'remaining_payment' => $paymentAmount,
            'deductions_applied' => [],
        ];

        if ($paymentAmount <= 0) {
            return $result;
        }

        // BR-371: Get unsettled deductions in FIFO order for this tenant
        $deductions = PendingDeduction::query()
            ->unsettled()
            ->forTenant($tenantId)
            ->forUser($cookUserId)
            ->oldestFirst()
            ->lockForUpdate()
            ->get();

        if ($deductions->isEmpty()) {
            return $result;
        }

        $remainingPayment = $paymentAmount;

        foreach ($deductions as $deduction) {
            if ($remainingPayment <= 0) {
                break;
            }

            $remaining = (float) $deduction->remaining_amount;
            // BR-369/BR-370: Deduct the lesser of remaining payment and deduction remaining
            $deductAmount = min($remainingPayment, $remaining);
            $newRemaining = round($remaining - $deductAmount, 2);
            $fullySettled = $newRemaining <= 0;

            // Update the deduction
            $deduction->update([
                'remaining_amount' => $newRemaining,
                'settled_at' => $fullySettled ? now() : null,
            ]);

            // BR-373: Create wallet transaction for auto-deduction
            $this->createDeductionTransaction(
                $cookUserId,
                $tenantId,
                $deductAmount,
                $deduction,
                $orderId
            );

            // BR-376: Log settlement
            activity('pending_deductions')
                ->performedOn($deduction)
                ->causedByAnonymous()
                ->withProperties([
                    'amount_deducted' => $deductAmount,
                    'remaining_after' => $newRemaining,
                    'fully_settled' => $fullySettled,
                    'source_order_id' => $orderId,
                ])
                ->log($fullySettled ? 'deduction_fully_settled' : 'deduction_partially_settled');

            $result['deductions_applied'][] = [
                'deduction_id' => $deduction->id,
                'amount_deducted' => $deductAmount,
                'fully_settled' => $fullySettled,
            ];

            $result['deducted'] = round($result['deducted'] + $deductAmount, 2);
            $remainingPayment = round($remainingPayment - $deductAmount, 2);
        }

        $result['remaining_payment'] = (float) max(0, $remainingPayment);

        Log::info('F-174: Deductions applied to payment', [
            'cook_user_id' => $cookUserId,
            'tenant_id' => $tenantId,
            'payment_amount' => $paymentAmount,
            'total_deducted' => $result['deducted'],
            'remaining_payment' => $result['remaining_payment'],
            'deductions_count' => count($result['deductions_applied']),
        ]);

        return $result;
    }

    /**
     * Check if the cook has already withdrawn funds for a specific order.
     *
     * Looks for withdrawal transactions that occurred after the payment credit
     * for this order was created.
     */
    public function hasCookWithdrawnOrderFunds(Order $order): bool
    {
        // Check if there's a payment credit for this order
        $paymentCredit = WalletTransaction::query()
            ->where('order_id', $order->id)
            ->where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)
            ->where('status', 'completed')
            ->first();

        if (! $paymentCredit) {
            return false;
        }

        // Check if ANY withdrawal has occurred after this payment credit
        return WalletTransaction::query()
            ->where('user_id', $paymentCredit->user_id)
            ->where('tenant_id', $paymentCredit->tenant_id)
            ->where('type', WalletTransaction::TYPE_WITHDRAWAL)
            ->where('status', 'completed')
            ->where('created_at', '>=', $paymentCredit->created_at)
            ->exists();
    }

    /**
     * Get the total pending deduction amount for a cook in a tenant.
     *
     * BR-372: Wallet shows total pending deduction amount.
     */
    public function getTotalPendingAmount(int $tenantId, int $cookUserId): float
    {
        return (float) PendingDeduction::query()
            ->unsettled()
            ->forTenant($tenantId)
            ->forUser($cookUserId)
            ->sum('remaining_amount');
    }

    /**
     * Get all pending deductions for a cook in a tenant (for dashboard display).
     *
     * BR-375: Transparent — cook can see every deduction and its settlement.
     *
     * @return Collection<int, PendingDeduction>
     */
    public function getPendingDeductions(int $tenantId, int $cookUserId): Collection
    {
        return PendingDeduction::query()
            ->unsettled()
            ->forTenant($tenantId)
            ->forUser($cookUserId)
            ->oldestFirst()
            ->with(['order:id,order_number'])
            ->get();
    }

    /**
     * Get all deductions (including settled) for a cook in a tenant.
     *
     * @return Collection<int, PendingDeduction>
     */
    public function getAllDeductions(int $tenantId, int $cookUserId): Collection
    {
        return PendingDeduction::query()
            ->forTenant($tenantId)
            ->forUser($cookUserId)
            ->orderByDesc('created_at')
            ->with(['order:id,order_number'])
            ->get();
    }

    /**
     * Cancel a pending deduction (admin action for erroneous refunds).
     *
     * Edge case: Refund reversal by admin.
     */
    public function cancelDeduction(PendingDeduction $deduction, ?User $admin = null): bool
    {
        if ($deduction->isSettled()) {
            return false;
        }

        $deduction->update([
            'remaining_amount' => 0,
            'settled_at' => now(),
        ]);

        activity('pending_deductions')
            ->performedOn($deduction)
            ->when($admin, fn ($log) => $log->causedBy($admin), fn ($log) => $log->causedByAnonymous())
            ->withProperties([
                'action' => 'cancelled',
                'original_amount' => $deduction->original_amount,
                'reason' => 'Admin cancelled erroneous deduction',
            ])
            ->log('deduction_cancelled');

        return true;
    }

    /**
     * Create a wallet transaction record for an auto-deduction.
     *
     * BR-373: Each deduction creates a wallet transaction (type: refund_deduction).
     * BR-374: References original order and refund reason.
     */
    private function createDeductionTransaction(
        int $cookUserId,
        int $tenantId,
        float $amount,
        PendingDeduction $deduction,
        ?int $sourceOrderId = null
    ): WalletTransaction {
        $orderNumber = $deduction->order?->order_number ?? __('Unknown');
        $description = __('Auto-deduction for refund on Order :number', [
            'number' => $orderNumber,
        ]);

        return WalletTransaction::create([
            'user_id' => $cookUserId,
            'tenant_id' => $tenantId,
            'order_id' => $deduction->order_id,
            'type' => WalletTransaction::TYPE_REFUND_DEDUCTION,
            'amount' => $amount,
            'currency' => 'XAF',
            'balance_before' => 0,
            'balance_after' => 0,
            'is_withdrawable' => false,
            'status' => 'completed',
            'description' => $description,
            'metadata' => [
                'deduction_id' => $deduction->id,
                'original_order_id' => $deduction->order_id,
                'original_order_number' => $orderNumber,
                'refund_reason' => $deduction->reason,
                'source_order_id' => $sourceOrderId,
                'deduction_original_amount' => (float) $deduction->original_amount,
                'deduction_remaining_after' => (float) $deduction->remaining_amount,
            ],
        ]);
    }
}
