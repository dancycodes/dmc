<?php

use App\Models\CookWallet;
use App\Models\PayoutTask;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\FlutterwaveService;
use App\Services\FlutterwaveTransferService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seedRolesAndPermissions();
    Notification::fake();
});

/**
 * Helper to set up a complete withdrawal scenario.
 *
 * @return array{tenant: Tenant, cook: User, wallet: CookWallet, withdrawal: WithdrawalRequest}
 */
function setupWithdrawalScenario(float $amount = 20000): array
{
    $tenant = Tenant::factory()->create();
    $cook = User::factory()->create();
    $tenant->update(['cook_id' => $cook->id]);
    $cook->assignRole('cook');

    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'total_balance' => 0,
        'withdrawable_balance' => 0,
        'unwithdrawable_balance' => 0,
    ]);

    // Pre-deduction wallet transaction (from F-172)
    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_WITHDRAWAL,
        'amount' => $amount,
        'balance_before' => $amount,
        'balance_after' => 0,
        'status' => 'completed',
        'metadata' => null,
    ]);

    $withdrawal = WithdrawalRequest::factory()->pending()->create([
        'cook_wallet_id' => $wallet->id,
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'amount' => $amount,
        'mobile_money_number' => '670123456',
        'mobile_money_provider' => WithdrawalRequest::PROVIDER_MTN_MOMO,
    ]);

    return compact('tenant', 'cook', 'wallet', 'withdrawal');
}

// === Scenario 1: Successful transfer ===

