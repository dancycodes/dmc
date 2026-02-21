<?php

/**
 * F-171: Withdrawable Timer Logic -- Unit Tests
 *
 * Tests for OrderClearance model, OrderClearanceService,
 * FundsWithdrawableNotification, and related model modifications.
 * BR-333 through BR-343.
 */

use App\Models\Complaint;
use App\Models\CookWallet;
use App\Models\Order;
use App\Models\OrderClearance;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\FundsWithdrawableNotification;
use App\Services\CookWalletService;
use App\Services\OrderClearanceService;
use App\Services\PlatformSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
    Notification::fake();
});

// ─────────────────────────────────────────────
// OrderClearance Model Tests
// ─────────────────────────────────────────────

test('OrderClearance isEligibleForClearance returns true when hold expired and not blocked', function () {
    $clearance = OrderClearance::factory()->eligible()->make();

    expect($clearance->isEligibleForClearance())->toBeTrue();
});

test('OrderClearance isEligibleForClearance returns false when already cleared', function () {
    $clearance = OrderClearance::factory()->cleared()->make();

    expect($clearance->isEligibleForClearance())->toBeFalse();
});

test('OrderClearance isEligibleForClearance returns false when paused', function () {
    $clearance = OrderClearance::factory()->paused()->make();

    expect($clearance->isEligibleForClearance())->toBeFalse();
});

test('OrderClearance isEligibleForClearance returns false when cancelled', function () {
    $clearance = OrderClearance::factory()->cancelled()->make();

    expect($clearance->isEligibleForClearance())->toBeFalse();
});

test('OrderClearance isEligibleForClearance returns false when still in hold period', function () {
    $clearance = OrderClearance::factory()->inHoldPeriod()->make();

    expect($clearance->isEligibleForClearance())->toBeFalse();
});

test('OrderClearance isInHoldPeriod returns true when hold not expired', function () {
    $clearance = OrderClearance::factory()->inHoldPeriod()->make();

    expect($clearance->isInHoldPeriod())->toBeTrue();
});

test('OrderClearance isInHoldPeriod returns false when cleared', function () {
    $clearance = OrderClearance::factory()->cleared()->make();

    expect($clearance->isInHoldPeriod())->toBeFalse();
});

test('OrderClearance isInHoldPeriod returns false when cancelled', function () {
    $clearance = OrderClearance::factory()->cancelled()->make();

    expect($clearance->isInHoldPeriod())->toBeFalse();
});

// ─────────────────────────────────────────────
// OrderClearance Scopes
// ─────────────────────────────────────────────

test('eligibleForClearance scope finds eligible records', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'completed_at' => now()->subHours(4),
    ]);

    $eligible = OrderClearance::factory()->eligible()->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $order2 = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'completed_at' => now()->subMinutes(30),
    ]);

    OrderClearance::factory()->inHoldPeriod()->create([
        'order_id' => $order2->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $results = OrderClearance::eligibleForClearance()->get();
    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($eligible->id);
});

test('paused scope finds paused records', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $paused = OrderClearance::factory()->paused()->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $results = OrderClearance::paused()->get();
    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($paused->id);
});

// ─────────────────────────────────────────────
// OrderClearanceService — createClearance
// ─────────────────────────────────────────────

test('createClearance creates record with correct hold period snapshot', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $service = app(OrderClearanceService::class);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    $clearance = $service->createClearance($order, 4500.00);

    expect($clearance->order_id)->toBe($order->id)
        ->and($clearance->tenant_id)->toBe($tenant->id)
        ->and($clearance->cook_id)->toBe($cook->id)
        ->and((float) $clearance->amount)->toBe(4500.00)
        ->and($clearance->hold_hours)->toBe(3)
        ->and($clearance->is_cleared)->toBeFalse()
        ->and($clearance->is_paused)->toBeFalse()
        ->and($clearance->is_cancelled)->toBeFalse();
});

test('createClearance uses custom hold period from platform settings', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();

    // Set custom hold period
    PlatformSetting::updateOrCreate(
        ['key' => 'withdrawable_hold_hours'],
        ['value' => '6', 'type' => 'integer', 'group' => 'orders']
    );
    app(PlatformSettingService::class)->clearCache('withdrawable_hold_hours');

    $service = app(OrderClearanceService::class);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    $clearance = $service->createClearance($order, 5000.00);

    expect($clearance->hold_hours)->toBe(6);
    expect((int) abs($clearance->withdrawable_at->diffInHours($clearance->completed_at)))->toBe(6);
});

