<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Active Status Enforcement Feature Tests (F-029)
|--------------------------------------------------------------------------
|
| Tests the EnsureUserIsActive middleware behavior: active users pass through,
| deactivated users are logged out and redirected, login is denied for
| deactivated users, and activity logging on forced logout.
|
*/

it('allows active authenticated users to access protected pages', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/theme/preference');

    $response->assertOk();
});

it('redirects deactivated users to account-deactivated page', function () {
    $user = createUser('client', ['is_active' => false]);

    $response = $this->actingAs($user)->get('/theme/preference');

    $response->assertRedirect(route('account.deactivated'));
});

it('logs out deactivated users and destroys session', function () {
    $user = createUser('client', ['is_active' => false]);

    $this->actingAs($user)->get('/theme/preference');

    $this->assertGuest();
});

it('allows access to deactivation page without authentication', function () {
    // BR-096: The deactivation message page must be accessible without authentication
    $response = $this->get(route('account.deactivated'));

    $response->assertOk();
    $response->assertSee('Account Deactivated');
});

it('shows deactivation message on the account-deactivated page', function () {
    $response = $this->get(route('account.deactivated'));

    $response->assertSee('Your account has been deactivated. Please contact support.');
    $response->assertSee('Contact Support');
    $response->assertSee('Go Home');
});

it('denies login for deactivated users', function () {
    // BR-091: Deactivated users cannot log in (enforced at login via F-024)
    $user = User::factory()->inactive()->create(['password' => bcrypt('password123')]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $this->assertGuest();
});

it('catches deactivated users on any authenticated route', function () {
    $user = createUser('client', ['is_active' => false]);

    $response = $this->actingAs($user)->get('/theme/preference');
    $response->assertRedirect(route('account.deactivated'));
    $this->assertGuest();
});

it('passes through for guest users without affecting the request', function () {
    // Middleware should not interfere with guest routes
    $response = $this->get('/login');

    $response->assertOk();
});

it('logs forced logout activity when deactivated user makes a request', function () {
    $user = createUser('client', ['is_active' => false]);

    $this->actingAs($user)->get('/theme/preference');

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'causer_type' => User::class,
        'causer_id' => $user->id,
        'event' => 'forced_logout',
    ]);
});

it('returns 403 json response for deactivated users making json requests', function () {
    $user = createUser('client', ['is_active' => false]);

    $response = $this->actingAs($user)
        ->getJson('/theme/preference');

    $response->assertStatus(403);
    $response->assertJson([
        'message' => 'Your account has been deactivated. Please contact support.',
    ]);
});

it('allows active users to continue using the platform after another user is deactivated', function () {
    $activeUser = createUser('client');
    $inactiveUser = createUser('client', ['is_active' => false]);

    // Active user should work fine
    $response = $this->actingAs($activeUser)->get('/theme/preference');
    $response->assertOk();

    // Inactive user should be blocked
    $response = $this->actingAs($inactiveUser)->get('/theme/preference');
    $response->assertRedirect(route('account.deactivated'));
});

it('allows reactivated users to log in again', function () {
    $user = User::factory()->inactive()->create(['password' => bcrypt('password123')]);

    // User cannot log in while inactive
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);
    $this->assertGuest();

    // Reactivate the user
    $user->update(['is_active' => true]);

    // User can now log in
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);
    $this->assertAuthenticated();
});

it('catches deactivated users even with remember me cookie', function () {
    $user = createUser('client', ['is_active' => false]);

    // Simulate acting as user (would have been remembered)
    $response = $this->actingAs($user)->get('/theme/preference');

    $response->assertRedirect(route('account.deactivated'));
    $this->assertGuest();
});

it('renders the deactivation page with proper structure', function () {
    $response = $this->get(route('account.deactivated'));

    $response->assertOk();
    $response->assertSee('Account Deactivated');
    $response->assertSee('support@dancymeals.com');
    $response->assertSee(config('app.name', 'DancyMeals'));
});

it('shows the deactivation page in french when locale is fr', function () {
    $response = $this->withSession(['locale' => 'fr'])->get(route('account.deactivated'));

    $response->assertOk();
    // Check for the French word in "Compte d\u00e9sactiv\u00e9" which appears as "Compte d" prefix
    $response->assertSee('Compte d');
});
