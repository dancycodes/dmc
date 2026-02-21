<?php

use App\Models\CookWallet;
use App\Models\Order;
use App\Models\WalletTransaction;
use App\Services\CommissionDeductionService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();

    // Create tenant and cook
    $result = createTenantWithCook();
    $this->tenant = $result['tenant'];
    $this->cook = $result['cook'];
});

// --- BR-377: Commission calculated when order status changes to Completed ---

test('BR-377: commission is calculated on completed order', function () {
    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 500,
        'grand_total' => 5500,
    ]);

    $service = app(CommissionDeductionService::class);
    $result = $service->processOrderCompletion($order);

    expect($result['success'])->toBeTrue()
        ->and($result['commission_amount'])->toBe(500.0)
        ->and($result['commission_rate'])->toBe(10.0);
});

test('BR-381: commission is NOT processed for non-completed orders', function () {
    $order = Order::factory()->paid()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 0,
        'grand_total' => 5000,
    ]);

    $service = app(CommissionDeductionService::class);
    $result = $service->processOrderCompletion($order);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('Order is not in completed status.');
});

// --- BR-378: Commission rate per-cook with default 10% ---

test('BR-378: default commission rate of 10% is used', function () {
    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 10000,
        'delivery_fee' => 0,
        'grand_total' => 10000,
    ]);

    $service = app(CommissionDeductionService::class);
    $result = $service->processOrderCompletion($order);

    expect($result['commission_rate'])->toBe(10.0)
        ->and($result['commission_amount'])->toBe(1000.0);
});

test('BR-378: custom commission rate is used when configured', function () {
    $this->tenant->setSetting('commission_rate', 8.0);
    $this->tenant->save();

    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 10000,
        'delivery_fee' => 0,
        'grand_total' => 10000,
    ]);

    $service = app(CommissionDeductionService::class);
    $result = $service->processOrderCompletion($order);

    expect($result['commission_rate'])->toBe(8.0)
        ->and($result['commission_amount'])->toBe(800.0);
});

// --- BR-379: Commission = order_subtotal * commission_rate ---

test('BR-379: commission equals subtotal times rate', function () {
    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 7500,
        'delivery_fee' => 500,
        'grand_total' => 8000,
    ]);

    $service = app(CommissionDeductionService::class);
    $result = $service->processOrderCompletion($order);

    // 7500 * 10% = 750
    expect($result['commission_amount'])->toBe(750.0);
});

// --- BR-380: Delivery fee excluded from commission calculation ---

test('BR-380: delivery fee is excluded from commission calculation', function () {
    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 1000,
        'grand_total' => 6000,
    ]);

    $service = app(CommissionDeductionService::class);
    $result = $service->processOrderCompletion($order);

    // Commission on 5000 only (not 6000)
    expect($result['commission_amount'])->toBe(500.0);
});

// --- BR-382: Cook wallet receives (subtotal - commission) + delivery_fee ---

test('BR-382: cook receives subtotal minus commission plus delivery fee', function () {
    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 500,
        'grand_total' => 5500,
    ]);

    $service = app(CommissionDeductionService::class);
    $result = $service->processOrderCompletion($order);

    // Cook receives: (5000 - 500) + 500 = 5000
    expect($result['cook_credit'])->toBe(5000.0);
});

test('BR-382: pickup order — cook receives subtotal minus commission only', function () {
    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 0,
        'delivery_method' => Order::METHOD_PICKUP,
        'grand_total' => 5000,
    ]);

    $service = app(CommissionDeductionService::class);
    $result = $service->processOrderCompletion($order);

    // Cook receives: (5000 - 500) + 0 = 4500
    expect($result['cook_credit'])->toBe(4500.0);
});

// --- BR-383: Commission transaction record created ---

test('BR-383: commission transaction record is created', function () {
    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 500,
        'grand_total' => 5500,
    ]);

    $service = app(CommissionDeductionService::class);
    $service->processOrderCompletion($order);

    $commissionTx = WalletTransaction::query()
        ->where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_COMMISSION)
        ->first();

    expect($commissionTx)->not->toBeNull()
        ->and((float) $commissionTx->amount)->toBe(500.0)
        ->and($commissionTx->status)->toBe('completed');
});

// --- BR-384: Commission transaction references order and shows rate ---