test('createClearance with 0 hold hours makes funds immediately eligible', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();

    PlatformSetting::updateOrCreate(
        ['key' => 'withdrawable_hold_hours'],
        ['value' => '0', 'type' => 'integer', 'group' => 'orders']
    );
    app(PlatformSettingService::class)->clearCache('withdrawable_hold_hours');

    $service = app(OrderClearanceService::class);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    $clearance = $service->createClearance($order, 3000.00);

    expect($clearance->hold_hours)->toBe(0);
    expect($clearance->isEligibleForClearance())->toBeTrue();
});

// ─────────────────────────────────────────────
// OrderClearanceService — pauseTimer
// ─────────────────────────────────────────────

test('pauseTimer pauses an active clearance with remaining seconds', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $service = app(OrderClearanceService::class);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'completed_at' => now()->subHours(2),
    ]);

    OrderClearance::factory()->inHoldPeriod()->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $result = $service->pauseTimer($order);

    expect($result)->not->toBeNull()
        ->and($result->is_paused)->toBeTrue()
        ->and($result->paused_at)->not->toBeNull()
        ->and($result->remaining_seconds_at_pause)->toBeGreaterThan(0);
});

test('pauseTimer returns null for already cleared order', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $service = app(OrderClearanceService::class);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    OrderClearance::factory()->cleared()->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    expect($service->pauseTimer($order))->toBeNull();
});

test('pauseTimer returns null if complaint filed after hold expired', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $service = app(OrderClearanceService::class);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    OrderClearance::factory()->eligible()->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    expect($service->pauseTimer($order))->toBeNull();
});

// ─────────────────────────────────────────────
// OrderClearanceService — resumeTimer
// ─────────────────────────────────────────────

test('resumeTimer resumes a paused clearance with correct time calculation', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $service = app(OrderClearanceService::class);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    OrderClearance::factory()->paused()->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'remaining_seconds_at_pause' => 3600,
    ]);

    $result = $service->resumeTimer($order);

    expect($result)->not->toBeNull()
        ->and($result->is_paused)->toBeFalse()
        ->and($result->paused_at)->toBeNull()
        ->and($result->remaining_seconds_at_pause)->toBeNull();

    // The new withdrawable_at should be ~1 hour from now
    $diffSeconds = abs(now()->diffInSeconds($result->withdrawable_at, false));
    expect($diffSeconds)->toBeLessThanOrEqual(3605)
        ->and($diffSeconds)->toBeGreaterThanOrEqual(3595);
});

test('resumeTimer does not resume if active complaints remain', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = \App\Models\User::factory()->create();
    $service = app(OrderClearanceService::class);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
    ]);

    OrderClearance::factory()->paused()->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    // Create an active complaint
    Complaint::factory()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'status' => 'open',
    ]);

    $result = $service->resumeTimer($order);
    expect($result)->toBeNull();
});

// ─────────────────────────────────────────────
// OrderClearanceService — cancelClearance
// ─────────────────────────────────────────────

test('cancelClearance marks clearance as cancelled', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    CookWallet::getOrCreateForTenant($tenant, $cook);
    $service = app(OrderClearanceService::class);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    OrderClearance::factory()->paused()->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $result = $service->cancelClearance($order);

    expect($result)->not->toBeNull()
        ->and($result->is_cancelled)->toBeTrue()
        ->and($result->is_paused)->toBeFalse();
});

test('cancelClearance returns null for already cancelled clearance', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $service = app(OrderClearanceService::class);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    OrderClearance::factory()->cancelled()->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    expect($service->cancelClearance($order))->toBeNull();
});

// ─────────────────────────────────────────────
// OrderClearanceService — processEligibleClearances
// ─────────────────────────────────────────────

