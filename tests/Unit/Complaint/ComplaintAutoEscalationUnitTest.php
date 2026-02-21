<?php

use App\Models\Complaint;
use App\Notifications\ComplaintEscalatedAdminNotification;
use App\Notifications\ComplaintEscalatedClientNotification;
use App\Notifications\ComplaintEscalatedCookNotification;
use App\Services\ComplaintEscalationService;

/**
 * F-185: Unit tests for Complaint Auto-Escalation.
 *
 * Tests constants, notification structure (no DB/translator calls),
 * and artisan command metadata.
 */
$projectRoot = dirname(__DIR__, 2);

// --- Service Constants ---

it('has correct escalation threshold constant', function () {
    expect(ComplaintEscalationService::ESCALATION_THRESHOLD_HOURS)->toBe(24);
});

it('has correct batch size constant', function () {
    expect(ComplaintEscalationService::BATCH_SIZE)->toBe(100);
});

// --- Model Constants ---

it('has ESCALATION_AUTO_24H constant on Complaint model', function () {
    expect(Complaint::ESCALATION_AUTO_24H)->toBe('auto_24h');
});

it('has ESCALATION_MANUAL_CLIENT constant', function () {
    expect(Complaint::ESCALATION_MANUAL_CLIENT)->toBe('manual_client');
});

it('has ESCALATION_MANUAL_COOK constant', function () {
    expect(Complaint::ESCALATION_MANUAL_COOK)->toBe('manual_cook');
});

it('has escalated status in COOK_STATUSES', function () {
    expect(Complaint::COOK_STATUSES)->toContain('escalated');
});

it('has escalated status in ALL_STATUSES', function () {
    expect(Complaint::ALL_STATUSES)->toContain('escalated');
});

it('has open status in COOK_STATUSES', function () {
    expect(Complaint::COOK_STATUSES)->toContain('open');
});

it('has in_review status in COOK_STATUSES', function () {
    expect(Complaint::COOK_STATUSES)->toContain('in_review');
});

it('has resolved status in COOK_STATUSES', function () {
    expect(Complaint::COOK_STATUSES)->toContain('resolved');
});

// --- Notification Class Structure (no app context needed) ---

it('admin notification extends BasePushNotification', function () {
    $complaint = new Complaint;
    $notification = new ComplaintEscalatedAdminNotification($complaint);
    expect($notification)->toBeInstanceOf(\App\Notifications\BasePushNotification::class);
});

it('client notification extends BasePushNotification', function () {
    $complaint = new Complaint;
    $notification = new ComplaintEscalatedClientNotification($complaint);
    expect($notification)->toBeInstanceOf(\App\Notifications\BasePushNotification::class);
});

it('cook notification extends BasePushNotification', function () {
    $complaint = new Complaint;
    $notification = new ComplaintEscalatedCookNotification($complaint);
    expect($notification)->toBeInstanceOf(\App\Notifications\BasePushNotification::class);
});

it('all three notifications implement ShouldQueue', function () {
    $complaint = new Complaint;

    expect(new ComplaintEscalatedAdminNotification($complaint))
        ->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
    expect(new ComplaintEscalatedClientNotification($complaint))
        ->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
    expect(new ComplaintEscalatedCookNotification($complaint))
        ->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

// --- URL Generation (pure string — no __() or DB) ---

it('admin notification links to admin complaint page', function () {
    $complaint = new Complaint;
    $complaint->id = 42;
    $notification = new ComplaintEscalatedAdminNotification($complaint);

    expect($notification->getActionUrl(new \stdClass))->toBe('/vault-entry/complaints/42');
});

it('client notification links to client complaint page', function () {
    $complaint = new Complaint;
    $complaint->id = 42;
    $complaint->order_id = 10;
    $notification = new ComplaintEscalatedClientNotification($complaint);

    expect($notification->getActionUrl(new \stdClass))->toBe('/my-orders/10/complaint/42');
});

it('cook notification links to cook dashboard complaint page', function () {
    $complaint = new Complaint;
    $complaint->id = 42;
    $notification = new ComplaintEscalatedCookNotification($complaint);

    expect($notification->getActionUrl(new \stdClass))->toBe('/dashboard/complaints/42');
});

// --- Tag Uniqueness (pure string — no __() or DB) ---

it('notifications have unique tags per complaint', function () {
    $complaint1 = new Complaint;
    $complaint1->id = 1;
    $complaint2 = new Complaint;
    $complaint2->id = 2;

    $admin1 = new ComplaintEscalatedAdminNotification($complaint1);
    $admin2 = new ComplaintEscalatedAdminNotification($complaint2);

    expect($admin1->getTag(new \stdClass))
        ->not->toBe($admin2->getTag(new \stdClass));
});

it('admin notification tag format is complaint-escalated-{id}', function () {
    $complaint = new Complaint;
    $complaint->id = 99;

    $notification = new ComplaintEscalatedAdminNotification($complaint);
    expect($notification->getTag(new \stdClass))->toBe('complaint-escalated-99');
});

it('client notification tag format is complaint-escalated-client-{id}', function () {
    $complaint = new Complaint;
    $complaint->id = 99;

    $notification = new ComplaintEscalatedClientNotification($complaint);
    expect($notification->getTag(new \stdClass))->toBe('complaint-escalated-client-99');
});

it('cook notification tag format is complaint-escalated-cook-{id}', function () {
    $complaint = new Complaint;
    $complaint->id = 99;

    $notification = new ComplaintEscalatedCookNotification($complaint);
    expect($notification->getTag(new \stdClass))->toBe('complaint-escalated-cook-99');
});

// --- Command Metadata ---

it('has correct artisan command signature', function () {
    $command = new \App\Console\Commands\EscalateOverdueComplaintsCommand;
    expect($command->getName())->toBe('dancymeals:escalate-overdue-complaints');
});

it('has correct artisan command description', function () {
    $command = new \App\Console\Commands\EscalateOverdueComplaintsCommand;
    expect($command->getDescription())->toContain('24 hours');
});
