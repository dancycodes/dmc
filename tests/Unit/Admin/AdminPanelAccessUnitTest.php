<?php

use App\Http\Middleware\EnsureAdminAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/*
|--------------------------------------------------------------------------
| F-043: Admin Panel Layout & Access Control — Unit Tests
|--------------------------------------------------------------------------
|
| Unit tests for the EnsureAdminAccess middleware, admin layout structure,
| and permission-based navigation filtering.
|
| BR-045: Only users with can-access-admin-panel permission may access
| BR-046: Sidebar sections based on user permissions
|
*/

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| Middleware Unit Tests
|--------------------------------------------------------------------------
*/

it('EnsureAdminAccess middleware class exists', function () {
    expect(class_exists(EnsureAdminAccess::class))->toBeTrue();
});

it('EnsureAdminAccess middleware has handle method', function () {
    $middleware = new EnsureAdminAccess;

    expect(method_exists($middleware, 'handle'))->toBeTrue();
});

it('EnsureAdminAccess middleware checks can-access-admin-panel permission', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Middleware'.DIRECTORY_SEPARATOR.'EnsureAdminAccess.php';
    $content = file_get_contents($path);

    // Middleware must check the can-access-admin-panel permission
    expect($content)
        ->toContain('can-access-admin-panel')
        ->toContain('abort(403)');
});

/*
|--------------------------------------------------------------------------
| File Structure Unit Tests
|--------------------------------------------------------------------------
*/

it('admin layout blade file exists', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'layouts'.DIRECTORY_SEPARATOR.'admin.blade.php';
    expect(file_exists($path))->toBeTrue();
});

it('admin dashboard blade file exists', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR.'dashboard.blade.php';
    expect(file_exists($path))->toBeTrue();
});

it('admin breadcrumb component exists', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR.'breadcrumb.blade.php';
    expect(file_exists($path))->toBeTrue();
});

it('403 error page exists', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.'403.blade.php';
    expect(file_exists($path))->toBeTrue();
});

it('EnsureAdminAccess middleware file exists', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Middleware'.DIRECTORY_SEPARATOR.'EnsureAdminAccess.php';
    expect(file_exists($path))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Admin Layout Content Unit Tests
|--------------------------------------------------------------------------
*/

it('admin layout extends app layout', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'layouts'.DIRECTORY_SEPARATOR.'admin.blade.php';
    $content = file_get_contents($path);

    expect($content)->toContain("@extends('layouts.app')");
});

it('admin layout has permission-based navigation', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'layouts'.DIRECTORY_SEPARATOR.'admin.blade.php';
    $content = file_get_contents($path);

    expect($content)
        ->toContain("'permission'")
        ->toContain('can-view-tenants')
        ->toContain('can-manage-users')
        ->toContain('can-manage-roles')
        ->toContain('can-view-platform-analytics')
        ->toContain('can-manage-financials')
        ->toContain('can-manage-complaints-escalated')
        ->toContain('can-manage-payouts')
        ->toContain('can-manage-platform-settings')
        ->toContain('can-view-activity-log');
});

it('admin layout has grouped navigation sections', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'layouts'.DIRECTORY_SEPARATOR.'admin.blade.php';
    $content = file_get_contents($path);

    expect($content)
        ->toContain("__('Overview')")
        ->toContain("__('Management')")
        ->toContain("__('Insights')")
        ->toContain("__('Operations')")
        ->toContain("__('System')");
});

it('admin layout filters groups by permission', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'layouts'.DIRECTORY_SEPARATOR.'admin.blade.php';
    $content = file_get_contents($path);

    expect($content)
        ->toContain('$user->can(')
        ->toContain('array_filter');
});

it('admin layout has mobile overlay', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'layouts'.DIRECTORY_SEPARATOR.'admin.blade.php';
    $content = file_get_contents($path);

    expect($content)->toContain('mobileMenuOpen');
});

it('admin layout has sidebar collapse functionality', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'layouts'.DIRECTORY_SEPARATOR.'admin.blade.php';
    $content = file_get_contents($path);

    expect($content)->toContain('sidebarCollapsed');
});

