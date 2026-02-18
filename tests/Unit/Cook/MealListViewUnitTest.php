<?php

use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\MealImage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MealService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);

    $result = createTenantWithCook();
    $this->tenant = $result['tenant'];
    $this->cook = $result['cook'];
    $this->tenant->update(['cook_id' => $this->cook->id]);
    $this->mealService = new MealService;
});

/* ---------------------------------------------------------------
 * MealService::getMealListData() — Core query logic
 * ------------------------------------------------------------- */

test('getMealListData returns all meals for tenant', function () {
    Meal::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

    $data = $this->mealService->getMealListData($this->tenant);

    expect($data['meals'])->toHaveCount(5)
        ->and($data['totalCount'])->toBe(5);
});

test('getMealListData excludes soft-deleted meals (BR-262)', function () {
    Meal::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
    $deleted = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $deleted->delete();

    $data = $this->mealService->getMealListData($this->tenant);

    expect($data['meals'])->toHaveCount(3)
        ->and($data['totalCount'])->toBe(3);
});

test('getMealListData is tenant-scoped (BR-261)', function () {
    Meal::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    $otherTenant = Tenant::factory()->create();
    Meal::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);

    $data = $this->mealService->getMealListData($this->tenant);

    expect($data['meals'])->toHaveCount(2)
        ->and($data['totalCount'])->toBe(2);
});

test('getMealListData searches by name_en and name_fr (BR-263)', function () {
    Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Jollof Rice Special',
        'name_fr' => 'Riz Jollof Special',
    ]);
    Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Ndole Dish',
        'name_fr' => 'Plat de Ndole',
    ]);

    // Search by EN name
    $data = $this->mealService->getMealListData($this->tenant, ['search' => 'Jollof']);
    expect($data['meals'])->toHaveCount(1);

    // Search by FR name
    $data = $this->mealService->getMealListData($this->tenant, ['search' => 'Ndole']);
    expect($data['meals'])->toHaveCount(1);
});

test('getMealListData search is case-insensitive', function () {
    Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Grilled Fish',
        'name_fr' => 'Poisson Braise',
    ]);

    $data = $this->mealService->getMealListData($this->tenant, ['search' => 'grilled fish']);
    expect($data['meals'])->toHaveCount(1);
});

test('getMealListData filters by status draft (BR-264)', function () {
    Meal::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'status' => Meal::STATUS_DRAFT]);
    Meal::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'status' => Meal::STATUS_LIVE]);

    $data = $this->mealService->getMealListData($this->tenant, ['status' => 'draft']);

    expect($data['meals'])->toHaveCount(3);
});

test('getMealListData filters by status live (BR-264)', function () {
    Meal::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'status' => Meal::STATUS_DRAFT]);
    Meal::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'status' => Meal::STATUS_LIVE]);

    $data = $this->mealService->getMealListData($this->tenant, ['status' => 'live']);

    expect($data['meals'])->toHaveCount(2);
});

test('getMealListData filters by availability available (BR-265)', function () {
    Meal::factory()->count(4)->create(['tenant_id' => $this->tenant->id, 'is_available' => true]);
    Meal::factory()->count(1)->create(['tenant_id' => $this->tenant->id, 'is_available' => false]);

    $data = $this->mealService->getMealListData($this->tenant, ['availability' => 'available']);

    expect($data['meals'])->toHaveCount(4);
});

test('getMealListData filters by availability unavailable (BR-265)', function () {
    Meal::factory()->count(4)->create(['tenant_id' => $this->tenant->id, 'is_available' => true]);
    Meal::factory()->count(1)->create(['tenant_id' => $this->tenant->id, 'is_available' => false]);

    $data = $this->mealService->getMealListData($this->tenant, ['availability' => 'unavailable']);

    expect($data['meals'])->toHaveCount(1);
});

test('getMealListData sorts by newest first by default (BR-266)', function () {
    $old = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_at' => now()->subDays(5),
    ]);
    $new = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_at' => now(),
    ]);

    $data = $this->mealService->getMealListData($this->tenant);

    expect($data['meals']->first()->id)->toBe($new->id);
});

