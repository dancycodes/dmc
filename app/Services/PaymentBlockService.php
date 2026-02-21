<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Order;
use App\Models\OrderClearance;
use Illuminate\Support\Collection;

/**
 * F-186: Complaint-Triggered Payment Block
 *
 * Central service for blocking/unblocking cook payments when complaints are filed.
 *
 * BR-217: When a complaint is filed, check the cook's payment record for the order.
 * BR-218: If payment is unwithdrawable, pause the timer immediately.
 * BR-219: Retain remaining duration for resumption.
 * BR-220: Block lifted only when complaint is resolved.
 * BR-221: Dismiss -> resume timer from where it paused.
 * BR-222: Refund -> adjust payment amount accordingly.
 * BR-223: Already-withdrawable payments are flagged (not blocked).
 * BR-224: Flagged payments subject to admin-managed deduction.
 * BR-225: Cook cannot withdraw blocked/flagged amounts.
 * BR-226: Block status visible on wallet and order detail views.
 * BR-227: Block/unblock events logged via Spatie Activitylog.
 * BR-228: Auto-escalation keeps block in effect.
 */
class PaymentBlockService
{
    public function __construct(
        private OrderClearanceService $clearanceService
    ) {}

    /**
     * Block payment for an order when a complaint is filed.
     *
     * BR-217: Check the cook's payment record for the associated order.
     * BR-218: If unwithdrawable, pause timer immediately.
     * BR-223: If already withdrawable, flag for review.
     *
     * @return array{action: string, clearance: ?OrderClearance}
     */
    public function blockPaymentForComplaint(Order $order, Complaint $complaint): array
    {
        $clearance = OrderClearance::query()
            ->where('order_id', $order->id)
            ->where('is_cancelled', false)
            ->first();

        // No clearance record exists (order may not have been completed yet)
        if (! $clearance) {
            return ['action' => 'no_clearance', 'clearance' => null];
        }

        // Already blocked by another complaint (edge case: shouldn't happen per BR-184)
        if ($clearance->isBlocked() || $clearance->isFlaggedForReview()) {
            return ['action' => 'already_blocked', 'clearance' => $clearance];
        }

        // Case 1: Payment still in hold period (unwithdrawable) — pause timer
        if ($clearance->isInHoldPeriod() || ($clearance->is_paused && ! $clearance->complaint_id)) {
            return $this->pauseAndBlock($clearance, $complaint);
        }

        // Case 2: Payment already cleared/withdrawable — flag for review
        if ($clearance->is_cleared || $clearance->isEligibleForClearance()) {
            return $this->flagForReview($clearance, $complaint);
        }

        // Case 3: Still in hold period but not yet eligible — pause
        if (! $clearance->is_cleared && ! $clearance->is_cancelled) {
            return $this->pauseAndBlock($clearance, $complaint);
        }

        return ['action' => 'no_action', 'clearance' => $clearance];
    }

    /**
     * Unblock payment when a complaint is resolved.
     *
     * BR-220: Block lifted when complaint status changes to resolved.
     * BR-221: Dismiss -> resume timer.
     * BR-222: Refund -> payment adjusted.
     *
     * @return array{action: string, clearance: ?OrderClearance}
     */
    public function unblockPaymentOnResolution(Complaint $complaint, string $resolutionType): array
    {
        $clearance = OrderClearance::query()
            ->where('complaint_id', $complaint->id)
            ->first();

        if (! $clearance) {
            return ['action' => 'no_clearance', 'clearance' => null];
        }

        // BR-221: Dismiss or warning — resume timer
        if (in_array($resolutionType, ['dismiss', 'warning'], true)) {
            return $this->resumeAfterDismiss($clearance, $complaint);
        }

        // BR-222: Refund — cancel clearance (handled by refund flow in F-174)
        if (in_array($resolutionType, ['partial_refund', 'full_refund', 'suspend'], true)) {
            return $this->cancelOnRefund($clearance, $complaint);
        }

        return ['action' => 'no_action', 'clearance' => $clearance];
    }

    /**
     * Get all blocked/flagged clearances for a tenant.
     *
     * BR-226: Payment block status visible to the cook.
     *
     * @return Collection<int, OrderClearance>
     */
    public function getBlockedClearancesForTenant(int $tenantId): Collection
    {
        return OrderClearance::query()
            ->where('tenant_id', $tenantId)
            ->withActiveComplaintBlock()
            ->with(['order:id,order_number', 'complaint:id,status,category'])
            ->orderBy('blocked_at', 'desc')
            ->get();
    }

    /**
     * Get the total blocked amount for a tenant.
     *
     * BR-225: Cook cannot withdraw blocked/flagged amounts.
     */
    public function getTotalBlockedAmount(int $tenantId): float
    {
        return (float) OrderClearance::query()
            ->where('tenant_id', $tenantId)
            ->withActiveComplaintBlock()
            ->sum('amount');
    }

    /**
     * Get the blocked clearance for a specific order (if any).
     *
     * BR-226: Visible on order detail view.
     */
    public function getBlockedClearanceForOrder(int $orderId): ?OrderClearance
    {
        return OrderClearance::query()
            ->where('order_id', $orderId)
            ->withActiveComplaintBlock()
            ->with(['complaint:id,status,category,submitted_at'])
            ->first();
    }

