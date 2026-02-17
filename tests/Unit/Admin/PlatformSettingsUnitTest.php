<?php

use App\Models\PlatformSetting;
use App\Services\PlatformSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

// --- PlatformSetting Model Tests ---

test('PlatformSetting DEFAULTS contains all expected keys', function () {
    $expectedKeys = [
        'platform_name',
        'wallet_enabled',
        'default_cancellation_window',
        'support_email',
        'support_phone',
        'maintenance_mode',
        'maintenance_reason',
    ];

    foreach ($expectedKeys as $key) {
        expect(PlatformSetting::DEFAULTS)->toHaveKey($key);
    }
});

test('PlatformSetting DEFAULTS have correct types', function () {
    expect(PlatformSetting::DEFAULTS['wallet_enabled']['type'])->toBe('boolean');
    expect(PlatformSetting::DEFAULTS['maintenance_mode']['type'])->toBe('boolean');
    expect(PlatformSetting::DEFAULTS['default_cancellation_window']['type'])->toBe('integer');
    expect(PlatformSetting::DEFAULTS['platform_name']['type'])->toBe('string');
    expect(PlatformSetting::DEFAULTS['support_email']['type'])->toBe('string');
});

test('PlatformSetting DEFAULTS have correct groups', function () {
    expect(PlatformSetting::DEFAULTS['platform_name']['group'])->toBe('general');
    expect(PlatformSetting::DEFAULTS['wallet_enabled']['group'])->toBe('features');
    expect(PlatformSetting::DEFAULTS['default_cancellation_window']['group'])->toBe('orders');
    expect(PlatformSetting::DEFAULTS['support_email']['group'])->toBe('support');
    expect(PlatformSetting::DEFAULTS['maintenance_mode']['group'])->toBe('system');
});

test('GROUPS constant maps all groups to labels', function () {
    $groups = PlatformSetting::GROUPS;
    expect($groups)->toHaveKey('general')
        ->toHaveKey('features')
        ->toHaveKey('orders')
        ->toHaveKey('support')
        ->toHaveKey('system');
});

test('CRITICAL_SETTINGS contains wallet_enabled and maintenance_mode', function () {
    expect(PlatformSetting::CRITICAL_SETTINGS)
        ->toContain('wallet_enabled')
        ->toContain('maintenance_mode');
});

test('SUPER_ADMIN_ONLY contains maintenance_mode and maintenance_reason', function () {
    expect(PlatformSetting::SUPER_ADMIN_ONLY)
        ->toContain('maintenance_mode')
        ->toContain('maintenance_reason');
});

test('typed_value casts boolean true correctly', function () {
    $setting = PlatformSetting::factory()->walletEnabled(true)->create();
    expect($setting->typed_value)->toBeTrue();
});

test('typed_value casts boolean false correctly', function () {
    $setting = PlatformSetting::factory()->maintenanceMode(false)->create();
    expect($setting->typed_value)->toBeFalse();
});

test('typed_value casts integer correctly', function () {
    $setting = PlatformSetting::factory()->cancellationWindow(45)->create();
    expect($setting->typed_value)->toBe(45);
});

test('typed_value casts string correctly', function () {
    $setting = PlatformSetting::factory()->platformName('TestPlatform')->create();
    expect($setting->typed_value)->toBe('TestPlatform');
});

test('isCritical returns true for critical settings', function () {
    $setting = PlatformSetting::factory()->walletEnabled()->create();
    expect($setting->isCritical())->toBeTrue();

    $setting2 = PlatformSetting::factory()->maintenanceMode()->create();
    expect($setting2->isCritical())->toBeTrue();
});

test('isCritical returns false for non-critical settings', function () {
    $setting = PlatformSetting::factory()->platformName()->create();
    expect($setting->isCritical())->toBeFalse();
});

test('isSuperAdminOnly returns true for super-admin-only settings', function () {
    $setting = PlatformSetting::factory()->maintenanceMode()->create();
    expect($setting->isSuperAdminOnly())->toBeTrue();

    $setting2 = PlatformSetting::factory()->maintenanceReason('test')->create();
    expect($setting2->isSuperAdminOnly())->toBeTrue();
});

test('isSuperAdminOnly returns false for regular settings', function () {
    $setting = PlatformSetting::factory()->walletEnabled()->create();
    expect($setting->isSuperAdminOnly())->toBeFalse();
});

// --- PlatformSettingService Tests ---

test('service get returns default value when setting not in DB', function () {
    $service = app(PlatformSettingService::class);

    expect($service->get('platform_name'))->toBe('DancyMeals');
    expect($service->get('wallet_enabled'))->toBeTrue();
    expect($service->get('default_cancellation_window'))->toBe(30);
    expect($service->get('maintenance_mode'))->toBeFalse();
});

