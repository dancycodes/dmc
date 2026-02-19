<?php

use App\Models\Meal;
use App\Models\MealComponent;
use App\Services\MealComponentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-123: Meal Component Availability Toggle — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for MealComponentService::toggleAvailability() and model concerns.
| BR-326 to BR-333 coverage.
|
| HTTP endpoint behaviour is verified via Playwright in Phase 3.
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

// ── Service: toggleAvailability() ────────────────────────────────────────

test('toggleAvailability toggles from available to unavailable', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'is_available' => true,
    ]);

    $result = $this->componentService->toggleAvailability($component);

    expect($result['success'])->toBeTrue()
        ->and($result['old_availability'])->toBeTrue()
        ->and($result['new_availability'])->toBeFalse()
        ->and($result['component']->is_available)->toBeFalse();
});

test('toggleAvailability toggles from unavailable to available', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->unavailable()->create([
        'meal_id' => $meal->id,
    ]);

    $result = $this->componentService->toggleAvailability($component);

    expect($result['success'])->toBeTrue()
        ->and($result['old_availability'])->toBeFalse()
        ->and($result['new_availability'])->toBeTrue()
        ->and($result['component']->is_available)->toBeTrue();
});

test('toggleAvailability persists to database', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'is_available' => true,
    ]);

    $this->componentService->toggleAvailability($component);

    $this->assertDatabaseHas('meal_components', [
        'id' => $component->id,
        'is_available' => false,
    ]);
});

test('toggleAvailability returns fresh component model', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'is_available' => true,
    ]);

    $result = $this->componentService->toggleAvailability($component);

    expect($result['component'])->toBeInstanceOf(MealComponent::class)
        ->and($result['component']->is_available)->toBeFalse();
});

// ── BR-326: Independence from Meal Availability ─────────────────────────

test('BR-326: component availability is independent of meal availability', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => false,
    ]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'is_available' => true,
    ]);

    $result = $this->componentService->toggleAvailability($component);

    // Component toggled independently
    expect($result['new_availability'])->toBeFalse();
    // Meal availability unchanged
    $meal->refresh();
    expect($meal->is_available)->toBeFalse();
});

test('BR-326: meal availability toggle does not affect component availability', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_available' => true,
    ]);
    $component = MealComponent::factory()->unavailable()->create([
        'meal_id' => $meal->id,
    ]);

    // Toggle meal availability
    $meal->update(['is_available' => false]);

    // Component stays as-is
    $component->refresh();
    expect($component->is_available)->toBeFalse();
});

// ── BR-330: Immediate Effect & Rapid Toggle ──────────────────────────────

test('BR-330: rapid toggle results in correct final state', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'is_available' => true,
    ]);

    // Toggle 3 times: true -> false -> true -> false
    $this->componentService->toggleAvailability($component);
    $component->refresh();
    $this->componentService->toggleAvailability($component);
    $component->refresh();
    $result = $this->componentService->toggleAvailability($component);

    expect($result['new_availability'])->toBeFalse();
    $this->assertDatabaseHas('meal_components', [
        'id' => $component->id,
        'is_available' => false,
    ]);
});

test('BR-330: double toggle returns to original state', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'is_available' => true,
    ]);

    $this->componentService->toggleAvailability($component);
    $component->refresh();
    $result = $this->componentService->toggleAvailability($component);

    expect($result['new_availability'])->toBeTrue();
    $this->assertDatabaseHas('meal_components', [
        'id' => $component->id,
        'is_available' => true,
    ]);
});

// ── BR-329: Pending Orders Unaffected ────────────────────────────────────

test('BR-329: toggling does not affect other component fields', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'is_available' => true,
        'price' => 1500,
        'min_quantity' => 1,
        'max_quantity' => 5,
        'available_quantity' => 10,
        'position' => 2,
    ]);

    $this->componentService->toggleAvailability($component);
    $component->refresh();

    expect($component->price)->toBe(1500)
        ->and($component->min_quantity)->toBe(1)
        ->and($component->max_quantity)->toBe(5)
        ->and($component->available_quantity)->toBe(10)
        ->and($component->position)->toBe(2)
        ->and($component->is_available)->toBeFalse();
});

// ── Model Scope Tests ────────────────────────────────────────────────────

test('scopeAvailable returns only available components', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id, 'is_available' => true]);
    MealComponent::factory()->unavailable()->create(['meal_id' => $meal->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id, 'is_available' => true]);

    $available = MealComponent::where('meal_id', $meal->id)->available()->count();

    expect($available)->toBe(2);
});

test('scopeAvailable excludes toggled-off components', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $c1 = MealComponent::factory()->create(['meal_id' => $meal->id, 'is_available' => true]);
    $c2 = MealComponent::factory()->create(['meal_id' => $meal->id, 'is_available' => true]);

    $this->componentService->toggleAvailability($c1);

    $available = MealComponent::where('meal_id', $meal->id)->available()->get();

    expect($available)->toHaveCount(1)
        ->and($available->first()->id)->toBe($c2->id);
});

test('unavailable factory state works correctly', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->unavailable()->create(['meal_id' => $meal->id]);

    expect($component->is_available)->toBeFalse();
});

// ── Edge Case Tests ──────────────────────────────────────────────────────

test('all components of a meal can be toggled to unavailable', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'live',
        'is_available' => true,
    ]);
    $c1 = MealComponent::factory()->create(['meal_id' => $meal->id, 'is_available' => true]);
    $c2 = MealComponent::factory()->create(['meal_id' => $meal->id, 'is_available' => true]);

    $this->componentService->toggleAvailability($c1);
    $this->componentService->toggleAvailability($c2);

    $available = MealComponent::where('meal_id', $meal->id)->available()->count();
    expect($available)->toBe(0);

    // Meal itself remains available (BR-326)
    $meal->refresh();
    expect($meal->is_available)->toBeTrue();
});

test('toggling unavailable component with quantity 0 back to available works', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->unavailable()->create([
        'meal_id' => $meal->id,
        'available_quantity' => 0,
    ]);

    // Toggle works; BR-333 note: manual re-enable possible
    // only if available quantity is also increased (F-124 concern)
    $result = $this->componentService->toggleAvailability($component);

    expect($result['new_availability'])->toBeTrue();
});

test('toggling component with unlimited available quantity works', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'is_available' => true,
        'available_quantity' => null,
    ]);

    $result = $this->componentService->toggleAvailability($component);

    expect($result['new_availability'])->toBeFalse()
        ->and($result['component']->available_quantity)->toBeNull();
});

test('toggling preserves name translations', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'Ndole with Plantain',
        'name_fr' => 'Ndole avec Plantain',
        'is_available' => true,
    ]);

    $this->componentService->toggleAvailability($component);
    $component->refresh();

    expect($component->name_en)->toBe('Ndole with Plantain')
        ->and($component->name_fr)->toBe('Ndole avec Plantain')
        ->and($component->is_available)->toBeFalse();
});

test('multiple components in same meal can have different availability states', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $c1 = MealComponent::factory()->create(['meal_id' => $meal->id, 'is_available' => true]);
    $c2 = MealComponent::factory()->create(['meal_id' => $meal->id, 'is_available' => true]);
    $c3 = MealComponent::factory()->create(['meal_id' => $meal->id, 'is_available' => true]);

    // Toggle only the first one
    $this->componentService->toggleAvailability($c1);

    $c1->refresh();
    $c2->refresh();
    $c3->refresh();

    expect($c1->is_available)->toBeFalse()
        ->and($c2->is_available)->toBeTrue()
        ->and($c3->is_available)->toBeTrue();
});
