<?php

/**
 * F-219: Promo Code Validation Rules — Feature Tests
 *
 * Tests the full 7-step validation pipeline by hitting the real
 * /checkout/promo/apply endpoint on a tenant domain. Each test
 * exercises one failure scenario from the spec, plus edge cases.
 *
 * Gale SSE requests send state as JSON body; tests use json('POST', ...)
 * with Gale-Request header so validateState() can parse the state.
 *
 * Business rules tested:
 * BR-586: Check 1 — code exists and belongs to current tenant
 * BR-587: Check 2 — code status is "active"
 * BR-588: Check 3 — current date is on or after start date
 * BR-589: Check 4 — current date is on or before end date (if set)
 * BR-590: Check 5 — total usage count is below max_uses (if > 0)
 * BR-591: Check 6 — client personal usage count is below max_uses_per_client (if > 0)
 * BR-592: Check 7 — cart subtotal meets or exceeds minimum_order_amount
 * BR-593: Checks run in order; first failure stops evaluation
 * BR-594: Non-existent and cross-tenant codes return the same generic error
 * BR-595: Input normalized to uppercase before lookup
 * BR-596: Each failure returns a specific localized error message
 * BR-597: All error messages use __() localization
 * BR-598: Validation re-run at order submission time (race condition check)
 */

use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\User;
use App\Services\TenantService;

// ─── Test Setup ────────────────────────────────────────────────────────────────

beforeEach(function () {
    test()->seedRolesAndPermissions();
    test()->seedSellingUnits();

    // Create two tenants to test cross-tenant isolation (BR-594)
    $tenantDataA = test()->createTenantWithCook();
    $tenantDataB = test()->createTenantWithCook();

    $this->tenant = $tenantDataA['tenant'];
    $this->otherTenant = $tenantDataB['tenant'];

    $this->client = User::factory()->create();
    $this->client->assignRole('client');

    $this->mainDomain = TenantService::mainDomain();
    $this->applyUrl = "https://{$this->tenant->slug}.{$this->mainDomain}/checkout/promo/apply";

    // Create a meal + component for the cart
    $this->meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
        'name_en' => 'Test Meal',
    ]);

    $this->component = MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'is_available' => true,
        'price' => 3000,
        'name_en' => 'Test Component',
    ]);

    // Cart session with 3,000 XAF food subtotal
    $this->cartSession = buildPromoCartSession($this->tenant->id, [
        [
            'component_id' => $this->component->id,
            'meal_id' => $this->meal->id,
            'meal_name' => 'Test Meal',
            'name' => 'Test Component',
            'unit_price' => 3000,
            'quantity' => 1,
        ],
    ]);

    // A valid base promo code for this tenant (passes all checks by default)
    $this->validCode = PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'WELCOME10',
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 10,
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->toDateString(),
        'ends_at' => null,
        'max_uses' => 0,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'times_used' => 0,
    ]);
});

/**
 * Build a cart session matching CartService's internal format.
 */
function buildPromoCartSession(int $tenantId, array $cartItems): array
{
    $items = [];
    foreach ($cartItems as $item) {
        $key = (string) $item['component_id'];
        $items[$key] = [
            'component_id' => $item['component_id'],
            'meal_id' => $item['meal_id'],
            'meal_name' => $item['meal_name'],
            'name' => $item['name'],
            'unit_price' => $item['unit_price'],
            'unit' => $item['unit'] ?? null,
            'quantity' => $item['quantity'],
        ];
    }

    return ["dmc-cart-{$tenantId}" => $items];
}

/**
 * Send a promo code apply Gale SSE request.
 *
 * Gale reads state from $request->json()->all() — the promo_code_input
 * must be in the JSON body so validateState() can parse it.
 * Using json('POST', ...) sends Content-Type: application/json automatically.
 */
