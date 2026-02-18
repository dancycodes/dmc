<?php

use App\Http\Requests\Cook\UpdateMealRequest;
use App\Models\Meal;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MealService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-110: Meal Edit — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for MealService::updateMeal(), UpdateMealRequest validation,
| and the MealController update endpoint.
|
| Verifies business rules BR-210 through BR-217.
|
*/

// ── MealService::updateMeal() Tests ──

it('updates a meal name successfully', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Old Name EN',
        'name_fr' => 'Old Name FR',
        'description_en' => 'Old description EN',
        'description_fr' => 'Old description FR',
    ]);

    $service = app(MealService::class);
    $result = $service->updateMeal($meal, [
        'name_en' => 'New Name EN',
        'name_fr' => 'New Name FR',
        'description_en' => 'Old description EN',
        'description_fr' => 'Old description FR',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['meal']->name_en)->toBe('New Name EN')
        ->and($result['meal']->name_fr)->toBe('New Name FR');
});

it('updates a meal description successfully', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Meal Name EN',
        'name_fr' => 'Meal Name FR',
        'description_en' => 'Old desc EN',
        'description_fr' => 'Old desc FR',
    ]);

    $service = app(MealService::class);
    $result = $service->updateMeal($meal, [
        'name_en' => 'Meal Name EN',
        'name_fr' => 'Meal Name FR',
        'description_en' => 'Updated description with more details',
        'description_fr' => 'Description mise a jour avec plus de details',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['meal']->description_en)->toBe('Updated description with more details')
        ->and($result['meal']->description_fr)->toBe('Description mise a jour avec plus de details');
});

it('tracks changes when updating a meal', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Old EN',
        'name_fr' => 'Old FR',
        'description_en' => 'Old Desc',
        'description_fr' => 'Ancien Desc',
    ]);

    $service = app(MealService::class);
    $result = $service->updateMeal($meal, [
        'name_en' => 'New EN',
        'name_fr' => 'Old FR',
        'description_en' => 'Old Desc',
        'description_fr' => 'Ancien Desc',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['changes'])->toHaveKey('name_en')
        ->and($result['changes']['name_en']['old'])->toBe('Old EN')
        ->and($result['changes']['name_en']['new'])->toBe('New EN')
        ->and($result['changes'])->not->toHaveKey('name_fr')
        ->and($result['changes'])->not->toHaveKey('description_en')
        ->and($result['changes'])->not->toHaveKey('description_fr');
});

it('returns empty changes when no values changed', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Same EN',
        'name_fr' => 'Same FR',
        'description_en' => 'Same Desc',
        'description_fr' => 'Meme Desc',
    ]);

    $service = app(MealService::class);
    $result = $service->updateMeal($meal, [
        'name_en' => 'Same EN',
        'name_fr' => 'Same FR',
        'description_en' => 'Same Desc',
        'description_fr' => 'Meme Desc',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['changes'])->toBeEmpty();
});

it('rejects duplicate English name within same tenant', function () {
    $tenant = Tenant::factory()->create();
    Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Existing Meal',
        'name_fr' => 'Repas Existant',
    ]);
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Other Meal',
        'name_fr' => 'Autre Repas',
    ]);

    $service = app(MealService::class);
    $result = $service->updateMeal($meal, [
        'name_en' => 'Existing Meal',
        'name_fr' => 'Unique FR Name',
        'description_en' => 'Desc EN',
        'description_fr' => 'Desc FR',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['field'])->toBe('name_en')
        ->and($result['error'])->not->toBeEmpty();
});

it('rejects duplicate French name within same tenant', function () {
    $tenant = Tenant::factory()->create();
    Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Existing EN',
        'name_fr' => 'Repas Existant',
    ]);
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Other EN',
        'name_fr' => 'Autre Repas',
    ]);

    $service = app(MealService::class);
    $result = $service->updateMeal($meal, [
        'name_en' => 'Unique EN Name',
        'name_fr' => 'Repas Existant',
        'description_en' => 'Desc EN',
        'description_fr' => 'Desc FR',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['field'])->toBe('name_fr');
});

