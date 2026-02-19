<?php

/**
 * F-136: Meal Filters — Unit Tests
 *
 * Tests TenantLandingService::filterMeals(), getFilterData(), and MealSearchRequest
 * filter validation.
 *
 * BR-223: Tag filter multi-select with OR logic
 * BR-224: Availability filter: "all" or "available_now"
 * BR-226: Price range filter on starting price (min component price)
 * BR-227: Price range bounds from highest/lowest meal starting prices
 * BR-228: AND logic between filter types
 * BR-229: Active filter count badge
 * BR-230: Clear filters resets all
 * BR-232: Combinable with search
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
| F-136: getFilterData() Tests
|--------------------------------------------------------------------------
*/

test('BR-223: getFilterData returns tags assigned to live meals', function () {
    $tag1 = Tag::factory()->create(['tenant_id' => $this->tenant->id, 'name_en' => 'Spicy']);
    $tag2 = Tag::factory()->create(['tenant_id' => $this->tenant->id, 'name_en' => 'Traditional']);
    $unusedTag = Tag::factory()->create(['tenant_id' => $this->tenant->id, 'name_en' => 'Unused']);

    $meal = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 500]);
    $meal->tags()->attach([$tag1->id, $tag2->id]);

    $filterData = $this->service->getFilterData($this->tenant);

    expect($filterData['hasTags'])->toBeTrue()
        ->and($filterData['tags'])->toHaveCount(2)
        ->and(collect($filterData['tags'])->pluck('name')->toArray())->toContain('Spicy', 'Traditional')
        ->and(collect($filterData['tags'])->pluck('name')->toArray())->not->toContain('Unused');
});

test('getFilterData returns no tags when cook has none assigned to live meals', function () {
    $filterData = $this->service->getFilterData($this->tenant);

    expect($filterData['hasTags'])->toBeFalse()
        ->and($filterData['tags'])->toBeEmpty();
});

test('BR-227: getFilterData returns price range from available component prices', function () {
    $meal1 = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal1->id, 'price' => 500]);
    MealComponent::factory()->create(['meal_id' => $meal1->id, 'price' => 2000]);

    $meal2 = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal2->id, 'price' => 1000]);

    $filterData = $this->service->getFilterData($this->tenant);

    expect($filterData['priceRange']['min'])->toBe(500)
        ->and($filterData['priceRange']['max'])->toBe(2000)
        ->and($filterData['hasPriceRange'])->toBeTrue();
});

test('getFilterData hides price range when all meals have same price', function () {
    $meal = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 500]);

    $filterData = $this->service->getFilterData($this->tenant);

    expect($filterData['hasPriceRange'])->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| F-136: filterMeals() — Tag Filter Tests (BR-223)
|--------------------------------------------------------------------------
*/

test('BR-223: tag filter returns meals matching any selected tag (OR logic)', function () {
    $tag1 = Tag::factory()->create(['tenant_id' => $this->tenant->id, 'name_en' => 'Spicy']);
    $tag2 = Tag::factory()->create(['tenant_id' => $this->tenant->id, 'name_en' => 'Traditional']);

    $meal1 = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal1->id]);
    $meal1->tags()->attach($tag1->id);

    $meal2 = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal2->id]);
    $meal2->tags()->attach($tag2->id);

    $meal3 = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal3->id]);
    // No tags

    // Filter by single tag
    $results = $this->service->filterMeals($this->tenant, '', [$tag1->id]);
    expect($results->total())->toBe(1);

    // Filter by both tags — OR logic should return 2 meals
    $results = $this->service->filterMeals($this->tenant, '', [$tag1->id, $tag2->id]);
    expect($results->total())->toBe(2);
});

test('BR-223: tag filter with empty array returns all meals', function () {
    $meal = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $results = $this->service->filterMeals($this->tenant, '', []);

    expect($results->total())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| F-136: filterMeals() — Price Range Tests (BR-226)
|--------------------------------------------------------------------------
*/

test('BR-226: price range filters by starting price (min component price)', function () {
    $meal1 = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal1->id, 'price' => 500]);

    $meal2 = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal2->id, 'price' => 2000]);

    $meal3 = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal3->id, 'price' => 3000]);

    // Filter: 500-2000
    $results = $this->service->filterMeals($this->tenant, '', [], 'all', 500, 2000);
    expect($results->total())->toBe(2);

    // Filter: 1000-3000
    $results = $this->service->filterMeals($this->tenant, '', [], 'all', 1000, 3000);
    expect($results->total())->toBe(2);

    // Filter: 2500+
    $results = $this->service->filterMeals($this->tenant, '', [], 'all', 2500, null);
    expect($results->total())->toBe(1);
});

