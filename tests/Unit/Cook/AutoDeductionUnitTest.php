<?php

use App\Models\CookWallet;
use App\Models\Order;
use App\Models\PendingDeduction;
use App\Models\Tenant;
use App\Models\WalletTransaction;
use App\Services\AutoDeductionService;
use App\Services\CookWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
    $this->service = app(AutoDeductionService::class);
});

// -----------------------------------------------
// PendingDeduction Model Tests
// -----------------------------------------------

test('PendingDeduction model: isSettled returns true when settled_at is set', function () {
    $deduction = PendingDeduction::factory()->settled()->make();
    expect($deduction->isSettled())->toBeTrue();
});

test('PendingDeduction model: isSettled returns false when not settled', function () {
    $deduction = PendingDeduction::factory()->make();
    expect($deduction->isSettled())->toBeFalse();
});

test('PendingDeduction model: isPending returns true for unsettled deductions', function () {
    $deduction = PendingDeduction::factory()->withAmount(5000)->make();
    expect($deduction->isPending())->toBeTrue();
});

test('PendingDeduction model: isPending returns false for settled deductions', function () {
    $deduction = PendingDeduction::factory()->settled()->make();
    expect($deduction->isPending())->toBeFalse();
});

test('PendingDeduction model: settledAmount calculates correctly', function () {
    $deduction = PendingDeduction::factory()->make([
        'original_amount' => 5000,
        'remaining_amount' => 2000,
    ]);
    expect($deduction->settledAmount())->toBe(3000.0);
});

test('PendingDeduction model: settlementProgress returns correct percentage', function () {
    $deduction = PendingDeduction::factory()->make([
        'original_amount' => 10000,
        'remaining_amount' => 3000,
    ]);
    expect($deduction->settlementProgress())->toBe(70.0);
});

test('PendingDeduction model: settlementProgress returns 100 for zero original', function () {
    $deduction = PendingDeduction::factory()->make([
        'original_amount' => 0,
        'remaining_amount' => 0,
    ]);
    expect($deduction->settlementProgress())->toBe(100.0);
});

test('PendingDeduction model: formattedOriginalAmount formats XAF', function () {
    $deduction = PendingDeduction::factory()->withAmount(5000)->make();
    expect($deduction->formattedOriginalAmount())->toBe('5,000 XAF');
});

test('PendingDeduction model: formattedRemainingAmount formats XAF', function () {
    $deduction = PendingDeduction::factory()->make([
        'original_amount' => 5000,
        'remaining_amount' => 3000,
    ]);
    expect($deduction->formattedRemainingAmount())->toBe('3,000 XAF');
});

test('PendingDeduction model: belongsTo CookWallet', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    $deduction = PendingDeduction::factory()->create([
        'cook_wallet_id' => $wallet->id,
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    expect($deduction->cookWallet->id)->toBe($wallet->id);
});

test('PendingDeduction model: unsettled scope filters correctly', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    PendingDeduction::factory()->forWallet($wallet)->withAmount(5000)->create();
    PendingDeduction::factory()->forWallet($wallet)->settled()->create();

    expect(PendingDeduction::unsettled()->count())->toBe(1);
});

test('PendingDeduction model: FIFO scope orders by created_at asc', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    $older = PendingDeduction::factory()->forWallet($wallet)->withAmount(3000)->create([
        'created_at' => now()->subHours(2),
    ]);
    $newer = PendingDeduction::factory()->forWallet($wallet)->withAmount(2000)->create([
        'created_at' => now()->subHour(),
    ]);

    $results = PendingDeduction::unsettled()->oldestFirst()->get();
    expect($results->first()->id)->toBe($older->id);
    expect($results->last()->id)->toBe($newer->id);
});

// -----------------------------------------------
// AutoDeductionService: createDeduction
// -----------------------------------------------

test('BR-366: createDeduction creates a pending deduction', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $deduction = $this->service->createDeduction(
        $wallet,
        $order,
        5000,
        'Refund for order',
        PendingDeduction::SOURCE_COMPLAINT_REFUND
    );

    expect($deduction)->not->toBeNull()
        ->and($deduction->original_amount)->toBe('5000.00')
        ->and($deduction->remaining_amount)->toBe('5000.00')
        ->and($deduction->cook_wallet_id)->toBe($wallet->id)
        ->and($deduction->tenant_id)->toBe($tenant->id)
        ->and($deduction->user_id)->toBe($cook->id)
        ->and($deduction->order_id)->toBe($order->id)
        ->and($deduction->settled_at)->toBeNull();
});

