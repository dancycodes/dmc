<?php

namespace App\Services;

use App\Mail\SystemAnnouncementMail;
use App\Models\Announcement;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SystemAnnouncementNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * F-195: Announcement Service
 *
 * Centralizes all business logic for system announcements:
 * - Creating and scheduling announcements
 * - Resolving target recipients
 * - Dispatching notifications via push, database, and email
 * - Cancelling scheduled announcements
 *
 * BR-311 through BR-322 are enforced here.
 */
class AnnouncementService
{
    /**
     * Create and optionally dispatch an announcement.
     *
     * BR-316: Immediate send dispatches via queue.
     * BR-317: Scheduled announcements stored for later dispatch.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAnnouncement(User $admin, array $data): Announcement
    {
        $announcement = DB::transaction(function () use ($admin, $data) {
            $status = isset($data['scheduled_at'])
                ? Announcement::STATUS_SCHEDULED
                : Announcement::STATUS_SENT;

            $announcement = Announcement::create([
                'user_id' => $admin->id,
                'content' => $data['content'],
                'target_type' => $data['target_type'],
                'target_tenant_id' => $data['target_tenant_id'] ?? null,
                'status' => $status,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'sent_at' => isset($data['scheduled_at']) ? null : now(),
            ]);

            // BR-320: Log via Spatie Activitylog
            activity()
                ->causedBy($admin)
                ->performedOn($announcement)
                ->withProperties([
                    'target_type' => $announcement->target_type,
                    'target_tenant_id' => $announcement->target_tenant_id,
                    'status' => $announcement->status,
                ])
                ->log('announcement_created');

            return $announcement;
        });

        // BR-316: Send immediately if not scheduled
        if ($announcement->status === Announcement::STATUS_SENT) {
            $this->dispatchAnnouncement($announcement);
        }

        return $announcement;
    }

    /**
     * Update a scheduled or draft announcement.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAnnouncement(User $admin, Announcement $announcement, array $data): Announcement
    {
        DB::transaction(function () use ($admin, $announcement, $data) {
            $oldValues = $announcement->only(['content', 'target_type', 'target_tenant_id', 'scheduled_at']);

            $status = isset($data['scheduled_at'])
                ? Announcement::STATUS_SCHEDULED
                : Announcement::STATUS_DRAFT;

            $announcement->update([
                'content' => $data['content'],
                'target_type' => $data['target_type'],
                'target_tenant_id' => $data['target_tenant_id'] ?? null,
                'status' => $status,
                'scheduled_at' => $data['scheduled_at'] ?? null,
            ]);

            activity()
                ->causedBy($admin)
                ->performedOn($announcement)
                ->withProperties(['old' => $oldValues, 'new' => $announcement->fresh()->only(array_keys($oldValues))])
                ->log('announcement_updated');
        });

        return $announcement->fresh();
    }

    /**
     * Cancel a scheduled announcement.
     *
     * BR-322: Admin can cancel a scheduled announcement before it is sent.
     */
    public function cancelAnnouncement(User $admin, Announcement $announcement): void
    {
        DB::transaction(function () use ($admin, $announcement) {
            $announcement->update(['status' => Announcement::STATUS_CANCELLED]);

            activity()
                ->causedBy($admin)
                ->performedOn($announcement)
                ->log('announcement_cancelled');
        });
    }

    /**
     * Dispatch an announcement to all targeted recipients.
     *
     * BR-315: All three channels: push + DB + email
     * BR-317: Called for immediate sends and by scheduled job
     */
    public function dispatchAnnouncement(Announcement $announcement): void
    {
        $recipients = $this->resolveRecipients($announcement);

        if (empty($recipients)) {
            Log::info('F-195: Announcement dispatched but no recipients found', [
                'announcement_id' => $announcement->id,
                'target_type' => $announcement->target_type,
            ]);

            return;
        }

        foreach ($recipients as $recipient) {
            $this->notifyRecipient($recipient, $announcement);
        }

        // Mark as sent after all notifications dispatched
        $announcement->update([
            'status' => Announcement::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Resolve the list of recipients for an announcement based on target type.
     *
     * BR-312: Target options: all_users, all_cooks, all_clients, specific_tenant
     * BR-313: For specific_tenant, only the cook and managers of that tenant
     *
     * @return array<User>
     */
    public function resolveRecipients(Announcement $announcement): array
    {
        return match ($announcement->target_type) {
            Announcement::TARGET_ALL_USERS => User::query()
                ->where('is_active', true)
                ->whereNotNull('email_verified_at')
                ->get()
                ->all(),

            Announcement::TARGET_ALL_COOKS => User::query()
                ->where('is_active', true)
                ->whereNotNull('email_verified_at')
                ->whereHas('roles', fn ($q) => $q->where('name', 'cook'))
                ->get()
                ->all(),

            Announcement::TARGET_ALL_CLIENTS => User::query()
                ->where('is_active', true)
                ->whereNotNull('email_verified_at')
                ->whereHas('roles', fn ($q) => $q->where('name', 'client'))
                ->get()
                ->all(),

            Announcement::TARGET_SPECIFIC_TENANT => $this->resolveTenantRecipients($announcement),

            default => [],
        };
    }

    /**
     * Send push + DB + email notification to a single recipient.
     *
     * BR-315: All three channels.
     * Email failures do not block push/DB delivery.
     */
    private function notifyRecipient(User $recipient, Announcement $announcement): void
    {
        // Push + Database notification
        try {
            $recipient->notify(new SystemAnnouncementNotification($announcement));
        } catch (\Throwable $e) {
            Log::warning('F-195: Push/DB notification failed', [
                'announcement_id' => $announcement->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Email notification (BR-315)
        if (! empty($recipient->email)) {
            try {
                Mail::to($recipient->email)
                    ->send(
                        (new SystemAnnouncementMail($announcement))
                            ->forRecipient($recipient)
                    );
            } catch (\Throwable $e) {
                // BR-edge: Email failure logged; push/DB still delivered
                Log::warning('F-195: Email notification failed', [
                    'announcement_id' => $announcement->id,
                    'recipient_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Resolve recipients for a specific tenant (cook + managers).
     *
     * BR-313: Specific tenant targeting includes cook and managers of that tenant.
     *
     * @return array<User>
     */
    private function resolveTenantRecipients(Announcement $announcement): array
    {
        if (! $announcement->target_tenant_id) {
            return [];
        }

        $tenant = $announcement->targetTenant;
        if (! $tenant) {
            return [];
        }

        $recipients = [];
        $seenIds = [];

        // Add the cook
        $cook = $tenant->cook;
        if ($cook && $cook->is_active) {
            $recipients[] = $cook;
            $seenIds[] = $cook->id;
        }

        // Add managers associated with this tenant
        try {
            $managerIds = DB::table('tenant_managers')
                ->where('tenant_id', $tenant->id)
                ->pluck('user_id')
                ->all();

            if (! empty($managerIds)) {
                $managers = User::query()
                    ->whereIn('id', $managerIds)
                    ->where('is_active', true)
                    ->get();

                foreach ($managers as $manager) {
                    if (! in_array($manager->id, $seenIds, true)) {
                        $recipients[] = $manager;
                        $seenIds[] = $manager->id;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('F-195: Error resolving tenant managers', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $recipients;
    }

    /**
     * Get paginated announcements list for admin view.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<Announcement>
     */
    public function getAnnouncementsList(int $perPage = 20)
    {
        return Announcement::query()
            ->with(['creator', 'targetTenant'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get all active tenants for the target tenant dropdown.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Tenant>
     */
    public function getActiveTenantsForDropdown()
    {
        return Tenant::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }
}