test('processEligibleClearances transitions eligible funds and creates wallet transaction', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    CookWallet::getOrCreateForTenant($tenant, $cook);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'completed_at' => now()->subHours(4),
    ]);

    // Create initial payment credit transaction
    WalletTransaction::create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
        'amount' => 4500,
        'currency' => 'XAF',
        'balance_before' => 0,
        'balance_after' => 4500,
        'is_withdrawable' => false,
        'withdrawable_at' => now()->subHour(),
        'status' => 'completed',
    ]);

    OrderClearance::factory()->eligible()->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'amount' => 4500,
    ]);

    $service = app(OrderClearanceService::class);
    $result = $service->processEligibleClearances();

    expect($result['processed'])->toBe(1)
        ->and($result['total_amount'])->toBe(4500.0)
        ->and($result['cooks_notified'])->toBe(1);

    // Verify clearance is marked as cleared
    $clearance = OrderClearance::where('order_id', $order->id)->first();
    expect($clearance->is_cleared)->toBeTrue()
        ->and($clearance->cleared_at)->not->toBeNull();

    // Verify became_withdrawable transaction was created
    $tx = WalletTransaction::where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_BECAME_WITHDRAWABLE)
        ->first();
    expect($tx)->not->toBeNull()
        ->and((float) $tx->amount)->toBe(4500.0);

    // Verify payment_credit was marked as withdrawable
    $credit = WalletTransaction::where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)
        ->first();
    expect($credit->is_withdrawable)->toBeTrue();
});

test('processEligibleClearances sends consolidated notification for multiple orders', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    CookWallet::getOrCreateForTenant($tenant, $cook);

    // Create 3 eligible orders
    foreach ([3000, 5000, 5500] as $amount) {
        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'cook_id' => $cook->id,
            'status' => Order::STATUS_COMPLETED,
            'completed_at' => now()->subHours(4),
        ]);

        WalletTransaction::create([
            'user_id' => $cook->id,
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
            'amount' => $amount,
            'currency' => 'XAF',
            'balance_before' => 0,
            'balance_after' => $amount,
            'is_withdrawable' => false,
            'status' => 'completed',
        ]);

        OrderClearance::factory()->eligible()->create([
            'order_id' => $order->id,
            'tenant_id' => $tenant->id,
            'cook_id' => $cook->id,
            'amount' => $amount,
        ]);
    }

    $service = app(OrderClearanceService::class);
    $result = $service->processEligibleClearances();

    expect($result['processed'])->toBe(3)
        ->and($result['total_amount'])->toBe(13500.0)
        ->and($result['cooks_notified'])->toBe(1);

    // Only ONE notification was sent (consolidated)
    Notification::assertSentTo($cook, FundsWithdrawableNotification::class, function ($notification) {
        $body = $notification->getBody($notification);

        return str_contains($body, '13,500 XAF') && str_contains($body, '3 orders');
    });
});

test('processEligibleClearances skips paused and cancelled clearances', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();

    $order1 = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    OrderClearance::factory()->paused()->create([
        'order_id' => $order1->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $order2 = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    OrderClearance::factory()->cancelled()->create([
        'order_id' => $order2->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = app(OrderClearanceService::class);
    $result = $service->processEligibleClearances();

    expect($result['processed'])->toBe(0);
    Notification::assertNothingSent();
});

test('processEligibleClearances returns zero counts when nothing to process', function () {
    $service = app(OrderClearanceService::class);
    $result = $service->processEligibleClearances();

    expect($result)->toBe(['processed' => 0, 'total_amount' => 0.0, 'cooks_notified' => 0]);
});

// ─────────────────────────────────────────────
// FundsWithdrawableNotification Tests
// ─────────────────────────────────────────────

test('FundsWithdrawableNotification single order body format', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-cook']);

    $notification = new FundsWithdrawableNotification(
        amount: 4500,
        orderCount: 1,
        orderNumbers: ['DMC-260221-0001'],
        tenant: $tenant
    );

    $cook = User::factory()->create();
    $body = $notification->getBody($cook);

    expect($body)->toContain('4,500 XAF')
        ->and($body)->toContain('DMC-260221-0001');
});

test('FundsWithdrawableNotification multiple orders body format', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-cook']);

    $notification = new FundsWithdrawableNotification(
        amount: 13500,
        orderCount: 3,
        orderNumbers: ['DMC-260221-0001', 'DMC-260221-0002', 'DMC-260221-0003'],
        tenant: $tenant
    );

    $cook = User::factory()->create();
    $body = $notification->getBody($cook);

    expect($body)->toContain('13,500 XAF')
        ->and($body)->toContain('3 orders');
});

test('FundsWithdrawableNotification action URL points to wallet dashboard', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-cook']);
    $mainHost = parse_url(config('app.url'), PHP_URL_HOST);

    $notification = new FundsWithdrawableNotification(
        amount: 4500,
        orderCount: 1,
        orderNumbers: ['DMC-260221-0001'],
        tenant: $tenant
    );

    $cook = User::factory()->create();
    $url = $notification->getActionUrl($cook);

    expect($url)->toContain('/dashboard/wallet');
});

