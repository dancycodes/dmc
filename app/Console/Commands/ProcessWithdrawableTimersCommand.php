<?php

namespace App\Console\Commands;

use App\Services\OrderClearanceService;
use Illuminate\Console\Command;

/**
 * F-171: Scheduled command to process withdrawable fund timers.
 *
 * BR-336: Runs every 5 minutes to check and transition eligible funds.
 * Edge case: If the job fails to run, the next run catches up.
 */
class ProcessWithdrawableTimersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'dancymeals:process-withdrawable-timers';

    /**
     * The console command description.
     */
    protected $description = 'Process order clearance timers and transition eligible funds to withdrawable';

    /**
     * Execute the console command.
     */
    public function handle(OrderClearanceService $service): int
    {
        $result = $service->processEligibleClearances();

        if ($result['processed'] > 0) {
            $this->info(__(
                'Processed :count clearances totaling :amount XAF. Notified :cooks cook(s).',
                [
                    'count' => $result['processed'],
                    'amount' => number_format($result['total_amount'], 0, '.', ','),
                    'cooks' => $result['cooks_notified'],
                ]
            ));
        } else {
            $this->info(__('No eligible clearances to process.'));
        }

        return Command::SUCCESS;
    }
}