test('service get returns DB value when setting exists', function () {
    PlatformSetting::factory()->platformName('MyPlatform')->create();
    $service = app(PlatformSettingService::class);
    $service->clearCache('platform_name');

    expect($service->get('platform_name'))->toBe('MyPlatform');
});

test('service get returns null for unknown key', function () {
    $service = app(PlatformSettingService::class);
    expect($service->get('nonexistent_key'))->toBeNull();
});

test('service getAll returns all settings organized by group', function () {
    $service = app(PlatformSettingService::class);
    $all = $service->getAll();

    expect($all)->toHaveKey('general')
        ->toHaveKey('features')
        ->toHaveKey('orders')
        ->toHaveKey('support')
        ->toHaveKey('system');

    expect($all['general'])->toHaveKey('platform_name');
    expect($all['features'])->toHaveKey('wallet_enabled');
});

test('service getAllFlat returns flat key-value map', function () {
    $service = app(PlatformSettingService::class);
    $flat = $service->getAllFlat();

    expect($flat)->toHaveKey('platform_name')
        ->toHaveKey('wallet_enabled')
        ->toHaveKey('default_cancellation_window')
        ->toHaveKey('support_email')
        ->toHaveKey('support_phone')
        ->toHaveKey('maintenance_mode')
        ->toHaveKey('maintenance_reason');
});

test('service update creates setting if not exists', function () {
    $service = app(PlatformSettingService::class);
    $admin = $this->createUserWithRole('super-admin');

    $result = $service->update('platform_name', 'NewName', $admin);

    expect($result['old_value'])->toBe('DancyMeals');
    expect($result['new_value'])->toBe('NewName');
    expect($result['setting'])->toBeInstanceOf(PlatformSetting::class);

    $this->assertDatabaseHas('platform_settings', [
        'key' => 'platform_name',
        'value' => 'NewName',
    ]);
});

test('service update modifies existing setting', function () {
    PlatformSetting::factory()->platformName('OldName')->create();
    $service = app(PlatformSettingService::class);
    $service->clearCache('platform_name');
    $admin = $this->createUserWithRole('super-admin');

    $result = $service->update('platform_name', 'NewName', $admin);

    expect($result['old_value'])->toBe('OldName');
    expect($result['new_value'])->toBe('NewName');

    $this->assertDatabaseHas('platform_settings', [
        'key' => 'platform_name',
        'value' => 'NewName',
    ]);
});

test('service update handles boolean values correctly', function () {
    $service = app(PlatformSettingService::class);
    $admin = $this->createUserWithRole('super-admin');

    $result = $service->update('wallet_enabled', false, $admin);

    expect($result['new_value'])->toBeFalse();

    $this->assertDatabaseHas('platform_settings', [
        'key' => 'wallet_enabled',
        'value' => '0',
    ]);
});

test('service update handles integer values correctly', function () {
    $service = app(PlatformSettingService::class);
    $admin = $this->createUserWithRole('super-admin');

    $result = $service->update('default_cancellation_window', 15, $admin);

    expect($result['new_value'])->toBe(15);

    $this->assertDatabaseHas('platform_settings', [
        'key' => 'default_cancellation_window',
        'value' => '15',
    ]);
});

test('service update logs activity', function () {
    $service = app(PlatformSettingService::class);
    $admin = $this->createUserWithRole('super-admin');

    $service->update('platform_name', 'TestName', $admin);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'platform_settings',
        'description' => 'Updated platform setting: platform_name',
        'causer_id' => $admin->id,
    ]);
});

test('service update throws exception for unknown key', function () {
    $service = app(PlatformSettingService::class);
    $admin = $this->createUserWithRole('super-admin');

    expect(fn () => $service->update('nonexistent', 'value', $admin))
        ->toThrow(\InvalidArgumentException::class);
});

test('service update clears cache for the setting', function () {
    $service = app(PlatformSettingService::class);
    $admin = $this->createUserWithRole('super-admin');

    // Populate cache
    $service->get('platform_name');
    expect(Cache::has('platform_setting:platform_name'))->toBeTrue();

    // Update should clear cache
    $service->update('platform_name', 'NewName', $admin);
    expect(Cache::has('platform_setting:platform_name'))->toBeFalse();
});

test('service isWalletEnabled returns correct value', function () {
    $service = app(PlatformSettingService::class);
    expect($service->isWalletEnabled())->toBeTrue();

    PlatformSetting::factory()->walletEnabled(false)->create();
    $service->clearCache('wallet_enabled');
    expect($service->isWalletEnabled())->toBeFalse();
});