test('FundsWithdrawableNotification toArray includes all expected fields', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-cook']);

    $notification = new FundsWithdrawableNotification(
        amount: 5000,
        orderCount: 1,
        orderNumbers: ['DMC-260221-0001'],
        tenant: $tenant
    );

    $cook = User::factory()->create();
    $array = $notification->toArray($cook);

    expect($array)->toHaveKeys(['title', 'body', 'icon', 'action_url', 'data'])
        ->and($array['data']['type'])->toBe('funds_withdrawable')
        ->and($array['data']['amount'])->toBe(5000.0)
        ->and($array['data']['order_count'])->toBe(1);
});

// ─────────────────────────────────────────────
// WalletTransaction TYPE_BECAME_WITHDRAWABLE
// ─────────────────────────────────────────────

test('WalletTransaction TYPE_BECAME_WITHDRAWABLE is in TYPES array', function () {
    expect(WalletTransaction::TYPES)->toContain(WalletTransaction::TYPE_BECAME_WITHDRAWABLE);
});

test('became_withdrawable transaction is not a credit or debit', function () {
    $tx = new WalletTransaction(['type' => WalletTransaction::TYPE_BECAME_WITHDRAWABLE]);

    // It's an informational record, not a credit/debit
    expect($tx->isCredit())->toBeFalse()
        ->and($tx->isDebit())->toBeFalse();
});

// ─────────────────────────────────────────────
// PlatformSetting — withdrawable_hold_hours
// ─────────────────────────────────────────────

test('PlatformSetting DEFAULTS includes withdrawable_hold_hours', function () {
    expect(PlatformSetting::DEFAULTS)->toHaveKey('withdrawable_hold_hours');
    expect(PlatformSetting::DEFAULTS['withdrawable_hold_hours'])->toBe([
        'value' => '3',
        'type' => 'integer',
        'group' => 'orders',
    ]);
});

test('PlatformSettingService getWithdrawableHoldHours returns default 3', function () {
    $service = app(PlatformSettingService::class);
    expect($service->getWithdrawableHoldHours())->toBe(3);
});

test('PlatformSettingService getWithdrawableHoldHours returns updated value', function () {
    PlatformSetting::updateOrCreate(
        ['key' => 'withdrawable_hold_hours'],
        ['value' => '6', 'type' => 'integer', 'group' => 'orders']
    );
    app(PlatformSettingService::class)->clearCache('withdrawable_hold_hours');

    $service = app(PlatformSettingService::class);
    expect($service->getWithdrawableHoldHours())->toBe(6);
});

// ─────────────────────────────────────────────
// Complaint model additions
// ─────────────────────────────────────────────

test('Complaint isActive returns true for open complaint', function () {
    $complaint = new Complaint(['status' => 'open']);
    expect($complaint->isActive())->toBeTrue();
});

test('Complaint isActive returns false for resolved complaint', function () {
    $complaint = new Complaint(['status' => 'resolved']);
    expect($complaint->isActive())->toBeFalse();
});

test('Complaint isActive returns false for dismissed complaint', function () {
    $complaint = new Complaint(['status' => 'dismissed']);
    expect($complaint->isActive())->toBeFalse();
});

test('Complaint isActive returns true for escalated complaint', function () {
    $complaint = new Complaint(['status' => 'escalated']);
    expect($complaint->isActive())->toBeTrue();
});

// ─────────────────────────────────────────────
// Order model additions
// ─────────────────────────────────────────────

test('Order has clearance relationship', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    OrderClearance::factory()->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    expect($order->clearance)->not->toBeNull()
        ->and($order->clearance)->toBeInstanceOf(OrderClearance::class);
});

test('Order has complaints relationship', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = \App\Models\User::factory()->create();

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
    ]);

    Complaint::factory()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($order->complaints)->toHaveCount(1);
});

// ─────────────────────────────────────────────
// CookWalletService label updates
// ─────────────────────────────────────────────

test('CookWalletService getTransactionTypeLabel handles became_withdrawable', function () {
    $label = CookWalletService::getTransactionTypeLabel(WalletTransaction::TYPE_BECAME_WITHDRAWABLE);
    expect($label)->toBe('Became Withdrawable');
});

test('CookWalletService getTypeFilterOptions includes became_withdrawable', function () {
    $options = CookWalletService::getTypeFilterOptions();
    $values = array_column($options, 'value');

    expect($values)->toContain(WalletTransaction::TYPE_BECAME_WITHDRAWABLE);
});
