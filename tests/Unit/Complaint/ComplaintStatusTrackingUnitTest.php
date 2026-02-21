<?php

use App\Models\Complaint;
use App\Services\ComplaintTrackingService;

/**
 * F-187: Unit tests for Complaint Status Tracking.
 *
 * Tests constants, status mapping, and XAF formatting — all pure logic
 * without DB, translator, or app context dependencies.
 */

// --- Service Constants ---

it('has the four timeline states in correct order', function () {
    expect(ComplaintTrackingService::TIMELINE_STATES)
        ->toBe(['open', 'in_review', 'escalated', 'resolved']);
});

it('has four timeline states', function () {
    expect(ComplaintTrackingService::TIMELINE_STATES)->toHaveCount(4);
});

it('first timeline state is open', function () {
    expect(ComplaintTrackingService::TIMELINE_STATES[0])->toBe('open');
});

it('last timeline state is resolved', function () {
    expect(ComplaintTrackingService::TIMELINE_STATES[3])->toBe('resolved');
});

// --- Status Mapping (mapToTimelineStatus) --- pure string logic, no app context

it('maps open status to open timeline state', function () {
    $service = new ComplaintTrackingService;
    expect($service->mapToTimelineStatus('open'))->toBe('open');
});

it('maps in_review status to in_review timeline state', function () {
    $service = new ComplaintTrackingService;
    expect($service->mapToTimelineStatus('in_review'))->toBe('in_review');
});

it('maps responded status to in_review timeline state', function () {
    $service = new ComplaintTrackingService;
    expect($service->mapToTimelineStatus('responded'))->toBe('in_review');
});

it('maps escalated status to escalated timeline state', function () {
    $service = new ComplaintTrackingService;
    expect($service->mapToTimelineStatus('escalated'))->toBe('escalated');
});

it('maps pending_resolution status to escalated timeline state', function () {
    $service = new ComplaintTrackingService;
    expect($service->mapToTimelineStatus('pending_resolution'))->toBe('escalated');
});

it('maps under_review status to escalated timeline state', function () {
    $service = new ComplaintTrackingService;
    expect($service->mapToTimelineStatus('under_review'))->toBe('escalated');
});

it('maps resolved status to resolved timeline state', function () {
    $service = new ComplaintTrackingService;
    expect($service->mapToTimelineStatus('resolved'))->toBe('resolved');
});

it('maps dismissed status to resolved timeline state', function () {
    $service = new ComplaintTrackingService;
    expect($service->mapToTimelineStatus('dismissed'))->toBe('resolved');
});

it('maps unknown status to open as default', function () {
    $service = new ComplaintTrackingService;
    expect($service->mapToTimelineStatus('unknown_status'))->toBe('open');
});

it('maps empty string to open as default', function () {
    $service = new ComplaintTrackingService;
    expect($service->mapToTimelineStatus(''))->toBe('open');
});

// --- Model Status Constants ---

it('has resolved in ALL_STATUSES', function () {
    expect(Complaint::ALL_STATUSES)->toContain('resolved');
});

it('has dismissed in ADMIN_STATUSES', function () {
    expect(Complaint::ADMIN_STATUSES)->toContain('dismissed');
});

it('has open in ALL_STATUSES', function () {
    expect(Complaint::ALL_STATUSES)->toContain('open');
});

it('has in_review in ALL_STATUSES', function () {
    expect(Complaint::ALL_STATUSES)->toContain('in_review');
});

it('has pending_resolution in ALL_STATUSES', function () {
    expect(Complaint::ALL_STATUSES)->toContain('pending_resolution');
});

it('has under_review in ALL_STATUSES', function () {
    expect(Complaint::ALL_STATUSES)->toContain('under_review');
});

// --- isResolved / isActive pure model logic ---

it('complaint with resolved status is resolved', function () {
    $complaint = new Complaint;
    $complaint->status = 'resolved';
    expect($complaint->isResolved())->toBeTrue();
});

it('complaint with dismissed status is resolved', function () {
    $complaint = new Complaint;
    $complaint->status = 'dismissed';
    expect($complaint->isResolved())->toBeTrue();
});

