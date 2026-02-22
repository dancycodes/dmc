<?php

/**
 * F-198: Favorites List View — FavoritesService Unit Tests
 *
 * Tests the FavoritesService methods for retrieving and removing favorite
 * cooks and meals for authenticated users.
 */

use App\Models\Meal;
use App\Models\Tenant;
use App\Services\FavoritesService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new FavoritesService;
    (new RoleAndPermissionSeeder)->run();
});

// --- Constants ---

it('has a default per page of 12', function () {
    expect(FavoritesService::PER_PAGE)->toBe(12);
});

// --- getFavoriteCooks ---

it('returns empty paginator when user has no favorite cooks', function () {
    $user = $this->createUserWithRole('client');

    $result = $this->service->getFavoriteCooks($user);

    expect($result->total())->toBe(0)
        ->and($result->items())->toBeEmpty();
});

it('returns favorite cooks ordered reverse chronologically', function () {
    $user = $this->createUserWithRole('client');
    $cook1 = $this->createUserWithRole('cook');
    $cook2 = $this->createUserWithRole('cook');

    // First cook favorited earlier
    DB::table('favorite_cooks')->insert([
        'user_id' => $user->id,
        'cook_user_id' => $cook1->id,
        'created_at' => now()->subDays(2),
    ]);
    // Second cook favorited more recently
    DB::table('favorite_cooks')->insert([
        'user_id' => $user->id,
        'cook_user_id' => $cook2->id,
        'created_at' => now()->subDay(),
    ]);

    $result = $this->service->getFavoriteCooks($user);

    expect($result->total())->toBe(2)
        ->and($result->items()[0]['cook_user_id'])->toBe($cook2->id)
        ->and($result->items()[1]['cook_user_id'])->toBe($cook1->id);
});

it('includes tenant url in favorite cook data', function () {
    $user = $this->createUserWithRole('client');
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->create(['cook_id' => $cook->id, 'slug' => 'test-cook', 'is_active' => true]);

    DB::table('favorite_cooks')->insert([
        'user_id' => $user->id,
        'cook_user_id' => $cook->id,
        'created_at' => now(),
    ]);

    $result = $this->service->getFavoriteCooks($user);

    expect($result->items()[0]['tenant_url'])->not->toBeNull()
        ->and($result->items()[0]['tenant_slug'])->toBe('test-cook');
});

it('marks cook as available when tenant is active', function () {
    $user = $this->createUserWithRole('client');
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->create(['cook_id' => $cook->id, 'is_active' => true]);

    DB::table('favorite_cooks')->insert([
        'user_id' => $user->id,
        'cook_user_id' => $cook->id,
        'created_at' => now(),
    ]);

    $result = $this->service->getFavoriteCooks($user);

    expect($result->items()[0]['is_available'])->toBeTrue();
});

it('marks cook as unavailable when no tenant or tenant is inactive', function () {
    $user = $this->createUserWithRole('client');
    $cook = $this->createUserWithRole('cook');
    // No tenant assigned

    DB::table('favorite_cooks')->insert([
        'user_id' => $user->id,
        'cook_user_id' => $cook->id,
        'created_at' => now(),
    ]);

    $result = $this->service->getFavoriteCooks($user);

    expect($result->items()[0]['is_available'])->toBeFalse();
});

it('paginates favorite cooks with correct per page', function () {
    $user = $this->createUserWithRole('client');

    for ($i = 0; $i < 14; $i++) {
        $cook = $this->createUserWithRole('cook');
        DB::table('favorite_cooks')->insert([
            'user_id' => $user->id,
            'cook_user_id' => $cook->id,
            'created_at' => now()->subMinutes($i),
        ]);
    }

    $result = $this->service->getFavoriteCooks($user, 1);

    expect($result->total())->toBe(14)
        ->and($result->count())->toBe(12)
        ->and($result->lastPage())->toBe(2);
});

it('returns second page of favorite cooks', function () {
    $user = $this->createUserWithRole('client');

    for ($i = 0; $i < 14; $i++) {
        $cook = $this->createUserWithRole('cook');
        DB::table('favorite_cooks')->insert([
            'user_id' => $user->id,
            'cook_user_id' => $cook->id,
            'created_at' => now()->subMinutes($i),
        ]);
    }

    $result = $this->service->getFavoriteCooks($user, 2);

    expect($result->count())->toBe(2)
        ->and($result->currentPage())->toBe(2);
});

