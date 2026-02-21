<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Tenant;

/**
 * F-192: Order Status Update Email (N-002, BR-279)
 *
 * Email sent to the client for key order status transitions:
 * Confirmed, Ready for Pickup, Out for Delivery, Delivered, Completed.
 *
 * BR-279: Email only for key statuses (not Preparing, not Paid).
 * BR-283: Delivered/Completed email includes a "Rate Your Order" CTA.
 * BR-286: Email includes a "View Order" button linking to the client's order detail.
 * BR-287: Queued via BaseMailableNotification.
 * BR-288: All text uses __() localization.
 */
class OrderStatusUpdateMail extends BaseMailableNotification
{
    /**
     * Key statuses that trigger an email notification.
     *
     * BR-279: Email notifications only for these statuses.
     *
     * @var array<string>
     */
    public const EMAIL_STATUSES = [
        Order::STATUS_CONFIRMED,
        Order::STATUS_READY_FOR_PICKUP,
        Order::STATUS_OUT_FOR_DELIVERY,
        Order::STATUS_DELIVERED,
        Order::STATUS_PICKED_UP,
        Order::STATUS_COMPLETED,
    ];

    private Order $order;

    private Tenant $orderTenant;

    private string $newStatus;

    public function __construct(Order $order, Tenant $orderTenant, string $newStatus)
    {
        $this->order = $order;
        $this->orderTenant = $orderTenant;
        $this->newStatus = $newStatus;

        $this->forTenant($orderTenant);
        $this->initializeMailable();
    }

    /**
     * Determine if an email should be sent for this status.
     *
     * BR-279: Email notifications are sent only for key statuses.
     */
    public static function shouldSendEmailForStatus(string $status): bool
    {
        return in_array($status, self::EMAIL_STATUSES, true);
    }

    /**
     * Get the email subject line.
     *
     * Subject varies by status (BR-280 / UI/UX notes).
     */
    protected function getSubjectLine(): string
    {
        $number = $this->order->order_number;

        return match ($this->newStatus) {
            Order::STATUS_CONFIRMED => $this->trans('Order :number Confirmed', ['number' => $number]),
            Order::STATUS_READY_FOR_PICKUP => $this->trans('Order :number Ready for Pickup', ['number' => $number]),
            Order::STATUS_OUT_FOR_DELIVERY => $this->trans('Order :number Out for Delivery', ['number' => $number]),
            Order::STATUS_DELIVERED => $this->trans('Order :number Delivered', ['number' => $number]),
            Order::STATUS_PICKED_UP => $this->trans('Order :number Picked Up', ['number' => $number]),
            Order::STATUS_COMPLETED => $this->trans('Order :number Completed', ['number' => $number]),
            default => $this->trans('Order :number Updated', ['number' => $number]),
        };
    }

    /**
     * Get the blade view name for the email content.
     */
    protected function getEmailView(): string
    {
        return 'emails.order-status-update';
    }

    /**
     * Get the data to pass to the email view.
     *
     * @return array<string, mixed>
     */
    protected function getEmailData(): array
    {
        $this->order->load(['client', 'pickupLocation', 'town', 'quarter']);

        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
        $viewOrderUrl = 'https://'.$mainDomain.'/my-orders/'.$this->order->id;

        // BR-283: Delivered/Completed includes rate/review prompt
        $isRateable = in_array($this->newStatus, [
            Order::STATUS_DELIVERED,
            Order::STATUS_PICKED_UP,
            Order::STATUS_COMPLETED,
        ], true);

        $rateOrderUrl = $isRateable
            ? 'https://'.$mainDomain.'/my-orders/'.$this->order->id
            : null;

        return [
            'order' => $this->order,
            'newStatus' => $this->newStatus,
            'statusLabel' => Order::getStatusLabel($this->newStatus),
            'clientName' => $this->order->client?->name ?? $this->trans('Customer'),
            'cookName' => $this->orderTenant->name ?? 'DancyMeals',
            'viewOrderUrl' => $viewOrderUrl,
            'isRateable' => $isRateable,
            'rateOrderUrl' => $rateOrderUrl,
            'pickupDetails' => $this->getPickupDetails(),
            'emailLocale' => $this->emailLocale,
        ];
    }

    /**
     * Get pickup location details for Ready for Pickup emails.
     */
    private function getPickupDetails(): string
    {
        if ($this->order->delivery_method !== Order::METHOD_PICKUP) {
            return '';
        }

        $pickup = $this->order->pickupLocation;
        if (! $pickup) {
            return '';
        }

        $parts = [];

        if ($pickup->name) {
            $parts[] = $pickup->name;
        }

        if ($pickup->address) {
            $parts[] = $pickup->address;
        }

        return implode(', ', array_filter($parts));
    }

    /**
     * Get the email type identifier for queue routing.
     */
    protected function getEmailType(): string
    {
        return 'general';
    }
}
