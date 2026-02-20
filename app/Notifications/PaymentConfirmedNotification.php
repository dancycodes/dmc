<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Tenant;

/**
 * F-154: Payment Confirmed Push Notification (N-006)
 *
 * Sent to the client after a successful payment.
 *
 * BR-400: A push notification is sent to the client confirming payment.
 * BR-407: Uses all three channels: push, database (in-app), and email.
 *         Email is handled separately by PaymentReceiptMail.
 */
class PaymentConfirmedNotification extends BasePushNotification
{
    public function __construct(
        private Order $order,
        private Tenant $tenant,
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        return __('Payment Confirmed!');
    }

    /**
     * Get the push notification body text.
     *
     * BR-400: "Payment confirmed! Your order #DM-2024-0045 is now being processed by Chef Latifa."
     */
    public function getBody(object $notifiable): string
    {
        return __('Payment confirmed! Your order :number is now being processed by :cook.', [
            'number' => $this->order->order_number,
            'cook' => $this->tenant->name ?? 'DancyMeals',
        ]);
    }

    /**
     * Get the URL the notification links to.
     *
     * BR-404: Links to the order tracking page.
     */
    public function getActionUrl(object $notifiable): string
    {
        $tenantUrl = $this->tenant->getUrl();

        return $tenantUrl.'/checkout/payment/receipt/'.$this->order->id;
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
            'tenant_id' => $this->tenant->id,
            'type' => 'payment_confirmed',
        ];
    }

    /**
     * Get the notification tag for grouping.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'payment-'.$this->order->id;
    }
}