test('BR-384: commission transaction references order and shows rate', function () {
    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 0,
        'grand_total' => 5000,
    ]);

    $service = app(CommissionDeductionService::class);
    $service->processOrderCompletion($order);

    $commissionTx = WalletTransaction::query()
        ->where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_COMMISSION)
        ->first();

    expect($commissionTx->order_id)->toBe($order->id)
        ->and((float) $commissionTx->metadata['commission_rate'])->toBe(10.0)
        ->and($commissionTx->metadata['order_number'])->toBe($order->order_number);

    // Also check order record is updated
    $order->refresh();
    expect((float) $order->commission_amount)->toBe(500.0)
        ->and((float) $order->commission_rate)->toBe(10.0);
});

// --- BR-386: Commission deduction logged via Spatie Activitylog ---

test('BR-386: commission deduction is logged via activity log', function () {
    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 0,
        'grand_total' => 5000,
    ]);

    $service = app(CommissionDeductionService::class);
    $service->processOrderCompletion($order);

    $logEntry = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'commissions')
        ->where('description', 'commission_deducted')
        ->latest()
        ->first();

    expect($logEntry)->not->toBeNull()
        ->and((float) $logEntry->properties['commission_rate'])->toBe(10.0)
        ->and((float) $logEntry->properties['commission_amount'])->toBe(500.0)
        ->and($logEntry->properties['order_id'])->toBe($order->id);
});

// --- BR-387: 0% rate creates a 0 XAF record for transparency ---

test('BR-387: zero percent commission creates a 0 XAF record', function () {
    $this->tenant->setSetting('commission_rate', 0.0);
    $this->tenant->save();

    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 500,
        'grand_total' => 5500,
    ]);

    $service = app(CommissionDeductionService::class);
    $result = $service->processOrderCompletion($order);

    expect($result['commission_amount'])->toBe(0.0)
        ->and($result['cook_credit'])->toBe(5500.0);

    // 0 XAF commission record still created
    $commissionTx = WalletTransaction::query()
        ->where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_COMMISSION)
        ->first();

    expect($commissionTx)->not->toBeNull()
        ->and((float) $commissionTx->amount)->toBe(0.0);
});

// --- Edge case: high commission rate ---

test('edge case: 50% commission — cook receives half subtotal plus delivery', function () {
    $this->tenant->setSetting('commission_rate', 50.0);
    $this->tenant->save();

    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 10000,
        'delivery_fee' => 500,
        'grand_total' => 10500,
    ]);

    $service = app(CommissionDeductionService::class);
    $result = $service->processOrderCompletion($order);

    // 50% of 10000 = 5000 commission
    expect($result['commission_amount'])->toBe(5000.0)
        ->and($result['cook_credit'])->toBe(5500.0); // (10000 - 5000) + 500
});

// --- Edge case: Fractional XAF rounds down in cook's favor ---

test('edge case: fractional commission rounds down in cooks favor', function () {
    $this->tenant->setSetting('commission_rate', 7.5);
    $this->tenant->save();

    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 3333,
        'delivery_fee' => 0,
        'grand_total' => 3333,
    ]);

    $service = app(CommissionDeductionService::class);
    $result = $service->processOrderCompletion($order);

    // 3333 * 7.5% = 249.975 -> floor = 249
    expect($result['commission_amount'])->toBe(249.0)
        ->and($result['cook_credit'])->toBe(3084.0); // 3333 - 249
});

// --- Edge case: 0 XAF subtotal (fully promo-covered) ---

test('edge case: zero subtotal results in zero commission', function () {
    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 0,
        'delivery_fee' => 500,
        'grand_total' => 500,
    ]);

    $service = app(CommissionDeductionService::class);
    $result = $service->processOrderCompletion($order);

    expect($result['commission_amount'])->toBe(0.0)
        ->and($result['cook_credit'])->toBe(500.0);
});

// --- Payment credit transaction created ---

test('payment credit wallet transaction is created', function () {
    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 500,
        'grand_total' => 5500,
    ]);

    $service = app(CommissionDeductionService::class);
    $service->processOrderCompletion($order);

    $creditTx = WalletTransaction::query()
        ->where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)
        ->first();

    expect($creditTx)->not->toBeNull()
        ->and((float) $creditTx->amount)->toBe(5000.0)
        ->and($creditTx->is_withdrawable)->toBeFalse()
        ->and($creditTx->withdrawable_at)->not->toBeNull();
});

