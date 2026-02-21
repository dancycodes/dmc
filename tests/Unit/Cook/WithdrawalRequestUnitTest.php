<?php

/**
 * F-172: Cook Withdrawal Request -- Unit Tests
 *
 * Tests for WithdrawalRequest model, WithdrawalRequestService,
 * and WalletController withdrawal methods.
 * BR-344 through BR-355.
 */

use App\Models\CookWallet;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\PlatformSettingService;
use App\Services\WithdrawalRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
    // Clear platform setting cache to ensure fresh values
    app(PlatformSettingService::class)->clearCache();
});

// =============================================
// WithdrawalRequest Model Tests
// =============================================

test('WithdrawalRequest can be created with factory', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    $withdrawal = WithdrawalRequest::factory()->create([
        'cook_wallet_id' => $wallet->id,
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    expect($withdrawal)->toBeInstanceOf(WithdrawalRequest::class)
        ->and($withdrawal->status)->toBe(WithdrawalRequest::STATUS_PENDING);
});

test('WithdrawalRequest belongs to cook wallet', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    $withdrawal = WithdrawalRequest::factory()->create([
        'cook_wallet_id' => $wallet->id,
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    expect($withdrawal->cookWallet)->toBeInstanceOf(CookWallet::class)
        ->and($withdrawal->cookWallet->id)->toBe($wallet->id);
});

test('WithdrawalRequest belongs to tenant', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    $withdrawal = WithdrawalRequest::factory()->create([
        'cook_wallet_id' => $wallet->id,
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    expect($withdrawal->tenant)->toBeInstanceOf(Tenant::class);
});

test('WithdrawalRequest belongs to user', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    $withdrawal = WithdrawalRequest::factory()->create([
        'cook_wallet_id' => $wallet->id,
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    expect($withdrawal->user)->toBeInstanceOf(User::class);
});

test('WithdrawalRequest formats amount correctly', function () {
    $withdrawal = WithdrawalRequest::factory()->make([
        'amount' => 25000,
        'currency' => 'XAF',
    ]);

    expect($withdrawal->formattedAmount())->toBe('25,000 XAF');
});

test('WithdrawalRequest returns correct provider label', function () {
    $mtn = WithdrawalRequest::factory()->make(['mobile_money_provider' => 'mtn_momo']);
    $orange = WithdrawalRequest::factory()->make(['mobile_money_provider' => 'orange_money']);

    expect($mtn->providerLabel())->toBe('MTN MoMo')
        ->and($orange->providerLabel())->toBe('Orange Money');
});

test('WithdrawalRequest status checks work', function () {
    $pending = WithdrawalRequest::factory()->make(['status' => WithdrawalRequest::STATUS_PENDING]);
    $completed = WithdrawalRequest::factory()->make(['status' => WithdrawalRequest::STATUS_COMPLETED]);
    $failed = WithdrawalRequest::factory()->make(['status' => WithdrawalRequest::STATUS_FAILED]);

    expect($pending->isPending())->toBeTrue()
        ->and($pending->isCompleted())->toBeFalse()
        ->and($completed->isCompleted())->toBeTrue()
        ->and($failed->isFailed())->toBeTrue();
});

test('WithdrawalRequest factory states work correctly', function () {
    $pending = WithdrawalRequest::factory()->pending()->make();
    $processing = WithdrawalRequest::factory()->processing()->make();
    $completed = WithdrawalRequest::factory()->completed()->make();
    $failed = WithdrawalRequest::factory()->failed()->make();

    expect($pending->status)->toBe('pending')
        ->and($processing->status)->toBe('processing')
        ->and($completed->status)->toBe('completed')
        ->and($completed->processed_at)->not->toBeNull()
        ->and($failed->status)->toBe('failed')
        ->and($failed->failure_reason)->not->toBeNull();
});

test('WithdrawalRequest scopes filter correctly', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    WithdrawalRequest::factory()->count(3)->create([
        'cook_wallet_id' => $wallet->id,
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'status' => WithdrawalRequest::STATUS_PENDING,
    ]);

    WithdrawalRequest::factory()->count(2)->create([
        'cook_wallet_id' => $wallet->id,
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'status' => WithdrawalRequest::STATUS_COMPLETED,
    ]);

    expect(WithdrawalRequest::forTenant($tenant->id)->count())->toBe(5)
        ->and(WithdrawalRequest::forUser($cook->id)->count())->toBe(5)
        ->and(WithdrawalRequest::withStatus(WithdrawalRequest::STATUS_PENDING)->count())->toBe(3)
        ->and(WithdrawalRequest::withStatus(WithdrawalRequest::STATUS_COMPLETED)->count())->toBe(2);
});

// =============================================
// WithdrawalRequestService Tests
// =============================================

test('service returns correct form data', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id, 'whatsapp' => '670123456']);

    CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'withdrawable_balance' => 35000,
    ]);

    $service = app(WithdrawalRequestService::class);
    $data = $service->getWithdrawFormData($tenant, $cook);

    expect($data['wallet'])->toBeInstanceOf(CookWallet::class)
        ->and($data['minAmount'])->toBe(1000)
        ->and($data['maxDailyAmount'])->toBe(500000)
        ->and($data['todayWithdrawn'])->toBe(0.0)
        ->and($data['defaultPhone'])->toBe('670123456')
        ->and($data['maxWithdrawable'])->toBe(35000.0);
});

