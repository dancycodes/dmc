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

// --- Constants ---

it('has a default per page of 12', function () {
    expect(DiscoveryService::PER_PAGE)->toBe(12);
});

// --- getDiscoverableCooks ---

it('returns empty results when no tenants exist', function () {
    $result = $this->service->getDiscoverableCooks();

    expect($result->total())->toBe(0)
        ->and($result->items())->toBeEmpty();
});

it('returns active tenants with assigned cooks', function () {
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();

    $result = $this->service->getDiscoverableCooks();

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($tenant->id);
});

it('excludes inactive tenants', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->inactive()->withCook($cook->id)->create();

    $result = $this->service->getDiscoverableCooks();

    expect($result->total())->toBe(0);
});

it('excludes tenants without assigned cook', function () {
    Tenant::factory()->create(['cook_id' => null]);

    $result = $this->service->getDiscoverableCooks();

    expect($result->total())->toBe(0);
});

it('eager loads cook relationship', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create();

    $result = $this->service->getDiscoverableCooks();

    expect($result->items()[0]->relationLoaded('cook'))->toBeTrue()
        ->and($result->items()[0]->cook->id)->toBe($cook->id);
});

it('paginates results with default per page', function () {
    $cook1 = $this->createUserWithRole('cook');
    $cook2 = $this->createUserWithRole('cook');

    // Create 15 active tenants with cooks
    for ($i = 0; $i < 15; $i++) {
        $cookForTenant = ($i % 2 === 0) ? $cook1 : $cook2;
        Tenant::factory()->withCook($cookForTenant->id)->create();
    }

    $result = $this->service->getDiscoverableCooks();

    expect($result->perPage())->toBe(12)
        ->and($result->total())->toBe(15)
        ->and($result->lastPage())->toBe(2)
        ->and($result->count())->toBe(12);
});

it('supports custom per page', function () {
    $cook = $this->createUserWithRole('cook');
    for ($i = 0; $i < 5; $i++) {
        Tenant::factory()->withCook($cook->id)->create();
    }

    $result = $this->service->getDiscoverableCooks(perPage: 3);

    expect($result->perPage())->toBe(3)
        ->and($result->count())->toBe(3);
});

it('filters by search term on tenant name', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Chef Amara Kitchen', 'name_fr' => 'Cuisine de Chef Amara']);
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Beignet House', 'name_fr' => 'Maison du Beignet']);

    $result = $this->service->getDiscoverableCooks(search: 'amara');

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->name_en)->toBe('Chef Amara Kitchen');
});

it('sorts by name ascending', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Zara Kitchen']);
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Alpha Kitchen']);

    $result = $this->service->getDiscoverableCooks(sort: 'name', direction: 'asc');

    expect($result->items()[0]->name_en)->toBe('Alpha Kitchen')
        ->and($result->items()[1]->name_en)->toBe('Zara Kitchen');
});

it('sorts by newest first by default', function () {
    $cook = $this->createUserWithRole('cook');
    $older = Tenant::factory()->withCook($cook->id)->create(['created_at' => now()->subDays(5)]);
    $newer = Tenant::factory()->withCook($cook->id)->create(['created_at' => now()]);

    $result = $this->service->getDiscoverableCooks();

    expect($result->items()[0]->id)->toBe($newer->id)
        ->and($result->items()[1]->id)->toBe($older->id);
});

// --- getDiscoverableCookCount ---

it('counts only active tenants with assigned cooks', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(); // active with cook
    Tenant::factory()->inactive()->withCook($cook->id)->create(); // inactive
    Tenant::factory()->create(['cook_id' => null]); // no cook

    $count = $this->service->getDiscoverableCookCount();

    expect($count)->toBe(1);
});

it('returns zero when no discoverable cooks exist', function () {
    $count = $this->service->getDiscoverableCookCount();

    expect($count)->toBe(0);
});

// --- DiscoveryRequest ---

it('allows public access without authentication', function () {
    $request = new \App\Http\Requests\DiscoveryRequest;

    expect($request->authorize())->toBeTrue();
});

it('validates search field as optional string', function () {
    $request = new \App\Http\Requests\DiscoveryRequest;
    $rules = $request->rules();

    expect($rules['search'])->toContain('nullable')
        ->and($rules['search'])->toContain('string')
        ->and($rules['search'])->toContain('max:255');
});

it('validates sort field with allowed values', function () {
    $request = new \App\Http\Requests\DiscoveryRequest;
    $rules = $request->rules();

    expect($rules['sort'])->toContain('nullable')
        ->and($rules['sort'])->toContain('string');
});

it('validates direction field as asc or desc', function () {
    $request = new \App\Http\Requests\DiscoveryRequest;
    $rules = $request->rules();

    expect($rules['direction'])->toContain('nullable')
        ->and($rules['direction'])->toContain('string');
});

it('validates page field as positive integer', function () {
    $request = new \App\Http\Requests\DiscoveryRequest;
    $rules = $request->rules();

    expect($rules['page'])->toContain('nullable')
        ->and($rules['page'])->toContain('integer')
        ->and($rules['page'])->toContain('min:1');
});
