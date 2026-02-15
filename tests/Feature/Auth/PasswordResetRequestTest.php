<?php

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/*
|--------------------------------------------------------------------------
| F-026: Password Reset Request — Feature Tests
|--------------------------------------------------------------------------
|
| Tests the password reset request form display, email sending,
| anti-enumeration protection, validation, rate limiting,
| and multi-domain support.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
    Mail::fake();
});

/*
|--------------------------------------------------------------------------
| Happy Path — Form Display
|--------------------------------------------------------------------------
*/

it('displays the password reset request form on main domain', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
    $response->assertViewIs('auth.passwords.email');
    $response->assertSee(__('Reset your password'));
    $response->assertSee(__('Email Address'));
    $response->assertSee(__('Send Reset Link'));
});

it('passes tenant data to the reset request view', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
    $response->assertViewHas('tenant');
});

it('shows a back to login link on the reset request page (BR-071)', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
    $response->assertSee(route('login'), false);
    $response->assertSee(__('Back to sign in'));
});

it('does not include honeypot fields on the reset form (BR-069)', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
    // Honeypot renders hidden inputs with this CSS pattern
    $response->assertDontSee('position: absolute; left: -9999px', false);
});

/*
|--------------------------------------------------------------------------
| Happy Path — Sending Reset Link
|--------------------------------------------------------------------------
*/

it('sends a password reset email for a registered user', function () {
    $user = createUser('client');

    $response = $this->post(route('password.email'), [
        'email' => $user->email,
    ]);

    $response->assertRedirect();
    Mail::assertQueued(PasswordResetMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

it('returns the same success message for existing email (BR-064)', function () {
    $user = createUser('client');

    $response = $this->post(route('password.email'), [
        'email' => $user->email,
    ]);

    $response->assertSessionHas('status', __("If an account with that email exists, we've sent a password reset link."));
});

it('generates a password reset token when email exists', function () {
    $user = createUser('client');

    $this->post(route('password.email'), [
        'email' => $user->email,
    ]);

    $this->assertDatabaseHas('password_reset_tokens', [
        'email' => $user->email,
    ]);
});

/*
|--------------------------------------------------------------------------
| Anti-Enumeration (BR-064)
|--------------------------------------------------------------------------
*/

it('returns the same success message for non-existent email (BR-064)', function () {
    $response = $this->post(route('password.email'), [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertSessionHas('status', __("If an account with that email exists, we've sent a password reset link."));
});

it('does not send an email for non-existent email addresses', function () {
    $this->post(route('password.email'), [
        'email' => 'nonexistent@example.com',
    ]);

    Mail::assertNothingQueued();
});

it('does not reveal whether an email exists in the system', function () {
    $user = createUser('client');

    // Submit with existing email
    $response1 = $this->post(route('password.email'), [
        'email' => $user->email,
    ]);

    // Submit with non-existing email
    $response2 = $this->post(route('password.email'), [
        'email' => 'doesnotexist@example.com',
    ]);

    // Both should have the same success message
    $expectedMessage = __("If an account with that email exists, we've sent a password reset link.");
    $response1->assertSessionHas('status', $expectedMessage);
    $response2->assertSessionHas('status', $expectedMessage);
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

it('rejects submission without an email', function () {
    $response = $this->post(route('password.email'), [
        'email' => '',
    ]);

    $response->assertSessionHasErrors('email');
});

it('rejects submission with an invalid email format', function () {
    $response = $this->post(route('password.email'), [
        'email' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors('email');
});

it('rejects submission with a very long email address', function () {
    $response = $this->post(route('password.email'), [
        'email' => str_repeat('a', 250).'@test.com',
    ]);

    $response->assertSessionHasErrors('email');
});

/*
|--------------------------------------------------------------------------
| Rate Limiting (BR-065)
|--------------------------------------------------------------------------
*/

it('rate limits password reset requests to 3 per 15 minutes per email (BR-065)', function () {
    $user = createUser('client');

    // Make 3 valid requests
    for ($i = 0; $i < 3; $i++) {
        $this->post(route('password.email'), [
            'email' => $user->email,
        ]);
    }

    // 4th request should be rate limited
    $response = $this->post(route('password.email'), [
        'email' => $user->email,
    ]);

    $response->assertStatus(429);
});

it('does not rate limit different email addresses independently', function () {
    $user1 = createUser('client');
    $user2 = createUser('client');

    // 3 requests for user1
    for ($i = 0; $i < 3; $i++) {
        $this->post(route('password.email'), [
            'email' => $user1->email,
        ]);
    }

    // User2 should still be able to submit
    $response = $this->post(route('password.email'), [
        'email' => $user2->email,
    ]);

    $response->assertRedirect();
    expect($response->status())->not->toBe(429);
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

it('treats email as case-insensitive', function () {
    $user = createUser('client', ['email' => 'user@example.com']);

    $this->post(route('password.email'), [
        'email' => 'USER@EXAMPLE.COM',
    ]);

    Mail::assertQueued(PasswordResetMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

it('sends reset email even for deactivated accounts', function () {
    $user = User::factory()->inactive()->create();
    $user->assignRole('client');

    $this->post(route('password.email'), [
        'email' => $user->email,
    ]);

    Mail::assertQueued(PasswordResetMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

it('generates a new token when user submits again within token validity', function () {
    $user = createUser('client');

    $this->post(route('password.email'), ['email' => $user->email]);
    $this->post(route('password.email'), ['email' => $user->email]);

    // Only one token record should exist (new token replaces old)
    $this->assertDatabaseCount('password_reset_tokens', 1);
});

it('redirects authenticated users away from the reset request page', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get(route('password.request'));

    // Guest middleware should redirect authenticated users
    $response->assertRedirect();
});

/*
|--------------------------------------------------------------------------
| Token Expiration (BR-066)
|--------------------------------------------------------------------------
*/

it('configures token expiration to 60 minutes (BR-066)', function () {
    $expiration = config('auth.passwords.users.expire');

    expect($expiration)->toBe(60);
});

/*
|--------------------------------------------------------------------------
| Multi-Domain (BR-068)
|--------------------------------------------------------------------------
*/

it('is accessible on the main domain', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});
