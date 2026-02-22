<?php

use App\Http\Requests\DiscoveryRequest;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\DiscoveryService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new DiscoveryService;
    (new RoleAndPermissionSeeder)->run();
});

// --- BR-100: Sort options validation ---

it('validates all four sort options in DiscoveryRequest rules', function () {
    $request = new DiscoveryRequest;
    $rules = $request->rules();

    // The Rule::in constraint should include all 4 values
    $ruleString = collect($rules['sort'])->map(fn ($r) => (string) $r)->implode(',');
    expect($ruleString)->toContain('popularity')
        ->and($ruleString)->toContain('rating')
        ->and($ruleString)->toContain('newest')
        ->and($ruleString)->toContain('name');
});

// --- BR-101: Default sort is popularity ---

it('sorts by popularity (completed order count) descending', function () {
    $cook = $this->createUserWithRole('cook');

    $tenantLow = Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Low Orders']);
    $tenantHigh = Tenant::factory()->withCook($cook->id)->create(['name_en' => 'High Orders']);

    // 5 completed orders for tenantHigh
    Order::factory()->count(5)->for($tenantHigh)->completed()->create();
    // 1 completed order for tenantLow
    Order::factory()->for($tenantLow)->completed()->create();

    $result = $this->service->getDiscoverableCooks(sort: 'popularity');

    expect($result->items()[0]->id)->toBe($tenantHigh->id)
        ->and($result->items()[1]->id)->toBe($tenantLow->id);
});

it('places cooks with 0 completed orders last in popularity sort, secondary sort newest first', function () {
    $cook = $this->createUserWithRole('cook');

    $tenantNoOrders = Tenant::factory()->withCook($cook->id)->create(['created_at' => now()->subDays(3)]);
    $tenantWithOrders = Tenant::factory()->withCook($cook->id)->create(['created_at' => now()->subDays(1)]);

    Order::factory()->for($tenantWithOrders)->completed()->create();

    $result = $this->service->getDiscoverableCooks(sort: 'popularity');

    expect($result->items()[0]->id)->toBe($tenantWithOrders->id)
        ->and($result->items()[1]->id)->toBe($tenantNoOrders->id);
});

it('uses popularity sort when sort param is null (BR-101 default)', function () {
    $cook = $this->createUserWithRole('cook');

    $tenantOlder = Tenant::factory()->withCook($cook->id)->create(['created_at' => now()->subDays(5)]);
    $tenantNewer = Tenant::factory()->withCook($cook->id)->create(['created_at' => now()]);

    // Older tenant has more completed orders — popularity wins over recency
    Order::factory()->count(10)->for($tenantOlder)->completed()->create();

    $result = $this->service->getDiscoverableCooks(sort: null);

    expect($result->items()[0]->id)->toBe($tenantOlder->id);
});

it('does not count non-completed orders toward popularity', function () {
    $cook = $this->createUserWithRole('cook');

    $tenantPaidOrders = Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Paid Orders']);
    $tenantCompletedOrders = Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Completed Orders']);

    // Lots of paid orders but zero completed
    Order::factory()->count(10)->for($tenantPaidOrders)->paid()->create();
    // Just 1 completed order
    Order::factory()->for($tenantCompletedOrders)->completed()->create();

    $result = $this->service->getDiscoverableCooks(sort: 'popularity');

    // tenantCompletedOrders wins (1 completed vs 0 completed)
    expect($result->items()[0]->id)->toBe($tenantCompletedOrders->id);
});

// --- BR-102: Sort by rating ---

