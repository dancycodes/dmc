<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Service layer for platform settings management.
 *
 * F-063: Platform Settings Management
 * Handles CRUD operations, caching, and defaults for system-wide settings.
 * BR-189: All settings changes are logged in the activity log.
 * BR-191: Settings save without full page reload (via Gale).
 */
class PlatformSettingService
{
    /**
     * Cache key prefix for settings.
     */
    private const CACHE_PREFIX = 'platform_setting:';

    /**
     * Cache TTL in seconds (1 hour).
     */
    private const CACHE_TTL = 3600;

    /**
     * Get a single setting value by key, with fallback to default.
     */
    public function get(string $key): mixed
    {
        return Cache::remember(
            self::CACHE_PREFIX.$key,
            self::CACHE_TTL,
            function () use ($key) {
                $setting = PlatformSetting::query()->where('key', $key)->first();

                if ($setting) {
                    return $setting->typed_value;
                }

                // Return default value if not in DB
                if (isset(PlatformSetting::DEFAULTS[$key])) {
                    $default = PlatformSetting::DEFAULTS[$key];

                    return match ($default['type']) {
                        'boolean' => (bool) (int) $default['value'],
                        'integer' => (int) $default['value'],
                        default => $default['value'],
                    };
                }

                return null;
            }
        );
    }

    /**
     * Get all settings, organized by group.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAll(): array
    {
        $dbSettings = PlatformSetting::all()->keyBy('key');
        $result = [];

        foreach (PlatformSetting::DEFAULTS as $key => $default) {
            $group = $default['group'];

            if (! isset($result[$group])) {
                $result[$group] = [];
            }

            if ($dbSettings->has($key)) {
                $setting = $dbSettings->get($key);
                $result[$group][$key] = [
                    'value' => $setting->typed_value,
                    'type' => $setting->type,
                    'group' => $setting->group,
                ];
            } else {
                $result[$group][$key] = [
                    'value' => match ($default['type']) {
                        'boolean' => (bool) (int) $default['value'],
                        'integer' => (int) $default['value'],
                        default => $default['value'],
                    },
                    'type' => $default['type'],
                    'group' => $default['group'],
                ];
            }
        }

        return $result;
    }

    /**
     * Get all settings as a flat key-value map.
     *
     * @return array<string, mixed>
     */
    public function getAllFlat(): array
    {
        $grouped = $this->getAll();
        $flat = [];

        foreach ($grouped as $settings) {
            foreach ($settings as $key => $data) {
                $flat[$key] = $data['value'];
            }
        }

        return $flat;
    }

    /**
     * Update a single setting.
     *
     * BR-189: All settings changes are logged in the activity log.
     *
     * @return array{old_value: mixed, new_value: mixed, setting: PlatformSetting}
     */
    public function update(string $key, mixed $value, User $admin): array
    {
        if (! isset(PlatformSetting::DEFAULTS[$key])) {
            throw new \InvalidArgumentException("Unknown setting key: {$key}");
        }

        $default = PlatformSetting::DEFAULTS[$key];
        $oldValue = $this->get($key);

        // Cast value to string for storage
        $storedValue = match ($default['type']) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            default => (string) $value,
        };

        $setting = PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'type' => $default['type'],
                'group' => $default['group'],
            ]
        );

        // Log the activity
        $newValue = $setting->typed_value;

        activity('platform_settings')
            ->performedOn($setting)
            ->causedBy($admin)
            ->withProperties([
                'key' => $key,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ])
            ->log("Updated platform setting: {$key}");

        // Clear cache
        $this->clearCache($key);

        return [
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'setting' => $setting,
        ];
    }

    /**
     * Seed all default settings into the database if they don't exist.
     */
    public function seedDefaults(): void
    {
        foreach (PlatformSetting::DEFAULTS as $key => $default) {
            PlatformSetting::query()->firstOrCreate(
                ['key' => $key],
                [
                    'value' => $default['value'],
                    'type' => $default['type'],
                    'group' => $default['group'],
                ]
            );
        }
    }

    /**
     * Check if wallet payments are enabled.
     * BR-183: Wallet toggle affects only wallet-to-order payments.
     */
    public function isWalletEnabled(): bool
    {
        return (bool) $this->get('wallet_enabled');
    }

    /**
     * Get the default cancellation window in minutes.
     * BR-184: Default cancellation window applies to all cooks unless they have a custom override.
     */
    public function getDefaultCancellationWindow(): int
    {
        return (int) $this->get('default_cancellation_window');
    }

    /**
     * Get the withdrawable hold period in hours.
     *
     * F-171 BR-334: Default hold period is 3 hours, configurable by admin.
     */
    public function getWithdrawableHoldHours(): int
    {
        return (int) $this->get('withdrawable_hold_hours');
    }

    /**
     * Get the minimum withdrawal amount in XAF.
     *
     * F-172 BR-345: Default 1,000 XAF, configurable by admin.
     */
    public function getMinWithdrawalAmount(): int
    {
        return (int) $this->get('min_withdrawal_amount');
    }

    /**
     * Get the maximum daily withdrawal amount in XAF.
     *
     * F-172 BR-346: Default 500,000 XAF, configurable by admin.
     */
    public function getMaxDailyWithdrawalAmount(): int
    {
        return (int) $this->get('max_daily_withdrawal_amount');
    }

    /**
     * Check if maintenance mode is enabled.
     */
    public function isMaintenanceMode(): bool
    {
        return (bool) $this->get('maintenance_mode');
    }

    /**
     * Get the platform name.
     * BR-186: Used in emails, notifications, and PWA manifest.
     */
    public function getPlatformName(): string
    {
        return (string) $this->get('platform_name');
    }

    /**
     * Clear the cache for a specific setting or all settings.
     */
    public function clearCache(?string $key = null): void
    {
        if ($key !== null) {
            Cache::forget(self::CACHE_PREFIX.$key);
        } else {
            foreach (array_keys(PlatformSetting::DEFAULTS) as $settingKey) {
                Cache::forget(self::CACHE_PREFIX.$settingKey);
            }
        }
    }
}
