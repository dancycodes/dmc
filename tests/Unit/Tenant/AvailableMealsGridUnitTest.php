<?php

/**
 * F-128: Available Meals Grid Display — Unit Tests
 *
 * Tests TenantLandingService methods for building meal grid data.
 * BR-146: Only live + available meals displayed
 * BR-147: Schedule-based filtering
 * BR-149: Starting price = min component price
 * BR-151: Tag chips max 3 visible
 * BR-155: Order by position then name
 */

use App\Models\CookSchedule;
use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\MealImage;
use App\Models\MealSchedule;
use App\Models\Tag;
use App\Models\Tenant;
use App\Services\TenantLandingService;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SellingUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
    (new SellingUnitSeeder)->run();
    $this->tenant = Tenant::factory()->create();
    $this->service = app(TenantLandingService::class);
});

// --- BR-146: Status and availability filtering ---

it('returns only live and available meals', function () {
    Meal::factory()->for($this->tenant)->live()->create(); // live + available
    Meal::factory()->for($this->tenant)->draft()->create(); // draft — hidden
    Meal::factory()->for($this->tenant)->live()->unavailable()->create(); // live but unavailable — hidden

    $meals = $this->service->getAvailableMeals($this->tenant);

    expect($meals->total())->toBe(1);
});

it('excludes soft-deleted meals', function () {
    $meal = Meal::factory()->for($this->tenant)->live()->create();
    Meal::factory()->for($this->tenant)->live()->create();
    $meal->delete(); // soft delete

    $meals = $this->service->getAvailableMeals($this->tenant);

    expect($meals->total())->toBe(1);
});

it('returns empty paginator when no available meals exist', function () {
    $meals = $this->service->getAvailableMeals($this->tenant);

    expect($meals->total())->toBe(0)
        ->and($meals->items())->toBeEmpty();
});

// --- BR-147: Schedule filtering ---

it('shows meals when cook has no schedules defined', function () {
    Meal::factory()->for($this->tenant)->live()->count(3)->create();

    // No CookSchedule entries → all meals show
    $meals = $this->service->getAvailableMeals($this->tenant);

    expect($meals->total())->toBe(3);
});

it('shows meals without custom schedule when cook has schedule days', function () {
    // Cook schedule for monday and wednesday
    CookSchedule::factory()->for($this->tenant)->create([
        'day_of_week' => 'monday',
        'is_available' => true,
    ]);
    CookSchedule::factory()->for($this->tenant)->create([
        'day_of_week' => 'wednesday',
        'is_available' => true,
    ]);

    // Meals without custom schedules — should appear (they follow cook schedule)
    Meal::factory()->for($this->tenant)->live()->count(2)->create();

    $meals = $this->service->getAvailableMeals($this->tenant);

    expect($meals->total())->toBe(2);
});

it('filters meals with custom schedule by scheduled days', function () {
    CookSchedule::factory()->for($this->tenant)->create([
        'day_of_week' => 'monday',
        'is_available' => true,
    ]);

    // Meal with custom schedule on monday — should appear
    $mealOnMonday = Meal::factory()->for($this->tenant)->live()->create();
    MealSchedule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'meal_id' => $mealOnMonday->id,
        'day_of_week' => 'monday',
        'is_available' => true,
    ]);

    // Meal with custom schedule on sunday only — should not appear
    $mealOnSunday = Meal::factory()->for($this->tenant)->live()->create();
    MealSchedule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'meal_id' => $mealOnSunday->id,
        'day_of_week' => 'sunday',
        'is_available' => true,
    ]);

    $meals = $this->service->getAvailableMeals($this->tenant);

    expect($meals->total())->toBe(1)
        ->and($meals->first()->id)->toBe($mealOnMonday->id);
});

// --- BR-149: Starting price ---

it('calculates starting price as minimum available component price', function () {
    $meal = Meal::factory()->for($this->tenant)->live()->create();
    MealComponent::factory()->for($meal)->withPrice(1500)->create();
    MealComponent::factory()->for($meal)->withPrice(500)->create();
    MealComponent::factory()->for($meal)->withPrice(2000)->create();

    $card = $this->service->buildMealCardData($meal->load(['images', 'components', 'tags']));

    expect($card['startingPrice'])->toBe(500);
});

it('returns null starting price when meal has no components', function () {
    $meal = Meal::factory()->for($this->tenant)->live()->create();

    $card = $this->service->buildMealCardData($meal->load(['images', 'components', 'tags']));

    expect($card['startingPrice'])->toBeNull();
});

it('only considers available components for starting price', function () {
    $meal = Meal::factory()->for($this->tenant)->live()->create();
    MealComponent::factory()->for($meal)->withPrice(100)->unavailable()->create();
    MealComponent::factory()->for($meal)->withPrice(500)->create();

    $card = $this->service->buildMealCardData($meal->load(['images', 'components', 'tags']));

    expect($card['startingPrice'])->toBe(500);
});

// --- BR-148: Card data building ---

it('builds card data with primary image', function () {
    $meal = Meal::factory()->for($this->tenant)->live()->withPrepTime(30)->create();
    MealImage::factory()->for($meal)->create(['position' => 1, 'path' => 'meals/test.jpg', 'thumbnail_path' => 'meals/test-thumb.jpg']);

    $card = $this->service->buildMealCardData($meal->load(['images', 'components', 'tags']));

    expect($card['image'])->toContain('test-thumb.jpg')
        ->and($card['prepTime'])->toBe(30)
        ->and($card['name'])->not->toBeEmpty();
});