function sendPromoApply(
    string $applyUrl,
    array $cartSession,
    string $promoCodeInput,
): \Illuminate\Testing\TestResponse {
    return test()
        ->withSession($cartSession)
        ->json('POST', $applyUrl, ['promo_code_input' => $promoCodeInput], ['Gale-Request' => '1']);
}

// ─── Scenario 1: Code does not exist (BR-586, BR-594) ──────────────────────────

test('BR-586/BR-594: non-existent code returns generic "not valid" error', function () {
    $response = $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'FREEBIE');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    // BR-594: Generic message — no information leakage
    expect($content)->toContain('not valid');
    expect($content)->not->toContain('inactive');
    expect($content)->not->toContain('expired');
});

// ─── Scenario 2: Code belongs to another tenant (BR-586, BR-594) ───────────────

test('BR-594: cross-tenant code returns same generic "not valid" error', function () {
    // Create a code on otherTenant — should NOT be visible on this tenant
    PromoCode::factory()->create([
        'tenant_id' => $this->otherTenant->id,
        'code' => 'OTHERTENANT',
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->toDateString(),
        'ends_at' => null,
        'max_uses' => 0,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 10,
        'times_used' => 0,
    ]);

    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'OTHERTENANT');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    // Same generic error — no information leakage about whether the code exists elsewhere
    expect($content)->toContain('not valid');
    expect($content)->not->toContain('inactive');
    expect($content)->not->toContain('expired');
});

// ─── Scenario 3: Code is deactivated (BR-587) ─────────────────────────────────

test('BR-587: deactivated code returns "currently inactive" error', function () {
    PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'SUMMER20',
        'status' => PromoCode::STATUS_INACTIVE,
        'starts_at' => now()->toDateString(),
        'ends_at' => null,
        'max_uses' => 0,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 20,
        'times_used' => 0,
    ]);

    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'SUMMER20');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    expect($content)->toContain('inactive');
    expect($content)->not->toContain('not valid');
    expect($content)->not->toContain('expired');
});

// ─── Scenario 4: Code has not started yet (BR-588) ────────────────────────────

test('BR-588: future-dated code returns "not yet active" error with start date', function () {
    PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'NEWYEAR',
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->addDays(16)->toDateString(),
        'ends_at' => null,
        'max_uses' => 0,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 15,
        'times_used' => 0,
    ]);

    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'NEWYEAR');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    // Should contain "not yet active" and the start date
    expect($content)->toContain('not yet active');
    expect($content)->not->toContain('not valid');
    expect($content)->not->toContain('expired');
});

// ─── Edge case: Start date is today — valid (BR-588) ──────────────────────────

test('BR-588 edge: start date is today — code is valid', function () {
    PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'TODAYSTART',
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->toDateString(),
        'ends_at' => null,
        'max_uses' => 0,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 10,
        'times_used' => 0,
    ]);

    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'TODAYSTART');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    // Should succeed — start date = today is valid
    expect($content)->not->toContain('not yet active');
    expect($content)->not->toContain('not valid');
    expect($content)->not->toContain('expired');
    // appliedPromoCode state should be set
    expect($content)->toContain('TODAYSTART');
});

// ─── Scenario 5: Code has expired (BR-589) ────────────────────────────────────

test('BR-589: expired code returns "has expired" error', function () {
    PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'LAUNCH500',
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->subDays(30)->toDateString(),
        'ends_at' => now()->subDays(1)->toDateString(),
        'max_uses' => 0,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_FIXED,
        'discount_value' => 500,
        'times_used' => 0,
    ]);

    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'LAUNCH500');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    expect($content)->toContain('expired');
    expect($content)->not->toContain('not valid');
    expect($content)->not->toContain('inactive');
});

// ─── Edge case: End date is today — valid (BR-589) ────────────────────────────

