<?php

use App\Mail\EmailVerificationMail;
use App\Models\User;
use App\Services\EmailNotificationService;

/*
|--------------------------------------------------------------------------
| Email Verification Unit Tests (F-023)
|--------------------------------------------------------------------------
|
| Tests the EmailVerificationMail mailable, User model override,
| and verification-related configuration.
|
*/

$projectRoot = dirname(__DIR__, 3);

test('EmailVerificationMail extends BaseMailableNotification', function () {
    expect(EmailVerificationMail::class)
        ->toExtend(\App\Mail\BaseMailableNotification::class);
});

test('EmailVerificationMail is queued on high priority queue', function () {
    $emailService = new EmailNotificationService;

    $queueName = $emailService->getQueueName('email_verification');

    expect($queueName)->toBe('emails-high');
});

test('email_verification is marked as critical email type', function () {
    $emailService = new EmailNotificationService;

    expect($emailService->isCriticalEmail('email_verification'))->toBeTrue();
});

test('User model implements MustVerifyEmail', function () {
    $user = new User;

    expect($user)->toBeInstanceOf(\Illuminate\Contracts\Auth\MustVerifyEmail::class);
});

test('User model has custom sendEmailVerificationNotification method', function () {
    expect(method_exists(User::class, 'sendEmailVerificationNotification'))->toBeTrue();

    $reflection = new ReflectionMethod(User::class, 'sendEmailVerificationNotification');
    expect($reflection->getDeclaringClass()->getName())->toBe(User::class);
});

test('EmailVerificationMail implements ShouldQueue', function () {
    expect(EmailVerificationMail::class)
        ->toImplement(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

test('all verification strings exist in English translations', function () use ($projectRoot) {
    $enTranslations = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

    $requiredKeys = [
        'Verify your email address',
        'A verification link has been sent to your email address.',
        'Resend verification email',
        'Verification email sent.',
        'Email verified successfully.',
        'Please verify your email address.',
        'We sent a verification link to',
        'Verify your DancyMeals email address',
        'Hello :name,',
        'Verify Email Address',
        'This verification link will expire in :minutes minutes.',
    ];

    foreach ($requiredKeys as $key) {
        expect(array_key_exists($key, $enTranslations))->toBeTrue();
    }
});

test('all verification strings exist in French translations', function () use ($projectRoot) {
    $frTranslations = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    $requiredKeys = [
        'Verify your email address',
        'A verification link has been sent to your email address.',
        'Resend verification email',
        'Verification email sent.',
        'Email verified successfully.',
        'Please verify your email address.',
        'We sent a verification link to',
        'Verify your DancyMeals email address',
        'Hello :name,',
        'Verify Email Address',
        'This verification link will expire in :minutes minutes.',
    ];

    foreach ($requiredKeys as $key) {
        expect(array_key_exists($key, $frTranslations))->toBeTrue();
    }
});

test('email verification email template file exists', function () use ($projectRoot) {
    expect(file_exists($projectRoot.'/resources/views/emails/verify-email.blade.php'))->toBeTrue();
});

test('email verification banner component file exists', function () use ($projectRoot) {
    expect(file_exists($projectRoot.'/resources/views/components/email-verification-banner.blade.php'))->toBeTrue();
});
