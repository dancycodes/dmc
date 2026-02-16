<?php

use App\Http\Controllers\ProfileController;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-030: Profile View — Unit Tests
|--------------------------------------------------------------------------
|
| Unit tests for the profile view feature covering: controller existence,
| route configuration, view file presence, translation keys, and the
| User model's relevant fields and formatting behavior.
|
*/

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| Controller Tests
|--------------------------------------------------------------------------
*/

it('has a ProfileController class', function () {
    expect(class_exists(ProfileController::class))->toBeTrue();
});

it('ProfileController has a show method', function () {
    $controller = new ProfileController;
    expect(method_exists($controller, 'show'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| View File Tests
|--------------------------------------------------------------------------
*/

it('has a profile show blade file', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/show.blade.php';
    expect(file_exists($viewPath))->toBeTrue();
});

it('profile view extends the correct layout for main domain', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/show.blade.php';
    $content = file_get_contents($viewPath);
    expect($content)->toContain('layouts.main-public');
});

it('profile view extends the correct layout for tenant domain', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/show.blade.php';
    $content = file_get_contents($viewPath);
    expect($content)->toContain('layouts.tenant-public');
});

it('profile view uses the __() translation helper for user-facing strings', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/show.blade.php';
    $content = file_get_contents($viewPath);

    // Check key strings use __() translation helper
    expect($content)->toContain("__('Profile')")
        ->and($content)->toContain("__('Email')")
        ->and($content)->toContain("__('Phone')")
        ->and($content)->toContain("__('Language')")
        ->and($content)->toContain("__('Verified')")
        ->and($content)->toContain("__('Unverified')")
        ->and($content)->toContain("__('Verify now')")
        ->and($content)->toContain("__('Edit Profile')")
        ->and($content)->toContain("__('Account Settings')");
});

it('profile view includes dark mode variants', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/show.blade.php';
    $content = file_get_contents($viewPath);
    expect($content)->toContain('dark:');
});

it('profile view uses semantic color tokens', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/show.blade.php';
    $content = file_get_contents($viewPath);

    expect($content)->toContain('bg-surface')
        ->and($content)->toContain('text-on-surface')
        ->and($content)->toContain('bg-primary')
        ->and($content)->toContain('border-outline');
});

/*
|--------------------------------------------------------------------------
| Translation Key Tests
|--------------------------------------------------------------------------
*/

it('has English translations for profile strings', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

    expect($translations)->toHaveKey('Member since :date')
        ->and($translations)->toHaveKey('Verified')
        ->and($translations)->toHaveKey('Unverified')
        ->and($translations)->toHaveKey('Verify now')
        ->and($translations)->toHaveKey('Edit Profile')
        ->and($translations)->toHaveKey('Change Photo')
        ->and($translations)->toHaveKey('Delivery Addresses')
        ->and($translations)->toHaveKey('Payment Methods')
        ->and($translations)->toHaveKey('Notification Preferences')
        ->and($translations)->toHaveKey('Language Preference')
        ->and($translations)->toHaveKey('Account Settings');
});

it('has French translations for profile strings', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    expect($translations)->toHaveKey('Member since :date')
        ->and($translations)->toHaveKey('Verified')
        ->and($translations)->toHaveKey('Unverified')
        ->and($translations)->toHaveKey('Verify now')
        ->and($translations)->toHaveKey('Edit Profile')
        ->and($translations)->toHaveKey('Change Photo')
        ->and($translations)->toHaveKey('Delivery Addresses')
        ->and($translations)->toHaveKey('Payment Methods')
        ->and($translations)->toHaveKey('Notification Preferences')
        ->and($translations)->toHaveKey('Language Preference')
        ->and($translations)->toHaveKey('Account Settings');
});

/*
|--------------------------------------------------------------------------
| User Model Tests
|--------------------------------------------------------------------------
*/

it('User model has profile_photo_path in fillable', function () {
    $user = new User;
    expect($user->getFillable())->toContain('profile_photo_path');
});

it('User model has preferred_language in fillable', function () {
    $user = new User;
    expect($user->getFillable())->toContain('preferred_language');
});

it('User model has phone in fillable', function () {
    $user = new User;
    expect($user->getFillable())->toContain('phone');
});

it('User model has email_verified_at cast as datetime', function () {
    $user = new User;
    $casts = $user->getCasts();
    expect($casts)->toHaveKey('email_verified_at');
});

it('User model has created_at available for member since formatting', function () {
    $user = new User;
    expect($user->getDates())->toContain('created_at');
});
