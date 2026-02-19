<?php

use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\SellingUnit;
use App\Models\User;
use App\Services\MealComponentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-118: Meal Component Creation — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for MealComponentService, MealComponent model, and factory.
| BR-278 to BR-291 coverage.
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

// ── Model Tests ────────────────────────────────────────────────────────

test('meal component belongs to meal', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id]);

    expect($component->meal->id)->toBe($meal->id);
});

test('meal component has correct fillable attributes', function () {
    $component = new MealComponent;
    $fillable = $component->getFillable();

    expect($fillable)->toContain('meal_id')
        ->toContain('name_en')
        ->toContain('name_fr')
        ->toContain('price')
        ->toContain('selling_unit')
        ->toContain('min_quantity')
        ->toContain('max_quantity')
        ->toContain('available_quantity')
        ->toContain('is_available')
        ->toContain('position');
});

test('meal component casts price to integer', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => '1500']);

    expect($component->price)->toBeInt()->toBe(1500);
});

test('meal component casts is_available to boolean', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'is_available' => 1]);

    expect($component->is_available)->toBeBool()->toBeTrue();
});

test('meal component has unlimited max quantity when null', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'max_quantity' => null]);

    expect($component->hasUnlimitedMaxQuantity())->toBeTrue();
});

test('meal component has limited max quantity when set', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'max_quantity' => 5]);

    expect($component->hasUnlimitedMaxQuantity())->toBeFalse();
});

test('meal component has unlimited available quantity when null', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'available_quantity' => null]);

    expect($component->hasUnlimitedAvailableQuantity())->toBeTrue();
});

test('meal component has limited available quantity when set', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'available_quantity' => 10]);

    expect($component->hasUnlimitedAvailableQuantity())->toBeFalse();
});

test('meal component formats price correctly', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 1000]);

    expect($component->formatted_price)->toBe('1,000 XAF');
});

test('meal component formats large price correctly', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 1000000]);

    expect($component->formatted_price)->toBe('1,000,000 XAF');
});

test('meal component next position for meal calculates correctly', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(MealComponent::nextPositionForMeal($meal->id))->toBe(1);

    MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    expect(MealComponent::nextPositionForMeal($meal->id))->toBe(2);

    MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 5]);
    expect(MealComponent::nextPositionForMeal($meal->id))->toBe(6);
});

test('meal component scope ordered works', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $c3 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 3]);
    $c1 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    $c2 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 2]);

    $ordered = $meal->components()->ordered()->get();

    expect($ordered->first()->id)->toBe($c1->id)
        ->and($ordered->last()->id)->toBe($c3->id);
});

test('meal component scope available works', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id, 'is_available' => true]);
    MealComponent::factory()->create(['meal_id' => $meal->id, 'is_available' => false]);

    expect($meal->components()->available()->count())->toBe(1);
});

test('standard units constant has correct values', function () {
    expect(MealComponent::STANDARD_UNITS)->toContain('plate')
        ->toContain('bowl')
        ->toContain('pot')
        ->toContain('cup')
        ->toContain('piece')
        ->toContain('portion')
        ->toContain('serving')
        ->toContain('pack')
        ->toHaveCount(8);
});

test('unit labels exist for all standard units', function () {
    foreach (MealComponent::STANDARD_UNITS as $unit) {
        expect(MealComponent::UNIT_LABELS)->toHaveKey($unit);
        expect(MealComponent::UNIT_LABELS[$unit])->toHaveKeys(['en', 'fr']);
    }
});

test('unit label accessor returns correct label for standard unit', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'selling_unit' => 'plate']);

    app()->setLocale('en');
    expect($component->unit_label)->toBe('Plate');

    app()->setLocale('fr');
    expect($component->unit_label)->toBe('Assiette');
});

test('unit label accessor capitalizes unknown units', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id, 'selling_unit' => 'calabash']);

    expect($component->unit_label)->toBe('Calabash');
});

// ── Service Tests ──────────────────────────────────────────────────────

