<?php

/**
 * F-170: Cook Wallet Transaction History -- Unit Tests
 *
 * Tests for CookWalletService transaction history methods
 * and WalletController::transactions().
 * BR-323 through BR-332.
 */

use App\Models\Order;
use App\Models\Tenant;
use App\Models\WalletTransaction;
use App\Services\CookWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
});

// =============================================
// CookWalletService::getTransactionHistory Tests
// =============================================

test('getTransactionHistory returns paginated results scoped to tenant', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    // Create 25 transactions for this tenant (more than 1 page)
    WalletTransaction::factory()->count(25)->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
    ]);

    // Create transactions for another tenant (should not appear)
    $otherTenant = Tenant::factory()->create();
    WalletTransaction::factory()->count(5)->create([
        'user_id' => $cook->id,
        'tenant_id' => $otherTenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
    ]);

    $service = new CookWalletService;
    $results = $service->getTransactionHistory($tenant, $cook, []);

    expect($results)->toHaveCount(20)
        ->and($results->total())->toBe(25);

    $results->each(fn ($txn) => expect($txn->tenant_id)->toBe($tenant->id));
});

test('getTransactionHistory defaults to newest first (desc)', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
        'created_at' => now()->subDays(2),
    ]);

    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_COMMISSION,
        'created_at' => now(),
    ]);

    $service = new CookWalletService;
    $results = $service->getTransactionHistory($tenant, $cook, []);

    expect($results->first()->created_at->gt($results->last()->created_at))->toBeTrue();
});

test('getTransactionHistory sorts ascending when direction is asc', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
        'created_at' => now()->subDays(2),
    ]);

    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_COMMISSION,
        'created_at' => now(),
    ]);

    $service = new CookWalletService;
    $results = $service->getTransactionHistory($tenant, $cook, ['direction' => 'asc']);

    expect($results->first()->created_at->lt($results->last()->created_at))->toBeTrue();
});

test('getTransactionHistory filters by type', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    WalletTransaction::factory()->count(5)->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
    ]);

    WalletTransaction::factory()->count(3)->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_COMMISSION,
    ]);

    WalletTransaction::factory()->count(2)->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_WITHDRAWAL,
    ]);

    $service = new CookWalletService;

    $paymentResults = $service->getTransactionHistory($tenant, $cook, ['type' => WalletTransaction::TYPE_PAYMENT_CREDIT]);
    expect($paymentResults->total())->toBe(5);

    $commissionResults = $service->getTransactionHistory($tenant, $cook, ['type' => WalletTransaction::TYPE_COMMISSION]);
    expect($commissionResults->total())->toBe(3);

    $withdrawalResults = $service->getTransactionHistory($tenant, $cook, ['type' => WalletTransaction::TYPE_WITHDRAWAL]);
    expect($withdrawalResults->total())->toBe(2);
});

test('getTransactionHistory returns all when type filter is empty', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    WalletTransaction::factory()->count(3)->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
    ]);

    WalletTransaction::factory()->count(2)->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_COMMISSION,
    ]);

    $service = new CookWalletService;
    $results = $service->getTransactionHistory($tenant, $cook, ['type' => '']);

    expect($results->total())->toBe(5);
});

test('getTransactionHistory returns empty paginator when no transactions', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $service = new CookWalletService;
    $results = $service->getTransactionHistory($tenant, $cook, []);

    expect($results)->toHaveCount(0)
        ->and($results->total())->toBe(0);
});

test('getTransactionHistory eager loads order relationship', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
    ]);

    $service = new CookWalletService;
    $results = $service->getTransactionHistory($tenant, $cook, []);

    expect($results->first()->relationLoaded('order'))->toBeTrue()
        ->and($results->first()->order->order_number)->toBe($order->order_number);
});

test('getTransactionHistory handles invalid direction gracefully', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
    ]);

    $service = new CookWalletService;
    $results = $service->getTransactionHistory($tenant, $cook, ['direction' => 'invalid']);

    // Should default to desc
    expect($results->total())->toBe(1);
});

// =============================================
// CookWalletService::getTransactionSummaryCounts Tests
// =============================================

test('getTransactionSummaryCounts returns correct counts per type', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    WalletTransaction::factory()->count(5)->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
    ]);

    WalletTransaction::factory()->count(3)->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_COMMISSION,
    ]);

    WalletTransaction::factory()->count(2)->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_WITHDRAWAL,
    ]);

    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_REFUND_DEDUCTION,
    ]);

    WalletTransaction::factory()->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_REFUND,
    ]);

    $service = new CookWalletService;
    $counts = $service->getTransactionSummaryCounts($tenant, $cook);

    expect($counts['total'])->toBe(12)
        ->and($counts['order_payments'])->toBe(5)
        ->and($counts['commissions'])->toBe(3)
        ->and($counts['withdrawals'])->toBe(2)
        ->and($counts['auto_deductions'])->toBe(1)
        ->and($counts['clearances'])->toBe(1);
});

