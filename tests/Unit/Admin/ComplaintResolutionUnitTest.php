<?php

use App\Models\Complaint;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ComplaintResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

// ── Model Tests ──────────────────────────────────────────────────────

test('complaint has resolution type constants', function () {
    expect(Complaint::RESOLUTION_TYPES)->toBe([
        'dismiss',
        'partial_refund',
        'full_refund',
        'warning',
        'suspend',
    ]);
});

test('isResolved returns true for resolved status', function () {
    $complaint = Complaint::factory()->resolved()->make();
    expect($complaint->isResolved())->toBeTrue();
});

test('isResolved returns true for dismissed status', function () {
    $complaint = Complaint::factory()->dismissed()->make();
    expect($complaint->isResolved())->toBeTrue();
});

test('isResolved returns false for pending_resolution status', function () {
    $complaint = Complaint::factory()->escalated()->make();
    expect($complaint->isResolved())->toBeFalse();
});

test('resolutionTypeLabel returns correct labels', function () {
    $complaint = new Complaint;

    $complaint->resolution_type = 'dismiss';
    expect($complaint->resolutionTypeLabel())->toBe('Dismissed');

    $complaint->resolution_type = 'partial_refund';
    expect($complaint->resolutionTypeLabel())->toBe('Partial Refund');

    $complaint->resolution_type = 'full_refund';
    expect($complaint->resolutionTypeLabel())->toBe('Full Refund');

    $complaint->resolution_type = 'warning';
    expect($complaint->resolutionTypeLabel())->toBe('Warning to Cook');

    $complaint->resolution_type = 'suspend';
    expect($complaint->resolutionTypeLabel())->toBe('Cook Suspended');
});

test('complaint casts resolution fields correctly', function () {
    $complaint = Complaint::factory()->resolvedWithSuspension(7)->create();

    expect($complaint->suspension_ends_at)->toBeInstanceOf(\Carbon\Carbon::class)
        ->and($complaint->refund_amount)->toBeNull();
});

test('complaint factory resolved with partial refund state works', function () {
    $complaint = Complaint::factory()->resolvedWithPartialRefund(5000)->create();

    expect($complaint->resolution_type)->toBe('partial_refund')
        ->and((float) $complaint->refund_amount)->toBe(5000.0)
        ->and($complaint->status)->toBe('resolved');
});

// ── Service Tests ────────────────────────────────────────────────────

test('service resolves complaint with dismiss', function () {
    $admin = $this->createUserWithRole('admin');
    $complaint = Complaint::factory()->escalated()->create();

    $service = new ComplaintResolutionService;
    $result = $service->resolve($complaint, [
        'resolution_type' => 'dismiss',
        'resolution_notes' => 'This is a subjective taste preference.',
    ], $admin);

    expect($result->status)->toBe('dismissed')
        ->and($result->resolution_type)->toBe('dismiss')
        ->and($result->resolution_notes)->toBe('This is a subjective taste preference.')
        ->and($result->resolved_by)->toBe($admin->id)
        ->and($result->resolved_at)->not->toBeNull();
});

test('service resolves complaint with partial refund', function () {
    $admin = $this->createUserWithRole('admin');
    $complaint = Complaint::factory()->escalated()->create();

    $service = new ComplaintResolutionService;
    $result = $service->resolve($complaint, [
        'resolution_type' => 'partial_refund',
        'resolution_notes' => 'Partial refund for late delivery issue.',
        'refund_amount' => 3000,
    ], $admin);

    expect($result->status)->toBe('resolved')
        ->and($result->resolution_type)->toBe('partial_refund')
        ->and((float) $result->refund_amount)->toBe(3000.0);
});

test('service resolves complaint with full refund using payment amount', function () {
    $admin = $this->createUserWithRole('admin');
    $complaint = Complaint::factory()->escalated()->create([
        'order_id' => 1001,
    ]);

    PaymentTransaction::factory()->create([
        'order_id' => 1001,
        'status' => 'successful',
        'amount' => 8500,
    ]);

    $service = new ComplaintResolutionService;
    $result = $service->resolve($complaint, [
        'resolution_type' => 'full_refund',
        'resolution_notes' => 'Full refund issued due to wrong order.',
    ], $admin);

    expect($result->status)->toBe('resolved')
        ->and($result->resolution_type)->toBe('full_refund')
        ->and((float) $result->refund_amount)->toBe(8500.0);
});

test('service resolves complaint with warning and logs activity', function () {
    $admin = $this->createUserWithRole('admin');
    $cook = User::factory()->create();
    $complaint = Complaint::factory()->escalated()->create(['cook_id' => $cook->id]);

    $service = new ComplaintResolutionService;
    $service->resolve($complaint, [
        'resolution_type' => 'warning',
        'resolution_notes' => 'Unprofessional communication with client noted.',
    ], $admin);

    // Check warning was logged on cook's activity
    $warningLog = Activity::query()
        ->where('log_name', 'complaints')
        ->where('subject_type', User::class)
        ->where('subject_id', $cook->id)
        ->where('description', 'warning_issued')
        ->first();

    expect($warningLog)->not->toBeNull()
        ->and($warningLog->causer_id)->toBe($admin->id)
        ->and($warningLog->properties['complaint_id'])->toBe($complaint->id);
});

