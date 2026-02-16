<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| F-042: Language Preference Setting — Feature Tests
|--------------------------------------------------------------------------
|
| Tests for the dedicated language preference page and update endpoint.
|
| BR-184: Supported languages: "en" (English) and "fr" (French).
| BR-185: Preference stored in user's preferred_language field.
| BR-186: Application locale set based on user's preferred_language.
| BR-190: If not set, default to English ("en").
| BR-191: Setting and language switcher stay in sync.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
    $this->user = createUser();
});

/*
|--------------------------------------------------------------------------
| Show Page Tests
|--------------------------------------------------------------------------
*/

it('displays the language preference page for authenticated users', function () {
    $response = $this->actingAs($this->user)
        ->get('/profile/language');

    $response->assertOk();
    $response->assertViewIs('profile.language');
    $response->assertViewHas('currentLanguage', 'en');
});

it('shows the current language as English when preferred_language is en', function () {
    $this->user->update(['preferred_language' => 'en']);

    $response = $this->actingAs($this->user)
        ->get('/profile/language');

    $response->assertOk();
    $response->assertViewHas('currentLanguage', 'en');
});

it('shows the current language as French when preferred_language is fr', function () {
    $this->user->update(['preferred_language' => 'fr']);

    $response = $this->actingAs($this->user)
        ->get('/profile/language');

    $response->assertOk();
    $response->assertViewHas('currentLanguage', 'fr');
});

it('redirects unauthenticated users to login', function () {
    $response = $this->get('/profile/language');

    $response->assertRedirect('/login');
});

it('passes the user and tenant to the view', function () {
    $response = $this->actingAs($this->user)
        ->get('/profile/language');

    $response->assertViewHas('user');
    $response->assertViewHas('tenant');
});

it('has a named route for the language preference page', function () {
    $route = app('router')->getRoutes()->getByName('language.show');

    expect($route)->not->toBeNull();
    expect(collect($route->methods()))->toContain('GET');
    expect($route->uri())->toBe('profile/language');
});

it('has a named route for the language preference update', function () {
    $route = app('router')->getRoutes()->getByName('language.update');

    expect($route)->not->toBeNull();
    expect(collect($route->methods()))->toContain('POST');
    expect($route->uri())->toBe('profile/language');
});

it('language preference routes require authentication', function () {
    $showRoute = app('router')->getRoutes()->getByName('language.show');
    $updateRoute = app('router')->getRoutes()->getByName('language.update');

    $showMiddleware = $showRoute->gatherMiddleware();
    $updateMiddleware = $updateRoute->gatherMiddleware();

    expect(collect($showMiddleware))->toContain('auth');
    expect(collect($updateMiddleware))->toContain('auth');
});

/*
|--------------------------------------------------------------------------
| Update Tests (Traditional HTTP)
|--------------------------------------------------------------------------
*/

it('updates language preference to French via HTTP POST', function () {
    $this->user->update(['preferred_language' => 'en']);

    $response = $this->actingAs($this->user)
        ->post('/profile/language', [
            'preferred_language' => 'fr',
        ]);

    $response->assertRedirect();

    $this->user->refresh();
    expect($this->user->preferred_language)->toBe('fr');
});

it('updates language preference to English via HTTP POST', function () {
    $this->user->update(['preferred_language' => 'fr']);

    $response = $this->actingAs($this->user)
        ->post('/profile/language', [
            'preferred_language' => 'en',
        ]);

    $response->assertRedirect();

    $this->user->refresh();
    expect($this->user->preferred_language)->toBe('en');
});

it('rejects invalid language values', function () {
    $response = $this->actingAs($this->user)
        ->post('/profile/language', [
            'preferred_language' => 'de',
        ]);

    $response->assertSessionHasErrors('preferred_language');
});

it('rejects empty language value', function () {
    $response = $this->actingAs($this->user)
        ->post('/profile/language', [
            'preferred_language' => '',
        ]);

    $response->assertSessionHasErrors('preferred_language');
});

it('rejects missing language value', function () {
    $response = $this->actingAs($this->user)
        ->post('/profile/language', []);

    $response->assertSessionHasErrors('preferred_language');
});

it('updates session locale when language is changed (BR-191)', function () {
    $this->user->update(['preferred_language' => 'en']);

    $this->actingAs($this->user)
        ->post('/profile/language', [
            'preferred_language' => 'fr',
        ]);

    expect(session('locale'))->toBe('fr');
});