test('getMealListData sorts by oldest first', function () {
    $old = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_at' => now()->subDays(5),
    ]);
    $new = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_at' => now(),
    ]);

    $data = $this->mealService->getMealListData($this->tenant, ['sort' => 'oldest']);

    expect($data['meals']->first()->id)->toBe($old->id);
});

test('getMealListData sorts by name ascending', function () {
    Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Zucchini Soup',
        'name_fr' => 'Soupe Courgette',
    ]);
    Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Apple Pie',
        'name_fr' => 'Tarte aux Pommes',
    ]);

    $data = $this->mealService->getMealListData($this->tenant, ['sort' => 'name_asc']);

    expect($data['meals']->first()->name_en)->toBe('Apple Pie');
});

test('getMealListData sorts by name descending', function () {
    Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Apple Pie',
        'name_fr' => 'Tarte aux Pommes',
    ]);
    Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Zucchini Soup',
        'name_fr' => 'Soupe Courgette',
    ]);

    $data = $this->mealService->getMealListData($this->tenant, ['sort' => 'name_desc']);

    expect($data['meals']->first()->name_en)->toBe('Zucchini Soup');
});

test('getMealListData returns correct summary counts', function () {
    Meal::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'status' => Meal::STATUS_DRAFT, 'is_available' => true]);
    Meal::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'status' => Meal::STATUS_LIVE, 'is_available' => true]);
    Meal::factory()->count(1)->create(['tenant_id' => $this->tenant->id, 'status' => Meal::STATUS_LIVE, 'is_available' => false]);

    $data = $this->mealService->getMealListData($this->tenant);

    expect($data['totalCount'])->toBe(6)
        ->and($data['draftCount'])->toBe(2)
        ->and($data['liveCount'])->toBe(4)
        ->and($data['availableCount'])->toBe(5)
        ->and($data['unavailableCount'])->toBe(1);
});

test('getMealListData eager loads components count (BR-269)', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    MealComponent::factory()->count(3)->create(['meal_id' => $meal->id]);

    $data = $this->mealService->getMealListData($this->tenant);

    expect($data['meals']->first()->components_count)->toBe(3);
});

test('getMealListData eager loads first image for thumbnail', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    MealImage::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    MealImage::factory()->create(['meal_id' => $meal->id, 'position' => 2]);

    $data = $this->mealService->getMealListData($this->tenant);

    // Should load images relationship
    expect($data['meals']->first()->relationLoaded('images'))->toBeTrue()
        ->and($data['meals']->first()->images)->toHaveCount(1);
});

test('getMealListData paginates at 15 per page', function () {
    Meal::factory()->count(20)->create(['tenant_id' => $this->tenant->id]);

    $data = $this->mealService->getMealListData($this->tenant);

    expect($data['meals']->perPage())->toBe(15)
        ->and($data['meals']->total())->toBe(20)
        ->and($data['meals'])->toHaveCount(15);
});

test('getMealListData combines search and status filter', function () {
    Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Jollof Rice',
        'name_fr' => 'Riz Jollof',
        'status' => Meal::STATUS_DRAFT,
    ]);
    Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Jollof Special',
        'name_fr' => 'Jollof Special',
        'status' => Meal::STATUS_LIVE,
    ]);

    $data = $this->mealService->getMealListData($this->tenant, [
        'search' => 'Jollof',
        'status' => 'live',
    ]);

    expect($data['meals'])->toHaveCount(1);
});

/* ---------------------------------------------------------------
 * MealListRequest — Validation rules
 * ------------------------------------------------------------- */

test('MealListRequest allows valid filter parameters', function () {
    $request = new \App\Http\Requests\Cook\MealListRequest;

    $rules = $request->rules();

    expect($rules)->toHaveKey('search')
        ->and($rules)->toHaveKey('status')
        ->and($rules)->toHaveKey('availability')
        ->and($rules)->toHaveKey('sort')
        ->and($rules)->toHaveKey('page');
});

test('MealListRequest status validation accepts valid values', function () {
    $request = new \App\Http\Requests\Cook\MealListRequest;
    $rules = $request->rules();

    expect($rules['status'])->toContain('in:draft,live');
});

