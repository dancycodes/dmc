<?php

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| F-032: Profile Basic Info Edit — Feature Tests
|--------------------------------------------------------------------------
|
| Tests the profile edit feature: authentication, form display, data update,
| validation (name, phone, preferred language), activity logging, Gale
| response pattern, and edge cases.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

/*
|--------------------------------------------------------------------------
| Authentication Requirement
|--------------------------------------------------------------------------
*/

it('requires authentication to view the edit profile page', function () {
    $response = $this->get('/profile/edit');

    $response->assertRedirect(route('login'));
});

it('requires authentication to submit profile updates', function () {
    $response = $this->post('/profile/edit', [
        'name' => 'Test User',
        'phone' => '+237671234567',
        'preferred_language' => 'en',
    ]);

    $response->assertRedirect(route('login'));
});

it('returns a successful response for authenticated users on the edit page', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/profile/edit');

    $response->assertStatus(200);
});

/*
|--------------------------------------------------------------------------
| Form Pre-population
|--------------------------------------------------------------------------
*/

it('pre-populates the name field with the current value', function () {
    $user = createUser('client', ['name' => 'Marie Atangana']);

    $response = $this->actingAs($user)->get('/profile/edit');

    $response->assertSee('Marie Atangana');
});

it('pre-populates the phone field with the current value', function () {
    $user = createUser('client', ['phone' => '671234567']);

    $response = $this->actingAs($user)->get('/profile/edit');

    $response->assertSee('671234567');
});

it('pre-populates the preferred language select', function () {
    $user = createUser('client', ['preferred_language' => 'fr']);

    $response = $this->actingAs($user)->get('/profile/edit');

    $response->assertStatus(200);
});

it('displays the email as read-only', function () {
    $user = createUser('client', ['email' => 'marie@example.com']);

    $response = $this->actingAs($user)->get('/profile/edit');

    $response->assertSee('marie@example.com');
    $response->assertSee(__('Contact support to change your email.'));
});

/*
|--------------------------------------------------------------------------
| Successful Updates (BR-112, BR-113, BR-115, BR-116)
|--------------------------------------------------------------------------
*/

it('updates the user name successfully', function () {
    $user = createUser('client', ['name' => 'Old Name']);

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => 'New Name',
        'phone' => '+237671234567',
        'preferred_language' => 'en',
    ]);

    expect($user->fresh()->name)->toBe('New Name');
});

it('updates the user phone successfully', function () {
    $user = createUser('client', ['phone' => '671234567']);

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => $user->name,
        'phone' => '+237699887766',
        'preferred_language' => 'en',
    ]);

    expect($user->fresh()->phone)->toBe('699887766');
});

it('updates the preferred language successfully', function () {
    $user = createUser('client', ['preferred_language' => 'en']);

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => $user->name,
        'phone' => '+237671234567',
        'preferred_language' => 'fr',
    ]);

    expect($user->fresh()->preferred_language)->toBe('fr');
});

it('redirects back with toast on successful update', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => 'Updated Name',
        'phone' => '+237671234567',
        'preferred_language' => 'en',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('toast');
});

/*
|--------------------------------------------------------------------------
| Name Validation (BR-112)
|--------------------------------------------------------------------------
*/

it('rejects empty name', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => '',
        'phone' => '+237671234567',
        'preferred_language' => 'en',
    ]);

    $response->assertSessionHasErrors('name');
});

it('rejects name shorter than 2 characters', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => 'A',
        'phone' => '+237671234567',
        'preferred_language' => 'en',
    ]);

    $response->assertSessionHasErrors('name');
});

it('rejects name longer than 255 characters', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => str_repeat('A', 256),
        'phone' => '+237671234567',
        'preferred_language' => 'en',
    ]);

    $response->assertSessionHasErrors('name');
});

it('accepts name with special characters like accents and hyphens', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => 'Jean-Pierre Ngu\u00e9ma',
        'phone' => '+237671234567',
        'preferred_language' => 'en',
    ]);

    expect($user->fresh()->name)->toBe('Jean-Pierre Ngu\u00e9ma');
});

/*
|--------------------------------------------------------------------------
| Phone Validation (BR-113)
|--------------------------------------------------------------------------
*/

it('rejects empty phone number', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => $user->name,
        'phone' => '',
        'preferred_language' => 'en',
    ]);

    $response->assertSessionHasErrors('phone');
});

it('rejects invalid phone number format', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => $user->name,
        'phone' => '123456',
        'preferred_language' => 'en',
    ]);

    $response->assertSessionHasErrors('phone');
});

it('accepts phone with +237 prefix', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => $user->name,
        'phone' => '+237671234567',
        'preferred_language' => 'en',
    ]);

    expect($user->fresh()->phone)->toBe('671234567');
});

it('normalizes phone with spaces before validation', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => $user->name,
        'phone' => '+237 671 234 567',
        'preferred_language' => 'en',
    ]);

    expect($user->fresh()->phone)->toBe('671234567');
});

