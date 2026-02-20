<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Services\PaymentReceiptService;

/**
 * F-154: Payment Receipt Email (N-006, BR-402)
 *
 * Email receipt sent to the client after successful payment.
 * Includes order summary, payment confirmation, transaction reference,
 * and a link to track the order.
 *
 * BR-402: An email receipt is sent to the client's registered email.
 * BR-407: Part of the 3-channel notification (push + database + email).
 */
class PaymentReceiptMail extends BaseMailableNotification
{
    private Order $order;

    private Tenant $orderTenant;

    private ?PaymentTransaction $transaction;

    public function __construct(Order $order, Tenant $orderTenant, ?PaymentTransaction $transaction = null)
    {
        $this->order = $order;
        $this->orderTenant = $orderTenant;
        $this->transaction = $transaction;

        $this->forTenant($orderTenant);
        $this->initializeMailable();
    }

    /**
     * Get the email subject line.
     */
    protected function getSubjectLine(): string
    {
        return $this->trans('Payment Receipt - Order :number', [
            'number' => $this->order->order_number,
        ]);
    }

    /**
     * Get the blade view name for the email content.
     */
    protected function getEmailView(): string
    {
        return 'emails.payment-receipt';
    }

    /**
     * Get the data to pass to the email view.
     *
     * @return array<string, mixed>
     */
    protected function getEmailData(): array
    {
        $receiptService = app(PaymentReceiptService::class);

        $items = $receiptService->parseItemsSnapshot($this->order->items_snapshot);
        $paymentLabel = $receiptService->getPaymentMethodLabel($this->order->payment_provider);
        $transactionReference = $receiptService->getTransactionReference($this->transaction, $this->order);

        $trackingUrl = $this->orderTenant->getUrl().'/checkout/payment/receipt/'.$this->order->id;

        return [
            'order' => $this->order,
            'items' => $items,
            'paymentLabel' => $paymentLabel,
            'transactionReference' => $transactionReference,
            'cookName' => $this->orderTenant->name ?? 'DancyMeals',
            'trackingUrl' => $trackingUrl,
            'emailLocale' => $this->emailLocale,
        ];
    }

    /**
     * Get the email type identifier for queue routing.
     * Payment receipts are important but not critical like password resets.
     */
    protected function getEmailType(): string
    {
        return 'general';
    }
}
