<?php

namespace App\Console\Commands;

use App\Services\FlutterwaveTransferService;
use Illuminate\Console\Command;

/**
 * F-173: Process pending withdrawal requests via Flutterwave Transfer API.
 *
 * Scenario 4: Multiple withdrawals are processed sequentially.
 * BR-356: Withdrawals executed via Flutterwave Transfer API to mobile money.
 */
class ProcessWithdrawalsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'dancymeals:process-withdrawals';

    /**
     * The console command description.
     */
    protected $description = 'Process pending withdrawal requests via Flutterwave Transfer API';

    /**
     * Execute the console command.
     */
    public function handle(FlutterwaveTransferService $transferService): int
    {
        $this->info('Processing pending withdrawal requests...');

        $stats = $transferService->processAllPending();

        $this->info('Withdrawal processing complete:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $stats['processed']],
                ['Succeeded', $stats['succeeded']],
                ['Failed', $stats['failed']],
                ['Timeouts', $stats['timeouts']],
                ['Skipped', $stats['skipped']],
            ]
        );

        if ($stats['failed'] > 0) {
            $this->warn("{$stats['failed']} withdrawal(s) failed. Check the manual payout queue.");
        }

        if ($stats['timeouts'] > 0) {
            $this->warn("{$stats['timeouts']} withdrawal(s) timed out. Will be verified in next cycle.");
        }

        return self::SUCCESS;
    }
}
