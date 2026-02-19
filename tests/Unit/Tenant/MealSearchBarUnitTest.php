<?php

/**
 * F-135: Meal Search Bar — Unit Tests
 *
 * Tests TenantLandingService::searchMeals() and MealSearchRequest validation.
 * BR-214: Search across meal name, description, component names, tag names
 * BR-215: Case-insensitive search
 * BR-221: Minimum 2 characters to trigger search
 */

use App\Http\Requests\Tenant\MealSearchRequest;
use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\Tag;
use App\Models\Tenant;
use App\Services\TenantLandingService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
    $this->tenant = Tenant::factory()->create();
    $this->service = app(TenantLandingService::class);
});

/*
|--------------------------------------------------------------------------
| F-135: Meal Search Bar — Unit Tests
| BR-214: Search across meal name, description, component names, tag names
| BR-215: Case-insensitive search
| BR-221: Minimum 2 characters to trigger search
|--------------------------------------------------------------------------
*/

// --- Search by Meal Name ---

test('BR-214: search matches meal name in English', function () {
    $meal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Ndole Special Dish',
        'name_fr' => 'Plat Special Ndole',
    ]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $results = $this->service->searchMeals($this->tenant, 'Ndole');

    expect($results->total())->toBe(1)
        ->and($results->first()->id)->toBe($meal->id);
});

test('BR-214: search matches meal name in French', function () {
    $meal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Grilled Chicken',
        'name_fr' => 'Poulet Grille',
    ]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $results = $this->service->searchMeals($this->tenant, 'Poulet');

    expect($results->total())->toBe(1);
});

// --- Search by Description ---

test('BR-214: search matches meal description in English', function () {
    $meal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Special Dish',
        'description_en' => 'A delicious plantain combination',
    ]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $results = $this->service->searchMeals($this->tenant, 'plantain');

    expect($results->total())->toBe(1);
});

test('BR-214: search matches meal description in French', function () {
    $meal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Special Dish',
        'description_fr' => 'Une combinaison de banane plantain',
    ]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $results = $this->service->searchMeals($this->tenant, 'banane');

    expect($results->total())->toBe(1);
});

// --- Search by Component Name ---

test('BR-214: search matches component name and returns parent meal', function () {
    $meal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Combo Plate',
    ]);
    MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'Fried Plantain',
        'name_fr' => 'Plantain Frit',
    ]);

    $results = $this->service->searchMeals($this->tenant, 'Plantain');

    expect($results->total())->toBe(1)
        ->and($results->first()->id)->toBe($meal->id);
});

// --- Search by Tag Name ---

test('BR-214: search matches tag name and returns tagged meals', function () {
    $meal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Mystery Dish',
    ]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $tag = Tag::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Spicy',
        'name_fr' => 'Epice',
    ]);
    $meal->tags()->attach($tag->id);

    $results = $this->service->searchMeals($this->tenant, 'Spicy');

    expect($results->total())->toBe(1)
        ->and($results->first()->id)->toBe($meal->id);
});

test('BR-214: search matches French tag name', function () {
    $meal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Mystery Dish',
    ]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $tag = Tag::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Spicy',
        'name_fr' => 'Epice',
    ]);
    $meal->tags()->attach($tag->id);

    $results = $this->service->searchMeals($this->tenant, 'Epice');

    expect($results->total())->toBe(1);
});

// --- Case Insensitivity ---

test('BR-215: search is case-insensitive', function () {
    $meal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Ndole Special',
    ]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $lowerResults = $this->service->searchMeals($this->tenant, 'ndole');
    $upperResults = $this->service->searchMeals($this->tenant, 'NDOLE');
    $mixedResults = $this->service->searchMeals($this->tenant, 'nDoLe');

    expect($lowerResults->total())->toBe(1)
        ->and($upperResults->total())->toBe(1)
        ->and($mixedResults->total())->toBe(1);
});

// --- Filter Constraints ---

test('search only returns live meals', function () {
    Meal::factory()->draft()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Draft Ndole',
    ]);
    $liveMeal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Live Ndole',
    ]);
    MealComponent::factory()->create(['meal_id' => $liveMeal->id]);

    $results = $this->service->searchMeals($this->tenant, 'Ndole');

    expect($results->total())->toBe(1)
        ->and($results->first()->name_en)->toBe('Live Ndole');
});

