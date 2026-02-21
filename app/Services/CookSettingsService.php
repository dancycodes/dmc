<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;

/**
 * F-212: Cancellation Window Configuration
 *
 * Handles reading and updating the cook's cancellation window setting.
 * The setting is stored in the tenant's settings JSON column.
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
}
