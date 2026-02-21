<?php

namespace App\Services;

use App\Mail\RefundCreditedMail;
use App\Models\ClientWallet;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\RefundCreditedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * F-167: Client Wallet Refund Credit
 *
 * Service layer for crediting refunds to client wallets.
 * Called by F-163 (Order Cancellation Refund) and F-061 (Admin Complaint Resolution).
 *
 * BR-290: Refund credits increase the client wallet balance by the refund amount.
 * BR-291: A wallet transaction record is created for each refund with type "refund".
 * BR-292: Transaction record includes amount, type, description, order reference, timestamp.
 * BR-293: If no wallet exists, one is created with the refund as initial balance.
 * BR-294: Wallet balance cannot go negative (refunds only add).
 * BR-297: Refund credit operations are atomic (database transaction).
 * BR-298: Refund credit is logged via Spatie Activitylog.
 */
class WalletRefundService
{
    /**
     * Refund source constants.
     */
    public const SOURCE_CANCELLATION = 'cancellation';

    public const SOURCE_COMPLAINT = 'complaint';

    /**
     * Credit a refund to the client's wallet.
     *
     * BR-290: Increases wallet balance by refund amount.
     * BR-291: Creates wallet transaction record with type "refund".
     * BR-293: Lazily creates wallet if none exists.
     * BR-294: Refunds only add (balance cannot go negative).
     * BR-297: Wrapped in a database transaction for atomicity.
     * BR-298: Logged via Spatie Activitylog.
     *
     * @param  array{source: string, complaint_id?: int}  $metadata  Additional context for the refund
     * @return array{wallet: ClientWallet, transaction: WalletTransaction}
     */
    public function creditRefund(
        User $client,
        float $amount,
        Order $order,
        string $source,
        string $description,
        array $metadata = []
    ): array {
        return DB::transaction(function () use ($client, $amount, $order, $source, $description, $metadata) {
            // BR-293: Get or lazily create the client wallet
            $wallet = ClientWallet::getOrCreateForUser($client);

            // Lock the wallet row for atomic update (prevent race conditions)
            $wallet = ClientWallet::query()
                ->where('id', $wallet->id)
                ->lockForUpdate()
                ->first();

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = $balanceBefore + $amount;

            // BR-290: Increment the wallet balance
            $wallet->update(['balance' => $balanceAfter]);

            // BR-291/BR-292: Create wallet transaction record
            $transaction = WalletTransaction::create([
                'user_id' => $client->id,
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'payment_transaction_id' => null,
                'type' => WalletTransaction::TYPE_REFUND,
                'amount' => $amount,
                'currency' => 'XAF',
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'is_withdrawable' => true,
                'withdrawable_at' => null,
                'status' => 'completed',
                'description' => $description,
                'metadata' => array_merge([
                    'source' => $source,
                    'order_number' => $order->order_number,
                ], $metadata),
            ]);

            // BR-298: Log via Spatie Activitylog
            activity('client_wallets')
                ->causedBy($client)
                ->performedOn($wallet)
                ->withProperties([
                    'refund_amount' => $amount,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'source' => $source,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'transaction_id' => $transaction->id,
                ])
                ->log('refund_credited');

            // BR-295: Send notifications (push + DB + email) after commit
            $this->dispatchNotifications($client, $amount, $order, $transaction);

            return [
                'wallet' => $wallet->fresh(),
                'transaction' => $transaction,
            ];
        });
    }

    /**
     * Credit a refund from order cancellation (F-163).
     *
     * @return array{wallet: ClientWallet, transaction: WalletTransaction}
     */
    public function creditCancellationRefund(User $client, float $amount, Order $order): array
    {
        $description = __('Refund for cancelled order :number', [
            'number' => $order->order_number,
        ]);

        return $this->creditRefund(
            $client,
            $amount,
            $order,
            self::SOURCE_CANCELLATION,
            $description,
        );
    }

    /**
     * Credit a refund from complaint resolution (F-061).
     *
     * @return array{wallet: ClientWallet, transaction: WalletTransaction}
     */
    public function creditComplaintRefund(
        User $client,
        float $amount,
        Order $order,
        int $complaintId
    ): array {
        $description = __('Complaint resolution refund for order :number', [
            'number' => $order->order_number,
        ]);

        return $this->creditRefund(
            $client,
            $amount,
            $order,
            self::SOURCE_COMPLAINT,
            $description,
            ['complaint_id' => $complaintId],
        );
    }

    /**
     * BR-295/BR-296: Dispatch refund notifications to the client.
     *
     * Push + Database notification via BasePushNotification.
     * Email via RefundCreditedMail.
     */
    private function dispatchNotifications(
        User $client,
        float $amount,
        Order $order,
        WalletTransaction $transaction
    ): void {
        // Push + Database notification (N-008)
        $client->notify(new RefundCreditedNotification($order, $amount, $transaction));

        // Email notification
        $tenant = $order->tenant;
        Mail::to($client->email)
            ->queue(
                (new RefundCreditedMail($order, $amount, $tenant))
                    ->forRecipient($client)
                    ->forTenant($tenant)
            );
    }

    /**
     * Format an amount in XAF.
     */
    public static function formatXAF(float $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }
}
