<?php

use App\Http\Requests\Auth\PasswordResetExecutionRequest;
use Illuminate\Validation\Rules\Password;

/*
|--------------------------------------------------------------------------
| F-027: Password Reset Execution — Unit Tests
|--------------------------------------------------------------------------
|
| Tests the PasswordResetExecutionRequest validation rules,
| view structure, and translation coverage.
|
*/

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| PasswordResetExecutionRequest Validation Rules
|--------------------------------------------------------------------------
*/

it('has correct validation rules for token field', function () {
    $request = new PasswordResetExecutionRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('token')
        ->and($rules['token'])->toContain('required')
        ->and($rules['token'])->toContain('string');
});

it('has correct validation rules for email field', function () {
    $request = new PasswordResetExecutionRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('email')
        ->and($rules['email'])->toContain('required')
        ->and($rules['email'])->toContain('string')
        ->and($rules['email'])->toContain('email')
        ->and($rules['email'])->toContain('max:255');
});

it('has correct password strength rules matching registration (BR-073)', function () {
    $request = new PasswordResetExecutionRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('password')
        ->and($rules['password'])->toContain('required')
        ->and($rules['password'])->toContain('string')
        ->and($rules['password'])->toContain('confirmed');

    // Check Password rule object exists
    $hasPasswordRule = false;
    foreach ($rules['password'] as $rule) {
        if ($rule instanceof Password) {
            $hasPasswordRule = true;
        }
    }
    expect($hasPasswordRule)->toBeTrue();
});

it('authorizes all requests (guest form)', function () {
    $request = new PasswordResetExecutionRequest;

    expect($request->authorize())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| View File Structure
|--------------------------------------------------------------------------
*/

it('has the password reset form blade view', function () use ($projectRoot) {
    expect(file_exists($projectRoot.'/resources/views/auth/passwords/reset.blade.php'))->toBeTrue();
});

it('reset view extends the auth layout', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/auth/passwords/reset.blade.php');

    expect(str_contains($content, "@extends('layouts.auth')"))->toBeTrue();
});

it('reset view has password fields with show/hide toggle', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/auth/passwords/reset.blade.php');

    expect(str_contains($content, 'showPassword'))->toBeTrue()
        ->and(str_contains($content, 'x-name="password"'))->toBeTrue()
        ->and(str_contains($content, 'x-name="password_confirmation"'))->toBeTrue();
});

it('reset view has error states for expired and invalid tokens', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/auth/passwords/reset.blade.php');

    expect(str_contains($content, "tokenError === 'expired'"))->toBeTrue()
        ->and(str_contains($content, "tokenError === 'invalid'"))->toBeTrue();
});

it('reset view has a link back to login', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/auth/passwords/reset.blade.php');

    expect(str_contains($content, "route('login')"))->toBeTrue()
        ->and(str_contains($content, 'Back to sign in'))->toBeTrue();
});

it('reset view has a link to request new reset link on error pages', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/auth/passwords/reset.blade.php');

    expect(str_contains($content, "route('password.request')"))->toBeTrue()
        ->and(str_contains($content, 'Request new reset link'))->toBeTrue();
});

it('reset view uses x-sync for Gale state', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/auth/passwords/reset.blade.php');

    expect(str_contains($content, 'x-sync'))->toBeTrue();
});

it('reset view has loading state on submit button', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/auth/passwords/reset.blade.php');

    expect(str_contains($content, '$fetching()'))->toBeTrue()
        ->and(str_contains($content, 'Resetting...'))->toBeTrue();
});

it('reset view uses $action for form submission', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/auth/passwords/reset.blade.php');

    expect(str_contains($content, '$action('))->toBeTrue()
        ->and(str_contains($content, "route('password.update')"))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Translation Coverage (BR-080)
|--------------------------------------------------------------------------
*/

it('has all required English translations', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

    expect($translations)->toHaveKey('Set new password')
        ->and($translations)->toHaveKey('Enter your new password below.')
        ->and($translations)->toHaveKey('New Password')
        ->and($translations)->toHaveKey('Resetting...')
        ->and($translations)->toHaveKey('Link Expired')
        ->and($translations)->toHaveKey('This password reset link has expired.')
        ->and($translations)->toHaveKey('Request new reset link')
        ->and($translations)->toHaveKey('Invalid Link')
        ->and($translations)->toHaveKey('This password reset link is invalid.')
        ->and($translations)->toHaveKey('Password reset successfully. Please log in.')
        ->and($translations)->toHaveKey('Password was reset');
});

it('has all required French translations', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    expect($translations)->toHaveKey('Set new password')
        ->and($translations)->toHaveKey('Enter your new password below.')
        ->and($translations)->toHaveKey('New Password')
        ->and($translations)->toHaveKey('Resetting...')
        ->and($translations)->toHaveKey('Link Expired')
        ->and($translations)->toHaveKey('This password reset link has expired.')
        ->and($translations)->toHaveKey('Request new reset link')
        ->and($translations)->toHaveKey('Invalid Link')
        ->and($translations)->toHaveKey('This password reset link is invalid.')
        ->and($translations)->toHaveKey('Password reset successfully. Please log in.')
        ->and($translations)->toHaveKey('Password was reset');
});

/*
|--------------------------------------------------------------------------
| Controller Structure
|--------------------------------------------------------------------------
*/

it('has the PasswordResetController with all required methods', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/app/Http/Controllers/Auth/PasswordResetController.php');

    expect(str_contains($content, 'function showResetForm'))->toBeTrue()
        ->and(str_contains($content, 'function resetPassword'))->toBeTrue()
        ->and(str_contains($content, 'function showRequestForm'))->toBeTrue()
        ->and(str_contains($content, 'function sendResetLink'))->toBeTrue();
});

it('controller returns gale responses', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/app/Http/Controllers/Auth/PasswordResetController.php');

    expect(str_contains($content, 'gale()->view('))->toBeTrue()
        ->and(str_contains($content, 'gale()->redirect('))->toBeTrue()
        ->and(str_contains($content, '->messages('))->toBeTrue();

    // Verify no bare return view()
    expect(preg_match('/return\s+view\s*\(/', $content))->toBe(0);
});

it('controller logs activity for password reset (BR-078)', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/app/Http/Controllers/Auth/PasswordResetController.php');

    expect(str_contains($content, "activity('users')"))->toBeTrue()
        ->and(str_contains($content, "->event('password_reset')"))->toBeTrue();
});

it('controller uses Password facade for reset', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/app/Http/Controllers/Auth/PasswordResetController.php');

    expect(str_contains($content, 'Password::reset('))->toBeTrue();
});
