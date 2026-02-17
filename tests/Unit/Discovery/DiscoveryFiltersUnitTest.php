<?php

/**
 * F-069 — Discovery Filters Unit Tests
 *
 * Tests the DiscoveryService filter logic, filter counting,
 * and filter option retrieval methods.
 */

use App\Models\Tenant;
use App\Models\Town;
use App\Models\User;
use App\Services\DiscoveryService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new DiscoveryService;
    (new RoleAndPermissionSeeder)->run();
});

/*
|--------------------------------------------------------------------------
| Active Filter Count (BR-093)
|--------------------------------------------------------------------------
*/

it('counts zero active filters when none applied', function () {
    $count = $this->service->countActiveFilters();
    expect($count)->toBe(0);
});

it('counts town as one active filter', function () {
    $count = $this->service->countActiveFilters(town: 1);
    expect($count)->toBe(1);
});

it('counts availability filter when not "all"', function () {
    $count = $this->service->countActiveFilters(availability: 'now');
    expect($count)->toBe(1);
});

it('does not count availability "all" as active filter', function () {
    $count = $this->service->countActiveFilters(availability: 'all');
    expect($count)->toBe(0);
});

it('counts each selected tag individually', function () {
    $count = $this->service->countActiveFilters(tags: [1, 2, 3]);
    expect($count)->toBe(3);
});

it('counts min rating as one active filter', function () {
    $count = $this->service->countActiveFilters(minRating: 4);
    expect($count)->toBe(1);
});

it('counts combined filters correctly', function () {
    // town (1) + availability (1) + 2 tags (2) + rating (1) = 5
    $count = $this->service->countActiveFilters(
        town: 5,
        availability: 'today',
        tags: [10, 20],
        minRating: 3,
    );
    expect($count)->toBe(5);
});

it('counts empty tags array as zero', function () {
    $count = $this->service->countActiveFilters(tags: []);
    expect($count)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Filter Towns (BR-097)
|--------------------------------------------------------------------------
*/

it('returns active towns for filter dropdown', function () {
    Town::factory()->create(['name_en' => 'Douala', 'is_active' => true]);
    Town::factory()->create(['name_en' => 'Yaounde', 'is_active' => true]);
    Town::factory()->create(['name_en' => 'Inactive Town', 'is_active' => false]);

    $towns = $this->service->getFilterTowns();

    expect($towns)->toHaveCount(2);
    expect($towns->pluck('name_en')->toArray())->toContain('Douala', 'Yaounde');
    expect($towns->pluck('name_en')->toArray())->not->toContain('Inactive Town');
});

it('returns towns ordered by locale-specific name', function () {
    Town::factory()->create(['name_en' => 'Bamenda']);
    Town::factory()->create(['name_en' => 'Akonolinga']);
    Town::factory()->create(['name_en' => 'Douala']);

    $towns = $this->service->getFilterTowns();

    expect($towns->first()->name_en)->toBe('Akonolinga');
    expect($towns->last()->name_en)->toBe('Douala');
});

it('returns empty collection when no towns exist', function () {
    $towns = $this->service->getFilterTowns();
    expect($towns)->toHaveCount(0);
});

/*
|--------------------------------------------------------------------------
| Filter Tags (Forward-compatible)
|--------------------------------------------------------------------------
*/

it('returns empty collection for tags when tables do not exist', function () {
    // tags/meal_tag/meals tables do not exist yet
    $tags = $this->service->getFilterTags();
    expect($tags)->toHaveCount(0);
});

/*
|--------------------------------------------------------------------------
| Discovery Cooks with Filters (BR-091: AND logic)
|--------------------------------------------------------------------------
*/

it('returns all discoverable cooks with no filters', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['is_active' => true, 'cook_id' => $user->id]);

    $result = $this->service->getDiscoverableCooks();

    expect($result->total())->toBe(1);
});

it('accepts filter parameters without error', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['is_active' => true, 'cook_id' => $user->id]);

    $result = $this->service->getDiscoverableCooks(
        town: 999,
        availability: 'now',
        tags: [1, 2],
        minRating: 4,
    );

    // Forward-compatible: without actual tables, filters are no-ops so all cooks returned
    expect($result)->not->toBeNull();
});

it('filters with availability "all" returns all cooks', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['is_active' => true, 'cook_id' => $user->id]);

    $result = $this->service->getDiscoverableCooks(availability: 'all');

    expect($result->total())->toBe(1);
});

it('combines search with filters without error', function () {
    $user = User::factory()->create();
    Tenant::factory()->create([
        'is_active' => true,
        'cook_id' => $user->id,
        'name_en' => 'Chef Latifa',
    ]);

    // BR-096: Filters combine with search
    $result = $this->service->getDiscoverableCooks(
        search: 'Latifa',
        availability: 'today',
    );

    expect($result)->not->toBeNull();
});

it('excludes inactive tenants even with filters', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['is_active' => false, 'cook_id' => $user->id]);

    $result = $this->service->getDiscoverableCooks(availability: 'all');

    expect($result->total())->toBe(0);
});

it('excludes tenants without cook even with filters', function () {
    Tenant::factory()->create(['is_active' => true, 'cook_id' => null]);

    $result = $this->service->getDiscoverableCooks(availability: 'all');

    expect($result->total())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Validation Rules (DiscoveryRequest)
|--------------------------------------------------------------------------
*/

it('validates filter parameters in discovery request', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['is_active' => true, 'cook_id' => $user->id]);

    // Valid parameters should work
    $response = $this->get('/?town=1&availability=now&min_rating=3');
    $response->assertStatus(200);
});

it('rejects invalid availability value', function () {
    $response = $this->get('/?availability=invalid');
    // FormRequest validation redirects back with errors
    $response->assertStatus(302);
});

it('rejects min_rating above 5', function () {
    $response = $this->get('/?min_rating=6');
    // FormRequest validation redirects back with errors
    $response->assertStatus(302);
});

it('accepts tags as array parameter', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['is_active' => true, 'cook_id' => $user->id]);

    $response = $this->get('/?tags[]=1&tags[]=2');
    $response->assertStatus(200);
});