// --- F-171 integration: OrderClearance created ---

test('F-171: order clearance record is created after commission', function () {
    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 500,
        'grand_total' => 5500,
    ]);

    $service = app(CommissionDeductionService::class);
    $service->processOrderCompletion($order);

    $clearance = \App\Models\OrderClearance::query()
        ->where('order_id', $order->id)
        ->first();

    expect($clearance)->not->toBeNull()
        ->and((float) $clearance->amount)->toBe(5000.0)
        ->and($clearance->is_cleared)->toBeFalse();
});

// --- Wallet balance recalculation ---

test('cook wallet balance is recalculated after commission', function () {
    $wallet = CookWallet::getOrCreateForTenant($this->tenant, $this->cook);

    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 500,
        'grand_total' => 5500,
    ]);

    $service = app(CommissionDeductionService::class);
    $service->processOrderCompletion($order);

    $wallet->refresh();
    expect((float) $wallet->total_balance)->toBe(5000.0)
        ->and((float) $wallet->unwithdrawable_balance)->toBe(5000.0);
});

// --- Static calculation helpers ---

test('calculateCommission static method works correctly', function () {
    expect(CommissionDeductionService::calculateCommission(5000, 10))->toBe(500.0)
        ->and(CommissionDeductionService::calculateCommission(10000, 8))->toBe(800.0)
        ->and(CommissionDeductionService::calculateCommission(3333, 7.5))->toBe(249.0)
        ->and(CommissionDeductionService::calculateCommission(0, 10))->toBe(0.0)
        ->and(CommissionDeductionService::calculateCommission(5000, 0))->toBe(0.0);
});

test('calculateCookCredit static method works correctly', function () {
    expect(CommissionDeductionService::calculateCookCredit(5000, 500, 10))->toBe(5000.0)
        ->and(CommissionDeductionService::calculateCookCredit(5000, 0, 10))->toBe(4500.0)
        ->and(CommissionDeductionService::calculateCookCredit(10000, 1000, 8))->toBe(10200.0);
});

// --- Missing tenant/cook handling ---

test('returns failure when cook is missing', function () {
    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => null,
        'subtotal' => 5000,
        'delivery_fee' => 0,
        'grand_total' => 5000,
    ]);

    $service = app(CommissionDeductionService::class);
    $result = $service->processOrderCompletion($order);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('Missing tenant or cook.');
});

// --- Cancelled order: no commission ---

test('cancelled order never has commission deducted', function () {
    $order = Order::factory()->cancelled()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 0,
        'grand_total' => 5000,
    ]);

    $service = app(CommissionDeductionService::class);
    $result = $service->processOrderCompletion($order);

    expect($result['success'])->toBeFalse();

    $commissionCount = WalletTransaction::query()
        ->where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_COMMISSION)
        ->count();

    expect($commissionCount)->toBe(0);
});

// --- Two transactions created per order ---

test('both payment credit and commission transactions are created', function () {
    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 5000,
        'delivery_fee' => 500,
        'grand_total' => 5500,
    ]);

    $service = app(CommissionDeductionService::class);
    $service->processOrderCompletion($order);

    $transactions = WalletTransaction::query()
        ->where('order_id', $order->id)
        ->get();

    expect($transactions->count())->toBe(2);
    expect($transactions->where('type', WalletTransaction::TYPE_PAYMENT_CREDIT)->count())->toBe(1);
    expect($transactions->where('type', WalletTransaction::TYPE_COMMISSION)->count())->toBe(1);
});

// --- Commission description contains rate ---

test('commission description contains the rate percentage', function () {
    $this->tenant->setSetting('commission_rate', 12.5);
    $this->tenant->save();

    $order = Order::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'cook_id' => $this->cook->id,
        'subtotal' => 8000,
        'delivery_fee' => 0,
        'grand_total' => 8000,
    ]);

    $service = app(CommissionDeductionService::class);
    $service->processOrderCompletion($order);

    $commissionTx = WalletTransaction::query()
        ->where('order_id', $order->id)
        ->where('type', WalletTransaction::TYPE_COMMISSION)
        ->first();

    expect($commissionTx->description)->toContain('12.5')
        ->and($commissionTx->description)->toContain($order->order_number);
});
