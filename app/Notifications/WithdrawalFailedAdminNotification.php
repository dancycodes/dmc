<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;

/**
 * F-173: Notification to admin when a cook's withdrawal fails.
 *
 * N-014: Withdrawal failed - Push + DB for admin.
 * BR-359: Admin notified on failure so they can handle via manual payout queue.
 */
class WithdrawalFailedAdminNotification extends BasePushNotification
{
    public function __construct(
        private WithdrawalRequest $withdrawal
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        return __('Withdrawal Failed - Manual Action Needed');
    }

    /**
     * Get the push notification body text.
     */
    public function getBody(object $notifiable): string
    {
        $formattedAmount = number_format((float) $this->withdrawal->amount, 0, '.', ',').' XAF';
        $cookName = $this->withdrawal->user?->name ?? __('Unknown Cook');

        return __('Withdrawal of :amount for :cook failed. Added to manual payout queue.', [
            'amount' => $formattedAmount,
            'cook' => $cookName,
        ]);
    }

    /**
     * Get the URL the notification links to -- admin manual payout queue.
     */
    public function getActionUrl(object $notifiable): string
    {
        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

        return 'https://'.$mainDomain.'/vault-entry/payouts';
    }

    /**
     * Get additional data payload.
     *
     * @return array<string, mixed>
     */
    public function getData(object $notifiable): array
    {
        return [
            'type' => 'withdrawal_failed_admin',
            'withdrawal_id' => $this->withdrawal->id,
            'amount' => (float) $this->withdrawal->amount,
            'cook_id' => $this->withdrawal->user_id,
            'tenant_id' => $this->withdrawal->tenant_id,
            'failure_reason' => $this->withdrawal->failure_reason,
        ];
    }

    /**
     * Get the notification tag for grouping.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'withdrawal-failed-admin-'.$this->withdrawal->id;
    }
}
