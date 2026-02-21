<?php

namespace App\Services;

use App\Mail\WithdrawalProcessedMail;
use App\Models\CookWallet;
use App\Models\PayoutTask;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Notifications\WithdrawalFailedAdminNotification;
use App\Notifications\WithdrawalProcessedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * F-173: Flutterwave Transfer Execution
 *
 * Service layer for executing cook withdrawals via Flutterwave Transfer API.
 * BR-356: Withdrawals executed via Flutterwave Transfer API to mobile money.
 * BR-358: On success: completed, wallet transaction, cook notified.
 * BR-359: On failure: failed, balance restored, cook + admin notified, manual payout task.
 * BR-360: Timeout: pending_verification, follow-up check.
 * BR-361: All transfer attempts logged for audit.
 * BR-364: Idempotent processing.
 */
class FlutterwaveTransferService
{
    public function __construct(
        private FlutterwaveService $flutterwaveService
    ) {}

    /**
     * Process a single pending withdrawal request.
     *
     * BR-364: Idempotent - checks status before processing.
     * BR-361: All attempts are logged.
     *
     * @return array{success: bool, message: string, status: string}
     */
    public function processWithdrawal(WithdrawalRequest $withdrawal): array
    {
        // BR-364: Idempotency check - only process pending withdrawals
        if (! $withdrawal->canBeProcessed()) {
            Log::info('Withdrawal already processed, skipping', [
                'withdrawal_id' => $withdrawal->id,
                'status' => $withdrawal->status,
            ]);

            return [
                'success' => false,
                'message' => __('Withdrawal has already been processed.'),
                'status' => $withdrawal->status,
            ];
        }

        // Mark as processing
        $withdrawal->update([
            'status' => WithdrawalRequest::STATUS_PROCESSING,
            'processed_at' => now(),
        ]);

        // Generate idempotency key
        $idempotencyKey = $withdrawal->generateIdempotencyKey();
        $withdrawal->update(['idempotency_key' => $idempotencyKey]);

        // Generate transfer reference
        $reference = 'DMC-WD-'.$withdrawal->id.'-'.time();

        // BR-361: Log the transfer attempt
        activity('withdrawal_requests')
            ->performedOn($withdrawal)
            ->withProperties([
                'action' => 'transfer_initiated',
                'amount' => (float) $withdrawal->amount,
                'phone' => $withdrawal->mobile_money_number,
                'provider' => $withdrawal->mobile_money_provider,
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
            ])
            ->log('Flutterwave transfer initiated');

        // Call Flutterwave Transfer API
        $result = $this->flutterwaveService->initiateTransfer([
            'amount' => (float) $withdrawal->amount,
            'currency' => $withdrawal->currency,
            'phone' => $withdrawal->mobile_money_number,
            'provider' => $withdrawal->mobile_money_provider,
            'reference' => $reference,
            'narration' => __('DancyMeals Payout - :amount XAF', ['amount' => number_format((float) $withdrawal->amount)]),
            'idempotency_key' => $idempotencyKey,
        ]);

        // Store the Flutterwave reference and response
        $withdrawal->update([
            'flutterwave_reference' => $reference,
            'flutterwave_transfer_id' => $result['data']['id'] ?? null,
            'flutterwave_response' => $result['data'] ?? $result,
        ]);

        if ($result['success']) {
            return $this->handleTransferSuccess($withdrawal, $result);
        }

        if ($result['is_timeout'] ?? false) {
            return $this->handleTransferTimeout($withdrawal, $result);
        }

        return $this->handleTransferFailure($withdrawal, $result);
    }

    /**
     * Handle a successful transfer.
     *
     * BR-358: Withdrawal marked completed, wallet transaction created, cook notified.
     *
     * @return array{success: bool, message: string, status: string}
     */
    private function handleTransferSuccess(WithdrawalRequest $withdrawal, array $result): array
    {
        DB::transaction(function () use ($withdrawal, $result) {
            // BR-358: Mark withdrawal as completed
            $withdrawal->update([
                'status' => WithdrawalRequest::STATUS_COMPLETED,
                'completed_at' => now(),
                'flutterwave_transfer_id' => $result['data']['id'] ?? $withdrawal->flutterwave_transfer_id,
            ]);

            // BR-361: Log successful transfer
            activity('withdrawal_requests')
                ->performedOn($withdrawal)
                ->withProperties([
                    'action' => 'transfer_completed',
                    'amount' => (float) $withdrawal->amount,
                    'flutterwave_transfer_id' => $result['data']['id'] ?? null,
                    'reference' => $withdrawal->flutterwave_reference,
                ])
                ->log('Flutterwave transfer completed successfully');
        });

        // BR-358: Notify the cook (push + DB + email)
        $this->notifyCookSuccess($withdrawal);

        return [
            'success' => true,
            'message' => __('Transfer completed successfully.'),
            'status' => WithdrawalRequest::STATUS_COMPLETED,
        ];
    }

