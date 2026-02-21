<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * F-187: Complaint Status Tracking Service.
 *
 * Provides data for complaint status timeline, message history,
 * and resolution details. Read-only service — status changes
 * happen in F-183, F-184, F-185, and F-061.
 */
class ComplaintTrackingService
{
    /**
     * BR-229: Complaint states in order.
     *
     * @var list<string>
     */
    public const TIMELINE_STATES = ['open', 'in_review', 'escalated', 'resolved'];

    /**
     * BR-229: State labels for timeline display.
     *
     * @return array<string, string>
     */
    public static function getStateLabels(): array
    {
        return [
            'open' => __('Open'),
            'in_review' => __('In Review'),
            'escalated' => __('Escalated'),
            'resolved' => __('Resolved'),
        ];
    }

    /**
     * Get full complaint detail with all related data for tracking view.
     *
     * BR-233: Both client and cook/manager can see all messages.
     * BR-236: Timestamps shown for each state transition.
     */
    public function getComplaintTrackingData(Complaint $complaint): Complaint
    {
        return $complaint->load([
            'client:id,name,email,phone',
            'cook:id,name',
            'tenant:id,name_en,name_fr,slug',
            'order:id,order_number,grand_total,status,delivery_method,items_snapshot,created_at',
            'resolvedByUser:id,name',
            'responses' => function ($q) {
                $q->with('user:id,name')->orderBy('created_at', 'asc');
            },
        ]);
    }

    /**
     * Build the status timeline data for display.
     *
     * BR-229: Open > In Review > Escalated > Resolved
     * BR-230: Can skip In Review (direct Open > Escalated)
     * BR-231: Can skip Escalated (In Review > Resolved)
     * BR-232: Visual timeline with current state highlighted
     * BR-236: Timestamps for each state transition
     *
     * @return list<array{state: string, label: string, status: string, timestamp: string|null}>
     */
    public function buildTimeline(Complaint $complaint): array
    {
        $currentStatus = $this->mapToTimelineStatus($complaint->status);
        $stateLabels = self::getStateLabels();
        $timeline = [];

        // Collect timestamps for each state
        $timestamps = $this->getStateTimestamps($complaint);

        // Determine which states were reached
        $reachedStates = $this->getReachedStates($complaint);

        foreach (self::TIMELINE_STATES as $state) {
            $stepStatus = $this->getStepStatus($state, $currentStatus, $reachedStates);

            $timeline[] = [
                'state' => $state,
                'label' => $stateLabels[$state],
                'status' => $stepStatus, // 'completed', 'active', 'skipped', 'upcoming'
                'timestamp' => $timestamps[$state] ?? null,
            ];
        }

        return $timeline;
    }

    /**
     * Map the actual complaint status to one of the four timeline states.
     *
     * Some statuses (pending_resolution, under_review, responded) map to
     * escalated or in_review for timeline display.
     */
    public function mapToTimelineStatus(string $status): string
    {
        return match ($status) {
            'open' => 'open',
            'in_review', 'responded' => 'in_review',
            'escalated', 'pending_resolution', 'under_review' => 'escalated',
            'resolved', 'dismissed' => 'resolved',
            default => 'open',
        };
    }

    /**
     * Get timestamps for each reached state.
     *
     * @return array<string, string|null>
     */
    public function getStateTimestamps(Complaint $complaint): array
    {
        $timestamps = [
            'open' => null,
            'in_review' => null,
            'escalated' => null,
            'resolved' => null,
        ];

        // Open: submitted_at or created_at
        $submittedAt = $complaint->submitted_at ?? $complaint->created_at;
        if ($submittedAt) {
            $timestamps['open'] = $submittedAt->format('M d, Y H:i');
        }

        // In Review: cook_responded_at
        if ($complaint->cook_responded_at) {
            $timestamps['in_review'] = $complaint->cook_responded_at->format('M d, Y H:i');
        }

        // Escalated: escalated_at
        if ($complaint->escalated_at) {
            $timestamps['escalated'] = $complaint->escalated_at->format('M d, Y H:i');
        }

        // Resolved: resolved_at
        if ($complaint->resolved_at) {
            $timestamps['resolved'] = $complaint->resolved_at->format('M d, Y H:i');
        }

        return $timestamps;
    }

    /**
     * Determine which states were actually reached during the complaint lifecycle.
     *
     * @return list<string>
     */
    private function getReachedStates(Complaint $complaint): array
    {
        $reached = ['open']; // Always reached

        // Check if In Review was reached (cook responded)
        if ($complaint->cook_responded_at) {
            $reached[] = 'in_review';
        }

        // Check if Escalated was reached
        if ($complaint->is_escalated || $complaint->escalated_at) {
            $reached[] = 'escalated';
        }

        // Check if Resolved was reached
        if (in_array($complaint->status, ['resolved', 'dismissed'], true)) {
            $reached[] = 'resolved';
        }

        return $reached;
    }

