<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\CookWallet;
use App\Models\Order;
use App\Models\OrderClearance;
use App\Models\WalletTransaction;
use App\Notifications\FundsWithdrawableNotification;
use Illuminate\Support\Facades\DB;

/**
 * F-171: Withdrawable Timer Logic
 *
 * Service layer for managing order fund clearance timers.
 * Handles creation of clearance records, pause/resume on complaints,
 * and the periodic transition of funds from unwithdrawable to withdrawable.
 */
class OrderClearanceService
{
    public function __construct(
        private PlatformSettingService $platformSettingService,
        private CookWalletService $cookWalletService
    ) {}

    /**
     * Create a clearance record when an order is completed.
     *
     * BR-333: Hold period starts when order status changes to Completed.
     * BR-334: Default hold period is 3 hours, configurable by admin.
     * BR-341: Snapshot hold period at creation time.
     *
     * @param  float  $amount  Amount after commission deduction
     */
    public function createClearance(Order $order, float $amount): OrderClearance
    {
        $holdHours = $this->platformSettingService->getWithdrawableHoldHours();
        $completedAt = $order->completed_at ?? now();

        // Edge case: Hold period set to 0 hours — funds become immediately eligible
        $withdrawableAt = $holdHours > 0
            ? $completedAt->copy()->addHours($holdHours)
            : $completedAt->copy();

        return OrderClearance::create([
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
            'cook_id' => $order->cook_id,
            'amount' => $amount,
            'hold_hours' => $holdHours,
            'completed_at' => $completedAt,
            'withdrawable_at' => $withdrawableAt,
            'is_cleared' => false,
            'is_paused' => false,
            'is_cancelled' => false,
        ]);
    }

    /**
     * Pause the timer when a complaint is filed on the order.
     *
     * BR-338: Timer pauses if a complaint is filed during the hold period.
     * Records the remaining seconds so it can resume accurately.
     */
    public function pauseTimer(Order $order): ?OrderClearance
    {
        $clearance = OrderClearance::query()
            ->where('order_id', $order->id)
            ->where('is_cleared', false)
            ->where('is_cancelled', false)
            ->where('is_paused', false)
            ->first();

        if (! $clearance) {
            return null;
        }

        // Edge case: Complaint filed after hold period already expired
        if ($clearance->isEligibleForClearance()) {
            return null;
        }

        $remainingSeconds = max(0, (int) now()->diffInSeconds($clearance->withdrawable_at, false));

        $clearance->update([
            'is_paused' => true,
            'paused_at' => now(),
            'remaining_seconds_at_pause' => $remainingSeconds,
        ]);

        activity('order_clearances')
            ->performedOn($clearance)
            ->withProperties([
                'order_id' => $order->id,
                'remaining_seconds' => $remainingSeconds,
                'reason' => 'complaint_filed',
            ])
            ->log('timer_paused');

        return $clearance->fresh();
    }

    /**
     * Resume the timer when a complaint is resolved without refund.
     *
     * BR-339: Timer resumes when complaint is resolved (no refund).
     * Recalculates withdrawable_at based on remaining seconds at pause.
     */
    public function resumeTimer(Order $order): ?OrderClearance
    {
        $clearance = OrderClearance::query()
            ->where('order_id', $order->id)
            ->where('is_paused', true)
            ->where('is_cancelled', false)
            ->first();

        if (! $clearance) {
            return null;
        }

        // BR-310 edge case: Multiple complaints on same order
        // Only resume if ALL complaints on this order are resolved
        $activeComplaints = Complaint::query()
            ->where('order_id', $order->id)
            ->whereNotIn('status', ['resolved', 'dismissed'])
            ->count();

        if ($activeComplaints > 0) {
            return null;
        }

        $remainingSeconds = $clearance->remaining_seconds_at_pause ?? 0;
        $newWithdrawableAt = now()->addSeconds($remainingSeconds);

        $clearance->update([
            'is_paused' => false,
            'paused_at' => null,
            'remaining_seconds_at_pause' => null,
            'withdrawable_at' => $newWithdrawableAt,
        ]);

        activity('order_clearances')
            ->performedOn($clearance)
            ->withProperties([
                'order_id' => $order->id,
                'remaining_seconds' => $remainingSeconds,
                'new_withdrawable_at' => $newWithdrawableAt->toDateTimeString(),
                'reason' => 'complaint_resolved_no_refund',
            ])
            ->log('timer_resumed');

        return $clearance->fresh();
    }

    /**
     * Cancel a clearance when a complaint results in a refund.
     *
     * BR-340: Funds are removed from unwithdrawable (never become withdrawable).
     */
    public function cancelClearance(Order $order): ?OrderClearance
    {
        $clearance = OrderClearance::query()
            ->where('order_id', $order->id)
            ->where('is_cleared', false)
            ->where('is_cancelled', false)
            ->first();

        if (! $clearance) {
            return null;
        }

        $clearance->update([
            'is_cancelled' => true,
            'is_paused' => false,
        ]);

        activity('order_clearances')
            ->performedOn($clearance)
            ->withProperties([
                'order_id' => $order->id,
                'amount' => $clearance->amount,
                'reason' => 'complaint_refund',
            ])
            ->log('clearance_cancelled');

        // Recalculate wallet balances since unwithdrawable funds were removed
        $this->recalculateWalletForClearance($clearance);

        return $clearance->fresh();
    }

