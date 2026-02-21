<?php

use App\Models\CookWallet;
use App\Models\PayoutTask;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Notifications\WithdrawalFailedAdminNotification;
use App\Notifications\WithdrawalProcessedNotification;
use App\Services\FlutterwaveService;
use App\Services\FlutterwaveTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seedRolesAndPermissions();
    Notification::fake();
});

/**
 * Helper to create a pending withdrawal with related models.
 *
 * @return array{tenant: Tenant, cook: User, wallet: CookWallet, withdrawal: WithdrawalRequest}
 */
function createPendingWithdrawal(float $amount = 20000): array
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

    // Create the deduction transaction (as done by F-172 on submission)
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

// === WithdrawalRequest Model Tests ===

test('withdrawal request has pending_verification status constant', function () {
    expect(WithdrawalRequest::STATUS_PENDING_VERIFICATION)->toBe('pending_verification');
    expect(WithdrawalRequest::STATUSES)->toContain('pending_verification');
});

test('canBeProcessed returns true only for pending status', function () {
    $withdrawal = WithdrawalRequest::factory()->pending()->make();
    expect($withdrawal->canBeProcessed())->toBeTrue();

    $withdrawal = WithdrawalRequest::factory()->processing()->make();
    expect($withdrawal->canBeProcessed())->toBeFalse();

    $withdrawal = WithdrawalRequest::factory()->completed()->make();
    expect($withdrawal->canBeProcessed())->toBeFalse();

    $withdrawal = WithdrawalRequest::factory()->failed()->make();
    expect($withdrawal->canBeProcessed())->toBeFalse();
});

test('isPendingVerification returns correct result', function () {
    $withdrawal = WithdrawalRequest::factory()->pendingVerification()->make();
    expect($withdrawal->isPendingVerification())->toBeTrue();

    $withdrawal = WithdrawalRequest::factory()->pending()->make();
    expect($withdrawal->isPendingVerification())->toBeFalse();
});

test('generateIdempotencyKey returns consistent key', function () {
    $withdrawal = WithdrawalRequest::factory()->pending()->create(
        createPendingWithdrawal()['withdrawal']->only(['cook_wallet_id', 'tenant_id', 'user_id']) + [
            'amount' => 15000,
            'mobile_money_number' => '670123456',
        ]
    );

    $key1 = $withdrawal->generateIdempotencyKey();
    $key2 = $withdrawal->generateIdempotencyKey();

    expect($key1)->toBe($key2);
    expect($key1)->toStartWith('DMC-WD-');
});

test('withdrawal has flutterwave_response cast as array', function () {
    $data = createPendingWithdrawal();
    $data['withdrawal']->update([
        'flutterwave_response' => ['status' => 'success', 'id' => 12345],
    ]);

    $data['withdrawal']->refresh();
    expect($data['withdrawal']->flutterwave_response)->toBeArray();
    expect($data['withdrawal']->flutterwave_response['status'])->toBe('success');
});

