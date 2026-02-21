<?php

/**
 * F-212: Cancellation Window Configuration — Unit Tests
 *
 * Tests the CookSettingsService business logic for managing the
 * cancellation window setting in the tenant's settings JSON.
 */
uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CookSettingsService;

// ─────────────────────────────────────────────────────────────────────────────
// Service Constants
// ─────────────────────────────────────────────────────────────────────────────

test('CookSettingsService has correct default cancellation window', function () {
    expect(CookSettingsService::DEFAULT_CANCELLATION_WINDOW)->toBe(15);
});

test('CookSettingsService has correct min cancellation window', function () {
    expect(CookSettingsService::MIN_CANCELLATION_WINDOW)->toBe(5);
});

test('CookSettingsService has correct max cancellation window', function () {
    expect(CookSettingsService::MAX_CANCELLATION_WINDOW)->toBe(120);
});

test('CookSettingsService has correct settings key', function () {
    expect(CookSettingsService::SETTINGS_KEY)->toBe('cancellation_window_minutes');
});

// ─────────────────────────────────────────────────────────────────────────────
// getCancellationWindow
// ─────────────────────────────────────────────────────────────────────────────

test('getCancellationWindow returns default when no setting configured', function () {
    $tenant = Tenant::factory()->create(['settings' => null]);
    $service = new CookSettingsService;

    expect($service->getCancellationWindow($tenant))->toBe(15);
});

test('getCancellationWindow returns default when key missing from settings', function () {
    $tenant = Tenant::factory()->create(['settings' => ['some_other_key' => 'value']]);
    $service = new CookSettingsService;

    expect($service->getCancellationWindow($tenant))->toBe(15);
});

test('getCancellationWindow returns configured value', function () {
    $tenant = Tenant::factory()->create([
        'settings' => ['cancellation_window_minutes' => 30],
    ]);
    $service = new CookSettingsService;

    expect($service->getCancellationWindow($tenant))->toBe(30);
});

test('getCancellationWindow returns minimum value when set to 5', function () {
    $tenant = Tenant::factory()->create([
        'settings' => ['cancellation_window_minutes' => 5],
    ]);
    $service = new CookSettingsService;

    expect($service->getCancellationWindow($tenant))->toBe(5);
});

test('getCancellationWindow returns maximum value when set to 120', function () {
    $tenant = Tenant::factory()->create([
        'settings' => ['cancellation_window_minutes' => 120],
    ]);
    $service = new CookSettingsService;

    expect($service->getCancellationWindow($tenant))->toBe(120);
});

// ─────────────────────────────────────────────────────────────────────────────
// updateCancellationWindow
// ─────────────────────────────────────────────────────────────────────────────

test('updateCancellationWindow persists new value to tenant settings', function () {
    $tenant = Tenant::factory()->create(['settings' => null]);
    $cook = User::factory()->create();
    $service = new CookSettingsService;

    $service->updateCancellationWindow($tenant, 45, $cook);

    $tenant->refresh();
    expect($tenant->getSetting('cancellation_window_minutes'))->toBe(45);
});

test('updateCancellationWindow returns old and new values', function () {
    $tenant = Tenant::factory()->create([
        'settings' => ['cancellation_window_minutes' => 15],
    ]);
    $cook = User::factory()->create();
    $service = new CookSettingsService;

    $result = $service->updateCancellationWindow($tenant, 60, $cook);

    expect($result)->toBe([
        'old_value' => 15,
        'new_value' => 60,
    ]);
});

test('updateCancellationWindow creates activity log entry', function () {
    $tenant = Tenant::factory()->create([
        'settings' => ['cancellation_window_minutes' => 15],
    ]);
    $cook = User::factory()->create();
    $service = new CookSettingsService;

    $service->updateCancellationWindow($tenant, 30, $cook);

    $log = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'tenants')
        ->where('description', 'cancellation_window_updated')
        ->where('subject_id', $tenant->id)
        ->where('causer_id', $cook->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties['old']['cancellation_window_minutes'])->toBe(15);
    expect($log->properties['attributes']['cancellation_window_minutes'])->toBe(30);
});

test('updateCancellationWindow logs correct old value when setting was default', function () {
    $tenant = Tenant::factory()->create(['settings' => null]);
    $cook = User::factory()->create();
    $service = new CookSettingsService;

    $result = $service->updateCancellationWindow($tenant, 45, $cook);

    // Old value should be the default (15) when nothing was configured
    expect($result['old_value'])->toBe(15);
    expect($result['new_value'])->toBe(45);
});

// ─────────────────────────────────────────────────────────────────────────────
// Tenant model: getCancellationWindowMinutes
// ─────────────────────────────────────────────────────────────────────────────

test('Tenant::getCancellationWindowMinutes returns default when unconfigured', function () {
    $tenant = Tenant::factory()->create(['settings' => null]);

    expect($tenant->getCancellationWindowMinutes())->toBe(15);
});

test('Tenant::getCancellationWindowMinutes returns configured value', function () {
    $tenant = Tenant::factory()->create([
        'settings' => ['cancellation_window_minutes' => 45],
    ]);

    expect($tenant->getCancellationWindowMinutes())->toBe(45);
});

// ─────────────────────────────────────────────────────────────────────────────
// Order model: cancellation_window_minutes snapshot
// ─────────────────────────────────────────────────────────────────────────────

test('Order model has cancellation_window_minutes in fillable', function () {
    $order = new Order;
    expect(in_array('cancellation_window_minutes', $order->getFillable()))->toBeTrue();
});

test('Order model casts cancellation_window_minutes as integer', function () {
    $order = new Order;
    expect($order->getCasts())->toHaveKey('cancellation_window_minutes');
    expect($order->getCasts()['cancellation_window_minutes'])->toBe('integer');
});