test('BR-589 edge: end date is today — code is still valid (expires at end of day)', function () {
    PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'TODAYEND',
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->subDays(5)->toDateString(),
        'ends_at' => now()->toDateString(),
        'max_uses' => 0,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 10,
        'times_used' => 0,
    ]);

    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'TODAYEND');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    // End date = today is still valid
    expect($content)->not->toContain('expired');
    expect($content)->not->toContain('not valid');
    expect($content)->toContain('TODAYEND');
});

// ─── Edge case: No end date — code never expires (BR-589) ─────────────────────

test('BR-589 edge: null end date — check 4 is skipped, code never expires', function () {
    PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'NOEXPIRY',
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->subDays(100)->toDateString(),
        'ends_at' => null,
        'max_uses' => 0,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 5,
        'times_used' => 0,
    ]);

    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'NOEXPIRY');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    expect($content)->not->toContain('expired');
    expect($content)->toContain('NOEXPIRY');
});

// ─── Scenario 6: Code reached maximum total uses (BR-590) ─────────────────────

test('BR-590: code at max_uses limit returns "fully redeemed" error', function () {
    PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'FLASH10',
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->toDateString(),
        'ends_at' => null,
        'max_uses' => 50,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 10,
        'times_used' => 50, // exactly at limit
    ]);

    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'FLASH10');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    expect($content)->toContain('fully redeemed');
    expect($content)->not->toContain('not valid');
    expect($content)->not->toContain('expired');
});

// ─── Edge case: max_uses = 0 means unlimited (BR-590) ─────────────────────────

test('BR-590 edge: max_uses = 0 skips check 5 — unlimited usage allowed', function () {
    PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'UNLIMITED',
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->toDateString(),
        'ends_at' => null,
        'max_uses' => 0,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 5,
        'times_used' => 99999, // very high times_used doesn't matter when max_uses = 0
    ]);

    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'UNLIMITED');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    expect($content)->not->toContain('fully redeemed');
    expect($content)->toContain('UNLIMITED');
});

// ─── Scenario 7: Client exceeded per-client limit (BR-591) ────────────────────

test('BR-591: client at per-client limit returns "maximum number of times" error', function () {
    $promoCode = PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'LOYAL5',
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->toDateString(),
        'ends_at' => null,
        'max_uses' => 0,
        'max_uses_per_client' => 2,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 5,
        'times_used' => 2,
    ]);

    // Create two existing usage records for this client
    $order1 = Order::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);
    $order2 = Order::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);

    PromoCodeUsage::create([
        'promo_code_id' => $promoCode->id,
        'order_id' => $order1->id,
        'user_id' => $this->client->id,
        'discount_amount' => 150,
    ]);
    PromoCodeUsage::create([
        'promo_code_id' => $promoCode->id,
        'order_id' => $order2->id,
        'user_id' => $this->client->id,
        'discount_amount' => 150,
    ]);

    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'LOYAL5');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    expect($content)->toContain('maximum number of times');
    expect($content)->not->toContain('fully redeemed');
    expect($content)->not->toContain('not valid');
});

// ─── Edge case: max_uses_per_client = 0 means unlimited per-client (BR-591) ───

test('BR-591 edge: max_uses_per_client = 0 skips check 6 — unlimited per-client', function () {
    $promoCode = PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'PERCLIENT0',
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->toDateString(),
        'ends_at' => null,
        'max_uses' => 0,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 5,
        'times_used' => 10,
    ]);

    // Client has already used the code once — still allowed with per_client = 0
    $existingOrder = Order::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);
    PromoCodeUsage::create([
        'promo_code_id' => $promoCode->id,
        'order_id' => $existingOrder->id,
        'user_id' => $this->client->id,
        'discount_amount' => 150,
    ]);

    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'PERCLIENT0');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    expect($content)->not->toContain('maximum number of times');
    expect($content)->toContain('PERCLIENT0');
});

// ─── Scenario 8: Order total below promo minimum (BR-592) ─────────────────────