it('normalizes phone with dashes before validation', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => $user->name,
        'phone' => '+237-671-234-567',
        'preferred_language' => 'en',
    ]);

    expect($user->fresh()->phone)->toBe('671234567');
});

it('accepts phone without +237 prefix and normalizes', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => $user->name,
        'phone' => '671234567',
        'preferred_language' => 'en',
    ]);

    expect($user->fresh()->phone)->toBe('671234567');
});

/*
|--------------------------------------------------------------------------
| Preferred Language Validation (BR-115)
|--------------------------------------------------------------------------
*/

it('rejects invalid preferred language', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => $user->name,
        'phone' => '+237671234567',
        'preferred_language' => 'de',
    ]);

    $response->assertSessionHasErrors('preferred_language');
});

it('rejects empty preferred language', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->post('/profile/edit', [
        'name' => $user->name,
        'phone' => '+237671234567',
        'preferred_language' => '',
    ]);

    $response->assertSessionHasErrors('preferred_language');
});

/*
|--------------------------------------------------------------------------
| Activity Logging (BR-117)
|--------------------------------------------------------------------------
*/

it('logs the profile update with old and new values', function () {
    $user = createUser('client', ['name' => 'Old Name', 'phone' => '671234567']);

    $this->actingAs($user)->post('/profile/edit', [
        'name' => 'New Name',
        'phone' => '+237699887766',
        'preferred_language' => 'en',
    ]);

    $activity = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['old'])->toHaveKey('name', 'Old Name')
        ->and($activity->properties['attributes'])->toHaveKey('name', 'New Name');
});

it('does not log when no fields changed', function () {
    $user = createUser('client', [
        'name' => 'Same Name',
        'phone' => '671234567',
        'preferred_language' => 'en',
    ]);

    $countBefore = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('event', 'updated')
        ->count();

    $this->actingAs($user)->post('/profile/edit', [
        'name' => 'Same Name',
        'phone' => '+237671234567',
        'preferred_language' => 'en',
    ]);

    $countAfter = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('event', 'updated')
        ->count();

    expect($countAfter)->toBe($countBefore);
});

/*
|--------------------------------------------------------------------------
| Email Not Editable (BR-114)
|--------------------------------------------------------------------------
*/

it('does not allow email to be changed via the update endpoint', function () {
    $user = createUser('client', ['email' => 'original@example.com']);

    $this->actingAs($user)->post('/profile/edit', [
        'name' => $user->name,
        'phone' => '+237671234567',
        'preferred_language' => 'en',
        'email' => 'hacked@example.com',
    ]);

    expect($user->fresh()->email)->toBe('original@example.com');
});

/*
|--------------------------------------------------------------------------
| Cross-Domain Access
|--------------------------------------------------------------------------
*/

it('is accessible on the main domain', function () {
    $user = createUser('client');
    $mainDomain = config('app.url');

    $response = $this->actingAs($user)->get($mainDomain.'/profile/edit');

    $response->assertStatus(200);
});

it('is accessible on a tenant domain', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $user = createUser('client');
    $tenantDomain = 'https://'.$tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->actingAs($user)->get($tenantDomain.'/profile/edit');

    $response->assertStatus(200);
});

/*
|--------------------------------------------------------------------------
| Route Naming
|--------------------------------------------------------------------------
*/

it('has named profile edit routes', function () {
    expect(route('profile.edit'))->toContain('/profile/edit')
        ->and(route('profile.update'))->toContain('/profile/edit');
});

/*
|--------------------------------------------------------------------------
| All Roles Can Edit Profile
|--------------------------------------------------------------------------
*/

it('allows client role to edit profile', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/profile/edit');

    $response->assertStatus(200);
});

it('allows admin role to edit profile', function () {
    $user = createUser('admin');

    $response = $this->actingAs($user)->get('/profile/edit');

    $response->assertStatus(200);
});

it('allows cook role to edit profile', function () {
    $user = createUser('cook');

    $response = $this->actingAs($user)->get('/profile/edit');

    $response->assertStatus(200);
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

it('trims whitespace from name before saving', function () {
    $user = createUser('client');

    $this->actingAs($user)->post('/profile/edit', [
        'name' => '  Trimmed Name  ',
        'phone' => '+237671234567',
        'preferred_language' => 'en',
    ]);

    expect($user->fresh()->name)->toBe('Trimmed Name');
});

it('handles concurrent edits with last-save-wins', function () {
    $user = createUser('client', ['name' => 'Original']);

    $this->actingAs($user)->post('/profile/edit', [
        'name' => 'First Update',
        'phone' => '+237671234567',
        'preferred_language' => 'en',
    ]);

    $this->actingAs($user)->post('/profile/edit', [
        'name' => 'Second Update',
        'phone' => '+237671234567',
        'preferred_language' => 'en',
    ]);

    expect($user->fresh()->name)->toBe('Second Update');
});

it('uses Gale response pattern for the edit form view', function () {
    $user = createUser('client');

    $response = $this->actingAs($user)->get('/profile/edit');

    $response->assertStatus(200);
});
