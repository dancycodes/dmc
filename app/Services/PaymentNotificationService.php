<?php

namespace App\Services;

use App\Mail\PaymentReceiptMail;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * F-194: Payment Notification Service
 *
 * Central service for dispatching all payment-related notifications.
 *
 * BR-299: Payment success: client receives push + DB + email (receipt)
 * BR-300: Payment failure: client receives push + DB (no email) with retry prompt
 * BR-309: All notifications are queued to not block payment processing
 * BR-310: All notification text must use __() localization
 *
 * Note: Refund notifications (N-008, BR-301) are handled by WalletRefundService.
 * Note: Withdrawal notifications (N-013/N-014, BR-302/BR-303) are handled by
 *       FlutterwaveTransferService. Both delegate to their respective mail/notification
 *       classes that were established in F-167 and F-173 respectively.
 */
class PaymentNotificationService
{
    /**
     * BR-299: Notify the client of a successful payment.
     *
     * Sends push + DB + email (receipt).
     * Email failure is caught gracefully so push/DB always succeed.
     *
     * Called from:
     * - CheckoutController::sendPaymentNotifications() after wallet or Flutterwave payment
     * - WebhookService after successful Flutterwave charge.completed event
     */
    public function notifyPaymentSuccess(
        Order $order,
        Tenant $tenant,
        User $client,
        ?PaymentTransaction $transaction = null,
        bool $alreadyNotified = false
    ): void {
        if ($alreadyNotified) {
            return;
        }

        // Push + Database notification (N-006)
        $this->sendSuccessPushAndDb($order, $tenant, $client);

        // Email receipt (BR-299, BR-304)
        $this->sendReceiptEmail($order, $tenant, $client, $transaction);
    }

    /**
     * BR-300: Notify the client of a failed payment.
     *
     * Sends push + DB only (no email per BR-300).
     * Includes a retry prompt with order reference.
     *
     * Called from WebhookService after failed Flutterwave charge.completed event.
     */
    public function notifyPaymentFailed(
        Order $order,
        User $client,
        string $failureReason = ''
    ): void {
        try {
            $client->notify(new PaymentFailedNotification($order, $failureReason));
        } catch (\Throwable $e) {
            // Notification failure is non-fatal; the payment failure is already recorded
            Log::warning('F-194: PaymentFailed push/DB notification failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send the payment success push + DB notification to the client.
     *
     * N-006: Push + DB notification.
     */
    private function sendSuccessPushAndDb(Order $order, Tenant $tenant, User $client): void
    {
        try {
            $client->notify(new PaymentConfirmedNotification($order, $tenant));
        } catch (\Throwable $e) {
            Log::warning('F-194: PaymentConfirmed push/DB notification failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send the payment receipt email to the client.
     *
     * BR-299: Email acts as a receipt.
     * BR-304: Receipt includes order ID, items, amounts, payment method, transaction reference, date/time.
     * Email failure is caught gracefully — push/DB already delivered (BR-299 edge case).
     */
    private function sendReceiptEmail(
        Order $order,
        Tenant $tenant,
        User $client,
        ?PaymentTransaction $transaction
    ): void {
        if (empty($client->email)) {
            return;
        }

        try {
            Mail::to($client->email)
                ->queue(
                    (new PaymentReceiptMail($order, $tenant, $transaction))
                        ->forRecipient($client)
                        ->forTenant($tenant)
                );
        } catch (\Throwable $e) {
            // Email failure is non-fatal per BR-299 edge case
            Log::warning('F-194: PaymentReceipt email failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
