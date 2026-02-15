<?php

declare(strict_types=1);

/**
 * Unit tests for F-017: Activity Logging Setup
 *
 * Tests configuration values, trait existence, and structural
 * correctness without requiring database access.
 */
$configPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR;

describe('Activitylog Configuration', function () use ($configPath) {
    it('has activitylog config file', function () use ($configPath) {
        expect(file_exists($configPath.'activitylog.php'))->toBeTrue();
    });

    it('sets retention to 90 days by default (BR-139)', function () use ($configPath) {
        $config = include $configPath.'activitylog.php';

        expect($config['delete_records_older_than_days'])->toBe(90);
    });

    it('allows retention period to be configured via env variable', function () use ($configPath) {
        $configContent = file_get_contents($configPath.'activitylog.php');

        // Verify the config reads from ACTIVITY_LOG_RETENTION_DAYS env variable
        expect($configContent)->toContain('ACTIVITY_LOG_RETENTION_DAYS');
    });

    it('has excluded_attributes key with sensitive fields (BR-138)', function () use ($configPath) {
        $config = include $configPath.'activitylog.php';

        expect($config)->toHaveKey('excluded_attributes');
        expect($config['excluded_attributes'])->toContain('password');
        expect($config['excluded_attributes'])->toContain('remember_token');
        expect($config['excluded_attributes'])->toContain('two_factor_secret');
        expect($config['excluded_attributes'])->toContain('two_factor_recovery_codes');
    });

    it('uses the default Spatie Activity model', function () use ($configPath) {
        $config = include $configPath.'activitylog.php';

        expect($config['activity_model'])->toBe(\Spatie\Activitylog\Models\Activity::class);
    });

    it('has logging enabled by default', function () use ($configPath) {
        $config = include $configPath.'activitylog.php';

        expect($config['enabled'])->toBeTrue();
    });

    it('uses default log name', function () use ($configPath) {
        $config = include $configPath.'activitylog.php';

        expect($config['default_log_name'])->toBe('default');
    });
});

describe('LogsActivityTrait Structure', function () {
    it('trait file exists', function () {
        expect(file_exists(
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Traits'.DIRECTORY_SEPARATOR.'LogsActivityTrait.php'
        ))->toBeTrue();
    });

    it('trait class is loadable', function () {
        expect(trait_exists(\App\Traits\LogsActivityTrait::class))->toBeTrue();
    });

    it('uses Spatie LogsActivity trait internally', function () {
        $traits = class_uses(\App\Traits\LogsActivityTrait::class);

        expect($traits)->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);
    });
});

describe('Model Trait Usage', function () {
    it('User model uses LogsActivityTrait', function () {
        $traits = class_uses_recursive(\App\Models\User::class);

        expect($traits)->toHaveKey(\App\Traits\LogsActivityTrait::class);
    });

    it('Tenant model uses LogsActivityTrait', function () {
        $traits = class_uses_recursive(\App\Models\Tenant::class);

        expect($traits)->toHaveKey(\App\Traits\LogsActivityTrait::class);
    });

    it('User model has getActivitylogOptions method', function () {
        expect(method_exists(\App\Models\User::class, 'getActivitylogOptions'))->toBeTrue();
    });

    it('Tenant model has getActivitylogOptions method', function () {
        expect(method_exists(\App\Models\Tenant::class, 'getActivitylogOptions'))->toBeTrue();
    });
});

describe('Scheduled Cleanup Registration', function () {
    it('has cleanup command in console routes file', function () {
        $consoleRoutes = file_get_contents(
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'console.php'
        );

        expect($consoleRoutes)->toContain('activitylog:clean');
        expect($consoleRoutes)->toContain('Schedule::');
    });

    it('uses --force flag for production compatibility', function () {
        $consoleRoutes = file_get_contents(
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'console.php'
        );

        expect($consoleRoutes)->toContain('--force');
    });
});
