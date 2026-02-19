<?php

use App\Models\Meal;
use App\Models\MealComponent;
use App\Services\MealComponentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-120: Meal Component Delete — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for MealComponentService::deleteComponent(),
| canDeleteComponent(), and position recalculation.
| BR-298 to BR-305 coverage.
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

/*
|--------------------------------------------------------------------------
| deleteComponent() — Success Cases
|--------------------------------------------------------------------------
*/

test('deletes a component from a meal with multiple components', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
    ]);

    $comp1 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    $comp2 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 2]);
    $comp3 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 3]);

    $result = $this->componentService->deleteComponent($comp2);

    expect($result['success'])->toBeTrue()
        ->and($result['entity_name'])->not->toBeEmpty()
        ->and(MealComponent::find($comp2->id))->toBeNull()
        ->and($meal->components()->count())->toBe(2);
});

test('deletes the only component of a draft meal', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_DRAFT,
    ]);

    $comp = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);

    $result = $this->componentService->deleteComponent($comp);

    expect($result['success'])->toBeTrue()
        ->and(MealComponent::find($comp->id))->toBeNull()
        ->and($meal->components()->count())->toBe(0);
});

test('hard deletes the component (not soft delete)', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_DRAFT,
    ]);

    $comp = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    $compId = $comp->id;

    $this->componentService->deleteComponent($comp);

    // Hard delete — no record exists even if SoftDeletes were used
    $this->assertDatabaseMissing('meal_components', ['id' => $compId]);
});

/*
|--------------------------------------------------------------------------
| deleteComponent() — Block Cases
|--------------------------------------------------------------------------
*/

test('blocks deletion of the only component of a live meal (BR-298)', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
    ]);

    $comp = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);

    $result = $this->componentService->deleteComponent($comp);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('Cannot delete the only component')
        ->and(MealComponent::find($comp->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Position Recalculation (BR-304)
|--------------------------------------------------------------------------
*/

test('recalculates positions after deleting a middle component', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
    ]);

    $comp1 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    $comp2 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 2]);
    $comp3 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 3]);

    $this->componentService->deleteComponent($comp2);

    expect($comp1->fresh()->position)->toBe(1)
        ->and($comp3->fresh()->position)->toBe(2);
});

test('recalculates positions after deleting the first component', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
    ]);

    $comp1 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    $comp2 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 2]);
    $comp3 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 3]);

    $this->componentService->deleteComponent($comp1);

    expect($comp2->fresh()->position)->toBe(1)
        ->and($comp3->fresh()->position)->toBe(2);
});

test('no position changes when deleting the last position component', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
    ]);

    $comp1 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    $comp2 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 2]);

    $this->componentService->deleteComponent($comp2);

    expect($comp1->fresh()->position)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| canDeleteComponent() — UI Check
|--------------------------------------------------------------------------
*/

test('canDeleteComponent returns true for a deletable component', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
    ]);

    $comp1 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    $comp2 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 2]);

    $result = $this->componentService->canDeleteComponent($comp1, $meal, 2);

    expect($result['can_delete'])->toBeTrue();
});

test('canDeleteComponent returns false for the only component of a live meal', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
    ]);

    $comp = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);

    $result = $this->componentService->canDeleteComponent($comp, $meal, 1);

    expect($result['can_delete'])->toBeFalse()
        ->and($result['reason'])->toContain('Cannot delete the only component');
});

test('canDeleteComponent returns true for the only component of a draft meal', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_DRAFT,
    ]);

    $comp = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);

    $result = $this->componentService->canDeleteComponent($comp, $meal, 1);

    expect($result['can_delete'])->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Forward-Compatible Order Check (BR-299)
|--------------------------------------------------------------------------
*/

test('getPendingOrderCountForComponent returns 0 when orders table does not exist', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $comp = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);

    $count = $this->componentService->getPendingOrderCountForComponent($comp);

    expect($count)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Entity Name Return
|--------------------------------------------------------------------------
*/

test('returns the entity name on successful deletion', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_DRAFT,
    ]);

    $comp = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'Extra Rice',
        'name_fr' => 'Riz supplementaire',
        'position' => 1,
    ]);

    $result = $this->componentService->deleteComponent($comp);

    expect($result['success'])->toBeTrue()
        ->and($result['entity_name'])->not->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

test('deleting all components from a draft meal leaves zero components', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_DRAFT,
    ]);

    $comp1 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    $comp2 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 2]);

    $this->componentService->deleteComponent($comp1);
    // After deleting comp1, comp2 is position 1 and is the only component (draft meal allows)
    $this->componentService->deleteComponent($comp2->fresh());

    expect($meal->components()->count())->toBe(0);
});

test('multiple deletions maintain correct position sequence', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_LIVE,
    ]);

    $comp1 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    $comp2 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 2]);
    $comp3 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 3]);
    $comp4 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 4]);

    // Delete position 2
    $this->componentService->deleteComponent($comp2);

    // After deletion: comp1=1, comp3=2, comp4=3
    expect($comp1->fresh()->position)->toBe(1)
        ->and($comp3->fresh()->position)->toBe(2)
        ->and($comp4->fresh()->position)->toBe(3);

    // Delete position 2 again (which is now comp3)
    $this->componentService->deleteComponent($comp3->fresh());

    // After deletion: comp1=1, comp4=2
    expect($comp1->fresh()->position)->toBe(1)
        ->and($comp4->fresh()->position)->toBe(2);
});
