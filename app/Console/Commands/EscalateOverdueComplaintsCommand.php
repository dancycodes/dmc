<?php

namespace App\Console\Commands;

use App\Services\ComplaintEscalationService;
use Illuminate\Console\Command;

/**
 * F-185 BR-207: Scheduled command to auto-escalate overdue complaints.
 *
 * Runs every 15 minutes to check for open complaints that have not
 * received a cook response within 24 hours of submission.
 *
 * Edge case: If the job fails mid-execution, already-escalated complaints
 * retain their status; remaining are picked up on the next run.
 */
class EscalateOverdueComplaintsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'dancymeals:escalate-overdue-complaints';

    /**
     * The console command description.
     */
    protected $description = 'Auto-escalate open complaints that have not received a cook response within 24 hours';

    /**
     * Execute the console command.
     */
    public function handle(ComplaintEscalationService $service): int
    {
        $result = $service->processOverdueComplaints();

        if ($result['escalated'] > 0) {
            $this->info(__(':count complaint(s) auto-escalated.', [
                'count' => $result['escalated'],
            ]));
        }

        if ($result['failed'] > 0) {
            $this->warn(__(':count complaint(s) failed to escalate.', [
                'count' => $result['failed'],
            ]));

            foreach ($result['errors'] as $error) {
                $this->error($error);
            }
        }

        if ($result['escalated'] === 0 && $result['failed'] === 0) {
            $this->info(__('No overdue complaints to escalate.'));
        }

        return $result['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
