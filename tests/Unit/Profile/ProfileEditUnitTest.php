<?php

use App\Http\Controllers\ProfileController;
use App\Http\Requests\Profile\UpdateProfileRequest;

/*
|--------------------------------------------------------------------------
| F-032: Profile Basic Info Edit — Unit Tests
|--------------------------------------------------------------------------
|
| Unit tests for the profile edit feature covering: controller methods,
| form request rules, view file presence, translation keys, blade
| structure, and the UpdateProfileRequest validation configuration.
|
*/

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| Controller Tests
|--------------------------------------------------------------------------
*/

it('ProfileController has an edit method', function () {
    $controller = new ProfileController;
    expect(method_exists($controller, 'edit'))->toBeTrue();
});

it('ProfileController has an update method', function () {
    $controller = new ProfileController;
    expect(method_exists($controller, 'update'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Form Request Tests
|--------------------------------------------------------------------------
*/

it('UpdateProfileRequest class exists', function () {
    expect(class_exists(UpdateProfileRequest::class))->toBeTrue();
});

it('UpdateProfileRequest authorizes all authenticated users', function () {
    $request = new UpdateProfileRequest;
    expect($request->authorize())->toBeTrue();
});

it('UpdateProfileRequest has name validation rules', function () {
    $request = new UpdateProfileRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('name')
        ->and($rules['name'])->toContain('required')
        ->and($rules['name'])->toContain('string');
});

it('UpdateProfileRequest has phone validation rules', function () {
    $request = new UpdateProfileRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('phone')
        ->and($rules['phone'])->toContain('required')
        ->and($rules['phone'])->toContain('string');
});

it('UpdateProfileRequest has preferred_language validation rules', function () {
    $request = new UpdateProfileRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('preferred_language')
        ->and($rules['preferred_language'])->toContain('required')
        ->and($rules['preferred_language'])->toContain('string');
});

it('UpdateProfileRequest does not have email in rules', function () {
    $request = new UpdateProfileRequest;
    $rules = $request->rules();

    expect($rules)->not->toHaveKey('email');
});

it('UpdateProfileRequest has a messages method', function () {
    $request = new UpdateProfileRequest;

    expect(method_exists($request, 'messages'))->toBeTrue();
});

it('UpdateProfileRequest has the Cameroon phone regex constant', function () {
    expect(UpdateProfileRequest::CAMEROON_PHONE_REGEX)->toBeString()
        ->and(UpdateProfileRequest::CAMEROON_PHONE_REGEX)->toContain('237');
});

/*
|--------------------------------------------------------------------------
| View File Tests
|--------------------------------------------------------------------------
*/

it('has a profile edit blade file', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/edit.blade.php';
    expect(file_exists($viewPath))->toBeTrue();
});

it('profile edit view extends the correct layout for main domain', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/edit.blade.php';
    $content = file_get_contents($viewPath);
    expect($content)->toContain('layouts.main-public');
});

it('profile edit view extends the correct layout for tenant domain', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/edit.blade.php';
    $content = file_get_contents($viewPath);
    expect($content)->toContain('layouts.tenant-public');
});

it('profile edit view uses the __() translation helper for user-facing strings', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/edit.blade.php';
    $content = file_get_contents($viewPath);

    expect($content)->toContain("__('Edit Profile')")
        ->and($content)->toContain("__('Full Name')")
        ->and($content)->toContain("__('Phone Number')")
        ->and($content)->toContain("__('Preferred Language')")
        ->and($content)->toContain("__('Save Changes')")
        ->and($content)->toContain("__('Contact support to change your email.')");
});

it('profile edit view includes dark mode variants', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/edit.blade.php';
    $content = file_get_contents($viewPath);
    expect($content)->toContain('dark:');
});

it('profile edit view uses semantic color tokens', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/edit.blade.php';
    $content = file_get_contents($viewPath);

    expect($content)->toContain('bg-surface')
        ->and($content)->toContain('text-on-surface')
        ->and($content)->toContain('bg-primary')
        ->and($content)->toContain('border-outline');
});

it('profile edit view contains x-data with user pre-population', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/edit.blade.php';
    $content = file_get_contents($viewPath);

    expect($content)->toContain('x-data')
        ->and($content)->toContain('x-sync')
        ->and($content)->toContain('x-name=')
        ->and($content)->toContain('x-model=')
        ->and($content)->toContain('x-message=');
});

it('profile edit view contains email read-only field', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/edit.blade.php';
    $content = file_get_contents($viewPath);

    expect($content)->toContain('disabled')
        ->and($content)->toContain('readonly');
});

it('profile edit view contains submit button with loading state', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/edit.blade.php';
    $content = file_get_contents($viewPath);

    expect($content)->toContain('$fetching()')
        ->and($content)->toContain("__('Saving...')");
});

it('profile edit view uses Gale $action for form submission', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/edit.blade.php';
    $content = file_get_contents($viewPath);

    expect($content)->toContain('$action(')
        ->and($content)->toContain('@submit.prevent');
});

/*
|--------------------------------------------------------------------------
| Translation Key Tests
|--------------------------------------------------------------------------
*/

it('has English translations for profile edit strings', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

    expect($translations)->toHaveKey('Back to Profile')
        ->and($translations)->toHaveKey('Update your personal information.')
        ->and($translations)->toHaveKey('Contact support to change your email.')
        ->and($translations)->toHaveKey('Preferred Language')
        ->and($translations)->toHaveKey('Save Changes')
        ->and($translations)->toHaveKey('Saving...')
        ->and($translations)->toHaveKey('Profile updated successfully.')
        ->and($translations)->toHaveKey('Profile was updated')
        ->and($translations)->toHaveKey('Name must be at least 2 characters.')
        ->and($translations)->toHaveKey('Please enter a valid Cameroon phone number (+237 followed by 9 digits).')
        ->and($translations)->toHaveKey('Please select a preferred language.');
});

it('has French translations for profile edit strings', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    expect($translations)->toHaveKey('Back to Profile')
        ->and($translations)->toHaveKey('Update your personal information.')
        ->and($translations)->toHaveKey('Contact support to change your email.')
        ->and($translations)->toHaveKey('Preferred Language')
        ->and($translations)->toHaveKey('Save Changes')
        ->and($translations)->toHaveKey('Saving...')
        ->and($translations)->toHaveKey('Profile updated successfully.')
        ->and($translations)->toHaveKey('Profile was updated')
        ->and($translations)->toHaveKey('Name must be at least 2 characters.')
        ->and($translations)->toHaveKey('Please enter a valid Cameroon phone number (+237 followed by 9 digits).')
        ->and($translations)->toHaveKey('Please select a preferred language.');
});
