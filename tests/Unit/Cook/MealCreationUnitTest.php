<?php

use App\Models\Meal;
use App\Models\Tenant;
use App\Services\MealService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-108: Meal Creation Form — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for the Meal model, MealFactory, and MealService.
| Verifies business rules BR-187 through BR-197.
|
*/

// ── Meal Model Tests ──

it('has the correct fillable attributes', function () {
    $meal = new Meal;
    $fillable = $meal->getFillable();

    expect($fillable)->toContain('tenant_id')
        ->toContain('name_en')
        ->toContain('name_fr')
        ->toContain('description_en')
        ->toContain('description_fr')
        ->toContain('status')
        ->toContain('is_available')
        ->toContain('estimated_prep_time')
        ->toContain('position')
        ->toContain('price')
        ->toContain('is_active');
});

it('has correct casts', function () {
    $meal = new Meal;
    $casts = $meal->getCasts();

    expect($casts['price'])->toBe('integer')
        ->and($casts['is_active'])->toBe('boolean')
        ->and($casts['is_available'])->toBe('boolean')
        ->and($casts['estimated_prep_time'])->toBe('integer')
        ->and($casts['position'])->toBe('integer');
});

it('has status constants', function () {
    expect(Meal::STATUS_DRAFT)->toBe('draft')
        ->and(Meal::STATUS_LIVE)->toBe('live')
        ->and(Meal::STATUSES)->toContain('draft', 'live');
});

it('uses soft deletes', function () {
    expect(in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(Meal::class)))->toBeTrue();
});

it('has HasTranslatable trait', function () {
    expect(in_array('App\Traits\HasTranslatable', class_uses_recursive(Meal::class)))->toBeTrue();
});

it('has LogsActivityTrait', function () {
    expect(in_array('App\Traits\LogsActivityTrait', class_uses_recursive(Meal::class)))->toBeTrue();
});

it('belongs to a tenant', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    expect($meal->tenant)->toBeInstanceOf(Tenant::class)
        ->and($meal->tenant->id)->toBe($tenant->id);
});

it('has many components relationship', function () {
    $meal = new Meal;
    expect($meal->components())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

it('can check if meal is draft', function () {
    $meal = Meal::factory()->make(['status' => Meal::STATUS_DRAFT]);
    expect($meal->isDraft())->toBeTrue()
        ->and($meal->isLive())->toBeFalse();
});

it('can check if meal is live', function () {
    $meal = Meal::factory()->make(['status' => Meal::STATUS_LIVE]);
    expect($meal->isLive())->toBeTrue()
        ->and($meal->isDraft())->toBeFalse();
});

it('scopes to draft meals', function () {
    $tenant = Tenant::factory()->create();
    Meal::factory()->create(['tenant_id' => $tenant->id, 'status' => Meal::STATUS_DRAFT, 'name_en' => 'Draft Meal', 'name_fr' => 'Plat Brouillon']);
    Meal::factory()->create(['tenant_id' => $tenant->id, 'status' => Meal::STATUS_LIVE, 'name_en' => 'Live Meal', 'name_fr' => 'Plat En Ligne']);

    expect(Meal::draft()->count())->toBe(1);
});

it('scopes to live meals', function () {
    $tenant = Tenant::factory()->create();
    Meal::factory()->create(['tenant_id' => $tenant->id, 'status' => Meal::STATUS_DRAFT, 'name_en' => 'Draft Scope', 'name_fr' => 'Brouillon Scope']);
    Meal::factory()->create(['tenant_id' => $tenant->id, 'status' => Meal::STATUS_LIVE, 'name_en' => 'Live Scope', 'name_fr' => 'En Ligne Scope']);

    expect(Meal::live()->count())->toBe(1);
});

it('scopes to available meals', function () {
    $tenant = Tenant::factory()->create();
    Meal::factory()->create(['tenant_id' => $tenant->id, 'is_available' => true, 'name_en' => 'Available Meal', 'name_fr' => 'Plat Disponible']);
    Meal::factory()->create(['tenant_id' => $tenant->id, 'is_available' => false, 'name_en' => 'Unavailable Meal', 'name_fr' => 'Plat Indisponible']);

    expect(Meal::available()->count())->toBe(1);
});

it('scopes to meals for a specific tenant', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    Meal::factory()->create(['tenant_id' => $tenant1->id, 'name_en' => 'Tenant1 Meal', 'name_fr' => 'Plat Tenant1']);
    Meal::factory()->create(['tenant_id' => $tenant2->id, 'name_en' => 'Tenant2 Meal', 'name_fr' => 'Plat Tenant2']);

    expect(Meal::forTenant($tenant1->id)->count())->toBe(1)
        ->and(Meal::forTenant($tenant2->id)->count())->toBe(1);
});

it('calculates next position for tenant', function () {
    $tenant = Tenant::factory()->create();

    expect(Meal::nextPositionForTenant($tenant->id))->toBe(1);

    Meal::factory()->create(['tenant_id' => $tenant->id, 'position' => 5]);

    expect(Meal::nextPositionForTenant($tenant->id))->toBe(6);
});

// ── MealFactory Tests ──

it('creates a meal with factory defaults', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    expect($meal)->toBeInstanceOf(Meal::class)
        ->and($meal->name_en)->not->toBeEmpty()
        ->and($meal->name_fr)->not->toBeEmpty()
        ->and($meal->description_en)->not->toBeEmpty()
        ->and($meal->description_fr)->not->toBeEmpty()
        ->and($meal->status)->toBe(Meal::STATUS_DRAFT)
        ->and($meal->is_available)->toBeTrue()
        ->and($meal->estimated_prep_time)->toBeNull()
        ->and($meal->position)->toBe(0);
});

