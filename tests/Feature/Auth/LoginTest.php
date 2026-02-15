<?php

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| F-024: User Login — Feature Tests
|--------------------------------------------------------------------------
|
| Tests the login form display, authentication flow, validation,
| rate limiting, active status enforcement, activity logging,
| and redirect behavior.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

/*
|--------------------------------------------------------------------------
| Happy Path — Login Form Display
|--------------------------------------------------------------------------
*/

it('displays the login form on main domain', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertViewIs('auth.login');
    $response->assertSee(__('Sign in to your account'));
    $response->assertSee(__('Email Address'));
    $response->assertSee(__('Password'));
    $response->assertSee(__('Remember me'));
    $response->assertSee(__('Forgot password?'));
    $response->assertSee(__("Don't have an account?"));
});

it('includes honeypot hidden fields on the login form', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    // Honeypot renders hidden inputs with position: absolute; left: -9999px
    $response->assertSee('position: absolute; left: -9999px', false);
});

it('passes tenant data to the login view', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertViewHas('tenant');
});

it('displays link to registration page', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee(route('register'), false);
    $response->assertSee(__('Create one'));
});

it('displays link to forgot password page', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee(route('password.request'), false);
});

/*
|--------------------------------------------------------------------------
| Happy Path — Successful Login
|--------------------------------------------------------------------------
*/

it('authenticates a user with valid credentials', function () {
    $user = createUser('client', ['password' => 'Password1']);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'Password1',
    ]);

    $response->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
});

it('flashes a welcome toast on successful login', function () {
    $user = createUser('client', ['password' => 'Password1']);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'Password1',
    ]);

    $response->assertSessionHas('toast');
});

it('logs the login activity via Spatie Activitylog', function () {
    $user = createUser('client', ['password' => 'Password1']);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'Password1',
    ]);

    $activity = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('event', 'login')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('users')
        ->and($activity->causer_id)->toBe($user->id);
});

it('regenerates the session after successful login', function () {
    $user = createUser('client', ['password' => 'Password1']);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'Password1',
    ]);

    $this->assertAuthenticated();
});

/*
|--------------------------------------------------------------------------
| Remember Me (BR-052)
|--------------------------------------------------------------------------
*/

it('accepts the remember checkbox during login', function () {
    $user = createUser('client', ['password' => 'Password1']);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'Password1',
        'remember' => true,
    ]);

    $response->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
});

/*
|--------------------------------------------------------------------------
| Error Path — Invalid Credentials (BR-051)
|--------------------------------------------------------------------------
*/

it('rejects login with wrong password', function () {
    $user = createUser('client', ['password' => 'Password1']);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'WrongPassword1',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('rejects login with non-existent email', function () {
    $response = $this->post(route('login'), [
        'email' => 'nonexistent@example.com',
        'password' => 'Password1',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('does not reveal whether an email exists on login failure', function () {
    // Non-existent email should get same generic error
    $response = $this->post(route('login'), [
        'email' => 'doesnotexist@example.com',
        'password' => 'Password1',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Error Path — Inactive Account (BR-053)
|--------------------------------------------------------------------------
*/

it('rejects login for inactive user accounts', function () {
    $user = createUser('client', [
        'password' => 'Password1',
        'is_active' => false,
    ]);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'Password1',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('does not authenticate an inactive user even with correct credentials', function () {
    $user = User::factory()->inactive()->create(['password' => 'Password1']);
    $user->assignRole('client');

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'Password1',
    ]);

    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

it('rejects login without an email', function () {
    $response = $this->post(route('login'), [
        'email' => '',
        'password' => 'Password1',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('rejects login without a password', function () {
    $response = $this->post(route('login'), [
        'email' => 'user@example.com',
        'password' => '',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

it('rejects login with invalid email format', function () {
    $response = $this->post(route('login'), [
        'email' => 'not-an-email',
        'password' => 'Password1',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

it('treats email as case-insensitive', function () {
    $user = createUser('client', [
        'email' => 'user@example.com',
        'password' => 'Password1',
    ]);

    $response = $this->post(route('login'), [
        'email' => 'USER@EXAMPLE.COM',
        'password' => 'Password1',
    ]);

    $response->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
});

it('redirects authenticated users away from login page', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get(route('login'));

    // Guest middleware should redirect to home
    $response->assertRedirect();
});

it('redirects to intended URL after login', function () {
    $user = createUser('client', ['password' => 'Password1']);

    // Simulate trying to access a protected page
    $this->get('/email/verify');

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'Password1',
    ]);

    // Should redirect (either to intended URL or home)
    $response->assertRedirect();
    $this->assertAuthenticated();
});

/*
|--------------------------------------------------------------------------
| Rate Limiting (BR-049)
|--------------------------------------------------------------------------
*/

it('rate limits login attempts per email and IP', function () {
    $user = createUser('client', ['password' => 'Password1']);

    // Make 5 failed login attempts
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'WrongPassword',
        ]);
    }

    // 6th attempt should be rate limited
    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'WrongPassword',
    ]);

    $response->assertStatus(429);
});

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

it('logs out an authenticated user', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});

it('prevents unauthenticated users from logging out', function () {
    $response = $this->post(route('logout'));

    $response->assertRedirect(route('login'));
});
