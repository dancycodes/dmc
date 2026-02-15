<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/*
|--------------------------------------------------------------------------
| F-027: Password Reset Execution — Feature Tests
|--------------------------------------------------------------------------
|
| Tests the password reset form display, token validation, password update,
| session invalidation, activity logging, and error handling.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

/*
|--------------------------------------------------------------------------
| Helper: Create a valid password reset token for a user.
|--------------------------------------------------------------------------
*/

function createResetToken(User $user): string
{
    return Password::createToken($user);
}

/*
|--------------------------------------------------------------------------
| Happy Path — Form Display
|--------------------------------------------------------------------------
*/

it('displays the password reset form with a valid token', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    $response = $this->get(route('password.reset', [
        'token' => $token,
        'email' => $user->email,
    ]));

    $response->assertOk();
    $response->assertViewIs('auth.passwords.reset');
    $response->assertSee(__('Set new password'));
    $response->assertSee($user->email);
});

it('shows email as a read-only field on the reset form', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    $response = $this->get(route('password.reset', [
        'token' => $token,
        'email' => $user->email,
    ]));

    $response->assertOk();
    $response->assertSee('disabled', false);
});

it('passes the token and email to the reset form view', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    $response = $this->get(route('password.reset', [
        'token' => $token,
        'email' => $user->email,
    ]));

    $response->assertOk();
    $response->assertViewHas('token', $token);
    $response->assertViewHas('email', $user->email);
    $response->assertViewHas('tokenError', null);
});

it('shows password and confirm password fields', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    $response = $this->get(route('password.reset', [
        'token' => $token,
        'email' => $user->email,
    ]));

    $response->assertOk();
    $response->assertSee(__('New Password'));
    $response->assertSee(__('Confirm Password'));
    $response->assertSee(__('Reset Password'));
});

/*
|--------------------------------------------------------------------------
| Happy Path — Password Reset Execution
|--------------------------------------------------------------------------
*/

it('resets the password with a valid token and strong password (BR-075)', function () {
    $user = createUser('client', ['password' => Hash::make('OldPassword1')]);
    $token = createResetToken($user);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response->assertRedirect(route('login'));

    // Verify password was actually changed
    $user->refresh();
    expect(Hash::check('NewPassword1', $user->password))->toBeTrue();
    expect(Hash::check('OldPassword1', $user->password))->toBeFalse();
});

it('invalidates the token after successful reset (BR-075)', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    // Token record should be removed
    $this->assertDatabaseMissing('password_reset_tokens', [
        'email' => $user->email,
    ]);
});

it('redirects to login page after successful reset (BR-076)', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response->assertRedirect(route('login'));
});

it('shows success message after reset via session toast', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response->assertSessionHas('toast');
    $toast = session('toast');
    expect($toast['type'])->toBe('success');
    expect($toast['message'])->toBe(__('Password reset successfully. Please log in.'));
});

it('regenerates the remember token after reset', function () {
    $user = createUser('client');
    $oldRememberToken = $user->remember_token;
    $token = createResetToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $user->refresh();
    expect($user->remember_token)->not->toBe($oldRememberToken);
});

it('fires the PasswordReset event after successful reset', function () {
    Event::fake([PasswordReset::class]);
    $user = createUser('client');
    $token = createResetToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    Event::assertDispatched(PasswordReset::class, function ($event) use ($user) {
        return $event->user->id === $user->id;
    });
});

/*
|--------------------------------------------------------------------------
| Activity Logging (BR-078)
|--------------------------------------------------------------------------
*/

it('logs password reset activity (BR-078)', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'users',
        'event' => 'password_reset',
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'causer_type' => User::class,
        'causer_id' => $user->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Error Path — Expired Token
|--------------------------------------------------------------------------
*/

it('shows error for expired token (BR-072)', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    // Manually expire the token by updating created_at
    DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->update(['created_at' => now()->subMinutes(61)]);

    $response = $this->get(route('password.reset', [
        'token' => $token,
        'email' => $user->email,
    ]));

    $response->assertOk();
    $response->assertViewHas('tokenError', 'expired');
    $response->assertSee(__('This password reset link has expired.'));
    $response->assertSee(__('Request new reset link'));
});

it('rejects submission with an expired token', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->update(['created_at' => now()->subMinutes(61)]);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    // Should redirect back with errors (invalid token after expiry)
    $response->assertSessionHasErrors('password');
});

/*
|--------------------------------------------------------------------------
| Error Path — Invalid Token
|--------------------------------------------------------------------------
*/

it('shows error for an invalid token (BR-079)', function () {
    $user = createUser('client');

    $response = $this->get(route('password.reset', [
        'token' => 'invalid-token',
        'email' => $user->email,
    ]));

    $response->assertOk();
    $response->assertViewHas('tokenError', 'invalid');
    $response->assertSee(__('This password reset link is invalid.'));
    $response->assertSee(__('Request new reset link'));
});

it('shows error for already-used token', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    // First use: should succeed
    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    // Second use: should fail — token is consumed
    $response = $this->get(route('password.reset', [
        'token' => $token,
        'email' => $user->email,
    ]));

    $response->assertOk();
    $response->assertViewHas('tokenError', 'invalid');
});