it('has a live factory state', function () {
    $meal = Meal::factory()->live()->make();
    expect($meal->status)->toBe(Meal::STATUS_LIVE);
});

it('has a draft factory state', function () {
    $meal = Meal::factory()->draft()->make();
    expect($meal->status)->toBe(Meal::STATUS_DRAFT);
});

it('has an unavailable factory state', function () {
    $meal = Meal::factory()->unavailable()->make();
    expect($meal->is_available)->toBeFalse();
});

it('has a positioned factory state', function () {
    $meal = Meal::factory()->positioned(5)->make();
    expect($meal->position)->toBe(5);
});

it('has a prep time factory state', function () {
    $meal = Meal::factory()->withPrepTime(30)->make();
    expect($meal->estimated_prep_time)->toBe(30);
});

// ── MealService Tests ──

it('creates a meal successfully', function () {
    $tenant = Tenant::factory()->create();
    $service = app(MealService::class);

    $result = $service->createMeal($tenant, [
        'name_en' => 'Jollof Rice',
        'name_fr' => 'Riz Jollof',
        'description_en' => 'Spicy tomato rice with vegetables',
        'description_fr' => 'Riz tomate epice avec des legumes',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['meal'])->toBeInstanceOf(Meal::class)
        ->and($result['meal']->name_en)->toBe('Jollof Rice')
        ->and($result['meal']->name_fr)->toBe('Riz Jollof')
        ->and($result['meal']->description_en)->toBe('Spicy tomato rice with vegetables')
        ->and($result['meal']->description_fr)->toBe('Riz tomate epice avec des legumes')
        ->and($result['meal']->status)->toBe(Meal::STATUS_DRAFT)
        ->and($result['meal']->is_available)->toBeTrue()
        ->and($result['meal']->tenant_id)->toBe($tenant->id);
});

it('BR-190: creates meal with draft status by default', function () {
    $tenant = Tenant::factory()->create();
    $service = app(MealService::class);

    $result = $service->createMeal($tenant, [
        'name_en' => 'Ndole',
        'name_fr' => 'Ndole',
        'description_en' => 'Traditional dish',
        'description_fr' => 'Plat traditionnel',
    ]);

    expect($result['meal']->status)->toBe(Meal::STATUS_DRAFT);
});

it('BR-191: creates meal with available status by default', function () {
    $tenant = Tenant::factory()->create();
    $service = app(MealService::class);

    $result = $service->createMeal($tenant, [
        'name_en' => 'Pepper Soup',
        'name_fr' => 'Soupe Poivre',
        'description_en' => 'Spicy broth',
        'description_fr' => 'Bouillon epice',
    ]);

    expect($result['meal']->is_available)->toBeTrue();
});

it('BR-189: rejects duplicate English meal name within tenant', function () {
    $tenant = Tenant::factory()->create();
    $service = app(MealService::class);

    $service->createMeal($tenant, [
        'name_en' => 'Jollof Rice',
        'name_fr' => 'Riz Jollof',
        'description_en' => 'Original',
        'description_fr' => 'Original',
    ]);

    $result = $service->createMeal($tenant, [
        'name_en' => 'Jollof Rice',
        'name_fr' => 'Riz Jollof v2',
        'description_en' => 'Duplicate',
        'description_fr' => 'Duplicat',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['field'])->toBe('name_en')
        ->and($result['error'])->toBe(__('A meal with this name already exists.'));
});

it('BR-189: rejects duplicate French meal name within tenant', function () {
    $tenant = Tenant::factory()->create();
    $service = app(MealService::class);

    $service->createMeal($tenant, [
        'name_en' => 'Jollof Rice',
        'name_fr' => 'Riz Jollof',
        'description_en' => 'Original',
        'description_fr' => 'Original',
    ]);

    $result = $service->createMeal($tenant, [
        'name_en' => 'Jollof Rice v2',
        'name_fr' => 'Riz Jollof',
        'description_en' => 'Duplicate',
        'description_fr' => 'Duplicat',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['field'])->toBe('name_fr');
});

it('BR-189: name uniqueness is case-insensitive', function () {
    $tenant = Tenant::factory()->create();
    $service = app(MealService::class);

    $service->createMeal($tenant, [
        'name_en' => 'Jollof Rice',
        'name_fr' => 'Riz Jollof',
        'description_en' => 'Original',
        'description_fr' => 'Original',
    ]);

    $result = $service->createMeal($tenant, [
        'name_en' => 'jollof rice',
        'name_fr' => 'Riz Jollof Different',
        'description_en' => 'Case-insensitive test',
        'description_fr' => 'Test insensible a la casse',
    ]);

    expect($result['success'])->toBeFalse();
});

it('BR-193: allows same meal name in different tenants', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $service = app(MealService::class);

    $result1 = $service->createMeal($tenant1, [
        'name_en' => 'Jollof Rice',
        'name_fr' => 'Riz Jollof',
        'description_en' => 'First tenant',
        'description_fr' => 'Premier locataire',
    ]);

    $result2 = $service->createMeal($tenant2, [
        'name_en' => 'Jollof Rice',
        'name_fr' => 'Riz Jollof',
        'description_en' => 'Second tenant',
        'description_fr' => 'Deuxieme locataire',
    ]);

    expect($result1['success'])->toBeTrue()
        ->and($result2['success'])->toBeTrue();
});

it('trims whitespace from meal names and descriptions', function () {
    $tenant = Tenant::factory()->create();
    $service = app(MealService::class);

    $result = $service->createMeal($tenant, [
        'name_en' => '  Jollof Rice  ',
        'name_fr' => '  Riz Jollof  ',
        'description_en' => '  Spicy rice  ',
        'description_fr' => '  Riz epice  ',
    ]);

    expect($result['meal']->name_en)->toBe('Jollof Rice')
        ->and($result['meal']->name_fr)->toBe('Riz Jollof')
        ->and($result['meal']->description_en)->toBe('Spicy rice')
        ->and($result['meal']->description_fr)->toBe('Riz epice');
});

it('strips HTML tags from descriptions for XSS prevention', function () {
    $tenant = Tenant::factory()->create();
    $service = app(MealService::class);

    $result = $service->createMeal($tenant, [
        'name_en' => 'Test Meal',
        'name_fr' => 'Plat Test',
        'description_en' => '<script>alert("xss")</script>Spicy rice',
        'description_fr' => '<b>Bold</b> description',
    ]);

    expect($result['meal']->description_en)->toBe('alert("xss")Spicy rice')
        ->and($result['meal']->description_fr)->toBe('Bold description');
});

it('assigns auto-incrementing position for new meals', function () {
    $tenant = Tenant::factory()->create();
    $service = app(MealService::class);

    $result1 = $service->createMeal($tenant, [
        'name_en' => 'First Meal',
        'name_fr' => 'Premier Plat',
        'description_en' => 'First',
        'description_fr' => 'Premier',
    ]);

    $result2 = $service->createMeal($tenant, [
        'name_en' => 'Second Meal',
        'name_fr' => 'Deuxieme Plat',
        'description_en' => 'Second',
        'description_fr' => 'Deuxieme',
    ]);

    expect($result1['meal']->position)->toBe(1)
        ->and($result2['meal']->position)->toBe(2);
});

it('accepts meal name with special characters', function () {
    $tenant = Tenant::factory()->create();
    $service = app(MealService::class);

    $result = $service->createMeal($tenant, [
        'name_en' => "Chef's Special Ndole",
        'name_fr' => 'Special du Chef Ndole',
        'description_en' => 'With accents: cafe, naive, resume',
        'description_fr' => 'Avec accents: cafe, naive, resume',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['meal']->name_en)->toBe("Chef's Special Ndole");
});

it('accepts name at exactly 150 characters', function () {
    $tenant = Tenant::factory()->create();
    $service = app(MealService::class);

    $longName = str_repeat('A', 150);
    $result = $service->createMeal($tenant, [
        'name_en' => $longName,
        'name_fr' => 'Short name',
        'description_en' => 'Description',
        'description_fr' => 'Description',
    ]);

    expect($result['success'])->toBeTrue()
        ->and(strlen($result['meal']->name_en))->toBe(150);
});

// ── StoreMealRequest Tests ──

it('StoreMealRequest authorizes users with can-manage-meals permission', function () {
    $this->seedRolesAndPermissions();

    $request = new \App\Http\Requests\Cook\StoreMealRequest;

    // Cook has can-manage-meals permission
    $cook = $this->createUserWithRole('cook');
    $request->setUserResolver(fn () => $cook);
    expect($request->authorize())->toBeTrue();

    // Client does not have can-manage-meals permission
    $client = $this->createUserWithRole('client');
    $request->setUserResolver(fn () => $client);
    expect($request->authorize())->toBeFalse();
});

it('StoreMealRequest validates all required fields', function () {
    $request = new \App\Http\Requests\Cook\StoreMealRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('name_en')
        ->toHaveKey('name_fr')
        ->toHaveKey('description_en')
        ->toHaveKey('description_fr');

    expect($rules['name_en'])->toContain('required')
        ->toContain('string')
        ->toContain('max:150');

    expect($rules['name_fr'])->toContain('required')
        ->toContain('string')
        ->toContain('max:150');

    expect($rules['description_en'])->toContain('required')
        ->toContain('string')
        ->toContain('max:2000');

    expect($rules['description_fr'])->toContain('required')
        ->toContain('string')
        ->toContain('max:2000');
});

it('StoreMealRequest has localized validation messages', function () {
    $request = new \App\Http\Requests\Cook\StoreMealRequest;
    $messages = $request->messages();

    expect($messages)->toHaveKey('name_en.required')
        ->toHaveKey('name_fr.required')
        ->toHaveKey('description_en.required')
        ->toHaveKey('description_fr.required')
        ->toHaveKey('name_en.max')
        ->toHaveKey('name_fr.max')
        ->toHaveKey('description_en.max')
        ->toHaveKey('description_fr.max');
});

// ── MealService uniqueness check Tests ──

it('checkNameUniqueness can exclude a specific meal ID', function () {
    $tenant = Tenant::factory()->create();
    $service = app(MealService::class);

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Existing Meal',
        'name_fr' => 'Plat Existant',
    ]);

    // Without excludeId, should fail
    $result1 = $service->checkNameUniqueness($tenant, 'Existing Meal', 'Plat Different');
    expect($result1['unique'])->toBeFalse();

    // With excludeId, should pass (used for editing)
    $result2 = $service->checkNameUniqueness($tenant, 'Existing Meal', 'Plat Different', $meal->id);
    expect($result2['unique'])->toBeTrue();
});