test('Edge case: createDeduction returns null for 0 XAF amount', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $deduction = $this->service->createDeduction($wallet, $order, 0, 'No amount');
    expect($deduction)->toBeNull();
    expect(PendingDeduction::count())->toBe(0);
});

test('Edge case: createDeduction returns null for negative amount', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $deduction = $this->service->createDeduction($wallet, $order, -100, 'Negative');
    expect($deduction)->toBeNull();
});

test('BR-376: createDeduction logs activity', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $this->service->createDeduction($wallet, $order, 5000, 'Refund test');

    $log = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'pending_deductions')
        ->where('description', 'pending_deduction_created')
        ->first();

    expect($log)->not->toBeNull()
        ->and((float) $log->properties['amount'])->toBe(5000.0);
});

// -----------------------------------------------
// AutoDeductionService: applyDeductions
// -----------------------------------------------

test('BR-367/BR-370: applyDeductions fully settles deduction when payment exceeds', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    PendingDeduction::factory()->forWallet($wallet)->withAmount(5000)
        ->create(['order_id' => $order->id]);

    $result = $this->service->applyDeductions($cook->id, $tenant->id, 8000, $order->id);

    expect($result['deducted'])->toBe(5000.0)
        ->and($result['remaining_payment'])->toBe(3000.0)
        ->and($result['deductions_applied'])->toHaveCount(1)
        ->and($result['deductions_applied'][0]['fully_settled'])->toBeTrue();

    // Deduction should be settled
    $deduction = PendingDeduction::first();
    expect((float) $deduction->remaining_amount)->toBe(0.0)
        ->and($deduction->settled_at)->not->toBeNull();
});

test('BR-369: applyDeductions partially settles deduction when payment is less', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    PendingDeduction::factory()->forWallet($wallet)->withAmount(5000)
        ->create(['order_id' => $order->id]);

    $result = $this->service->applyDeductions($cook->id, $tenant->id, 3000, $order->id);

    expect($result['deducted'])->toBe(3000.0)
        ->and($result['remaining_payment'])->toBe(0.0)
        ->and($result['deductions_applied'][0]['fully_settled'])->toBeFalse();

    $deduction = PendingDeduction::first();
    expect((float) $deduction->remaining_amount)->toBe(2000.0)
        ->and($deduction->settled_at)->toBeNull();
});

test('BR-371: applyDeductions settles FIFO (oldest first)', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    $olderOrder = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);
    $newerOrder = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $older = PendingDeduction::factory()->forWallet($wallet)->withAmount(3000)->create([
        'order_id' => $olderOrder->id,
        'created_at' => now()->subHours(2),
    ]);
    $newer = PendingDeduction::factory()->forWallet($wallet)->withAmount(2000)->create([
        'order_id' => $newerOrder->id,
        'created_at' => now()->subHour(),
    ]);

    // Payment of 4000 should settle the older (3000) fully and partially settle newer (1000)
    $result = $this->service->applyDeductions($cook->id, $tenant->id, 4000);

    expect($result['deducted'])->toBe(4000.0)
        ->and($result['remaining_payment'])->toBe(0.0)
        ->and($result['deductions_applied'])->toHaveCount(2);

    $older->refresh();
    $newer->refresh();

    expect((float) $older->remaining_amount)->toBe(0.0)
        ->and($older->settled_at)->not->toBeNull()
        ->and((float) $newer->remaining_amount)->toBe(1000.0)
        ->and($newer->settled_at)->toBeNull();
});

test('BR-373: applyDeductions creates wallet transactions of type refund_deduction', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    PendingDeduction::factory()->forWallet($wallet)->withAmount(5000)
        ->create(['order_id' => $order->id]);

    $this->service->applyDeductions($cook->id, $tenant->id, 3000, $order->id);

    $txn = WalletTransaction::where('type', WalletTransaction::TYPE_REFUND_DEDUCTION)->first();
    expect($txn)->not->toBeNull()
        ->and((float) $txn->amount)->toBe(3000.0)
        ->and($txn->user_id)->toBe($cook->id)
        ->and($txn->tenant_id)->toBe($tenant->id)
        ->and($txn->metadata['deduction_id'])->not->toBeNull()
        ->and($txn->metadata['refund_reason'])->not->toBeNull();
});

test('applyDeductions returns zero when no pending deductions exist', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();

    $result = $this->service->applyDeductions($cook->id, $tenant->id, 8000);

    expect($result['deducted'])->toBe(0.0)
        ->and($result['remaining_payment'])->toBe(8000.0)
        ->and($result['deductions_applied'])->toBeEmpty();
});