test('withdrawal has completed_at and failed_at datetime casts', function () {
    $data = createPendingWithdrawal();
    $data['withdrawal']->update([
        'completed_at' => now(),
        'failed_at' => now(),
    ]);

    $data['withdrawal']->refresh();
    expect($data['withdrawal']->completed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($data['withdrawal']->failed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

// === FlutterwaveTransferService Tests ===

test('processWithdrawal skips already processed withdrawal (BR-364 idempotency)', function () {
    $data = createPendingWithdrawal();
    $data['withdrawal']->update(['status' => WithdrawalRequest::STATUS_COMPLETED]);

    $service = app(FlutterwaveTransferService::class);
    $result = $service->processWithdrawal($data['withdrawal']);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('already been processed');
});

test('processWithdrawal handles successful transfer (BR-358)', function () {
    $data = createPendingWithdrawal();

    // Mock FlutterwaveService
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

    expect($result['success'])->toBeTrue();
    expect($result['status'])->toBe(WithdrawalRequest::STATUS_COMPLETED);

    $data['withdrawal']->refresh();
    expect($data['withdrawal']->status)->toBe(WithdrawalRequest::STATUS_COMPLETED);
    expect($data['withdrawal']->completed_at)->not->toBeNull();
    expect($data['withdrawal']->flutterwave_reference)->not->toBeNull();

    // Cook should be notified
    Notification::assertSentTo($data['cook'], WithdrawalProcessedNotification::class);
});

test('processWithdrawal handles failed transfer (BR-359)', function () {
    $data = createPendingWithdrawal();

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn([
            'success' => false,
            'data' => ['status' => 'FAILED'],
            'error' => 'Invalid recipient',
            'is_timeout' => false,
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $result = $service->processWithdrawal($data['withdrawal']);

    expect($result['success'])->toBeFalse();
    expect($result['status'])->toBe(WithdrawalRequest::STATUS_FAILED);

    $data['withdrawal']->refresh();
    expect($data['withdrawal']->status)->toBe(WithdrawalRequest::STATUS_FAILED);
    expect($data['withdrawal']->failed_at)->not->toBeNull();
    expect($data['withdrawal']->failure_reason)->toBe('Invalid recipient');

    // Balance should be restored
    $data['wallet']->refresh();
    expect((float) $data['wallet']->withdrawable_balance)->toBe(20000.0);
    expect((float) $data['wallet']->total_balance)->toBe(20000.0);
});

test('processWithdrawal creates manual payout task on failure (BR-363)', function () {
    $data = createPendingWithdrawal();

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn([
            'success' => false,
            'data' => null,
            'error' => 'Service unavailable',
            'is_timeout' => false,
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $service->processWithdrawal($data['withdrawal']);

    // Verify manual payout task was created
    $task = PayoutTask::where('cook_id', $data['cook']->id)
        ->where('tenant_id', $data['tenant']->id)
        ->first();

    expect($task)->not->toBeNull();
    expect((float) $task->amount)->toBe(20000.0);
    expect($task->status)->toBe(PayoutTask::STATUS_PENDING);
    expect($task->failure_reason)->toBe('Service unavailable');
    expect($task->mobile_money_number)->toBe('670123456');
});

test('processWithdrawal handles transfer timeout (BR-360)', function () {
    $data = createPendingWithdrawal();

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

    expect($result['status'])->toBe(WithdrawalRequest::STATUS_PENDING_VERIFICATION);

    $data['withdrawal']->refresh();
    expect($data['withdrawal']->status)->toBe(WithdrawalRequest::STATUS_PENDING_VERIFICATION);

    // Balance should NOT be restored for pending_verification
    $data['wallet']->refresh();
    expect((float) $data['wallet']->withdrawable_balance)->toBe(0.0);
});

test('processWithdrawal notifies cook on failure (N-014)', function () {
    $data = createPendingWithdrawal();

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn([
            'success' => false,
            'data' => null,
            'error' => 'Invalid recipient',
            'is_timeout' => false,
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $service->processWithdrawal($data['withdrawal']);

    Notification::assertSentTo($data['cook'], WithdrawalProcessedNotification::class);
});

test('processWithdrawal notifies admin on failure (N-014)', function () {
    $data = createPendingWithdrawal();

    // Create an admin user
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn([
            'success' => false,
            'data' => null,
            'error' => 'Invalid recipient',
            'is_timeout' => false,
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $service->processWithdrawal($data['withdrawal']);

    Notification::assertSentTo($admin, WithdrawalFailedAdminNotification::class);
});

test('verifyPendingTransfer resolves successful transfer', function () {
    $data = createPendingWithdrawal();
    $data['withdrawal']->update([
        'status' => WithdrawalRequest::STATUS_PENDING_VERIFICATION,
        'flutterwave_transfer_id' => '123456',
        'flutterwave_reference' => 'DMC-WD-1-12345',
    ]);

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('verifyTransfer')
        ->with('123456')
        ->once()
        ->andReturn([
            'success' => true,
            'data' => ['id' => '123456', 'status' => 'SUCCESSFUL'],
            'error' => null,
            'status' => 'SUCCESSFUL',
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $result = $service->verifyPendingTransfer($data['withdrawal']);

    expect($result['status'])->toBe(WithdrawalRequest::STATUS_COMPLETED);

    $data['withdrawal']->refresh();
    expect($data['withdrawal']->status)->toBe(WithdrawalRequest::STATUS_COMPLETED);
});

test('verifyPendingTransfer resolves failed transfer', function () {
    $data = createPendingWithdrawal();
    $data['withdrawal']->update([
        'status' => WithdrawalRequest::STATUS_PENDING_VERIFICATION,
        'flutterwave_transfer_id' => '123456',
        'flutterwave_reference' => 'DMC-WD-1-12345',
    ]);

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('verifyTransfer')
        ->with('123456')
        ->once()
        ->andReturn([
            'success' => true,
            'data' => ['id' => '123456', 'status' => 'FAILED'],
            'error' => null,
            'status' => 'FAILED',
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $result = $service->verifyPendingTransfer($data['withdrawal']);

    expect($result['status'])->toBe(WithdrawalRequest::STATUS_FAILED);

    // Balance restored and payout task created
    $data['wallet']->refresh();
    expect((float) $data['wallet']->withdrawable_balance)->toBe(20000.0);

    expect(PayoutTask::where('cook_id', $data['cook']->id)->exists())->toBeTrue();
});

test('processAllPending processes multiple withdrawals sequentially', function () {
    // Create multiple pending withdrawals for different cooks
    $data1 = createPendingWithdrawal(10000);
    $data2 = createPendingWithdrawal(15000);

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->twice()
        ->andReturn([
            'success' => true,
            'data' => ['id' => '111', 'status' => 'SUCCESSFUL'],
            'error' => null,
            'is_timeout' => false,
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $stats = $service->processAllPending();

    expect($stats['processed'])->toBe(2);
    expect($stats['succeeded'])->toBe(2);
    expect($stats['failed'])->toBe(0);
});

test('processAllPending handles mixed success and failure', function () {
    $data1 = createPendingWithdrawal(10000);
    $data2 = createPendingWithdrawal(15000);

    $callCount = 0;
    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->twice()
        ->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return [
                    'success' => true,
                    'data' => ['id' => '111', 'status' => 'SUCCESSFUL'],
                    'error' => null,
                    'is_timeout' => false,
                ];
            }

            return [
                'success' => false,
                'data' => null,
                'error' => 'API Error',
                'is_timeout' => false,
            ];
        });

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $stats = $service->processAllPending();

    expect($stats['processed'])->toBe(2);
    expect($stats['succeeded'])->toBe(1);
    expect($stats['failed'])->toBe(1);
});

test('withdrawal creates reversal wallet transaction on failure', function () {
    $data = createPendingWithdrawal();

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn([
            'success' => false,
            'data' => null,
            'error' => 'Failed',
            'is_timeout' => false,
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $service->processWithdrawal($data['withdrawal']);

    // Check reversal transaction was created
    $reversalTx = WalletTransaction::where('user_id', $data['cook']->id)
        ->where('tenant_id', $data['tenant']->id)
        ->where('type', WalletTransaction::TYPE_REFUND)
        ->where('description', 'like', '%reversal%')
        ->first();

    expect($reversalTx)->not->toBeNull();
    expect((float) $reversalTx->amount)->toBe(20000.0);
});

// === Notification Tests ===

test('WithdrawalProcessedNotification has correct success content', function () {
    $data = createPendingWithdrawal();
    $notification = new WithdrawalProcessedNotification($data['withdrawal'], true);

    expect($notification->getTitle($data['cook']))->toBe(__('Withdrawal Sent'));
    expect($notification->getBody($data['cook']))->toContain('20,000 XAF');
    expect($notification->getBody($data['cook']))->toContain('MTN MoMo');
});

test('WithdrawalProcessedNotification has correct failure content', function () {
    $data = createPendingWithdrawal();
    $notification = new WithdrawalProcessedNotification($data['withdrawal'], false);

    expect($notification->getTitle($data['cook']))->toBe(__('Withdrawal Failed'));
    expect($notification->getBody($data['cook']))->toContain('20,000 XAF');
    expect($notification->getBody($data['cook']))->toContain('returned to your wallet');
});

test('WithdrawalFailedAdminNotification has correct content', function () {
    $data = createPendingWithdrawal();
    $data['withdrawal']->update(['failure_reason' => 'Invalid recipient']);

    $notification = new WithdrawalFailedAdminNotification($data['withdrawal']);

    expect($notification->getTitle($data['cook']))->toContain('Manual Action Needed');
    expect($notification->getBody($data['cook']))->toContain('20,000 XAF');
    expect($notification->getActionUrl($data['cook']))->toContain('/vault-entry/payouts');
});

test('WithdrawalProcessedNotification action URL points to wallet dashboard', function () {
    $data = createPendingWithdrawal();
    $notification = new WithdrawalProcessedNotification($data['withdrawal'], true);

    $url = $notification->getActionUrl($data['cook']);
    expect($url)->toContain('/dashboard/wallet');
});

// === FlutterwaveService Transfer Method Tests ===

test('FlutterwaveService getTransferBankCode maps providers correctly', function () {
    $service = new FlutterwaveService;

    // Use reflection to access private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getTransferBankCode');
    $method->setAccessible(true);

    expect($method->invoke($service, 'mtn_momo'))->toBe('MPS');
    expect($method->invoke($service, 'orange_money'))->toBe('FMM');
    expect($method->invoke($service, 'unknown'))->toBe('MPS');
});

// === PayoutTask Integration Tests ===

test('failed withdrawal maps provider name to PayoutTask format', function () {
    $data = createPendingWithdrawal();

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn([
            'success' => false,
            'data' => null,
            'error' => 'Failed',
            'is_timeout' => false,
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $service->processWithdrawal($data['withdrawal']);

    $task = PayoutTask::where('cook_id', $data['cook']->id)->first();
    expect($task->payment_method)->toBe('mtn_mobile_money');
});

test('failed withdrawal with Orange Money maps correctly', function () {
    $data = createPendingWithdrawal();
    $data['withdrawal']->update([
        'mobile_money_provider' => WithdrawalRequest::PROVIDER_ORANGE_MONEY,
    ]);

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn([
            'success' => false,
            'data' => null,
            'error' => 'Failed',
            'is_timeout' => false,
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $service->processWithdrawal($data['withdrawal']);

    $task = PayoutTask::where('cook_id', $data['cook']->id)->first();
    expect($task->payment_method)->toBe('orange_money');
});

// === Activity Logging Tests ===

test('transfer attempt is logged (BR-361)', function () {
    $data = createPendingWithdrawal();

    $mock = Mockery::mock(FlutterwaveService::class);
    $mock->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn([
            'success' => true,
            'data' => ['id' => '999', 'status' => 'SUCCESSFUL'],
            'error' => null,
            'is_timeout' => false,
        ]);

    app()->instance(FlutterwaveService::class, $mock);

    $service = app(FlutterwaveTransferService::class);
    $service->processWithdrawal($data['withdrawal']);

    // Check activity log entries
    $activities = \Spatie\Activitylog\Models\Activity::where('subject_type', WithdrawalRequest::class)
        ->where('subject_id', $data['withdrawal']->id)
        ->get();

    expect($activities->count())->toBeGreaterThanOrEqual(2); // initiated + completed
});
