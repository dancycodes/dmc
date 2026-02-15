<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;

/**
 * Centralized service for email notification configuration.
 *
 * Handles locale resolution based on recipient preferences,
 * tenant branding for tenant-context emails, and email
 * configuration defaults for the DancyMeals platform.
 */
class EmailNotificationService
{
    /**
     * The default from address for all platform emails.
     */
    public const DEFAULT_FROM_ADDRESS = 'noreply@dancymeals.com';

    /**
     * The default from name for all platform emails.
     */
    public const DEFAULT_FROM_NAME = 'DancyMeals';

    /**
     * The default number of retry attempts for failed emails.
     */
    public const MAX_RETRIES = 3;

    /**
     * Queue names for different priority levels.
     */
    public const QUEUE_DEFAULT = 'emails';

    public const QUEUE_HIGH = 'emails-high';

    /**
     * Critical email types that use the high-priority queue.
     *
     * @var array<int, string>
     */
    public const CRITICAL_EMAIL_TYPES = [
        'password_reset',
        'email_verification',
    ];

    /**
     * Resolve the preferred locale for a given user.
     * Falls back to the application default locale.
     */
    public function resolveLocale(?User $user): string
    {
        if ($user && $user->preferred_language) {
            $available = config('app.available_locales', ['en', 'fr']);

            if (in_array($user->preferred_language, $available, true)) {
                return $user->preferred_language;
            }
        }

        return config('app.locale', 'en');
    }

    /**
     * Get the tenant branding data for email context.
     * Returns null if no tenant context is available.
     *
     * @return array{name: string, slug: string}|null
     */
    public function getTenantBranding(?Tenant $tenant): ?array
    {
        if (! $tenant) {
            return null;
        }

        return [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
        ];
    }

    /**
     * Get the configured from address for emails.
     */
    public function getFromAddress(): string
    {
        return config('mail.from.address', self::DEFAULT_FROM_ADDRESS);
    }

    /**
     * Get the configured from name for emails.
     */
    public function getFromName(): string
    {
        return config('mail.from.name', self::DEFAULT_FROM_NAME);
    }

    /**
     * Determine the appropriate queue name for an email type.
     */
    public function getQueueName(string $emailType): string
    {
        if (in_array($emailType, self::CRITICAL_EMAIL_TYPES, true)) {
            return self::QUEUE_HIGH;
        }

        return self::QUEUE_DEFAULT;
    }

    /**
     * Check if an email type is considered critical (high priority).
     */
    public function isCriticalEmail(string $emailType): bool
    {
        return in_array($emailType, self::CRITICAL_EMAIL_TYPES, true);
    }

    /**
     * Get the retry delay in seconds for a given attempt number.
     * Uses exponential backoff: 10s, 30s, 90s.
     *
     * @return array<int, int>
     */
    public function getRetryDelays(): array
    {
        return [10, 30, 90];
    }

    /**
     * Get the support contact email for email footers.
     */
    public function getSupportEmail(): string
    {
        return config('mail.support_email', 'support@dancymeals.com');
    }

    /**
     * Get the platform URL for email links.
     */
    public function getPlatformUrl(): string
    {
        return config('app.url', 'https://dancymeals.com');
    }

    /**
     * Get common email template data shared across all emails.
     *
     * @return array<string, mixed>
     */
    public function getCommonTemplateData(?Tenant $tenant = null, ?User $recipient = null): array
    {
        return [
            'appName' => config('app.name', 'DancyMeals'),
            'appUrl' => $this->getPlatformUrl(),
            'supportEmail' => $this->getSupportEmail(),
            'tenantBranding' => $this->getTenantBranding($tenant),
            'locale' => $this->resolveLocale($recipient),
            'currentYear' => now()->year,
        ];
    }
}
