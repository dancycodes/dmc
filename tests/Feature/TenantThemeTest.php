<?php

use App\Models\Tenant;
use App\Services\TenantThemeService;

// --- Middleware Integration ---

test('tenant theme css is injected on tenant domain with custom preset', function () {
    Tenant::factory()
        ->withThemePreset('arctic')
        ->withSlug('latifa')
        ->create();

    $response = $this->get('http://latifa.dmc.test/login');

    $response->assertStatus(200)
        ->assertSee('tenant-theme', false);
});

test('tenant theme css is not injected on main domain', function () {
    $response = $this->get('http://dmc.test/login');

    $response->assertStatus(200)
        ->assertDontSee('id="tenant-theme"', false);
});

test('custom font link is injected for tenant with custom font', function () {
    Tenant::factory()
        ->withFont('poppins')
        ->withSlug('chef-powel')
        ->create();

    $response = $this->get('http://chef-powel.dmc.test/login');

    $response->assertStatus(200)
        ->assertSee('Poppins', false)
        ->assertSee('fonts.googleapis.com', false);
});

test('no extra font link for tenant with default font', function () {
    Tenant::factory()
        ->withFont('inter')
        ->withSlug('mama-caro')
        ->create();

    $response = $this->get('http://mama-caro.dmc.test/login');

    $content = $response->getContent();

    // Count Inter font link occurrences - should be just the default one
    $interLinks = substr_count($content, 'family=Inter');
    expect($interLinks)->toBe(1);
});

test('tenant theme includes color overrides for arctic preset', function () {
    Tenant::factory()
        ->withThemePreset('arctic')
        ->withSlug('ice-cook')
        ->create();

    $response = $this->get('http://ice-cook.dmc.test/login');

    $response->assertStatus(200)
        ->assertSee('--color-primary', false);
});

test('tenant with default settings does not inject theme overrides', function () {
    Tenant::factory()
        ->withSlug('default-cook')
        ->create();

    $response = $this->get('http://default-cook.dmc.test/login');

    $response->assertStatus(200)
        ->assertDontSee('id="tenant-theme"', false);
});

test('tenant with neo-brutalism preset injects outline overrides', function () {
    Tenant::factory()
        ->withThemePreset('neo-brutalism')
        ->withSlug('bold-cook')
        ->create();

    $response = $this->get('http://bold-cook.dmc.test/login');

    $response->assertStatus(200)
        ->assertSee('--color-outline', false);
});

test('tenant with custom radius injects radius overrides', function () {
    Tenant::factory()
        ->withBorderRadius('none')
        ->withSlug('sharp-cook')
        ->create();

    $response = $this->get('http://sharp-cook.dmc.test/login');

    $response->assertStatus(200)
        ->assertSee('--radius-md', false)
        ->assertSee('0px', false);
});

// --- Tenant Model ---

test('tenant getSetting returns value from settings JSON', function () {
    $tenant = Tenant::factory()->make([
        'settings' => ['theme' => 'arctic', 'font' => 'poppins'],
    ]);

    expect($tenant->getSetting('theme'))->toBe('arctic')
        ->and($tenant->getSetting('font'))->toBe('poppins')
        ->and($tenant->getSetting('missing', 'default'))->toBe('default');
});

test('tenant setSetting updates settings JSON', function () {
    $tenant = Tenant::factory()->make(['settings' => []]);

    $tenant->setSetting('theme', 'ocean');

    expect($tenant->settings)->toBe(['theme' => 'ocean']);
});

test('tenant setSetting preserves existing settings', function () {
    $tenant = Tenant::factory()->make([
        'settings' => ['existing_key' => 'existing_value'],
    ]);

    $tenant->setSetting('theme', 'forest');

    expect($tenant->settings)
        ->toHaveKey('existing_key', 'existing_value')
        ->toHaveKey('theme', 'forest');
});

test('tenant getThemePreset returns preset from settings', function () {
    $tenant = Tenant::factory()->withThemePreset('neo-brutalism')->make();

    expect($tenant->getThemePreset())->toBe('neo-brutalism');
});

test('tenant getThemePreset returns null when not set', function () {
    $tenant = Tenant::factory()->make(['settings' => []]);

    expect($tenant->getThemePreset())->toBeNull();
});

test('tenant getThemeFont returns font from settings', function () {
    $tenant = Tenant::factory()->withFont('montserrat')->make();

    expect($tenant->getThemeFont())->toBe('montserrat');
});