test('BR-226: price range with no min returns all below max', function () {
    $meal1 = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal1->id, 'price' => 500]);

    $meal2 = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal2->id, 'price' => 3000]);

    $results = $this->service->filterMeals($this->tenant, '', [], 'all', null, 1000);
    expect($results->total())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| F-136: filterMeals() — Combined Filters Tests (BR-228)
|--------------------------------------------------------------------------
*/

test('BR-228: combined tag + price range filters use AND logic', function () {
    $tag = Tag::factory()->create(['tenant_id' => $this->tenant->id, 'name_en' => 'Spicy']);

    $meal1 = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal1->id, 'price' => 500]);
    $meal1->tags()->attach($tag->id);

    $meal2 = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal2->id, 'price' => 3000]);
    $meal2->tags()->attach($tag->id);

    // Tag Spicy + price 0-1000 = only meal1
    $results = $this->service->filterMeals($this->tenant, '', [$tag->id], 'all', 0, 1000);
    expect($results->total())->toBe(1)
        ->and($results->first()->id)->toBe($meal1->id);
});

test('BR-232: filters combine with search', function () {
    $tag = Tag::factory()->create(['tenant_id' => $this->tenant->id, 'name_en' => 'Traditional']);

    $meal1 = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Unique Zamba Plate',
        'name_fr' => 'Assiette Zamba Unique',
        'description_en' => 'A delicious Zamba dish',
        'description_fr' => 'Un delicieux plat Zamba',
    ]);
    MealComponent::factory()->create([
        'meal_id' => $meal1->id,
        'name_en' => 'Zamba Base',
        'name_fr' => 'Base Zamba',
    ]);
    $meal1->tags()->attach($tag->id);

    $meal2 = Meal::factory()->live()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Chicken Grill',
        'name_fr' => 'Poulet Grille',
        'description_en' => 'A grilled chicken meal',
        'description_fr' => 'Un plat de poulet grille',
    ]);
    MealComponent::factory()->create([
        'meal_id' => $meal2->id,
        'name_en' => 'Chicken Piece',
        'name_fr' => 'Morceau de Poulet',
    ]);
    $meal2->tags()->attach($tag->id);

    // Search "Zamba" + tag Traditional = only meal1
    $results = $this->service->filterMeals($this->tenant, 'Zamba', [$tag->id]);
    expect($results->total())->toBe(1)
        ->and($results->first()->id)->toBe($meal1->id);
});

test('BR-228: no meals match all combined filters shows zero results', function () {
    $tag = Tag::factory()->create(['tenant_id' => $this->tenant->id, 'name_en' => 'Vegetarian']);

    $meal = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id, 'price' => 500]);
    // No tags assigned

    // Filter by tag "Vegetarian" which no meal has
    $results = $this->service->filterMeals($this->tenant, '', [$tag->id]);
    expect($results->total())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| F-136: filterMeals() — Availability Filter Tests (BR-224/BR-225)
|--------------------------------------------------------------------------
*/

test('BR-224: availability "all" returns all live+available meals', function () {
    $meal = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    $results = $this->service->filterMeals($this->tenant, '', [], 'all');
    expect($results->total())->toBe(1);
});

test('BR-225: availability "available_now" with no active schedule returns zero', function () {
    $meal = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $meal->id]);

    // No schedules created — no active windows
    $results = $this->service->filterMeals($this->tenant, '', [], 'available_now');
    expect($results->total())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| F-136: MealSearchRequest Filter Validation Tests
|--------------------------------------------------------------------------
*/

test('BR-229: activeFilterCount counts tag selections individually', function () {
    $request = MealSearchRequest::create('/meals/search', 'GET', [
        'tags' => '1,2,3',
        'availability' => 'all',
    ]);

    $request->setContainer(app());
    $request->validateResolved();

    expect($request->activeFilterCount())->toBe(3)
        ->and($request->hasActiveFilters())->toBeTrue();
});

