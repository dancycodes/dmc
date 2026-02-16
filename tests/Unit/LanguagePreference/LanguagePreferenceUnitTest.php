<?php

use App\Http\Requests\Profile\UpdateLanguagePreferenceRequest;

/*
|--------------------------------------------------------------------------
| F-042: Language Preference Setting — Unit Tests
|--------------------------------------------------------------------------
|
| Unit tests for the UpdateLanguagePreferenceRequest validation rules,
| view file existence, route definitions, and controller structure.
|
*/

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| View File Existence
|--------------------------------------------------------------------------
*/

it('has a language preference view file', function () use ($projectRoot) {
    expect(file_exists($projectRoot.'/resources/views/profile/language.blade.php'))->toBeTrue();
});

it('has the LanguagePreferenceController file', function () use ($projectRoot) {
    expect(file_exists($projectRoot.'/app/Http/Controllers/LanguagePreferenceController.php'))->toBeTrue();
});

it('has the UpdateLanguagePreferenceRequest file', function () use ($projectRoot) {
    expect(file_exists($projectRoot.'/app/Http/Requests/Profile/UpdateLanguagePreferenceRequest.php'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Form Request Validation Rules
|--------------------------------------------------------------------------
*/

it('UpdateLanguagePreferenceRequest authorizes authenticated users', function () {
    $request = new UpdateLanguagePreferenceRequest;
    expect($request->authorize())->toBeTrue();
});

it('UpdateLanguagePreferenceRequest has preferred_language rule', function () {
    $request = new UpdateLanguagePreferenceRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('preferred_language');
    expect($rules['preferred_language'])->toContain('required');
    expect($rules['preferred_language'])->toContain('string');
});

it('UpdateLanguagePreferenceRequest validates only en and fr (BR-184)', function () {
    $request = new UpdateLanguagePreferenceRequest;
    $rules = $request->rules();

    // The in:en,fr rule should be present
    $inRule = collect($rules['preferred_language'])->first(fn ($r) => str_starts_with($r, 'in:'));
    expect($inRule)->toBe('in:en,fr');
});

it('UpdateLanguagePreferenceRequest has custom error messages defined', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/app/Http/Requests/Profile/UpdateLanguagePreferenceRequest.php');

    expect($content)->toContain('preferred_language.required');
    expect($content)->toContain('preferred_language.in');
    expect($content)->toContain('messages()');
});

/*
|--------------------------------------------------------------------------
| View Content Checks
|--------------------------------------------------------------------------
*/

it('language view includes English option', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/language.blade.php');

    expect($content)->toContain('value="en"');
    expect($content)->toContain("__('English')");
});

it('language view includes French option', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/language.blade.php');

    expect($content)->toContain('value="fr"');
    expect($content)->toContain("__('French')");
});

it('language view uses Gale $action for form submission', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/language.blade.php');

    expect($content)->toContain('$action(');
    expect($content)->toContain('@submit.prevent');
});

it('language view includes x-sync for Gale state', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/language.blade.php');

    expect($content)->toContain('x-sync');
});

it('language view has back link to profile', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/language.blade.php');

    expect($content)->toContain("url('/profile')");
    expect($content)->toContain("__('Back to Profile')");
});

it('language view uses x-message for validation errors', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/language.blade.php');

    expect($content)->toContain('x-message="preferred_language"');
});

it('language view uses semantic color tokens', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/language.blade.php');

    expect($content)->toContain('bg-surface-alt');
    expect($content)->toContain('text-on-surface-strong');
    expect($content)->toContain('bg-primary');
    expect($content)->toContain('text-primary');
    expect($content)->toContain('border-outline');
});

it('language view uses dark mode variants', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/language.blade.php');

    expect($content)->toContain('dark:');
});

it('language view has loading state for submit button', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/language.blade.php');

    expect($content)->toContain('$fetching()');
    expect($content)->toContain("__('Saving...')");
});

it('language view extends the correct layout', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/language.blade.php');

    expect($content)->toContain("@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')");
});

/*
|--------------------------------------------------------------------------
| Translation Keys
|--------------------------------------------------------------------------
*/

it('has English translations for language preference strings', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

    expect($translations)->toHaveKey('Language Preference');
    expect($translations)->toHaveKey('Save Language');
    expect($translations)->toHaveKey('Language preference updated. The change will apply on the next page.');
    expect($translations)->toHaveKey('Select your preferred language');
});

it('has French translations for language preference strings', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    expect($translations)->toHaveKey('Language Preference');
    expect($translations)->toHaveKey('Save Language');
    expect($translations)->toHaveKey('Language preference updated. The change will apply on the next page.');
    expect($translations)->toHaveKey('Select your preferred language');
});

/*
|--------------------------------------------------------------------------
| Controller File Structure
|--------------------------------------------------------------------------
*/

it('controller has show method', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/app/Http/Controllers/LanguagePreferenceController.php');

    expect($content)->toContain('public function show(');
    expect($content)->toContain("gale()->view('profile.language'");
});

it('controller has update method', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/app/Http/Controllers/LanguagePreferenceController.php');

    expect($content)->toContain('public function update(');
    expect($content)->toContain('isGale()');
    expect($content)->toContain('validateState(');
});

it('controller uses activity logging', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/app/Http/Controllers/LanguagePreferenceController.php');

    expect($content)->toContain("activity('users')");
    expect($content)->toContain('->performedOn($user)');
    expect($content)->toContain('->causedBy($user)');
});