it('admin layout uses semantic color tokens', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'layouts'.DIRECTORY_SEPARATOR.'admin.blade.php';
    $content = file_get_contents($path);

    expect($content)
        ->toContain('bg-surface-alt')
        ->toContain('text-on-surface')
        ->toContain('bg-primary')
        ->toContain('text-primary')
        ->toContain('border-outline');
});

it('admin layout has dark mode variants', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'layouts'.DIRECTORY_SEPARATOR.'admin.blade.php';
    $content = file_get_contents($path);

    expect($content)
        ->toContain('dark:bg-surface-alt')
        ->toContain('dark:border-outline');
});

it('admin layout uses translation helpers for all strings', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'layouts'.DIRECTORY_SEPARATOR.'admin.blade.php';
    $content = file_get_contents($path);

    expect($content)
        ->toContain("__('Dashboard')")
        ->toContain("__('Admin')")
        ->toContain("__('Toggle menu')")
        ->toContain("__('Expand sidebar')")
        ->toContain("__('Collapse sidebar')")
        ->toContain("__('Admin navigation')");
});

it('admin layout uses Gale navigation directive', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'layouts'.DIRECTORY_SEPARATOR.'admin.blade.php';
    $content = file_get_contents($path);

    expect($content)->toContain('x-navigate');
});

it('admin dashboard uses Gale view response', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Controllers'.DIRECTORY_SEPARATOR.'DashboardController.php';
    $content = file_get_contents($path);

    expect($content)->toContain("gale()->view('admin.dashboard'");
});

/*
|--------------------------------------------------------------------------
| Translation Tests
|--------------------------------------------------------------------------
*/

it('english translations include admin panel strings', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR.'en.json';
    $translations = json_decode(file_get_contents($path), true);

    expect($translations)
        ->toHaveKey('Overview')
        ->toHaveKey('Management')
        ->toHaveKey('Insights')
        ->toHaveKey('Operations')
        ->toHaveKey('Analytics')
        ->toHaveKey('Finance')
        ->toHaveKey('Payouts')
        ->toHaveKey('Breadcrumb')
        ->toHaveKey('Welcome back, :name')
        ->toHaveKey('Forbidden');
});

it('french translations include admin panel strings', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR.'fr.json';
    $translations = json_decode(file_get_contents($path), true);

    expect($translations)
        ->toHaveKey('Overview')
        ->toHaveKey('Management')
        ->toHaveKey('Insights')
        ->toHaveKey('Operations')
        ->toHaveKey('Analytics')
        ->toHaveKey('Finance')
        ->toHaveKey('Payouts')
        ->toHaveKey('Breadcrumb')
        ->toHaveKey('Welcome back, :name')
        ->toHaveKey('Forbidden');
});

/*
|--------------------------------------------------------------------------
| 403 Error Page Tests
|--------------------------------------------------------------------------
*/

it('403 error page uses semantic color tokens', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.'403.blade.php';
    $content = file_get_contents($path);

    expect($content)
        ->toContain('bg-surface')
        ->toContain('text-on-surface')
        ->toContain('bg-danger-subtle')
        ->toContain('text-danger')
        ->toContain('bg-primary');
});

it('403 error page has dark mode support', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.'403.blade.php';
    $content = file_get_contents($path);

    expect($content)
        ->toContain('dark:bg-surface')
        ->toContain("localStorage.getItem('dmc-theme')");
});

it('403 error page uses translation helpers', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.'403.blade.php';
    $content = file_get_contents($path);

    expect($content)
        ->toContain("__('Forbidden')")
        ->toContain("__('You do not have permission to access this page.')")
        ->toContain("__('Return to Homepage')");
});

/*
|--------------------------------------------------------------------------
| Middleware Registration Tests
|--------------------------------------------------------------------------
*/

it('admin access middleware alias is registered', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
    $content = file_get_contents($path);

    expect($content)->toContain("'admin.access' => EnsureAdminAccess::class");
});

it('admin routes use admin access middleware', function () use ($projectRoot) {
    $path = $projectRoot.DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'web.php';
    $content = file_get_contents($path);

    expect($content)->toContain("'admin.access'");
});