it('returns null image when meal has no images', function () {
    $meal = Meal::factory()->for($this->tenant)->live()->create();

    $card = $this->service->buildMealCardData($meal->load(['images', 'components', 'tags']));

    expect($card['image'])->toBeNull();
});

// --- BR-151: Tag chips ---

it('shows max 3 tags with overflow count', function () {
    $meal = Meal::factory()->for($this->tenant)->live()->create();
    $tags = Tag::factory()->for($this->tenant)->count(5)->create();
    $meal->tags()->attach($tags->pluck('id'));

    $card = $this->service->buildMealCardData($meal->load(['images', 'components', 'tags']));

    expect($card['tags'])->toHaveCount(3)
        ->and($card['tagOverflow'])->toBe(2);
});

it('shows all tags when 3 or fewer', function () {
    $meal = Meal::factory()->for($this->tenant)->live()->create();
    $tags = Tag::factory()->for($this->tenant)->count(2)->create();
    $meal->tags()->attach($tags->pluck('id'));

    $card = $this->service->buildMealCardData($meal->load(['images', 'components', 'tags']));

    expect($card['tags'])->toHaveCount(2)
        ->and($card['tagOverflow'])->toBe(0);
});

it('handles meal with no tags', function () {
    $meal = Meal::factory()->for($this->tenant)->live()->create();

    $card = $this->service->buildMealCardData($meal->load(['images', 'components', 'tags']));

    expect($card['tags'])->toBeEmpty()
        ->and($card['tagOverflow'])->toBe(0);
});

// --- Edge case: all components unavailable ---

it('detects all components unavailable as sold out', function () {
    $meal = Meal::factory()->for($this->tenant)->live()->create();
    MealComponent::factory()->for($meal)->unavailable()->count(2)->create();

    $card = $this->service->buildMealCardData($meal->load(['images', 'components', 'tags']));

    expect($card['allComponentsUnavailable'])->toBeTrue();
});

it('does not show sold out when at least one component available', function () {
    $meal = Meal::factory()->for($this->tenant)->live()->create();
    MealComponent::factory()->for($meal)->unavailable()->create();
    MealComponent::factory()->for($meal)->create(); // available

    $card = $this->service->buildMealCardData($meal->load(['images', 'components', 'tags']));

    expect($card['allComponentsUnavailable'])->toBeFalse();
});

it('does not show sold out when meal has no components', function () {
    $meal = Meal::factory()->for($this->tenant)->live()->create();

    $card = $this->service->buildMealCardData($meal->load(['images', 'components', 'tags']));

    expect($card['allComponentsUnavailable'])->toBeFalse();
});

// --- BR-155/BR-234: Ordering ---
// F-137: Default sort is "Most Popular" (BR-234). With zero orders, falls back to created_at desc.

it('orders meals by popularity (most popular first) by default', function () {
    // All have zero orders, so fallback is created_at DESC (newest first)
    $mealOldest = Meal::factory()->for($this->tenant)->live()->create(['created_at' => now()->subDays(3)]);
    $mealMiddle = Meal::factory()->for($this->tenant)->live()->create(['created_at' => now()->subDay()]);
    $mealNewest = Meal::factory()->for($this->tenant)->live()->create(['created_at' => now()]);

    $meals = $this->service->getAvailableMeals($this->tenant);

    // All have 0 orders, so sorted by created_at DESC: newest first
    expect($meals->first()->id)->toBe($mealNewest->id)
        ->and($meals->items()[1]->id)->toBe($mealMiddle->id)
        ->and($meals->last()->id)->toBe($mealOldest->id);
});

// --- Pagination ---

it('paginates at 12 per page', function () {
    Meal::factory()->for($this->tenant)->live()->count(15)->create();

    $page1 = $this->service->getAvailableMeals($this->tenant, 1);
    $page2 = $this->service->getAvailableMeals($this->tenant, 2);

    expect($page1->count())->toBe(12)
        ->and($page2->count())->toBe(3)
        ->and($page1->total())->toBe(15);
});

// --- Price formatting ---

it('formats price with XAF currency', function () {
    expect(TenantLandingService::formatPrice(2500))->toBe('2,500 XAF')
        ->and(TenantLandingService::formatPrice(500))->toBe('500 XAF')
        ->and(TenantLandingService::formatPrice(10000))->toBe('10,000 XAF');
});

// --- Locale-aware name ---

it('returns meal name in current locale', function () {
    $meal = Meal::factory()->for($this->tenant)->live()->create([
        'name_en' => 'Ndole Platter',
        'name_fr' => 'Assiette de Ndole',
    ]);

    app()->setLocale('en');
    $cardEn = $this->service->buildMealCardData($meal->load(['images', 'components', 'tags']));

    app()->setLocale('fr');
    $cardFr = $this->service->buildMealCardData($meal->load(['images', 'components', 'tags']));

    expect($cardEn['name'])->toBe('Ndole Platter')
        ->and($cardFr['name'])->toBe('Assiette de Ndole');
});

// --- Tenant isolation ---

it('only returns meals for the specified tenant', function () {
    $otherTenant = Tenant::factory()->create();
    Meal::factory()->for($this->tenant)->live()->create();
    Meal::factory()->for($otherTenant)->live()->create();

    $meals = $this->service->getAvailableMeals($this->tenant);

    expect($meals->total())->toBe(1);
});

// --- getLandingPageData integration ---

it('includes meals in landing page data', function () {
    Meal::factory()->for($this->tenant)->live()->create();

    $data = $this->service->getLandingPageData($this->tenant);

    expect($data)->toHaveKey('meals')
        ->and($data['meals']->total())->toBe(1);
});
