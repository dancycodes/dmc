<?php

use App\Http\Controllers\AddressController;
use App\Http\Requests\Address\UpdateAddressRequest;

/*
|--------------------------------------------------------------------------
| F-035: Edit Delivery Address — Unit Tests
|--------------------------------------------------------------------------
|
| Unit tests for the edit address controller methods, form request,
| view files, route definitions, and translation keys.
|
| BR-135: Same validation rules as add form.
| BR-136: Form pre-populated with current values.
| BR-137: Label uniqueness excludes current address.
| BR-138: Quarter dropdown populated for current town.
| BR-139: Users can only edit their own addresses.
| BR-140: Save via Gale without page reload.
|
*/

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| Controller Method Tests
|--------------------------------------------------------------------------
*/

it('AddressController has an edit method', function () {
    $controller = new AddressController;
    expect(method_exists($controller, 'edit'))->toBeTrue();
});

it('AddressController has an update method', function () {
    $controller = new AddressController;
    expect(method_exists($controller, 'update'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Form Request Tests
|--------------------------------------------------------------------------
*/

it('UpdateAddressRequest class exists', function () {
    expect(class_exists(UpdateAddressRequest::class))->toBeTrue();
});

it('UpdateAddressRequest extends FormRequest', function () {
    $request = new UpdateAddressRequest;
    expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

it('UpdateAddressRequest has validation rules for all address fields', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/app/Http/Requests/Address/UpdateAddressRequest.php');

    expect($content)
        ->toContain("'label'")
        ->toContain("'town_id'")
        ->toContain("'quarter_id'")
        ->toContain("'neighbourhood'")
        ->toContain("'additional_directions'")
        ->toContain("'latitude'")
        ->toContain("'longitude'");
});

it('UpdateAddressRequest uses ignore rule for label uniqueness (BR-137)', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/app/Http/Requests/Address/UpdateAddressRequest.php');

    expect($content)->toContain('->ignore($addressId)');
});

it('UpdateAddressRequest has custom validation messages', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/app/Http/Requests/Address/UpdateAddressRequest.php');

    expect($content)
        ->toContain("__('Address label is required.')")
        ->toContain("__('You already have an address with this label.')")
        ->toContain("__('Town is required.')")
        ->toContain("__('Quarter is required.')");
});

/*
|--------------------------------------------------------------------------
| View File Tests
|--------------------------------------------------------------------------
*/

it('edit blade view exists', function () use ($projectRoot) {
    expect(file_exists($projectRoot.'/resources/views/profile/addresses/edit.blade.php'))->toBeTrue();
});

it('edit view contains edit delivery address heading', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/edit.blade.php');

    expect($viewContent)->toContain("__('Edit Delivery Address')");
});

it('edit view contains update address button', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/edit.blade.php');

    expect($viewContent)->toContain("__('Update Address')");
});

it('edit view contains cancel button linking to addresses list', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/edit.blade.php');

    expect($viewContent)->toContain("__('Cancel')");
    expect($viewContent)->toContain("url('/profile/addresses')");
});

it('edit view has pre-populated Alpine data using Js::from (BR-136)', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/edit.blade.php');

    expect($viewContent)
        ->toContain('Js::from($address->label)')
        ->toContain('Js::from((string) $address->town_id)')
        ->toContain('Js::from((string) $address->quarter_id)')
        ->toContain('Js::from($quarters)');
});

it('edit view extends correct layout with tenant check', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/edit.blade.php');

    expect($viewContent)->toContain("@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')");
});

it('edit view has dark mode support', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/edit.blade.php');

    expect($viewContent)->toContain('dark:');
});

it('edit view uses semantic color tokens', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/edit.blade.php');

    expect($viewContent)
        ->toContain('bg-surface')
        ->toContain('text-on-surface')
        ->toContain('border-outline');
});

it('edit view uses gale navigation', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/edit.blade.php');

    expect($viewContent)->toContain('x-navigate');
});

it('edit view uses gale action for form submission (BR-140)', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/edit.blade.php');

    expect($viewContent)->toContain('$action(');
    expect($viewContent)->toContain('@submit.prevent');
});