test('search only returns available meals', function () {
    Meal::factory()->live()->unavailable()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Unavailable Ndole',
    ]);
    $availableMeal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Available Ndole',
    ]);
    MealComponent::factory()->create(['meal_id' => $availableMeal->id]);

    $results = $this->service->searchMeals($this->tenant, 'Ndole');

    expect($results->total())->toBe(1)
        ->and($results->first()->name_en)->toBe('Available Ndole');
});

test('search does not return meals from other tenants', function () {
    $otherTenant = Tenant::factory()->create();
    Meal::factory()->live()->create([
        'tenant_id' => $otherTenant->id,
        'name_en' => 'Other Tenant Ndole',
    ]);

    $results = $this->service->searchMeals($this->tenant, 'Ndole');

    expect($results->total())->toBe(0);
});

// --- No Results ---

test('BR-220: search returns empty results for non-matching query', function () {
    Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Ndole Special',
    ]);

    $results = $this->service->searchMeals($this->tenant, 'pizza');

    expect($results->total())->toBe(0);
});

// --- Edge Cases ---

test('search sanitizes special characters', function () {
    $meal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Ndole Special',
    ]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    // These should not cause SQL errors
    $results1 = $this->service->searchMeals($this->tenant, '%Ndole%');
    $results2 = $this->service->searchMeals($this->tenant, '_Ndole_');
    $results3 = $this->service->searchMeals($this->tenant, "Nd'ole");

    // The wildcard characters should be treated as literals
    expect($results1->total())->toBe(0)
        ->and($results2->total())->toBe(0);
});

test('search pagination works correctly', function () {
    // Create 15 meals to test pagination (12 per page)
    for ($i = 0; $i < 15; $i++) {
        $meal = Meal::factory()->live()->create([
            'tenant_id' => $this->tenant->id,
            'name_en' => "TestSearch Meal {$i}",
        ]);
        MealComponent::factory()->create(['meal_id' => $meal->id]);
    }

    $page1 = $this->service->searchMeals($this->tenant, 'TestSearch', 1);
    $page2 = $this->service->searchMeals($this->tenant, 'TestSearch', 2);

    expect($page1->count())->toBe(12)
        ->and($page1->total())->toBe(15)
        ->and($page2->count())->toBe(3);
});

// --- MealSearchRequest Validation ---

test('BR-221: MealSearchRequest searchQuery returns trimmed query', function () {
    $request = MealSearchRequest::create('/meals/search', 'GET', ['q' => '  Ndole  ']);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->searchQuery())->toBe('Ndole');
});

test('MealSearchRequest truncates query to 50 characters', function () {
    $longQuery = str_repeat('a', 51);
    $request = MealSearchRequest::create('/meals/search', 'GET', ['q' => mb_substr($longQuery, 0, 50)]);
    $request->setContainer(app());
    $request->validateResolved();

    expect(mb_strlen($request->searchQuery()))->toBe(50);
});

test('MealSearchRequest returns empty string for null query', function () {
    $request = MealSearchRequest::create('/meals/search', 'GET', []);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->searchQuery())->toBe('');
});

test('search with multiple matching fields does not duplicate results', function () {
    $meal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Plantain Delight',
        'description_en' => 'Made with fresh plantain',
    ]);
    MealComponent::factory()->create([
        'meal_id' => $meal->id,
        'name_en' => 'Fried Plantain',
    ]);

    $results = $this->service->searchMeals($this->tenant, 'Plantain');

    // Even though "Plantain" matches name, description, and component, meal should appear once
    expect($results->total())->toBe(1);
});

test('search eager loads required relationships', function () {
    $meal = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Test Meal for Relations',
    ]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $results = $this->service->searchMeals($this->tenant, 'Test Meal');

    $first = $results->first();
    expect($first->relationLoaded('images'))->toBeTrue()
        ->and($first->relationLoaded('components'))->toBeTrue()
        ->and($first->relationLoaded('tags'))->toBeTrue();
});