    /**
     * Handle a transfer timeout.
     *
     * BR-360: Marked as pending_verification, follow-up job re-checks.
     *
     * @return array{success: bool, message: string, status: string}
     */
    private function handleTransferTimeout(WithdrawalRequest $withdrawal, array $result): array
    {
        $withdrawal->update([
            'status' => WithdrawalRequest::STATUS_PENDING_VERIFICATION,
        ]);

        // BR-361: Log timeout
        activity('withdrawal_requests')
            ->performedOn($withdrawal)
            ->withProperties([
                'action' => 'transfer_timeout',
                'amount' => (float) $withdrawal->amount,
                'reference' => $withdrawal->flutterwave_reference,
                'error' => $result['error'] ?? 'Connection timeout',
            ])
            ->log('Flutterwave transfer timed out - pending verification');

        return [
            'success' => false,
            'message' => __('Transfer timed out. Status will be verified shortly.'),
            'status' => WithdrawalRequest::STATUS_PENDING_VERIFICATION,
        ];
    }

    /**
     * Handle a transfer failure.
     *
     * BR-359: Withdrawal failed, balance restored, cook + admin notified, manual payout task.
     *
     * @return array{success: bool, message: string, status: string}
     */
    private function handleTransferFailure(WithdrawalRequest $withdrawal, array $result): array
    {
        $failureReason = $result['error'] ?? __('Unknown error');

        DB::transaction(function () use ($withdrawal, $failureReason, $result) {
            // BR-359: Mark withdrawal as failed
            $withdrawal->update([
                'status' => WithdrawalRequest::STATUS_FAILED,
                'failed_at' => now(),
                'failure_reason' => $failureReason,
            ]);

            // BR-359: Restore the withdrawable balance
            $this->restoreWithdrawableBalance($withdrawal);

            // BR-363: Create a manual payout task for admin
            $this->createManualPayoutTask($withdrawal, $failureReason);

            // BR-361: Log the failure
            activity('withdrawal_requests')
                ->performedOn($withdrawal)
                ->withProperties([
                    'action' => 'transfer_failed',
                    'amount' => (float) $withdrawal->amount,
                    'reference' => $withdrawal->flutterwave_reference,
                    'failure_reason' => $failureReason,
                    'flutterwave_response' => $result['data'] ?? null,
                ])
                ->log('Flutterwave transfer failed');
        });

        // BR-359: Notify cook and admin
        $this->notifyCookFailure($withdrawal);
        $this->notifyAdminFailure($withdrawal);

        return [
            'success' => false,
            'message' => $failureReason,
            'status' => WithdrawalRequest::STATUS_FAILED,
        ];
    }

