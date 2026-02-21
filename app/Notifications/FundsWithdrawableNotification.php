<?php

namespace App\Notifications;

use App\Models\Tenant;

/**
 * F-171: Notification when cook's funds become withdrawable.
 *
 * N-015: Amount withdrawable — Push + DB channels.
 * BR-337: Cook is notified when funds become withdrawable.
 * Scenario 4: Consolidated notification for multiple orders.
 */
class FundsWithdrawableNotification extends BasePushNotification
{
    /**
     * @param  array<string>  $orderNumbers
     */
    public function __construct(
        private float $amount,
        private int $orderCount,
        private array $orderNumbers,
        private Tenant $tenant
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        return __('Funds Now Withdrawable');
    }

    /**
     * Get the push notification body text.
     *
     * Scenario 1: Single order — "4,500 XAF from Order #ORD-1234 is now withdrawable."
     * Scenario 4: Multiple orders — "13,500 XAF from 3 orders is now withdrawable."
     */
    public function getBody(object $notifiable): string
    {
        $formattedAmount = number_format($this->amount, 0, '.', ',').' XAF';

        if ($this->orderCount === 1 && ! empty($this->orderNumbers)) {
            return __(':amount from Order #:order is now withdrawable.', [
                'amount' => $formattedAmount,
                'order' => $this->orderNumbers[0],
            ]);
        }

        return __(':amount from :count orders is now withdrawable.', [
            'amount' => $formattedAmount,
            'count' => $this->orderCount,
        ]);
    }

    /**
     * Get the URL the notification links to — cook wallet dashboard.
     */
    public function getActionUrl(object $notifiable): string
    {
        $domain = $this->tenant->domain ?? ($this->tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST));

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
            'type' => 'funds_withdrawable',
            'amount' => $this->amount,
            'order_count' => $this->orderCount,
            'order_numbers' => $this->orderNumbers,
            'tenant_id' => $this->tenant->id,
        ];
    }

    /**
     * Get the notification tag for grouping.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'funds-withdrawable-'.$this->tenant->id;
    }
}