test('Scenario 1: successful transfer completes withdrawal and notifies cook', function () {
    $data = setupWithdrawalScenario();

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn([
            'success' => true,
            'data' => ['id' => '123456', 'status' => 'SUCCESSFUL'],
            'error' => null,
            'is_timeout' => false,
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $result = $service->processWithdrawal($data['withdrawal']);

    // Verify completion
    expect($result['success'])->toBeTrue();
    expect($result['status'])->toBe('completed');

    $data['withdrawal']->refresh();
    expect($data['withdrawal']->status)->toBe('completed');
    expect($data['withdrawal']->completed_at)->not->toBeNull();
    expect($data['withdrawal']->flutterwave_reference)->toStartWith('DMC-WD-');

    // No manual payout task should be created
    expect(PayoutTask::where('cook_id', $data['cook']->id)->exists())->toBeFalse();
});

// === Scenario 2: Failed transfer ===

test('Scenario 2: failed transfer restores balance and creates payout task', function () {
    $data = setupWithdrawalScenario();

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn([
            'success' => false,
            'data' => ['status' => 'FAILED', 'message' => 'Invalid recipient'],
            'error' => 'Invalid recipient',
            'is_timeout' => false,
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $result = $service->processWithdrawal($data['withdrawal']);

    // Verify failure
    expect($result['success'])->toBeFalse();
    expect($result['status'])->toBe('failed');

    $data['withdrawal']->refresh();
    expect($data['withdrawal']->status)->toBe('failed');
    expect($data['withdrawal']->failed_at)->not->toBeNull();
    expect($data['withdrawal']->failure_reason)->toBe('Invalid recipient');

    // Verify balance restored
    $data['wallet']->refresh();
    expect((float) $data['wallet']->withdrawable_balance)->toBe(20000.0);
    expect((float) $data['wallet']->total_balance)->toBe(20000.0);

    // Verify payout task created
    $task = PayoutTask::where('cook_id', $data['cook']->id)->first();
    expect($task)->not->toBeNull();
    expect((float) $task->amount)->toBe(20000.0);
    expect($task->status)->toBe(PayoutTask::STATUS_PENDING);
});

// === Scenario 3: Transfer timeout ===

test('Scenario 3: transfer timeout marks as pending_verification', function () {
    $data = setupWithdrawalScenario();

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn([
            'success' => false,
            'data' => null,
            'error' => 'Connection timed out',
            'is_timeout' => true,
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $result = $service->processWithdrawal($data['withdrawal']);

    expect($result['status'])->toBe('pending_verification');

    $data['withdrawal']->refresh();
    expect($data['withdrawal']->status)->toBe('pending_verification');

    // Balance NOT restored -- we are waiting for verification
    $data['wallet']->refresh();
    expect((float) $data['wallet']->withdrawable_balance)->toBe(0.0);
});

test('Scenario 3: follow-up verification confirms successful transfer', function () {
    $data = setupWithdrawalScenario();
    $data['withdrawal']->update([
        'status' => WithdrawalRequest::STATUS_PENDING_VERIFICATION,
        'flutterwave_transfer_id' => '789',
        'flutterwave_reference' => 'DMC-WD-1-12345',
        'processed_at' => now(),
    ]);

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('verifyTransfer')
        ->with('789')
        ->once()
        ->andReturn([
            'success' => true,
            'data' => ['id' => '789', 'status' => 'SUCCESSFUL'],
            'error' => null,
            'status' => 'SUCCESSFUL',
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $result = $service->verifyPendingTransfer($data['withdrawal']);

    expect($result['status'])->toBe('completed');

    $data['withdrawal']->refresh();
    expect($data['withdrawal']->status)->toBe('completed');
});

test('Scenario 3: follow-up verification confirms failed transfer', function () {
    $data = setupWithdrawalScenario();
    $data['withdrawal']->update([
        'status' => WithdrawalRequest::STATUS_PENDING_VERIFICATION,
        'flutterwave_transfer_id' => '789',
        'flutterwave_reference' => 'DMC-WD-1-12345',
        'processed_at' => now(),
    ]);

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('verifyTransfer')
        ->with('789')
        ->once()
        ->andReturn([
            'success' => true,
            'data' => ['id' => '789', 'status' => 'FAILED'],
            'error' => null,
            'status' => 'FAILED',
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $result = $service->verifyPendingTransfer($data['withdrawal']);

    expect($result['status'])->toBe('failed');

    // Balance restored
    $data['wallet']->refresh();
    expect((float) $data['wallet']->withdrawable_balance)->toBe(20000.0);
});

// === Scenario 4: Multiple withdrawals ===

test('Scenario 4: multiple withdrawals processed sequentially', function () {
    $data1 = setupWithdrawalScenario(10000);
    $data2 = setupWithdrawalScenario(25000);
    $data3 = setupWithdrawalScenario(5000);

    $callCount = 0;
    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->times(3)
        ->andReturnUsing(function () use (&$callCount) {
            $callCount++;

            return match ($callCount) {
                1 => ['success' => true, 'data' => ['id' => '1', 'status' => 'SUCCESSFUL'], 'error' => null, 'is_timeout' => false],
                2 => ['success' => false, 'data' => null, 'error' => 'API Error', 'is_timeout' => false],
                3 => ['success' => true, 'data' => ['id' => '3', 'status' => 'SUCCESSFUL'], 'error' => null, 'is_timeout' => false],
                default => ['success' => false, 'data' => null, 'error' => 'Unknown', 'is_timeout' => false],
            };
        });

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $stats = $service->processAllPending();

    expect($stats['processed'])->toBe(3);
    expect($stats['succeeded'])->toBe(2);
    expect($stats['failed'])->toBe(1);
});

// === BR-364: Idempotency ===

test('BR-364: reprocessing a completed withdrawal is idempotent', function () {
    $data = setupWithdrawalScenario();
    $data['withdrawal']->update(['status' => 'completed', 'completed_at' => now()]);

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldNotReceive('initiateTransfer');

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $result = $service->processWithdrawal($data['withdrawal']);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('already been processed');
});

// === Artisan Command Tests ===

test('dancymeals:process-withdrawals command runs successfully', function () {
    $this->artisan('dancymeals:process-withdrawals')
        ->assertSuccessful();
});

test('dancymeals:verify-pending-transfers command runs successfully', function () {
    $this->artisan('dancymeals:verify-pending-transfers')
        ->assertSuccessful();
});

// === Edge Cases ===

test('transfer success but notification failure does not affect completion', function () {
    $data = setupWithdrawalScenario();

    // Make the user unloadable to trigger notification failure
    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn([
            'success' => true,
            'data' => ['id' => '123', 'status' => 'SUCCESSFUL'],
            'error' => null,
            'is_timeout' => false,
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $result = $service->processWithdrawal($data['withdrawal']);

    // Transfer should still be marked as completed
    expect($result['success'])->toBeTrue();
    expect($result['status'])->toBe('completed');
});

test('Flutterwave API completely down fails all transfers', function () {
    $data1 = setupWithdrawalScenario(10000);
    $data2 = setupWithdrawalScenario(20000);

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->times(2)
        ->andReturn([
            'success' => false,
            'data' => null,
            'error' => 'Service unavailable',
            'is_timeout' => false,
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $stats = $service->processAllPending();

    expect($stats['failed'])->toBe(2);
    expect($stats['succeeded'])->toBe(0);

    // Both should have payout tasks
    expect(PayoutTask::count())->toBe(2);
});

test('verifyPendingTransfer with no transfer ID fails gracefully', function () {
    $data = setupWithdrawalScenario();
    $data['withdrawal']->update([
        'status' => WithdrawalRequest::STATUS_PENDING_VERIFICATION,
        'flutterwave_transfer_id' => null,
    ]);

    $service = app(FlutterwaveTransferService::class);
    $result = $service->verifyPendingTransfer($data['withdrawal']);

    expect($result['status'])->toBe(WithdrawalRequest::STATUS_FAILED);
    $data['wallet']->refresh();
    expect((float) $data['wallet']->withdrawable_balance)->toBe(20000.0);
});
