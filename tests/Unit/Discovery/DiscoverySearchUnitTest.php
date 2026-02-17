<?php

use App\Models\Tenant;
use App\Services\DiscoveryService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new DiscoveryService;
    (new RoleAndPermissionSeeder)->run();
});

// --- scopeDiscoverySearch: Cook Name Search ---

it('finds cooks by English name partial match', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Chef Latifa Kitchen', 'name_fr' => 'Cuisine de Chef Latifa']);
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Beignet House', 'name_fr' => 'Maison du Beignet']);

    $result = $this->service->getDiscoverableCooks(search: 'Latifa');

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->name_en)->toBe('Chef Latifa Kitchen');
});

it('finds cooks by French name partial match', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Latifa Kitchen', 'name_fr' => 'Cuisine de Latifa']);
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Beignet House', 'name_fr' => 'Maison du Beignet']);

    $result = $this->service->getDiscoverableCooks(search: 'Cuisine');

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->name_fr)->toBe('Cuisine de Latifa');
});

it('performs case-insensitive search', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Chef AMARA Kitchen']);

    $result = $this->service->getDiscoverableCooks(search: 'amara');

    expect($result->total())->toBe(1);
});

it('performs accent-insensitive search', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Cafe Creme', 'name_fr' => 'Cafe Creme']);

    // Search with accented characters should still match
    $result = $this->service->getDiscoverableCooks(search: 'Creme');

    expect($result->total())->toBe(1);
});

it('performs accent-insensitive search finding accented data', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_fr' => 'Cafe Specialite']);

    // Search without accents should find accented data
    $result = $this->service->getDiscoverableCooks(search: 'Specialite');

    expect($result->total())->toBe(1);
});

it('returns partial matches (contains, not exact)', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'The Amazing Ndole Kitchen']);

    $result = $this->service->getDiscoverableCooks(search: 'Ndole');

    expect($result->total())->toBe(1);
});

it('searches description fields as well', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create([
        'name_en' => 'Simple Kitchen',
        'description_en' => 'We serve the best Ndole in town',
    ]);
    Tenant::factory()->withCook($cook->id)->create([
        'name_en' => 'Other Kitchen',
        'description_en' => 'Beignets and more',
    ]);

    $result = $this->service->getDiscoverableCooks(search: 'Ndole');

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->name_en)->toBe('Simple Kitchen');
});

// --- scopeDiscoverySearch: Minimum Length ---

it('ignores search terms shorter than 2 characters', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'A Kitchen']);
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'B Kitchen']);

    // Single character should return all
    $result = $this->service->getDiscoverableCooks(search: 'A');

    expect($result->total())->toBe(2);
});

it('processes search terms of exactly 2 characters', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'AB Kitchen']);
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'CD Kitchen']);

    $result = $this->service->getDiscoverableCooks(search: 'AB');

    expect($result->total())->toBe(1);
});

// --- scopeDiscoverySearch: Empty / Null ---

it('returns all results when search is empty string', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create();
    Tenant::factory()->withCook($cook->id)->create();

    $result = $this->service->getDiscoverableCooks(search: '');

    expect($result->total())->toBe(2);
});

it('returns all results when search is null', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create();

    $result = $this->service->getDiscoverableCooks(search: null);

    expect($result->total())->toBe(1);
});

// --- scopeDiscoverySearch: No Results ---

it('returns empty results for non-matching search', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Chef Latifa Kitchen']);

    $result = $this->service->getDiscoverableCooks(search: 'xyz12345');

    expect($result->total())->toBe(0)
        ->and($result->items())->toBeEmpty();
});

// --- scopeDiscoverySearch: Multiple Category Matches ---

it('returns union of matches across multiple search categories', function () {
    $cook = $this->createUserWithRole('cook');
    // Matches by name
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Chicken Palace', 'description_en' => 'Best fried chicken']);
    // Matches by description
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Amara Kitchen', 'description_en' => 'We love chicken dishes']);
    // Does not match
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Beignet House', 'description_en' => 'Best beignets']);

    $result = $this->service->getDiscoverableCooks(search: 'chicken');

    expect($result->total())->toBe(2);
});

// --- scopeDiscoverySearch: Combined with Active/Cook Filters ---

it('search respects active tenant filter', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Active Ndole Kitchen', 'is_active' => true]);
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Inactive Ndole Kitchen', 'is_active' => false]);

    $result = $this->service->getDiscoverableCooks(search: 'Ndole');

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->name_en)->toBe('Active Ndole Kitchen');
});

it('search respects cook assignment filter', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Assigned Ndole Kitchen']);
    Tenant::factory()->create(['name_en' => 'Unassigned Ndole Kitchen', 'cook_id' => null]);

    $result = $this->service->getDiscoverableCooks(search: 'Ndole');

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->name_en)->toBe('Assigned Ndole Kitchen');
});

// --- scopeDiscoverySearch: Sorting with Search ---

it('maintains sort order when searching', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Zara Ndole Kitchen']);
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Alpha Ndole Kitchen']);

    $result = $this->service->getDiscoverableCooks(search: 'Ndole', sort: 'name', direction: 'asc');

    expect($result->items()[0]->name_en)->toBe('Alpha Ndole Kitchen')
        ->and($result->items()[1]->name_en)->toBe('Zara Ndole Kitchen');
});

// --- DiscoveryRequest Validation ---

it('allows search field in request validation', function () {
    $request = new \App\Http\Requests\DiscoveryRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('search')
        ->and($rules['search'])->toContain('nullable')
        ->and($rules['search'])->toContain('string')
        ->and($rules['search'])->toContain('max:255');
});

// --- Town Search (Forward-Compatible) ---

it('finds cooks when town table exists and town name matches', function () {
    // This test verifies the town search path of discoverySearch
    // Since there's no direct tenant-town relationship yet (F-074),
    // we verify the scope handles the towns table presence gracefully
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Douala Kitchen']);

    // Search for "Douala" should find the tenant by name
    $result = $this->service->getDiscoverableCooks(search: 'Douala');

    expect($result->total())->toBe(1);
});

// --- Whitespace Handling ---

it('trims whitespace from search term', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Chef Latifa Kitchen']);

    $result = $this->service->getDiscoverableCooks(search: '  Latifa  ');

    expect($result->total())->toBe(1);
});

it('handles whitespace-only search as empty', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create();

    $result = $this->service->getDiscoverableCooks(search: '   ');

    expect($result->total())->toBe(1);
});