    /**
     * Determine step status for a timeline state.
     */
    private function getStepStatus(string $state, string $currentStatus, array $reachedStates): string
    {
        $stateIndex = array_search($state, self::TIMELINE_STATES, true);
        $currentIndex = array_search($currentStatus, self::TIMELINE_STATES, true);

        if ($stateIndex === false || $currentIndex === false) {
            return 'upcoming';
        }

        // If this IS the current state, it's active
        if ($state === $currentStatus) {
            return 'active';
        }

        // If this state was reached and is before the current, it's completed
        if ($stateIndex < $currentIndex && in_array($state, $reachedStates, true)) {
            return 'completed';
        }

        // If state is before current but was NOT reached, it was skipped
        // BR-230: Open > Escalated (In Review skipped)
        // BR-231: In Review > Resolved (Escalated skipped)
        if ($stateIndex < $currentIndex && ! in_array($state, $reachedStates, true)) {
            return 'skipped';
        }

        return 'upcoming';
    }

    /**
     * Build the message thread for display.
     *
     * BR-233: Combines original complaint message with all responses.
     * Messages shown in chronological order with sender info and role badges.
     *
     * @return list<array{type: string, sender: string, role: string, message: string, timestamp: string, resolution_type?: string, refund_amount?: int|null}>
     */
    public function buildMessageThread(Complaint $complaint): array
    {
        $messages = [];

        // First message: the original complaint from the client
        $messages[] = [
            'type' => 'complaint',
            'sender' => $complaint->client?->name ?? __('Client'),
            'role' => 'client',
            'message' => $complaint->description,
            'timestamp' => ($complaint->submitted_at ?? $complaint->created_at)?->format('M d, Y H:i') ?? '',
        ];

        // All responses from cook/manager/admin
        foreach ($complaint->responses as $response) {
            $role = $this->determineResponderRole($response->user, $complaint);

            $entry = [
                'type' => 'response',
                'sender' => $response->user?->name ?? __('Unknown'),
                'role' => $role,
                'message' => $response->message,
                'timestamp' => $response->created_at->format('M d, Y H:i'),
            ];

            if ($response->resolution_type) {
                $entry['resolution_type'] = $response->resolution_type;
                $entry['resolution_label'] = $response->resolutionTypeLabel();
                $entry['refund_amount'] = $response->refund_amount;
            }

            $messages[] = $entry;
        }

        return $messages;
    }

    /**
     * Determine the role of a responder relative to the complaint.
     */
    private function determineResponderRole(?User $user, Complaint $complaint): string
    {
        if (! $user) {
            return 'unknown';
        }

        // Check if this is the cook (tenant owner)
        if ($complaint->cook_id && $user->id === $complaint->cook_id) {
            return 'cook';
        }

        // Check if this is an admin
        if ($user->hasRole('admin') || $user->hasRole('super-admin')) {
            return 'admin';
        }

        // Otherwise, manager
        return 'manager';
    }

    /**
     * Get resolution display data.
     *
     * BR-234: Resolution details include type, refund amount, admin notes.
     * Edge case: "warn_cook" shows "Action taken" to client, specific message to cook.
     *
     * @return array{type: string, label: string, amount: int|null, notes: string|null, resolved_at: string|null, resolved_by: string|null}|null
     */
    public function getResolutionData(Complaint $complaint, string $viewerRole = 'client'): ?array
    {
        if (! $complaint->isResolved()) {
            return null;
        }

        $label = $complaint->resolutionTypeLabel();

        // Edge case: warn_cook shows different text to client vs cook
        if ($complaint->resolution_type === 'warning' && $viewerRole === 'client') {
            $label = __('Resolved - Action taken');
        }

        return [
            'type' => $complaint->resolution_type ?? 'dismiss',
            'label' => $label,
            'amount' => $complaint->refund_amount ? (int) $complaint->refund_amount : null,
            'notes' => $viewerRole === 'client' && $complaint->resolution_type === 'warning'
                ? null
                : $complaint->resolution_notes,
            'resolved_at' => $complaint->resolved_at?->format('M d, Y H:i'),
            'resolved_by' => $complaint->resolvedByUser?->name,
        ];
    }

    /**
     * Check if a user can view a complaint.
     *
     * BR-238: Only accessible by filing client, tenant cook, authorized managers, and admins.
     */
    public function canViewComplaint(Complaint $complaint, User $user): bool
    {
        // The filing client
        if ($complaint->client_id === $user->id) {
            return true;
        }

        // The tenant cook
        if ($complaint->cook_id === $user->id) {
            return true;
        }

        // Admin or super-admin
        if ($user->hasRole('admin') || $user->hasRole('super-admin')) {
            return true;
        }

        // Manager with manage-complaints permission (F-210 delegatable permission)
        if ($user->can('can-manage-complaints')) {
            return true;
        }

        return false;
    }

    /**
     * Get all complaints for a client.
     *
     * UI/UX: "My Complaints" section in client profile/settings area.
     */
    public function getClientComplaints(User $client, int $perPage = 15): LengthAwarePaginator
    {
        return Complaint::query()
            ->where('client_id', $client->id)
            ->with([
                'order:id,order_number,grand_total,status',
                'tenant:id,name_en,name_fr,slug',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Format XAF amount for display.
     */
    public static function formatXAF(int|float $amount): string
    {
        return number_format((int) $amount, 0, '.', ',').' XAF';
    }
}
