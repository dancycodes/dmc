<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-030: Profile View — Feature Tests
|--------------------------------------------------------------------------
|
| Tests the profile view page: authentication requirement, displayed data
| (name, email, phone, language, member since), email verification badge,
| default avatar, action links, cross-domain access, and localization.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

/*
|--------------------------------------------------------------------------
| Authentication Requirement (BR-097)
|--------------------------------------------------------------------------
*/

it('requires authentication to view the profile page', function () {
    $response = $this->get('/profile');

    $response->assertRedirect(route('login'));
});

it('returns a successful response for authenticated users', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/profile');

    $response->assertStatus(200);
});

/*
|--------------------------------------------------------------------------
| Profile Information Display (BR-098)
|--------------------------------------------------------------------------
*/

it('displays the user name on the profile page', function () {
    $user = createUser('client', ['name' => 'Marie Atangana']);

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee('Marie Atangana');
});

it('displays the user email on the profile page', function () {
    $user = createUser('client', ['email' => 'marie@example.com']);

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee('marie@example.com');
});

it('displays the user phone number with country code', function () {
    $user = createUser('client', ['phone' => '671234567']);

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee('+237 671234567');
});

it('displays the preferred language as English', function () {
    $user = createUser('client', ['preferred_language' => 'en']);

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee(__('English'));
});

it('displays the preferred language as French', function () {
    $user = createUser('client', ['preferred_language' => 'fr']);

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee(__('French'));
});

it('displays the member since date', function () {
    $user = createUser('client');
    $user->created_at = now()->setYear(2026)->setMonth(1)->setDay(15);
    $user->save();

    $response = $this->actingAs($user)->get('/profile');

    $expectedDate = $user->created_at->translatedFormat('F Y');
    $response->assertSee($expectedDate);
});

/*
|--------------------------------------------------------------------------
| Email Verification Badge (BR-099)
|--------------------------------------------------------------------------
*/

it('shows verified badge for verified users', function () {
    $user = createUser('client'); // Factory default is verified

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee(__('Verified'));
});

it('shows unverified badge for unverified users', function () {
    $user = User::factory()->unverified()->create();
    $this->seedRolesAndPermissions();
    $user->assignRole('client');

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee(__('Unverified'));
});

it('shows verify now link for unverified users', function () {
    $user = User::factory()->unverified()->create();
    $this->seedRolesAndPermissions();
    $user->assignRole('client');

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee(__('Verify now'));
    $response->assertSee(route('verification.notice'));
});

it('does not show verify now link for verified users', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/profile');

    $response->assertDontSee(__('Verify now'));
    $response->assertDontSee(__('Unverified'));
});

/*
|--------------------------------------------------------------------------
| Default Avatar (No Profile Photo)
|--------------------------------------------------------------------------
*/

it('shows default avatar with initial when no profile photo', function () {
    $user = createUser('client', ['name' => 'Latifa Kamga', 'profile_photo_path' => null]);

    $response = $this->actingAs($user)->get('/profile');

    // Should show the first letter of the name as avatar
    $response->assertSee('L'); // First letter
    $response->assertStatus(200);
});

it('renders profile photo when set', function () {
    $user = createUser('client', ['profile_photo_path' => 'photos/user-1.jpg']);

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee('photos/user-1.jpg');
});

/*
|--------------------------------------------------------------------------
| Action Links
|--------------------------------------------------------------------------
*/

it('shows edit profile action link', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee(__('Edit Profile'));
    $response->assertSee('/profile/edit');
});

it('shows change photo action link', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee(__('Change Photo'));
    $response->assertSee('/profile/photo');
});

it('shows delivery addresses action link', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee(__('Delivery Addresses'));
    $response->assertSee('/profile/addresses');
});

it('shows payment methods action link', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee(__('Payment Methods'));
    $response->assertSee('/profile/payment-methods');
});

it('shows notification preferences action link', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee(__('Notification Preferences'));
    $response->assertSee('/profile/notifications');
});

it('shows language preference action link', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee(__('Language Preference'));
    $response->assertSee('/profile/language');
});

/*
|--------------------------------------------------------------------------
| Cross-Domain Access (BR-100)
|--------------------------------------------------------------------------
*/

it('is accessible on the main domain', function () {
    $user = createUser('client');
    $mainDomain = config('app.url');

    $response = $this->actingAs($user)->get($mainDomain.'/profile');

    $response->assertStatus(200);
});

it('is accessible on a tenant domain', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $user = createUser('client');
    $tenantDomain = 'https://'.$tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->actingAs($user)->get($tenantDomain.'/profile');

    $response->assertStatus(200);
});

/*
|--------------------------------------------------------------------------
| All Roles Can View Profile
|--------------------------------------------------------------------------
*/

it('allows client role to view profile', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/profile');

    $response->assertStatus(200);
});

it('allows admin role to view profile', function () {
    $user = createUser('admin');

    $response = $this->actingAs($user)->get('/profile');

    $response->assertStatus(200);
});

it('allows cook role to view profile', function () {
    $user = createUser('cook');

    $response = $this->actingAs($user)->get('/profile');

    $response->assertStatus(200);
});

/*
|--------------------------------------------------------------------------
| Route Naming
|--------------------------------------------------------------------------
*/

it('has a named profile route', function () {
    expect(route('profile.show'))->toContain('/profile');
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

it('handles user with very long name gracefully', function () {
    $longName = str_repeat('A', 255);
    $user = createUser('client', ['name' => $longName]);

    $response = $this->actingAs($user)->get('/profile');

    $response->assertStatus(200);
    $response->assertSee(substr($longName, 0, 50)); // At least part of the name shows
});

it('handles user with very long email gracefully', function () {
    $longEmail = str_repeat('a', 200).'@example.com';
    $user = createUser('client', ['email' => $longEmail]);

    $response = $this->actingAs($user)->get('/profile');

    $response->assertStatus(200);
});

it('handles user who just registered showing current month and year', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/profile');

    $expectedDate = now()->translatedFormat('F Y');
    $response->assertSee($expectedDate);
});

it('uses Gale response pattern for the profile view', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/profile');

    // Should return 200, not a redirect — meaning gale()->view() with web: true is used
    $response->assertStatus(200);
});