it('allows same name when updating own meal (BR-212 excludeId)', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'My Meal',
        'name_fr' => 'Mon Repas',
        'description_en' => 'Original description',
        'description_fr' => 'Description originale',
    ]);

    $service = app(MealService::class);
    $result = $service->updateMeal($meal, [
        'name_en' => 'My Meal',
        'name_fr' => 'Mon Repas',
        'description_en' => 'Updated description',
        'description_fr' => 'Description mise a jour',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['meal']->description_en)->toBe('Updated description');
});

it('performs case-insensitive uniqueness check (BR-212)', function () {
    $tenant = Tenant::factory()->create();
    Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Jollof Rice',
        'name_fr' => 'Riz Jollof',
    ]);
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Other Meal',
        'name_fr' => 'Autre Repas',
    ]);

    $service = app(MealService::class);
    $result = $service->updateMeal($meal, [
        'name_en' => 'JOLLOF RICE',
        'name_fr' => 'Unique FR',
        'description_en' => 'Desc',
        'description_fr' => 'Desc',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['field'])->toBe('name_en');
});

it('allows duplicate name across different tenants', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    Meal::factory()->create([
        'tenant_id' => $tenant1->id,
        'name_en' => 'Jollof Rice',
        'name_fr' => 'Riz Jollof',
    ]);
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant2->id,
        'name_en' => 'Other Meal',
        'name_fr' => 'Autre Repas',
    ]);

    $service = app(MealService::class);
    $result = $service->updateMeal($meal, [
        'name_en' => 'Jollof Rice',
        'name_fr' => 'Riz Jollof',
        'description_en' => 'Desc',
        'description_fr' => 'Desc',
    ]);

    expect($result['success'])->toBeTrue();
});

it('strips HTML from description (XSS prevention)', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $service = app(MealService::class);
    $result = $service->updateMeal($meal, [
        'name_en' => 'Safe Meal',
        'name_fr' => 'Repas Sure',
        'description_en' => '<script>alert("xss")</script>Safe text',
        'description_fr' => '<b>Bold</b> description',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['meal']->description_en)->toBe('alert("xss")Safe text')
        ->and($result['meal']->description_fr)->toBe('Bold description');
});

it('trims whitespace from input', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Trimmed',
        'name_fr' => 'Coupe',
        'description_en' => 'Desc',
        'description_fr' => 'Desc',
    ]);

    $service = app(MealService::class);
    $result = $service->updateMeal($meal, [
        'name_en' => '  New Name  ',
        'name_fr' => '  Nouveau Nom  ',
        'description_en' => '  Updated desc  ',
        'description_fr' => '  Desc mise a jour  ',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['meal']->name_en)->toBe('New Name')
        ->and($result['meal']->name_fr)->toBe('Nouveau Nom')
        ->and($result['meal']->description_en)->toBe('Updated desc')
        ->and($result['meal']->description_fr)->toBe('Desc mise a jour');
});

it('does not change meal status when editing (BR-217)', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->live()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Live Meal',
        'name_fr' => 'Repas En Ligne',
        'description_en' => 'Desc EN',
        'description_fr' => 'Desc FR',
    ]);

    $service = app(MealService::class);
    $result = $service->updateMeal($meal, [
        'name_en' => 'Updated Name',
        'name_fr' => 'Nom Mis A Jour',
        'description_en' => 'Desc EN',
        'description_fr' => 'Desc FR',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['meal']->status)->toBe(Meal::STATUS_LIVE)
        ->and($result['meal']->is_available)->toBeTrue();
});

it('handles special characters in name correctly', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $service = app(MealService::class);
    $result = $service->updateMeal($meal, [
        'name_en' => "Poulet DG — Chef's Special",
        'name_fr' => 'Poulet DG — Special du Chef',
        'description_en' => 'With accents: cafe, naive, resume',
        'description_fr' => 'Avec accents: cafe, naive, resume',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['meal']->name_en)->toBe("Poulet DG — Chef's Special")
        ->and($result['meal']->name_fr)->toBe('Poulet DG — Special du Chef');
});

