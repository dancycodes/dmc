<?php

use App\Http\Requests\Auth\PasswordResetRequest;
use App\Mail\PasswordResetMail;
use App\Services\EmailNotificationService;

/*
|--------------------------------------------------------------------------
| F-026: Password Reset Request — Unit Tests
|--------------------------------------------------------------------------
|
| Tests the PasswordResetRequest form request, PasswordResetMail mailable
| structure, EmailNotificationService queue routing, and file existence.
|
*/

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| PasswordResetRequest Form Request
|--------------------------------------------------------------------------
*/

it('has correct validation rules for password reset request', function () {
    $request = new PasswordResetRequest;

    $rules = $request->rules();

    expect($rules)->toHaveKey('email')
        ->and($rules['email'])->toContain('required')
        ->and($rules['email'])->toContain('email')
        ->and($rules['email'])->toContain('max:255');
});

it('authorizes all users to make password reset requests', function () {
    $request = new PasswordResetRequest;

    expect($request->authorize())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| EmailNotificationService — Queue Routing
|--------------------------------------------------------------------------
*/

it('routes password reset emails to the high-priority queue', function () {
    $service = new EmailNotificationService;

    $queueName = $service->getQueueName('password_reset');

    expect($queueName)->toBe('emails-high');
});

it('classifies password reset as a critical email type', function () {
    $service = new EmailNotificationService;

    expect($service->isCriticalEmail('password_reset'))->toBeTrue();
});

it('does not classify general emails as critical', function () {
    $service = new EmailNotificationService;

    expect($service->isCriticalEmail('general'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Blade Templates Exist
|--------------------------------------------------------------------------
*/

it('has the password reset request blade template', function () use ($projectRoot) {
    expect(file_exists($projectRoot.'/resources/views/auth/passwords/email.blade.php'))->toBeTrue();
});

it('has the password reset email template', function () use ($projectRoot) {
    expect(file_exists($projectRoot.'/resources/views/emails/password-reset.blade.php'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Localization
|--------------------------------------------------------------------------
*/

it('has English translations for password reset strings', function () use ($projectRoot) {
    $translations = json_decode(
        file_get_contents($projectRoot.'/lang/en.json'),
        true
    );

    expect($translations)->toHaveKey('Reset your password')
        ->and($translations)->toHaveKey('Send Reset Link')
        ->and($translations)->toHaveKey('Back to sign in')
        ->and($translations)->toHaveKey('Reset your DancyMeals password');
});

it('has French translations for password reset strings', function () use ($projectRoot) {
    $translations = json_decode(
        file_get_contents($projectRoot.'/lang/fr.json'),
        true
    );

    expect($translations)->toHaveKey('Reset your password')
        ->and($translations)->toHaveKey('Send Reset Link')
        ->and($translations)->toHaveKey('Back to sign in')
        ->and($translations)->toHaveKey('Reset your DancyMeals password');
});

/*
|--------------------------------------------------------------------------
| PasswordResetMail Class Structure
|--------------------------------------------------------------------------
*/

it('implements ShouldQueue for async delivery', function () {
    $reflection = new ReflectionClass(PasswordResetMail::class);

    expect($reflection->implementsInterface(\Illuminate\Contracts\Queue\ShouldQueue::class))->toBeTrue();
});

it('extends BaseMailableNotification', function () {
    $reflection = new ReflectionClass(PasswordResetMail::class);

    expect($reflection->getParentClass()->getName())->toBe(\App\Mail\BaseMailableNotification::class);
});
