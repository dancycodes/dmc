<?php

namespace App\Services;

use App\Models\PayoutTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutService
{
    public function __construct(
        private FlutterwaveService $flutterwaveService
    ) {}

    /**
     * Mark a payout task as manually completed.
     *
     * BR-200: Requires reference number as proof of manual payment.
     * BR-204: Action is logged in activity log.
     * BR-205: Task moves to completed tab.
     *
     * @param  array<string, mixed>  $data
     */
    public function markAsManuallyCompleted(PayoutTask $task, array $data, User $admin): PayoutTask
    {
        return DB::transaction(function () use ($task, $data, $admin) {
            $task->update([
                'status' => PayoutTask::STATUS_MANUALLY_COMPLETED,
                'reference_number' => $data['reference_number'],
                'resolution_notes' => $data['resolution_notes'] ?? null,
                'completed_by' => $admin->id,
                'completed_at' => now(),
            ]);

            // BR-204: Activity logging handled automatically by LogsActivityTrait
            // Additional explicit log for audit trail
            activity('payout_tasks')
                ->performedOn($task)
                ->causedBy($admin)
                ->withProperties([
                    'action' => 'manually_completed',
                    'reference_number' => $data['reference_number'],
                    'amount' => $task->amount,
                    'cook_id' => $task->cook_id,
                ])
                ->log('Payout task manually completed');

            return $task->fresh();
        });
    }

    /**
     * Retry a failed Flutterwave transfer.
     *
     * BR-201: Retry initiates a new Flutterwave transfer with same parameters.
     * BR-202: Maximum 3 automatic retry attempts.
     * BR-204: Action is logged in activity log.
     *
     * @return array{success: bool, message: string}
     */
    public function retryTransfer(PayoutTask $task, User $admin): array
    {
        // BR-202: Check retry limit
        if (! $task->canRetry()) {
            return [
                'success' => false,
                'message' => __('Maximum retry attempts reached. Please use manual completion.'),
            ];
        }

        // Attempt Flutterwave transfer
        $transferResult = $this->initiateFlutterwaveTransfer($task);

        if ($transferResult['success']) {
            // Transfer succeeded - mark task as completed
            $task->update([
                'status' => PayoutTask::STATUS_COMPLETED,
                'retry_count' => $task->retry_count + 1,
                'last_retry_at' => now(),
                'completed_at' => now(),
                'completed_by' => $admin->id,
                'flutterwave_response' => $transferResult['response'] ?? null,
            ]);

            activity('payout_tasks')
                ->performedOn($task)
                ->causedBy($admin)
                ->withProperties([
                    'action' => 'retry_success',
                    'retry_count' => $task->retry_count,
                    'amount' => $task->amount,
                ])
                ->log('Payout retry successful');

            return [
                'success' => true,
                'message' => __('Transfer retry successful. The payout has been completed.'),
            ];
        }

        // Transfer failed - update retry count and failure reason
        $task->update([
            'retry_count' => $task->retry_count + 1,
            'last_retry_at' => now(),
            'failure_reason' => $transferResult['message'] ?? $task->failure_reason,
            'flutterwave_response' => $transferResult['response'] ?? $task->flutterwave_response,
        ]);

        activity('payout_tasks')
            ->performedOn($task)
            ->causedBy($admin)
            ->withProperties([
                'action' => 'retry_failed',
                'retry_count' => $task->retry_count,
                'error' => $transferResult['message'],
            ])
            ->log('Payout retry failed');

        $retriesRemaining = PayoutTask::MAX_RETRIES - $task->retry_count;

        return [
            'success' => false,
            'message' => __('Transfer retry failed: :reason. :remaining retry attempts remaining.', [
                'reason' => $transferResult['message'] ?? __('Unknown error'),
                'remaining' => $retriesRemaining,
            ]),
        ];
    }

    /**
     * Initiate a Flutterwave transfer via FlutterwaveService.
     *
     * F-173: Uses FlutterwaveService.initiateTransfer() for actual API calls.
     *
     * @return array{success: bool, message: string, response: array<string, mixed>|null}
     */
    private function initiateFlutterwaveTransfer(PayoutTask $task): array
    {
        // Map PayoutTask payment_method to withdrawal provider format
        $provider = match ($task->payment_method) {
            'mtn_mobile_money' => 'mtn_momo',
            'orange_money' => 'orange_money',
            default => 'mtn_momo',
        };

        $reference = 'DMC-RETRY-'.$task->id.'-'.time();

        $result = $this->flutterwaveService->initiateTransfer([
            'amount' => (float) $task->amount,
            'currency' => $task->currency,
            'phone' => $task->mobile_money_number,
            'provider' => $provider,
            'reference' => $reference,
            'narration' => 'DancyMeals Payout - Retry #'.($task->retry_count + 1),
            'idempotency_key' => $reference,
        ]);

        return [
            'success' => $result['success'],
            'message' => $result['error'] ?? ($result['success'] ? __('Transfer successful') : __('Transfer failed')),
            'response' => $result['data'],
        ];
    }

    /**
     * Get the count of pending payout tasks.
     *
     * BR-206: Used for sidebar badge count.
     */
    public function getPendingCount(): int
    {
        return PayoutTask::pending()->count();
    }
}
