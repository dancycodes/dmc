<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    /**
     * Notification types supported by the platform (BR-175).
     */
    public const TYPES = [
        'orders',
        'payments',
        'complaints',
        'promotions',
        'system',
    ];

    /**
     * Human-readable labels for each notification type.
     * Keys match TYPES values; returned for use in views.
     */
    public const TYPE_LABELS = [
        'orders' => 'Orders',
        'payments' => 'Payments',
        'complaints' => 'Complaints',
        'promotions' => 'Promotions',
        'system' => 'System',
    ];

    /**
     * Brief descriptions for each notification type.
     * Displayed in the preference matrix UI.
     */
    public const TYPE_DESCRIPTIONS = [
        'orders' => 'New orders, status updates, cancellations',
        'payments' => 'Payment confirmations, failures, refunds',
        'complaints' => 'New complaints, responses, resolutions',
        'promotions' => 'Promo codes, special offers, discounts',
        'system' => 'System announcements and platform updates',
    ];

    protected $fillable = [
        'user_id',
        'notification_type',
        'push_enabled',
        'email_enabled',
    ];

    public function casts(): array
    {
        return [
            'push_enabled' => 'boolean',
            'email_enabled' => 'boolean',
        ];
    }

    /**
     * The user this preference belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get or create a default preference record for a user and type.
     * BR-178: Defaults all channels to ON for new users.
     */
    public static function getOrCreateForUserAndType(int $userId, string $type): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId, 'notification_type' => $type],
            ['push_enabled' => true, 'email_enabled' => true]
        );
    }

    /**
     * Get all preferences for a user, keyed by notification_type.
     * Creates defaults for any missing types (BR-178).
     *
     * @return array<string, self>
     */
    public static function getAllForUser(int $userId): array
    {
        $existing = self::where('user_id', $userId)
            ->get()
            ->keyBy('notification_type');

        $result = [];
        foreach (self::TYPES as $type) {
            $result[$type] = $existing->get($type)
                ?? self::getOrCreateForUserAndType($userId, $type);
        }

        return $result;
    }

    /**
     * Check whether push notifications should be sent for a given user and type.
     * BR-180: Checked before dispatching push notifications.
     */
    public static function isPushEnabled(int $userId, string $type): bool
    {
        $preference = self::where('user_id', $userId)
            ->where('notification_type', $type)
            ->first();

        // BR-178: Default to true if no preference set yet
        return $preference === null || $preference->push_enabled;
    }

    /**
     * Check whether email notifications should be sent for a given user and type.
     * BR-180: Checked before dispatching email notifications.
     */
    public static function isEmailEnabled(int $userId, string $type): bool
    {
        $preference = self::where('user_id', $userId)
            ->where('notification_type', $type)
            ->first();

        // BR-178: Default to true if no preference set yet
        return $preference === null || $preference->email_enabled;
    }
}
