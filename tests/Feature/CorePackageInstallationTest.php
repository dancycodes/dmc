<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

describe('Spatie Permission Database Tables', function () {
    it('has roles table', function () {
        expect(Schema::hasTable('roles'))->toBeTrue();
    });

    it('has permissions table', function () {
        expect(Schema::hasTable('permissions'))->toBeTrue();
    });

    it('has model_has_roles table', function () {
        expect(Schema::hasTable('model_has_roles'))->toBeTrue();
    });

    it('has model_has_permissions table', function () {
        expect(Schema::hasTable('model_has_permissions'))->toBeTrue();
    });

    it('has role_has_permissions table', function () {
        expect(Schema::hasTable('role_has_permissions'))->toBeTrue();
    });

    it('has correct columns on roles table', function () {
        expect(Schema::hasColumns('roles', ['id', 'name', 'guard_name', 'created_at', 'updated_at']))->toBeTrue();
    });

    it('has correct columns on permissions table', function () {
        expect(Schema::hasColumns('permissions', ['id', 'name', 'guard_name', 'created_at', 'updated_at']))->toBeTrue();
    });
});

describe('Spatie Activitylog Database Tables', function () {
    it('has activity_log table', function () {
        expect(Schema::hasTable('activity_log'))->toBeTrue();
    });

    it('has correct columns on activity_log table', function () {
        expect(Schema::hasColumns('activity_log', [
            'id',
            'log_name',
            'description',
            'subject_type',
            'subject_id',
            'causer_type',
            'causer_id',
            'properties',
            'event',
            'batch_uuid',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
    });
});

describe('Media Library Database Tables', function () {
    it('has media table', function () {
        expect(Schema::hasTable('media'))->toBeTrue();
    });

    it('has correct columns on media table', function () {
        expect(Schema::hasColumns('media', [
            'id',
            'model_type',
            'model_id',
            'collection_name',
            'name',
            'file_name',
            'mime_type',
            'disk',
            'size',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
    });
});

describe('WebPush Database Tables', function () {
    it('has push_subscriptions table', function () {
        expect(Schema::hasTable('push_subscriptions'))->toBeTrue();
    });

    it('has correct columns on push_subscriptions table', function () {
        expect(Schema::hasColumns('push_subscriptions', [
            'id',
            'subscribable_type',
            'subscribable_id',
            'endpoint',
            'public_key',
            'auth_token',
            'content_encoding',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
    });
});

describe('Composer Package Installation', function () {
    it('has spatie/laravel-permission installed', function () {
        expect(class_exists(\Spatie\Permission\PermissionServiceProvider::class))->toBeTrue();
    });

    it('has spatie/laravel-activitylog installed', function () {
        expect(class_exists(\Spatie\Activitylog\ActivitylogServiceProvider::class))->toBeTrue();
    });

    it('has spatie/laravel-honeypot installed', function () {
        expect(class_exists(\Spatie\Honeypot\HoneypotServiceProvider::class))->toBeTrue();
    });

    it('has flutterwavedev/flutterwave-v3 installed', function () {
        expect(class_exists(\Flutterwave\Flutterwave::class))->toBeTrue();
    });

    it('has laravel-notification-channels/webpush installed', function () {
        expect(class_exists(\NotificationChannels\WebPush\WebPushServiceProvider::class))->toBeTrue();
    });

    it('has spatie/laravel-medialibrary installed', function () {
        expect(class_exists(\Spatie\MediaLibrary\MediaLibraryServiceProvider::class))->toBeTrue();
    });
});

describe('Package Configuration Loaded', function () {
    it('has permission config loaded', function () {
        $config = config('permission');

        expect($config)->not->toBeNull();
        expect($config)->toHaveKey('models');
        expect($config['models'])->toHaveKeys(['permission', 'role']);
    });

    it('has activitylog config loaded', function () {
        $config = config('activitylog');

        expect($config)->not->toBeNull();
    });

    it('has honeypot config loaded', function () {
        $config = config('honeypot');

        expect($config)->not->toBeNull();
    });

    it('has flutterwave config loaded', function () {
        $config = config('flutterwave');

        expect($config)->not->toBeNull();
        expect($config)->toHaveKeys([
            'public_key',
            'secret_key',
            'encryption_key',
            'webhook_secret',
            'env',
            'currency',
            'country',
        ]);
    });

    it('has webpush config loaded', function () {
        $config = config('webpush');

        expect($config)->not->toBeNull();
        expect($config)->toHaveKey('vapid');
    });

    it('has media-library config loaded', function () {
        $config = config('media-library');

        expect($config)->not->toBeNull();
    });
});

describe('Alpine.js and Vite Build', function () {
    it('has alpinejs listed in package.json dependencies', function () {
        $packageJson = json_decode(file_get_contents(base_path('package.json')), true);

        $allDeps = array_merge(
            $packageJson['dependencies'] ?? [],
            $packageJson['devDependencies'] ?? [],
        );

        expect($allDeps)->toHaveKey('alpinejs');
    });

    it('has alpine available via gale package', function () {
        $appJs = file_get_contents(resource_path('js/app.js'));

        // Alpine is provided by Gale (@gale blade directive), not imported directly in app.js
        // Direct import causes conflicts with Gale's Alpine plugin ($fetching, $action, etc.)
        expect($appJs)->not->toContain("import Alpine from 'alpinejs'");
    });

    it('has compiled CSS output', function () {
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);

        expect($manifest)->toHaveKey('resources/css/app.css');
    });

    it('has compiled JS output', function () {
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);

        expect($manifest)->toHaveKey('resources/js/app.js');
    });
});