test('service getDefaultCancellationWindow returns correct value', function () {
    $service = app(PlatformSettingService::class);
    expect($service->getDefaultCancellationWindow())->toBe(30);

    PlatformSetting::factory()->cancellationWindow(15)->create();
    $service->clearCache('default_cancellation_window');
    expect($service->getDefaultCancellationWindow())->toBe(15);
});

test('service isMaintenanceMode returns correct value', function () {
    $service = app(PlatformSettingService::class);
    expect($service->isMaintenanceMode())->toBeFalse();

    PlatformSetting::factory()->maintenanceMode(true)->create();
    $service->clearCache('maintenance_mode');
    expect($service->isMaintenanceMode())->toBeTrue();
});

test('service getPlatformName returns correct value', function () {
    $service = app(PlatformSettingService::class);
    expect($service->getPlatformName())->toBe('DancyMeals');

    PlatformSetting::factory()->platformName('TestApp')->create();
    $service->clearCache('platform_name');
    expect($service->getPlatformName())->toBe('TestApp');
});

test('service seedDefaults creates all settings', function () {
    $service = app(PlatformSettingService::class);
    $service->seedDefaults();

    foreach (array_keys(PlatformSetting::DEFAULTS) as $key) {
        $this->assertDatabaseHas('platform_settings', ['key' => $key]);
    }
});

test('service seedDefaults does not overwrite existing settings', function () {
    PlatformSetting::factory()->platformName('Custom')->create();

    $service = app(PlatformSettingService::class);
    $service->seedDefaults();

    $this->assertDatabaseHas('platform_settings', [
        'key' => 'platform_name',
        'value' => 'Custom',
    ]);
});

test('service clearCache removes specific key', function () {
    $service = app(PlatformSettingService::class);
    $service->get('platform_name'); // populate
    expect(Cache::has('platform_setting:platform_name'))->toBeTrue();

    $service->clearCache('platform_name');
    expect(Cache::has('platform_setting:platform_name'))->toBeFalse();
});

test('service clearCache removes all keys when called without argument', function () {
    $service = app(PlatformSettingService::class);

    // Populate cache for all keys
    foreach (array_keys(PlatformSetting::DEFAULTS) as $key) {
        $service->get($key);
    }

    $service->clearCache();

    foreach (array_keys(PlatformSetting::DEFAULTS) as $key) {
        expect(Cache::has('platform_setting:'.$key))->toBeFalse();
    }
});

// --- Controller/Route Access Tests ---

test('settings page requires authentication', function () {
    $response = $this->get('/vault-entry/settings');
    $response->assertRedirect('/login');
});

test('settings page requires can-manage-platform-settings permission', function () {
    $user = $this->createUserWithRole('client');
    $response = $this->actingAs($user)->get('/vault-entry/settings');
    // Admin middleware blocks before permission check
    $response->assertStatus(403);
});

test('settings page accessible to admin with permission', function () {
    $user = $this->createUserWithRole('admin');
    $response = $this->actingAs($user)->get('/vault-entry/settings');
    $response->assertOk();
});

test('settings page accessible to super-admin', function () {
    $user = $this->createUserWithRole('super-admin');
    $response = $this->actingAs($user)->get('/vault-entry/settings');
    $response->assertOk();
});

test('settings update requires authentication', function () {
    $response = $this->post('/vault-entry/settings', [
        'setting_key' => 'platform_name',
        'setting_value' => 'NewName',
    ]);
    $response->assertRedirect('/login');
});

test('settings update requires permission', function () {
    $user = $this->createUserWithRole('client');
    $response = $this->actingAs($user)->post('/vault-entry/settings', [
        'setting_key' => 'platform_name',
        'setting_value' => 'NewName',
    ]);
    $response->assertStatus(403);
});

test('admin can update non-super-admin-only settings', function () {
    $user = $this->createUserWithRole('admin');
    $response = $this->actingAs($user)->post('/vault-entry/settings', [
        'setting_key' => 'platform_name',
        'setting_value' => 'NewPlatform',
    ]);
    $response->assertRedirect('/vault-entry/settings');

    $this->assertDatabaseHas('platform_settings', [
        'key' => 'platform_name',
        'value' => 'NewPlatform',
    ]);
});

test('admin cannot update maintenance_mode (super-admin only)', function () {
    $user = $this->createUserWithRole('admin');
    $response = $this->actingAs($user)->post('/vault-entry/settings', [
        'setting_key' => 'maintenance_mode',
        'setting_value' => '1',
    ]);
    $response->assertRedirect('/vault-entry/settings');

    // Setting should not be saved
    $this->assertDatabaseMissing('platform_settings', [
        'key' => 'maintenance_mode',
        'value' => '1',
    ]);
});

