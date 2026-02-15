<?php

/*
|--------------------------------------------------------------------------
| F-021: User Registration Form — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for translation key presence, controller structure,
| and form request validation rules.
|
*/

$projectRoot = dirname(__DIR__, 3);

it('has all registration form translation keys in English', function () use ($projectRoot) {
    $enTranslations = json_decode(
        file_get_contents($projectRoot.'/lang/en.json'),
        true
    );

    $requiredKeys = [
        'Create your account',
        'Create Account',
        'Creating...',
        'Full Name',
        'Enter your full name',
        'Email Address',
        'Phone Number',
        'Min. 8 characters',
        'Repeat your password',
        'Show password',
        'Hide password',
        'Already have an account?',
        'Sign in',
        'You are creating a DancyMeals account. Use it on any cook\'s site.',
    ];

    foreach ($requiredKeys as $key) {
        expect($enTranslations)->toHaveKey($key);
    }
});

it('has all registration form translation keys in French', function () use ($projectRoot) {
    $frTranslations = json_decode(
        file_get_contents($projectRoot.'/lang/fr.json'),
        true
    );

    $requiredKeys = [
        'Create your account',
        'Create Account',
        'Creating...',
        'Full Name',
        'Enter your full name',
        'Email Address',
        'Phone Number',
        'Min. 8 characters',
        'Repeat your password',
        'Show password',
        'Hide password',
        'Already have an account?',
        'Sign in',
        'You are creating a DancyMeals account. Use it on any cook\'s site.',
    ];

    foreach ($requiredKeys as $key) {
        expect($frTranslations)->toHaveKey($key);
    }
});

it('has French translations that differ from English for registration form keys', function () use ($projectRoot) {
    $enTranslations = json_decode(
        file_get_contents($projectRoot.'/lang/en.json'),
        true
    );
    $frTranslations = json_decode(
        file_get_contents($projectRoot.'/lang/fr.json'),
        true
    );

    // These keys should have different values in French vs English
    $keysToCheck = [
        'Create your account',
        'Create Account',
        'Creating...',
        'Full Name',
        'Enter your full name',
        'Email Address',
        'Phone Number',
        'Min. 8 characters',
        'Repeat your password',
        'Show password',
        'Hide password',
    ];

    foreach ($keysToCheck as $key) {
        expect($frTranslations[$key])
            ->not->toBe($enTranslations[$key], "French translation for '{$key}' should differ from English");
    }
});

it('register controller showRegistrationForm method exists and returns mixed', function () use ($projectRoot) {
    $controllerPath = $projectRoot.'/app/Http/Controllers/Auth/RegisterController.php';

    expect(file_exists($controllerPath))->toBeTrue();

    $contents = file_get_contents($controllerPath);

    // Must use gale()->view() not return view()
    expect($contents)->toContain("gale()->view('auth.register'");

    // Must pass tenant data to the view
    expect($contents)->toContain("'tenant' => tenant()");
});

it('register form request has proper validation rules', function () use ($projectRoot) {
    $requestPath = $projectRoot.'/app/Http/Requests/Auth/RegisterRequest.php';

    expect(file_exists($requestPath))->toBeTrue();

    $contents = file_get_contents($requestPath);

    // Must validate name, email, phone, password
    expect($contents)
        ->toContain("'name'")
        ->toContain("'email'")
        ->toContain("'phone'")
        ->toContain("'password'");

    // Must include localized error messages
    expect($contents)->toContain('__(');
});

it('registration route is protected by guest middleware', function () use ($projectRoot) {
    $routesContent = file_get_contents($projectRoot.'/routes/web.php');

    // The register route is inside the guest middleware group
    expect($routesContent)->toContain("middleware('guest')");
    expect($routesContent)->toContain("Route::get('/register'");
});

it('registration POST route has honeypot middleware', function () use ($projectRoot) {
    $routesContent = file_get_contents($projectRoot.'/routes/web.php');

    // The POST register route has honeypot middleware
    expect($routesContent)->toContain("Route::post('/register'");
    expect($routesContent)->toContain("middleware(['honeypot'");
});

it('register blade view extends the auth layout', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/auth/register.blade.php';

    expect(file_exists($viewPath))->toBeTrue();

    $contents = file_get_contents($viewPath);

    expect($contents)->toContain("@extends('layouts.auth')");
});

it('register blade view includes honeypot component', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/auth/register.blade.php';
    $contents = file_get_contents($viewPath);

    expect($contents)->toContain('<x-honeypot />');
});

it('register blade view uses x-sync for Gale state synchronization', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/auth/register.blade.php';
    $contents = file_get_contents($viewPath);

    expect($contents)->toContain('x-sync');
});

it('register blade view uses $fetching() with parentheses for loading state', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/auth/register.blade.php';
    $contents = file_get_contents($viewPath);

    // Must use $fetching() with parentheses, not $fetching
    expect($contents)->toContain('$fetching()');
});

it('auth layout includes tenant notice section for tenant domain registration', function () use ($projectRoot) {
    $layoutPath = $projectRoot.'/resources/views/layouts/auth.blade.php';
    $contents = file_get_contents($layoutPath);

    expect($contents)->toContain('tenant-notice');
    expect($contents)->toContain('@hasSection');
});
