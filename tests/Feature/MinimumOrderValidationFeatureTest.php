<?php

/**
 * F-144: Minimum Order Amount Validation — Feature Tests
 *
 * Tests the server-side minimum order amount validation at the checkout endpoint.
 * Verifies that crafted POST requests cannot bypass the frontend minimum check.
 *
 * Business rules tested:
 * - BR-299: Minimum configured per cook in tenant settings
 * - BR-300: Minimum vs food subtotal ONLY (before delivery fee)
 * - BR-301: Error message format
 * - BR-302: Checkout blocked when minimum not met
 * - BR-304: Skip validation when minimum is 0 or null
 */

use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\User;
use App\Services\CookSettingsService;
use App\Services\TenantService;

beforeEach(function () {
    test()->seedRolesAndPermissions();
    test()->seedSellingUnits();

    $this->tenantData = test()->createTenantWithCook();
    $this->tenant = $this->tenantData['tenant'];
    $this->cook = $this->tenantData['cook'];
    $this->client = User::factory()->create();
    $this->client->assignRole('client');

    $this->mainDomain = TenantService::mainDomain();
    $this->checkoutUrl = "https://{$this->tenant->slug}.{$this->mainDomain}/cart/checkout";

    $this->meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_available' => true,
        'name_en' => 'Test Meal',
    ]);
    $this->component = MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'is_available' => true,
        'price' => 1000,
        'name_en' => 'Test Component',
    ]);
});

/**
 * Build a cart session matching CartService's internal format.
 *
 * CartService stores items keyed by component_id (string) in:
 *   session["dmc-cart-{tenantId}"] = ["{componentId}" => [...item data...]]
 *
 * Each item has: component_id, meal_id, meal_name, name, unit_price, unit, quantity
 */
function buildCartSession(int $tenantId, array $cartItems): array
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

// ---- BR-304: No minimum configured — checkout proceeds ----

test('checkout proceeds when no minimum is configured (BR-304)', function () {
    // Minimum = 0 (default) — validation skipped
    $sessionData = buildCartSession($this->tenant->id, [
        [
            'component_id' => $this->component->id,
            'meal_id' => $this->meal->id,
            'meal_name' => 'Test Meal',
            'name' => 'Test Component',
            'unit_price' => 1000,
            'quantity' => 1,
        ],
    ]);

    $response = $this->actingAs($this->client)
        ->withSession($sessionData)
        ->post($this->checkoutUrl, [], ['Gale-Request' => '1']);

    // Should redirect to delivery method (not blocked) — 200 SSE redirect or 302
    expect($response->status())->toBeIn([200, 302]);
    $content = $response->getContent();
    expect($content)->not->toContain('Minimum order');
});

// ---- BR-302: Checkout blocked when below minimum ----

test('checkout is blocked when cart subtotal is below minimum (BR-302)', function () {
    // Set minimum to 2000 XAF
    $this->tenant->setSetting(CookSettingsService::MINIMUM_ORDER_AMOUNT_KEY, 2000);
    $this->tenant->save();

    // Cart = 1000 XAF (below 2000 minimum)
    $sessionData = buildCartSession($this->tenant->id, [
        [
            'component_id' => $this->component->id,
            'meal_id' => $this->meal->id,
            'meal_name' => 'Test Meal',
            'name' => 'Test Component',
            'unit_price' => 1000,
            'quantity' => 1,
        ],
    ]);

    $response = $this->actingAs($this->client)
        ->withSession($sessionData)
        ->post($this->checkoutUrl, [], ['Gale-Request' => '1']);

    // Gale state response — status 200 (SSE stream)
    expect($response->status())->toBe(200);
    $content = $response->getContent();
    // Should contain the cart error with minimum order message
    expect($content)->toContain('cartError');
});

// ---- Cart exactly meets minimum — proceeds ----

test('checkout proceeds when cart subtotal exactly equals minimum', function () {
    // Set minimum to 1000 XAF
    $this->tenant->setSetting(CookSettingsService::MINIMUM_ORDER_AMOUNT_KEY, 1000);
    $this->tenant->save();

    // Cart = 1000 XAF (exactly meets minimum: NOT below)
    $sessionData = buildCartSession($this->tenant->id, [
        [
            'component_id' => $this->component->id,
            'meal_id' => $this->meal->id,
            'meal_name' => 'Test Meal',
            'name' => 'Test Component',
            'unit_price' => 1000,
            'quantity' => 1,
        ],
    ]);

    $response = $this->actingAs($this->client)
        ->withSession($sessionData)
        ->post($this->checkoutUrl, [], ['Gale-Request' => '1']);

    // Should redirect to delivery method (Gale 200 SSE redirect or 302)
    expect($response->status())->toBeIn([200, 302]);
    $content = $response->getContent();
    // Must NOT show the minimum error
    expect($content)->not->toContain('Minimum order is 1,000');
});

// ---- BR-304: Zero minimum — always valid ----

