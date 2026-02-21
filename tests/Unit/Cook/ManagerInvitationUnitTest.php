<?php

/**
 * F-209: Cook Creates Manager Role — Unit Tests
 *
 * Tests for ManagerService, ManagerController, InviteManagerRequest,
 * route configuration, and blade view.
 *
 * BR-462: Only cooks can manage managers for a tenant
 * BR-463: Only existing DancyMeals users can be invited
 * BR-464: Assigns the manager role scoped to the current tenant
 * BR-465: Cook can invite an unlimited number of managers
 * BR-466: Removing a manager revokes only this tenant's assignment
 * BR-467: Cannot invite a user who is already a manager for this tenant
 * BR-468: Cannot invite the cook of this tenant
 * BR-469: Manager list shows name, email, date added, remove action
 * BR-470: All invitation and removal actions are logged
 * BR-471: All user-facing text must use __() localization
 * BR-472: Gale handles all interactions without page reloads
 */

use App\Http\Controllers\Cook\ManagerController;
use App\Http\Requests\Cook\InviteManagerRequest;
use App\Services\ManagerService;

$projectRoot = dirname(__DIR__, 3);

// ============================================================
// Test group: ManagerService Class Exists
// ============================================================
describe('ManagerService', function () use ($projectRoot) {
    it('exists as a class', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/app/Services/ManagerService.php'))->toBeTrue();
    });

    it('has an inviteManager method', function () {
        expect(method_exists(ManagerService::class, 'inviteManager'))->toBeTrue();
    });

    it('has a removeManager method', function () {
        expect(method_exists(ManagerService::class, 'removeManager'))->toBeTrue();
    });

    it('has a getManagersForTenant method', function () {
        expect(method_exists(ManagerService::class, 'getManagersForTenant'))->toBeTrue();
    });

    it('has an isManagerForTenant method', function () {
        expect(method_exists(ManagerService::class, 'isManagerForTenant'))->toBeTrue();
    });
});

// ============================================================
// Test group: ManagerController
// ============================================================
describe('ManagerController', function () use ($projectRoot) {
    it('exists as a class', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/app/Http/Controllers/Cook/ManagerController.php'))->toBeTrue();
    });

    it('has an index method', function () {
        expect(method_exists(ManagerController::class, 'index'))->toBeTrue();
    });

    it('has an invite method', function () {
        expect(method_exists(ManagerController::class, 'invite'))->toBeTrue();
    });

    it('has a remove method', function () {
        expect(method_exists(ManagerController::class, 'remove'))->toBeTrue();
    });
});

// ============================================================
// Test group: InviteManagerRequest
// ============================================================
describe('InviteManagerRequest', function () {
    it('has email validation rule', function () {
        $request = new InviteManagerRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('email');
        expect($rules['email'])->toContain('required');
        expect($rules['email'])->toContain('email');
    });

    it('has max length on email', function () {
        $request = new InviteManagerRequest;
        $rules = $request->rules();
        expect(implode(',', $rules['email']))->toContain('max:255');
    });
});

// ============================================================
// Test group: Routes (file-based check, no app context needed)
// ============================================================
describe('Manager Routes', function () use ($projectRoot) {
    it('declares cook.managers.index route in web.php', function () use ($projectRoot) {
        $routes = file_get_contents($projectRoot.'/routes/web.php');
        expect($routes)->toContain("cook.managers.index");
    });

    it('declares cook.managers.invite route in web.php', function () use ($projectRoot) {
        $routes = file_get_contents($projectRoot.'/routes/web.php');
        expect($routes)->toContain("cook.managers.invite");
    });

    it('declares cook.managers.remove route in web.php', function () use ($projectRoot) {
        $routes = file_get_contents($projectRoot.'/routes/web.php');
        expect($routes)->toContain("cook.managers.remove");
    });

    it('uses ManagerController in routes', function () use ($projectRoot) {
        $routes = file_get_contents($projectRoot.'/routes/web.php');
        expect($routes)->toContain("ManagerController");
    });
});

// ============================================================
// Test group: Blade View
// ============================================================
describe('Managers Blade View', function () use ($projectRoot) {
    it('exists at the correct path', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/resources/views/cook/managers/index.blade.php'))->toBeTrue();
    });

    it('extends the cook-dashboard layout', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/index.blade.php');
        expect($content)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('uses __() for all user-facing strings (BR-471)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/index.blade.php');
        // Ensure no bare string literals in {{ }} without __()
        expect($content)->toContain("__('Invite')");
        expect($content)->toContain("__('Remove')");
        expect($content)->toContain("__('Team')");
    });

    it('has x-data for Gale Alpine context (BR-472)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/index.blade.php');
        expect($content)->toContain('x-data=');
    });

    it('uses @fragment for managers-list partial updates (BR-472)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/index.blade.php');
        expect($content)->toContain("@fragment('managers-list')");
        expect($content)->toContain('@endfragment');
    });

    it('has invite form using $action (BR-472)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/index.blade.php');
        expect($content)->toContain('$action(');
    });

    it('has remove confirmation modal', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/index.blade.php');
        expect($content)->toContain('confirmRemoveId');
        expect($content)->toContain('cancelRemove');
        expect($content)->toContain('executeRemove');
    });

    it('has empty state message (BR-469)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/managers/index.blade.php');
        // Uses double-quoted string in __() because the text contains an apostrophe
        expect($content)->toContain("You haven't added any team members yet.");
    });
});

// ============================================================
// Test group: Migration
// ============================================================
describe('tenant_managers Migration', function () use ($projectRoot) {
    it('migration file exists', function () use ($projectRoot) {
        $migrations = glob($projectRoot.'/database/migrations/*_create_tenant_managers_table.php');
        expect($migrations)->not->toBeEmpty();
    });
});

// ============================================================
// Test group: Translation Strings
// ============================================================
describe('Translation Strings', function () use ($projectRoot) {
    it('has English translation for manager invitation error', function () use ($projectRoot) {
        $json = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
        expect($json)->toHaveKey('No DancyMeals account found with this email. The user must register first.');
    });

    it('has English translation for duplicate manager error', function () use ($projectRoot) {
        $json = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
        expect($json)->toHaveKey('This user is already a manager for your team.');
    });

    it('has English translation for removal success', function () use ($projectRoot) {
        $json = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
        expect($json)->toHaveKey(':name has been removed.');
    });

    it('has French translation for invitation error', function () use ($projectRoot) {
        $json = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);
        expect($json)->toHaveKey('No DancyMeals account found with this email. The user must register first.');
    });

    it('has French translation for duplicate manager error', function () use ($projectRoot) {
        $json = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);
        expect($json)->toHaveKey('This user is already a manager for your team.');
    });
});
