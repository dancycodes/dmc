<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\WalletTransaction;

/**
 * F-167: Refund Credited Push + Database Notification (N-008)
 *
 * Sent to the client when a refund is credited to their wallet.
 *
 * BR-295: Client is notified of the refund via push + DB + email.
 * BR-296: The notification includes the refund amount and the order reference.
 *
 * Email is handled separately by RefundCreditedMail.
 */
class RefundCreditedNotification extends BasePushNotification
{
    public function __construct(
        private Order $order,
        private float $amount,
        private WalletTransaction $transaction,
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        return __('Refund Credited!');
    }

    /**
     * Get the push notification body text.
     *
     * BR-296: Includes refund amount and order reference.
     */
    public function getBody(object $notifiable): string
    {
        $formattedAmount = number_format($this->amount, 0, '.', ',').' XAF';

        return __(':amount has been credited to your wallet for order :number.', [
            'amount' => $formattedAmount,
            'number' => $this->order->order_number,
        ]);
    }

    /**
     * Get the URL the notification links to.
     *
     * Links to the client's wallet dashboard.
     */
    public function getActionUrl(object $notifiable): string
    {
        return url('/my-wallet');
    }

    /**
     * Get additional data payload for the notification.
     *
     * @return array<string, mixed>
     */
    public function getData(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'amount' => $this->amount,
            'transaction_id' => $this->transaction->id,
            'type' => 'refund_credited',
        ];
    }

    /**
     * Get the notification tag for grouping/deduplication.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'refund-'.$this->order->id;
    }
}
