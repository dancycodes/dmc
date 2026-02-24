<?php

/**
 * F-169: Cook Wallet Dashboard -- Unit Tests
 *
 * Tests for CookWallet model, CookWalletService, and CookWalletController.
 * BR-311 through BR-322.
 */

use App\Models\CookWallet;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\CookWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
});

// =============================================
// CookWallet Model Tests
// =============================================

test('CookWallet can be created with factory', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    expect($wallet)->toBeInstanceOf(CookWallet::class)
        ->and($wallet->tenant_id)->toBe($tenant->id)
        ->and($wallet->user_id)->toBe($cook->id);
});

test('CookWallet belongs to tenant', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    expect($wallet->tenant)->toBeInstanceOf(Tenant::class)
        ->and($wallet->tenant->id)->toBe($tenant->id);
});

test('CookWallet belongs to user', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    expect($wallet->user)->toBeInstanceOf(User::class)
        ->and($wallet->user->id)->toBe($cook->id);
});

test('CookWallet formats total balance in XAF', function () {
    $wallet = CookWallet::factory()->make([
        'total_balance' => 50000,
        'currency' => 'XAF',
    ]);

    expect($wallet->formattedTotalBalance())->toBe('50,000 XAF');
});

test('CookWallet formats withdrawable balance in XAF', function () {
    $wallet = CookWallet::factory()->make([
        'withdrawable_balance' => 35000,
        'currency' => 'XAF',
    ]);

    expect($wallet->formattedWithdrawableBalance())->toBe('35,000 XAF');
});

test('CookWallet formats unwithdrawable balance in XAF', function () {
    $wallet = CookWallet::factory()->make([
        'unwithdrawable_balance' => 15000,
        'currency' => 'XAF',
    ]);

    expect($wallet->formattedUnwithdrawableBalance())->toBe('15,000 XAF');
});

test('CookWallet formats very large amounts correctly', function () {
    $wallet = CookWallet::factory()->make([
        'total_balance' => 1500000,
        'currency' => 'XAF',
    ]);

    expect($wallet->formattedTotalBalance())->toBe('1,500,000 XAF');
});

test('CookWallet hasWithdrawableBalance returns true when balance > 0', function () {
    $wallet = CookWallet::factory()->withdrawable(35000)->make();

    expect($wallet->hasWithdrawableBalance())->toBeTrue();
});

test('CookWallet hasWithdrawableBalance returns false when balance = 0', function () {
    $wallet = CookWallet::factory()->unwithdrawable(15000)->make();

    expect($wallet->hasWithdrawableBalance())->toBeFalse();
});

test('CookWallet getOrCreateForTenant creates wallet lazily', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $wallet = CookWallet::getOrCreateForTenant($tenant, $cook);

    expect($wallet)->toBeInstanceOf(CookWallet::class)
        ->and($wallet->tenant_id)->toBe($tenant->id)
        ->and($wallet->user_id)->toBe($cook->id)
        ->and((float) $wallet->total_balance)->toBe(0.0)
        ->and((float) $wallet->withdrawable_balance)->toBe(0.0)
        ->and((float) $wallet->unwithdrawable_balance)->toBe(0.0)
        ->and($wallet->currency)->toBe('XAF');
});

test('CookWallet getOrCreateForTenant returns existing wallet', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $existing = CookWallet::factory()->mixed(35000, 15000)->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    $wallet = CookWallet::getOrCreateForTenant($tenant, $cook);

    expect($wallet->id)->toBe($existing->id)
        ->and((float) $wallet->total_balance)->toBe(50000.0);
});

test('CookWallet factory states work correctly', function () {
    $empty = CookWallet::factory()->empty()->make();
    expect((float) $empty->total_balance)->toBe(0.0)
        ->and((float) $empty->withdrawable_balance)->toBe(0.0)
        ->and((float) $empty->unwithdrawable_balance)->toBe(0.0);

    $withdrawable = CookWallet::factory()->withdrawable(50000)->make();
    expect((float) $withdrawable->total_balance)->toBe(50000.0)
        ->and((float) $withdrawable->withdrawable_balance)->toBe(50000.0)
        ->and((float) $withdrawable->unwithdrawable_balance)->toBe(0.0);

    $unwithdrawable = CookWallet::factory()->unwithdrawable(15000)->make();
    expect((float) $unwithdrawable->total_balance)->toBe(15000.0)
        ->and((float) $unwithdrawable->withdrawable_balance)->toBe(0.0)
        ->and((float) $unwithdrawable->unwithdrawable_balance)->toBe(15000.0);

    $mixed = CookWallet::factory()->mixed(35000, 15000)->make();
    expect((float) $mixed->total_balance)->toBe(50000.0)
        ->and((float) $mixed->withdrawable_balance)->toBe(35000.0)
        ->and((float) $mixed->unwithdrawable_balance)->toBe(15000.0);
});

// =============================================
// CookWalletService Tests
// =============================================

test('service getWallet creates wallet lazily for new cook', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $service = new CookWalletService;
    $wallet = $service->getWallet($tenant, $cook);

    expect($wallet)->toBeInstanceOf(CookWallet::class)
        ->and((float) $wallet->total_balance)->toBe(0.0);
});