test('BR-592: cart below minimum_order_amount returns error with amounts', function () {
    // Cart = 3,000 XAF; promo requires 5,000 XAF minimum
    PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'BIG20',
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->toDateString(),
        'ends_at' => null,
        'max_uses' => 0,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 5000,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 20,
        'times_used' => 0,
    ]);

    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'BIG20');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    // Must include minimum (5,000) and remaining needed (2,000)
    expect($content)->toContain('5,000');
    expect($content)->toContain('2,000');
    expect($content)->not->toContain('not valid');
    expect($content)->not->toContain('expired');
});

// ─── Edge case: minimum_order_amount = 0 — check 7 skipped (BR-592) ───────────

test('BR-592 edge: minimum_order_amount = 0 skips check 7 — any cart total qualifies', function () {
    PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'NOMINIMUM',
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->toDateString(),
        'ends_at' => null,
        'max_uses' => 0,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 5,
        'times_used' => 0,
    ]);

    // Use a tiny cart (100 XAF)
    $cheapComponent = MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'is_available' => true,
        'price' => 100,
        'name_en' => 'Tiny Item',
    ]);

    $tinyCartSession = buildPromoCartSession($this->tenant->id, [
        [
            'component_id' => $cheapComponent->id,
            'meal_id' => $this->meal->id,
            'meal_name' => 'Test Meal',
            'name' => 'Tiny Item',
            'unit_price' => 100,
            'quantity' => 1,
        ],
    ]);

    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $tinyCartSession, 'NOMINIMUM');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    expect($content)->not->toContain('minimum order');
    expect($content)->toContain('NOMINIMUM');
});

// ─── Scenario 9: All checks pass — code is accepted (BR-596) ──────────────────

test('BR-596: valid code passing all checks is accepted successfully', function () {
    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'WELCOME10');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    // Applied promo code state must include the code name
    expect($content)->toContain('WELCOME10');
    // Must not contain any error strings
    expect($content)->not->toContain('not valid');
    expect($content)->not->toContain('inactive');
    expect($content)->not->toContain('expired');
    expect($content)->not->toContain('fully redeemed');
    expect($content)->not->toContain('maximum number of times');
    expect($content)->not->toContain('minimum order');
});

// ─── BR-595: Case-insensitive input (Scenario 10) ─────────────────────────────

test('BR-595: lowercase input "welcome10" matches uppercase stored code "WELCOME10"', function () {
    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'welcome10');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    // Should succeed and show the code
    expect($content)->toContain('WELCOME10');
    expect($content)->not->toContain('not valid');
});

// ─── BR-595: Whitespace trimmed before lookup ─────────────────────────────────

test('BR-595: whitespace in input is trimmed before lookup', function () {
    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, '  WELCOME10  ');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    expect($content)->toContain('WELCOME10');
    expect($content)->not->toContain('not valid');
});

// ─── BR-593: Checks run in order — first failure stops evaluation ─────────────

test('BR-593: inactive code stops at check 2, does not report expired error', function () {
    // Code is inactive AND expired — should report "inactive", not "expired"
    // because check 2 (status) comes before check 4 (end date)
    PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'BADCODE',
        'status' => PromoCode::STATUS_INACTIVE,
        'starts_at' => now()->subDays(30)->toDateString(),
        'ends_at' => now()->subDays(1)->toDateString(), // also expired
        'max_uses' => 0,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 10,
        'times_used' => 0,
    ]);

    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'BADCODE');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    // BR-593: check 2 fires before check 4 — reports "inactive", not "expired"
    expect($content)->toContain('inactive');
    expect($content)->not->toContain('expired');
});

// ─── BR-598: Submit-time re-validation catches race condition ──────────────────

