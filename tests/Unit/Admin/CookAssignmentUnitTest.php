<?php

/**
 * F-049: Cook Account Assignment to Tenant — Unit Tests
 *
 * Tests the CookAssignmentService business logic:
 * - User search
 * - Cook assignment
 * - Cook reassignment
 * - Role management
 */

use App\Models\Tenant;
use App\Models\User;
use App\Services\CookAssignmentService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
});

/*
|--------------------------------------------------------------------------
| Search Tests
|--------------------------------------------------------------------------
*/

test('searchUsers returns empty collection for short search terms', function () {
    $service = new CookAssignmentService;

    expect($service->searchUsers(''))->toBeEmpty();
    expect($service->searchUsers('a'))->toBeEmpty();
});

test('searchUsers finds users by name', function () {
    User::factory()->create(['name' => 'Amara Njoh']);
    User::factory()->create(['name' => 'Brice Tamo']);

    $service = new CookAssignmentService;
    $results = $service->searchUsers('Amara');

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('Amara Njoh');
});

test('searchUsers finds users by email', function () {
    User::factory()->create(['email' => 'amara@example.com', 'name' => 'Amara Njoh']);
    User::factory()->create(['email' => 'brice@example.com', 'name' => 'Brice Tamo']);

    $service = new CookAssignmentService;
    $results = $service->searchUsers('amara@example');

    expect($results)->toHaveCount(1);
    expect($results->first()->email)->toBe('amara@example.com');
});

test('searchUsers is case insensitive', function () {
    User::factory()->create(['name' => 'Amara Njoh']);

    $service = new CookAssignmentService;

    expect($service->searchUsers('amara'))->toHaveCount(1);
    expect($service->searchUsers('AMARA'))->toHaveCount(1);
    expect($service->searchUsers('aMaRa'))->toHaveCount(1);
});

test('searchUsers limits results to specified count', function () {
    User::factory()->count(15)->create(['name' => 'Test User']);

    $service = new CookAssignmentService;
    $results = $service->searchUsers('Test', 5);

    expect($results)->toHaveCount(5);
});

test('searchUsers returns no results for non-matching term', function () {
    User::factory()->create(['name' => 'Amara Njoh']);

    $service = new CookAssignmentService;
    $results = $service->searchUsers('xyz123');

    expect($results)->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| getUserCookTenants Tests
|--------------------------------------------------------------------------
*/

test('getUserCookTenants returns tenants where user is cook', function () {
    $user = User::factory()->create();
    $user->assignRole('cook');

    Tenant::factory()->withSlug('kitchen-one')->withCook($user->id)->create();
    Tenant::factory()->withSlug('kitchen-two')->withCook($user->id)->create();
    Tenant::factory()->withSlug('kitchen-three')->create();

    $service = new CookAssignmentService;
    $tenants = $service->getUserCookTenants($user);

    expect($tenants)->toHaveCount(2);
});

test('getUserCookTenants returns empty for user with no cook assignments', function () {
    $user = User::factory()->create();

    $service = new CookAssignmentService;
    $tenants = $service->getUserCookTenants($user);

    expect($tenants)->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| assignCook Tests
|--------------------------------------------------------------------------
*/

test('assignCook assigns a user as cook to a tenant with no previous cook', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withSlug('new-kitchen')->create();

    $service = new CookAssignmentService;
    $result = $service->assignCook($tenant, $user);

    $tenant->refresh();

    expect($tenant->cook_id)->toBe($user->id);
    expect($user->hasRole('cook'))->toBeTrue();
    expect($result['previous_cook'])->toBeNull();
    expect($result['new_cook']->id)->toBe($user->id);
});

test('assignCook reassigns cook and removes role from previous cook (BR-084)', function () {
    $oldCook = User::factory()->create();
    $oldCook->assignRole('cook');
    $newCook = User::factory()->create();

    $tenant = Tenant::factory()->withSlug('reassign-kitchen')->withCook($oldCook->id)->create();

    $service = new CookAssignmentService;
    $result = $service->assignCook($tenant, $newCook);

    $tenant->refresh();
    $oldCook->refresh();

    expect($tenant->cook_id)->toBe($newCook->id);
    expect($newCook->hasRole('cook'))->toBeTrue();
    // Old cook should lose the role since they are not cook for any other tenant
    expect($oldCook->hasRole('cook'))->toBeFalse();
    expect($result['previous_cook']->id)->toBe($oldCook->id);
});

test('assignCook keeps cook role if old cook has other tenant assignments (BR-083)', function () {
    $oldCook = User::factory()->create();
    $oldCook->assignRole('cook');

    $tenant1 = Tenant::factory()->withSlug('kitchen-alpha')->withCook($oldCook->id)->create();
    $tenant2 = Tenant::factory()->withSlug('kitchen-beta')->withCook($oldCook->id)->create();

    $newCook = User::factory()->create();

    $service = new CookAssignmentService;
    $service->assignCook($tenant1, $newCook);

    $oldCook->refresh();

    // Old cook should keep cook role because they still own kitchen-beta
    expect($oldCook->hasRole('cook'))->toBeTrue();
});

test('assignCook does not duplicate role if user already has cook role', function () {
    $user = User::factory()->create();
    $user->assignRole('cook');

    $tenant = Tenant::factory()->withSlug('test-kitchen')->create();

    $service = new CookAssignmentService;
    $service->assignCook($tenant, $user);

    // Should still have exactly one cook role assignment
    expect($user->roles()->where('name', 'cook')->count())->toBe(1);
});

test('assignCook handles assigning same user again (no-op for role)', function () {
    $user = User::factory()->create();
    $user->assignRole('cook');

    $tenant = Tenant::factory()->withSlug('same-cook-kitchen')->withCook($user->id)->create();

    $service = new CookAssignmentService;
    $result = $service->assignCook($tenant, $user);

    $tenant->refresh();

    expect($tenant->cook_id)->toBe($user->id);
    expect($user->hasRole('cook'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Tenant Model Tests
|--------------------------------------------------------------------------
*/

test('tenant has cook relationship', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withSlug('model-test')->withCook($user->id)->create();

    expect($tenant->cook)->toBeInstanceOf(User::class);
    expect($tenant->cook->id)->toBe($user->id);
});

test('tenant cook_id is nullable', function () {
    $tenant = Tenant::factory()->withSlug('no-cook-test')->create();

    expect($tenant->cook_id)->toBeNull();
    expect($tenant->cook)->toBeNull();
});

test('tenant cook_id is in fillable', function () {
    $tenant = new Tenant;

    expect($tenant->getFillable())->toContain('cook_id');
});
