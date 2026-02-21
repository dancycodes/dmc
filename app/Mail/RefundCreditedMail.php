<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Tenant;

/**
 * F-167: Refund Credited Email (N-008, BR-295)
 *
 * Email sent to the client when a refund is credited to their wallet.
 * Includes refund amount, order reference, and a link to the wallet.
 *
 * BR-295: Client notified via push + DB + email.
 * BR-296: Notification includes refund amount and order reference.
 */
class RefundCreditedMail extends BaseMailableNotification
{
    private Order $order;

    private float $amount;

    private ?Tenant $orderTenant;

    public function __construct(Order $order, float $amount, ?Tenant $orderTenant = null)
    {
        $this->order = $order;
        $this->amount = $amount;
        $this->orderTenant = $orderTenant;

        $this->forTenant($orderTenant);
        $this->initializeMailable();
    }

    /**
     * Get the email subject line.
     */
    protected function getSubjectLine(): string
    {
        return $this->trans('Refund Credited - Order :number', [
            'number' => $this->order->order_number,
        ]);
    }

    /**
     * Get the blade view name for the email content.
     */
    protected function getEmailView(): string
    {
        return 'emails.refund-credited';
    }

    /**
     * Get the data to pass to the email view.
     *
     * @return array<string, mixed>
     */
    protected function getEmailData(): array
    {
        $formattedAmount = number_format($this->amount, 0, '.', ',').' XAF';
        $cookName = $this->orderTenant?->name ?? 'DancyMeals';
        $walletUrl = url('/my-wallet');

        return [
            'order' => $this->order,
            'refundAmount' => $formattedAmount,
            'cookName' => $cookName,
            'walletUrl' => $walletUrl,
            'emailLocale' => $this->emailLocale,
        ];
    }

    /**
     * Get the email type identifier for queue routing.
     * Refund emails are important but not critical.
     */
    protected function getEmailType(): string
    {
        return 'general';
    }
}