it('does not update database if language has not changed', function () {
    $this->user->update(['preferred_language' => 'en']);

    $response = $this->actingAs($this->user)
        ->post('/profile/language', [
            'preferred_language' => 'en',
        ]);

    $response->assertRedirect();
    $this->user->refresh();
    expect($this->user->preferred_language)->toBe('en');
});

it('prevents unauthenticated users from updating language', function () {
    $response = $this->post('/profile/language', [
        'preferred_language' => 'fr',
    ]);

    $response->assertRedirect('/login');
});

it('shows success toast after language update', function () {
    $this->user->update(['preferred_language' => 'en']);

    $response = $this->actingAs($this->user)
        ->post('/profile/language', [
            'preferred_language' => 'fr',
        ]);

    $toast = session('toast');
    expect($toast)->not->toBeNull();
    expect($toast['type'])->toBe('success');
});

/*
|--------------------------------------------------------------------------
| Activity Logging Tests
|--------------------------------------------------------------------------
*/

it('logs activity when language preference is changed', function () {
    $this->user->update(['preferred_language' => 'en']);

    // Clear any auto-logged activities from the user creation
    \Spatie\Activitylog\Models\Activity::query()->delete();

    $this->actingAs($this->user)
        ->post('/profile/language', [
            'preferred_language' => 'fr',
        ]);

    $log = \Spatie\Activitylog\Models\Activity::where('event', 'updated')
        ->where('log_name', 'users')
        ->latest()
        ->first();

    expect($log)->not->toBeNull();
    expect($log->event)->toBe('updated');
    expect($log->properties['old']['preferred_language'])->toBe('en');
    expect($log->properties['attributes']['preferred_language'])->toBe('fr');
});

it('does not log activity when language preference is unchanged', function () {
    $this->user->update(['preferred_language' => 'en']);

    // Clear any auto-logged activities
    \Spatie\Activitylog\Models\Activity::query()->delete();

    $this->actingAs($this->user)
        ->post('/profile/language', [
            'preferred_language' => 'en',
        ]);

    $logCount = \Spatie\Activitylog\Models\Activity::where('event', 'updated')
        ->where('log_name', 'users')
        ->count();

    expect($logCount)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Locale Middleware Integration Tests
|--------------------------------------------------------------------------
*/

it('sets locale based on user preferred_language on page load (BR-186)', function () {
    $this->user->update(['preferred_language' => 'fr']);

    $this->actingAs($this->user)
        ->get('/profile/language');

    expect(app()->getLocale())->toBe('fr');
});

/*
|--------------------------------------------------------------------------
| Language Switcher Sync Tests (BR-191)
|--------------------------------------------------------------------------
*/

it('language switcher updates user preferred_language for authenticated users', function () {
    $this->user->update(['preferred_language' => 'en']);

    $this->actingAs($this->user)
        ->post('/locale/switch', [
            'locale' => 'fr',
        ]);

    $this->user->refresh();
    expect($this->user->preferred_language)->toBe('fr');
});

it('language preference page reflects language switcher changes', function () {
    $this->user->update(['preferred_language' => 'en']);

    // Switch via language switcher
    $this->actingAs($this->user)
        ->post('/locale/switch', [
            'locale' => 'fr',
        ]);

    // Check the language preference page shows French
    $response = $this->actingAs($this->user)
        ->get('/profile/language');

    $response->assertViewHas('currentLanguage', 'fr');
});

/*
|--------------------------------------------------------------------------
| Edge Case Tests
|--------------------------------------------------------------------------
*/

it('rejects numeric language values', function () {
    $response = $this->actingAs($this->user)
        ->post('/profile/language', [
            'preferred_language' => '123',
        ]);

    $response->assertSessionHasErrors('preferred_language');
});

it('rejects three-letter language codes', function () {
    $response = $this->actingAs($this->user)
        ->post('/profile/language', [
            'preferred_language' => 'eng',
        ]);

    $response->assertSessionHasErrors('preferred_language');
});

it('handles rapid successive language changes correctly', function () {
    $this->user->update(['preferred_language' => 'en']);

    // Switch to French
    $this->actingAs($this->user)
        ->post('/profile/language', [
            'preferred_language' => 'fr',
        ]);

    $this->user->refresh();
    expect($this->user->preferred_language)->toBe('fr');

    // Switch back to English
    $this->actingAs($this->user)
        ->post('/profile/language', [
            'preferred_language' => 'en',
        ]);

    $this->user->refresh();
    expect($this->user->preferred_language)->toBe('en');
});
