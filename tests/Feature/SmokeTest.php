<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Application Smoke Tests
|--------------------------------------------------------------------------
|
| These tests verify the fundamental health of the DancyMeals application.
| They confirm the app boots, the home page loads, the database is connected,
| and the testing infrastructure (Pest, factories, isolation) works correctly.
|
*/

test('the application boots and home page returns HTTP 200', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('the test database connection is active', function () {
    $pdo = DB::connection()->getPdo();

    expect($pdo)->toBeInstanceOf(PDO::class);
    expect($pdo->getAttribute(PDO::ATTR_DRIVER_NAME))->toBe('pgsql');
});

test('the test database is the correct dedicated database', function () {
    $databaseName = DB::connection()->getDatabaseName();

    expect($databaseName)->toBe('dancymeals_test');
});

test('core application tables exist in test database', function () {
    expect(Schema::hasTable('users'))->toBeTrue();
    expect(Schema::hasTable('tenants'))->toBeTrue();
    expect(Schema::hasTable('roles'))->toBeTrue();
    expect(Schema::hasTable('permissions'))->toBeTrue();
});

test('Pest framework is the active test runner', function () {
    expect(function_exists('test'))->toBeTrue();
    expect(function_exists('expect'))->toBeTrue();
    expect(function_exists('it'))->toBeTrue();
});

test('database transactions provide test isolation', function () {
    $uniqueEmail = 'smoke-isolation-'.uniqid().'@test.com';

    App\Models\User::factory()->create(['email' => $uniqueEmail]);

    expect(App\Models\User::where('email', $uniqueEmail)->exists())->toBeTrue();

    // Note: RefreshDatabase rolls back after this test, so the next test
    // will not see this user. This is verified by the companion test below.
});

test('previous test data does not leak into this test', function () {
    $users = App\Models\User::where('email', 'like', 'smoke-isolation-%@test.com')->count();

    expect($users)->toBe(0);
});

test('user factory creates valid models', function () {
    $user = App\Models\User::factory()->create();

    expect($user)->toBeInstanceOf(App\Models\User::class);
    expect($user->id)->toBeGreaterThan(0);
    expect($user->name)->toBeString()->not->toBeEmpty();
    expect($user->email)->toContain('@');
});

test('tenant factory creates valid models', function () {
    $tenant = App\Models\Tenant::factory()->create();

    expect($tenant)->toBeInstanceOf(App\Models\Tenant::class);
    expect($tenant->id)->toBeGreaterThan(0);
    expect($tenant->slug)->toBeString()->not->toBeEmpty();
});

test('test environment is correctly configured', function () {
    expect(app()->environment())->toBe('testing');
    expect(config('app.name'))->toBe('DancyMeals');
    expect(config('database.default'))->toBe('pgsql');
    expect(config('cache.default'))->toBe('array');
    expect(config('session.driver'))->toBe('array');
    expect(config('mail.default'))->toBe('array');
    expect(config('queue.default'))->toBe('sync');
});

test('base test case helpers are available', function () {
    expect(method_exists($this, 'createUserWithRole'))->toBeTrue();
    expect(method_exists($this, 'actingAsRole'))->toBeTrue();
    expect(method_exists($this, 'createTenantWithCook'))->toBeTrue();
    expect(method_exists($this, 'seedRolesAndPermissions'))->toBeTrue();
});

test('createUserWithRole helper creates user with correct role', function () {
    $admin = $this->createUserWithRole('admin');

    expect($admin)->toBeInstanceOf(App\Models\User::class);
    expect($admin->hasRole('admin'))->toBeTrue();
});

test('actingAsRole helper authenticates user with role', function () {
    $cook = $this->actingAsRole('cook');

    $this->assertAuthenticatedAs($cook);
    expect($cook->hasRole('cook'))->toBeTrue();
});