// ── UpdateMealRequest Tests ──

it('UpdateMealRequest has correct validation rules', function () {
    $request = new UpdateMealRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('name_en')
        ->and($rules)->toHaveKey('name_fr')
        ->and($rules)->toHaveKey('description_en')
        ->and($rules)->toHaveKey('description_fr')
        ->and($rules['name_en'])->toContain('required')
        ->and($rules['name_en'])->toContain('max:150')
        ->and($rules['name_fr'])->toContain('required')
        ->and($rules['name_fr'])->toContain('max:150')
        ->and($rules['description_en'])->toContain('required')
        ->and($rules['description_en'])->toContain('max:2000')
        ->and($rules['description_fr'])->toContain('required')
        ->and($rules['description_fr'])->toContain('max:2000');
});

it('UpdateMealRequest requires can-manage-meals permission', function () {
    $request = new UpdateMealRequest;

    // Without user
    expect($request->authorize())->toBeFalse();
});

it('UpdateMealRequest has custom error messages', function () {
    $request = new UpdateMealRequest;
    $messages = $request->messages();

    expect($messages)->toHaveKey('name_en.required')
        ->and($messages)->toHaveKey('name_fr.required')
        ->and($messages)->toHaveKey('description_en.required')
        ->and($messages)->toHaveKey('description_fr.required')
        ->and($messages)->toHaveKey('name_en.max')
        ->and($messages)->toHaveKey('description_en.max');
});

// ── Controller Tests ──

it('rejects unauthorized access to update endpoint (BR-215)', function () {
    $user = test()->createUserWithRole('client');
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($user)
        ->put("https://{$tenant->slug}.{$mainDomain}/dashboard/meals/{$meal->id}", [
            'name_en' => 'New Name',
            'name_fr' => 'Nouveau Nom',
            'description_en' => 'New Desc',
            'description_fr' => 'Nouvelle Desc',
        ])
        ->assertForbidden();
});

it('allows cook to update meal (BR-215)', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Original EN',
        'name_fr' => 'Original FR',
        'description_en' => 'Desc EN',
        'description_fr' => 'Desc FR',
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($cook)
        ->put("https://{$tenant->slug}.{$mainDomain}/dashboard/meals/{$meal->id}", [
            'name_en' => 'Updated EN',
            'name_fr' => 'Updated FR',
            'description_en' => 'Desc EN',
            'description_fr' => 'Desc FR',
        ])
        ->assertRedirect();

    expect($meal->fresh()->name_en)->toBe('Updated EN')
        ->and($meal->fresh()->name_fr)->toBe('Updated FR');
});

it('logs activity when meal is updated (BR-216)', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Before EN',
        'name_fr' => 'Before FR',
        'description_en' => 'Before Desc',
        'description_fr' => 'Avant Desc',
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($cook)
        ->put("https://{$tenant->slug}.{$mainDomain}/dashboard/meals/{$meal->id}", [
            'name_en' => 'After EN',
            'name_fr' => 'Before FR',
            'description_en' => 'Before Desc',
            'description_fr' => 'Avant Desc',
        ]);

    $activity = Activity::where('log_name', 'meals')
        ->where('description', 'Meal updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['action'])->toBe('meal_updated')
        ->and($activity->properties['old']['name_en'])->toBe('Before EN')
        ->and($activity->properties['new']['name_en'])->toBe('After EN')
        ->and($activity->properties['old'])->not->toHaveKey('name_fr')
        ->and($activity->properties['new'])->not->toHaveKey('name_fr');
});

it('does not log activity when no changes are made', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Same EN',
        'name_fr' => 'Same FR',
        'description_en' => 'Same Desc',
        'description_fr' => 'Meme Desc',
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($cook)
        ->put("https://{$tenant->slug}.{$mainDomain}/dashboard/meals/{$meal->id}", [
            'name_en' => 'Same EN',
            'name_fr' => 'Same FR',
            'description_en' => 'Same Desc',
            'description_fr' => 'Meme Desc',
        ]);

    $activity = Activity::where('log_name', 'meals')
        ->where('description', 'Meal updated')
        ->count();

    expect($activity)->toBe(0);
});

