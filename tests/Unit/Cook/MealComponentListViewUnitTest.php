<?php

use App\Models\Meal;
use App\Models\MealComponent;
use App\Services\MealComponentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-125: Meal Component List View — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for MealComponentService::reorderComponents(),
| MealComponentService::getComponentsData(), and model ordering concerns.
| BR-348 to BR-354 coverage.
|
| HTTP endpoint behaviour and UI rendering verified via Playwright in Phase 3.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
    $this->seedSellingUnits();
    $result = createTenantWithCook();
    $this->tenant = $result['tenant'];
    $this->cook = $result['cook'];
    $this->tenant->update(['cook_id' => $this->cook->id]);
    $this->componentService = new MealComponentService;
});

// ── Service: reorderComponents() ─────────────────────────────────────────

test('reorderComponents updates positions for all components', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $c1 = MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal->id]);
    $c2 = MealComponent::factory()->withPosition(2)->create(['meal_id' => $meal->id]);
    $c3 = MealComponent::factory()->withPosition(3)->create(['meal_id' => $meal->id]);

    // Reverse order: c3, c1, c2
    $result = $this->componentService->reorderComponents($meal, [$c3->id, $c1->id, $c2->id]);

    expect($result['success'])->toBeTrue();

    $c1->refresh();
    $c2->refresh();
    $c3->refresh();

    expect($c3->position)->toBe(1)
        ->and($c1->position)->toBe(2)
        ->and($c2->position)->toBe(3);
});

test('reorderComponents fails with invalid component IDs', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $c1 = MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal->id]);

    $result = $this->componentService->reorderComponents($meal, [$c1->id, 99999]);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe(__('Invalid component IDs provided.'));
});

test('reorderComponents fails when not all components included', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $c1 = MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal->id]);
    MealComponent::factory()->withPosition(2)->create(['meal_id' => $meal->id]);

    // Only include one of two components
    $result = $this->componentService->reorderComponents($meal, [$c1->id]);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe(__('All components must be included in the reorder.'));
});

test('reorderComponents rejects components from a different meal', function () {
    $meal1 = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $meal2 = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $c1 = MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal1->id]);
    $c2 = MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal2->id]);

    $result = $this->componentService->reorderComponents($meal1, [$c1->id, $c2->id]);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe(__('Invalid component IDs provided.'));
});

test('reorderComponents positions start at 1', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $c1 = MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal->id]);
    $c2 = MealComponent::factory()->withPosition(2)->create(['meal_id' => $meal->id]);

    $result = $this->componentService->reorderComponents($meal, [$c2->id, $c1->id]);

    expect($result['success'])->toBeTrue();

    $c1->refresh();
    $c2->refresh();

    expect($c2->position)->toBe(1)
        ->and($c1->position)->toBe(2);
});

test('reorderComponents with same order keeps positions unchanged', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $c1 = MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal->id]);
    $c2 = MealComponent::factory()->withPosition(2)->create(['meal_id' => $meal->id]);
    $c3 = MealComponent::factory()->withPosition(3)->create(['meal_id' => $meal->id]);

    $result = $this->componentService->reorderComponents($meal, [$c1->id, $c2->id, $c3->id]);

    expect($result['success'])->toBeTrue();

    $c1->refresh();
    $c2->refresh();
    $c3->refresh();

    expect($c1->position)->toBe(1)
        ->and($c2->position)->toBe(2)
        ->and($c3->position)->toBe(3);
});

// ── Service: getComponentsData() ─────────────────────────────────────────

test('getComponentsData returns components ordered by position', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->withPosition(3)->create(['meal_id' => $meal->id, 'name_en' => 'Third']);
    MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal->id, 'name_en' => 'First']);
    MealComponent::factory()->withPosition(2)->create(['meal_id' => $meal->id, 'name_en' => 'Second']);

    $data = $this->componentService->getComponentsData($meal);

    expect($data['count'])->toBe(3)
        ->and($data['components'])->toHaveCount(3)
        ->and($data['components'][0]->name_en)->toBe('First')
        ->and($data['components'][1]->name_en)->toBe('Second')
        ->and($data['components'][2]->name_en)->toBe('Third');
});

test('getComponentsData returns empty collection for meal with no components', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);

    $data = $this->componentService->getComponentsData($meal);

    expect($data['count'])->toBe(0)
        ->and($data['components'])->toHaveCount(0);
});

