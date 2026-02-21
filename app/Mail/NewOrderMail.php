<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Tenant;
use App\Services\CookOrderService;

/**
 * F-191: New Order Email (N-001, BR-273)
 *
 * Email sent to the cook and managers when a new paid order arrives.
 * Contains full order detail: client name, all items with quantities
 * and prices, total, delivery/pickup choice, delivery address if
 * applicable, and order timestamp.
 *
 * BR-273: Full order detail in email.
 * BR-275: Includes a "View Order" button linking to cook dashboard.
 * BR-276: Queued via BaseMailableNotification.
 * BR-277: All text uses __() localization.
 */
class NewOrderMail extends BaseMailableNotification
{
    private Order $order;

    private Tenant $orderTenant;

    public function __construct(Order $order, Tenant $orderTenant)
    {
        $this->order = $order;
        $this->orderTenant = $orderTenant;

        $this->forTenant($orderTenant);
        $this->initializeMailable();
    }

    /**
     * Get the email subject line.
     *
     * BR-273: "New Order #[ID] -- [total] XAF"
     */
    protected function getSubjectLine(): string
    {
        return $this->trans('New Order :number - :amount', [
            'number' => $this->order->order_number,
            'amount' => $this->order->formattedGrandTotal(),
        ]);
    }

    /**
     * Get the blade view name for the email content.
     */
    protected function getEmailView(): string
    {
        return 'emails.new-order';
    }

    /**
     * Get the data to pass to the email view.
     *
     * BR-273: Full order detail including client name, all items
     * with quantities and prices, total, delivery/pickup choice,
     * delivery address if applicable, order timestamp.
     *
     * @return array<string, mixed>
     */
    protected function getEmailData(): array
    {
        $this->order->load(['client', 'town', 'quarter', 'pickupLocation']);

        $cookOrderService = app(CookOrderService::class);
        $items = $cookOrderService->parseOrderItems($this->order);

        $itemCount = 0;
        foreach ($items as $item) {
            $itemCount += $item['quantity'];
        }

        $deliveryLabel = $this->order->delivery_method === Order::METHOD_DELIVERY
            ? $this->trans('Delivery')
            : $this->trans('Pickup');

        // Build delivery address string
        $deliveryAddress = $this->buildDeliveryAddress();

        // BR-275: View Order URL links to cook dashboard order detail
        $viewOrderUrl = $this->orderTenant->getUrl().'/dashboard/orders/'.$this->order->id;

        return [
            'order' => $this->order,
            'items' => $items,
            'itemCount' => $itemCount,
            'clientName' => $this->order->client?->name ?? $this->trans('Customer'),
            'cookName' => $this->orderTenant->name ?? 'DancyMeals',
            'deliveryLabel' => $deliveryLabel,
            'deliveryAddress' => $deliveryAddress,
            'viewOrderUrl' => $viewOrderUrl,
            'orderDate' => $this->order->created_at?->format('M d, Y H:i') ?? '',
            'emailLocale' => $this->emailLocale,
        ];
    }

    /**
     * Build the delivery address string from order data.
     */
    private function buildDeliveryAddress(): string
    {
        if ($this->order->delivery_method === Order::METHOD_PICKUP) {
            $pickup = $this->order->pickupLocation;
            if ($pickup) {
                return $pickup->name ?? '';
            }

            return $this->trans('Pickup');
        }

        $parts = [];

        if ($this->order->neighbourhood) {
            $parts[] = $this->order->neighbourhood;
        }

        $quarter = $this->order->quarter;
        if ($quarter) {
            $parts[] = $quarter->name ?? '';
        }

        $town = $this->order->town;
        if ($town) {
            $parts[] = $town->name ?? '';
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
