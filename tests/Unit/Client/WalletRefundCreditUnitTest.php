<?php

/**
 * F-167: Client Wallet Refund Credit — Unit Tests
 *
 * Tests the WalletRefundService, notifications, and integration points.
 * BR-290: Refund credits increase client wallet balance.
 * BR-291: Wallet transaction record created with type "refund".
 * BR-292: Transaction record includes amount, type, description, order reference.
 * BR-293: Wallet lazily created if none exists.
 * BR-294: Wallet balance cannot go negative.
 * BR-297: Operations are atomic (DB transaction).
 * BR-298: Logged via Spatie Activitylog.
 */

use App\Mail\RefundCreditedMail;
use App\Models\ClientWallet;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\RefundCreditedNotification;
use App\Services\ComplaintResolutionService;
use App\Services\WalletRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ============================================================
// WalletRefundService — Core Credit Logic
// ============================================================

test('creditRefund increases wallet balance by refund amount (BR-290)', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $wallet = ClientWallet::factory()->withBalance(3000)->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0001',
        'grand_total' => 5000,
    ]);

    $service = app(WalletRefundService::class);
    $result = $service->creditRefund($user, 5000, $order, WalletRefundService::SOURCE_CANCELLATION, 'Refund for order');

    expect($result['wallet']->balance)->toBe('8000.00');
});

test('creditRefund creates wallet transaction with type refund (BR-291)', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0002',
    ]);

    $service = app(WalletRefundService::class);
    $result = $service->creditRefund($user, 2500, $order, WalletRefundService::SOURCE_CANCELLATION, 'Test refund');

    $transaction = $result['transaction'];
    expect($transaction->type)->toBe(WalletTransaction::TYPE_REFUND)
        ->and($transaction->amount)->toBe('2500.00')
        ->and($transaction->status)->toBe('completed');
});

test('wallet transaction record includes required details (BR-292)', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->withBalance(1000)->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0003',
    ]);

    $service = app(WalletRefundService::class);
    $result = $service->creditRefund($user, 3000, $order, WalletRefundService::SOURCE_COMPLAINT, 'Complaint refund', ['complaint_id' => 99]);

    $transaction = $result['transaction'];
    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->tenant_id)->toBe($tenant->id)
        ->and($transaction->order_id)->toBe($order->id)
        ->and($transaction->currency)->toBe('XAF')
        ->and($transaction->balance_before)->toBe('1000.00')
        ->and($transaction->balance_after)->toBe('4000.00')
        ->and($transaction->description)->toBe('Complaint refund')
        ->and($transaction->metadata)->toHaveKey('source', WalletRefundService::SOURCE_COMPLAINT)
        ->and($transaction->metadata)->toHaveKey('order_number', 'DMC-260220-0003')
        ->and($transaction->metadata)->toHaveKey('complaint_id', 99)
        ->and($transaction->created_at)->not->toBeNull();
});

test('wallet is lazily created if none exists (BR-293)', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0004',
    ]);

    // No wallet exists for this user
    expect(ClientWallet::where('user_id', $user->id)->exists())->toBeFalse();

    $service = app(WalletRefundService::class);
    $result = $service->creditRefund($user, 5000, $order, WalletRefundService::SOURCE_CANCELLATION, 'First refund');

    // Wallet should now exist with refund as initial balance
    expect(ClientWallet::where('user_id', $user->id)->exists())->toBeTrue()
        ->and($result['wallet']->balance)->toBe('5000.00');
});

test('wallet balance cannot go negative - refunds only add (BR-294)', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $wallet = ClientWallet::factory()->zeroBalance()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = app(WalletRefundService::class);
    $result = $service->creditRefund($user, 1000, $order, WalletRefundService::SOURCE_CANCELLATION, 'Refund');

    // Balance went from 0 to 1000 (only positive changes)
    expect((float) $result['wallet']->balance)->toBeGreaterThanOrEqual(0);
});

test('refund is logged via Spatie Activitylog (BR-298)', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0005',
    ]);

    $service = app(WalletRefundService::class);
    $service->creditRefund($user, 4000, $order, WalletRefundService::SOURCE_CANCELLATION, 'Refund for test');

    $log = Activity::query()
        ->where('log_name', 'client_wallets')
        ->where('description', 'refund_credited')
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->causer_id)->toBe($user->id)
        ->and($log->properties['refund_amount'])->toEqual(4000)
        ->and($log->properties['order_id'])->toBe($order->id)
        ->and($log->properties['order_number'])->toBe('DMC-260220-0005')
        ->and($log->properties['source'])->toBe(WalletRefundService::SOURCE_CANCELLATION);
});