test('service getRecentTransactions returns last 10 transactions scoped to tenant', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    // Create 12 transactions for this tenant
    WalletTransaction::factory()->count(12)->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
    ]);

    // Create transactions for different tenant (should not appear)
    $otherTenant = Tenant::factory()->create();
    WalletTransaction::factory()->count(3)->create([
        'user_id' => $cook->id,
        'tenant_id' => $otherTenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
    ]);

    $service = new CookWalletService;
    $transactions = $service->getRecentTransactions($tenant, $cook);

    expect($transactions)->toHaveCount(10);
    $transactions->each(fn ($txn) => expect($txn->tenant_id)->toBe($tenant->id));
});

test('service getRecentTransactions returns empty collection when no transactions', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $service = new CookWalletService;
    $transactions = $service->getRecentTransactions($tenant, $cook);

    expect($transactions)->toHaveCount(0);
});

test('service getEarningsSummary returns correct totals', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    CookWallet::factory()->mixed(30000, 10000)->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    // Payment credits
    WalletTransaction::factory()->count(2)->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
        'amount' => 25000,
        'status' => 'completed',
    ]);

    // Withdrawal
    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_WITHDRAWAL,
        'amount' => 15000,
        'status' => 'completed',
    ]);

    $service = new CookWalletService;
    $summary = $service->getEarningsSummary($tenant, $cook);

    expect($summary['total_earned'])->toBe(50000.0)
        ->and($summary['total_withdrawn'])->toBe(15000.0)
        ->and($summary['pending'])->toBe(10000.0);
});

test('service getMonthlyEarnings returns data for past 6 months', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    // Create transaction for current month
    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
        'amount' => 30000,
        'status' => 'completed',
        'created_at' => now(),
    ]);

    // Create transaction for 2 months ago
    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
        'amount' => 20000,
        'status' => 'completed',
        'created_at' => now()->subMonths(2),
    ]);

    $service = new CookWalletService;
    $earnings = $service->getMonthlyEarnings($tenant, $cook);

    expect($earnings)->toHaveCount(6);

    // Current month should have 30000
    $lastMonth = end($earnings);
    expect($lastMonth['amount'])->toBe(30000.0);
});

test('service getMonthlyEarnings returns zeros when no earnings', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $service = new CookWalletService;
    $earnings = $service->getMonthlyEarnings($tenant, $cook);

    expect($earnings)->toHaveCount(6);
    foreach ($earnings as $month) {
        expect($month['amount'])->toBe(0.0);
    }
});

test('service getDashboardData returns all required data', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $service = new CookWalletService;
    $data = $service->getDashboardData($tenant, $cook);

    expect($data)
        ->toHaveKey('wallet')
        ->toHaveKey('recentTransactions')
        ->toHaveKey('earningsSummary')
        ->toHaveKey('monthlyEarnings')
        ->toHaveKey('totalTransactionCount')
        ->toHaveKey('isCook');

    expect($data['wallet'])->toBeInstanceOf(CookWallet::class)
        ->and($data['isCook'])->toBeTrue()
        ->and($data['totalTransactionCount'])->toBe(0);
});

test('service getDashboardData sets isCook false for managers', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $manager = createUser('manager');

    $service = new CookWalletService;
    $data = $service->getDashboardData($tenant, $manager);

    expect($data['isCook'])->toBeFalse();
});

test('service formatXAF formats amounts correctly', function () {
    expect(CookWalletService::formatXAF(50000))->toBe('50,000 XAF')
        ->and(CookWalletService::formatXAF(0))->toBe('0 XAF')
        ->and(CookWalletService::formatXAF(1500000))->toBe('1,500,000 XAF');
});

test('service recalculateBalances computes correct values from transactions', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $wallet = CookWallet::factory()->empty()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    // Create withdrawable credit (cleared)
    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
        'amount' => 40000,
        'is_withdrawable' => true,
        'withdrawable_at' => now()->subHour(),
        'status' => 'completed',
    ]);

    // Create unwithdrawable credit (not yet cleared)
    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
        'amount' => 15000,
        'is_withdrawable' => false,
        'withdrawable_at' => now()->addHours(2),
        'status' => 'completed',
    ]);

    // Create a withdrawal
    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_WITHDRAWAL,
        'amount' => 5000,
        'status' => 'completed',
    ]);

    $service = new CookWalletService;
    $updated = $service->recalculateBalances($wallet);

    expect((float) $updated->total_balance)->toBe(50000.0)
        ->and((float) $updated->withdrawable_balance)->toBe(35000.0)
        ->and((float) $updated->unwithdrawable_balance)->toBe(15000.0);
});

// =============================================
// Controller Access Tests
// =============================================

test('cook can access wallet dashboard', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($cook);

    $response = $this->get("https://{$tenant->slug}.{$mainDomain}/dashboard/wallet");

    $response->assertStatus(200);
});

test('manager with can-manage-cook-wallet permission can access wallet', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $manager = createUser('manager');
    $manager->givePermissionTo('can-manage-cook-wallet');

    \Illuminate\Support\Facades\DB::table('tenant_managers')->insert([
        'tenant_id' => $tenant->id,
        'user_id' => $manager->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($manager);

    $response = $this->get("https://{$tenant->slug}.{$mainDomain}/dashboard/wallet");

    $response->assertStatus(200);
});

test('manager without can-manage-cook-wallet permission gets 403', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $manager = createUser('manager');

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($manager);

    $response = $this->get("https://{$tenant->slug}.{$mainDomain}/dashboard/wallet");

    $response->assertStatus(403);
});

test('unauthenticated user is redirected from wallet', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->get("https://{$tenant->slug}.{$mainDomain}/dashboard/wallet");

    $response->assertRedirect();
});
