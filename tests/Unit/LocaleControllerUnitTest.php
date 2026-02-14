<?php

uses(Tests\TestCase::class);

it('lists available locales via availableLocales helper', function () {
    $locales = availableLocales();

    expect($locales)->toBeArray();
    expect($locales)->toContain('en');
    expect($locales)->toContain('fr');
});

it('generates localized column name via localized helper', function () {
    app()->setLocale('fr');
    expect(localized('name'))->toBe('name_fr');

    app()->setLocale('en');
    expect(localized('name'))->toBe('name_en');
});

it('has en and fr as the only available locales', function () {
    $locales = config('app.available_locales');

    expect($locales)->toHaveCount(2);
    expect($locales)->toBe(['en', 'fr']);
});

it('defaults to en locale', function () {
    expect(config('app.locale'))->toBe('en');
});

it('has preferred_language in User fillable', function () {
    $user = new \App\Models\User;

    expect($user->getFillable())->toContain('preferred_language');
});

it('has the SwitchLocaleRequest authorize everyone', function () {
    $request = new \App\Http\Requests\SwitchLocaleRequest;

    expect($request->authorize())->toBeTrue();
});

it('validates locale is required in SwitchLocaleRequest', function () {
    $request = new \App\Http\Requests\SwitchLocaleRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('locale');
    expect($rules['locale'])->toContain('required');
});

it('validates locale must be in available locales', function () {
    $request = new \App\Http\Requests\SwitchLocaleRequest;
    $rules = $request->rules();

    expect($rules['locale'])->toContain('string');
    $inRule = collect($rules['locale'])->first(fn ($rule) => str_starts_with($rule, 'in:'));
    expect($inRule)->toContain('en');
    expect($inRule)->toContain('fr');
});

it('has custom error messages in SwitchLocaleRequest', function () {
    $request = new \App\Http\Requests\SwitchLocaleRequest;
    $messages = $request->messages();

    expect($messages)->toHaveKey('locale.required');
    expect($messages)->toHaveKey('locale.in');
});