test('super-admin can update maintenance_mode', function () {
    $user = $this->createUserWithRole('super-admin');
    $response = $this->actingAs($user)->post('/vault-entry/settings', [
        'setting_key' => 'maintenance_mode',
        'setting_value' => '1',
    ]);
    $response->assertRedirect('/vault-entry/settings');

    $this->assertDatabaseHas('platform_settings', [
        'key' => 'maintenance_mode',
        'value' => '1',
    ]);
});

test('invalid setting key returns error', function () {
    $user = $this->createUserWithRole('super-admin');
    $response = $this->actingAs($user)->post('/vault-entry/settings', [
        'setting_key' => 'nonexistent_key',
        'setting_value' => 'value',
    ]);
    $response->assertRedirect('/vault-entry/settings');
});

test('platform name cannot be empty', function () {
    $user = $this->createUserWithRole('super-admin');
    $response = $this->actingAs($user)->post('/vault-entry/settings', [
        'setting_key' => 'platform_name',
        'setting_value' => '',
    ]);
    $response->assertRedirect('/vault-entry/settings');

    // Should not have saved
    $this->assertDatabaseMissing('platform_settings', [
        'key' => 'platform_name',
        'value' => '',
    ]);
});

test('cancellation window validates range 0 to 120', function () {
    $user = $this->createUserWithRole('super-admin');

    // Too high
    $response = $this->actingAs($user)->post('/vault-entry/settings', [
        'setting_key' => 'default_cancellation_window',
        'setting_value' => '150',
    ]);
    $response->assertRedirect('/vault-entry/settings');
    $this->assertDatabaseMissing('platform_settings', [
        'key' => 'default_cancellation_window',
        'value' => '150',
    ]);

    // Valid
    $response = $this->actingAs($user)->post('/vault-entry/settings', [
        'setting_key' => 'default_cancellation_window',
        'setting_value' => '15',
    ]);
    $response->assertRedirect('/vault-entry/settings');
    $this->assertDatabaseHas('platform_settings', [
        'key' => 'default_cancellation_window',
        'value' => '15',
    ]);
});

test('cancellation window with negative value fails', function () {
    $user = $this->createUserWithRole('super-admin');
    $response = $this->actingAs($user)->post('/vault-entry/settings', [
        'setting_key' => 'default_cancellation_window',
        'setting_value' => '-5',
    ]);
    $response->assertRedirect('/vault-entry/settings');
    $this->assertDatabaseMissing('platform_settings', [
        'key' => 'default_cancellation_window',
        'value' => '-5',
    ]);
});

test('support email validates format', function () {
    $user = $this->createUserWithRole('super-admin');

    // Invalid email
    $response = $this->actingAs($user)->post('/vault-entry/settings', [
        'setting_key' => 'support_email',
        'setting_value' => 'not-an-email',
    ]);
    $response->assertRedirect('/vault-entry/settings');
    $this->assertDatabaseMissing('platform_settings', [
        'key' => 'support_email',
        'value' => 'not-an-email',
    ]);

    // Valid email
    $response = $this->actingAs($user)->post('/vault-entry/settings', [
        'setting_key' => 'support_email',
        'setting_value' => 'support@example.com',
    ]);
    $response->assertRedirect('/vault-entry/settings');
    $this->assertDatabaseHas('platform_settings', [
        'key' => 'support_email',
        'value' => 'support@example.com',
    ]);

    // Empty is allowed
    app(PlatformSettingService::class)->clearCache('support_email');
    $response = $this->actingAs($user)->post('/vault-entry/settings', [
        'setting_key' => 'support_email',
        'setting_value' => '',
    ]);
    $response->assertRedirect('/vault-entry/settings');
});

// --- Factory Tests ---

test('factory creates valid PlatformSetting', function () {
    $setting = PlatformSetting::factory()->create();
    expect($setting)->toBeInstanceOf(PlatformSetting::class);
    expect($setting->key)->not->toBeEmpty();
});

test('factory walletEnabled state works', function () {
    $setting = PlatformSetting::factory()->walletEnabled(true)->create();
    expect($setting->key)->toBe('wallet_enabled');
    expect($setting->value)->toBe('1');
    expect($setting->type)->toBe('boolean');
    expect($setting->group)->toBe('features');
});

test('factory cancellationWindow state works', function () {
    $setting = PlatformSetting::factory()->cancellationWindow(45)->create();
    expect($setting->key)->toBe('default_cancellation_window');
    expect($setting->value)->toBe('45');
    expect($setting->type)->toBe('integer');
    expect($setting->group)->toBe('orders');
});

test('factory maintenanceMode state works', function () {
    $setting = PlatformSetting::factory()->maintenanceMode(true)->create();
    expect($setting->key)->toBe('maintenance_mode');
    expect($setting->value)->toBe('1');
    expect($setting->type)->toBe('boolean');
    expect($setting->group)->toBe('system');
});
