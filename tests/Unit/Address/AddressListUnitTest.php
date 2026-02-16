<?php

use App\Http\Controllers\AddressController;
use App\Models\Address;

/*
|--------------------------------------------------------------------------
| F-034: Delivery Address List — Unit Tests
|--------------------------------------------------------------------------
|
| Unit tests for the address list controller methods, view files,
| route definitions, and translation keys.
|
*/

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| Controller Method Tests
|--------------------------------------------------------------------------
*/

it('AddressController has an index method', function () {
    $controller = new AddressController;
    expect(method_exists($controller, 'index'))->toBeTrue();
});

it('AddressController has a setDefault method', function () {
    $controller = new AddressController;
    expect(method_exists($controller, 'setDefault'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| View File Tests
|--------------------------------------------------------------------------
*/

it('index blade view exists', function () use ($projectRoot) {
    expect(file_exists($projectRoot.'/resources/views/profile/addresses/index.blade.php'))->toBeTrue();
});

it('index view contains delivery addresses heading', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain("__('Delivery Addresses')");
});

it('index view contains empty state message', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain("__('You have no saved addresses.')");
});

it('index view contains add your first address button', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain("__('Add Your First Address')");
});

it('index view contains default badge', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain("__('Default')");
});

it('index view contains set as default button', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain("__('Set as Default')");
});

it('index view contains edit link', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain("__('Edit')");
});

it('index view contains delete link', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain("__('Delete')");
});

it('index view uses localized town and quarter names', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain("localized('name')");
});

it('index view extends correct layout with tenant check', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain("@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')");
});

it('index view has dark mode support', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain('dark:');
});

it('index view uses semantic color tokens', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)
        ->toContain('bg-surface')
        ->toContain('text-on-surface')
        ->toContain('border-outline');
});

it('index view uses gale navigation', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain('x-navigate');
});

it('index view uses gale action for set default', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain('$action(');
});

it('index view uses fetching for loading state', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain('$fetching()');
});

/*
|--------------------------------------------------------------------------
| Route Tests
|--------------------------------------------------------------------------
*/

it('address list route is defined', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.'/routes/web.php');

    expect($routeContent)->toContain("Route::get('/profile/addresses'");
    expect($routeContent)->toContain("->name('addresses.index')");
});

it('set default route is defined', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.'/routes/web.php');

    expect($routeContent)->toContain("Route::post('/profile/addresses/{address}/set-default'");
    expect($routeContent)->toContain("->name('addresses.set-default')");
});

/*
|--------------------------------------------------------------------------
| Translation Key Tests
|--------------------------------------------------------------------------
*/

it('has required English translation keys for address list', function () use ($projectRoot) {
    $enJson = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

    expect($enJson)
        ->toHaveKey('Delivery Addresses')
        ->toHaveKey('Manage your saved delivery addresses.')
        ->toHaveKey('Default')
        ->toHaveKey('Set as Default')
        ->toHaveKey('Edit')
        ->toHaveKey('Delete')
        ->toHaveKey('Add Address')
        ->toHaveKey('Add Your First Address')
        ->toHaveKey('You have no saved addresses.')
        ->toHaveKey('Default address updated.')
        ->toHaveKey('This address is already your default.')
        ->toHaveKey(':count of :max addresses');
});

it('has required French translation keys for address list', function () use ($projectRoot) {
    $frJson = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    expect($frJson)
        ->toHaveKey('Delivery Addresses')
        ->toHaveKey('Manage your saved delivery addresses.')
        ->toHaveKey('Default')
        ->toHaveKey('Set as Default')
        ->toHaveKey('Edit')
        ->toHaveKey('Delete')
        ->toHaveKey('Add Address')
        ->toHaveKey('Add Your First Address')
        ->toHaveKey('You have no saved addresses.')
        ->toHaveKey('Default address updated.')
        ->toHaveKey('This address is already your default.')
        ->toHaveKey(':count of :max addresses');
});

/*
|--------------------------------------------------------------------------
| Model Constant Tests
|--------------------------------------------------------------------------
*/

it('address model has MAX_ADDRESSES_PER_USER constant', function () {
    expect(Address::MAX_ADDRESSES_PER_USER)->toBe(5);
});
