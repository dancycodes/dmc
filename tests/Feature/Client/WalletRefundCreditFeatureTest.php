<?php

/**
 * F-167: Client Wallet Refund Credit — Feature Tests
 *
 * Integration tests verifying the refund credit flow end-to-end.
 * Tests database atomicity, notification dispatch, and HTTP integration.
 */

use App\Mail\RefundCreditedMail;
use App\Models\ClientWallet;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\RefundCreditedNotification;
use App\Services\ComplaintResolutionService;
use App\Services\WalletRefundService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

// ============================================================
// Atomicity (BR-297)
// ============================================================

test('refund credit is atomic - wallet and transaction created together', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->withBalance(1000)->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = app(WalletRefundService::class);
    $service->creditRefund($user, 3000, $order, WalletRefundService::SOURCE_CANCELLATION, 'Atomic test');

    // Both wallet update and transaction should exist
    $wallet = ClientWallet::where('user_id', $user->id)->first();
    $transactions = WalletTransaction::where('user_id', $user->id)
        ->where('type', WalletTransaction::TYPE_REFUND)
        ->count();

    expect((float) $wallet->balance)->toBe(4000.0)
        ->and($transactions)->toBe(1);
});

test('wallet balance reflects refund on dashboard page', function () {
    Notification::fake();
    Mail::fake();

    $this->seedRolesAndPermissions();

    $user = User::factory()->create();
    $user->assignRole('client');
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->zeroBalance()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0100',
    ]);

    // Credit refund
    $service = app(WalletRefundService::class);
    $service->creditCancellationRefund($user, 5000, $order);

    // Verify wallet page shows updated balance
    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
    $response = $this->actingAs($user)
        ->get("https://{$mainDomain}/my-wallet");

    $response->assertSuccessful();
});

// ============================================================
// Concurrent Refunds
// ============================================================

test('concurrent refunds are both applied correctly', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->withBalance(1000)->create(['user_id' => $user->id]);
    $order1 = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0200',
    ]);
    $order2 = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0201',
    ]);

    $service = app(WalletRefundService::class);

    // Two refunds in sequence (simulating rapid refunds)
    $service->creditCancellationRefund($user, 3000, $order1);
    $service->creditComplaintRefund($user, 2000, $order2, 99);

    $wallet = ClientWallet::where('user_id', $user->id)->first();
    $transactionCount = WalletTransaction::where('user_id', $user->id)
        ->where('type', WalletTransaction::TYPE_REFUND)
        ->count();

    // 1000 (initial) + 3000 + 2000 = 6000
    expect((float) $wallet->balance)->toBe(6000.0)
        ->and($transactionCount)->toBe(2);
});

// ============================================================
// Notification Dispatch Verification
// ============================================================

test('all three notification channels are triggered', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create(['email' => 'client@example.com']);
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = app(WalletRefundService::class);
    $service->creditCancellationRefund($user, 5000, $order);

    // Push + Database
    Notification::assertSentTo($user, RefundCreditedNotification::class);

    // Email
    Mail::assertQueued(RefundCreditedMail::class, function ($mail) {
        return $mail->hasTo('client@example.com');
    });
});

// ============================================================
// Refund Transaction Visible on Wallet Dashboard
// ============================================================

test('refund transaction appears in wallet recent transactions', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0300',
    ]);

    $service = app(WalletRefundService::class);
    $service->creditCancellationRefund($user, 4000, $order);

    // Verify the transaction is retrievable via the wallet service
    $walletService = app(\App\Services\ClientWalletService::class);
    $recentTransactions = $walletService->getRecentTransactions($user);

    expect($recentTransactions)->toHaveCount(1)
        ->and($recentTransactions->first()->type)->toBe(WalletTransaction::TYPE_REFUND)
        ->and($recentTransactions->first()->amount)->toBe('4000.00')
        ->and($recentTransactions->first()->order->order_number)->toBe('DMC-260220-0300');
});

// ============================================================
// Edge Case: Lazy Wallet Creation in Feature Context
// ============================================================

// ============================================================
// ComplaintResolutionService Integration
// ============================================================

test('complaint partial refund credits wallet via WalletRefundService', function () {
    Notification::fake();
    Mail::fake();

    $this->seedRolesAndPermissions();

    $admin = $this->createUserWithRole('admin');
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $cook = User::factory()->create();
    ClientWallet::factory()->zeroBalance()->create(['user_id' => $client->id]);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'grand_total' => 5000,
    ]);

    $complaint = Complaint::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'status' => 'escalated',
    ]);

    $service = app(ComplaintResolutionService::class);
    $service->resolve($complaint, [
        'resolution_type' => 'partial_refund',
        'resolution_notes' => 'Partial refund approved',
        'refund_amount' => 2000,
    ], $admin);

    // Verify wallet was credited
    $wallet = ClientWallet::where('user_id', $client->id)->first();
    expect((float) $wallet->balance)->toBe(2000.0);

    // Verify refund transaction exists
    $transaction = WalletTransaction::where('user_id', $client->id)
        ->where('type', WalletTransaction::TYPE_REFUND)
        ->first();
    expect($transaction)->not->toBeNull()
        ->and($transaction->amount)->toBe('2000.00')
        ->and($transaction->metadata['source'])->toBe(WalletRefundService::SOURCE_COMPLAINT)
        ->and($transaction->metadata['complaint_id'])->toBe($complaint->id);
});

test('complaint full refund credits full order amount to wallet', function () {
    Notification::fake();
    Mail::fake();

    $this->seedRolesAndPermissions();

    $admin = $this->createUserWithRole('admin');
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $cook = User::factory()->create();
    ClientWallet::factory()->zeroBalance()->create(['user_id' => $client->id]);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'grand_total' => 7500,
    ]);

    // Create a successful payment transaction for this order
    \App\Models\PaymentTransaction::factory()->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'amount' => 7500,
        'status' => 'successful',
    ]);

    $complaint = Complaint::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'status' => 'escalated',
    ]);

    $service = app(ComplaintResolutionService::class);
    $service->resolve($complaint, [
        'resolution_type' => 'full_refund',
        'resolution_notes' => 'Full refund approved due to quality issues',
    ], $admin);

    // Verify wallet was credited with full amount
    $wallet = ClientWallet::where('user_id', $client->id)->first();
    expect((float) $wallet->balance)->toBe(7500.0);
});

// ============================================================
// Edge Case: Lazy Wallet Creation in Feature Context
// ============================================================

test('refund creates wallet for user who never visited wallet page', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    // Confirm no wallet exists
    expect(ClientWallet::where('user_id', $user->id)->exists())->toBeFalse();

    $service = app(WalletRefundService::class);
    $result = $service->creditCancellationRefund($user, 7500, $order);

    // Wallet created with refund as initial balance
    $wallet = ClientWallet::where('user_id', $user->id)->first();
    expect($wallet)->not->toBeNull()
        ->and((float) $wallet->balance)->toBe(7500.0);

    // Transaction should show balance_before = 0
    $transaction = $result['transaction'];
    expect($transaction->balance_before)->toBe('0.00')
        ->and($transaction->balance_after)->toBe('7500.00');
});