it('edit view uses x-sync for state management', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/edit.blade.php');

    expect($viewContent)->toContain('x-sync=');
});

it('edit view uses fetching for loading state', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/edit.blade.php');

    expect($viewContent)->toContain('$fetching()');
});

it('edit view uses x-message for validation errors', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/edit.blade.php');

    expect($viewContent)
        ->toContain('x-message="label"')
        ->toContain('x-message="town_id"')
        ->toContain('x-message="quarter_id"');
});

it('edit view uses x-name attributes for form fields', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/edit.blade.php');

    expect($viewContent)
        ->toContain('x-name="label"')
        ->toContain('x-name="town_id"')
        ->toContain('x-name="quarter_id"')
        ->toContain('x-name="neighbourhood"')
        ->toContain('x-name="additional_directions"');
});

it('edit view has OpenStreetMap autocomplete for neighbourhood', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/edit.blade.php');

    expect($viewContent)->toContain('nominatim.openstreetmap.org');
    expect($viewContent)->toContain('searchNeighbourhood()');
    expect($viewContent)->toContain('selectSuggestion(suggestion)');
});

it('edit view has dynamic quarter dropdown via gale action (BR-138)', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/addresses/edit.blade.php');

    expect($viewContent)->toContain('fetchQuarters()');
    expect($viewContent)->toContain('@change="fetchQuarters()"');
});

/*
|--------------------------------------------------------------------------
| Route Tests
|--------------------------------------------------------------------------
*/

it('address edit route is defined', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.'/routes/web.php');

    expect($routeContent)->toContain("Route::get('/profile/addresses/{address}/edit'");
    expect($routeContent)->toContain("->name('addresses.edit')");
});

it('address update route is defined', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.'/routes/web.php');

    expect($routeContent)->toContain("Route::post('/profile/addresses/{address}'");
    expect($routeContent)->toContain("->name('addresses.update')");
});

/*
|--------------------------------------------------------------------------
| Translation Key Tests
|--------------------------------------------------------------------------
*/

it('has required English translation keys for address edit', function () use ($projectRoot) {
    $enJson = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

    expect($enJson)
        ->toHaveKey('Edit Delivery Address')
        ->toHaveKey('Update the details of your saved address.')
        ->toHaveKey('Back to Addresses')
        ->toHaveKey('Update Address')
        ->toHaveKey('Updating...')
        ->toHaveKey('Address updated successfully.')
        ->toHaveKey('Delivery address updated');
});

it('has required French translation keys for address edit', function () use ($projectRoot) {
    $frJson = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    expect($frJson)
        ->toHaveKey('Edit Delivery Address')
        ->toHaveKey('Update the details of your saved address.')
        ->toHaveKey('Back to Addresses')
        ->toHaveKey('Update Address')
        ->toHaveKey('Updating...')
        ->toHaveKey('Address updated successfully.')
        ->toHaveKey('Delivery address updated');
});

/*
|--------------------------------------------------------------------------
| Controller Implementation Tests
|--------------------------------------------------------------------------
*/

it('edit method returns gale view response', function () use ($projectRoot) {
    $controllerContent = file_get_contents($projectRoot.'/app/Http/Controllers/AddressController.php');

    expect($controllerContent)->toContain("gale()->view('profile.addresses.edit'");
});

it('update method returns gale redirect with toast', function () use ($projectRoot) {
    $controllerContent = file_get_contents($projectRoot.'/app/Http/Controllers/AddressController.php');

    expect($controllerContent)->toContain("__('Address updated successfully.')");
    expect($controllerContent)->toContain("gale()->redirect('/profile/addresses')");
});

it('update method logs activity', function () use ($projectRoot) {
    $controllerContent = file_get_contents($projectRoot.'/app/Http/Controllers/AddressController.php');

    expect($controllerContent)->toContain("activity('addresses')");
    expect($controllerContent)->toContain("__('Delivery address updated')");
});

it('edit method checks address ownership (BR-139)', function () use ($projectRoot) {
    $controllerContent = file_get_contents($projectRoot.'/app/Http/Controllers/AddressController.php');

    // The edit method should have ownership check
    expect($controllerContent)->toContain('$address->user_id !== $user->id');
});
