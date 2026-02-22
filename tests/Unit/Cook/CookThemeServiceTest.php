<?php

/**
 * F-214: Cook Theme Selection — Unit Tests
 *
 * Tests for CookSettingsService appearance methods and TenantThemeService
 * theme resolution logic.
 *
 * BR-520: Valid theme presets from TenantThemeService.
 * BR-521: Valid font families from TenantThemeService.
 * BR-522: Valid border radius options from TenantThemeService.
 * BR-525: Theme settings stored in tenant.settings JSON.
 * BR-527: Reset to Default = Modern + Inter + medium.
 * BR-530: Activity log only created when values actually change.
 */

use App\Models\Tenant;
use App\Services\CookSettingsService;
use App\Services\TenantThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->themeService = new TenantThemeService;
    $this->settingsService = app(CookSettingsService::class);
});

// ============================================================
// TenantThemeService — catalog tests (no DB needed)
// ============================================================

test('theme service returns available presets including required themes', function (): void {
    $presets = $this->themeService->availablePresets();

    expect($presets)->toBeArray()
        ->toHaveKey('modern')
        ->toHaveKey('arctic')
        ->toHaveKey('high-contrast')
        ->toHaveKey('minimal')
        ->toHaveKey('neo-brutalism');
});

test('theme service returns all required fonts', function (): void {
    $fonts = $this->themeService->availableFonts();

    expect($fonts)->toBeArray()
        ->toHaveKey('inter')
        ->toHaveKey('roboto')
        ->toHaveKey('poppins')
        ->toHaveKey('nunito')
        ->toHaveKey('open-sans')
        ->toHaveKey('montserrat');
});

test('theme service returns all required border radius options', function (): void {
    $radii = $this->themeService->availableRadii();

    expect($radii)->toBeArray()
        ->toHaveKey('none')
        ->toHaveKey('small')
        ->toHaveKey('medium')
        ->toHaveKey('large')
        ->toHaveKey('full');
});

test('theme service defaults are modern, inter, medium', function (): void {
    expect($this->themeService->defaultPreset())->toBe('modern')
        ->and($this->themeService->defaultFont())->toBe('inter')
        ->and($this->themeService->defaultRadius())->toBe('medium');
});

test('theme service validates preset names correctly', function (): void {
    expect($this->themeService->isValidPreset('modern'))->toBeTrue()
        ->and($this->themeService->isValidPreset('arctic'))->toBeTrue()
        ->and($this->themeService->isValidPreset('invalid-theme'))->toBeFalse()
        ->and($this->themeService->isValidPreset(''))->toBeFalse();
});

test('theme service validates font names correctly', function (): void {
    expect($this->themeService->isValidFont('inter'))->toBeTrue()
        ->and($this->themeService->isValidFont('poppins'))->toBeTrue()
        ->and($this->themeService->isValidFont('comic-sans'))->toBeFalse();
});

test('theme service validates radius names correctly', function (): void {
    expect($this->themeService->isValidRadius('medium'))->toBeTrue()
        ->and($this->themeService->isValidRadius('full'))->toBeTrue()
        ->and($this->themeService->isValidRadius('huge'))->toBeFalse();
});

test('theme service resolves to defaults for null tenant', function (): void {
    expect($this->themeService->resolvePreset(null))->toBe('modern')
        ->and($this->themeService->resolveFont(null))->toBe('inter')
        ->and($this->themeService->resolveRadius(null))->toBe('medium');
});

test('theme service falls back to defaults for invalid preset name', function (): void {
    $tenant = Tenant::factory()->make(['settings' => ['theme' => 'nonexistent-theme']]);

    expect($this->themeService->resolvePreset($tenant))->toBe('modern');
});

test('theme service generates empty css when all settings are default', function (): void {
    $tenant = Tenant::factory()->make(['settings' => ['theme' => 'modern', 'font' => 'inter', 'border_radius' => 'medium']]);

    expect($this->themeService->generateInlineCss($tenant))->toBe('');
});

