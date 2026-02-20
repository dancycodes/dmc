<?php

namespace App\Console\Commands;

use App\Services\PaymentRetryService;
use Illuminate\Console\Command;

/**
 * F-152: Payment Retry with Timeout
 *
 * BR-381: After 15 minutes without successful payment, order auto-cancels.
 * BR-382: Auto-cancellation is handled by a scheduled job.
 * BR-385: Cancelled orders release any held stock/availability.
 */
class CancelExpiredOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dancymeals:cancel-expired-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel orders that have exceeded their payment retry window';

    /**
     * Execute the console command.
     */
    public function handle(PaymentRetryService $retryService): int
    {
        $cancelled = $retryService->cancelExpiredOrders();

        if ($cancelled > 0) {
            $this->info("Cancelled {$cancelled} expired order(s).");
        } else {
            $this->info('No expired orders found.');
        }

        return self::SUCCESS;
    }
}
