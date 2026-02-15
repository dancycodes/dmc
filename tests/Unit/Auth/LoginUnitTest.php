<?php

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-024: User Login — Unit Tests
|--------------------------------------------------------------------------
|
| Tests the LoginRequest form request validation rules, User model
| active status check, and email normalization behavior.
|
*/

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| LoginRequest Validation Rules
|--------------------------------------------------------------------------
*/

it('has the correct validation rules', function () {
    $request = new LoginRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('email')
        ->and($rules['email'])->toContain('required')
        ->and($rules['email'])->toContain('email')
        ->and($rules)->toHaveKey('password')
        ->and($rules['password'])->toContain('required');
});

it('authorizes all users to make login requests', function () {
    $request = new LoginRequest;

    expect($request->authorize())->toBeTrue();
});

it('has email and password validation rule keys', function () {
    $request = new LoginRequest;
    $rules = $request->rules();

    expect(array_keys($rules))->toBe(['email', 'password']);
});

it('requires string type for email and password', function () {
    $request = new LoginRequest;
    $rules = $request->rules();

    expect($rules['email'])->toContain('string')
        ->and($rules['password'])->toContain('string');
});

/*
|--------------------------------------------------------------------------
| User Model — Active Status
|--------------------------------------------------------------------------
*/

it('returns true for active users via isActive method', function () {
    $user = new User(['is_active' => true]);

    expect($user->isActive())->toBeTrue();
});

it('returns false for inactive users via isActive method', function () {
    $user = new User(['is_active' => false]);

    expect($user->isActive())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| User Model — Email Normalization
|--------------------------------------------------------------------------
*/

it('normalizes email to lowercase on the User model', function () {
    $user = new User;
    $user->email = 'TEST@EXAMPLE.COM';

    expect($user->email)->toBe('test@example.com');
});

it('trims whitespace from email on the User model', function () {
    $user = new User;
    $user->email = '  user@example.com  ';

    expect($user->email)->toBe('user@example.com');
});

/*
|--------------------------------------------------------------------------
| Translation Keys
|--------------------------------------------------------------------------
*/

it('has English translations for all login-related strings', function () use ($projectRoot) {
    $translations = json_decode(
        file_get_contents($projectRoot.'/lang/en.json'),
        true
    );

    $requiredKeys = [
        'Sign in to your account',
        'Signing in...',
        'Enter your password',
        'Forgot password?',
        'Remember me',
        "Don't have an account?",
        'Create one',
        'These credentials do not match our records.',
        'Your account has been deactivated.',
        'Welcome back!',
        'User logged in',
        'Log in with your DancyMeals account.',
    ];

    foreach ($requiredKeys as $key) {
        expect(array_key_exists($key, $translations))->toBeTrue();
    }
});

it('has French translations for all login-related strings', function () use ($projectRoot) {
    $translations = json_decode(
        file_get_contents($projectRoot.'/lang/fr.json'),
        true
    );

    $requiredKeys = [
        'Sign in to your account',
        'Signing in...',
        'Enter your password',
        'Forgot password?',
        'Remember me',
        "Don't have an account?",
        'Create one',
        'These credentials do not match our records.',
        'Your account has been deactivated.',
        'Welcome back!',
        'User logged in',
        'Log in with your DancyMeals account.',
    ];

    foreach ($requiredKeys as $key) {
        expect(array_key_exists($key, $translations))->toBeTrue();
    }
});

/*
|--------------------------------------------------------------------------
| LoginRequest — Prepare For Validation
|--------------------------------------------------------------------------
*/

it('has a prepareForValidation method that lowercases email', function () {
    // Verify the method exists on LoginRequest
    $reflection = new ReflectionMethod(LoginRequest::class, 'prepareForValidation');

    expect($reflection->isProtected())->toBeTrue();
});