// --- getFavoriteMeals ---

it('returns empty paginator when user has no favorite meals', function () {
    $user = $this->createUserWithRole('client');

    $result = $this->service->getFavoriteMeals($user);

    expect($result->total())->toBe(0)
        ->and($result->items())->toBeEmpty();
});

it('returns favorite meals ordered reverse chronologically', function () {
    $user = $this->createUserWithRole('client');
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id, 'is_active' => true]);

    $meal1 = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_active' => true,
    ]);
    $meal2 = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_active' => true,
    ]);

    DB::table('favorite_meals')->insert([
        'user_id' => $user->id,
        'meal_id' => $meal1->id,
        'created_at' => now()->subDays(2),
    ]);
    DB::table('favorite_meals')->insert([
        'user_id' => $user->id,
        'meal_id' => $meal2->id,
        'created_at' => now()->subDay(),
    ]);

    $result = $this->service->getFavoriteMeals($user);

    expect($result->total())->toBe(2)
        ->and($result->items()[0]['meal_id'])->toBe($meal2->id)
        ->and($result->items()[1]['meal_id'])->toBe($meal1->id);
});

it('marks meal as available when live, active, and tenant active', function () {
    $user = $this->createUserWithRole('client');
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id, 'is_active' => true]);

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_active' => true,
    ]);

    DB::table('favorite_meals')->insert([
        'user_id' => $user->id,
        'meal_id' => $meal->id,
        'created_at' => now(),
    ]);

    $result = $this->service->getFavoriteMeals($user);

    expect($result->items()[0]['is_available'])->toBeTrue();
});

it('marks meal as unavailable when meal is draft', function () {
    $user = $this->createUserWithRole('client');
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id, 'is_active' => true]);

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_DRAFT,
        'is_active' => true,
    ]);

    DB::table('favorite_meals')->insert([
        'user_id' => $user->id,
        'meal_id' => $meal->id,
        'created_at' => now(),
    ]);

    $result = $this->service->getFavoriteMeals($user);

    expect($result->items()[0]['is_available'])->toBeFalse();
});

it('includes meal price in favorite meal data', function () {
    $user = $this->createUserWithRole('client');
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id, 'is_active' => true]);

    $meal = Meal::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => Meal::STATUS_LIVE,
        'is_active' => true,
        'price' => 2500,
    ]);

    DB::table('favorite_meals')->insert([
        'user_id' => $user->id,
        'meal_id' => $meal->id,
        'created_at' => now(),
    ]);

    $result = $this->service->getFavoriteMeals($user);

    expect($result->items()[0]['price'])->toBe(2500);
});

// --- removeFavoriteCook ---

it('removes cook from favorites successfully', function () {
    $user = $this->createUserWithRole('client');
    $cook = $this->createUserWithRole('cook');

    DB::table('favorite_cooks')->insert([
        'user_id' => $user->id,
        'cook_user_id' => $cook->id,
        'created_at' => now(),
    ]);

    $result = $this->service->removeFavoriteCook($user, $cook->id);

    expect($result['success'])->toBeTrue();
    expect(DB::table('favorite_cooks')
        ->where('user_id', $user->id)
        ->where('cook_user_id', $cook->id)
        ->exists())->toBeFalse();
});

it('returns failure when cook not in favorites', function () {
    $user = $this->createUserWithRole('client');
    $cook = $this->createUserWithRole('cook');

    $result = $this->service->removeFavoriteCook($user, $cook->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBeString();
});

// --- removeFavoriteMeal ---

it('removes meal from favorites successfully', function () {
    $user = $this->createUserWithRole('client');
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    DB::table('favorite_meals')->insert([
        'user_id' => $user->id,
        'meal_id' => $meal->id,
        'created_at' => now(),
    ]);

    $result = $this->service->removeFavoriteMeal($user, $meal->id);

    expect($result['success'])->toBeTrue();
    expect(DB::table('favorite_meals')
        ->where('user_id', $user->id)
        ->where('meal_id', $meal->id)
        ->exists())->toBeFalse();
});

it('returns failure when meal not in favorites', function () {
    $user = $this->createUserWithRole('client');
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    $result = $this->service->removeFavoriteMeal($user, $meal->id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBeString();
});
