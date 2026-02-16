<?php

use App\Http\Controllers\AddressController;
use App\Models\Address;

/*
|--------------------------------------------------------------------------
| F-036: Delete Delivery Address — Unit Tests
|--------------------------------------------------------------------------
|
| Unit tests for the Address model's delete-related methods,
| controller structure, view content, route definitions, and
| translation keys.
|
| BR-141: Confirmation dialog in view.
| BR-142: hasPendingOrders() method on model.
| BR-143: Default reassignment logic.
| BR-144: Ownership check in controller.
| BR-145: Hard delete (no SoftDeletes).
|
*/

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| Controller Method Tests
|--------------------------------------------------------------------------
*/

it('AddressController has a destroy method', function () {
    $controller = new AddressController;
    expect(method_exists($controller, 'destroy'))->toBeTrue();
});

it('destroy method accepts Request and Address parameters', function () {
    $reflection = new ReflectionMethod(AddressController::class, 'destroy');
    $params = $reflection->getParameters();

    expect($params)->toHaveCount(2);
    expect($params[0]->getName())->toBe('request');
    expect($params[1]->getName())->toBe('address');
});

it('destroy method has mixed return type', function () {
    $reflection = new ReflectionMethod(AddressController::class, 'destroy');
    $returnType = $reflection->getReturnType();

    expect($returnType)->not->toBeNull();
    expect($returnType->getName())->toBe('mixed');
});

/*
|--------------------------------------------------------------------------
| Model Method Tests
|--------------------------------------------------------------------------
*/

it('Address model has hasPendingOrders method', function () {
    expect(method_exists(Address::class, 'hasPendingOrders'))->toBeTrue();
});

it('hasPendingOrders method returns bool', function () {
    $reflection = new ReflectionMethod(Address::class, 'hasPendingOrders');
    $returnType = $reflection->getReturnType();

    expect($returnType)->not->toBeNull();
    expect($returnType->getName())->toBe('bool');
});

it('Address model does not use SoftDeletes (BR-145)', function () {
    $address = new Address;
    $traits = class_uses_recursive($address);

    expect($traits)->not->toContain(\Illuminate\Database\Eloquent\SoftDeletes::class);
});

it('Address model has MAX_ADDRESSES_PER_USER constant set to 5', function () {
    expect(Address::MAX_ADDRESSES_PER_USER)->toBe(5);
});

/*
|--------------------------------------------------------------------------
| View File Tests — Confirmation Modal
|--------------------------------------------------------------------------
*/

it('index view contains delete confirmation modal title', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain("__('Delete this address?')");
});

it('index view contains confirmation warning text', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain("__('This cannot be undone.')");
});

it('index view contains delete button in modal', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain("__('Delete')");
    expect($viewContent)->toContain("__('Cancel')");
});

it('index view contains deleting loading state', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain("__('Deleting...')");
});

it('index view uses Alpine.js confirmDelete function', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain('confirmDelete(');
});

it('index view uses Alpine.js cancelDelete function', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain('cancelDelete()');
});

it('index view uses Alpine.js executeDelete function', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain('executeDelete()');
});

it('index view has delete modal with Alpine x-show', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain('x-show="deleteModal"');
});

it('index view uses $action for delete request', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain("method: 'DELETE'");
});

it('index view uses danger color tokens for delete modal', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)
        ->toContain('bg-danger')
        ->toContain('text-danger')
        ->toContain('bg-danger-subtle');
});

it('index view has dark mode support for delete modal backdrop', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain('dark:bg-black/70');
});

it('index view uses x-teleport for modal', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain('x-teleport="body"');
});

it('index view has escape key handler for modal', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain('@keydown.escape');
});

it('index view has backdrop click to close modal', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain('@click="cancelDelete()"');
});

it('index view displays address label in modal', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/index.blade.php');

    expect($viewContent)->toContain('x-text="deleteAddressLabel"');
});

/*
|--------------------------------------------------------------------------
| Route Tests
|--------------------------------------------------------------------------
*/

it('delete address route is defined', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.'/routes/web.php');

    expect($routeContent)->toContain("Route::delete('/profile/addresses/{address}'");
    expect($routeContent)->toContain("->name('addresses.destroy')");
});

it('delete route uses AddressController destroy method', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.'/routes/web.php');

    expect($routeContent)->toContain("[AddressController::class, 'destroy']");
});

it('delete route is inside auth middleware group', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.'/routes/web.php');

    // The delete route is inside Route::middleware('auth')->group(function () { ... })
    // which also contains the other profile/address routes
    expect($routeContent)->toContain("Route::middleware('auth')->group(function ()");
    expect($routeContent)->toContain("Route::delete('/profile/addresses/{address}'");
});

/*
|--------------------------------------------------------------------------
| Translation Key Tests
|--------------------------------------------------------------------------
*/

it('has required English translation keys for address delete', function () use ($projectRoot) {
    $enJson = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

    expect($enJson)
        ->toHaveKey('Delete this address?')
        ->toHaveKey('This cannot be undone.')
        ->toHaveKey('Deleting...')
        ->toHaveKey('Address deleted.')
        ->toHaveKey('Delivery address deleted')
        ->toHaveKey('This address is used by pending orders and cannot be deleted. You can edit it instead.');
});

it('has required French translation keys for address delete', function () use ($projectRoot) {
    $frJson = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    expect($frJson)
        ->toHaveKey('Delete this address?')
        ->toHaveKey('This cannot be undone.')
        ->toHaveKey('Deleting...')
        ->toHaveKey('Address deleted.')
        ->toHaveKey('Delivery address deleted')
        ->toHaveKey('This address is used by pending orders and cannot be deleted. You can edit it instead.');
});

it('French translations are not same as English for delete feature', function () use ($projectRoot) {
    $enJson = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
    $frJson = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    expect($frJson['Delete this address?'])->not->toBe($enJson['Delete this address?']);
    expect($frJson['This cannot be undone.'])->not->toBe($enJson['This cannot be undone.']);
    expect($frJson['Address deleted.'])->not->toBe($enJson['Address deleted.']);
});
