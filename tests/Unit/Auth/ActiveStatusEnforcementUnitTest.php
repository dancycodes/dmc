<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Active Status Enforcement Unit Tests (F-029)
|--------------------------------------------------------------------------
|
| Tests for the EnsureUserIsActive middleware class, User model isActive()
| method, and the deactivation page view existence.
|
*/

$projectRoot = dirname(__DIR__, 3);

it('has the EnsureUserIsActive middleware class', function () {
    expect(class_exists(EnsureUserIsActive::class))->toBeTrue();
});

it('has the handle method on the middleware', function () {
    $middleware = new EnsureUserIsActive;
    expect(method_exists($middleware, 'handle'))->toBeTrue();
});

it('has the isActive method on the User model', function () {
    expect(method_exists(User::class, 'isActive'))->toBeTrue();
});

it('returns true from isActive when user is active', function () {
    $user = new User;
    $user->is_active = true;

    expect($user->isActive())->toBeTrue();
});

it('returns false from isActive when user is inactive', function () {
    $user = new User;
    $user->is_active = false;

    expect($user->isActive())->toBeFalse();
});

it('has is_active in the fillable array', function () {
    $user = new User;

    expect($user->getFillable())->toContain('is_active');
});

it('casts is_active to boolean', function () {
    $user = new User;
    $casts = $user->getCasts();

    expect($casts)->toHaveKey('is_active')
        ->and($casts['is_active'])->toBe('boolean');
});

it('has the account-deactivated blade view file', function () use ($projectRoot) {
    $viewPath = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.'account-deactivated.blade.php';

    expect(file_exists($viewPath))->toBeTrue();
});

it('has the middleware registered in bootstrap app', function () use ($projectRoot) {
    $bootstrapContent = file_get_contents($projectRoot.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php');

    expect(str_contains($bootstrapContent, 'EnsureUserIsActive'))->toBeTrue();
});

it('has the account deactivated route defined', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'web.php');

    expect(str_contains($routeContent, 'account.deactivated'))->toBeTrue()
        ->and(str_contains($routeContent, 'account-deactivated'))->toBeTrue();
});

it('has translation strings for deactivation messages in en.json', function () use ($projectRoot) {
    $enJson = json_decode(file_get_contents($projectRoot.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR.'en.json'), true);

    expect($enJson)->toHaveKey('Account Deactivated')
        ->and($enJson)->toHaveKey('Your account has been deactivated. Please contact support.')
        ->and($enJson)->toHaveKey('Contact Support');
});

it('has translation strings for deactivation messages in fr.json', function () use ($projectRoot) {
    $frJson = json_decode(file_get_contents($projectRoot.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR.'fr.json'), true);

    expect($frJson)->toHaveKey('Account Deactivated')
        ->and($frJson)->toHaveKey('Your account has been deactivated. Please contact support.')
        ->and($frJson)->toHaveKey('Contact Support');
});
