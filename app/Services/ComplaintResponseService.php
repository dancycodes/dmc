<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\ComplaintResponse;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * F-184: Cook/Manager Complaint Response Service.
 *
 * Handles business logic for viewing and responding to complaints
 * filed against a tenant's orders.
 */
class ComplaintResponseService
{
    /**
     * BR-196: Response message constraints.
     */
    public const MIN_MESSAGE_LENGTH = 10;

    public const MAX_MESSAGE_LENGTH = 2000;

    /**
     * Get complaints for a tenant with optional filters.
     *
     * @param  array{search?: string, status?: string}  $filters
     */
    public function getComplaintsForTenant(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Complaint::query()
            ->forTenant($tenantId)
            ->with(['client:id,name,email', 'order:id,order_number,grand_total,status'])
            ->when(! empty($filters['search']), function (Builder $q) use ($filters) {
                $term = '%'.trim($filters['search']).'%';
                $q->where(function (Builder $sub) use ($term, $filters) {
                    $sub->where('description', 'ilike', $term)
                        ->orWhereHas('client', function (Builder $clientQ) use ($term) {
                            $clientQ->where('name', 'ilike', $term)
                                ->orWhere('email', 'ilike', $term);
                        });

                    // Search by order number
                    $search = trim($filters['search']);
                    if (preg_match('/^DMC-?/i', $search)) {
                        $sub->orWhereHas('order', function (Builder $orderQ) use ($term) {
                            $orderQ->where('order_number', 'ilike', $term);
                        });
                    }

                    if (is_numeric($search)) {
                        $sub->orWhere('id', (int) $search);
                    }
                });
            })
            ->ofCookStatus($filters['status'] ?? null)
            ->cookPrioritySort()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get complaint summary counts for a tenant.
     *
     * @return array{total: int, open: int, in_review: int, escalated: int, resolved: int}
     */
    public function getComplaintSummary(int $tenantId): array
    {
        $counts = Complaint::query()
            ->forTenant($tenantId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as in_review,
                SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated,
                SUM(CASE WHEN status IN ('resolved', 'dismissed') THEN 1 ELSE 0 END) as resolved
            ")
            ->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'open' => (int) ($counts->open ?? 0),
            'in_review' => (int) ($counts->in_review ?? 0),
            'escalated' => (int) ($counts->escalated ?? 0),
            'resolved' => (int) ($counts->resolved ?? 0),
        ];
    }

    /**
     * Get a single complaint with all related data for the detail view.
     */
    public function getComplaintDetail(Complaint $complaint): Complaint
    {
        return $complaint->load([
            'client:id,name,email,phone',
            'order:id,order_number,grand_total,status,delivery_method,items_snapshot,created_at',
            'responses' => function ($q) {
                $q->with('user:id,name')->orderBy('created_at', 'asc');
            },
        ]);
    }

    /**
     * Submit a response to a complaint.
     *
     * BR-200: First response changes status from "open" to "in_review".
     * BR-201: First response cancels the 24h auto-escalation clock.
     * BR-202: Client notified via push + DB.
     * BR-203: Multiple responses allowed; only first changes status.
     * BR-206: Activity logged.
     *
     * @param  array{message: string, resolution_type: string, refund_amount?: int|null}  $data
     */
    public function submitResponse(
        Complaint $complaint,
        User $responder,
        array $data
    ): ComplaintResponse {
        return DB::transaction(function () use ($complaint, $responder, $data) {
            // Create the response record
            $response = ComplaintResponse::create([
                'complaint_id' => $complaint->id,
                'user_id' => $responder->id,
                'message' => $data['message'],
                'resolution_type' => $data['resolution_type'],
                'refund_amount' => $data['refund_amount'] ?? null,
            ]);

            // BR-200/BR-203: Only the first response changes status
            $isFirstResponse = $complaint->status === 'open';

            if ($isFirstResponse) {
                $complaint->update([
                    'status' => 'in_review',
                    'cook_response' => $data['message'],
                    'cook_responded_at' => now(),
                ]);
            }

            // BR-206: Activity logging
            activity('complaints')
                ->performedOn($complaint)
                ->causedBy($responder)
                ->withProperties([
                    'response_id' => $response->id,
                    'resolution_type' => $data['resolution_type'],
                    'refund_amount' => $data['refund_amount'] ?? null,
                    'is_first_response' => $isFirstResponse,
                    'order_id' => $complaint->order_id,
                ])
                ->log('complaint_response_submitted');

            // BR-202: Notify client
            $this->notifyClient($complaint, $response);

            return $response;
        });
    }

    /**
     * Check if a user is authorized to respond to a complaint.
     *
     * BR-195: Only the cook or a manager with manage-complaints permission.
     */
    public function canRespond(Complaint $complaint, User $user): bool
    {
        $tenant = $complaint->tenant;

        if (! $tenant) {
            return false;
        }

        // Cook (owner of the tenant)
        if ($tenant->cook_id === $user->id) {
            return true;
        }

        // Manager with the manage-complaints permission (F-210)
        if ($user->can('can-manage-complaints')) {
            return true;
        }

        return false;
    }

    /**
     * BR-290: Notify the client about the cook's response via central notification service (F-193).
     */
    private function notifyClient(Complaint $complaint, ComplaintResponse $response): void
    {
        try {
            $notificationService = app(\App\Services\ComplaintNotificationService::class);
            $notificationService->notifyComplaintResponse($complaint, $response);
        } catch (\Throwable $e) {
            Log::warning('F-193: Complaint response notification dispatch failed', [
                'complaint_id' => $complaint->id,
                'response_id' => $response->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Parse items snapshot for display.
     *
     * @return array<int, array{name: string, quantity: int, price: int}>
     */
    public function parseOrderItems(mixed $itemsSnapshot): array
    {
        if (is_string($itemsSnapshot)) {
            $itemsSnapshot = json_decode($itemsSnapshot, true);
        }

        if (! is_array($itemsSnapshot)) {
            return [];
        }

        return collect($itemsSnapshot)->map(function ($item) {
            return [
                'name' => $item['meal_name'] ?? $item['meal'] ?? __('Unknown Item'),
                'quantity' => (int) ($item['quantity'] ?? 1),
                'price' => (int) ($item['unit_price'] ?? $item['price'] ?? 0),
            ];
        })->all();
    }

    /**
     * Format XAF amount for display.
     */
    public static function formatXAF(int|float $amount): string
    {
        return number_format((int) $amount, 0, '.', ',').' XAF';
    }
}