// ============================================================
// Convenience Methods
// ============================================================

test('creditCancellationRefund sets correct description and source', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0010',
    ]);

    $service = app(WalletRefundService::class);
    $result = $service->creditCancellationRefund($user, 5000, $order);

    $transaction = $result['transaction'];
    expect($transaction->description)->toContain('DMC-260220-0010')
        ->and($transaction->metadata['source'])->toBe(WalletRefundService::SOURCE_CANCELLATION);
});

test('creditComplaintRefund includes complaint ID in metadata', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0011',
    ]);

    $service = app(WalletRefundService::class);
    $result = $service->creditComplaintRefund($user, 2000, $order, 42);

    $transaction = $result['transaction'];
    expect($transaction->metadata['source'])->toBe(WalletRefundService::SOURCE_COMPLAINT)
        ->and($transaction->metadata['complaint_id'])->toBe(42)
        ->and($transaction->description)->toContain('DMC-260220-0011');
});

// ============================================================
// Notifications (BR-295, BR-296)
// ============================================================

test('client receives push and DB notification on refund (BR-295)', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0020',
    ]);

    $service = app(WalletRefundService::class);
    $service->creditRefund($user, 3000, $order, WalletRefundService::SOURCE_CANCELLATION, 'Refund');

    Notification::assertSentTo($user, RefundCreditedNotification::class);
});

test('client receives email notification on refund (BR-295)', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = app(WalletRefundService::class);
    $service->creditRefund($user, 2000, $order, WalletRefundService::SOURCE_CANCELLATION, 'Refund');

    Mail::assertQueued(RefundCreditedMail::class, function ($mail) {
        return $mail->hasTo('test@example.com');
    });
});

test('refund notification includes amount and order reference (BR-296)', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0021',
        'grand_total' => 5000,
    ]);

    $service = app(WalletRefundService::class);
    $service->creditRefund($user, 5000, $order, WalletRefundService::SOURCE_CANCELLATION, 'Refund');

    Notification::assertSentTo($user, RefundCreditedNotification::class, function ($notification) use ($user) {
        $data = $notification->getData($user);

        return $data['amount'] === 5000.0
            && $data['order_number'] === 'DMC-260220-0021'
            && $data['type'] === 'refund_credited';
    });
});

// ============================================================
// RefundCreditedNotification Tests
// ============================================================

test('notification title is Refund Credited', function () {
    $order = Order::factory()->make(['order_number' => 'DMC-260220-0030']);
    $transaction = WalletTransaction::factory()->refund()->make();
    $user = User::factory()->make();

    $notification = new RefundCreditedNotification($order, 5000, $transaction);

    expect($notification->getTitle($user))->toBe('Refund Credited!');
});

test('notification body includes amount and order number', function () {
    $order = Order::factory()->make(['order_number' => 'DMC-260220-0031']);
    $transaction = WalletTransaction::factory()->refund()->make();
    $user = User::factory()->make();

    $notification = new RefundCreditedNotification($order, 5000, $transaction);
    $body = $notification->getBody($user);

    expect($body)->toContain('5,000 XAF')
        ->and($body)->toContain('DMC-260220-0031');
});

test('notification action URL links to wallet page', function () {
    $order = Order::factory()->make();
    $transaction = WalletTransaction::factory()->refund()->make();
    $user = User::factory()->make();

    $notification = new RefundCreditedNotification($order, 5000, $transaction);
    $url = $notification->getActionUrl($user);

    expect($url)->toContain('/my-wallet');
});

test('notification tag prevents duplicates per order', function () {
    $order = Order::factory()->make(['id' => 42]);
    $transaction = WalletTransaction::factory()->refund()->make();
    $user = User::factory()->make();

    $notification = new RefundCreditedNotification($order, 5000, $transaction);

    expect($notification->getTag($user))->toBe('refund-42');
});

test('notification data payload is complete', function () {
    $order = Order::factory()->make([
        'id' => 99,
        'order_number' => 'DMC-260220-0032',
    ]);
    $transaction = WalletTransaction::factory()->refund()->make(['id' => 55]);
    $user = User::factory()->make();

    $notification = new RefundCreditedNotification($order, 3500, $transaction);
    $data = $notification->getData($user);

    expect($data)->toHaveKey('order_id', 99)
        ->and($data)->toHaveKey('order_number', 'DMC-260220-0032')
        ->and($data)->toHaveKey('amount', 3500.0)
        ->and($data)->toHaveKey('transaction_id', 55)
        ->and($data)->toHaveKey('type', 'refund_credited');
});

