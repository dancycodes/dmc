<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\User;
use App\Notifications\ComplaintEscalatedAdminNotification;
use App\Notifications\ComplaintEscalatedClientNotification;
use App\Notifications\ComplaintEscalatedCookNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * F-185: Complaint Auto-Escalation Service.
 *
 * Handles business logic for detecting and escalating overdue complaints.
 * BR-207: Runs via scheduled command every 15 minutes.
 * BR-208: Complaints eligible if status "open" and created_at > 24 hours ago.
 * BR-209: Never escalates "in_review", "escalated", or "resolved" complaints.
 * BR-215: Idempotent — safe to run multiple times.
 */
class ComplaintEscalationService
{
    /**
     * BR-208: Hours before an open complaint is auto-escalated.
     */
    public const ESCALATION_THRESHOLD_HOURS = 24;

    /**
     * Edge case: Process in batches to avoid memory/timeout issues.
     */
    public const BATCH_SIZE = 100;

    /**
     * Process all eligible complaints for auto-escalation.
     *
     * BR-208: A complaint is eligible if status is "open" and more than 24 hours
     * have passed since created_at.
     * BR-209: Complaints with status "in_review", "escalated", or "resolved" are skipped.
     * BR-215: Idempotent — already-escalated complaints are never re-processed.
     *
     * @return array{escalated: int, failed: int, errors: list<string>}
     */
    public function processOverdueComplaints(): array
    {
        $result = [
            'escalated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $threshold = now()->subHours(self::ESCALATION_THRESHOLD_HOURS);

        // BR-208: Only "open" complaints older than 24 hours
        // BR-209: Explicitly exclude non-open statuses
        // BR-215: Idempotent — only targets "open" status
        Complaint::query()
            ->where('status', 'open')
            ->where('created_at', '<=', $threshold)
            ->with(['client:id,name,email', 'cook:id,name,email', 'tenant:id,name_en,name_fr,slug', 'order:id,order_number'])
            ->chunkById(self::BATCH_SIZE, function ($complaints) use (&$result) {
                foreach ($complaints as $complaint) {
                    try {
                        $this->escalateComplaint($complaint);
                        $result['escalated']++;
                    } catch (\Throwable $e) {
                        $result['failed']++;
                        $result['errors'][] = "Complaint #{$complaint->id}: {$e->getMessage()}";

                        Log::error('F-185: Failed to escalate complaint', [
                            'complaint_id' => $complaint->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $result;
    }

    /**
     * Escalate a single complaint.
     *
     * BR-210: Status changes from "open" to "escalated".
     * BR-211: Admin users receive push + DB notification.
     * BR-212: Client receives push + DB notification.
     * BR-213: Cook receives push + DB notification.
     * BR-214: Escalation logged via Spatie Activitylog with system as actor.
     */
    public function escalateComplaint(Complaint $complaint): void
    {
        DB::transaction(function () use ($complaint) {
            // BR-210: Update status to "escalated"
            $complaint->update([
                'status' => 'escalated',
                'is_escalated' => true,
                'escalation_reason' => Complaint::ESCALATION_AUTO_24H,
                'escalated_at' => now(),
            ]);

            // BR-214: Activity log with system as actor (no user caused it)
            activity('complaints')
                ->performedOn($complaint)
                ->causedByAnonymous()
                ->withProperties([
                    'escalation_reason' => Complaint::ESCALATION_AUTO_24H,
                    'order_id' => $complaint->order_id,
                    'tenant_id' => $complaint->tenant_id,
                    'hours_since_submission' => $complaint->created_at
                        ? now()->diffInHours($complaint->created_at)
                        : null,
                ])
                ->log('complaint_auto_escalated');
        });

        // Send notifications outside the transaction (non-critical)
        $this->sendEscalationNotifications($complaint);
    }

    /**
     * Send escalation notifications to admin, client, and cook.
     *
     * BR-211: Admin users receive push + DB notification.
     * BR-212: Client receives push + DB notification.
     * BR-213: Cook receives push + DB notification.
     *
     * Edge case: If no admin users exist, notification delivery fails silently
     * but complaint status update is already committed.
     */
    private function sendEscalationNotifications(Complaint $complaint): void
    {
        // BR-211: Notify all admin users
        $this->notifyAdmins($complaint);

        // BR-212: Notify the client
        $this->notifyClient($complaint);

        // BR-213: Notify the cook
        $this->notifyCook($complaint);
    }

    /**
     * BR-211: Notify admin users about the escalated complaint.
     */
    private function notifyAdmins(Complaint $complaint): void
    {
        try {
            $admins = User::role(['admin', 'super-admin'])->get();

            foreach ($admins as $admin) {
                $admin->notify(new ComplaintEscalatedAdminNotification($complaint));
            }
        } catch (\Throwable $e) {
            Log::warning('F-185: Admin notification dispatch failed', [
                'complaint_id' => $complaint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * BR-212: Notify the client that their complaint was escalated.
     */
    private function notifyClient(Complaint $complaint): void
    {
        try {
            $client = $complaint->client;

            if ($client) {
                $client->notify(new ComplaintEscalatedClientNotification($complaint));
            }
        } catch (\Throwable $e) {
            Log::warning('F-185: Client notification dispatch failed', [
                'complaint_id' => $complaint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * BR-213: Notify the cook that their complaint was auto-escalated.
     */
    private function notifyCook(Complaint $complaint): void
    {
        try {
            $cook = $complaint->cook;

            if ($cook) {
                $cook->notify(new ComplaintEscalatedCookNotification($complaint));
            }
        } catch (\Throwable $e) {
            Log::warning('F-185: Cook notification dispatch failed', [
                'complaint_id' => $complaint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
