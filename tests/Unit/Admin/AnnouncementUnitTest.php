<?php

/**
 * F-195: Unit Tests — System Announcement Notifications
 *
 * Tests for the Announcement model, factory states, and helper methods.
 * Does not test HTTP endpoints or browser behavior (Playwright handles that).
 */

uses(Tests\TestCase::class);

use App\Models\Announcement;

// ── Model Constants ──────────────────────────────────────────────────────────

test('announcement has correct status constants', function () {
    expect(Announcement::STATUS_DRAFT)->toBe('draft');
    expect(Announcement::STATUS_SCHEDULED)->toBe('scheduled');
    expect(Announcement::STATUS_SENT)->toBe('sent');
    expect(Announcement::STATUS_CANCELLED)->toBe('cancelled');
});

test('announcement has correct target type constants', function () {
    expect(Announcement::TARGET_ALL_USERS)->toBe('all_users');
    expect(Announcement::TARGET_ALL_COOKS)->toBe('all_cooks');
    expect(Announcement::TARGET_ALL_CLIENTS)->toBe('all_clients');
    expect(Announcement::TARGET_SPECIFIC_TENANT)->toBe('specific_tenant');
});

// ── Factory States ───────────────────────────────────────────────────────────

test('announcement factory creates sent announcement by default', function () {
    $projectRoot = dirname(__DIR__, 3);
    $factoryFile = $projectRoot.'/database/factories/AnnouncementFactory.php';

    expect(file_exists($factoryFile))->toBeTrue();

    $contents = file_get_contents($factoryFile);
    expect($contents)->toContain('STATUS_SENT')
        ->and($contents)->toContain('sent_at')
        ->and($contents)->toContain('draft()')
        ->and($contents)->toContain('scheduled()')
        ->and($contents)->toContain('cancelled()');
});

// ── canBeEdited ──────────────────────────────────────────────────────────────

test('announcement can be edited when draft', function () {
    $announcement = new Announcement(['status' => Announcement::STATUS_DRAFT]);
    expect($announcement->canBeEdited())->toBeTrue();
});

test('announcement can be edited when scheduled', function () {
    $announcement = new Announcement(['status' => Announcement::STATUS_SCHEDULED]);
    expect($announcement->canBeEdited())->toBeTrue();
});

test('announcement cannot be edited when sent', function () {
    $announcement = new Announcement(['status' => Announcement::STATUS_SENT]);
    expect($announcement->canBeEdited())->toBeFalse();
});

test('announcement cannot be edited when cancelled', function () {
    $announcement = new Announcement(['status' => Announcement::STATUS_CANCELLED]);
    expect($announcement->canBeEdited())->toBeFalse();
});

// ── canBeCancelled ───────────────────────────────────────────────────────────

test('announcement can be cancelled when scheduled', function () {
    $announcement = new Announcement(['status' => Announcement::STATUS_SCHEDULED]);
    expect($announcement->canBeCancelled())->toBeTrue();
});

test('announcement cannot be cancelled when sent', function () {
    $announcement = new Announcement(['status' => Announcement::STATUS_SENT]);
    expect($announcement->canBeCancelled())->toBeFalse();
});

test('announcement cannot be cancelled when draft', function () {
    $announcement = new Announcement(['status' => Announcement::STATUS_DRAFT]);
    expect($announcement->canBeCancelled())->toBeFalse();
});

// ── getContentPreview ────────────────────────────────────────────────────────

test('getContentPreview returns full content when under limit', function () {
    $content = 'Short content';
    $announcement = new Announcement(['content' => $content]);
    expect($announcement->getContentPreview())->toBe($content);
});

test('getContentPreview truncates and appends ellipsis when over limit', function () {
    $content = str_repeat('A', 150);
    $announcement = new Announcement(['content' => $content]);
    $preview = $announcement->getContentPreview(100);
    expect(mb_strlen($preview))->toBe(103); // 100 chars + '...'
    expect($preview)->toEndWith('...');
});

test('getContentPreview strips HTML tags', function () {
    $announcement = new Announcement(['content' => '<b>Hello</b> <em>World</em>']);
    expect($announcement->getContentPreview())->toBe('Hello World');
});

// ── getTargetLabel ───────────────────────────────────────────────────────────