test('service creates component with valid data', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $plateUnit = SellingUnit::where('name_en', 'Plate')->where('is_standard', true)->first();

    $result = $this->componentService->createComponent($meal, [
        'name_en' => 'Ndole + Plantain',
        'name_fr' => 'Ndole + Plantain',
        'price' => 1000,
        'selling_unit' => (string) $plateUnit->id,
        'min_quantity' => 0,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['component'])->toBeInstanceOf(MealComponent::class)
        ->and($result['component']->name_en)->toBe('Ndole + Plantain')
        ->and($result['component']->price)->toBe(1000)
        ->and($result['component']->selling_unit)->toBe((string) $plateUnit->id)
        ->and($result['component']->min_quantity)->toBe(0)
        ->and($result['component']->max_quantity)->toBeNull()
        ->and($result['component']->available_quantity)->toBeNull()
        ->and($result['component']->is_available)->toBeTrue()
        ->and($result['component']->position)->toBe(1);
});

test('service creates component with quantity limits', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $potUnit = SellingUnit::where('name_en', 'Pot')->where('is_standard', true)->first();

    $result = $this->componentService->createComponent($meal, [
        'name_en' => 'Special Pot',
        'name_fr' => 'Marmite speciale',
        'price' => 5000,
        'selling_unit' => (string) $potUnit->id,
        'min_quantity' => 1,
        'max_quantity' => 3,
        'available_quantity' => 10,
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['component']->min_quantity)->toBe(1)
        ->and($result['component']->max_quantity)->toBe(3)
        ->and($result['component']->available_quantity)->toBe(10);
});

test('service fails when min quantity exceeds max quantity', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->componentService->createComponent($meal, [
        'name_en' => 'Test Component',
        'name_fr' => 'Composant test',
        'price' => 1000,
        'selling_unit' => 'plate',
        'min_quantity' => 5,
        'max_quantity' => 3,
        'available_quantity' => null,
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('Minimum quantity');
});

test('service fails with invalid selling unit', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->componentService->createComponent($meal, [
        'name_en' => 'Test Component',
        'name_fr' => 'Composant test',
        'price' => 1000,
        'selling_unit' => 'invalid_unit_xyz',
        'min_quantity' => 0,
        'max_quantity' => null,
        'available_quantity' => null,
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('selling unit');
});

test('service auto-increments position for each new component', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $plateUnit = SellingUnit::where('name_en', 'Plate')->where('is_standard', true)->first();
    $bowlUnit = SellingUnit::where('name_en', 'Bowl')->where('is_standard', true)->first();

    $result1 = $this->componentService->createComponent($meal, [
        'name_en' => 'First',
        'name_fr' => 'Premier',
        'price' => 500,
        'selling_unit' => (string) $plateUnit->id,
    ]);

    $result2 = $this->componentService->createComponent($meal, [
        'name_en' => 'Second',
        'name_fr' => 'Deuxieme',
        'price' => 750,
        'selling_unit' => (string) $bowlUnit->id,
    ]);

    expect($result1['component']->position)->toBe(1)
        ->and($result2['component']->position)->toBe(2);
});

test('service treats empty string max quantity as unlimited', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $plateUnit = SellingUnit::where('name_en', 'Plate')->where('is_standard', true)->first();

    $result = $this->componentService->createComponent($meal, [
        'name_en' => 'Test',
        'name_fr' => 'Test',
        'price' => 1000,
        'selling_unit' => (string) $plateUnit->id,
        'max_quantity' => '',
        'available_quantity' => '',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['component']->max_quantity)->toBeNull()
        ->and($result['component']->available_quantity)->toBeNull();
});

test('service trims component names', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $plateUnit = SellingUnit::where('name_en', 'Plate')->where('is_standard', true)->first();

    $result = $this->componentService->createComponent($meal, [
        'name_en' => '  Spaced Name  ',
        'name_fr' => '  Nom Espace  ',
        'price' => 1000,
        'selling_unit' => (string) $plateUnit->id,
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['component']->name_en)->toBe('Spaced Name')
        ->and($result['component']->name_fr)->toBe('Nom Espace');
});

test('service accepts very high price', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $potUnit = SellingUnit::where('name_en', 'Pot')->where('is_standard', true)->first();

    $result = $this->componentService->createComponent($meal, [
        'name_en' => 'Expensive Item',
        'name_fr' => 'Article cher',
        'price' => 1000000,
        'selling_unit' => (string) $potUnit->id,
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['component']->price)->toBe(1000000);
});

test('service allows same name components in same meal', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $plateUnit = SellingUnit::where('name_en', 'Plate')->where('is_standard', true)->first();
    $bowlUnit = SellingUnit::where('name_en', 'Bowl')->where('is_standard', true)->first();

    $result1 = $this->componentService->createComponent($meal, [
        'name_en' => 'Same Name',
        'name_fr' => 'Meme Nom',
        'price' => 1000,
        'selling_unit' => (string) $plateUnit->id,
    ]);

    $result2 = $this->componentService->createComponent($meal, [
        'name_en' => 'Same Name',
        'name_fr' => 'Meme Nom',
        'price' => 1500,
        'selling_unit' => (string) $bowlUnit->id,
    ]);

    expect($result1['success'])->toBeTrue()
        ->and($result2['success'])->toBeTrue();
});

