<?php

namespace App\Services;

use App\Models\CommissionChange;
use App\Models\Tenant;
use App\Models\User;

class CommissionService
{
    /**
     * Update the commission rate for a tenant.
     *
     * BR-176: Commission range 0%-50% in 0.5% increments
     * BR-177: New rate applies only to orders placed after the change
     * BR-179: Commission changes recorded with new rate, admin, timestamp, reason
     * BR-181: Commission configuration logged in activity log
     * BR-182: Flutterwave subaccount split percentage update (noted for future)
     *
     * @return array{change: CommissionChange, flutterwave_warning: bool}
     */
    public function updateRate(Tenant $tenant, float $newRate, User $admin, ?string $reason = null): array
    {
        $oldRate = $tenant->getCommissionRate();

        // Snap to 0.5 increments
        $newRate = round($newRate * 2) / 2;

        // Create commission change record
        $change = CommissionChange::create([
            'tenant_id' => $tenant->id,
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
            'changed_by' => $admin->id,
            'reason' => $reason,
        ]);

        // Update tenant settings with new rate
        $tenant->setSetting('commission_rate', $newRate);
        $tenant->save();

        // BR-181: Log in activity log
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($admin)
            ->withProperties([
                'old' => ['commission_rate' => $oldRate],
                'attributes' => ['commission_rate' => $newRate],
                'reason' => $reason,
                'change_id' => $change->id,
            ])
            ->log('commission_updated');

        // BR-182: Flutterwave subaccount update is a future concern
        // For now, flag that the admin should manually verify Flutterwave settings
        $flutterwaveWarning = $tenant->cook_id !== null;

        return [
            'change' => $change,
            'flutterwave_warning' => $flutterwaveWarning,
        ];
    }

    /**
     * Reset the commission rate to the platform default.
     *
     * BR-180: "Reset to Default" sets rate back to platform default (10%)
     *
     * @return array{change: CommissionChange, flutterwave_warning: bool}
     */
    public function resetToDefault(Tenant $tenant, User $admin): array
    {
        return $this->updateRate(
            $tenant,
            CommissionChange::DEFAULT_RATE,
            $admin,
            __('Reset to platform default')
        );
    }

    /**
     * Get the commission change history for a tenant.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<CommissionChange>
     */
    public function getHistory(Tenant $tenant, int $perPage = 10): mixed
    {
        return $tenant->commissionChanges()
            ->with('admin')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