test('applyDeductions handles zero payment amount', function () {
    $result = $this->service->applyDeductions(1, 1, 0);

    expect($result['deducted'])->toBe(0.0)
        ->and($result['remaining_payment'])->toBe(0.0);
});

test('Multiple deductions settled across multiple payments', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    $order1 = Order::factory()->create(['tenant_id' => $tenant->id, 'cook_id' => $cook->id]);
    $order2 = Order::factory()->create(['tenant_id' => $tenant->id, 'cook_id' => $cook->id]);

    PendingDeduction::factory()->forWallet($wallet)->withAmount(3000)->create([
        'order_id' => $order1->id,
        'created_at' => now()->subHours(3),
    ]);
    PendingDeduction::factory()->forWallet($wallet)->withAmount(4000)->create([
        'order_id' => $order2->id,
        'created_at' => now()->subHours(2),
    ]);

    // First payment: 2000 -> settles part of first deduction
    $result1 = $this->service->applyDeductions($cook->id, $tenant->id, 2000);
    expect($result1['deducted'])->toBe(2000.0);

    // Second payment: 5000 -> settles remaining 1000 of first + 4000 of second
    $result2 = $this->service->applyDeductions($cook->id, $tenant->id, 5000);
    expect($result2['deducted'])->toBe(5000.0)
        ->and($result2['remaining_payment'])->toBe(0.0);

    // Both should be settled
    expect(PendingDeduction::unsettled()->count())->toBe(0);
});

// -----------------------------------------------
// AutoDeductionService: hasCookWithdrawnOrderFunds
// -----------------------------------------------

test('hasCookWithdrawnOrderFunds returns true when withdrawal exists after credit', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    // Payment credit for the order
    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
        'status' => 'completed',
        'created_at' => now()->subHours(5),
    ]);

    // Withdrawal after credit
    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_WITHDRAWAL,
        'status' => 'completed',
        'created_at' => now()->subHour(),
    ]);

    expect($this->service->hasCookWithdrawnOrderFunds($order))->toBeTrue();
});

test('hasCookWithdrawnOrderFunds returns false when no withdrawal exists', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
        'status' => 'completed',
    ]);

    expect($this->service->hasCookWithdrawnOrderFunds($order))->toBeFalse();
});

test('hasCookWithdrawnOrderFunds returns false when no payment credit exists', function () {
    $order = Order::factory()->create();
    expect($this->service->hasCookWithdrawnOrderFunds($order))->toBeFalse();
});

// -----------------------------------------------
// AutoDeductionService: getTotalPendingAmount
// -----------------------------------------------

test('BR-372: getTotalPendingAmount sums unsettled deductions', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    PendingDeduction::factory()->forWallet($wallet)->withAmount(3000)->create();
    PendingDeduction::factory()->forWallet($wallet)->withAmount(2000)->create();
    PendingDeduction::factory()->forWallet($wallet)->settled()->create();

    $total = $this->service->getTotalPendingAmount($tenant->id, $cook->id);
    expect($total)->toBe(5000.0);
});

test('getTotalPendingAmount returns 0 when no deductions exist', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $total = $this->service->getTotalPendingAmount($tenant->id, $cook->id);
    expect($total)->toBe(0.0);
});

// -----------------------------------------------
// AutoDeductionService: cancelDeduction
// -----------------------------------------------

test('Edge case: cancelDeduction settles the deduction', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    $deduction = PendingDeduction::factory()->forWallet($wallet)->withAmount(5000)->create();

    $result = $this->service->cancelDeduction($deduction, $cook);
    expect($result)->toBeTrue();

    $deduction->refresh();
    expect((float) $deduction->remaining_amount)->toBe(0.0)
        ->and($deduction->settled_at)->not->toBeNull();
});

test('Edge case: cancelDeduction returns false for already settled', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    $deduction = PendingDeduction::factory()->forWallet($wallet)->settled()->create();
    $result = $this->service->cancelDeduction($deduction);
    expect($result)->toBeFalse();
});

// -----------------------------------------------
// AutoDeductionService: getPendingDeductions
// -----------------------------------------------

test('BR-375: getPendingDeductions returns unsettled deductions with order', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    PendingDeduction::factory()->forWallet($wallet)->create([
        'order_id' => $order->id,
    ]);
    PendingDeduction::factory()->forWallet($wallet)->settled()->create();

    $deductions = $this->service->getPendingDeductions($tenant->id, $cook->id);
    expect($deductions)->toHaveCount(1)
        ->and($deductions->first()->order)->not->toBeNull();
});