    /**
     * Process all eligible clearances — the main scheduled job logic.
     *
     * BR-336: Runs periodically to check and transition eligible funds.
     * BR-335: After hold period expires, funds transition to withdrawable.
     * BR-337: Cook is notified when funds become withdrawable.
     * BR-342: A wallet transaction record is created (type: became_withdrawable).
     * BR-343: Transition is logged via Spatie Activitylog.
     *
     * Scenario 4: Multiple orders clearing simultaneously — consolidated notification.
     *
     * @return array{processed: int, total_amount: float, cooks_notified: int}
     */
    public function processEligibleClearances(): array
    {
        $eligibleClearances = OrderClearance::query()
            ->eligibleForClearance()
            ->with(['order', 'tenant', 'cook'])
            ->get();

        if ($eligibleClearances->isEmpty()) {
            return ['processed' => 0, 'total_amount' => 0.0, 'cooks_notified' => 0];
        }

        $processedCount = 0;
        $totalAmount = 0.0;

        // Group by cook_id for consolidated notifications (Scenario 4)
        $groupedByCook = $eligibleClearances->groupBy('cook_id');
        $cooksNotified = 0;

        foreach ($groupedByCook as $cookId => $clearances) {
            $cookAmount = 0.0;
            $orderNumbers = [];

            foreach ($clearances as $clearance) {
                $this->transitionSingleClearance($clearance);
                $cookAmount += (float) $clearance->amount;
                $totalAmount += (float) $clearance->amount;
                $processedCount++;

                if ($clearance->order) {
                    $orderNumbers[] = $clearance->order->order_number;
                }
            }

            // Send consolidated notification per cook (BR-337, Scenario 4)
            $cook = $clearances->first()->cook;
            $tenant = $clearances->first()->tenant;

            if ($cook && $tenant) {
                $this->notifyCook($cook, $cookAmount, $orderNumbers, $tenant);
                $cooksNotified++;
            }
        }

        return [
            'processed' => $processedCount,
            'total_amount' => round($totalAmount, 2),
            'cooks_notified' => $cooksNotified,
        ];
    }

    /**
     * Transition a single clearance to cleared state.
     *
     * Creates a wallet transaction and updates wallet balances.
     */
    private function transitionSingleClearance(OrderClearance $clearance): void
    {
        DB::transaction(function () use ($clearance) {
            // Mark as cleared
            $clearance->update([
                'is_cleared' => true,
                'cleared_at' => now(),
            ]);

            // Mark the original payment_credit transaction as withdrawable
            if ($clearance->order_id) {
                WalletTransaction::query()
                    ->where('order_id', $clearance->order_id)
                    ->where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)
                    ->where('is_withdrawable', false)
                    ->update([
                        'is_withdrawable' => true,
                    ]);
            }

            // BR-342: Create a wallet transaction record (type: became_withdrawable)
            WalletTransaction::create([
                'user_id' => $clearance->cook_id,
                'tenant_id' => $clearance->tenant_id,
                'order_id' => $clearance->order_id,
                'type' => WalletTransaction::TYPE_BECAME_WITHDRAWABLE,
                'amount' => $clearance->amount,
                'currency' => 'XAF',
                'balance_before' => 0,
                'balance_after' => 0,
                'is_withdrawable' => true,
                'status' => 'completed',
                'description' => __('Funds from order became withdrawable'),
            ]);

            // Recalculate wallet balances
            $this->recalculateWalletForClearance($clearance);

            // BR-343: Log the transition
            activity('order_clearances')
                ->performedOn($clearance)
                ->withProperties([
                    'order_id' => $clearance->order_id,
                    'amount' => $clearance->amount,
                    'hold_hours' => $clearance->hold_hours,
                ])
                ->log('funds_became_withdrawable');
        });
    }

    /**
     * Recalculate the cook's wallet balances after a clearance state change.
     */
    private function recalculateWalletForClearance(OrderClearance $clearance): void
    {
        if (! $clearance->tenant_id || ! $clearance->cook_id) {
            return;
        }

        $tenant = $clearance->tenant;
        $cook = $clearance->cook;

        if (! $tenant || ! $cook) {
            return;
        }

        $wallet = CookWallet::getOrCreateForTenant($tenant, $cook);
        $this->cookWalletService->recalculateBalances($wallet);
    }

    /**
     * Send consolidated notification to the cook about newly withdrawable funds.
     *
     * BR-337: Cook is notified (push + DB) when funds become withdrawable.
     * Scenario 4: Consolidated notification for multiple orders.
     *
     * @param  array<string>  $orderNumbers
     */
    private function notifyCook(
        \App\Models\User $cook,
        float $amount,
        array $orderNumbers,
        \App\Models\Tenant $tenant
    ): void {
        $cook->notify(new FundsWithdrawableNotification(
            amount: $amount,
            orderCount: count($orderNumbers),
            orderNumbers: $orderNumbers,
            tenant: $tenant
        ));
    }

    /**
     * Check if an order has an active (unresolved) complaint.
     *
     * Used to determine if a clearance timer should be paused.
     */
    public function orderHasActiveComplaint(Order $order): bool
    {
        return Complaint::query()
            ->where('order_id', $order->id)
            ->whereNotIn('status', ['resolved', 'dismissed'])
            ->exists();
    }

    /**
     * Get the clearance record for an order.
     */
    public function getClearanceForOrder(Order $order): ?OrderClearance
    {
        return OrderClearance::query()
            ->where('order_id', $order->id)
            ->first();
    }
}