test('getTargetLabel returns correct label for all_users', function () {
    $announcement = new Announcement([
        'target_type' => Announcement::TARGET_ALL_USERS,
        'target_tenant_id' => null,
    ]);
    expect($announcement->getTargetLabel())->toBe('All Users');
});

test('getTargetLabel returns correct label for all_cooks', function () {
    $announcement = new Announcement([
        'target_type' => Announcement::TARGET_ALL_COOKS,
        'target_tenant_id' => null,
    ]);
    expect($announcement->getTargetLabel())->toBe('All Cooks');
});

test('getTargetLabel returns correct label for all_clients', function () {
    $announcement = new Announcement([
        'target_type' => Announcement::TARGET_ALL_CLIENTS,
        'target_tenant_id' => null,
    ]);
    expect($announcement->getTargetLabel())->toBe('All Clients');
});

// ── targetTypeOptions ────────────────────────────────────────────────────────

test('targetTypeOptions returns all four target types', function () {
    $options = Announcement::targetTypeOptions();

    expect($options)->toHaveCount(4)
        ->and($options)->toHaveKey(Announcement::TARGET_ALL_USERS)
        ->and($options)->toHaveKey(Announcement::TARGET_ALL_COOKS)
        ->and($options)->toHaveKey(Announcement::TARGET_ALL_CLIENTS)
        ->and($options)->toHaveKey(Announcement::TARGET_SPECIFIC_TENANT);
});

// ── scopeReadyToDispatch ─────────────────────────────────────────────────────

test('announcement service resolveRecipients method exists', function () {
    $projectRoot = dirname(__DIR__, 3);
    $serviceFile = $projectRoot.'/app/Services/AnnouncementService.php';

    expect(file_exists($serviceFile))->toBeTrue();

    $contents = file_get_contents($serviceFile);
    expect($contents)->toContain('resolveRecipients')
        ->and($contents)->toContain('dispatchAnnouncement')
        ->and($contents)->toContain('createAnnouncement')
        ->and($contents)->toContain('cancelAnnouncement')
        ->and($contents)->toContain('updateAnnouncement');
});

// ── Notification class ───────────────────────────────────────────────────────

test('SystemAnnouncementNotification extends BasePushNotification', function () {
    $projectRoot = dirname(__DIR__, 3);
    $notificationFile = $projectRoot.'/app/Notifications/SystemAnnouncementNotification.php';

    expect(file_exists($notificationFile))->toBeTrue();

    $contents = file_get_contents($notificationFile);
    expect($contents)->toContain('extends BasePushNotification')
        ->and($contents)->toContain('getTitle')
        ->and($contents)->toContain('getBody')
        ->and($contents)->toContain('getActionUrl')
        ->and($contents)->toContain('system_announcement');
});

// ── Mail class ───────────────────────────────────────────────────────────────

test('SystemAnnouncementMail extends BaseMailableNotification', function () {
    $projectRoot = dirname(__DIR__, 3);
    $mailFile = $projectRoot.'/app/Mail/SystemAnnouncementMail.php';

    expect(file_exists($mailFile))->toBeTrue();

    $contents = file_get_contents($mailFile);
    expect($contents)->toContain('extends BaseMailableNotification')
        ->and($contents)->toContain('DancyMeals Announcement');
});

// ── Console command ──────────────────────────────────────────────────────────

test('DispatchScheduledAnnouncementsCommand has correct signature', function () {
    $projectRoot = dirname(__DIR__, 3);
    $commandFile = $projectRoot.'/app/Console/Commands/DispatchScheduledAnnouncementsCommand.php';

    expect(file_exists($commandFile))->toBeTrue();

    $contents = file_get_contents($commandFile);
    expect($contents)->toContain('dancymeals:dispatch-scheduled-announcements');
});

// ── Routes ───────────────────────────────────────────────────────────────────

test('announcement routes are registered in web.php', function () {
    $projectRoot = dirname(__DIR__, 3);
    $routesFile = $projectRoot.'/routes/web.php';

    $contents = file_get_contents($routesFile);
    expect($contents)->toContain('admin.announcements.index')
        ->and($contents)->toContain('admin.announcements.create')
        ->and($contents)->toContain('admin.announcements.store')
        ->and($contents)->toContain('admin.announcements.cancel');
});