test('service resolves complaint with suspension and deactivates tenant', function () {
    $admin = $this->createUserWithRole('admin');
    $tenant = Tenant::factory()->create(['is_active' => true]);
    $cook = User::factory()->create();
    $complaint = Complaint::factory()->escalated()->create([
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new ComplaintResolutionService;
    $service->resolve($complaint, [
        'resolution_type' => 'suspend',
        'resolution_notes' => 'Multiple complaints about food quality.',
        'suspension_days' => 7,
    ], $admin);

    $tenant->refresh();
    $complaint->refresh();

    expect($tenant->is_active)->toBeFalse()
        ->and($complaint->suspension_days)->toBe(7)
        ->and($complaint->suspension_ends_at)->not->toBeNull();
});

test('service throws exception when resolving already resolved complaint', function () {
    $admin = $this->createUserWithRole('admin');
    $complaint = Complaint::factory()->resolved()->create();

    $service = new ComplaintResolutionService;

    expect(fn () => $service->resolve($complaint, [
        'resolution_type' => 'dismiss',
        'resolution_notes' => 'Attempting re-resolution.',
    ], $admin))->toThrow(\LogicException::class);
});

test('service logs resolution in activity log', function () {
    $admin = $this->createUserWithRole('admin');
    $complaint = Complaint::factory()->escalated()->create();

    $service = new ComplaintResolutionService;
    $service->resolve($complaint, [
        'resolution_type' => 'dismiss',
        'resolution_notes' => 'No actionable issue found.',
    ], $admin);

    $resolutionLog = Activity::query()
        ->where('log_name', 'complaints')
        ->where('subject_type', Complaint::class)
        ->where('subject_id', $complaint->id)
        ->where('description', 'complaint_resolved')
        ->first();

    expect($resolutionLog)->not->toBeNull()
        ->and($resolutionLog->causer_id)->toBe($admin->id)
        ->and($resolutionLog->properties['resolution_type'])->toBe('dismiss');
});

test('getCookWarningCount returns correct count', function () {
    $cook = User::factory()->create();

    activity('complaints')
        ->performedOn($cook)
        ->log('warning_issued');

    activity('complaints')
        ->performedOn($cook)
        ->log('cook_suspended');

    $service = new ComplaintResolutionService;
    expect($service->getCookWarningCount($cook))->toBe(2);
});

test('getCookComplaintCount returns correct count', function () {
    $cook = User::factory()->create();
    Complaint::factory()->count(3)->create(['cook_id' => $cook->id]);

    $service = new ComplaintResolutionService;
    expect($service->getCookComplaintCount($cook))->toBe(3);
});

test('getCookPreviousSuspensions returns suspension history', function () {
    $cook = User::factory()->create();

    Complaint::factory()->resolvedWithSuspension(5)->create(['cook_id' => $cook->id]);
    Complaint::factory()->resolvedWithSuspension(14)->create(['cook_id' => $cook->id]);

    $service = new ComplaintResolutionService;
    $suspensions = $service->getCookPreviousSuspensions($cook);

    expect($suspensions)->toHaveCount(2);
});

test('isOrderAlreadyRefunded returns true when order has been refunded', function () {
    $complaint1 = Complaint::factory()->resolvedWithPartialRefund(2000)->create([
        'order_id' => 999,
    ]);

    $complaint2 = Complaint::factory()->escalated()->create([
        'order_id' => 999,
    ]);

    $service = new ComplaintResolutionService;
    expect($service->isOrderAlreadyRefunded($complaint2))->toBeTrue();
});

test('isOrderAlreadyRefunded returns false when no refund exists', function () {
    $complaint = Complaint::factory()->escalated()->create([
        'order_id' => 888,
    ]);

    $service = new ComplaintResolutionService;
    expect($service->isOrderAlreadyRefunded($complaint))->toBeFalse();
});

// ── Form Request Tests ───────────────────────────────────────────────

test('resolve complaint request validates resolution type is required', function () {
    $admin = $this->createUserWithRole('admin');
    $complaint = Complaint::factory()->escalated()->create();

    $response = $this->actingAs($admin)
        ->post(url('/vault-entry/complaints/'.$complaint->id.'/resolve'), [
            'resolution_notes' => 'Some notes about this resolution.',
        ]);

    // Gale will handle this as SSE with validation errors
    // The point is: it doesn't crash, and the complaint isn't resolved
    $complaint->refresh();
    expect($complaint->isResolved())->toBeFalse();
});

test('resolve complaint request validates resolution notes minimum length', function () {
    $admin = $this->createUserWithRole('admin');
    $complaint = Complaint::factory()->escalated()->create();

    $response = $this->actingAs($admin)
        ->post(url('/vault-entry/complaints/'.$complaint->id.'/resolve'), [
            'resolution_type' => 'dismiss',
            'resolution_notes' => 'short',
        ]);

    $complaint->refresh();
    expect($complaint->isResolved())->toBeFalse();
});

test('resolve complaint request validates suspension days required for suspend type', function () {
    $admin = $this->createUserWithRole('admin');
    $complaint = Complaint::factory()->escalated()->create();

    $response = $this->actingAs($admin)
        ->post(url('/vault-entry/complaints/'.$complaint->id.'/resolve'), [
            'resolution_type' => 'suspend',
            'resolution_notes' => 'Multiple serious complaints against this cook.',
        ]);

    $complaint->refresh();
    expect($complaint->isResolved())->toBeFalse();
});

// ── Authorization Tests ──────────────────────────────────────────────

test('unauthorized user cannot resolve complaint', function () {
    $client = $this->createUserWithRole('client');
    $complaint = Complaint::factory()->escalated()->create();

    $response = $this->actingAs($client)
        ->post(url('/vault-entry/complaints/'.$complaint->id.'/resolve'), [
            'resolution_type' => 'dismiss',
            'resolution_notes' => 'Attempting unauthorized resolution.',
        ]);

    $response->assertForbidden();
});

test('admin user can access complaint show page', function () {
    $admin = $this->createUserWithRole('admin');
    $complaint = Complaint::factory()->escalated()->create();

    $response = $this->actingAs($admin)
        ->get(url('/vault-entry/complaints/'.$complaint->id));

    $response->assertSuccessful();
});
