<?php

namespace App\Notifications;

use App\Models\Order;

/**
 * F-194: Payment Failed Push + Database Notification (N-007)
 *
 * Sent to the client when a payment attempt fails.
 *
 * BR-300: Payment failure: client receives push + DB (no email) with retry prompt.
 * BR-307: Notification includes a "Retry Payment" link valid for 15 minutes.
 * BR-308: All payment amounts are formatted in XAF.
 * BR-309: Notifications are queued to not block payment processing.
 */
class PaymentFailedNotification extends BasePushNotification
{
    public function __construct(
        private Order $order,
        private string $failureReason = '',
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        return __('Payment Failed');
    }

    /**
     * Get the push notification body text.
     *
     * BR-300: Includes order reference and retry prompt.
     */
    public function getBody(object $notifiable): string
    {
        return __('Payment failed for order :number. Please try again.', [
            'number' => $this->order->order_number,
        ]);
    }

    /**
     * Get the URL the notification links to — order retry page.
     *
     * BR-307: Links to the payment retry page.
     */
    public function getActionUrl(object $notifiable): string
    {
        $tenant = $this->order->tenant;

        if ($tenant) {
            $tenantUrl = $tenant->getUrl();

            return $tenantUrl.'/checkout/payment/retry/'.$this->order->id;
        }

        return url('/my-orders/'.$this->order->id);
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
            'amount' => $this->order->grand_total,
            'tenant_id' => $this->order->tenant_id,
            'failure_reason' => $this->failureReason,
            'type' => 'payment_failed',
        ];
    }

    /**
     * Get the notification tag for grouping/deduplication.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'payment-failed-'.$this->order->id;
    }
}
