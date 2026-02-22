<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * F-195: Dispatch Scheduled Announcements
 *
 * Checks for scheduled announcements whose dispatch time has arrived
 * and dispatches them to all targeted recipients.
 *
 * BR-317: Scheduled announcements dispatched by scheduled job at specified time.
 * Run every minute via scheduler.
 */
class DispatchScheduledAnnouncementsCommand extends Command
{
    protected $signature = 'dancymeals:dispatch-scheduled-announcements';

    protected $description = 'Dispatch scheduled announcements that are due for sending';

    public function handle(AnnouncementService $announcementService): int
    {
        $due = Announcement::readyToDispatch()->get();

        if ($due->isEmpty()) {
            $this->line('No scheduled announcements due for dispatch.');

            return self::SUCCESS;
        }

        $this->info("Found {$due->count()} announcement(s) to dispatch.");

        foreach ($due as $announcement) {
            try {
                $this->line("Dispatching announcement #{$announcement->id} ({$announcement->target_type})...");
                $announcementService->dispatchAnnouncement($announcement);
                $this->info("Announcement #{$announcement->id} dispatched successfully.");
            } catch (\Throwable $e) {
                Log::error('F-195: Failed to dispatch scheduled announcement', [
                    'announcement_id' => $announcement->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("Failed to dispatch announcement #{$announcement->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