    /**
     * Verify a pending_verification transfer with Flutterwave.
     *
     * BR-360: Follow-up check for timed-out transfers.
     *
     * @return array{success: bool, message: string, status: string}
     */
    public function verifyPendingTransfer(WithdrawalRequest $withdrawal): array
    {
        if (! $withdrawal->isPendingVerification()) {
            return [
                'success' => false,
                'message' => __('Withdrawal is not pending verification.'),
                'status' => $withdrawal->status,
            ];
        }

        $transferId = $withdrawal->flutterwave_transfer_id;

        // If no transfer ID, we cannot verify - treat as failure
        if (! $transferId) {
            return $this->handleTransferFailure($withdrawal, [
                'success' => false,
                'error' => __('No Flutterwave transfer ID available for verification.'),
                'data' => null,
            ]);
        }

        $result = $this->flutterwaveService->verifyTransfer($transferId);

        // Update the stored response
        $withdrawal->update([
            'flutterwave_response' => $result['data'] ?? $withdrawal->flutterwave_response,
        ]);

        $transferStatus = strtoupper($result['status'] ?? '');

        // BR-360: Resolve based on Flutterwave's response
        if (in_array($transferStatus, ['SUCCESSFUL', 'COMPLETED'])) {
            activity('withdrawal_requests')
                ->performedOn($withdrawal)
                ->withProperties([
                    'action' => 'verification_confirmed',
                    'flutterwave_status' => $transferStatus,
                ])
                ->log('Pending transfer verified as successful');

            return $this->handleTransferSuccess($withdrawal, $result);
        }

        if (in_array($transferStatus, ['FAILED', 'REVERSED'])) {
            activity('withdrawal_requests')
                ->performedOn($withdrawal)
                ->withProperties([
                    'action' => 'verification_failed',
                    'flutterwave_status' => $transferStatus,
                ])
                ->log('Pending transfer verified as failed');

            return $this->handleTransferFailure($withdrawal, [
                'success' => false,
                'error' => __('Transfer failed after verification: :status', ['status' => $transferStatus]),
                'data' => $result['data'],
            ]);
        }

        // Still pending at Flutterwave - keep as pending_verification
        activity('withdrawal_requests')
            ->performedOn($withdrawal)
            ->withProperties([
                'action' => 'verification_still_pending',
                'flutterwave_status' => $transferStatus ?: 'UNKNOWN',
            ])
            ->log('Pending transfer still not resolved');

        return [
            'success' => false,
            'message' => __('Transfer status still pending at Flutterwave.'),
            'status' => WithdrawalRequest::STATUS_PENDING_VERIFICATION,
        ];
    }

    /**
     * Process all pending withdrawal requests.
     *
     * Scenario 4: Multiple withdrawals processed sequentially.
     *
     * @return array{processed: int, succeeded: int, failed: int, timeouts: int, skipped: int}
     */
    public function processAllPending(): array
    {
        $pending = WithdrawalRequest::query()
            ->where('status', WithdrawalRequest::STATUS_PENDING)
            ->orderBy('requested_at', 'asc')
            ->get();

        $stats = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'timeouts' => 0,
            'skipped' => 0,
        ];

        foreach ($pending as $withdrawal) {
            // Refresh to get latest status (idempotency)
            $withdrawal->refresh();

            if (! $withdrawal->canBeProcessed()) {
                $stats['skipped']++;

                continue;
            }

            $result = $this->processWithdrawal($withdrawal);
            $stats['processed']++;

            match ($result['status']) {
                WithdrawalRequest::STATUS_COMPLETED => $stats['succeeded']++,
                WithdrawalRequest::STATUS_FAILED => $stats['failed']++,
                WithdrawalRequest::STATUS_PENDING_VERIFICATION => $stats['timeouts']++,
                default => null,
            };
        }