test('BR-345: minimum withdrawal amount enforced', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'withdrawable_balance' => 50000,
        'total_balance' => 50000,
    ]);

    $service = app(WithdrawalRequestService::class);
    $result = $service->submitWithdrawal($tenant, $cook, [
        'amount' => 500,
        'mobile_money_number' => '670123456',
        'mobile_money_provider' => 'mtn_momo',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('1,000');
});

test('BR-344: cannot withdraw more than withdrawable balance', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'withdrawable_balance' => 10000,
        'total_balance' => 10000,
    ]);

    $service = app(WithdrawalRequestService::class);
    $result = $service->submitWithdrawal($tenant, $cook, [
        'amount' => 15000,
        'mobile_money_number' => '670123456',
        'mobile_money_provider' => 'mtn_momo',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('10,000');
});

test('BR-346: daily withdrawal limit enforced', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'withdrawable_balance' => 600000,
        'total_balance' => 600000,
    ]);

    // Create existing withdrawal today for 500,000
    WithdrawalRequest::factory()->create([
        'cook_wallet_id' => $wallet->id,
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'amount' => 500000,
        'status' => WithdrawalRequest::STATUS_COMPLETED,
        'requested_at' => now(),
    ]);

    $service = app(WithdrawalRequestService::class);
    $result = $service->submitWithdrawal($tenant, $cook, [
        'amount' => 20000,
        'mobile_money_number' => '670123456',
        'mobile_money_provider' => 'mtn_momo',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('500,000');
});

test('BR-351: successful withdrawal creates pending record', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'withdrawable_balance' => 35000,
        'total_balance' => 35000,
    ]);

    $service = app(WithdrawalRequestService::class);
    $result = $service->submitWithdrawal($tenant, $cook, [
        'amount' => 20000,
        'mobile_money_number' => '670123456',
        'mobile_money_provider' => 'mtn_momo',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['withdrawal'])->toBeInstanceOf(WithdrawalRequest::class)
        ->and($result['withdrawal']->status)->toBe(WithdrawalRequest::STATUS_PENDING)
        ->and((float) $result['withdrawal']->amount)->toBe(20000.0);
});

test('BR-352: withdrawable balance decremented on submission', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'withdrawable_balance' => 35000,
        'total_balance' => 35000,
    ]);

    $service = app(WithdrawalRequestService::class);
    $service->submitWithdrawal($tenant, $cook, [
        'amount' => 20000,
        'mobile_money_number' => '670123456',
        'mobile_money_provider' => 'mtn_momo',
    ]);

    $wallet = CookWallet::where('tenant_id', $tenant->id)
        ->where('user_id', $cook->id)
        ->first();

    expect((float) $wallet->withdrawable_balance)->toBe(15000.0)
        ->and((float) $wallet->total_balance)->toBe(15000.0);
});

test('BR-352: wallet transaction created for withdrawal', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'withdrawable_balance' => 35000,
        'total_balance' => 35000,
    ]);

    $service = app(WithdrawalRequestService::class);
    $service->submitWithdrawal($tenant, $cook, [
        'amount' => 20000,
        'mobile_money_number' => '670123456',
        'mobile_money_provider' => 'mtn_momo',
    ]);

    $txn = WalletTransaction::where('user_id', $cook->id)
        ->where('type', WalletTransaction::TYPE_WITHDRAWAL)
        ->first();

    expect($txn)->not->toBeNull()
        ->and((float) $txn->amount)->toBe(20000.0)
        ->and((float) $txn->balance_before)->toBe(35000.0)
        ->and((float) $txn->balance_after)->toBe(15000.0);
});

test('BR-354: withdrawal activity logged', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'withdrawable_balance' => 35000,
        'total_balance' => 35000,
    ]);

    $service = app(WithdrawalRequestService::class);
    $service->submitWithdrawal($tenant, $cook, [
        'amount' => 20000,
        'mobile_money_number' => '670123456',
        'mobile_money_provider' => 'mtn_momo',
    ]);

    $log = \Spatie\Activitylog\Models\Activity::where('log_name', 'withdrawal_requests')
        ->where('causer_id', $cook->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toBe('Submitted withdrawal request')
        ->and((float) $log->properties['amount'])->toBe(20000.0);
});

