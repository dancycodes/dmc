<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class ComplaintResolutionService
{
    /**
     * Resolve a complaint with the specified action.
     *
     * BR-165: Resolution options: Dismiss, Partial Refund, Full Refund, Warning, Suspend
     * BR-166: Resolution note required for all types
     * BR-174: A complaint can only be resolved once
     *
     * @param  array{resolution_type: string, resolution_notes: string, refund_amount?: float, suspension_days?: int}  $data
     */
    public function resolve(Complaint $complaint, array $data, User $admin): Complaint
    {
        if ($complaint->isResolved()) {
            throw new \LogicException(__('This complaint has already been resolved.'));
        }

        return DB::transaction(function () use ($complaint, $data, $admin) {
            $resolutionType = $data['resolution_type'];

            $complaint->forceFill([
                'resolution_type' => $resolutionType,
                'resolution_notes' => $data['resolution_notes'],
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
                'status' => $resolutionType === 'dismiss' ? 'dismissed' : 'resolved',
            ]);

            // Handle resolution-type-specific actions
            match ($resolutionType) {
                'partial_refund' => $this->handlePartialRefund($complaint, $data),
                'full_refund' => $this->handleFullRefund($complaint),
                'warning' => $this->handleWarning($complaint, $data, $admin),
                'suspend' => $this->handleSuspension($complaint, $data, $admin),
                default => null,
            };

            $complaint->save();

            // BR-173: Log resolution in activity log
            $this->logResolution($complaint, $admin);

            return $complaint;
        });
    }

    /**
     * BR-167: Process partial refund — record amount on complaint.
     * BR-168: Refunds credited to client wallet (wallet feature F-166 pending).
     */
    private function handlePartialRefund(Complaint $complaint, array $data): void
    {
        $complaint->refund_amount = $data['refund_amount'];
    }

    /**
     * BR-169: Process full refund — record full order amount.
     * BR-168: Refunds credited to client wallet (wallet feature F-166 pending).
     */
    private function handleFullRefund(Complaint $complaint): void
    {
        // The order_id references a future Order model.
        // For now, the refund amount will be set from the payment transaction if available.
        $complaint->refund_amount = $this->getOrderAmount($complaint);
    }

    /**
     * BR-170: Record warning on cook's profile via activity log.
     */
    private function handleWarning(Complaint $complaint, array $data, User $admin): void
    {
        $cook = $complaint->cook;
        if ($cook) {
            activity('complaints')
                ->causedBy($admin)
                ->performedOn($cook)
                ->withProperties([
                    'complaint_id' => $complaint->id,
                    'type' => 'warning',
                    'note' => $data['resolution_notes'],
                ])
                ->log('warning_issued');
        }
    }

    /**
     * BR-171: Suspend cook by deactivating their tenant for the specified duration.
     */
    private function handleSuspension(Complaint $complaint, array $data, User $admin): void
    {
        $suspensionDays = (int) $data['suspension_days'];
        $complaint->suspension_days = $suspensionDays;
        $complaint->suspension_ends_at = now()->addDays($suspensionDays);

        // Deactivate the cook's tenant
        $tenant = $complaint->tenant;
        if ($tenant && $tenant->is_active) {
            $tenant->update(['is_active' => false]);

            activity('tenants')
                ->causedBy($admin)
                ->performedOn($tenant)
                ->withProperties([
                    'complaint_id' => $complaint->id,
                    'suspension_days' => $suspensionDays,
                    'suspension_ends_at' => $complaint->suspension_ends_at->toDateTimeString(),
                    'reason' => 'complaint_suspension',
                ])
                ->log('suspended');
        }

        // Log warning on cook as well
        $cook = $complaint->cook;
        if ($cook) {
            activity('complaints')
                ->causedBy($admin)
                ->performedOn($cook)
                ->withProperties([
                    'complaint_id' => $complaint->id,
                    'type' => 'suspension',
                    'suspension_days' => $suspensionDays,
                    'note' => $data['resolution_notes'],
                ])
                ->log('cook_suspended');
        }
    }

    /**
     * Get the order amount from associated payment transactions.
     * Returns the amount from the first successful payment transaction for this order.
     */
    private function getOrderAmount(Complaint $complaint): ?float
    {
        if (! $complaint->order_id) {
            return null;
        }

        $transaction = \App\Models\PaymentTransaction::query()
            ->where('order_id', $complaint->order_id)
            ->where('status', 'successful')
            ->first();

        return $transaction ? (float) $transaction->amount : null;
    }

    /**
     * BR-173: Log the resolution action in the activity log.
     */
    private function logResolution(Complaint $complaint, User $admin): void
    {
        activity('complaints')
            ->causedBy($admin)
            ->performedOn($complaint)
            ->withProperties([
                'resolution_type' => $complaint->resolution_type,
                'resolution_notes' => $complaint->resolution_notes,
                'refund_amount' => $complaint->refund_amount,
                'suspension_days' => $complaint->suspension_days,
            ])
            ->log('complaint_resolved');
    }

    /**
     * Get the count of warnings issued to a specific cook.
     */
    public function getCookWarningCount(User $cook): int
    {
        return Activity::query()
            ->where('log_name', 'complaints')
            ->where('subject_type', User::class)
            ->where('subject_id', $cook->id)
            ->whereIn('description', ['warning_issued', 'cook_suspended'])
            ->count();
    }

    /**
     * Get the count of previous complaints against a cook.
     */
    public function getCookComplaintCount(User $cook): int
    {
        return Complaint::query()
            ->where('cook_id', $cook->id)
            ->count();
    }

    /**
     * Get previous suspensions for the cook.
     *
     * @return array<int, array{complaint_id: int, suspension_days: int, suspension_ends_at: string, resolved_at: string}>
     */
    public function getCookPreviousSuspensions(User $cook): array
    {
        return Complaint::query()
            ->where('cook_id', $cook->id)
            ->where('resolution_type', 'suspend')
            ->whereNotNull('suspension_ends_at')
            ->orderBy('resolved_at', 'desc')
            ->get(['id', 'suspension_days', 'suspension_ends_at', 'resolved_at'])
            ->map(fn (Complaint $c) => [
                'complaint_id' => $c->id,
                'suspension_days' => $c->suspension_days,
                'suspension_ends_at' => $c->suspension_ends_at?->format('M d, Y'),
                'resolved_at' => $c->resolved_at?->format('M d, Y'),
            ])
            ->all();
    }

    /**
     * Check if the order associated with this complaint has already been refunded.
     */
    public function isOrderAlreadyRefunded(Complaint $complaint): bool
    {
        if (! $complaint->order_id) {
            return false;
        }

        // Check if there's another resolved complaint with a refund for this order
        return Complaint::query()
            ->where('order_id', $complaint->order_id)
            ->where('id', '!=', $complaint->id)
            ->whereIn('resolution_type', ['partial_refund', 'full_refund'])
            ->exists();
    }
}
