<?php

/**
 * F-171: Withdrawable Timer Logic -- Feature Tests
 *
 * Integration tests for the artisan command, full scenarios,
 * and edge cases around fund clearance.
 */

use App\Models\CookWallet;
use App\Models\Order;
use App\Models\OrderClearance;
use App\Models\PlatformSetting;
use App\Models\WalletTransaction;
use App\Notifications\FundsWithdrawableNotification;
use App\Services\PlatformSettingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    test()->seedRolesAndPermissions();
    Notification::fake();
});

// ─────────────────────────────────────────────
// Artisan Command Tests
// ─────────────────────────────────────────────

test('dancymeals:process-withdrawable-timers command processes eligible clearances', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    CookWallet::getOrCreateForTenant($tenant, $cook);

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
        'amount' => 5000,
        'currency' => 'XAF',
        'balance_before' => 0,
        'balance_after' => 5000,
        'is_withdrawable' => false,
        'status' => 'completed',
    ]);

    OrderClearance::factory()->eligible()->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'amount' => 5000,
    ]);

    Artisan::call('dancymeals:process-withdrawable-timers');

    $clearance = OrderClearance::where('order_id', $order->id)->first();
    expect($clearance->is_cleared)->toBeTrue();

    Notification::assertSentTo($cook, FundsWithdrawableNotification::class);
});

test('dancymeals:process-withdrawable-timers command outputs info when nothing to process', function () {
    $this->artisan('dancymeals:process-withdrawable-timers')
        ->expectsOutput(__('No eligible clearances to process.'))
        ->assertSuccessful();
});

test('dancymeals:process-withdrawable-timers command skips orders still in hold period', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'completed_at' => now()->subHour(),
    ]);

    OrderClearance::factory()->inHoldPeriod()->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    Artisan::call('dancymeals:process-withdrawable-timers');

    $clearance = OrderClearance::where('order_id', $order->id)->first();
    expect($clearance->is_cleared)->toBeFalse();

    Notification::assertNothingSent();
});

// ─────────────────────────────────────────────
// Scenario 2: Complaint filed during hold
// ─────────────────────────────────────────────

test('full scenario: complaint pauses timer then resume after resolution', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = test()->createUserWithRole('client');
    CookWallet::getOrCreateForTenant($tenant, $cook);

    $service = app(\App\Services\OrderClearanceService::class);

    // 1. Order completed
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'status' => Order::STATUS_COMPLETED,
        'completed_at' => now()->subHours(2),
    ]);

    $clearance = $service->createClearance($order, 4500);
    expect($clearance->is_paused)->toBeFalse();

    // 2. Complaint filed — timer pauses
    $complaint = \App\Models\Complaint::factory()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'status' => 'open',
    ]);

    $paused = $service->pauseTimer($order);
    expect($paused)->not->toBeNull()
        ->and($paused->is_paused)->toBeTrue()
        ->and($paused->remaining_seconds_at_pause)->toBeGreaterThan(0);

    // 3. Resolve complaint (no refund) — timer should NOT resume (complaint still active)
    // Attempt to resume while complaint is still open should fail
    $resumed = $service->resumeTimer($order);
    expect($resumed)->toBeNull();

    // 4. Now mark complaint as resolved
    $complaint->update(['status' => 'resolved']);

    $resumed = $service->resumeTimer($order);
    expect($resumed)->not->toBeNull()
        ->and($resumed->is_paused)->toBeFalse();
});

// ─────────────────────────────────────────────
// Scenario 5: Admin changes hold period
// ─────────────────────────────────────────────

test('changing hold period does not affect existing clearances', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $service = app(\App\Services\OrderClearanceService::class);

    // Create clearance with default 3 hours
    $order1 = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    $clearance1 = $service->createClearance($order1, 3000);
    expect($clearance1->hold_hours)->toBe(3);

    // Admin changes hold period to 6 hours
    PlatformSetting::updateOrCreate(
        ['key' => 'withdrawable_hold_hours'],
        ['value' => '6', 'type' => 'integer', 'group' => 'orders']
    );
    app(PlatformSettingService::class)->clearCache('withdrawable_hold_hours');

    // New order gets the new hold period
    $order2 = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    $clearance2 = $service->createClearance($order2, 5000);
    expect($clearance2->hold_hours)->toBe(6);

    // Original clearance still has 3 hours
    $clearance1->refresh();
    expect($clearance1->hold_hours)->toBe(3);
});

// ─────────────────────────────────────────────
// Database Schema Verification
// ─────────────────────────────────────────────

test('order_clearances table exists with expected columns', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('order_clearances'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Schema::hasColumns('order_clearances', [
        'id', 'order_id', 'tenant_id', 'cook_id', 'amount', 'hold_hours',
        'completed_at', 'withdrawable_at', 'paused_at', 'remaining_seconds_at_pause',
        'cleared_at', 'is_cleared', 'is_paused', 'is_cancelled',
    ]))->toBeTrue();
});

// ─────────────────────────────────────────────
// Edge Cases
// ─────────────────────────────────────────────

test('scheduled job catches up if it missed a run', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    CookWallet::getOrCreateForTenant($tenant, $cook);

    // Create an order that should have been cleared hours ago
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'completed_at' => now()->subHours(24),
    ]);

    WalletTransaction::create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
        'amount' => 2000,
        'currency' => 'XAF',
        'balance_before' => 0,
        'balance_after' => 2000,
        'is_withdrawable' => false,
        'status' => 'completed',
    ]);

    $completedAt = now()->subHours(24);
    OrderClearance::factory()->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'amount' => 2000,
        'completed_at' => $completedAt,
        'withdrawable_at' => $completedAt->copy()->addHours(3),
        'is_cleared' => false,
        'is_paused' => false,
        'is_cancelled' => false,
    ]);

    Artisan::call('dancymeals:process-withdrawable-timers');

    $clearance = OrderClearance::where('order_id', $order->id)->first();
    expect($clearance->is_cleared)->toBeTrue();
});

test('wallet balances updated correctly after clearance', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::getOrCreateForTenant($tenant, $cook);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
        'completed_at' => now()->subHours(4),
    ]);

    // Payment credit (initially unwithdrawable)
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

    $service = app(\App\Services\OrderClearanceService::class);
    $service->processEligibleClearances();

    $wallet->refresh();
    expect((float) $wallet->total_balance)->toBe(4500.0)
        ->and((float) $wallet->withdrawable_balance)->toBe(4500.0)
        ->and((float) $wallet->unwithdrawable_balance)->toBe(0.0);
});

test('command is registered in schedule', function () {
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    $events = collect($schedule->events());

    $found = $events->first(function ($event) {
        return str_contains($event->command ?? '', 'process-withdrawable-timers');
    });

    expect($found)->not->toBeNull();
});
