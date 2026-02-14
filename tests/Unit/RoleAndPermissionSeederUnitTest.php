<?php

declare(strict_types=1);

use Database\Seeders\RoleAndPermissionSeeder;

describe('Permission Constants Structure', function () {
    it('has no duplicate permission names across all categories', function () {
        $all = RoleAndPermissionSeeder::allPermissions();
        $unique = array_unique($all);

        expect(count($all))->toBe(count($unique));
    });

    it('admin permissions all start with can- prefix', function () {
        foreach (RoleAndPermissionSeeder::adminPermissions() as $perm) {
            expect($perm)->toStartWith('can-');
        }
    });

    it('cook permissions all start with can- prefix', function () {
        foreach (RoleAndPermissionSeeder::cookPermissions() as $perm) {
            expect($perm)->toStartWith('can-');
        }
    });

    it('client permissions all start with can- prefix', function () {
        foreach (RoleAndPermissionSeeder::clientPermissions() as $perm) {
            expect($perm)->toStartWith('can-');
        }
    });

    it('has admin permissions defined', function () {
        expect(RoleAndPermissionSeeder::adminPermissions())->not->toBeEmpty();
    });

    it('has cook permissions defined', function () {
        expect(RoleAndPermissionSeeder::cookPermissions())->not->toBeEmpty();
    });

    it('has client permissions defined', function () {
        expect(RoleAndPermissionSeeder::clientPermissions())->not->toBeEmpty();
    });

    it('allPermissions returns the union of all three categories', function () {
        $expected = array_merge(
            RoleAndPermissionSeeder::adminPermissions(),
            RoleAndPermissionSeeder::cookPermissions(),
            RoleAndPermissionSeeder::clientPermissions(),
        );

        expect(RoleAndPermissionSeeder::allPermissions())->toBe($expected);
    });

    it('includes can-access-admin-panel in admin permissions', function () {
        expect(RoleAndPermissionSeeder::adminPermissions())->toContain('can-access-admin-panel');
    });

    it('includes can-manage-meals in cook permissions', function () {
        expect(RoleAndPermissionSeeder::cookPermissions())->toContain('can-manage-meals');
    });

    it('includes can-browse-meals in client permissions', function () {
        expect(RoleAndPermissionSeeder::clientPermissions())->toContain('can-browse-meals');
    });

    it('includes can-place-orders in client permissions', function () {
        expect(RoleAndPermissionSeeder::clientPermissions())->toContain('can-place-orders');
    });
});

describe('Gate::before Super-Admin Configuration', function () {
    it('has Gate::before registered in AppServiceProvider', function () {
        $appServiceProvider = file_get_contents(
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR
            .'Providers'.DIRECTORY_SEPARATOR.'AppServiceProvider.php'
        );

        expect($appServiceProvider)->toContain('Gate::before');
        expect($appServiceProvider)->toContain("hasRole('super-admin')");
    });
});

describe('DatabaseSeeder Integration', function () {
    it('calls RoleAndPermissionSeeder from DatabaseSeeder', function () {
        $databaseSeeder = file_get_contents(
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR
            .'seeders'.DIRECTORY_SEPARATOR.'DatabaseSeeder.php'
        );

        expect($databaseSeeder)->toContain('RoleAndPermissionSeeder::class');
    });
});
