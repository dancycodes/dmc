<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;

/**
 * F-212: Cancellation Window Configuration
 * F-213: Minimum Order Amount Configuration
 *
 * Handles reading and updating cook settings stored in the tenant's settings JSON column.
 */
class CookSettingsService
{
    /**
     * Default cancellation window in minutes.
     *
     * BR-494: Default cancellation window is 15 minutes.
     */
    public const DEFAULT_CANCELLATION_WINDOW = 15;

    /**
     * Minimum allowed cancellation window in minutes.
     *
     * BR-495: Allowed range: 5 to 120 minutes (inclusive).
     */
    public const MIN_CANCELLATION_WINDOW = 5;

    /**
     * Maximum allowed cancellation window in minutes.
     *
     * BR-495: Allowed range: 5 to 120 minutes (inclusive).
     */
    public const MAX_CANCELLATION_WINDOW = 120;

    /**
     * Settings JSON key for cancellation window.
     *
     * BR-499: The cancellation window value is stored as `cancellation_window_minutes`
     * in the tenant settings JSON column.
     */
    public const SETTINGS_KEY = 'cancellation_window_minutes';

    /**
     * Default minimum order amount in XAF.
     *
     * BR-507: Default minimum order amount is 0 XAF (no minimum).
     */
    public const DEFAULT_MINIMUM_ORDER_AMOUNT = 0;

    /**
     * Minimum allowed minimum order amount in XAF.
     *
     * BR-508: Allowed range: 0 to 100,000 XAF (inclusive).
     */
    public const MIN_ORDER_AMOUNT = 0;

    /**
     * Maximum allowed minimum order amount in XAF.
     *
     * BR-508: Allowed range: 0 to 100,000 XAF (inclusive).
     */
    public const MAX_ORDER_AMOUNT = 100000;

    /**
     * Settings JSON key for minimum order amount.
     *
     * BR-507: Stored as `minimum_order_amount` in tenant settings JSON column.
     */
    public const MINIMUM_ORDER_AMOUNT_KEY = 'minimum_order_amount';

    /**
     * Get the current cancellation window for a tenant.
     *
     * BR-494: Default is 15 minutes if not configured.
     * BR-498: Existing orders retain their snapshotted value.
     */
    public function getCancellationWindow(Tenant $tenant): int
    {
        $value = $tenant->getSetting(self::SETTINGS_KEY);

        if ($value === null) {
            return self::DEFAULT_CANCELLATION_WINDOW;
        }

        return (int) $value;
    }

    /**
     * Update the cancellation window for a tenant.
     *
     * BR-497: Setting applies to all new orders from the moment it is saved.
     * BR-503: Only the cook can modify this setting (enforced at controller level).
     * BR-504: All changes are logged via Spatie Activitylog with old and new values.
     *
     * @return array{old_value: int, new_value: int}
     */
    public function updateCancellationWindow(Tenant $tenant, int $minutes, User $cook): array
    {
        $oldValue = $this->getCancellationWindow($tenant);

        $tenant->setSetting(self::SETTINGS_KEY, $minutes);
        $tenant->save();

        // BR-504: Log the change with old and new values
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($cook)
            ->withProperties([
                'old' => [self::SETTINGS_KEY => $oldValue],
                'attributes' => [self::SETTINGS_KEY => $minutes],
            ])
            ->log('cancellation_window_updated');

        return [
            'old_value' => $oldValue,
            'new_value' => $minutes,
        ];
    }

    /**
     * Get the current minimum order amount for a tenant.
     *
     * BR-507: Default is 0 XAF (no minimum) if not configured.
     */
    public function getMinimumOrderAmount(Tenant $tenant): int
    {
        $value = $tenant->getSetting(self::MINIMUM_ORDER_AMOUNT_KEY);

        if ($value === null) {
            return self::DEFAULT_MINIMUM_ORDER_AMOUNT;
        }

        return (int) $value;
    }

    /**
     * Update the minimum order amount for a tenant.
     *
     * BR-515: Only the cook can modify this setting (enforced at controller level).
     * BR-517: All changes are logged via Spatie Activitylog with old and new values.
     *
     * @return array{old_value: int, new_value: int}
     */
    public function updateMinimumOrderAmount(Tenant $tenant, int $amount, User $cook): array
    {
        $oldValue = $this->getMinimumOrderAmount($tenant);

        $tenant->setSetting(self::MINIMUM_ORDER_AMOUNT_KEY, $amount);
        $tenant->save();

        // BR-517: Log the change with old and new values
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($cook)
            ->withProperties([
                'old' => [self::MINIMUM_ORDER_AMOUNT_KEY => $oldValue],
                'attributes' => [self::MINIMUM_ORDER_AMOUNT_KEY => $amount],
            ])
            ->log('minimum_order_amount_updated');

        return [
            'old_value' => $oldValue,
            'new_value' => $amount,
        ];
    }
}