it('returns validation error for duplicate name via HTTP', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();

    Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Taken Name',
        'name_fr' => 'Nom Pris',
    ]);

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'My Meal',
        'name_fr' => 'Mon Repas',
        'description_en' => 'Desc EN',
        'description_fr' => 'Desc FR',
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->actingAs($cook)
        ->put("https://{$tenant->slug}.{$mainDomain}/dashboard/meals/{$meal->id}", [
            'name_en' => 'Taken Name',
            'name_fr' => 'Unique FR',
            'description_en' => 'Desc EN',
            'description_fr' => 'Desc FR',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('name_en');
});

it('returns 404 for non-existent meal', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($cook)
        ->put("https://{$tenant->slug}.{$mainDomain}/dashboard/meals/99999", [
            'name_en' => 'Name',
            'name_fr' => 'Nom',
            'description_en' => 'Desc EN',
            'description_fr' => 'Desc FR',
        ])
        ->assertNotFound();
});

it('prevents accessing meals from other tenants', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant1 = Tenant::factory()->withCook($cook->id)->create();
    $tenant2 = Tenant::factory()->create();

    $otherMeal = Meal::factory()->create([
        'tenant_id' => $tenant2->id,
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($cook)
        ->put("https://{$tenant1->slug}.{$mainDomain}/dashboard/meals/{$otherMeal->id}", [
            'name_en' => 'Hijacked',
            'name_fr' => 'Pirate',
            'description_en' => 'Desc EN',
            'description_fr' => 'Desc FR',
        ])
        ->assertNotFound();
});

// ── Validation Edge Cases ──

it('rejects empty name_en', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($cook)
        ->put("https://{$tenant->slug}.{$mainDomain}/dashboard/meals/{$meal->id}", [
            'name_en' => '',
            'name_fr' => 'Nom FR',
            'description_en' => 'Desc EN',
            'description_fr' => 'Desc FR',
        ])
        ->assertSessionHasErrors('name_en');
});

it('rejects name exceeding 150 characters (BR-213)', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($cook)
        ->put("https://{$tenant->slug}.{$mainDomain}/dashboard/meals/{$meal->id}", [
            'name_en' => str_repeat('a', 151),
            'name_fr' => 'Valid FR',
            'description_en' => 'Desc EN',
            'description_fr' => 'Desc FR',
        ])
        ->assertSessionHasErrors('name_en');
});

it('rejects description exceeding 2000 characters (BR-214)', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $this->actingAs($cook)
        ->put("https://{$tenant->slug}.{$mainDomain}/dashboard/meals/{$meal->id}", [
            'name_en' => 'Valid EN',
            'name_fr' => 'Valid FR',
            'description_en' => str_repeat('x', 2001),
            'description_fr' => 'Desc FR',
        ])
        ->assertSessionHasErrors('description_en');
});

it('accepts name at exactly 150 characters', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    $service = app(MealService::class);
    $result = $service->updateMeal($meal, [
        'name_en' => str_repeat('a', 150),
        'name_fr' => str_repeat('b', 150),
        'description_en' => 'Desc',
        'description_fr' => 'Desc',
    ]);

    expect($result['success'])->toBeTrue()
        ->and(mb_strlen($result['meal']->name_en))->toBe(150);
});

it('accepts description at exactly 2000 characters', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    $service = app(MealService::class);
    $result = $service->updateMeal($meal, [
        'name_en' => 'Boundary Test',
        'name_fr' => 'Test Limite',
        'description_en' => str_repeat('x', 2000),
        'description_fr' => str_repeat('y', 2000),
    ]);

    expect($result['success'])->toBeTrue()
        ->and(mb_strlen($result['meal']->description_en))->toBe(2000);
});

// ── Route Tests ──

it('has PUT route for meal update', function () {
    $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes());
    $mealUpdateRoute = $routes->first(fn ($r) => $r->getName() === 'cook.meals.update');

    expect($mealUpdateRoute)->not->toBeNull()
        ->and($mealUpdateRoute->methods())->toContain('PUT');
});
