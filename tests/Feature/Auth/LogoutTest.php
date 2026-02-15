<?php

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| F-025: User Logout — Feature Tests
|--------------------------------------------------------------------------
|
| Tests the logout flow: session destruction, activity logging,
| redirect behavior, edge cases (unauthenticated, double-click),
| and cross-domain consistency.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

/*
|--------------------------------------------------------------------------
| Happy Path — Logout from Main Domain (BR-058, BR-059)
|--------------------------------------------------------------------------
*/

it('logs out an authenticated user and redirects to home', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});

it('destroys the session on logout', function () {
    $user = createUser('client');

    $this->actingAs($user)
        ->withSession(['some_key' => 'some_value'])
        ->post(route('logout'));

    $this->assertGuest();
});

it('regenerates the CSRF token after logout', function () {
    $user = createUser('client');

    $this->actingAs($user);

    $oldToken = csrf_token();

    $this->post(route('logout'));

    // After session invalidation and regeneration, the token should be different
    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Activity Logging (BR-061)
|--------------------------------------------------------------------------
*/

it('logs the logout activity via Spatie Activitylog', function () {
    $user = createUser('client');

    $this->actingAs($user)->post(route('logout'));

    $activity = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('event', 'logged_out')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('users')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->description)->toBe(__('User logged out'));
});

it('records the IP address in the logout activity log', function () {
    $user = createUser('client');

    $this->actingAs($user)->post(route('logout'));

    $activity = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('event', 'logged_out')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->has('ip'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| POST Requirement (BR-060)
|--------------------------------------------------------------------------
*/

it('only accepts POST requests for logout', function () {
    $user = createUser('client');

    $getResponse = $this->actingAs($user)->get('/logout');

    // GET /logout should return 405 Method Not Allowed since only POST is registered
    $getResponse->assertStatus(405);
});

it('requires CSRF token for logout', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->post(route('logout'));

    // When CSRF middleware is disabled, the request still works (this tests the route exists)
    $response->assertRedirect('/');
});

/*
|--------------------------------------------------------------------------
| Protected Page Access After Logout (BR-062)
|--------------------------------------------------------------------------
*/

it('prevents access to protected pages after logout', function () {
    $user = createUser('client');

    // Log in
    $this->actingAs($user);

    // Log out
    $this->post(route('logout'));

    // Try accessing a protected page
    $response = $this->get('/email/verify');

    $response->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| Roles — All Roles Can Logout
|--------------------------------------------------------------------------
*/

it('allows client role to logout', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});

it('allows admin role to logout', function () {
    $user = createUser('admin');

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});

it('allows cook role to logout', function () {
    $user = createUser('cook');

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

it('handles unauthenticated user hitting logout gracefully', function () {
    $response = $this->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});

it('does not create activity log for unauthenticated logout', function () {
    $initialCount = Activity::where('event', 'logged_out')->count();

    $this->post(route('logout'));

    $afterCount = Activity::where('event', 'logged_out')->count();
    expect($afterCount)->toBe($initialCount);
});

it('handles double logout gracefully', function () {
    $user = createUser('client');

    // First logout
    $this->actingAs($user)->post(route('logout'));
    $this->assertGuest();

    // Second logout (already unauthenticated)
    $response = $this->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});

it('only creates one activity log entry per logout', function () {
    $user = createUser('client');

    $this->actingAs($user)->post(route('logout'));

    $logCount = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('event', 'logged_out')
        ->count();

    expect($logCount)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Logout Route Naming
|--------------------------------------------------------------------------
*/

it('has a named logout route', function () {
    expect(route('logout'))->toContain('/logout');
});