test('full balance withdrawal works', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'withdrawable_balance' => 35000,
        'total_balance' => 35000,
    ]);

    $service = app(WithdrawalRequestService::class);
    $result = $service->submitWithdrawal($tenant, $cook, [
        'amount' => 35000,
        'mobile_money_number' => '670123456',
        'mobile_money_provider' => 'mtn_momo',
    ]);

    expect($result['success'])->toBeTrue();

    $wallet = CookWallet::where('tenant_id', $tenant->id)
        ->where('user_id', $cook->id)
        ->first();

    expect((float) $wallet->withdrawable_balance)->toBe(0.0);
});

test('decimal amount rejected', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'withdrawable_balance' => 35000,
        'total_balance' => 35000,
    ]);

    $service = app(WithdrawalRequestService::class);
    $result = $service->submitWithdrawal($tenant, $cook, [
        'amount' => 1000.50,
        'mobile_money_number' => '670123456',
        'mobile_money_provider' => 'mtn_momo',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('whole number');
});

test('service detects MTN provider from phone number', function () {
    $service = app(WithdrawalRequestService::class);

    expect($service->detectProvider('670123456'))->toBe('mtn_momo')
        ->and($service->detectProvider('680123456'))->toBe('mtn_momo')
        ->and($service->detectProvider('650123456'))->toBe('mtn_momo')
        ->and($service->detectProvider('+237670123456'))->toBe('mtn_momo');
});

test('service detects Orange provider from phone number', function () {
    $service = app(WithdrawalRequestService::class);

    expect($service->detectProvider('690123456'))->toBe('orange_money')
        ->and($service->detectProvider('655123456'))->toBe('orange_money')
        ->and($service->detectProvider('+237690123456'))->toBe('orange_money');
});

test('service validates mobile money number format', function () {
    $service = app(WithdrawalRequestService::class);

    expect($service->isValidMobileMoneyNumber('670123456'))->toBeTrue()
        ->and($service->isValidMobileMoneyNumber('+237670123456'))->toBeTrue()
        ->and($service->isValidMobileMoneyNumber('237670123456'))->toBeTrue()
        ->and($service->isValidMobileMoneyNumber('12345'))->toBeFalse()
        ->and($service->isValidMobileMoneyNumber('170123456'))->toBeFalse();
});

test('service normalizes phone numbers', function () {
    $service = app(WithdrawalRequestService::class);

    expect($service->normalizePhone('+237670123456'))->toBe('670123456')
        ->and($service->normalizePhone('237670123456'))->toBe('670123456')
        ->and($service->normalizePhone('670 123 456'))->toBe('670123456')
        ->and($service->normalizePhone('670-123-456'))->toBe('670123456');
});

test('BR-347: daily total only counts today withdrawals', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    // Yesterday withdrawal should not count
    WithdrawalRequest::factory()->create([
        'cook_wallet_id' => $wallet->id,
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'amount' => 50000,
        'status' => WithdrawalRequest::STATUS_COMPLETED,
        'requested_at' => now()->subDay(),
    ]);

    // Today withdrawal
    WithdrawalRequest::factory()->create([
        'cook_wallet_id' => $wallet->id,
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'amount' => 10000,
        'status' => WithdrawalRequest::STATUS_PENDING,
        'requested_at' => now(),
    ]);

    $service = app(WithdrawalRequestService::class);
    $todayTotal = $service->getTodayWithdrawalTotal($tenant, $cook);

    expect($todayTotal)->toBe(10000.0);
});

test('platform settings configure min and max withdrawal amounts', function () {
    $settingService = app(PlatformSettingService::class);

    expect($settingService->getMinWithdrawalAmount())->toBe(1000)
        ->and($settingService->getMaxDailyWithdrawalAmount())->toBe(500000);
});

test('custom platform settings are respected', function () {
    PlatformSetting::query()->updateOrCreate(
        ['key' => 'min_withdrawal_amount'],
        ['value' => '2000', 'type' => 'integer', 'group' => 'orders']
    );
    app(PlatformSettingService::class)->clearCache();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
        'withdrawable_balance' => 50000,
        'total_balance' => 50000,
    ]);

    $service = app(WithdrawalRequestService::class);
    $result = $service->submitWithdrawal($tenant, $cook, [
        'amount' => 1500,
        'mobile_money_number' => '670123456',
        'mobile_money_provider' => 'mtn_momo',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('2,000');
});