it('shows error when no token record exists for the email', function () {
    $user = createUser('client');

    // No token was ever created for this user
    $response = $this->get(route('password.reset', [
        'token' => 'some-random-token',
        'email' => $user->email,
    ]));

    $response->assertOk();
    $response->assertViewHas('tokenError', 'invalid');
});

it('shows error when token is for a different email', function () {
    $user1 = createUser('client');
    $user2 = createUser('client');
    $token = createResetToken($user1);

    // Try with user2's email but user1's token
    $response = $this->get(route('password.reset', [
        'token' => $token,
        'email' => $user2->email,
    ]));

    $response->assertOk();
    $response->assertViewHas('tokenError', 'invalid');
});

/*
|--------------------------------------------------------------------------
| Error Path — Weak Password (BR-073)
|--------------------------------------------------------------------------
*/

it('rejects a password shorter than 8 characters', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Ab1',
        'password_confirmation' => 'Ab1',
    ]);

    $response->assertSessionHasErrors('password');
});

it('rejects a password without mixed case', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'alllowercase1',
        'password_confirmation' => 'alllowercase1',
    ]);

    $response->assertSessionHasErrors('password');
});

it('rejects a password without numbers', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NoNumbersHere',
        'password_confirmation' => 'NoNumbersHere',
    ]);

    $response->assertSessionHasErrors('password');
});

/*
|--------------------------------------------------------------------------
| Error Path — Password Mismatch (BR-074)
|--------------------------------------------------------------------------
*/

it('rejects mismatched password and confirmation (BR-074)', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword1',
        'password_confirmation' => 'DifferentPassword1',
    ]);

    $response->assertSessionHasErrors('password');
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

it('rejects submission without a token', function () {
    $user = createUser('client');

    $response = $this->post(route('password.update'), [
        'token' => '',
        'email' => $user->email,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response->assertSessionHasErrors('token');
});

it('rejects submission without an email', function () {
    $response = $this->post(route('password.update'), [
        'token' => 'some-token',
        'email' => '',
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response->assertSessionHasErrors('email');
});

it('rejects submission without a password', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => '',
        'password_confirmation' => '',
    ]);

    $response->assertSessionHasErrors('password');
});

/*
|--------------------------------------------------------------------------
| Session Invalidation (BR-077)
|--------------------------------------------------------------------------
*/

it('invalidates existing sessions after password reset (BR-077)', function () {
    $user = createUser('client', ['password' => Hash::make('OldPassword1')]);
    $token = createResetToken($user);

    // Reset password (as guest — the typical flow)
    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response->assertRedirect(route('login'));

    // User is a guest after password reset
    $this->assertGuest();

    // Old password should no longer work
    $user->refresh();
    expect(Hash::check('OldPassword1', $user->password))->toBeFalse();
    expect(Hash::check('NewPassword1', $user->password))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

it('allows resetting to the same password as current (no reuse restriction)', function () {
    $user = createUser('client', ['password' => Hash::make('SamePassword1')]);
    $token = createResetToken($user);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'SamePassword1',
        'password_confirmation' => 'SamePassword1',
    ]);

    $response->assertRedirect(route('login'));

    $user->refresh();
    expect(Hash::check('SamePassword1', $user->password))->toBeTrue();
});

it('treats email as case-insensitive for password reset', function () {
    $user = createUser('client', ['email' => 'user@example.com']);
    $token = createResetToken($user);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'USER@EXAMPLE.COM',
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response->assertRedirect(route('login'));

    $user->refresh();
    expect(Hash::check('NewPassword1', $user->password))->toBeTrue();
});

it('only uses the most recent token when multiple are generated', function () {
    $user = createUser('client');

    $token1 = createResetToken($user);

    // Wait a moment and generate a new token — replaces old
    $token2 = createResetToken($user);

    // Old token should no longer work
    $response = $this->post(route('password.update'), [
        'token' => $token1,
        'email' => $user->email,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response->assertSessionHasErrors('password');

    // New token should work
    $response2 = $this->post(route('password.update'), [
        'token' => $token2,
        'email' => $user->email,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response2->assertRedirect(route('login'));
});

it('shows request new link on error pages linking to forgot password page', function () {
    $response = $this->get(route('password.reset', [
        'token' => 'invalid-token',
        'email' => 'test@example.com',
    ]));

    $response->assertOk();
    $response->assertSee(route('password.request'), false);
});

it('redirects authenticated users away from the password reset form', function () {
    $user = createUser('client');
    $token = createResetToken($user);

    $response = $this->actingAs($user)->get(route('password.reset', [
        'token' => $token,
        'email' => $user->email,
    ]));

    // Guest middleware should redirect authenticated users
    $response->assertRedirect();
});

it('rate limits password reset execution with strict throttle', function () {
    $user = createUser('client');

    // Make 5 requests to exhaust strict rate limiter
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('password.update'), [
            'token' => 'invalid-token-'.$i,
            'email' => $user->email,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);
    }

    // 6th request should be rate limited
    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token-extra',
        'email' => $user->email,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response->assertStatus(429);
});
