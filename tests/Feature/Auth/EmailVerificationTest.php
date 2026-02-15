<?php

use App\Mail\EmailVerificationMail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/*
|--------------------------------------------------------------------------
| Email Verification Feature Tests (F-023)
|--------------------------------------------------------------------------
|
| Tests the full email verification flow: notice page, verification link,
| resend functionality, rate limiting, and edge cases.
|
*/

test('unverified user sees verification notice page', function () {
    $user = createUser('client', ['email_verified_at' => null]);

    $response = $this->actingAs($user)->get('/email/verify');

    $response->assertOk();
    $response->assertViewIs('auth.verify-email');
    $response->assertSee($user->email);
});

test('verified user is redirected from notice page', function () {
    $user = createUser('client', ['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get('/email/verify');

    $response->assertRedirect();
});

test('guest cannot access verification notice', function () {
    $response = $this->get('/email/verify');

    $response->assertRedirect(route('login'));
});

test('valid verification link verifies the email', function () {
    $user = createUser('client', ['email_verified_at' => null]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    Event::fake([Verified::class]);

    $response = $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
    $response->assertRedirect();
});

test('already verified user clicking verification link is redirected', function () {
    $user = createUser('client', ['email_verified_at' => now()]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    $response->assertRedirect();
});

test('invalid signature is rejected', function () {
    $user = createUser('client', ['email_verified_at' => null]);

    $response = $this->actingAs($user)->get('/email/verify/'.$user->id.'/invalid-hash?signature=invalid');

    $response->assertForbidden();
});

test('resend sends verification email', function () {
    Mail::fake();

    $user = createUser('client', ['email_verified_at' => null]);

    $response = $this->actingAs($user)->post('/email/verification-notification');

    Mail::assertQueued(EmailVerificationMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

test('resend does not send if already verified', function () {
    Mail::fake();

    $user = createUser('client', ['email_verified_at' => now()]);

    $response = $this->actingAs($user)->post('/email/verification-notification');

    Mail::assertNothingQueued();
    $response->assertRedirect();
});

test('guest cannot resend verification email', function () {
    $response = $this->post('/email/verification-notification');

    $response->assertRedirect(route('login'));
});

test('verification email is sent on registration', function () {
    Mail::fake();
    $this->seedRolesAndPermissions();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'testverify@example.com',
        'phone' => '612345678',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    Mail::assertQueued(EmailVerificationMail::class, function ($mail) {
        return $mail->hasTo('testverify@example.com');
    });
});

test('verification banner is visible for unverified users on home page', function () {
    $user = createUser('client', ['email_verified_at' => null]);

    $response = $this->actingAs($user)->get('/');

    $response->assertOk();
    $response->assertSee(__('Please verify your email address.'));
    $response->assertSee($user->email);
});

test('verification banner is not visible for verified users', function () {
    $user = createUser('client', ['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get('/');

    $response->assertOk();
    $response->assertDontSee(__('Please verify your email address.'));
});

test('verification banner is not visible for guests', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertDontSee(__('Please verify your email address.'));
});

test('verification link for different user is rejected', function () {
    $user1 = createUser('client', ['email_verified_at' => null]);
    $user2 = createUser('client', ['email_verified_at' => null]);

    // Generate link for user2
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user2->id, 'hash' => sha1($user2->email)]
    );

    // Try to use it as user1
    $response = $this->actingAs($user1)->get($verificationUrl);

    // Laravel's EmailVerificationRequest rejects mismatched user IDs
    $response->assertForbidden();
    expect($user1->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verify email page shows correct translations', function () {
    $user = createUser('client', ['email_verified_at' => null]);

    $response = $this->actingAs($user)->get('/email/verify');

    $response->assertOk();
    $response->assertSee(__('Verify your email address'));
    $response->assertSee(__('A verification link has been sent to your email address.'));
    $response->assertSee(__('Resend verification email'));
});