test('BR-229: activeFilterCount counts availability filter', function () {
    $request = MealSearchRequest::create('/meals/search', 'GET', [
        'availability' => 'available_now',
    ]);

    $request->setContainer(app());
    $request->validateResolved();

    expect($request->activeFilterCount())->toBe(1);
});

test('BR-229: activeFilterCount counts price range as one filter', function () {
    $request = MealSearchRequest::create('/meals/search', 'GET', [
        'price_min' => '500',
        'price_max' => '2000',
    ]);

    $request->setContainer(app());
    $request->validateResolved();

    expect($request->activeFilterCount())->toBe(1);
});

test('BR-230: no active filters returns zero count', function () {
    $request = MealSearchRequest::create('/meals/search', 'GET', [
        'availability' => 'all',
    ]);

    $request->setContainer(app());
    $request->validateResolved();

    expect($request->activeFilterCount())->toBe(0)
        ->and($request->hasActiveFilters())->toBeFalse();
});

test('tagIds parses comma-separated string to integer array', function () {
    $request = MealSearchRequest::create('/meals/search', 'GET', [
        'tags' => '1,5,10',
    ]);

    $request->setContainer(app());
    $request->validateResolved();

    expect($request->tagIds())->toBe([1, 5, 10]);
});

test('tagIds returns empty array for empty string', function () {
    $request = MealSearchRequest::create('/meals/search', 'GET', [
        'tags' => '',
    ]);

    $request->setContainer(app());
    $request->validateResolved();

    expect($request->tagIds())->toBeEmpty();
});

test('tagIds filters out non-positive values', function () {
    $request = MealSearchRequest::create('/meals/search', 'GET', [
        'tags' => '1,0,-1,5',
    ]);

    $request->setContainer(app());
    $request->validateResolved();

    expect(array_values($request->tagIds()))->toBe([1, 5]);
});

test('availabilityFilter defaults to all', function () {
    $request = MealSearchRequest::create('/meals/search', 'GET', []);

    $request->setContainer(app());
    $request->validateResolved();

    expect($request->availabilityFilter())->toBe('all');
});

test('priceMin and priceMax return null when not set', function () {
    $request = MealSearchRequest::create('/meals/search', 'GET', []);

    $request->setContainer(app());
    $request->validateResolved();

    expect($request->priceMin())->toBeNull()
        ->and($request->priceMax())->toBeNull();
});

test('priceMin and priceMax return integers when set', function () {
    $request = MealSearchRequest::create('/meals/search', 'GET', [
        'price_min' => '500',
        'price_max' => '2000',
    ]);

    $request->setContainer(app());
    $request->validateResolved();

    expect($request->priceMin())->toBe(500)
        ->and($request->priceMax())->toBe(2000);
});

test('filter validation rejects invalid availability value', function () {
    $validator = validator(
        ['availability' => 'invalid_value'],
        (new MealSearchRequest)->rules()
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('availability'))->toBeTrue();
});

test('filter validation rejects negative price_min', function () {
    $validator = validator(
        ['price_min' => -100],
        (new MealSearchRequest)->rules()
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('price_min'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| F-136: Only live+available meals shown in filter results
|--------------------------------------------------------------------------
*/

test('filter excludes draft meals', function () {
    $tag = Tag::factory()->create(['tenant_id' => $this->tenant->id]);

    $liveMeal = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $liveMeal->id]);
    $liveMeal->tags()->attach($tag->id);

    $draftMeal = Meal::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'draft']);
    MealComponent::factory()->create(['meal_id' => $draftMeal->id]);
    $draftMeal->tags()->attach($tag->id);

    $results = $this->service->filterMeals($this->tenant, '', [$tag->id]);
    expect($results->total())->toBe(1)
        ->and($results->first()->id)->toBe($liveMeal->id);
});

test('filter excludes unavailable meals', function () {
    $availableMeal = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->create(['meal_id' => $availableMeal->id]);

    $unavailableMeal = Meal::factory()->live()->create(['tenant_id' => $this->tenant->id, 'is_available' => false]);
    MealComponent::factory()->create(['meal_id' => $unavailableMeal->id]);

    $results = $this->service->filterMeals($this->tenant);
    expect($results->total())->toBe(1);
});
