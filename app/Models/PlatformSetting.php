<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide configuration settings.
 *
 * F-063: Platform Settings Management
 * Key-value store for system-wide behaviors:
 * - wallet_enabled (boolean): client wallet for order payments
 * - default_cancellation_window (integer): minutes, 0-120
 * - platform_name (string): used in emails, notifications, PWA manifest
 * - support_email (string): displayed in help section and notification emails
 * - support_phone (string): displayed in help section
 * - maintenance_mode (boolean): show maintenance page to non-admins
 * - maintenance_reason (string): reason shown on maintenance page
 */
class PlatformSetting extends Model
{
    use HasFactory;
    use LogsActivityTrait;

    protected $table = 'platform_settings';

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
    ];

    /**
     * Default setting values.
     *
     * @var array<string, array{value: string, type: string, group: string}>
     */
    public const DEFAULTS = [
        'platform_name' => ['value' => 'DancyMeals', 'type' => 'string', 'group' => 'general'],
        'wallet_enabled' => ['value' => '1', 'type' => 'boolean', 'group' => 'features'],
        'default_cancellation_window' => ['value' => '30', 'type' => 'integer', 'group' => 'orders'],
        'support_email' => ['value' => '', 'type' => 'string', 'group' => 'support'],
        'support_phone' => ['value' => '', 'type' => 'string', 'group' => 'support'],
        'maintenance_mode' => ['value' => '0', 'type' => 'boolean', 'group' => 'system'],
        'maintenance_reason' => ['value' => '', 'type' => 'string', 'group' => 'system'],
    ];

    /**
     * Setting groups for UI organization.
     *
     * @var array<string, string>
     */
    public const GROUPS = [
        'general' => 'General',
        'features' => 'Feature Toggles',
        'orders' => 'Order Settings',
        'support' => 'Support',
        'system' => 'System',
    ];

    /**
     * Settings that require confirmation before changing.
     * BR-190: Critical settings require confirmation dialog.
     *
     * @var list<string>
     */
    public const CRITICAL_SETTINGS = [
        'wallet_enabled',
        'maintenance_mode',
    ];

    /**
     * Settings restricted to super-admin only.
     * BR-187: Maintenance mode is accessible only to super-admin.
     *
     * @var list<string>
     */
    public const SUPER_ADMIN_ONLY = [
        'maintenance_mode',
        'maintenance_reason',
    ];

    /**
     * Cast the stored string value to the appropriate PHP type.
     */
    public function getTypedValueAttribute(): mixed
    {
        return match ($this->type) {
            'boolean' => (bool) (int) $this->value,
            'integer' => (int) $this->value,
            default => $this->value ?? '',
        };
    }

    /**
     * Check if this setting requires confirmation to change.
     */
    public function isCritical(): bool
    {
        return in_array($this->key, self::CRITICAL_SETTINGS);
    }

    /**
     * Check if this setting is restricted to super-admin.
     */
    public function isSuperAdminOnly(): bool
    {
        return in_array($this->key, self::SUPER_ADMIN_ONLY);
    }
}
