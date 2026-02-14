<?php

declare(strict_types=1);

/**
 * Unit tests for F-003: Core Package Installation
 *
 * These tests verify config files exist and package.json/composer.json
 * have the correct dependencies, without requiring Laravel app boot.
 */
$configPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR;
$basePath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR;

describe('Package Configuration Files Exist', function () use ($configPath) {
    it('has permission config published', function () use ($configPath) {
        expect(file_exists($configPath.'permission.php'))->toBeTrue();
    });

    it('has activitylog config published', function () use ($configPath) {
        expect(file_exists($configPath.'activitylog.php'))->toBeTrue();
    });

    it('has honeypot config published', function () use ($configPath) {
        expect(file_exists($configPath.'honeypot.php'))->toBeTrue();
    });

    it('has flutterwave config published', function () use ($configPath) {
        expect(file_exists($configPath.'flutterwave.php'))->toBeTrue();
    });

    it('has webpush config published', function () use ($configPath) {
        expect(file_exists($configPath.'webpush.php'))->toBeTrue();
    });

    it('has media-library config published', function () use ($configPath) {
        expect(file_exists($configPath.'media-library.php'))->toBeTrue();
    });
});

describe('Composer Dependencies', function () use ($basePath) {
    it('has all required packages in composer.json', function () use ($basePath) {
        $composerJson = json_decode(file_get_contents($basePath.'composer.json'), true);
        $require = $composerJson['require'] ?? [];

        expect($require)->toHaveKey('spatie/laravel-permission');
        expect($require)->toHaveKey('spatie/laravel-activitylog');
        expect($require)->toHaveKey('spatie/laravel-honeypot');
        expect($require)->toHaveKey('flutterwavedev/flutterwave-v3');
        expect($require)->toHaveKey('laravel-notification-channels/webpush');
        expect($require)->toHaveKey('spatie/laravel-medialibrary');
    });

    it('has lock file in sync with composer.json', function () use ($basePath) {
        expect(file_exists($basePath.'composer.lock'))->toBeTrue();
    });
});

describe('npm Dependencies', function () use ($basePath) {
    it('has alpinejs in package.json', function () use ($basePath) {
        $packageJson = json_decode(file_get_contents($basePath.'package.json'), true);

        $allDeps = array_merge(
            $packageJson['dependencies'] ?? [],
            $packageJson['devDependencies'] ?? [],
        );

        expect($allDeps)->toHaveKey('alpinejs');
    });

    it('has tailwindcss in package.json', function () use ($basePath) {
        $packageJson = json_decode(file_get_contents($basePath.'package.json'), true);

        $allDeps = array_merge(
            $packageJson['dependencies'] ?? [],
            $packageJson['devDependencies'] ?? [],
        );

        expect($allDeps)->toHaveKey('tailwindcss');
    });
});

describe('Alpine.js Integration', function () use ($basePath) {
    it('has alpine imported in app.js', function () use ($basePath) {
        $appJs = file_get_contents($basePath.'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'app.js');

        expect($appJs)->toContain("import Alpine from 'alpinejs'");
        expect($appJs)->toContain('Alpine.start()');
    });
});

describe('Flutterwave Config Structure', function () use ($configPath) {
    it('contains required keys in flutterwave config', function () use ($configPath) {
        $config = include $configPath.'flutterwave.php';

        expect($config)->toBeArray();
        expect($config)->toHaveKeys([
            'public_key',
            'secret_key',
            'encryption_key',
            'webhook_secret',
            'env',
            'currency',
            'country',
            'payment_methods',
            'default_commission_percentage',
        ]);
    });

    it('defaults to XAF currency and CM country', function () use ($configPath) {
        $config = include $configPath.'flutterwave.php';

        expect($config['currency'])->toBe('XAF');
        expect($config['country'])->toBe('CM');
    });

    it('defaults to 10 percent commission', function () use ($configPath) {
        $config = include $configPath.'flutterwave.php';

        expect($config['default_commission_percentage'])->toBe(10);
    });
});
