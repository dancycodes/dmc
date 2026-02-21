<?php

namespace App\Mail;

use App\Models\WithdrawalRequest;

/**
 * F-173: Withdrawal Processed Email (N-013, BR-358)
 *
 * Email sent to the cook when a withdrawal is successfully processed.
 * Includes withdrawal amount, destination, and a link to the wallet.
 *
 * BR-358: Cook notified via push + DB + email on success.
 * BR-365: Notification includes amount, destination, status.
 */
class WithdrawalProcessedMail extends BaseMailableNotification
{
    private WithdrawalRequest $withdrawal;

    private bool $success;

    public function __construct(WithdrawalRequest $withdrawal, bool $success = true)
    {
        $this->withdrawal = $withdrawal;
        $this->success = $success;

        $this->forTenant($withdrawal->tenant);
        $this->initializeMailable();
    }

    /**
     * Get the email subject line.
     */
    protected function getSubjectLine(): string
    {
        if ($this->success) {
            return $this->trans('Withdrawal Sent - :amount XAF', [
                'amount' => number_format((float) $this->withdrawal->amount),
            ]);
        }

        return $this->trans('Withdrawal Failed - :amount XAF', [
            'amount' => number_format((float) $this->withdrawal->amount),
        ]);
    }

    /**
     * Get the blade view name for the email content.
     */
    protected function getEmailView(): string
    {
        return 'emails.withdrawal-processed';
    }

    /**
     * Get the data to pass to the email view.
     *
     * @return array<string, mixed>
     */
    protected function getEmailData(): array
    {
        $formattedAmount = number_format((float) $this->withdrawal->amount, 0, '.', ',').' XAF';
        $tenant = $this->withdrawal->tenant;
        $domain = $tenant?->domain ?? ($tenant?->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST));
        $walletUrl = 'https://'.$domain.'/dashboard/wallet';

        return [
            'withdrawal' => $this->withdrawal,
            'formattedAmount' => $formattedAmount,
            'providerLabel' => $this->withdrawal->providerLabel(),
            'success' => $this->success,
            'walletUrl' => $walletUrl,
            'emailLocale' => $this->emailLocale,
        ];
    }

    /**
     * Get the email type identifier for queue routing.
     */
    protected function getEmailType(): string
    {
        return 'general';
    }
}
