<?php

use App\Models\Complaint;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| F-060: Complaint Escalation Queue — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for Complaint model, scopes, accessors, and business logic.
|
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

// ============================================================
// Model Constants
// ============================================================

test('complaint has valid category constants', function () {
    $expected = ['food_quality', 'late_delivery', 'missing_items', 'wrong_order', 'rude_behavior', 'other'];
    expect(Complaint::CATEGORIES)->toBe($expected);
});

test('complaint has valid admin status constants', function () {
    $expected = ['pending_resolution', 'under_review', 'resolved', 'dismissed'];
    expect(Complaint::ADMIN_STATUSES)->toBe($expected);
});

test('complaint has valid all status constants', function () {
    $expected = ['open', 'in_review', 'responded', 'escalated', 'pending_resolution', 'under_review', 'resolved', 'dismissed'];
    expect(Complaint::ALL_STATUSES)->toBe($expected);
});

test('complaint has escalation reason constants', function () {
    expect(Complaint::ESCALATION_AUTO_24H)->toBe('auto_24h');
    expect(Complaint::ESCALATION_MANUAL_CLIENT)->toBe('manual_client');
    expect(Complaint::ESCALATION_MANUAL_COOK)->toBe('manual_cook');
});

// ============================================================
// Category Labels
// ============================================================

test('categoryLabel returns translated label for each category', function (string $category, string $expected) {
    $complaint = Complaint::factory()->make(['category' => $category]);
    expect($complaint->categoryLabel())->toBe($expected);
})->with([
    ['food_quality', 'Food Quality'],
    ['late_delivery', 'Late Delivery'],
    ['missing_items', 'Missing Items'],
    ['wrong_order', 'Wrong Order'],
    ['rude_behavior', 'Rude Behavior'],
    ['other', 'Other'],
]);

// ============================================================
// Status Labels
// ============================================================

test('statusLabel returns translated label for each status', function (string $status, string $expected) {
    $complaint = Complaint::factory()->make(['status' => $status]);
    expect($complaint->statusLabel())->toBe($expected);
})->with([
    ['open', 'Open'],
    ['responded', 'Responded'],
    ['escalated', 'Escalated'],
    ['pending_resolution', 'Pending Resolution'],
    ['under_review', 'Under Review'],
    ['resolved', 'Resolved'],
    ['dismissed', 'Dismissed'],
]);

// ============================================================
// Escalation Reason Labels
// ============================================================

test('escalationReasonLabel returns correct label for auto-escalation', function () {
    $complaint = Complaint::factory()->make(['escalation_reason' => 'auto_24h']);
    expect($complaint->escalationReasonLabel())->toBe('Auto-escalated (24h no response)');
});

test('escalationReasonLabel returns correct label for client escalation', function () {
    $complaint = Complaint::factory()->make(['escalation_reason' => 'manual_client']);
    expect($complaint->escalationReasonLabel())->toBe('Escalated by client');
});

test('escalationReasonLabel returns correct label for cook escalation', function () {
    $complaint = Complaint::factory()->make(['escalation_reason' => 'manual_cook']);
    expect($complaint->escalationReasonLabel())->toBe('Escalated by cook');
});

// ============================================================
// isUnresolved
// ============================================================

test('isUnresolved returns true for pending_resolution', function () {
    $complaint = Complaint::factory()->make(['status' => 'pending_resolution']);
    expect($complaint->isUnresolved())->toBeTrue();
});

test('isUnresolved returns true for under_review', function () {
    $complaint = Complaint::factory()->make(['status' => 'under_review']);
    expect($complaint->isUnresolved())->toBeTrue();
});

test('isUnresolved returns true for escalated', function () {
    $complaint = Complaint::factory()->make(['status' => 'escalated']);
    expect($complaint->isUnresolved())->toBeTrue();
});

test('isUnresolved returns false for resolved', function () {
    $complaint = Complaint::factory()->make(['status' => 'resolved']);
    expect($complaint->isUnresolved())->toBeFalse();
});

test('isUnresolved returns false for dismissed', function () {
    $complaint = Complaint::factory()->make(['status' => 'dismissed']);
    expect($complaint->isUnresolved())->toBeFalse();
});

// ============================================================
// isOverdue
// ============================================================

test('isOverdue returns true when escalated >48h ago and unresolved', function () {
    $complaint = Complaint::factory()->make([
        'status' => 'pending_resolution',
        'escalated_at' => now()->subHours(50),
    ]);
    expect($complaint->isOverdue())->toBeTrue();
});

test('isOverdue returns false when escalated <48h ago', function () {
    $complaint = Complaint::factory()->make([
        'status' => 'pending_resolution',
        'escalated_at' => now()->subHours(24),
    ]);
    expect($complaint->isOverdue())->toBeFalse();
});

test('isOverdue returns false when resolved even if old', function () {
    $complaint = Complaint::factory()->make([
        'status' => 'resolved',
        'escalated_at' => now()->subDays(5),
    ]);
    expect($complaint->isOverdue())->toBeFalse();
});

// ============================================================
// timeSinceEscalation
// ============================================================

test('timeSinceEscalation returns dash when not escalated', function () {
    $complaint = Complaint::factory()->make(['escalated_at' => null]);
    expect($complaint->timeSinceEscalation())->toBe('—');
});

