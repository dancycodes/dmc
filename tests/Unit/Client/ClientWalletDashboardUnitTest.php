<?php

/**
 * F-166: Client Wallet Dashboard — Unit Tests
 *
 * Tests wallet model, service, controller, and business rules.
 * BR-280: Each client has one wallet with a single balance.
 * BR-281: Wallet balance displayed in XAF format.
 * BR-282: Wallet balance cannot be negative.
 * BR-284: Recent transactions shows last 10.
 * BR-285: Link to full transaction history (F-164).
 * BR-287: If wallet payment disabled, a note indicates this.
 * BR-288: Authentication required.
 */

use App\Models\ClientWallet;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\ClientWalletService;
use App\Services\PlatformSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ============================================================
// ClientWallet Model Tests
// ============================================================

test('client wallet belongs to user', function () {
    $user = User::factory()->create();
    $wallet = ClientWallet::factory()->create(['user_id' => $user->id]);

    expect($wallet->user)->toBeInstanceOf(User::class)
        ->and($wallet->user->id)->toBe($user->id);
});

test('user has one client wallet', function () {
    $user = User::factory()->create();
    $wallet = ClientWallet::factory()->create(['user_id' => $user->id]);

    expect($user->clientWallet)->toBeInstanceOf(ClientWallet::class)
        ->and($user->clientWallet->id)->toBe($wallet->id);
});

test('wallet has formatted balance in XAF', function () {
    $wallet = ClientWallet::factory()->withBalance(5000)->create();

    expect($wallet->formattedBalance())->toBe('5,000 XAF');
});

test('wallet with zero balance formats correctly', function () {
    $wallet = ClientWallet::factory()->zeroBalance()->create();

    expect($wallet->formattedBalance())->toBe('0 XAF');
});

test('wallet with large balance formats with commas', function () {
    $wallet = ClientWallet::factory()->withBalance(1250000)->create();

    expect($wallet->formattedBalance())->toBe('1,250,000 XAF');
});

test('hasBalance returns true for positive balance', function () {
    $wallet = ClientWallet::factory()->withBalance(5000)->create();

    expect($wallet->hasBalance())->toBeTrue();
});

test('hasBalance returns false for zero balance', function () {
    $wallet = ClientWallet::factory()->zeroBalance()->create();

    expect($wallet->hasBalance())->toBeFalse();
});

test('getOrCreateForUser creates wallet on first access', function () {
    $user = User::factory()->create();

    expect(ClientWallet::where('user_id', $user->id)->count())->toBe(0);

    $wallet = ClientWallet::getOrCreateForUser($user);

    expect($wallet)->toBeInstanceOf(ClientWallet::class)
        ->and((float) $wallet->balance)->toBe(0.0)
        ->and($wallet->currency)->toBe('XAF')
        ->and(ClientWallet::where('user_id', $user->id)->count())->toBe(1);
});

test('getOrCreateForUser returns existing wallet', function () {
    $user = User::factory()->create();
    $existing = ClientWallet::factory()->withBalance(3000)->create(['user_id' => $user->id]);

    $wallet = ClientWallet::getOrCreateForUser($user);

    expect($wallet->id)->toBe($existing->id)
        ->and((float) $wallet->balance)->toBe(3000.0)
        ->and(ClientWallet::where('user_id', $user->id)->count())->toBe(1);
});

test('wallet default balance is zero', function () {
    $wallet = ClientWallet::factory()->create();

    expect((float) $wallet->balance)->toBe(0.0);
});