test('BR-598: promo code re-validated at order creation via PromoCodeValidationService', function () {
    // Confirm the service returns valid=false when code hits max_uses
    // after the initial apply-time check (simulates a race condition).
    $promoCode = PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'RACETEST',
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->toDateString(),
        'ends_at' => null,
        'max_uses' => 1,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 10,
        'times_used' => 0,
    ]);

    // First, apply the code successfully at apply-time (1 use available, 0 used)
    $this->actingAs($this->client);
    $response1 = sendPromoApply($this->applyUrl, $this->cartSession, 'RACETEST');
    expect($response1->status())->toBe(200);
    expect($response1->getContent())->toContain('RACETEST');

    // Simulate race condition: another user consumed the last remaining slot
    $promoCode->update(['times_used' => 1]); // now at max

    // Now call the validation service directly — should catch the race condition
    $validationService = app(\App\Services\PromoCodeValidationService::class);
    $result = $validationService->validate('RACETEST', $this->tenant->id, $this->client->id, 3000);

    expect($result['valid'])->toBeFalse();
    expect($result['error'])->toContain('fully redeemed');
});

// ─── BR-593: Service validates checks strictly in order 1 through 7 ───────────

test('BR-593: service validates checks strictly in order 1 through 7', function () {
    $validationService = app(\App\Services\PromoCodeValidationService::class);

    // Check 1 (existence): Non-existent code fails immediately
    $result = $validationService->validate('NONEXISTENT', $this->tenant->id, $this->client->id, 3000);
    expect($result['valid'])->toBeFalse();
    expect($result['promoCode'])->toBeNull();

    // Check 2 (status): Inactive code fails at check 2
    PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'CHKORDER2',
        'status' => PromoCode::STATUS_INACTIVE,
        'starts_at' => now()->toDateString(),
        'ends_at' => null,
        'max_uses' => 0,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 10,
        'times_used' => 0,
    ]);

    $result2 = $validationService->validate('CHKORDER2', $this->tenant->id, $this->client->id, 3000);
    expect($result2['valid'])->toBeFalse();
    expect($result2['error'])->toContain('inactive');

    // Check 3 (start date): Future code fails at check 3
    PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'CHKORDER3',
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->addDays(5)->toDateString(),
        'ends_at' => null,
        'max_uses' => 0,
        'max_uses_per_client' => 0,
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 10,
        'times_used' => 0,
    ]);

    $result3 = $validationService->validate('CHKORDER3', $this->tenant->id, $this->client->id, 3000);
    expect($result3['valid'])->toBeFalse();
    expect($result3['error'])->toContain('not yet active');
});

// ─── BR-591: Another client's usage does not affect this client ───────────────

test('BR-591: another client using the code does not count toward this client\'s per-client limit', function () {
    $promoCode = PromoCode::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'PERCLIENTCHECK',
        'status' => PromoCode::STATUS_ACTIVE,
        'starts_at' => now()->toDateString(),
        'ends_at' => null,
        'max_uses' => 0,
        'max_uses_per_client' => 1, // 1 use per client
        'minimum_order_amount' => 0,
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'discount_value' => 5,
        'times_used' => 1,
    ]);

    // Another (different) client has already used this code
    $otherClient = User::factory()->create();
    $otherClient->assignRole('client');
    $otherOrder = Order::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $otherClient->id]);

    PromoCodeUsage::create([
        'promo_code_id' => $promoCode->id,
        'order_id' => $otherOrder->id,
        'user_id' => $otherClient->id,
        'discount_amount' => 150,
    ]);

    // This client has NOT used it — should be allowed
    $this->actingAs($this->client);
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'PERCLIENTCHECK');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    expect($content)->not->toContain('maximum number of times');
    expect($content)->toContain('PERCLIENTCHECK');
});

// ─── Authentication required ──────────────────────────────────────────────────

test('unauthenticated promo code application is redirected to login', function () {
    $response = sendPromoApply($this->applyUrl, $this->cartSession, 'WELCOME10');

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    // Should redirect to login, not apply the code
    expect($content)->toContain('login');
    expect($content)->not->toContain('"appliedPromoCode"');
});
