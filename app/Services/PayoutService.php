<?php

namespace App\Services;

use App\Models\PayoutTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayoutService
{
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
     * Initiate a Flutterwave transfer.
     *
     * This is a stub that will be fully implemented when the Flutterwave
     * Transfer API integration is built (F-173). For now, it simulates
     * the transfer attempt.
     *
     * @return array{success: bool, message: string, response: array<string, mixed>|null}
     */
    private function initiateFlutterwaveTransfer(PayoutTask $task): array
    {
        try {
            $apiKey = config('flutterwave.secret_key');

            // If no API key configured, treat as API down
            if (! $apiKey) {
                return [
                    'success' => false,
                    'message' => __('Flutterwave API key not configured'),
                    'response' => ['error' => 'API key not configured'],
                ];
            }

            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.flutterwave.com/v3/transfers', [
                    'account_bank' => $this->getBankCode($task->payment_method),
                    'account_number' => preg_replace('/\D/', '', $task->mobile_money_number),
                    'amount' => (float) $task->amount,
                    'currency' => $task->currency,
                    'reference' => 'DMC-RETRY-'.$task->id.'-'.time(),
                    'narration' => 'DancyMeals Payout - Retry #'.($task->retry_count + 1),
                    'debit_currency' => $task->currency,
                ]);

            $responseData = $response->json();

            if ($response->successful() && ($responseData['status'] ?? '') === 'success') {
                return [
                    'success' => true,
                    'message' => __('Transfer successful'),
                    'response' => $responseData,
                ];
            }

            return [
                'success' => false,
                'message' => $responseData['message'] ?? __('Transfer failed'),
                'response' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave transfer retry failed', [
                'payout_task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('Transfer failed: :error', ['error' => $e->getMessage()]),
                'response' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Get the Flutterwave bank code for a payment method.
     */
    private function getBankCode(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'mtn_mobile_money' => 'MPS',
            'orange_money' => 'FMM',
            default => 'MPS',
        };
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
