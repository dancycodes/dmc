<?php

use App\Services\ThemeService;

// Project root for file path resolution in unit tests (no Laravel app boot)
$projectRoot = dirname(__DIR__, 2);

beforeEach(function () use ($projectRoot) {
    $this->projectRoot = $projectRoot;
    $this->componentPath = $projectRoot.'/resources/views/components/theme-switcher.blade.php';
    $this->componentContent = file_get_contents($this->componentPath);
});

test('theme switcher component blade file exists', function () {
    expect(file_exists($this->componentPath))->toBeTrue();
});

test('theme switcher component contains all three theme options', function () {
    expect($this->componentContent)
        ->toContain("switchTheme('light')")
        ->toContain("switchTheme('dark')")
        ->toContain("switchTheme('system')");
});

test('theme switcher component uses translation helpers for all labels', function () {
    expect($this->componentContent)
        ->toContain("__('Light')")
        ->toContain("__('Dark')")
        ->toContain("__('System')")
        ->toContain("__('Light mode')")
        ->toContain("__('Dark mode')")
        ->toContain("__('System default')")
        ->toContain("__('Theme')");
});

test('theme switcher component contains sun icon for light mode', function () {
    expect($this->componentContent)->toContain('<circle cx="12" cy="12" r="4"></circle>');
});

test('theme switcher component contains moon icon for dark mode', function () {
    expect($this->componentContent)->toContain('M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z');
});

test('theme switcher component contains monitor icon for system mode', function () {
    expect($this->componentContent)->toContain('<rect width="20" height="14" x="2" y="3" rx="2"></rect>');
});

test('theme switcher component uses semantic color tokens', function () {
    expect($this->componentContent)
        ->toContain('bg-primary-subtle')
        ->toContain('text-primary')
        ->toContain('text-on-surface')
        ->toContain('border-outline')
        ->toContain('bg-surface');
});

test('theme switcher component includes dark mode variants', function () {
    expect($this->componentContent)
        ->toContain('dark:bg-surface-alt')
        ->toContain('dark:border-outline')
        ->toContain('dark:text-on-surface')
        ->toContain('dark:text-primary');
});

test('theme switcher component has accessibility attributes', function () {
    expect($this->componentContent)
        ->toContain('role="radiogroup"')
        ->toContain('role="radio"')
        ->toContain(':aria-checked')
        ->toContain('aria-label');
});

test('theme switcher component uses localStorage for persistence', function () {
    expect($this->componentContent)->toContain('localStorage.getItem');
});

test('theme switcher component includes Gale action for authenticated users', function () {
    expect($this->componentContent)->toContain('$action');
});

test('theme switcher component has noscript fallback', function () {
    expect($this->componentContent)->toContain('<noscript>');
});

test('theme switcher component references theme update route', function () {
    expect($this->componentContent)->toContain("route('theme.update')");
});

test('translation files contain theme switcher strings', function () {
    $en = json_decode(file_get_contents($this->projectRoot.'/lang/en.json'), true);
    $fr = json_decode(file_get_contents($this->projectRoot.'/lang/fr.json'), true);

    $requiredKeys = ['Theme', 'Light', 'Dark', 'System', 'Light mode', 'Dark mode', 'System default'];

    foreach ($requiredKeys as $key) {
        expect($en)->toHaveKey($key);
        expect($fr)->toHaveKey($key);
    }
});

test('french translations are properly set for theme strings', function () {
    $fr = json_decode(file_get_contents($this->projectRoot.'/lang/fr.json'), true);

    expect($fr['Light'])->toBe('Clair');
    expect($fr['Dark'])->toBe('Sombre');
    expect($fr['System'])->toBe("Syst\u{00e8}me");
    expect($fr['Light mode'])->toBe('Mode clair');
    expect($fr['Dark mode'])->toBe('Mode sombre');
});

test('theme service valid themes match switcher options', function () {
    expect(ThemeService::VALID_THEMES)->toBe(['light', 'dark', 'system']);
});