test('tenant getThemeBorderRadius returns radius from settings', function () {
    $tenant = Tenant::factory()->withBorderRadius('large')->make();

    expect($tenant->getThemeBorderRadius())->toBe('large');
});

// --- Tenant Factory ---

test('tenant factory withThemePreset sets preset in settings', function () {
    $tenant = Tenant::factory()->withThemePreset('arctic')->make();

    expect($tenant->settings['theme'])->toBe('arctic');
});

test('tenant factory withFont sets font in settings', function () {
    $tenant = Tenant::factory()->withFont('poppins')->make();

    expect($tenant->settings['font'])->toBe('poppins');
});

test('tenant factory withBorderRadius sets radius in settings', function () {
    $tenant = Tenant::factory()->withBorderRadius('large')->make();

    expect($tenant->settings['border_radius'])->toBe('large');
});

test('tenant factory withTheme sets all theme options', function () {
    $tenant = Tenant::factory()
        ->withTheme('violet', 'nunito', 'full')
        ->make();

    expect($tenant->settings)
        ->toHaveKey('theme', 'violet')
        ->toHaveKey('font', 'nunito')
        ->toHaveKey('border_radius', 'full');
});

// --- Config File ---

test('tenant themes config file exists and has required keys', function () {
    expect(config('tenant-themes'))
        ->toHaveKey('default_preset')
        ->toHaveKey('default_font')
        ->toHaveKey('default_radius')
        ->toHaveKey('presets')
        ->toHaveKey('fonts')
        ->toHaveKey('radii');
});

test('tenant themes config has all required presets from spec', function () {
    $presets = config('tenant-themes.presets');

    expect($presets)
        ->toHaveKey('arctic')
        ->toHaveKey('high-contrast')
        ->toHaveKey('minimal')
        ->toHaveKey('modern')
        ->toHaveKey('neo-brutalism');
});

test('tenant themes config has all required fonts from spec', function () {
    $fonts = config('tenant-themes.fonts');

    expect($fonts)
        ->toHaveKey('inter')
        ->toHaveKey('roboto')
        ->toHaveKey('poppins')
        ->toHaveKey('nunito')
        ->toHaveKey('open-sans')
        ->toHaveKey('montserrat');
});

test('tenant themes config has all required radius options from spec', function () {
    $radii = config('tenant-themes.radii');

    expect($radii)
        ->toHaveKey('none')
        ->toHaveKey('small')
        ->toHaveKey('medium')
        ->toHaveKey('large')
        ->toHaveKey('full');
});

test('tenant themes config radius values match spec', function () {
    $radii = config('tenant-themes.radii');

    expect($radii['none']['value'])->toBe('0px')
        ->and($radii['small']['value'])->toBe('4px')
        ->and($radii['medium']['value'])->toBe('8px')
        ->and($radii['large']['value'])->toBe('12px');
});

// --- Service Resolution with Database ---

test('tenant theme persists through database save and load', function () {
    $tenant = Tenant::factory()
        ->withTheme('arctic', 'poppins', 'large')
        ->withSlug('test-persist')
        ->create();

    $loaded = Tenant::query()->find($tenant->id);

    $service = app(TenantThemeService::class);

    expect($service->resolvePreset($loaded))->toBe('arctic')
        ->and($service->resolveFont($loaded))->toBe('poppins')
        ->and($service->resolveRadius($loaded))->toBe('large');
});

test('tenant theme falls back to defaults after invalid settings update', function () {
    $tenant = Tenant::factory()
        ->withSlug('test-fallback')
        ->create(['settings' => ['theme' => 'invalid', 'font' => 'comic-sans']]);

    $service = app(TenantThemeService::class);

    expect($service->resolvePreset($tenant))->toBe('modern')
        ->and($service->resolveFont($tenant))->toBe('inter');
});

// --- Auth Layout Integration ---

test('auth layout includes tenant-theme-styles component', function () {
    $layoutContent = file_get_contents(resource_path('views/layouts/auth.blade.php'));

    expect($layoutContent)->toContain('<x-tenant-theme-styles');
});

test('tenant theme styles blade component file exists', function () {
    expect(file_exists(resource_path('views/components/tenant-theme-styles.blade.php')))->toBeTrue();
});

// --- Middleware Registration ---

test('InjectTenantTheme middleware is registered in web group', function () {
    $bootstrapContent = file_get_contents(base_path('bootstrap/app.php'));

    expect($bootstrapContent)->toContain('InjectTenantTheme');
});
