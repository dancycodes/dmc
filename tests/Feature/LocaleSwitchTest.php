<?php

use App\Models\User;

it('switches locale to French for a guest via POST', function () {
    $response = $this->post(route('locale.switch'), [
        'locale' => 'fr',
    ]);

    $response->assertRedirect();
    expect(session('locale'))->toBe('fr');
});

it('switches locale to English for a guest via POST', function () {
    $this->session(['locale' => 'fr']);

    $response = $this->post(route('locale.switch'), [
        'locale' => 'en',
    ]);

    $response->assertRedirect();
    expect(session('locale'))->toBe('en');
});

it('rejects invalid locale value', function () {
    $response = $this->post(route('locale.switch'), [
        'locale' => 'de',
    ]);

    $response->assertSessionHasErrors('locale');
});

it('rejects missing locale value', function () {
    $response = $this->post(route('locale.switch'), []);

    $response->assertSessionHasErrors('locale');
});

it('persists locale in session for guest', function () {
    $this->post(route('locale.switch'), ['locale' => 'fr']);

    expect(session('locale'))->toBe('fr');

    // Subsequent request should have French locale set by SetLocale middleware
    $this->get('/login');
    expect(app()->getLocale())->toBe('fr');
});

it('updates preferred_language for authenticated user', function () {
    $user = User::factory()->create(['preferred_language' => 'en']);

    $this->actingAs($user)->post(route('locale.switch'), [
        'locale' => 'fr',
    ]);

    expect($user->fresh()->preferred_language)->toBe('fr');
    expect(session('locale'))->toBe('fr');
});

it('updates session locale for authenticated user', function () {
    $user = User::factory()->create(['preferred_language' => 'en']);

    $this->actingAs($user)->post(route('locale.switch'), [
        'locale' => 'fr',
    ]);

    expect(session('locale'))->toBe('fr');
});

it('allows rapid successive toggles with last one winning', function () {
    $this->post(route('locale.switch'), ['locale' => 'fr']);
    expect(session('locale'))->toBe('fr');

    $this->post(route('locale.switch'), ['locale' => 'en']);
    expect(session('locale'))->toBe('en');

    $this->post(route('locale.switch'), ['locale' => 'fr']);
    expect(session('locale'))->toBe('fr');
});

it('has the locale switch route defined', function () {
    $route = route('locale.switch');

    expect($route)->toContain('/locale/switch');
});

it('renders the language switcher component on the auth login page', function () {
    $response = $this->get(route('login'));

    $response->assertStatus(200);
    $response->assertSee('/locale/switch', false);
});

it('uses __() translation helpers for user-facing strings', function () {
    // Verify the JSON translation files have the required keys
    $enTranslations = json_decode(file_get_contents(lang_path('en.json')), true);
    $frTranslations = json_decode(file_get_contents(lang_path('fr.json')), true);

    expect($enTranslations)->toHaveKey('Language');
    expect($enTranslations)->toHaveKey('Select language');
    expect($frTranslations)->toHaveKey('Language');
    expect($frTranslations)->toHaveKey('Select language');
});