test('BR-348: getComponentsData scopes to the current meal', function () {
    $meal1 = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $meal2 = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal1->id]);
    MealComponent::factory()->withPosition(2)->create(['meal_id' => $meal1->id]);
    MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal2->id]);

    $data1 = $this->componentService->getComponentsData($meal1);
    $data2 = $this->componentService->getComponentsData($meal2);

    expect($data1['count'])->toBe(2)
        ->and($data2['count'])->toBe(1);
});

// ── BR-349: Position Ordering ────────────────────────────────────────────

test('BR-349: ordered scope sorts by position ascending', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->withPosition(5)->create(['meal_id' => $meal->id, 'name_en' => 'Fifth']);
    MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal->id, 'name_en' => 'First']);
    MealComponent::factory()->withPosition(3)->create(['meal_id' => $meal->id, 'name_en' => 'Third']);

    $ordered = $meal->components()->ordered()->get();

    expect($ordered[0]->name_en)->toBe('First')
        ->and($ordered[1]->name_en)->toBe('Third')
        ->and($ordered[2]->name_en)->toBe('Fifth');
});

// ── BR-350: Reorder Persistence ──────────────────────────────────────────

test('BR-350: reorder persists new positions to database', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $c1 = MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal->id]);
    $c2 = MealComponent::factory()->withPosition(2)->create(['meal_id' => $meal->id]);

    $this->componentService->reorderComponents($meal, [$c2->id, $c1->id]);

    $this->assertDatabaseHas('meal_components', ['id' => $c1->id, 'position' => 2]);
    $this->assertDatabaseHas('meal_components', ['id' => $c2->id, 'position' => 1]);
});

// ── BR-351: Component Data Display ───────────────────────────────────────

test('BR-351: component model exposes display data', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->withPosition(1)->create([
        'meal_id' => $meal->id,
        'name_en' => 'Ndole Combo',
        'name_fr' => 'Combo Ndole',
        'price' => 2500,
        'min_quantity' => 1,
        'max_quantity' => 10,
        'available_quantity' => 25,
        'is_available' => true,
    ]);

    // Test formatted_price accessor
    expect($component->formatted_price)->toContain('2,500')
        ->and($component->formatted_price)->toContain('XAF');

    // Test unit_label accessor
    expect($component->unit_label)->toBeString();

    // Test name accessor uses localized name
    expect($component->name)->toBeString()->not->toBeEmpty();

    // Test quantity helpers
    expect($component->hasUnlimitedMaxQuantity())->toBeFalse()
        ->and($component->hasUnlimitedAvailableQuantity())->toBeFalse();
});

test('BR-351: stock status shows correct types', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);

    // Unlimited stock
    $unlimited = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => null,
    ]);
    expect($this->componentService->getStockStatus($unlimited)['type'])->toBe('unlimited');

    // Out of stock
    $outOfStock = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 0,
    ]);
    expect($this->componentService->getStockStatus($outOfStock)['type'])->toBe('out_of_stock');

    // Low stock (under threshold of 5)
    $lowStock = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 3,
    ]);
    expect($this->componentService->getStockStatus($lowStock)['type'])->toBe('low_stock');

    // In stock
    $inStock = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 50,
    ]);
    expect($this->componentService->getStockStatus($inStock)['type'])->toBe('in_stock');
});

// ── Edge Cases ───────────────────────────────────────────────────────────

test('reorderComponents with single component succeeds', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $c1 = MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal->id]);

    $result = $this->componentService->reorderComponents($meal, [$c1->id]);

    expect($result['success'])->toBeTrue();

    $c1->refresh();
    expect($c1->position)->toBe(1);
});

test('reorderComponents does not affect other meal components', function () {
    $meal1 = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $meal2 = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $c1 = MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal1->id]);
    $c2 = MealComponent::factory()->withPosition(2)->create(['meal_id' => $meal1->id]);
    $otherC = MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal2->id]);

    $this->componentService->reorderComponents($meal1, [$c2->id, $c1->id]);

    $otherC->refresh();
    expect($otherC->position)->toBe(1);
});

test('reorderComponents with duplicate IDs fails', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $c1 = MealComponent::factory()->withPosition(1)->create(['meal_id' => $meal->id]);
    MealComponent::factory()->withPosition(2)->create(['meal_id' => $meal->id]);

    // Send c1 twice — missing c2
    $result = $this->componentService->reorderComponents($meal, [$c1->id, $c1->id]);

    expect($result['success'])->toBeFalse();
});