it('sorts by average star rating descending', function () {
    $cook = $this->createUserWithRole('cook');
    $client = $this->createUserWithRole('client');

    $tenantLowRating = Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Low Rating']);
    $tenantHighRating = Tenant::factory()->withCook($cook->id)->create(['name_en' => 'High Rating']);

    // 5-star rating for high
    $orderHigh = Order::factory()->for($tenantHighRating)->completed()->create(['client_id' => $client->id]);
    DB::table('ratings')->insert([
        'order_id' => $orderHigh->id,
        'user_id' => $client->id,
        'tenant_id' => $tenantHighRating->id,
        'stars' => 5,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 2-star rating for low
    $orderLow = Order::factory()->for($tenantLowRating)->completed()->create(['client_id' => $client->id]);
    DB::table('ratings')->insert([
        'order_id' => $orderLow->id,
        'user_id' => $client->id,
        'tenant_id' => $tenantLowRating->id,
        'stars' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = $this->service->getDiscoverableCooks(sort: 'rating');

    expect($result->items()[0]->id)->toBe($tenantHighRating->id)
        ->and($result->items()[1]->id)->toBe($tenantLowRating->id);
});

it('places cooks with no ratings last in rating sort (BR-102 NULLS LAST)', function () {
    $cook = $this->createUserWithRole('cook');
    $client = $this->createUserWithRole('client');

    $tenantNoRating = Tenant::factory()->withCook($cook->id)->create(['name_en' => 'No Rating']);
    $tenantWithRating = Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Has Rating']);

    $order = Order::factory()->for($tenantWithRating)->completed()->create(['client_id' => $client->id]);
    DB::table('ratings')->insert([
        'order_id' => $order->id,
        'user_id' => $client->id,
        'tenant_id' => $tenantWithRating->id,
        'stars' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = $this->service->getDiscoverableCooks(sort: 'rating');

    // Rated cook first, unrated last
    expect($result->items()[0]->id)->toBe($tenantWithRating->id)
        ->and($result->items()[1]->id)->toBe($tenantNoRating->id);
});

it('breaks ties in rating sort by popularity (secondary sort)', function () {
    $cook = $this->createUserWithRole('cook');
    $client = $this->createUserWithRole('client');

    $tenantFewOrders = Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Few Orders']);
    $tenantManyOrders = Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Many Orders']);

    // Both have same 4-star rating
    foreach ([$tenantFewOrders, $tenantManyOrders] as $tenant) {
        $order = Order::factory()->for($tenant)->completed()->create(['client_id' => $client->id]);
        DB::table('ratings')->insert([
            'order_id' => $order->id,
            'user_id' => $client->id,
            'tenant_id' => $tenant->id,
            'stars' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // tenantManyOrders has 5 more completed orders
    Order::factory()->count(5)->for($tenantManyOrders)->completed()->create();

    $result = $this->service->getDiscoverableCooks(sort: 'rating');

    // Both have 4 stars, but tenantManyOrders has more orders → comes first
    expect($result->items()[0]->id)->toBe($tenantManyOrders->id);
});

// --- BR-103: Sort by newest ---

it('sorts by newest tenant creation date first', function () {
    $cook = $this->createUserWithRole('cook');

    $older = Tenant::factory()->withCook($cook->id)->create(['created_at' => now()->subDays(10)]);
    $newer = Tenant::factory()->withCook($cook->id)->create(['created_at' => now()]);
    $middle = Tenant::factory()->withCook($cook->id)->create(['created_at' => now()->subDays(5)]);

    $result = $this->service->getDiscoverableCooks(sort: 'newest');

    expect($result->items()[0]->id)->toBe($newer->id)
        ->and($result->items()[1]->id)->toBe($middle->id)
        ->and($result->items()[2]->id)->toBe($older->id);
});

// --- BR-104: Sort alphabetically ---

it('sorts alphabetically by name in en locale A-Z', function () {
    $cook = $this->createUserWithRole('cook');

    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Zara Kitchen', 'name_fr' => 'Cuisine Zara']);
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Alpha Kitchen', 'name_fr' => 'Cuisine Alpha']);
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Mango Bites', 'name_fr' => 'Bouchées Mango']);

    app()->setLocale('en');
    $result = $this->service->getDiscoverableCooks(sort: 'name');

    expect($result->items()[0]->name_en)->toBe('Alpha Kitchen')
        ->and($result->items()[1]->name_en)->toBe('Mango Bites')
        ->and($result->items()[2]->name_en)->toBe('Zara Kitchen');
});

it('sorts alphabetically using French name when locale is fr', function () {
    $cook = $this->createUserWithRole('cook');

    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'A English', 'name_fr' => 'Z Français']);
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Z English', 'name_fr' => 'A Français']);

    app()->setLocale('fr');
    $result = $this->service->getDiscoverableCooks(sort: 'name');

    expect($result->items()[0]->name_fr)->toBe('A Français')
        ->and($result->items()[1]->name_fr)->toBe('Z Français');
});

it('handles case-insensitive alphabetical sort', function () {
    $cook = $this->createUserWithRole('cook');

    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'zara kitchen']);
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Alpha Kitchen']);

    app()->setLocale('en');
    $result = $this->service->getDiscoverableCooks(sort: 'name');

    // 'alpha kitchen' < 'zara kitchen' case-insensitively
    expect(mb_strtolower($result->items()[0]->name_en))->toBe('alpha kitchen')
        ->and(mb_strtolower($result->items()[1]->name_en))->toBe('zara kitchen');
});

// --- BR-105/BR-106: Sort works with search and filters combined ---

it('preserves search results when sort changes (sort does not reset search)', function () {
    $cook = $this->createUserWithRole('cook');

    $tenantA = Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Amara Chicken']);
    $tenantB = Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Another Chicken Place']);
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Beignet House']);

    // tenantB has more popularity but tenantA should still come first alphabetically
    Order::factory()->count(5)->for($tenantB)->completed()->create();

    app()->setLocale('en');
    $result = $this->service->getDiscoverableCooks(search: 'chicken', sort: 'name');

    // Only 2 results (beignet excluded), alphabetical order
    expect($result->total())->toBe(2)
        ->and($result->items()[0]->name_en)->toBe('Amara Chicken')
        ->and($result->items()[1]->name_en)->toBe('Another Chicken Place');
});

// --- Edge case: 1 cook ---

it('returns single cook with any sort option without error', function () {
    $cook = $this->createUserWithRole('cook');
    Tenant::factory()->withCook($cook->id)->create(['name_en' => 'Solo Cook']);

    foreach (['popularity', 'rating', 'newest', 'name', null] as $sort) {
        $result = $this->service->getDiscoverableCooks(sort: $sort);
        expect($result->total())->toBe(1);
    }
});

// --- Controller default sort ---

it('controller view data uses popularity as default sort value', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertViewHas('sort', 'popularity');
});
