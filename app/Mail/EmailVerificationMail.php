<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * DancyMeals-branded email verification notification.
 *
 * Extends BaseMailableNotification for consistent branding, locale-aware
 * content, and queued delivery via the high-priority emails queue (BR-121).
 *
 * Generates a signed verification URL with 60-minute expiration (BR-039).
 */
class EmailVerificationMail extends BaseMailableNotification
{
    /**
     * The verification URL for the user.
     */
    protected string $verificationUrl;

    public function __construct(
        protected User $user,
    ) {
        $this->forRecipient($user);
        $this->forTenant(tenant());
        $this->verificationUrl = $this->generateVerificationUrl();
        $this->initializeMailable();
    }

    /**
     * Get the localized email subject line (N-021).
     */
    protected function getSubjectLine(): string
    {
        return $this->trans('Verify your DancyMeals email address');
    }

    /**
     * Get the blade view name for the email content.
     */
    protected function getEmailView(): string
    {
        return 'emails.verify-email';
    }

    /**
     * Get the data to pass to the email view.
     *
     * @return array<string, mixed>
     */
    protected function getEmailData(): array
    {
        return [
            'userName' => $this->user->name,
            'verificationUrl' => $this->verificationUrl,
            'expirationMinutes' => Config::get('auth.verification.expire', 60),
        ];
    }

    /**
     * Mark as critical email for high-priority queue routing (BR-121).
     */
    protected function getEmailType(): string
    {
        return 'email_verification';
    }

    /**
     * Generate a signed verification URL with expiration.
     *
     * Uses Laravel's URL::temporarySignedRoute to create a URL
     * that expires after the configured verification timeout (BR-039).
     */
    private function generateVerificationUrl(): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $this->user->getKey(),
                'hash' => sha1($this->user->getEmailForVerification()),
            ]
        );
    }
}
