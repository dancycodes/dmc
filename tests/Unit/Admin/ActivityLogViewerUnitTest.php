<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Requests\Admin\ActivityLogListRequest;

/**
 * F-064: Activity Log Viewer — Unit Tests
 *
 * Pure unit tests for controller helpers and form request authorization.
 * No app container, no database access.
 */

// ─── ActivityLogController::getShortModelName ───────────────────────────────

test('getShortModelName extracts short class name from fully qualified name', function () {
    expect(ActivityLogController::getShortModelName('App\\Models\\User'))->toBe('User');
    expect(ActivityLogController::getShortModelName('App\\Models\\Tenant'))->toBe('Tenant');
    expect(ActivityLogController::getShortModelName('App\\Models\\PaymentTransaction'))->toBe('PaymentTransaction');
    expect(ActivityLogController::getShortModelName('Spatie\\Permission\\Models\\Role'))->toBe('Role');
});

test('getShortModelName handles class name without namespace', function () {
    expect(ActivityLogController::getShortModelName('User'))->toBe('User');
});

test('getShortModelName handles deeply nested namespaces', function () {
    expect(ActivityLogController::getShortModelName('Very\\Deep\\Namespace\\Models\\SomeModel'))->toBe('SomeModel');
});

// ─── ActivityLogListRequest ──────────────────────────────────────────────────

test('ActivityLogListRequest has expected validation rules', function () {
    $projectRoot = dirname(__DIR__, 3);
    $file = $projectRoot.'/app/Http/Requests/Admin/ActivityLogListRequest.php';
    $contents = file_get_contents($file);

    expect($contents)->toContain("'search'");
    expect($contents)->toContain("'causer_user_id'");
    expect($contents)->toContain("'subject_type'");
    expect($contents)->toContain("'event'");
    expect($contents)->toContain("'date_from'");
    expect($contents)->toContain("'date_to'");
});

test('ActivityLogListRequest authorizes based on can-view-activity-log permission', function () {
    $projectRoot = dirname(__DIR__, 3);
    $file = $projectRoot.'/app/Http/Requests/Admin/ActivityLogListRequest.php';
    $contents = file_get_contents($file);

    expect($contents)->toContain('can-view-activity-log');
});

test('ActivityLogListRequest requires date_to to be after_or_equal date_from', function () {
    $projectRoot = dirname(__DIR__, 3);
    $file = $projectRoot.'/app/Http/Requests/Admin/ActivityLogListRequest.php';
    $contents = file_get_contents($file);

    expect($contents)->toContain('after_or_equal:date_from');
});

// ─── Controller file structure checks ───────────────────────────────────────

test('ActivityLogController uses Gale navigate key activity-log', function () {
    $projectRoot = dirname(__DIR__, 3);
    $file = $projectRoot.'/app/Http/Controllers/Admin/ActivityLogController.php';
    $contents = file_get_contents($file);

    expect($contents)->toContain("isGaleNavigate('activity-log')");
});

test('ActivityLogController uses gale view with web true for initial page load', function () {
    $projectRoot = dirname(__DIR__, 3);
    $file = $projectRoot.'/app/Http/Controllers/Admin/ActivityLogController.php';
    $contents = file_get_contents($file);

    expect($contents)->toContain("gale()->view('admin.activity-log.index'");
    expect($contents)->toContain('web: true');
});

test('ActivityLogController uses gale fragment for navigate requests', function () {
    $projectRoot = dirname(__DIR__, 3);
    $file = $projectRoot.'/app/Http/Controllers/Admin/ActivityLogController.php';
    $contents = file_get_contents($file);

    expect($contents)->toContain("gale()->fragment('admin.activity-log.index', 'activity-log-content'");
});

test('ActivityLogController returns BR-196 pagination of 25 per page', function () {
    $projectRoot = dirname(__DIR__, 3);
    $file = $projectRoot.'/app/Http/Controllers/Admin/ActivityLogController.php';
    $contents = file_get_contents($file);

    expect($contents)->toContain('paginate(25)');
});

// ─── View file checks ────────────────────────────────────────────────────────

test('activity log view extends admin layout', function () {
    $projectRoot = dirname(__DIR__, 3);
    $file = $projectRoot.'/resources/views/admin/activity-log/index.blade.php';
    $contents = file_get_contents($file);

    expect($contents)->toContain("@extends('layouts.admin')");
});

test('activity log view defines activity-log-content fragment', function () {
    $projectRoot = dirname(__DIR__, 3);
    $file = $projectRoot.'/resources/views/admin/activity-log/index.blade.php';
    $contents = file_get_contents($file);

    expect($contents)->toContain("@fragment('activity-log-content')");
    expect($contents)->toContain('@endfragment');
});

test('diff panel view handles both old/attributes and single-state activities', function () {
    $projectRoot = dirname(__DIR__, 3);
    $file = $projectRoot.'/resources/views/admin/activity-log/_diff-panel.blade.php';
    $contents = file_get_contents($file);

    // Before/After diff
    expect($contents)->toContain('$oldValues');
    expect($contents)->toContain('$newValues');
    // Single state for create/delete
    expect($contents)->toContain('Created State');
    expect($contents)->toContain('Deleted State');
});

test('activity log view uses semantic color tokens not hardcoded colors', function () {
    $projectRoot = dirname(__DIR__, 3);
    $indexFile = $projectRoot.'/resources/views/admin/activity-log/index.blade.php';
    $diffFile = $projectRoot.'/resources/views/admin/activity-log/_diff-panel.blade.php';

    foreach ([$indexFile, $diffFile] as $file) {
        $contents = file_get_contents($file);
        // Should not contain hardcoded Tailwind color classes like bg-blue-500
        expect($contents)->not->toContain('bg-blue-');
        expect($contents)->not->toContain('bg-green-');
        expect($contents)->not->toContain('bg-red-');
        expect($contents)->not->toContain('text-blue-');
        expect($contents)->not->toContain('text-green-');
        expect($contents)->not->toContain('text-red-');
    }
});