test('theme service generates non-empty css for non-default preset', function (): void {
    $tenant = Tenant::factory()->make(['settings' => ['theme' => 'arctic', 'font' => 'inter', 'border_radius' => 'medium']]);

    $css = $this->themeService->generateInlineCss($tenant);
    expect($css)->not->toBe('')->toContain('--color-primary');
});

test('theme service includes dark mode variables in generated css', function (): void {
    $tenant = Tenant::factory()->make(['settings' => ['theme' => 'arctic', 'font' => 'inter', 'border_radius' => 'medium']]);

    $css = $this->themeService->generateInlineCss($tenant);
    expect($css)->toContain('[data-theme="dark"]');
});

// ============================================================
// CookSettingsService — appearance methods (needs DB)
// ============================================================

test('get appearance returns defaults when tenant has no settings', function (): void {
    $tenant = Tenant::factory()->make(['settings' => []]);

    $appearance = $this->settingsService->getAppearance($tenant);

    expect($appearance)->toBe([
        'theme' => 'modern',
        'font' => 'inter',
        'border_radius' => 'medium',
    ]);
});

test('get appearance returns saved settings from tenant', function (): void {
    $tenant = Tenant::factory()->make(['settings' => [
        'theme' => 'arctic',
        'font' => 'poppins',
        'border_radius' => 'large',
    ]]);

    $appearance = $this->settingsService->getAppearance($tenant);

    expect($appearance)->toBe([
        'theme' => 'arctic',
        'font' => 'poppins',
        'border_radius' => 'large',
    ]);
});

test('update appearance persists new values to tenant settings', function (): void {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();

    $result = $this->settingsService->updateAppearance(
        $tenant,
        'neo-brutalism',
        'poppins',
        'full',
        $cook,
    );

    $tenant->refresh();

    expect($result['new'])->toBe([
        'theme' => 'neo-brutalism',
        'font' => 'poppins',
        'border_radius' => 'full',
    ])->and($tenant->getSetting('theme'))->toBe('neo-brutalism')
        ->and($tenant->getSetting('font'))->toBe('poppins')
        ->and($tenant->getSetting('border_radius'))->toBe('full');
});

test('update appearance logs activity when values change', function (): void {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();

    $tenant->setSetting('theme', 'modern');
    $tenant->setSetting('font', 'inter');
    $tenant->setSetting('border_radius', 'medium');
    $tenant->save();

    Activity::query()->delete();

    $this->settingsService->updateAppearance($tenant, 'arctic', 'poppins', 'large', $cook);

    $activity = Activity::query()
        ->where('log_name', 'tenants')
        ->where('description', 'appearance_updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($cook->id)
        ->and($activity->properties['attributes']['theme'])->toBe('arctic');
});

test('update appearance does not log activity when nothing changed', function (): void {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();

    $tenant->setSetting('theme', 'arctic');
    $tenant->setSetting('font', 'poppins');
    $tenant->setSetting('border_radius', 'large');
    $tenant->save();

    Activity::query()->delete();

    $this->settingsService->updateAppearance($tenant, 'arctic', 'poppins', 'large', $cook);

    $activityCount = Activity::query()
        ->where('log_name', 'tenants')
        ->where('description', 'appearance_updated')
        ->count();

    expect($activityCount)->toBe(0);
});

test('reset appearance restores defaults', function (): void {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();

    $tenant->setSetting('theme', 'arctic');
    $tenant->setSetting('font', 'poppins');
    $tenant->setSetting('border_radius', 'full');
    $tenant->save();

    $this->settingsService->resetAppearance($tenant, $cook);

    $tenant->refresh();

    expect($tenant->getSetting('theme'))->toBe('modern')
        ->and($tenant->getSetting('font'))->toBe('inter')
        ->and($tenant->getSetting('border_radius'))->toBe('medium');
});
