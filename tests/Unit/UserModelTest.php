<?php

use App\Models\User;

test('user model has required fillable fields', function () {
    $user = new User;
    $fillable = $user->getFillable();

    expect($fillable)
        ->toContain('name')
        ->toContain('email')
        ->toContain('phone')
        ->toContain('password')
        ->toContain('is_active')
        ->toContain('profile_photo_path')
        ->toContain('preferred_language');
});

test('user model casts include expected types', function () {
    $user = new User;
    $casts = $user->getCasts();

    expect($casts)
        ->toHaveKey('email_verified_at', 'datetime')
        ->toHaveKey('password', 'hashed')
        ->toHaveKey('is_active', 'boolean');
});

test('user model normalizes email to lowercase via mutator', function () {
    $user = new User;
    $user->email = 'Test@EXAMPLE.com';

    expect($user->email)->toBe('test@example.com');
});

test('user model trims email whitespace via mutator', function () {
    $user = new User;
    $user->email = '  user@example.com  ';

    expect($user->email)->toBe('user@example.com');
});

test('user model normalizes phone by stripping +237 prefix', function () {
    $user = new User;
    $user->phone = '+237690000000';

    expect($user->phone)->toBe('690000000');
});

test('user model normalizes phone by stripping 237 prefix', function () {
    $user = new User;
    $user->phone = '237690000000';

    expect($user->phone)->toBe('690000000');
});

test('user model keeps plain 9-digit phone unchanged', function () {
    $user = new User;
    $user->phone = '690000000';

    expect($user->phone)->toBe('690000000');
});

test('user model strips spaces and dashes from phone', function () {
    $user = new User;
    $user->phone = '690 000-000';

    expect($user->phone)->toBe('690000000');
});

test('user model isActive method returns boolean', function () {
    $activeUser = new User;
    $activeUser->is_active = true;

    $inactiveUser = new User;
    $inactiveUser->is_active = false;

    expect($activeUser->isActive())->toBeTrue()
        ->and($inactiveUser->isActive())->toBeFalse();
});

test('user model uses HasRoles trait', function () {
    $user = new User;

    expect(method_exists($user, 'assignRole'))->toBeTrue()
        ->and(method_exists($user, 'hasRole'))->toBeTrue()
        ->and(method_exists($user, 'hasPermissionTo'))->toBeTrue();
});

test('user model hidden attributes include password and remember_token', function () {
    $user = new User;
    $hidden = $user->getHidden();

    expect($hidden)
        ->toContain('password')
        ->toContain('remember_token');
});