// -----------------------------------------------
// CookWallet Model: pendingDeductions relationship
// -----------------------------------------------

test('CookWallet: totalPendingDeduction sums unsettled deductions', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    PendingDeduction::factory()->forWallet($wallet)->withAmount(3000)->create();
    PendingDeduction::factory()->forWallet($wallet)->withAmount(2000)->create();
    PendingDeduction::factory()->forWallet($wallet)->settled()->create();

    expect($wallet->totalPendingDeduction())->toBe(5000.0);
});

test('CookWallet: unsettledDeductions returns only unsettled', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    PendingDeduction::factory()->forWallet($wallet)->withAmount(3000)->create();
    PendingDeduction::factory()->forWallet($wallet)->settled()->create();

    expect($wallet->unsettledDeductions())->toHaveCount(1);
});

// -----------------------------------------------
// CookWalletService: Dashboard data
// -----------------------------------------------

test('CookWalletService: getDashboardData includes pending deductions', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    PendingDeduction::factory()->forWallet($wallet)->withAmount(5000)->create();

    $service = app(CookWalletService::class);
    $data = $service->getDashboardData($tenant, $cook);

    expect($data)->toHaveKeys(['pendingDeductions', 'totalPendingDeduction'])
        ->and($data['pendingDeductions'])->toHaveCount(1)
        ->and($data['totalPendingDeduction'])->toBe(5000.0);
});

test('CookWalletService: getDashboardData returns empty when no deductions', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);
    CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    $service = app(CookWalletService::class);
    $data = $service->getDashboardData($tenant, $cook);

    expect($data['pendingDeductions'])->toHaveCount(0)
        ->and($data['totalPendingDeduction'])->toBe(0.0);
});

// -----------------------------------------------
// Edge case: Tenant-scoped deductions
// -----------------------------------------------

test('Edge case: deductions are tenant-scoped', function () {
    ['tenant' => $tenant1, 'cook' => $cook] = createTenantWithCook();
    $tenant2 = Tenant::factory()->create();

    $wallet1 = CookWallet::factory()->create([
        'tenant_id' => $tenant1->id,
        'user_id' => $cook->id,
    ]);
    $wallet2 = CookWallet::factory()->create([
        'tenant_id' => $tenant2->id,
        'user_id' => $cook->id,
    ]);

    PendingDeduction::factory()->forWallet($wallet1)->withAmount(3000)->create();
    PendingDeduction::factory()->forWallet($wallet2)->withAmount(2000)->create();

    // Deductions should be tenant-scoped
    $tenant1Total = $this->service->getTotalPendingAmount($tenant1->id, $cook->id);
    $tenant2Total = $this->service->getTotalPendingAmount($tenant2->id, $cook->id);

    expect($tenant1Total)->toBe(3000.0)
        ->and($tenant2Total)->toBe(2000.0);

    // Apply deductions only to tenant1
    $result = $this->service->applyDeductions($cook->id, $tenant1->id, 5000);
    expect($result['deducted'])->toBe(3000.0);

    // tenant2 deduction should be unaffected
    expect($this->service->getTotalPendingAmount($tenant2->id, $cook->id))->toBe(2000.0);
});

// -----------------------------------------------
// PendingDeduction Factory Tests
// -----------------------------------------------

test('PendingDeductionFactory: default state creates unsettled deduction', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    $deduction = PendingDeduction::factory()->forWallet($wallet)->create();
    expect($deduction->isPending())->toBeTrue()
        ->and($deduction->isSettled())->toBeFalse();
});

test('PendingDeductionFactory: settled state creates settled deduction', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    $deduction = PendingDeduction::factory()->forWallet($wallet)->settled()->create();
    expect($deduction->isSettled())->toBeTrue()
        ->and((float) $deduction->remaining_amount)->toBe(0.0);
});

test('PendingDeductionFactory: partiallySettled state', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $wallet = CookWallet::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cook->id,
    ]);

    $deduction = PendingDeduction::factory()
        ->forWallet($wallet)
        ->withAmount(10000)
        ->partiallySettled(4000)
        ->create();

    expect((float) $deduction->original_amount)->toBe(10000.0)
        ->and((float) $deduction->remaining_amount)->toBe(6000.0)
        ->and($deduction->settledAmount())->toBe(4000.0);
});
