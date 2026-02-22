<?php

/**
 * Unit tests for F-041: Notification Preferences Management.
 *
 * Tests without app container context, following the $projectRoot pattern.
 * Covers model constants, controller/view file existence,
 * translation keys, and model structure.
 */

use App\Http\Controllers\NotificationPreferencesController;
use App\Models\NotificationPreference;

$projectRoot = dirname(__DIR__, 3);

// -------------------------
// Model constant tests
// -------------------------

it('defines the correct 5 notification types', function () {
    expect(NotificationPreference::TYPES)->toBe([
        'orders', 'payments', 'complaints', 'promotions', 'system',
    ]);
});

it('has type labels for all types', function () {
    foreach (NotificationPreference::TYPES as $type) {
        expect(NotificationPreference::TYPE_LABELS)->toHaveKey($type);
        expect(NotificationPreference::TYPE_LABELS[$type])->not->toBeEmpty();
    }
});

it('has type descriptions for all types', function () {
    foreach (NotificationPreference::TYPES as $type) {
        expect(NotificationPreference::TYPE_DESCRIPTIONS)->toHaveKey($type);
        expect(NotificationPreference::TYPE_DESCRIPTIONS[$type])->not->toBeEmpty();
    }
});

it('has fillable fields for user_id, notification_type, push_enabled, email_enabled', function () {
    $pref = new NotificationPreference;
    expect($pref->getFillable())->toBe([
        'user_id',
        'notification_type',
        'push_enabled',
        'email_enabled',
    ]);
});

it('casts push_enabled and email_enabled to boolean', function () {
    $pref = new NotificationPreference([
        'push_enabled' => 1,
        'email_enabled' => 0,
    ]);
    expect($pref->push_enabled)->toBeTrue()
        ->and($pref->email_enabled)->toBeFalse();
});

// -------------------------
// Controller tests
// -------------------------

it('has a NotificationPreferencesController class', function () {
    expect(class_exists(NotificationPreferencesController::class))->toBeTrue();
});

it('NotificationPreferencesController has a show method', function () {
    $controller = new NotificationPreferencesController;
    expect(method_exists($controller, 'show'))->toBeTrue();
});

it('NotificationPreferencesController has an update method', function () {
    $controller = new NotificationPreferencesController;
    expect(method_exists($controller, 'update'))->toBeTrue();
});

it('show method returns mixed type (Gale response)', function () {
    $ref = new ReflectionMethod(NotificationPreferencesController::class, 'show');
    $returnType = $ref->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('mixed');
});

it('update method returns mixed type (Gale response)', function () {
    $ref = new ReflectionMethod(NotificationPreferencesController::class, 'update');
    $returnType = $ref->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('mixed');
});

// -------------------------
// View file tests
// -------------------------

it('has a notifications blade view file', function () use ($projectRoot) {
    $path = $projectRoot.'/resources/views/profile/notifications.blade.php';
    expect(file_exists($path))->toBeTrue();
});

it('view extends the correct layouts for main and tenant domains', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/notifications.blade.php');
    expect($content)->toContain('layouts.main-public')
        ->and($content)->toContain('layouts.tenant-public');
});

it('view includes dark mode variants', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/notifications.blade.php');
    expect($content)->toContain('dark:');
});

it('view uses semantic color tokens', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/notifications.blade.php');
    expect($content)->toContain('bg-surface')
        ->and($content)->toContain('text-on-surface')
        ->and($content)->toContain('border-outline');
});

it('view uses x-data for Gale Alpine context', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/notifications.blade.php');
    expect($content)->toContain('x-data');
});

it('view uses x-sync for state synchronization', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/notifications.blade.php');
    expect($content)->toContain('x-sync');
});

it('view uses $action for Gale form submission', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/notifications.blade.php');
    expect($content)->toContain('$action');
});

it('view uses $fetching() with parentheses (not $fetching)', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/notifications.blade.php');
    expect($content)->toContain('$fetching()');
});

it('view shows database (in-app) column as non-interactive', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/notifications.blade.php');
    // The database column should be grayed/muted (cursor-not-allowed) and always on
    expect($content)->toContain('cursor-not-allowed');
});

it('view checks push permission via Notification.permission', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/notifications.blade.php');
    expect($content)->toContain('Notification.permission')
        ->and($content)->toContain('pushPermission');
});

it('view uses __() for all user-facing strings', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/notifications.blade.php');
    expect($content)->toContain("__('Notification Preferences')")
        ->and($content)->toContain("__('Push')")
        ->and($content)->toContain("__('Email')")
        ->and($content)->toContain("__('In-App')")
        ->and($content)->toContain("__('Save Preferences')")
        ->and($content)->toContain("__('In-app notifications are always active");
});

// -------------------------
// Translation key tests
// -------------------------

it('has English translation keys for notification preferences page', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
    expect($translations)->toHaveKey('Notification Preferences')
        ->and($translations)->toHaveKey('Save Preferences');
});

it('has French translation keys for notification preferences page', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);
    expect($translations)->toHaveKey('Notification Preferences')
        ->and($translations)->toHaveKey('Save Preferences');
});

// -------------------------
// Route file tests
// -------------------------

it('routes file includes notification preferences routes', function () use ($projectRoot) {
    $routesContent = file_get_contents($projectRoot.'/routes/web.php');
    expect($routesContent)->toContain('NotificationPreferencesController')
        ->and($routesContent)->toContain('/profile/notifications');
});
