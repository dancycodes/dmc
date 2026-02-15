<?php

use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('tenant has correct fillable attributes', function () {
    $tenant = new Tenant;

    expect($tenant->getFillable())->toBe([
        'slug',
        'name',
        'custom_domain',
        'is_active',
        'settings',
    ]);
});

test('tenant casts is_active to boolean', function () {
    $tenant = Tenant::factory()->withSlug('cast-test', 'Cast Test')->create();

    expect($tenant->is_active)->toBeBool();
});

test('tenant casts settings to array', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'settings-test',
        'name' => 'Settings Test',
        'settings' => ['theme' => 'ocean'],
    ]);

    expect($tenant->settings)->toBeArray()
        ->and($tenant->settings['theme'])->toBe('ocean');
});

test('tenant findBySlug returns correct tenant', function () {
    $tenant = Tenant::factory()->withSlug('test-cook', 'Test Cook')->create();

    $found = Tenant::findBySlug('test-cook');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($tenant->id);
});

test('tenant findBySlug returns null for non-existent slug', function () {
    expect(Tenant::findBySlug('non-existent'))->toBeNull();
});

test('tenant findByCustomDomain returns correct tenant', function () {
    $tenant = Tenant::factory()
        ->withSlug('custom-cook', 'Custom Cook')
        ->withCustomDomain('custom.cm')
        ->create();

    $found = Tenant::findByCustomDomain('custom.cm');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($tenant->id);
});

test('tenant findByCustomDomain returns null for non-existent domain', function () {
    expect(Tenant::findByCustomDomain('unknown.cm'))->toBeNull();
});

test('tenant active scope filters inactive tenants', function () {
    Tenant::factory()->withSlug('active-cook', 'Active Cook')->create();
    Tenant::factory()->withSlug('inactive-cook', 'Inactive Cook')->inactive()->create();

    $activeCount = Tenant::active()->count();
    $allCount = Tenant::count();

    expect($activeCount)->toBe(1)
        ->and($allCount)->toBe(2);
});

test('reserved subdomains are identified correctly', function () {
    expect(Tenant::isReservedSubdomain('www'))->toBeTrue()
        ->and(Tenant::isReservedSubdomain('api'))->toBeTrue()
        ->and(Tenant::isReservedSubdomain('mail'))->toBeTrue()
        ->and(Tenant::isReservedSubdomain('admin'))->toBeTrue()
        ->and(Tenant::isReservedSubdomain('latifa'))->toBeFalse()
        ->and(Tenant::isReservedSubdomain('my-cook'))->toBeFalse();
});

test('reserved subdomain check is case insensitive', function () {
    expect(Tenant::isReservedSubdomain('WWW'))->toBeTrue()
        ->and(Tenant::isReservedSubdomain('Api'))->toBeTrue();
});

test('tenant route key name is slug', function () {
    $tenant = new Tenant;

    expect($tenant->getRouteKeyName())->toBe('slug');
});

test('tenant service extracts subdomain correctly', function () {
    // For dmc.test main domain
    expect(TenantService::extractSubdomain('latifa.dmc.test'))->toBe('latifa')
        ->and(TenantService::extractSubdomain('dmc.test'))->toBeNull()
        ->and(TenantService::extractSubdomain('other.com'))->toBeNull();
});

test('tenant service detects IP addresses', function () {
    expect(TenantService::isIpAddress('127.0.0.1'))->toBeTrue()
        ->and(TenantService::isIpAddress('192.168.1.1'))->toBeTrue()
        ->and(TenantService::isIpAddress('::1'))->toBeTrue()
        ->and(TenantService::isIpAddress('dmc.test'))->toBeFalse()
        ->and(TenantService::isIpAddress('latifa.dmc.test'))->toBeFalse();
});

test('tenant service tracks resolution state', function () {
    $service = new TenantService;

    expect($service->isResolved())->toBeFalse();

    $service->set(null);

    expect($service->isResolved())->toBeTrue()
        ->and($service->isMainDomain())->toBeTrue()
        ->and($service->isTenantDomain())->toBeFalse();
});

test('tenant service tracks tenant domain state', function () {
    $service = new TenantService;
    $tenant = Tenant::factory()->withSlug('test-tenant', 'Test Tenant')->create();

    $service->set($tenant);

    expect($service->isResolved())->toBeTrue()
        ->and($service->isTenantDomain())->toBeTrue()
        ->and($service->isMainDomain())->toBeFalse()
        ->and($service->get()->slug)->toBe('test-tenant');
});

test('tenant factory creates valid tenant with Cameroonian names', function () {
    $tenant = Tenant::factory()->create();

    expect($tenant->name)->not->toBeEmpty()
        ->and($tenant->slug)->not->toBeEmpty()
        ->and($tenant->is_active)->toBeTrue()
        ->and($tenant->custom_domain)->toBeNull();
});

test('tenant factory inactive state works', function () {
    $tenant = Tenant::factory()->inactive()->create();

    expect($tenant->is_active)->toBeFalse();
});