test('getTransactionSummaryCounts returns zeros when no transactions', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $service = new CookWalletService;
    $counts = $service->getTransactionSummaryCounts($tenant, $cook);

    expect($counts['total'])->toBe(0)
        ->and($counts['order_payments'])->toBe(0)
        ->and($counts['commissions'])->toBe(0)
        ->and($counts['withdrawals'])->toBe(0)
        ->and($counts['auto_deductions'])->toBe(0)
        ->and($counts['clearances'])->toBe(0);
});

test('getTransactionSummaryCounts is tenant scoped', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    WalletTransaction::factory()->count(3)->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
    ]);

    // Other tenant transactions should not count
    $otherTenant = Tenant::factory()->create();
    WalletTransaction::factory()->count(5)->create([
        'user_id' => $cook->id,
        'tenant_id' => $otherTenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
    ]);

    $service = new CookWalletService;
    $counts = $service->getTransactionSummaryCounts($tenant, $cook);

    expect($counts['total'])->toBe(3)
        ->and($counts['order_payments'])->toBe(3);
});

// =============================================
// CookWalletService::getTypeFilterOptions Tests
// =============================================

test('getTypeFilterOptions returns all filter options', function () {
    $options = CookWalletService::getTypeFilterOptions();

    expect($options)->toHaveCount(5);

    $values = array_column($options, 'value');

    expect($values)->toContain(WalletTransaction::TYPE_PAYMENT_CREDIT)
        ->toContain(WalletTransaction::TYPE_COMMISSION)
        ->toContain(WalletTransaction::TYPE_WITHDRAWAL)
        ->toContain(WalletTransaction::TYPE_REFUND_DEDUCTION)
        ->toContain(WalletTransaction::TYPE_REFUND);
});

test('getTypeFilterOptions returns label and value for each option', function () {
    $options = CookWalletService::getTypeFilterOptions();

    foreach ($options as $option) {
        expect($option)->toHaveKey('value')
            ->toHaveKey('label');
        expect($option['label'])->not->toBeEmpty();
        expect($option['value'])->not->toBeEmpty();
    }
});

// =============================================
// CookWalletService::getTransactionTypeLabel Tests
// =============================================

test('getTransactionTypeLabel returns correct labels for all types', function () {
    expect(CookWalletService::getTransactionTypeLabel(WalletTransaction::TYPE_PAYMENT_CREDIT))
        ->toBe(__('Order Payment'))
        ->and(CookWalletService::getTransactionTypeLabel(WalletTransaction::TYPE_COMMISSION))
        ->toBe(__('Commission'))
        ->and(CookWalletService::getTransactionTypeLabel(WalletTransaction::TYPE_WITHDRAWAL))
        ->toBe(__('Withdrawal'))
        ->and(CookWalletService::getTransactionTypeLabel(WalletTransaction::TYPE_REFUND_DEDUCTION))
        ->toBe(__('Auto-Deduction'))
        ->and(CookWalletService::getTransactionTypeLabel(WalletTransaction::TYPE_REFUND))
        ->toBe(__('Clearance'))
        ->and(CookWalletService::getTransactionTypeLabel(WalletTransaction::TYPE_WALLET_PAYMENT))
        ->toBe(__('Wallet Payment'))
        ->and(CookWalletService::getTransactionTypeLabel('unknown_type'))
        ->toBe(__('Transaction'));
});

// =============================================
// Controller Access Tests
// =============================================

test('cook can access wallet transaction history', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($cook);

    $response = $this->get("https://{$tenant->slug}.{$mainDomain}/dashboard/wallet/transactions");

    $response->assertStatus(200);
});

test('manager with can-manage-cook-wallet permission can access transaction history', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $manager = createUser('manager');
    $manager->givePermissionTo('can-manage-cook-wallet');

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($manager);

    $response = $this->get("https://{$tenant->slug}.{$mainDomain}/dashboard/wallet/transactions");

    $response->assertStatus(200);
});

test('manager without permission gets 403 on transaction history', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $manager = createUser('manager');

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($manager);

    $response = $this->get("https://{$tenant->slug}.{$mainDomain}/dashboard/wallet/transactions");

    $response->assertStatus(403);
});

test('unauthenticated user is redirected from transaction history', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->get("https://{$tenant->slug}.{$mainDomain}/dashboard/wallet/transactions");

    $response->assertRedirect();
});

test('transaction history supports type filter via query param', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    WalletTransaction::factory()->count(3)->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
    ]);

    WalletTransaction::factory()->count(2)->create([
        'user_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'type' => WalletTransaction::TYPE_COMMISSION,
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($cook);

    $response = $this->get("https://{$tenant->slug}.{$mainDomain}/dashboard/wallet/transactions?type=payment_credit");

    $response->assertStatus(200);
});

test('transaction history rejects invalid type filter', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($cook);

    $response = $this->get("https://{$tenant->slug}.{$mainDomain}/dashboard/wallet/transactions?type=invalid_type");

    $response->assertStatus(302);
});

test('transaction history per page is 20', function () {
    expect(CookWalletService::TRANSACTION_HISTORY_PER_PAGE)->toBe(20);
});