test('notification uses database and webpush channels', function () {
    $order = Order::factory()->make();
    $transaction = WalletTransaction::factory()->refund()->make();
    $user = User::factory()->make();

    $notification = new RefundCreditedNotification($order, 5000, $transaction);
    $channels = $notification->via($user);

    expect($channels)->toContain('database')
        ->and($channels)->toContain(\NotificationChannels\WebPush\WebPushChannel::class);
});

// ============================================================
// RefundCreditedMail Tests
// ============================================================

test('refund email has correct subject', function () {
    $order = Order::factory()->make(['order_number' => 'DMC-260220-0040']);
    $tenant = Tenant::factory()->make();

    $mail = new RefundCreditedMail($order, 5000, $tenant);
    $mail->forRecipient(User::factory()->make());

    expect($mail->envelope()->subject)->toContain('DMC-260220-0040');
});

test('refund email uses correct view', function () {
    $order = Order::factory()->make();

    $mail = new RefundCreditedMail($order, 5000);
    $content = $mail->content();

    expect($content->view)->toBe('emails.refund-credited');
});

// ============================================================
// Multiple Refunds (Scenario 4)
// ============================================================

test('multiple refunds are applied correctly', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->zeroBalance()->create(['user_id' => $user->id]);
    $order1 = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0050',
    ]);
    $order2 = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260220-0051',
    ]);

    $service = app(WalletRefundService::class);

    $result1 = $service->creditCancellationRefund($user, 3000, $order1);
    $result2 = $service->creditCancellationRefund($user, 2000, $order2);

    // Balance should be the sum of both refunds
    $wallet = ClientWallet::where('user_id', $user->id)->first();
    expect((float) $wallet->balance)->toBe(5000.0);

    // Two separate transaction records
    $transactions = WalletTransaction::where('user_id', $user->id)
        ->where('type', WalletTransaction::TYPE_REFUND)
        ->orderBy('id')
        ->get();

    expect($transactions)->toHaveCount(2)
        ->and($transactions[0]->balance_before)->toBe('0.00')
        ->and($transactions[0]->balance_after)->toBe('3000.00')
        ->and($transactions[1]->balance_before)->toBe('3000.00')
        ->and($transactions[1]->balance_after)->toBe('5000.00');
});

// ============================================================
// Edge Cases
// ============================================================

test('zero amount refund creates transaction but balance unchanged', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->withBalance(5000)->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = app(WalletRefundService::class);
    $result = $service->creditRefund($user, 0, $order, WalletRefundService::SOURCE_CANCELLATION, 'Zero refund');

    expect($result['wallet']->balance)->toBe('5000.00')
        ->and($result['transaction']->amount)->toBe('0.00')
        ->and($result['transaction']->balance_before)->toBe('5000.00')
        ->and($result['transaction']->balance_after)->toBe('5000.00');

    // Notifications should still be sent per spec
    Notification::assertSentTo($user, RefundCreditedNotification::class);
});

test('large refund amount is handled correctly', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->zeroBalance()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = app(WalletRefundService::class);
    $result = $service->creditRefund($user, 999999, $order, WalletRefundService::SOURCE_CANCELLATION, 'Large refund');

    expect($result['wallet']->balance)->toBe('999999.00');
});

test('refund transaction is_withdrawable is true', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    ClientWallet::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = app(WalletRefundService::class);
    $result = $service->creditRefund($user, 1000, $order, WalletRefundService::SOURCE_CANCELLATION, 'Refund');

    // Client wallet refunds are immediately available (no hold period)
    expect($result['transaction']->is_withdrawable)->toBeTrue()
        ->and($result['transaction']->withdrawable_at)->toBeNull();
});

// ComplaintResolutionService integration tests are in the Feature test file
// since they require seedRolesAndPermissions() and createUserWithRole() helpers.

// ============================================================
// Format Helper
// ============================================================

test('formatXAF formats amounts correctly', function () {
    expect(WalletRefundService::formatXAF(5000))->toBe('5,000 XAF')
        ->and(WalletRefundService::formatXAF(0))->toBe('0 XAF')
        ->and(WalletRefundService::formatXAF(1250000))->toBe('1,250,000 XAF');
});

// ============================================================
// Source Constants
// ============================================================

test('source constants are defined', function () {
    expect(WalletRefundService::SOURCE_CANCELLATION)->toBe('cancellation')
        ->and(WalletRefundService::SOURCE_COMPLAINT)->toBe('complaint');
});