test('timeSinceEscalation returns human-readable time', function () {
    $complaint = Complaint::factory()->make([
        'escalated_at' => now()->subHours(3),
    ]);
    expect($complaint->timeSinceEscalation())->toContain('hours ago');
});

// ============================================================
// Scopes
// ============================================================

test('scopeEscalated returns only escalated complaints', function () {
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create();
    $cook = User::factory()->create();

    Complaint::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'is_escalated' => false,
    ]);
    Complaint::factory()->escalated()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect(Complaint::escalated()->count())->toBe(1);
});

test('scopeOfCategory filters by category', function () {
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create();
    $cook = User::factory()->create();

    Complaint::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'category' => 'food_quality',
    ]);
    Complaint::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'category' => 'late_delivery',
    ]);

    expect(Complaint::ofCategory('food_quality')->count())->toBe(1);
    expect(Complaint::ofCategory('late_delivery')->count())->toBe(1);
    expect(Complaint::ofCategory(null)->count())->toBe(2);
    expect(Complaint::ofCategory('invalid')->count())->toBe(2);
});

test('scopeOfStatus filters by admin status', function () {
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create();
    $cook = User::factory()->create();

    Complaint::factory()->escalated()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'status' => 'pending_resolution',
    ]);
    Complaint::factory()->resolved()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect(Complaint::ofStatus('pending_resolution')->count())->toBe(1);
    expect(Complaint::ofStatus('resolved')->count())->toBe(1);
    expect(Complaint::ofStatus(null)->count())->toBe(2);
});

test('scopeSearch filters by client name', function () {
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create(['name' => 'Amina Bello']);
    $cook = User::factory()->create();

    Complaint::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect(Complaint::search('Amina')->count())->toBe(1);
    expect(Complaint::search('nonexistent')->count())->toBe(0);
});

test('scopeSearch filters by numeric complaint ID', function () {
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create();
    $cook = User::factory()->create();

    $complaint = Complaint::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect(Complaint::search((string) $complaint->id)->count())->toBe(1);
});

test('scopePrioritySort puts unresolved complaints first', function () {
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create();
    $cook = User::factory()->create();

    // Create a resolved complaint (escalated 1 day ago)
    $resolved = Complaint::factory()->resolved()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'escalated_at' => now()->subDays(1),
    ]);

    // Create a pending complaint (escalated 2 days ago — older)
    $pending = Complaint::factory()->escalated()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'escalated_at' => now()->subDays(2),
    ]);

    $results = Complaint::escalated()->prioritySort()->get();
    expect($results->first()->id)->toBe($pending->id);
    expect($results->last()->id)->toBe($resolved->id);
});

// ============================================================
// Relationships
// ============================================================

test('complaint belongs to client', function () {
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create();
    $cook = User::factory()->create();

    $complaint = Complaint::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($complaint->client)->toBeInstanceOf(User::class);
    expect($complaint->client->id)->toBe($client->id);
});

test('complaint belongs to cook', function () {
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create();
    $cook = User::factory()->create();

    $complaint = Complaint::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($complaint->cook)->toBeInstanceOf(User::class);
    expect($complaint->cook->id)->toBe($cook->id);
});

test('complaint belongs to tenant', function () {
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create();
    $cook = User::factory()->create();

    $complaint = Complaint::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($complaint->tenant)->toBeInstanceOf(Tenant::class);
    expect($complaint->tenant->id)->toBe($tenant->id);
});

// ============================================================
// Factory States
// ============================================================

test('factory creates valid complaint', function () {
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create();
    $cook = User::factory()->create();

    $complaint = Complaint::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($complaint->exists)->toBeTrue();
    expect(in_array($complaint->category, Complaint::CATEGORIES, true))->toBeTrue();
});

test('factory escalated state sets correct fields', function () {
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create();
    $cook = User::factory()->create();

    $complaint = Complaint::factory()->escalated()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($complaint->is_escalated)->toBeTrue();
    expect($complaint->status)->toBe('pending_resolution');
    expect($complaint->escalated_at)->not()->toBeNull();
    expect($complaint->escalation_reason)->not()->toBeNull();
});

test('factory autoEscalated state uses auto_24h reason', function () {
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create();
    $cook = User::factory()->create();

    $complaint = Complaint::factory()->autoEscalated()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($complaint->escalation_reason)->toBe('auto_24h');
});

test('factory resolved state sets resolved fields', function () {
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create();
    $cook = User::factory()->create();

    $complaint = Complaint::factory()->resolved()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($complaint->status)->toBe('resolved');
    expect($complaint->resolved_at)->not()->toBeNull();
    expect($complaint->resolution_notes)->not()->toBeNull();
});

// ============================================================
// Casts
// ============================================================

test('is_escalated is cast to boolean', function () {
    $complaint = Complaint::factory()->make(['is_escalated' => true]);
    expect($complaint->is_escalated)->toBeBool();
});

test('escalated_at is cast to datetime', function () {
    $complaint = Complaint::factory()->make(['escalated_at' => now()]);
    expect($complaint->escalated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('submitted_at is cast to datetime', function () {
    $complaint = Complaint::factory()->make(['submitted_at' => now()]);
    expect($complaint->submitted_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});
