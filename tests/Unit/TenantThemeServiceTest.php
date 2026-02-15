<?php

use App\Models\Tenant;
use App\Services\TenantThemeService;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->service = new TenantThemeService;
});

// --- Preset Resolution ---

test('resolvePreset returns default modern for null tenant', function () {
    expect($this->service->resolvePreset(null))->toBe('modern');
});

test('resolvePreset returns configured preset for tenant with valid theme', function () {
    $tenant = Tenant::factory()->withThemePreset('arctic')->make();

    expect($this->service->resolvePreset($tenant))->toBe('arctic');
});

test('resolvePreset falls back to default for invalid preset', function () {
    $tenant = Tenant::factory()->make(['settings' => ['theme' => 'nonexistent']]);

    expect($this->service->resolvePreset($tenant))->toBe('modern');
});

test('resolvePreset falls back to default when settings is empty', function () {
    $tenant = Tenant::factory()->make(['settings' => []]);

    expect($this->service->resolvePreset($tenant))->toBe('modern');
});

test('resolvePreset falls back to default when settings is null', function () {
    $tenant = Tenant::factory()->make(['settings' => null]);

    expect($this->service->resolvePreset($tenant))->toBe('modern');
});

// --- Font Resolution ---

test('resolveFont returns default inter for null tenant', function () {
    expect($this->service->resolveFont(null))->toBe('inter');
});

test('resolveFont returns configured font for tenant with valid font', function () {
    $tenant = Tenant::factory()->withFont('poppins')->make();

    expect($this->service->resolveFont($tenant))->toBe('poppins');
});

test('resolveFont falls back to default for invalid font', function () {
    $tenant = Tenant::factory()->make(['settings' => ['font' => 'comic-sans']]);

    expect($this->service->resolveFont($tenant))->toBe('inter');
});

// --- Radius Resolution ---

test('resolveRadius returns default medium for null tenant', function () {
    expect($this->service->resolveRadius(null))->toBe('medium');
});

test('resolveRadius returns configured radius for tenant with valid radius', function () {
    $tenant = Tenant::factory()->withBorderRadius('none')->make();

    expect($this->service->resolveRadius($tenant))->toBe('none');
});

test('resolveRadius falls back to default for invalid radius', function () {
    $tenant = Tenant::factory()->make(['settings' => ['border_radius' => 'huge']]);

    expect($this->service->resolveRadius($tenant))->toBe('medium');
});

// --- Inline CSS Generation ---

test('generateInlineCss returns empty string for null tenant', function () {
    expect($this->service->generateInlineCss(null))->toBe('');
});

test('generateInlineCss returns empty string for tenant with all defaults', function () {
    $tenant = Tenant::factory()->make(['settings' => []]);

    expect($this->service->generateInlineCss($tenant))->toBe('');
});

test('generateInlineCss returns CSS with color overrides for custom preset', function () {
    $tenant = Tenant::factory()->withThemePreset('arctic')->make();

    $css = $this->service->generateInlineCss($tenant);

    expect($css)
        ->toContain(':root')
        ->toContain('--color-primary')
        ->toContain('[data-theme="dark"]');
});

test('generateInlineCss includes font override for custom font', function () {
    $tenant = Tenant::factory()->withFont('poppins')->make();

    $css = $this->service->generateInlineCss($tenant);

    expect($css)
        ->toContain('--font-sans')
        ->toContain('Poppins');
});

test('generateInlineCss includes radius override for custom radius', function () {
    $tenant = Tenant::factory()->withBorderRadius('none')->make();

    $css = $this->service->generateInlineCss($tenant);

    expect($css)
        ->toContain('--radius-md')
        ->toContain('0px');
});

test('generateInlineCss handles complete custom theme', function () {
    $tenant = Tenant::factory()
        ->withTheme('neo-brutalism', 'montserrat', 'none')
        ->make();

    $css = $this->service->generateInlineCss($tenant);

    expect($css)
        ->toContain('--color-primary')
        ->toContain('--font-sans')
        ->toContain('Montserrat')
        ->toContain('--radius-md')
        ->toContain('0px')
        ->toContain('[data-theme="dark"]');
});

test('generateInlineCss does not include dark overrides for default preset', function () {
    $tenant = Tenant::factory()->withFont('roboto')->make();

    $css = $this->service->generateInlineCss($tenant);

    // Should have root for font, but no dark overrides since preset is default
    expect($css)
        ->toContain(':root')
        ->not->toContain('[data-theme="dark"]');
});

// --- Font Link Tag ---

test('getFontLinkTag returns empty string for null tenant', function () {
    expect($this->service->getFontLinkTag(null))->toBe('');
});

test('getFontLinkTag returns empty string for default font', function () {
    $tenant = Tenant::factory()->make(['settings' => ['font' => 'inter']]);

    expect($this->service->getFontLinkTag($tenant))->toBe('');
});

test('getFontLinkTag returns link tag for custom font', function () {
    $tenant = Tenant::factory()->withFont('poppins')->make();

    $link = $this->service->getFontLinkTag($tenant);

    expect($link)
        ->toContain('<link')
        ->toContain('Poppins')
        ->toContain('fonts.googleapis.com');
});

// --- Theme Config Resolution ---

