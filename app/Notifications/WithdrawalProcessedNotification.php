<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;

/**
 * F-173: Notification when cook's withdrawal is processed (success or failure).
 *
 * N-013: Withdrawal processed - Push + DB + Email (success).
 * N-014: Withdrawal failed - Push + DB (failure).
 * BR-365: Cook notification includes: amount, destination, status.
 */
class WithdrawalProcessedNotification extends BasePushNotification
{
    public function __construct(
        private WithdrawalRequest $withdrawal,
        private bool $success
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        if ($this->success) {
            return __('Withdrawal Sent');
        }

        return __('Withdrawal Failed');
    }

    /**
     * Get the push notification body text.
     *
     * BR-365: Includes amount, destination, status.
     */
    public function getBody(object $notifiable): string
    {
        $formattedAmount = number_format((float) $this->withdrawal->amount, 0, '.', ',').' XAF';
        $provider = $this->withdrawal->providerLabel();

        if ($this->success) {
            return __(':amount has been sent to your :provider account.', [
                'amount' => $formattedAmount,
                'provider' => $provider,
            ]);
        }

        return __('Withdrawal of :amount failed. The amount has been returned to your wallet.', [
            'amount' => $formattedAmount,
        ]);
    }

    /**
     * Get the URL the notification links to -- cook wallet dashboard.
     */
    public function getActionUrl(object $notifiable): string
    {
        $tenant = $this->withdrawal->tenant;
        if (! $tenant) {
            return url('/dashboard/wallet');
        }

        $domain = $tenant->domain ?? ($tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST));

        return 'https://'.$domain.'/dashboard/wallet';
    }

    /**
     * Get additional data payload.
     *
     * @return array<string, mixed>
     */
    public function getData(object $notifiable): array
    {
        return [
            'type' => $this->success ? 'withdrawal_processed' : 'withdrawal_failed',
            'withdrawal_id' => $this->withdrawal->id,
            'amount' => (float) $this->withdrawal->amount,
            'provider' => $this->withdrawal->mobile_money_provider,
            'success' => $this->success,
            'tenant_id' => $this->withdrawal->tenant_id,
        ];
    }

    /**
     * Get the notification tag for grouping.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'withdrawal-'.$this->withdrawal->id;
    }
}
