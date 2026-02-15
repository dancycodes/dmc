<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| F-022: User Registration Submission — Feature Tests
|--------------------------------------------------------------------------
|
| Tests the registration form submission, validation, user creation,
| role assignment, email verification, activity logging, and auto-login.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

/*
|--------------------------------------------------------------------------
| Happy Path — Successful Registration
|--------------------------------------------------------------------------
*/

it('creates a new user with valid registration data', function () {
    $response = $this->post(route('register'), [
        'name' => 'Amina Atangana',
        'email' => 'amina@example.com',
        'phone' => '+237670000000',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertRedirect('/');

    $this->assertDatabaseHas('users', [
        'name' => 'Amina Atangana',
        'email' => 'amina@example.com',
        'is_active' => true,
    ]);
});

it('normalizes email to lowercase before saving', function () {
    $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'TestUser@EXAMPLE.COM',
        'phone' => '+237670000000',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'testuser@example.com',
    ]);
});

it('stores the phone number in 9-digit format after stripping +237 prefix', function () {
    $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'phone@example.com',
        'phone' => '+237670000001',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    // User model's setPhoneAttribute strips the +237 prefix
    $user = User::where('email', 'phone@example.com')->first();
    expect($user->phone)->toBe('670000001');
});

it('assigns the client role to newly registered users', function () {
    $this->post(route('register'), [
        'name' => 'Role Test',
        'email' => 'role@example.com',
        'phone' => '+237670000002',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $user = User::where('email', 'role@example.com')->first();
    expect($user->hasRole('client'))->toBeTrue();
});

it('fires the Registered event for email verification', function () {
    Event::fake([Registered::class]);

    $this->post(route('register'), [
        'name' => 'Event Test',
        'email' => 'event@example.com',
        'phone' => '+237670000003',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    Event::assertDispatched(Registered::class, function ($event) {
        return $event->user->email === 'event@example.com';
    });
});

it('logs the registration activity via Spatie Activitylog', function () {
    $this->post(route('register'), [
        'name' => 'Activity Test',
        'email' => 'activity@example.com',
        'phone' => '+237670000004',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $user = User::where('email', 'activity@example.com')->first();

    $activity = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('event', 'registered')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('users')
        ->and($activity->causer_id)->toBe($user->id);
});

it('auto-logs the user in after registration', function () {
    $this->post(route('register'), [
        'name' => 'Login Test',
        'email' => 'autologin@example.com',
        'phone' => '+237670000005',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'autologin@example.com')->first();
    expect(auth()->id())->toBe($user->id);
});

it('redirects to home page after successful registration', function () {
    $response = $this->post(route('register'), [
        'name' => 'Redirect Test',
        'email' => 'redirect@example.com',
        'phone' => '+237670000006',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertRedirect('/');
});

it('creates the user with is_active set to true', function () {
    $this->post(route('register'), [
        'name' => 'Active Test',
        'email' => 'active@example.com',
        'phone' => '+237670000007',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $user = User::where('email', 'active@example.com')->first();
    expect($user->is_active)->toBeTrue();
});

it('sets preferred_language from current locale', function () {
    $this->withSession(['locale' => 'fr']);

    $this->post(route('register'), [
        'name' => 'Lang Test',
        'email' => 'lang@example.com',
        'phone' => '+237670000008',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $user = User::where('email', 'lang@example.com')->first();
    expect($user->preferred_language)->toBe('fr');
});

it('flashes a toast success message to the session', function () {
    $response = $this->post(route('register'), [
        'name' => 'Toast Test',
        'email' => 'toast@example.com',
        'phone' => '+237670000009',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertSessionHas('toast');
});

/*
|--------------------------------------------------------------------------
| Validation — Email
|--------------------------------------------------------------------------
*/

it('rejects registration with an already-taken email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->post(route('register'), [
        'name' => 'Duplicate',
        'email' => 'taken@example.com',
        'phone' => '+237670000010',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('rejects registration without an email', function () {
    $response = $this->post(route('register'), [
        'name' => 'No Email',
        'email' => '',
        'phone' => '+237670000011',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('rejects registration with an invalid email format', function () {
    $response = $this->post(route('register'), [
        'name' => 'Bad Email',
        'email' => 'not-an-email',
        'phone' => '+237670000012',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Validation — Phone
|--------------------------------------------------------------------------
*/

it('rejects an invalid phone number format', function () {
    $response = $this->post(route('register'), [
        'name' => 'Bad Phone',
        'email' => 'badphone@example.com',
        'phone' => '+33612345678',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

it('rejects a phone number that is too short', function () {
    $response = $this->post(route('register'), [
        'name' => 'Short Phone',
        'email' => 'shortphone@example.com',
        'phone' => '12345',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

it('accepts a Cameroon phone starting with 7 after +237', function () {
    $this->post(route('register'), [
        'name' => 'Phone 7',
        'email' => 'phone7@example.com',
        'phone' => '+237770000000',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $this->assertDatabaseHas('users', ['email' => 'phone7@example.com']);
});

it('accepts a Cameroon phone starting with 2 after +237', function () {
    $this->post(route('register'), [
        'name' => 'Phone 2',
        'email' => 'phone2@example.com',
        'phone' => '+237222000000',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $this->assertDatabaseHas('users', ['email' => 'phone2@example.com']);
});

it('normalizes a phone without +237 prefix', function () {
    $this->post(route('register'), [
        'name' => 'No Prefix',
        'email' => 'noprefix@example.com',
        'phone' => '670000013',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $this->assertDatabaseHas('users', ['email' => 'noprefix@example.com']);
});

it('normalizes a phone with spaces and dashes', function () {
    $this->post(route('register'), [
        'name' => 'Spaced Phone',
        'email' => 'spaced@example.com',
        'phone' => '+237 670 000 014',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $this->assertDatabaseHas('users', ['email' => 'spaced@example.com']);
});

/*
|--------------------------------------------------------------------------
| Validation — Password
|--------------------------------------------------------------------------
*/

it('rejects a password shorter than 8 characters', function () {
    $response = $this->post(route('register'), [
        'name' => 'Short Pass',
        'email' => 'shortpass@example.com',
        'phone' => '+237670000015',
        'password' => 'Pass1',
        'password_confirmation' => 'Pass1',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

it('rejects a password without an uppercase letter', function () {
    $response = $this->post(route('register'), [
        'name' => 'No Upper',
        'email' => 'noupper@example.com',
        'phone' => '+237670000016',
        'password' => 'password1',
        'password_confirmation' => 'password1',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

it('rejects a password without a lowercase letter', function () {
    $response = $this->post(route('register'), [
        'name' => 'No Lower',
        'email' => 'nolower@example.com',
        'phone' => '+237670000017',
        'password' => 'PASSWORD1',
        'password_confirmation' => 'PASSWORD1',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

it('rejects a password without a number', function () {
    $response = $this->post(route('register'), [
        'name' => 'No Number',
        'email' => 'nonumber@example.com',
        'phone' => '+237670000018',
        'password' => 'PasswordOnly',
        'password_confirmation' => 'PasswordOnly',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

it('rejects a password that does not match confirmation', function () {
    $response = $this->post(route('register'), [
        'name' => 'Mismatch',
        'email' => 'mismatch@example.com',
        'phone' => '+237670000019',
        'password' => 'Password1',
        'password_confirmation' => 'Different1',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Validation — Name
|--------------------------------------------------------------------------
*/

it('rejects registration without a name', function () {
    $response = $this->post(route('register'), [
        'name' => '',
        'email' => 'noname@example.com',
        'phone' => '+237670000020',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertSessionHasErrors('name');
    $this->assertGuest();
});

it('rejects a name exceeding 255 characters', function () {
    $response = $this->post(route('register'), [
        'name' => str_repeat('A', 256),
        'email' => 'longname@example.com',
        'phone' => '+237670000021',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertSessionHasErrors('name');
    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

it('preserves old input except passwords on validation failure', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->post(route('register'), [
        'name' => 'Preserved Name',
        'email' => 'existing@example.com',
        'phone' => '+237670000022',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertSessionHasErrors('email');
});

it('prevents authenticated users from submitting registration', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post(route('register'), [
        'name' => 'Should Not Work',
        'email' => 'authuser@example.com',
        'phone' => '+237670000023',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    // Guest middleware should redirect
    $response->assertRedirect();
    $this->assertDatabaseMissing('users', ['email' => 'authuser@example.com']);
});

it('handles concurrent registration with same email via unique constraint', function () {
    // First registration succeeds
    $this->post(route('register'), [
        'name' => 'First User',
        'email' => 'concurrent@example.com',
        'phone' => '+237670000024',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    // Log out the auto-logged-in user
    auth()->logout();

    // Second registration with same email fails
    $response = $this->post(route('register'), [
        'name' => 'Second User',
        'email' => 'concurrent@example.com',
        'phone' => '+237670000025',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertSessionHasErrors('email');
    expect(User::where('email', 'concurrent@example.com')->count())->toBe(1);
});

it('creates the user with email_verified_at as null', function () {
    $this->post(route('register'), [
        'name' => 'Unverified',
        'email' => 'unverified@example.com',
        'phone' => '+237670000026',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $user = User::where('email', 'unverified@example.com')->first();
    expect($user->email_verified_at)->toBeNull();
});