test('checkout proceeds with any amount when minimum is 0 (BR-304)', function () {
    // Explicitly set minimum to 0 (disabled)
    $this->tenant->setSetting(CookSettingsService::MINIMUM_ORDER_AMOUNT_KEY, 0);
    $this->tenant->save();

    // Cart with just 100 XAF
    $cheapComponent = MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'is_available' => true,
        'price' => 100,
        'name_en' => 'Cheap Item',
    ]);

    $sessionData = buildCartSession($this->tenant->id, [
        [
            'component_id' => $cheapComponent->id,
            'meal_id' => $this->meal->id,
            'meal_name' => 'Test Meal',
            'name' => 'Cheap Item',
            'unit_price' => 100,
            'quantity' => 1,
        ],
    ]);

    $response = $this->actingAs($this->client)
        ->withSession($sessionData)
        ->post($this->checkoutUrl, [], ['Gale-Request' => '1']);

    expect($response->status())->toBeIn([200, 302]);
    $content = $response->getContent();
    expect($content)->not->toContain('Minimum order');
});

// ---- BR-260: Authentication still required ----

test('unauthenticated checkout is handled regardless of minimum', function () {
    $sessionData = buildCartSession($this->tenant->id, [
        [
            'component_id' => $this->component->id,
            'meal_id' => $this->meal->id,
            'meal_name' => 'Test Meal',
            'name' => 'Test Component',
            'unit_price' => 5000,
            'quantity' => 1,
        ],
    ]);

    $response = $this->withSession($sessionData)
        ->post($this->checkoutUrl, [], ['Gale-Request' => '1']);

    // Must not show minimum order error — auth check comes first
    $content = $response->getContent();
    expect($content)->not->toContain('Minimum order');
});

// ---- BR-301: Error message format ----

test('blocked checkout returns correctly formatted error message (BR-301)', function () {
    // Minimum = 3000, cart = 1000 XAF, remaining = 2000
    $this->tenant->setSetting(CookSettingsService::MINIMUM_ORDER_AMOUNT_KEY, 3000);
    $this->tenant->save();

    $sessionData = buildCartSession($this->tenant->id, [
        [
            'component_id' => $this->component->id,
            'meal_id' => $this->meal->id,
            'meal_name' => 'Test Meal',
            'name' => 'Test Component',
            'unit_price' => 1000,
            'quantity' => 1,
        ],
    ]);

    $response = $this->actingAs($this->client)
        ->withSession($sessionData)
        ->post($this->checkoutUrl, [], ['Gale-Request' => '1']);

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    // BR-301: message must reference "3,000" (minimum) and "2,000" (remaining = 3000-1000)
    expect($content)->toContain('3,000')
        ->and($content)->toContain('2,000');
});

// ---- BR-300: Delivery fee excluded from minimum calculation ----

test('minimum validation uses food subtotal only, not delivery fee (BR-300)', function () {
    // Minimum = 2000. Food subtotal = 1800 (below minimum).
    // Delivery fee is NOT part of the cart session — it is calculated later.
    // Even if delivery adds 500, the server must still block checkout (food < minimum).
    $this->tenant->setSetting(CookSettingsService::MINIMUM_ORDER_AMOUNT_KEY, 2000);
    $this->tenant->save();

    $component1800 = MealComponent::factory()->create([
        'meal_id' => $this->meal->id,
        'is_available' => true,
        'price' => 1800,
        'name_en' => 'Large Component',
    ]);

    $sessionData = buildCartSession($this->tenant->id, [
        [
            'component_id' => $component1800->id,
            'meal_id' => $this->meal->id,
            'meal_name' => 'Test Meal',
            'name' => 'Large Component',
            'unit_price' => 1800,
            'quantity' => 1,
        ],
    ]);

    $response = $this->actingAs($this->client)
        ->withSession($sessionData)
        ->post($this->checkoutUrl, [], ['Gale-Request' => '1']);

    // Should be blocked because food subtotal (1800) < minimum (2000)
    expect($response->status())->toBe(200);
    $content = $response->getContent();
    // Should show "2,000" (minimum) and "200" (remaining = 2000 - 1800)
    expect($content)->toContain('2,000')
        ->and($content)->toContain('cartError');
});

// ---- Very high minimum ----

test('error message displays correctly formatted amounts for very high minimums', function () {
    // Minimum = 100,000 XAF; cart = 1000 XAF; remaining = 99,000 XAF
    $this->tenant->setSetting(CookSettingsService::MINIMUM_ORDER_AMOUNT_KEY, 100000);
    $this->tenant->save();

    $sessionData = buildCartSession($this->tenant->id, [
        [
            'component_id' => $this->component->id,
            'meal_id' => $this->meal->id,
            'meal_name' => 'Test Meal',
            'name' => 'Test Component',
            'unit_price' => 1000,
            'quantity' => 1,
        ],
    ]);

    $response = $this->actingAs($this->client)
        ->withSession($sessionData)
        ->post($this->checkoutUrl, [], ['Gale-Request' => '1']);

    expect($response->status())->toBe(200);
    $content = $response->getContent();
    // Must contain formatted "100,000" (minimum) and "99,000" (remaining)
    expect($content)->toContain('100,000')
        ->and($content)->toContain('99,000');
});
