<?php

namespace App\Services;

use App\Mail\ComplaintResolvedMail;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\User;
use App\Notifications\ComplaintEscalatedAdminNotification;
use App\Notifications\ComplaintEscalatedClientNotification;
use App\Notifications\ComplaintEscalatedCookNotification;
use App\Notifications\ComplaintResolvedNotification;
use App\Notifications\ComplaintResponseNotification;
use App\Notifications\ComplaintSubmittedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * F-193: Complaint Notification Service
 *
 * Central service for dispatching all complaint lifecycle notifications.
 *
 * BR-289: Complaint submitted → cook + managers with manage-complaints receive push + DB
 * BR-290: Cook responds → client receives push + DB
 * BR-291: Escalated → admin, client, and cook each receive push + DB
 * BR-292: Resolved → client receives push + DB + email
 * BR-293: Email is sent ONLY on resolution
 * BR-297: Notifications are queued to not block the triggering action
 */
class ComplaintNotificationService
{
    /**
     * BR-289: Notify cook and managers with manage-complaints when a complaint is submitted.
     *
     * Sends push + DB notifications.
     */
    public function notifyComplaintSubmitted(Complaint $complaint, Order $order): void
    {
        $recipients = $this->resolveCookAndManagers($complaint);

        foreach ($recipients as $recipient) {
            try {
                $recipient->notify(new ComplaintSubmittedNotification($complaint, $order));
            } catch (\Throwable $e) {
                Log::warning('F-193: ComplaintSubmitted push/DB notification failed', [
                    'complaint_id' => $complaint->id,
                    'recipient_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * BR-290: Notify the client when the cook responds to a complaint.
     *
     * Sends push + DB notification.
     */
    public function notifyComplaintResponse(
        Complaint $complaint,
        \App\Models\ComplaintResponse $response
    ): void {
        try {
            $client = $complaint->client;

            if (! $client) {
                return;
            }

            $complaint->loadMissing('order:id,order_number');
            $client->notify(new ComplaintResponseNotification($complaint, $response));
        } catch (\Throwable $e) {
            Log::warning('F-193: ComplaintResponse push/DB notification failed', [
                'complaint_id' => $complaint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * BR-291: Notify admin, client, and cook when a complaint is escalated.
     *
     * All three parties receive push + DB notifications.
     */
    public function notifyComplaintEscalated(Complaint $complaint): void
    {
        $this->notifyAdminsOnEscalation($complaint);
        $this->notifyClientOnEscalation($complaint);
        $this->notifyCookOnEscalation($complaint);
    }

    /**
     * BR-292 + BR-293: Notify client when a complaint is resolved.
     *
     * Client receives push + DB + email.
     * Email ONLY sent for resolution (not for other events).
     */
    public function notifyComplaintResolved(Complaint $complaint): void
    {
        $this->sendResolutionPushAndDb($complaint);
        $this->sendResolutionEmail($complaint);
    }

    /**
     * BR-289: Resolve recipients for "complaint submitted" — cook + managers with manage-complaints.
     *
     * @return array<User>
     */
    public function resolveCookAndManagers(Complaint $complaint): array
    {
        $recipients = [];
        $seenIds = [];

        // Add the cook
        $cook = $complaint->cook;
        if ($cook) {
            $recipients[] = $cook;
            $seenIds[] = $cook->id;
        }

        // Add managers with can-manage-complaints permission (F-210 delegatable permission)
        try {
            $managers = User::permission('can-manage-complaints')->get();
            foreach ($managers as $manager) {
                if (! in_array($manager->id, $seenIds, true)) {
                    $recipients[] = $manager;
                    $seenIds[] = $manager->id;
                }
            }
        } catch (\Throwable) {
            // Permission may not exist yet — silent fail
        }

        return $recipients;
    }

    /**
     * BR-291: Notify all admin and super-admin users of escalation.
     */
    private function notifyAdminsOnEscalation(Complaint $complaint): void
    {
        try {
            $admins = User::role(['admin', 'super-admin'])->get();

            foreach ($admins as $admin) {
                $admin->notify(new ComplaintEscalatedAdminNotification($complaint));
            }
        } catch (\Throwable $e) {
            Log::warning('F-193: ComplaintEscalated admin notification failed', [
                'complaint_id' => $complaint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * BR-291: Notify the client of escalation.
     */
    private function notifyClientOnEscalation(Complaint $complaint): void
    {
        try {
            $client = $complaint->client;

            if ($client) {
                $client->notify(new ComplaintEscalatedClientNotification($complaint));
            }
        } catch (\Throwable $e) {
            Log::warning('F-193: ComplaintEscalated client notification failed', [
                'complaint_id' => $complaint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * BR-291: Notify the cook of escalation.
     */
    private function notifyCookOnEscalation(Complaint $complaint): void
    {
        try {
            $cook = $complaint->cook;

            if ($cook) {
                $cook->notify(new ComplaintEscalatedCookNotification($complaint));
            }
        } catch (\Throwable $e) {
            Log::warning('F-193: ComplaintEscalated cook notification failed', [
                'complaint_id' => $complaint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * BR-292: Send push + DB notification to client on resolution.
     */
    private function sendResolutionPushAndDb(Complaint $complaint): void
    {
        try {
            $client = $complaint->client;

            if ($client) {
                $client->notify(new ComplaintResolvedNotification($complaint));
            }
        } catch (\Throwable $e) {
            Log::warning('F-193: ComplaintResolved push/DB notification failed', [
                'complaint_id' => $complaint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * BR-292 + BR-293: Send resolution email to client.
     *
     * Email contains: order ID, complaint category, resolution type,
     * refund amount (if applicable), admin notes.
     */
    private function sendResolutionEmail(Complaint $complaint): void
    {
        try {
            $client = $complaint->client;

            if (! $client || empty($client->email)) {
                return;
            }

            $complaint->loadMissing(['order:id,order_number,grand_total', 'tenant:id,name_en,name_fr,slug']);

            Mail::to($client->email)
                ->send(
                    (new ComplaintResolvedMail($complaint))
                        ->forRecipient($client)
                );
        } catch (\Throwable $e) {
            // Email failure is non-fatal; push+DB already delivered
            Log::warning('F-193: ComplaintResolved email notification failed', [
                'complaint_id' => $complaint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
