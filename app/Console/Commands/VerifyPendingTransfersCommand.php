<?php

namespace App\Console\Commands;

use App\Services\FlutterwaveTransferService;
use Illuminate\Console\Command;

/**
 * F-173: Verify pending_verification transfers with Flutterwave.
 *
 * BR-360: Follow-up job re-checks status of timed-out transfers.
 * Runs on a schedule to resolve transfers stuck in pending_verification.
 */
class VerifyPendingTransfersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'dancymeals:verify-pending-transfers';

    /**
     * The console command description.
     */
    protected $description = 'Verify pending_verification transfers with Flutterwave Transfer API';

    /**
     * Execute the console command.
     */
    public function handle(FlutterwaveTransferService $transferService): int
    {
        $this->info('Verifying pending transfers...');

        $stats = $transferService->verifyAllPending();

        $this->info('Transfer verification complete:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Verified', $stats['verified']],
                ['Completed', $stats['completed']],
                ['Failed', $stats['failed']],
                ['Still Pending', $stats['still_pending']],
            ]
        );

        if ($stats['failed'] > 0) {
            $this->warn("{$stats['failed']} transfer(s) confirmed as failed. Added to manual payout queue.");
        }

        return self::SUCCESS;
    }
}