it('complaint with open status is not resolved', function () {
    $complaint = new Complaint;
    $complaint->status = 'open';
    expect($complaint->isResolved())->toBeFalse();
});

it('complaint with escalated status is not resolved', function () {
    $complaint = new Complaint;
    $complaint->status = 'escalated';
    expect($complaint->isResolved())->toBeFalse();
});

it('complaint with open status is active', function () {
    $complaint = new Complaint;
    $complaint->status = 'open';
    expect($complaint->isActive())->toBeTrue();
});

it('complaint with in_review status is active', function () {
    $complaint = new Complaint;
    $complaint->status = 'in_review';
    expect($complaint->isActive())->toBeTrue();
});

it('complaint with resolved status is not active', function () {
    $complaint = new Complaint;
    $complaint->status = 'resolved';
    expect($complaint->isActive())->toBeFalse();
});

it('complaint with dismissed status is not active', function () {
    $complaint = new Complaint;
    $complaint->status = 'dismissed';
    expect($complaint->isActive())->toBeFalse();
});

// --- XAF Formatting ---

it('formats XAF amount with thousand separators', function () {
    expect(ComplaintTrackingService::formatXAF(3000))->toBe('3,000 XAF');
});

it('formats large XAF amounts correctly', function () {
    expect(ComplaintTrackingService::formatXAF(15000))->toBe('15,000 XAF');
    expect(ComplaintTrackingService::formatXAF(1500000))->toBe('1,500,000 XAF');
});

it('formats zero XAF correctly', function () {
    expect(ComplaintTrackingService::formatXAF(0))->toBe('0 XAF');
});

it('formats float XAF by truncating to integer', function () {
    expect(ComplaintTrackingService::formatXAF(3500.75))->toBe('3,500 XAF');
});

// --- Access Control (canViewComplaint) - pure ID comparison ---

it('BR-238: allows filing client to view complaint', function () {
    $user = new \App\Models\User;
    $user->id = 10;

    $complaint = new Complaint;
    $complaint->client_id = 10;
    $complaint->cook_id = 20;

    $service = new ComplaintTrackingService;
    expect($service->canViewComplaint($complaint, $user))->toBeTrue();
});

it('BR-238: allows tenant cook to view complaint', function () {
    $user = new \App\Models\User;
    $user->id = 20;

    $complaint = new Complaint;
    $complaint->client_id = 10;
    $complaint->cook_id = 20;

    $service = new ComplaintTrackingService;
    expect($service->canViewComplaint($complaint, $user))->toBeTrue();
});

// --- Resolution Types ---

it('has all expected resolution types on Complaint model', function () {
    expect(Complaint::RESOLUTION_TYPES)->toContain('dismiss');
    expect(Complaint::RESOLUTION_TYPES)->toContain('partial_refund');
    expect(Complaint::RESOLUTION_TYPES)->toContain('full_refund');
    expect(Complaint::RESOLUTION_TYPES)->toContain('warning');
    expect(Complaint::RESOLUTION_TYPES)->toContain('suspend');
});

// --- Timeline state ordering consistency ---

it('open is before in_review in timeline order', function () {
    $states = ComplaintTrackingService::TIMELINE_STATES;
    expect(array_search('open', $states, true))
        ->toBeLessThan(array_search('in_review', $states, true));
});

it('in_review is before escalated in timeline order', function () {
    $states = ComplaintTrackingService::TIMELINE_STATES;
    expect(array_search('in_review', $states, true))
        ->toBeLessThan(array_search('escalated', $states, true));
});

it('escalated is before resolved in timeline order', function () {
    $states = ComplaintTrackingService::TIMELINE_STATES;
    expect(array_search('escalated', $states, true))
        ->toBeLessThan(array_search('resolved', $states, true));
});

// --- Every status maps to a valid timeline state ---

it('all ALL_STATUSES map to valid timeline states', function () {
    $service = new ComplaintTrackingService;
    $validStates = ComplaintTrackingService::TIMELINE_STATES;

    foreach (Complaint::ALL_STATUSES as $status) {
        $mapped = $service->mapToTimelineStatus($status);
        expect($validStates)->toContain($mapped);
    }
});