test('service get available units returns all standard unit IDs', function () {
    $units = $this->componentService->getAvailableUnits($this->tenant);

    // F-121: getAvailableUnits now returns string IDs from the selling_units table
    expect($units)->toHaveCount(8);
    foreach ($units as $unitId) {
        expect($unitId)->toBeString();
        expect(is_numeric($unitId))->toBeTrue();
    }
});

test('service get available units with labels returns labeled units', function () {
    app()->setLocale('en');
    $units = $this->componentService->getAvailableUnitsWithLabels($this->tenant);

    expect($units)->toHaveCount(8);
    expect($units[0])->toHaveKeys(['value', 'label', 'is_standard']);
    // F-121: value is now the selling_unit ID as a string
    expect(is_numeric($units[0]['value']))->toBeTrue();
    expect($units[0]['is_standard'])->toBeTrue();
});

test('service get components data returns ordered components', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 2]);
    MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);

    $data = $this->componentService->getComponentsData($meal);

    expect($data['count'])->toBe(2)
        ->and($data['components']->first()->position)->toBe(1);
});

// ── Factory Tests ──────────────────────────────────────────────────────

test('meal component factory creates valid component', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id]);

    // F-121: selling_unit is now a numeric ID referencing selling_units table
    $validUnitIds = SellingUnit::where('is_standard', true)->pluck('id')->map(fn ($id) => (string) $id)->toArray();

    expect($component)->toBeInstanceOf(MealComponent::class)
        ->and($component->name_en)->not->toBeEmpty()
        ->and($component->name_fr)->not->toBeEmpty()
        ->and($component->price)->toBeGreaterThanOrEqual(1)
        ->and($component->selling_unit)->toBeIn($validUnitIds)
        ->and($component->is_available)->toBeTrue();
});

test('meal component factory with quantity limits works', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->withQuantityLimits(1, 5, 20)->create(['meal_id' => $meal->id]);

    expect($component->min_quantity)->toBe(1)
        ->and($component->max_quantity)->toBe(5)
        ->and($component->available_quantity)->toBe(20);
});

test('meal component factory unavailable state works', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->unavailable()->create(['meal_id' => $meal->id]);

    expect($component->is_available)->toBeFalse();
});

test('meal component factory with unit works', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->withUnit('pot')->create(['meal_id' => $meal->id]);

    expect($component->selling_unit)->toBe('pot');
});

// ── Controller / Route Tests ───────────────────────────────────────────