        return $stats;
    }

    /**
     * Verify all pending_verification transfers.
     *
     * BR-360: Follow-up job re-checks status.
     *
     * @return array{verified: int, completed: int, failed: int, still_pending: int}
     */
    public function verifyAllPending(): array
    {
        $pendingVerification = WithdrawalRequest::query()
            ->where('status', WithdrawalRequest::STATUS_PENDING_VERIFICATION)
            ->orderBy('processed_at', 'asc')
            ->get();

        $stats = [
            'verified' => 0,
            'completed' => 0,
            'failed' => 0,
            'still_pending' => 0,
        ];

        foreach ($pendingVerification as $withdrawal) {
            $result = $this->verifyPendingTransfer($withdrawal);
            $stats['verified']++;

            match ($result['status']) {
                WithdrawalRequest::STATUS_COMPLETED => $stats['completed']++,
                WithdrawalRequest::STATUS_FAILED => $stats['failed']++,
                WithdrawalRequest::STATUS_PENDING_VERIFICATION => $stats['still_pending']++,
                default => null,
            };
        }

        return $stats;
    }

    /**
     * Restore the withdrawable balance on failure.
     *
     * BR-359: On failure, the amount is restored to the cook's withdrawable balance.
     */
    private function restoreWithdrawableBalance(WithdrawalRequest $withdrawal): void
    {
        $wallet = CookWallet::query()
            ->where('id', $withdrawal->cook_wallet_id)
            ->lockForUpdate()
            ->firstOrFail();

        $amount = (float) $withdrawal->amount;

        $wallet->update([
            'withdrawable_balance' => (float) $wallet->withdrawable_balance + $amount,
            'total_balance' => (float) $wallet->total_balance + $amount,
        ]);

        // Update the existing wallet transaction to reflect the reversal
        $existingTransaction = WalletTransaction::query()
            ->where('user_id', $withdrawal->user_id)
            ->where('tenant_id', $withdrawal->tenant_id)
            ->where('type', WalletTransaction::TYPE_WITHDRAWAL)
            ->where('status', 'completed')
            ->whereJsonContains('metadata->withdrawal_request_id', $withdrawal->id)
            ->first();

        if ($existingTransaction) {
            $existingTransaction->update([
                'status' => 'reversed',
                'description' => __('Withdrawal reversed - transfer failed'),
            ]);
        }

        // Create a reversal transaction entry
        WalletTransaction::create([
            'user_id' => $withdrawal->user_id,
            'tenant_id' => $withdrawal->tenant_id,
            'type' => WalletTransaction::TYPE_REFUND,
            'amount' => $amount,
            'currency' => $withdrawal->currency,
            'balance_before' => (float) $wallet->withdrawable_balance - $amount,
            'balance_after' => (float) $wallet->withdrawable_balance,
            'is_withdrawable' => true,
            'status' => 'completed',
            'description' => __('Withdrawal reversal - transfer failed'),
            'metadata' => [
                'withdrawal_request_id' => $withdrawal->id,
                'reason' => 'transfer_failed',
            ],
        ]);
    }

    /**
     * Create a manual payout task for the admin.
     *
     * BR-363: Failed transfers create a task in the admin manual payout queue.
     */
    private function createManualPayoutTask(WithdrawalRequest $withdrawal, string $failureReason): void
    {
        // Map provider names from withdrawal to PayoutTask format
        $paymentMethod = match ($withdrawal->mobile_money_provider) {
            WithdrawalRequest::PROVIDER_MTN_MOMO => 'mtn_mobile_money',
            WithdrawalRequest::PROVIDER_ORANGE_MONEY => 'orange_money',
            default => 'mtn_mobile_money',
        };

        PayoutTask::create([
            'cook_id' => $withdrawal->user_id,
            'tenant_id' => $withdrawal->tenant_id,
            'amount' => $withdrawal->amount,
            'currency' => $withdrawal->currency,
            'mobile_money_number' => $withdrawal->mobile_money_number,
            'payment_method' => $paymentMethod,
            'failure_reason' => $failureReason,
            'flutterwave_reference' => $withdrawal->flutterwave_reference,
            'flutterwave_transfer_id' => $withdrawal->flutterwave_transfer_id,
            'flutterwave_response' => $withdrawal->flutterwave_response,
            'status' => PayoutTask::STATUS_PENDING,
            'retry_count' => 0,
            'requested_at' => $withdrawal->requested_at,
        ]);
    }

    /**
     * Notify the cook of a successful withdrawal.
     *
     * N-013: Withdrawal processed - Push + DB + Email.
     * BR-365: Notification includes amount, destination, status.
     */
    private function notifyCookSuccess(WithdrawalRequest $withdrawal): void
    {
        try {
            $cook = $withdrawal->user;
            if (! $cook) {
                return;
            }

            // Push + DB notification
            $cook->notify(new WithdrawalProcessedNotification(
                withdrawal: $withdrawal,
                success: true
            ));

            // Email notification
            Mail::to($cook->email)
                ->queue(
                    (new WithdrawalProcessedMail($withdrawal, true))
                        ->forRecipient($cook)
                        ->forTenant($withdrawal->tenant)
                );
        } catch (\Exception $e) {
            // BR-Edge: Transfer succeeds but notification fails
            Log::error('Failed to send withdrawal success notification', [
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify the cook of a failed withdrawal.
     *
     * N-014: Withdrawal failed - Push + DB.
     * BR-365: Notification includes amount, destination, status.
     */
    private function notifyCookFailure(WithdrawalRequest $withdrawal): void
    {
        try {
            $cook = $withdrawal->user;
            if (! $cook) {
                return;
            }

            $cook->notify(new WithdrawalProcessedNotification(
                withdrawal: $withdrawal,
                success: false
            ));
        } catch (\Exception $e) {
            Log::error('Failed to send withdrawal failure notification', [
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify admins of a failed withdrawal.
     *
     * N-014: Withdrawal failed - Push + DB for admin.
     */
    private function notifyAdminFailure(WithdrawalRequest $withdrawal): void
    {
        try {
            $admins = User::role(['super-admin', 'admin'])->get();

            foreach ($admins as $admin) {
                $admin->notify(new WithdrawalFailedAdminNotification($withdrawal));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send admin withdrawal failure notification', [
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