test('resolveThemeConfig returns complete config for null tenant', function () {
    $config = $this->service->resolveThemeConfig(null);

    expect($config)
        ->toHaveKeys(['preset', 'font', 'radius', 'preset_label', 'font_label', 'radius_label'])
        ->and($config['preset'])->toBe('modern')
        ->and($config['font'])->toBe('inter')
        ->and($config['radius'])->toBe('medium')
        ->and($config['preset_label'])->toBe('Modern')
        ->and($config['font_label'])->toBe('Inter')
        ->and($config['radius_label'])->toBe('Medium');
});

test('resolveThemeConfig returns custom config for themed tenant', function () {
    $tenant = Tenant::factory()
        ->withTheme('arctic', 'poppins', 'large')
        ->make();

    $config = $this->service->resolveThemeConfig($tenant);

    expect($config['preset'])->toBe('arctic')
        ->and($config['font'])->toBe('poppins')
        ->and($config['radius'])->toBe('large')
        ->and($config['preset_label'])->toBe('Arctic')
        ->and($config['font_label'])->toBe('Poppins')
        ->and($config['radius_label'])->toBe('Large');
});

// --- Available Options ---

test('availablePresets returns all configured presets', function () {
    $presets = $this->service->availablePresets();

    expect($presets)
        ->toHaveKeys(['modern', 'arctic', 'high-contrast', 'minimal', 'neo-brutalism', 'ocean', 'forest', 'sunset', 'violet'])
        ->and($presets['modern']['label'])->toBe('Modern')
        ->and($presets['arctic']['label'])->toBe('Arctic');
});

test('availableFonts returns all configured fonts', function () {
    $fonts = $this->service->availableFonts();

    expect($fonts)
        ->toHaveKeys(['inter', 'roboto', 'poppins', 'nunito', 'open-sans', 'montserrat'])
        ->and($fonts['inter']['label'])->toBe('Inter')
        ->and($fonts['poppins']['label'])->toBe('Poppins');
});

test('availableRadii returns all configured radii', function () {
    $radii = $this->service->availableRadii();

    expect($radii)
        ->toHaveKeys(['none', 'small', 'medium', 'large', 'full'])
        ->and($radii['none']['label'])->toBe('Sharp')
        ->and($radii['full']['label'])->toBe('Pill');
});

// --- Validation ---

test('isValidPreset accepts all configured presets', function (string $preset) {
    expect($this->service->isValidPreset($preset))->toBeTrue();
})->with(['modern', 'arctic', 'high-contrast', 'minimal', 'neo-brutalism', 'ocean', 'forest', 'sunset', 'violet']);

test('isValidPreset rejects unknown presets', function (string $preset) {
    expect($this->service->isValidPreset($preset))->toBeFalse();
})->with(['nonexistent', '', 'MODERN', 'default']);

test('isValidFont accepts all configured fonts', function (string $font) {
    expect($this->service->isValidFont($font))->toBeTrue();
})->with(['inter', 'roboto', 'poppins', 'nunito', 'open-sans', 'montserrat']);

test('isValidFont rejects unknown fonts', function (string $font) {
    expect($this->service->isValidFont($font))->toBeFalse();
})->with(['comic-sans', '', 'Arial', 'INTER']);

test('isValidRadius accepts all configured radii', function (string $radius) {
    expect($this->service->isValidRadius($radius))->toBeTrue();
})->with(['none', 'small', 'medium', 'large', 'full']);

test('isValidRadius rejects unknown radii', function (string $radius) {
    expect($this->service->isValidRadius($radius))->toBeFalse();
})->with(['huge', '', 'extra-large', 'NONE']);

// --- Defaults ---

test('defaultPreset returns modern', function () {
    expect($this->service->defaultPreset())->toBe('modern');
});

test('defaultFont returns inter', function () {
    expect($this->service->defaultFont())->toBe('inter');
});

test('defaultRadius returns medium', function () {
    expect($this->service->defaultRadius())->toBe('medium');
});

// --- Edge Cases ---

test('generateInlineCss handles malformed settings gracefully', function () {
    $tenant = Tenant::factory()->make(['settings' => ['theme' => 123]]);

    expect($this->service->generateInlineCss($tenant))->toBe('');
});

test('generateInlineCss handles tenant with only font customization', function () {
    $tenant = Tenant::factory()->withFont('montserrat')->make();

    $css = $this->service->generateInlineCss($tenant);

    expect($css)
        ->toContain('Montserrat')
        ->not->toContain('--color-primary');
});

test('neo-brutalism preset includes outline overrides', function () {
    $tenant = Tenant::factory()->withThemePreset('neo-brutalism')->make();

    $css = $this->service->generateInlineCss($tenant);

    expect($css)->toContain('--color-outline');
});

test('each preset has both light and dark variants', function () {
    $presets = config('tenant-themes.presets');

    foreach ($presets as $name => $preset) {
        expect($preset)->toHaveKey('light');
        expect($preset)->toHaveKey('dark');
        expect($preset['light'])->toHaveKey('--color-primary');
        expect($preset['dark'])->toHaveKey('--color-primary');
    }
});

test('each font has google_fonts_url and family', function () {
    $fonts = config('tenant-themes.fonts');

    foreach ($fonts as $name => $font) {
        expect($font)->toHaveKey('google_fonts_url');
        expect($font)->toHaveKey('family');
        expect($font)->toHaveKey('label');
    }
});

test('each radius has label and value', function () {
    $radii = config('tenant-themes.radii');

    foreach ($radii as $name => $radius) {
        expect($radius)->toHaveKey('label');
        expect($radius)->toHaveKey('value');
    }
});