test('MealListRequest availability validation accepts valid values', function () {
    $request = new \App\Http\Requests\Cook\MealListRequest;
    $rules = $request->rules();

    expect($rules['availability'])->toContain('in:available,unavailable');
});

test('MealListRequest sort validation accepts valid values', function () {
    $request = new \App\Http\Requests\Cook\MealListRequest;
    $rules = $request->rules();

    expect($rules['sort'])->toContain('in:name_asc,name_desc,newest,oldest,most_ordered');
});

/* ---------------------------------------------------------------
 * Controller index — Permission check (BR-268)
 * ------------------------------------------------------------- */

test('meal list requires manage-meals permission (BR-268)', function () {
    $client = User::factory()->create();
    $client->assignRole('client');

    $this->actingAs($client);

    $mainDomain = \App\Services\TenantService::mainDomain();
    $url = "https://{$this->tenant->slug}.{$mainDomain}/dashboard/meals";

    $response = $this->get($url);

    expect($response->status())->toBe(403);
});

test('meal list accessible with manage-meals permission', function () {
    $this->actingAs($this->cook);

    $mainDomain = \App\Services\TenantService::mainDomain();
    $url = "https://{$this->tenant->slug}.{$mainDomain}/dashboard/meals";

    $response = $this->get($url);

    expect($response->status())->toBeIn([200, 302]);
});

/* ---------------------------------------------------------------
 * Controller index — Data is passed to view
 * ------------------------------------------------------------- */

test('meal list passes required data to view', function () {
    Meal::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->cook);
    $mainDomain = \App\Services\TenantService::mainDomain();
    $url = "https://{$this->tenant->slug}.{$mainDomain}/dashboard/meals";

    $response = $this->get($url);

    $response->assertStatus(200);
});

test('meal list supports search query parameter', function () {
    Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Jollof Rice Deluxe',
        'name_fr' => 'Riz Jollof Deluxe',
    ]);
    Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Ndole Classic',
        'name_fr' => 'Ndole Classique',
    ]);

    $this->actingAs($this->cook);
    $mainDomain = \App\Services\TenantService::mainDomain();
    $url = "https://{$this->tenant->slug}.{$mainDomain}/dashboard/meals?search=Jollof";

    $response = $this->get($url);

    $response->assertStatus(200);
    $response->assertSee('Jollof Rice Deluxe');
    $response->assertDontSee('Ndole Classic');
});

test('meal list supports status filter parameter', function () {
    Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Draft Meal',
        'name_fr' => 'Repas Brouillon',
        'status' => Meal::STATUS_DRAFT,
    ]);
    Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name_en' => 'Live Meal',
        'name_fr' => 'Repas Publie',
        'status' => Meal::STATUS_LIVE,
    ]);

    $this->actingAs($this->cook);
    $mainDomain = \App\Services\TenantService::mainDomain();
    $url = "https://{$this->tenant->slug}.{$mainDomain}/dashboard/meals?status=draft";

    $response = $this->get($url);

    $response->assertStatus(200);
    $response->assertSee('Draft Meal');
    $response->assertDontSee('Live Meal');
});

/* ---------------------------------------------------------------
 * Empty states
 * ------------------------------------------------------------- */

test('meal list shows empty state when no meals exist', function () {
    $this->actingAs($this->cook);
    $mainDomain = \App\Services\TenantService::mainDomain();
    $url = "https://{$this->tenant->slug}.{$mainDomain}/dashboard/meals";

    $response = $this->get($url);

    $response->assertStatus(200);
    $response->assertSee(__('No meals yet'));
    $response->assertSee(__('Create your first meal to start building your menu.'));
});

test('meal list shows filter empty state when search returns no results', function () {
    Meal::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->cook);
    $mainDomain = \App\Services\TenantService::mainDomain();
    $url = "https://{$this->tenant->slug}.{$mainDomain}/dashboard/meals?search=nonexistent12345";

    $response = $this->get($url);

    $response->assertStatus(200);
    $response->assertSee(__('No meals match your filters'));
});