test('store route returns 403 without permission', function () {
    $user = User::factory()->create();
    $user->assignRole('client');
    $plateUnit = SellingUnit::where('name_en', 'Plate')->where('is_standard', true)->first();

    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);

    $mainDomain = config('app.url');
    $tenantUrl = str_replace('://', '://'.$this->tenant->slug.'.', $mainDomain);

    $this->actingAs($user)
        ->post($tenantUrl.'/dashboard/meals/'.$meal->id.'/components', [
            'name_en' => 'Test',
            'name_fr' => 'Test',
            'price' => 1000,
            'selling_unit' => (string) $plateUnit->id,
        ])
        ->assertForbidden();
});

test('store route creates component with cook permission', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $plateUnit = SellingUnit::where('name_en', 'Plate')->where('is_standard', true)->first();

    $mainDomain = config('app.url');
    $tenantUrl = str_replace('://', '://'.$this->tenant->slug.'.', $mainDomain);

    $this->actingAs($this->cook)
        ->post($tenantUrl.'/dashboard/meals/'.$meal->id.'/components', [
            'name_en' => 'Test Component',
            'name_fr' => 'Composant Test',
            'price' => 1000,
            'selling_unit' => (string) $plateUnit->id,
        ]);

    $this->assertDatabaseHas('meal_components', [
        'meal_id' => $meal->id,
        'name_en' => 'Test Component',
        'price' => 1000,
        'selling_unit' => (string) $plateUnit->id,
    ]);
});

test('store route validates required fields', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);

    $mainDomain = config('app.url');
    $tenantUrl = str_replace('://', '://'.$this->tenant->slug.'.', $mainDomain);

    $this->actingAs($this->cook)
        ->post($tenantUrl.'/dashboard/meals/'.$meal->id.'/components', [
            'name_en' => '',
            'name_fr' => '',
            'price' => 0,
            'selling_unit' => '',
        ])
        ->assertSessionHasErrors(['name_en', 'name_fr', 'price', 'selling_unit']);
});

// ── Activity Logging ───────────────────────────────────────────────────

test('component creation is logged in activity log', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $bowlUnit = SellingUnit::where('name_en', 'Bowl')->where('is_standard', true)->first();

    $mainDomain = config('app.url');
    $tenantUrl = str_replace('://', '://'.$this->tenant->slug.'.', $mainDomain);

    $this->actingAs($this->cook)
        ->post($tenantUrl.'/dashboard/meals/'.$meal->id.'/components', [
            'name_en' => 'Logged Component',
            'name_fr' => 'Composant enregistre',
            'price' => 2000,
            'selling_unit' => (string) $bowlUnit->id,
        ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'meal_components',
        'description' => 'Meal component created',
    ]);
});

// ── Meal Relationship Tests ────────────────────────────────────────────

test('meal has many components relationship', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->count(3)->create(['meal_id' => $meal->id]);

    expect($meal->components()->count())->toBe(3);
});

test('meal component count is used for go-live check', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'draft',
    ]);

    $mealService = new \App\Services\MealService;
    $result = $mealService->toggleStatus($meal);
    expect($result['success'])->toBeFalse();

    MealComponent::factory()->create(['meal_id' => $meal->id]);
    $result = $mealService->toggleStatus($meal->fresh());
    expect($result['success'])->toBeTrue()
        ->and($result['new_status'])->toBe('live');
});

// ── Edge Cases ─────────────────────────────────────────────────────────

test('component name with accents is stored correctly', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $plateUnit = SellingUnit::where('name_en', 'Plate')->where('is_standard', true)->first();

    $result = $this->componentService->createComponent($meal, [
        'name_en' => 'Pate with legumes',
        'name_fr' => 'Pate aux legumes',
        'price' => 1500,
        'selling_unit' => (string) $plateUnit->id,
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['component']->name_fr)->toBe('Pate aux legumes');
});

test('meal component uses translatable trait for name', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'English Name',
        'name_fr' => 'French Name',
    ]);

    app()->setLocale('en');
    expect($component->name)->toBe('English Name');

    app()->setLocale('fr');
    expect($component->name)->toBe('French Name');
});
