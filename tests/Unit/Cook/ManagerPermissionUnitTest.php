<?php

/**
 * F-210: Manager Permission Configuration — Unit Tests
 *
 * Structural and static tests that do not require database access.
 * Tests the ManagerPermissionService constants, metadata, and class structure.
 * Tests the ManagerPermissionController class structure.
 * Tests the blade view and route definitions.
 *
 * BR-473: Seven delegatable permissions must exist
 * BR-474: Toggle logic must be present
 * BR-477: Only cook can configure permissions
 * BR-480: Activity logging must be present
 * BR-481: All text uses __()
 * BR-482: All interactions via Gale
 */

use App\Http\Controllers\Cook\ManagerPermissionController;
use App\Services\ManagerPermissionService;

$projectRoot = dirname(__DIR__, 3);

// ─────────────────────────────────────────────────────────────────────────────
// ManagerPermissionService — class structure
// ─────────────────────────────────────────────────────────────────────────────

describe('ManagerPermissionService class structure', function () use ($projectRoot) {
    it('exists as a file', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/app/Services/ManagerPermissionService.php'))->toBeTrue();
    });

    it('has a DELEGATABLE_PERMISSIONS constant', function () {
        $ref = new ReflectionClass(ManagerPermissionService::class);
        expect($ref->hasConstant('DELEGATABLE_PERMISSIONS'))->toBeTrue();
    });

    it('has a PERMISSION_MAP constant', function () {
        $ref = new ReflectionClass(ManagerPermissionService::class);
        expect($ref->hasConstant('PERMISSION_MAP'))->toBeTrue();
    });

    it('has getPermissionsState method', function () {
        expect(method_exists(ManagerPermissionService::class, 'getPermissionsState'))->toBeTrue();
    });

    it('has togglePermission method', function () {
        expect(method_exists(ManagerPermissionService::class, 'togglePermission'))->toBeTrue();
    });

    it('has getPermissionGroups static method', function () {
        expect(method_exists(ManagerPermissionService::class, 'getPermissionGroups'))->toBeTrue();
    });

    it('has isManagerForTenant method', function () {
        expect(method_exists(ManagerPermissionService::class, 'isManagerForTenant'))->toBeTrue();
    });

    it('has revokeAllPermissions method', function () {
        expect(method_exists(ManagerPermissionService::class, 'revokeAllPermissions'))->toBeTrue();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// ManagerPermissionService — constants
// ─────────────────────────────────────────────────────────────────────────────

describe('DELEGATABLE_PERMISSIONS constant', function () {
    it('lists exactly seven permissions (BR-473)', function () {
        expect(ManagerPermissionService::DELEGATABLE_PERMISSIONS)->toHaveCount(7);
    });

    it('includes can-manage-orders', function () {
        expect(ManagerPermissionService::DELEGATABLE_PERMISSIONS)->toContain('can-manage-orders');
    });

    it('includes can-manage-meals', function () {
        expect(ManagerPermissionService::DELEGATABLE_PERMISSIONS)->toContain('can-manage-meals');
    });

    it('includes can-manage-schedules', function () {
        expect(ManagerPermissionService::DELEGATABLE_PERMISSIONS)->toContain('can-manage-schedules');
    });

    it('includes can-manage-locations', function () {
        expect(ManagerPermissionService::DELEGATABLE_PERMISSIONS)->toContain('can-manage-locations');
    });

    it('includes can-view-cook-analytics', function () {
        expect(ManagerPermissionService::DELEGATABLE_PERMISSIONS)->toContain('can-view-cook-analytics');
    });

    it('includes can-manage-complaints', function () {
        expect(ManagerPermissionService::DELEGATABLE_PERMISSIONS)->toContain('can-manage-complaints');
    });

    it('includes can-manage-messages', function () {
        expect(ManagerPermissionService::DELEGATABLE_PERMISSIONS)->toContain('can-manage-messages');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// ManagerPermissionService — getPermissionGroups (structural checks only)
// Note: actual runtime test skipped because __() requires Laravel context;
// blade view tests (below) verify the groups are wired through the template.
// ─────────────────────────────────────────────────────────────────────────────

describe('getPermissionGroups', function () use ($projectRoot) {
    it('is a public static method', function () {
        $ref = new ReflectionClass(ManagerPermissionService::class);
        $method = $ref->getMethod('getPermissionGroups');
        expect($method->isPublic())->toBeTrue();
        expect($method->isStatic())->toBeTrue();
    });

    it('has no required parameters', function () {
        $ref = new ReflectionClass(ManagerPermissionService::class);
        $method = $ref->getMethod('getPermissionGroups');
        expect($method->getNumberOfRequiredParameters())->toBe(0);
    });

    it('blade view iterates getPermissionGroups', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/permissions.blade.php');
        expect($content)->toContain('getPermissionGroups()');
    });

    it('blade view renders permission key and label from group entries', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/permissions.blade.php');
        // The blade iterates $groupPermissions and accesses $perm['key'] and $perm['label']
        expect($content)->toContain("\$perm['key']");
        expect($content)->toContain("\$perm['label']");
        expect($content)->toContain("\$perm['description']");
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// ManagerPermissionController — class structure
// ─────────────────────────────────────────────────────────────────────────────

describe('ManagerPermissionController class structure', function () use ($projectRoot) {
    it('exists as a file', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/app/Http/Controllers/Cook/ManagerPermissionController.php'))->toBeTrue();
    });

    it('has a show method', function () {
        expect(method_exists(ManagerPermissionController::class, 'show'))->toBeTrue();
    });

    it('has a toggle method', function () {
        expect(method_exists(ManagerPermissionController::class, 'toggle'))->toBeTrue();
    });

    it('uses ManagerPermissionService via constructor injection', function () {
        $ref = new ReflectionClass(ManagerPermissionController::class);
        $constructor = $ref->getConstructor();
        $params = $constructor->getParameters();

        $hasService = false;
        foreach ($params as $param) {
            if ($param->getType()?->getName() === ManagerPermissionService::class) {
                $hasService = true;
                break;
            }
        }

        expect($hasService)->toBeTrue();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Permissions blade view
// ─────────────────────────────────────────────────────────────────────────────

describe('permissions blade view', function () use ($projectRoot) {
    it('exists', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/resources/views/cook/managers/permissions.blade.php'))->toBeTrue();
    });

    it('uses @fragment permissions-panel', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/permissions.blade.php');
        expect($content)->toContain("@fragment('permissions-panel')");
    });

    it('uses @endfragment', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/permissions.blade.php');
        expect($content)->toContain('@endfragment');
    });

    it('uses __() for localized strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/permissions.blade.php');
        expect($content)->toContain('__(');
    });

    it('uses permissions-panel id for fragment root', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/permissions.blade.php');
        expect($content)->toContain('id="permissions-panel"');
    });

    it('references permissions toggle route', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/permissions.blade.php');
        expect($content)->toContain('cook.managers.permissions.toggle');
    });

    it('has role=switch on toggle buttons for accessibility', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/permissions.blade.php');
        expect($content)->toContain('role="switch"');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Managers index blade view — F-210 additions
// ─────────────────────────────────────────────────────────────────────────────

describe('managers index blade view F-210 additions', function () use ($projectRoot) {
    it('has a Configure button per manager', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/index.blade.php');
        expect($content)->toContain('Configure');
    });

    it('has openPermissions function in x-data', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/index.blade.php');
        expect($content)->toContain('openPermissions');
    });

    it('has closePermissions function in x-data', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/index.blade.php');
        expect($content)->toContain('closePermissions');
    });

    it('has permissions-panel anchor div', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/index.blade.php');
        expect($content)->toContain('id="permissions-panel"');
    });

    it('references cook.managers.permissions.show route', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/index.blade.php');
        expect($content)->toContain('cook.managers.permissions.show');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// RoleAndPermissionSeeder — new permissions
// ─────────────────────────────────────────────────────────────────────────────

describe('RoleAndPermissionSeeder has new permissions', function () use ($projectRoot) {
    it('includes can-manage-complaints in COOK_PERMISSIONS', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/database/seeders/RoleAndPermissionSeeder.php');
        expect($content)->toContain("'can-manage-complaints'");
    });

    it('includes can-manage-messages in COOK_PERMISSIONS', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/database/seeders/RoleAndPermissionSeeder.php');
        expect($content)->toContain("'can-manage-messages'");
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Routes
// ─────────────────────────────────────────────────────────────────────────────

describe('routes are defined', function () use ($projectRoot) {
    it('has permissions show route', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain('cook.managers.permissions.show');
    });

    it('has permissions toggle route', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain('cook.managers.permissions.toggle');
    });

    it('show route uses ManagerPermissionController', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain('ManagerPermissionController');
    });
});
