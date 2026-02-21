<?php

/**
 * F-213: Minimum Order Amount Configuration — Unit Tests
 *
 * Tests CookSettingsService minimum order amount logic in isolation.
 *
 * BR-507: Default is 0 XAF (no minimum).
 * BR-508: Range 0–100,000 XAF inclusive.
 * BR-509: Value stored as integer.
 */

use App\Models\Tenant;
use App\Services\CookSettingsService;

uses(Tests\TestCase::class);

test('minimum order amount constants are defined correctly', function (): void {
    expect(CookSettingsService::DEFAULT_MINIMUM_ORDER_AMOUNT)->toBe(0);
    expect(CookSettingsService::MIN_ORDER_AMOUNT)->toBe(0);
    expect(CookSettingsService::MAX_ORDER_AMOUNT)->toBe(100000);
    expect(CookSettingsService::MINIMUM_ORDER_AMOUNT_KEY)->toBe('minimum_order_amount');
});

// BR-507: Default is 0 XAF when not configured
test('getMinimumOrderAmount returns 0 when setting is not configured', function (): void {
    $service = new CookSettingsService;

    $tenant = Mockery::mock(Tenant::class);
    $tenant->shouldReceive('getSetting')
        ->with(CookSettingsService::MINIMUM_ORDER_AMOUNT_KEY)
        ->andReturn(null);

    expect($service->getMinimumOrderAmount($tenant))->toBe(0);
});

// BR-509: Value returned as integer
test('getMinimumOrderAmount returns configured integer value', function (): void {
    $service = new CookSettingsService;

    $tenant = Mockery::mock(Tenant::class);
    $tenant->shouldReceive('getSetting')
        ->with(CookSettingsService::MINIMUM_ORDER_AMOUNT_KEY)
        ->andReturn('3000');

    expect($service->getMinimumOrderAmount($tenant))->toBe(3000);
});

// BR-514: 0 means no minimum
test('getMinimumOrderAmount returns 0 when setting is explicitly set to zero', function (): void {
    $service = new CookSettingsService;

    $tenant = Mockery::mock(Tenant::class);
    $tenant->shouldReceive('getSetting')
        ->with(CookSettingsService::MINIMUM_ORDER_AMOUNT_KEY)
        ->andReturn('0');

    expect($service->getMinimumOrderAmount($tenant))->toBe(0);
});

// BR-508: Maximum is 100,000 XAF
test('MAX_ORDER_AMOUNT constant is 100000', function (): void {
    expect(CookSettingsService::MAX_ORDER_AMOUNT)->toBe(100000);
});

// Tenant model helper
test('Tenant getMinimumOrderAmount returns default 0 when not set', function (): void {
    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->shouldReceive('getSetting')
        ->with(CookSettingsService::MINIMUM_ORDER_AMOUNT_KEY)
        ->andReturn(null);

    expect($tenant->getMinimumOrderAmount())->toBe(0);
});

test('Tenant getMinimumOrderAmount returns configured value', function (): void {
    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->shouldReceive('getSetting')
        ->with(CookSettingsService::MINIMUM_ORDER_AMOUNT_KEY)
        ->andReturn('2000');

    expect($tenant->getMinimumOrderAmount())->toBe(2000);
});