test('wallet user_id is unique', function () {
    $user = User::factory()->create();
    ClientWallet::factory()->create(['user_id' => $user->id]);

    expect(fn () => ClientWallet::factory()->create(['user_id' => $user->id]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

// ============================================================
// ClientWalletService Tests
// ============================================================

test('service getWallet creates wallet on first access', function () {
    $user = User::factory()->create();
    $service = app(ClientWalletService::class);

    $wallet = $service->getWallet($user);

    expect($wallet)->toBeInstanceOf(ClientWallet::class)
        ->and((float) $wallet->balance)->toBe(0.0);
});

test('service getWallet returns existing wallet', function () {
    $user = User::factory()->create();
    ClientWallet::factory()->withBalance(10000)->create(['user_id' => $user->id]);
    $service = app(ClientWalletService::class);

    $wallet = $service->getWallet($user);

    expect((float) $wallet->balance)->toBe(10000.0);
});

test('service getRecentTransactions returns last 10', function () {
    $user = User::factory()->create();

    // Create 15 wallet transactions
    for ($i = 0; $i < 15; $i++) {
        WalletTransaction::factory()->create([
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_REFUND,
            'created_at' => now()->subMinutes(15 - $i),
        ]);
    }

    $service = app(ClientWalletService::class);
    $transactions = $service->getRecentTransactions($user);

    expect($transactions)->toHaveCount(10);
});

test('service getRecentTransactions returns all when fewer than 10', function () {
    $user = User::factory()->create();

    WalletTransaction::factory()->count(3)->create([
        'user_id' => $user->id,
        'type' => WalletTransaction::TYPE_REFUND,
    ]);

    $service = app(ClientWalletService::class);
    $transactions = $service->getRecentTransactions($user);

    expect($transactions)->toHaveCount(3);
});

test('service getRecentTransactions returns empty when no transactions', function () {
    $user = User::factory()->create();
    $service = app(ClientWalletService::class);

    $transactions = $service->getRecentTransactions($user);

    expect($transactions)->toHaveCount(0);
});

test('service getRecentTransactions ordered by date descending', function () {
    $user = User::factory()->create();

    $older = WalletTransaction::factory()->create([
        'user_id' => $user->id,
        'type' => WalletTransaction::TYPE_REFUND,
        'created_at' => now()->subHour(),
    ]);
    $newer = WalletTransaction::factory()->create([
        'user_id' => $user->id,
        'type' => WalletTransaction::TYPE_REFUND,
        'created_at' => now(),
    ]);

    $service = app(ClientWalletService::class);
    $transactions = $service->getRecentTransactions($user);

    expect($transactions->first()->id)->toBe($newer->id)
        ->and($transactions->last()->id)->toBe($older->id);
});

test('service isWalletPaymentEnabled checks platform settings', function () {
    // Default is enabled (wallet_enabled = 1 in DEFAULTS)
    $service = app(ClientWalletService::class);

    expect($service->isWalletPaymentEnabled())->toBeTrue();
});

test('service isWalletPaymentEnabled returns false when disabled', function () {
    PlatformSetting::query()->updateOrCreate(
        ['key' => 'wallet_enabled'],
        ['value' => '0', 'type' => 'boolean', 'group' => 'features']
    );
    app(PlatformSettingService::class)->clearCache('wallet_enabled');

    $service = app(ClientWalletService::class);

    expect($service->isWalletPaymentEnabled())->toBeFalse();
});

test('service getDashboardData returns complete data', function () {
    $user = User::factory()->create();
    ClientWallet::factory()->withBalance(5000)->create(['user_id' => $user->id]);

    WalletTransaction::factory()->count(3)->create([
        'user_id' => $user->id,
        'type' => WalletTransaction::TYPE_REFUND,
    ]);

    $service = app(ClientWalletService::class);
    $data = $service->getDashboardData($user);

    expect($data)->toHaveKeys(['wallet', 'recentTransactions', 'walletEnabled', 'transactionCount'])
        ->and($data['wallet'])->toBeInstanceOf(ClientWallet::class)
        ->and((float) $data['wallet']->balance)->toBe(5000.0)
        ->and($data['recentTransactions'])->toHaveCount(3)
        ->and($data['walletEnabled'])->toBeTrue()
        ->and($data['transactionCount'])->toBe(3);
});

test('service formatXAF formats correctly', function () {
    expect(ClientWalletService::formatXAF(5000))->toBe('5,000 XAF')
        ->and(ClientWalletService::formatXAF(0))->toBe('0 XAF')
        ->and(ClientWalletService::formatXAF(1250000))->toBe('1,250,000 XAF');
});

// ============================================================
// WalletTransaction Model Credit/Debit Tests
// ============================================================

test('refund transaction is credit', function () {
    $txn = WalletTransaction::factory()->create([
        'type' => WalletTransaction::TYPE_REFUND,
    ]);

    expect($txn->isCredit())->toBeTrue()
        ->and($txn->isDebit())->toBeFalse();
});

test('commission transaction is debit', function () {
    $txn = WalletTransaction::factory()->create([
        'type' => WalletTransaction::TYPE_COMMISSION,
    ]);

    expect($txn->isCredit())->toBeFalse()
        ->and($txn->isDebit())->toBeTrue();
});

test('payment credit transaction is credit', function () {
    $txn = WalletTransaction::factory()->create([
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
    ]);

    expect($txn->isCredit())->toBeTrue();
});

test('withdrawal transaction is debit', function () {
    $txn = WalletTransaction::factory()->create([
        'type' => WalletTransaction::TYPE_WITHDRAWAL,
    ]);

    expect($txn->isDebit())->toBeTrue();
});

// ============================================================
// Controller / Route Tests
// ============================================================

test('wallet page requires authentication', function () {
    $response = $this->get('/my-wallet');

    expect($response->status())->toBeRedirect();
});

test('authenticated user can access wallet page', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/my-wallet');

    expect($response->status())->toBeSuccessful();
});

test('wallet page creates wallet on first visit', function () {
    $user = createUser('client');

    expect(ClientWallet::where('user_id', $user->id)->count())->toBe(0);

    $this->actingAs($user)->get('/my-wallet');

    expect(ClientWallet::where('user_id', $user->id)->count())->toBe(1);
});

test('wallet page shows balance', function () {
    $user = createUser('client');
    ClientWallet::factory()->withBalance(7500)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/my-wallet');

    $response->assertSee('7,500 XAF');
});

test('wallet page shows zero balance', function () {
    $user = createUser('client');
    ClientWallet::factory()->zeroBalance()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/my-wallet');

    $response->assertSee('0 XAF');
});

test('wallet page shows recent transactions', function () {
    $user = createUser('client');
    ClientWallet::factory()->withBalance(5000)->create(['user_id' => $user->id]);
    WalletTransaction::factory()->create([
        'user_id' => $user->id,
        'type' => WalletTransaction::TYPE_REFUND,
        'description' => 'Test refund description',
    ]);

    $response = $this->actingAs($user)->get('/my-wallet');

    $response->assertSee('Test refund description');
});

test('wallet page shows wallet disabled note when disabled', function () {
    PlatformSetting::query()->updateOrCreate(
        ['key' => 'wallet_enabled'],
        ['value' => '0', 'type' => 'boolean', 'group' => 'features']
    );
    app(PlatformSettingService::class)->clearCache('wallet_enabled');

    $user = createUser('client');
    ClientWallet::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/my-wallet');

    $response->assertSee('Wallet payments are currently not available for orders.');
});

test('wallet route is named correctly', function () {
    expect(route('client.wallet.index'))->toContain('/my-wallet');
});

// ============================================================
// Factory Tests
// ============================================================

test('factory creates valid wallet', function () {
    $wallet = ClientWallet::factory()->create();

    expect($wallet)->toBeInstanceOf(ClientWallet::class)
        ->and($wallet->user_id)->not->toBeNull()
        ->and((float) $wallet->balance)->toBe(0.0)
        ->and($wallet->currency)->toBe('XAF');
});

test('factory withBalance state works', function () {
    $wallet = ClientWallet::factory()->withBalance(15000)->create();

    expect((float) $wallet->balance)->toBe(15000.0);
});

test('factory zeroBalance state works', function () {
    $wallet = ClientWallet::factory()->zeroBalance()->create();

    expect((float) $wallet->balance)->toBe(0.0);
});
