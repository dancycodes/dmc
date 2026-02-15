<?php

use App\Services\ThemeService;

beforeEach(function () {
    $this->service = new ThemeService;
});

test('valid themes constant contains expected values', function () {
    expect(ThemeService::VALID_THEMES)
        ->toBe(['light', 'dark', 'system']);
});

test('default theme is system', function () {
    expect(ThemeService::DEFAULT_THEME)
        ->toBe('system');
});

test('storage key is dmc-theme', function () {
    expect(ThemeService::STORAGE_KEY)
        ->toBe('dmc-theme');
});

test('isValidTheme returns true for valid themes', function (string $theme) {
    expect($this->service->isValidTheme($theme))->toBeTrue();
})->with(['light', 'dark', 'system']);

test('isValidTheme returns true for null', function () {
    expect($this->service->isValidTheme(null))->toBeTrue();
});

test('isValidTheme returns false for invalid themes', function (string $theme) {
    expect($this->service->isValidTheme($theme))->toBeFalse();
})->with(['blue', 'auto', 'midnight', '']);

test('normalizeTheme returns null for system', function () {
    expect($this->service->normalizeTheme('system'))->toBeNull();
});

test('normalizeTheme returns null for null input', function () {
    expect($this->service->normalizeTheme(null))->toBeNull();
});

test('normalizeTheme returns light for light', function () {
    expect($this->service->normalizeTheme('light'))->toBe('light');
});

test('normalizeTheme returns dark for dark', function () {
    expect($this->service->normalizeTheme('dark'))->toBe('dark');
});

test('normalizeTheme returns null for invalid theme', function () {
    expect($this->service->normalizeTheme('purple'))->toBeNull();
});

test('resolvePreference returns system when db value is null', function () {
    expect($this->service->resolvePreference(null))->toBe('system');
});

test('resolvePreference returns stored preference when set', function (string $theme) {
    expect($this->service->resolvePreference($theme))->toBe($theme);
})->with(['light', 'dark']);

test('getDataThemeAttribute returns light for null', function () {
    expect($this->service->getDataThemeAttribute(null))->toBe('light');
});

test('getDataThemeAttribute returns light for system', function () {
    expect($this->service->getDataThemeAttribute('system'))->toBe('light');
});

test('getDataThemeAttribute returns correct value for light and dark', function (string $theme) {
    expect($this->service->getDataThemeAttribute($theme))->toBe($theme);
})->with(['light', 'dark']);

test('getDataThemeAttribute returns light for invalid value', function () {
    expect($this->service->getDataThemeAttribute('invalid'))->toBe('light');
});

test('getInlineScript returns non-empty string', function () {
    $script = $this->service->getInlineScript();

    expect($script)
        ->toBeString()
        ->not->toBeEmpty();
});

test('getInlineScript contains localStorage reference', function () {
    $script = $this->service->getInlineScript();

    expect($script)->toContain('localStorage');
});

test('getInlineScript contains storage key', function () {
    $script = $this->service->getInlineScript();

    expect($script)->toContain(ThemeService::STORAGE_KEY);
});

test('getInlineScript contains data-theme attribute setter', function () {
    $script = $this->service->getInlineScript();

    expect($script)->toContain('data-theme');
});

test('getInlineScript contains prefers-color-scheme media query', function () {
    $script = $this->service->getInlineScript();

    expect($script)->toContain('prefers-color-scheme');
});

test('getAlpineInitScript returns non-empty string', function () {
    $script = $this->service->getAlpineInitScript();

    expect($script)
        ->toBeString()
        ->not->toBeEmpty();
});

test('getAlpineInitScript contains preference property', function () {
    $script = $this->service->getAlpineInitScript();

    expect($script)->toContain('preference:');
});

test('getAlpineInitScript contains applyTheme method', function () {
    $script = $this->service->getAlpineInitScript();

    expect($script)->toContain('applyTheme()');
});

test('getAlpineInitScript contains setTheme method', function () {
    $script = $this->service->getAlpineInitScript();

    expect($script)->toContain('setTheme(theme)');
});

test('getAlpineInitScript contains media query listener for system changes', function () {
    $script = $this->service->getAlpineInitScript();

    expect($script)->toContain("addEventListener('change'");
});

test('getAlpineInitScript contains localStorage setter', function () {
    $script = $this->service->getAlpineInitScript();

    expect($script)->toContain('localStorage.setItem');
});