    /**
     * BR-218: Pause the timer and record the complaint block.
     *
     * @return array{action: string, clearance: OrderClearance}
     */
    private function pauseAndBlock(OrderClearance $clearance, Complaint $complaint): array
    {
        // Use existing pauseTimer if not already paused
        if (! $clearance->is_paused) {
            $this->clearanceService->pauseTimer($clearance->order);
            $clearance->refresh();
        }

        // Record the complaint block
        $clearance->update([
            'complaint_id' => $complaint->id,
            'blocked_at' => now(),
        ]);

        // BR-227: Log the block event
        activity('order_clearances')
            ->performedOn($clearance)
            ->withProperties([
                'order_id' => $clearance->order_id,
                'complaint_id' => $complaint->id,
                'amount' => $clearance->amount,
                'remaining_seconds' => $clearance->remaining_seconds_at_pause,
                'action' => 'payment_blocked',
            ])
            ->log('payment_blocked_by_complaint');

        return ['action' => 'blocked', 'clearance' => $clearance->fresh()];
    }

    /**
     * BR-223: Flag already-withdrawable payment for review.
     *
     * @return array{action: string, clearance: OrderClearance}
     */
    private function flagForReview(OrderClearance $clearance, Complaint $complaint): array
    {
        $clearance->update([
            'complaint_id' => $complaint->id,
            'is_flagged_for_review' => true,
            'blocked_at' => now(),
        ]);

        // BR-227: Log the flag event
        activity('order_clearances')
            ->performedOn($clearance)
            ->withProperties([
                'order_id' => $clearance->order_id,
                'complaint_id' => $complaint->id,
                'amount' => $clearance->amount,
                'action' => 'payment_flagged_for_review',
            ])
            ->log('payment_flagged_for_review');

        return ['action' => 'flagged', 'clearance' => $clearance->fresh()];
    }

    /**
     * BR-221: Resume timer after dismiss resolution.
     *
     * @return array{action: string, clearance: OrderClearance}
     */
    private function resumeAfterDismiss(OrderClearance $clearance, Complaint $complaint): array
    {
        // If it was flagged (already withdrawable), just remove the flag
        if ($clearance->is_flagged_for_review) {
            $clearance->update([
                'is_flagged_for_review' => false,
                'unblocked_at' => now(),
            ]);

            // BR-227: Log the unblock event
            activity('order_clearances')
                ->performedOn($clearance)
                ->withProperties([
                    'order_id' => $clearance->order_id,
                    'complaint_id' => $complaint->id,
                    'amount' => $clearance->amount,
                    'resolution_type' => 'dismiss',
                    'action' => 'payment_unflagged',
                ])
                ->log('payment_unblocked_on_resolution');

            return ['action' => 'unflagged', 'clearance' => $clearance->fresh()];
        }

        // If it was paused/blocked, resume the timer
        if ($clearance->is_paused) {
            $this->clearanceService->resumeTimer($clearance->order);
            $clearance->refresh();

            $clearance->update([
                'unblocked_at' => now(),
            ]);

            // BR-227: Log the unblock event
            activity('order_clearances')
                ->performedOn($clearance)
                ->withProperties([
                    'order_id' => $clearance->order_id,
                    'complaint_id' => $complaint->id,
                    'amount' => $clearance->amount,
                    'resolution_type' => 'dismiss',
                    'action' => 'payment_unblocked_timer_resumed',
                ])
                ->log('payment_unblocked_on_resolution');
        }

        return ['action' => 'resumed', 'clearance' => $clearance->fresh()];
    }

    /**
     * BR-222: Cancel clearance on refund resolution.
     *
     * @return array{action: string, clearance: OrderClearance}
     */
    private function cancelOnRefund(OrderClearance $clearance, Complaint $complaint): array
    {
        // If flagged (already withdrawable), mark as cancelled and record unblock
        // The actual deduction is handled by F-174 AutoDeductionService
        if ($clearance->is_flagged_for_review) {
            $clearance->update([
                'is_flagged_for_review' => false,
                'unblocked_at' => now(),
            ]);

            activity('order_clearances')
                ->performedOn($clearance)
                ->withProperties([
                    'order_id' => $clearance->order_id,
                    'complaint_id' => $complaint->id,
                    'amount' => $clearance->amount,
                    'resolution_type' => $complaint->resolution_type,
                    'action' => 'payment_flagged_resolved_with_refund',
                ])
                ->log('payment_unblocked_on_resolution');

            return ['action' => 'unflagged_for_refund', 'clearance' => $clearance->fresh()];
        }

        // If blocked (timer paused), cancel the clearance
        // F-171 OrderClearanceService::cancelClearance handles the wallet recalculation
        $this->clearanceService->cancelClearance($clearance->order);
        $clearance->refresh();

        $clearance->update([
            'unblocked_at' => now(),
        ]);

        activity('order_clearances')
            ->performedOn($clearance)
            ->withProperties([
                'order_id' => $clearance->order_id,
                'complaint_id' => $complaint->id,
                'amount' => $clearance->amount,
                'resolution_type' => $complaint->resolution_type,
                'action' => 'payment_cancelled_on_refund',
            ])
            ->log('payment_unblocked_on_resolution');

        return ['action' => 'cancelled', 'clearance' => $clearance->fresh()];
    }
}
