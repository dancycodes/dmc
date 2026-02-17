<?php

use App\Models\PayoutTask;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

/**
 * F-065: Manual Payout Task Queue — Unit Tests
 *
 * Tests for PayoutTask model, PayoutService, and business rules.
 */

// ==========================================
// PayoutTask Model Tests
// ==========================================

it('has correct table name', function () {
    $task = new PayoutTask;
    expect($task->getTable())->toBe('payout_tasks');
});

it('defines valid statuses', function () {
    expect(PayoutTask::STATUSES)->toBe(['pending', 'completed', 'manually_completed']);
    expect(PayoutTask::STATUS_PENDING)->toBe('pending');
    expect(PayoutTask::STATUS_COMPLETED)->toBe('completed');
    expect(PayoutTask::STATUS_MANUALLY_COMPLETED)->toBe('manually_completed');
});

it('defines maximum retries as 3', function () {
    // BR-202: Maximum 3 automatic retry attempts
    expect(PayoutTask::MAX_RETRIES)->toBe(3);
});

it('defines valid payment methods', function () {
    expect(PayoutTask::PAYMENT_METHODS)->toBe(['mtn_mobile_money', 'orange_money']);
});

it('casts amount to decimal', function () {
    $task = PayoutTask::factory()->make(['amount' => '45000.00']);
    expect($task->amount)->toBe('45000.00');
});

it('casts flutterwave_response to array', function () {
    $response = ['status' => 'error', 'message' => 'Failed'];
    $task = PayoutTask::factory()->make(['flutterwave_response' => $response]);
    expect($task->flutterwave_response)->toBe($response);
});

it('casts retry_count to integer', function () {
    $task = PayoutTask::factory()->make(['retry_count' => '2']);
    expect($task->retry_count)->toBeInt()->toBe(2);
});

it('casts datetime fields correctly', function () {
    $task = PayoutTask::factory()->make([
        'completed_at' => '2026-02-17 10:00:00',
        'requested_at' => '2026-02-15 08:00:00',
        'last_retry_at' => '2026-02-16 14:00:00',
    ]);
    expect($task->completed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($task->requested_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($task->last_retry_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

// ==========================================
// Status Check Methods
// ==========================================

it('identifies pending tasks', function () {
    $task = PayoutTask::factory()->make(['status' => PayoutTask::STATUS_PENDING]);
    expect($task->isPending())->toBeTrue();

    $task->status = PayoutTask::STATUS_COMPLETED;
    expect($task->isPending())->toBeFalse();
});

it('identifies resolved tasks', function () {
    $completed = PayoutTask::factory()->make(['status' => PayoutTask::STATUS_COMPLETED]);
    expect($completed->isResolved())->toBeTrue();

    $manual = PayoutTask::factory()->make(['status' => PayoutTask::STATUS_MANUALLY_COMPLETED]);
    expect($manual->isResolved())->toBeTrue();

    $pending = PayoutTask::factory()->make(['status' => PayoutTask::STATUS_PENDING]);
    expect($pending->isResolved())->toBeFalse();
});

it('checks retry availability correctly', function () {
    // BR-202: Maximum 3 retries
    $task = PayoutTask::factory()->make([
        'status' => PayoutTask::STATUS_PENDING,
        'retry_count' => 0,
    ]);
    expect($task->canRetry())->toBeTrue();

    $task->retry_count = 2;
    expect($task->canRetry())->toBeTrue();

    $task->retry_count = 3;
    expect($task->canRetry())->toBeFalse();

    // Resolved tasks cannot retry
    $resolved = PayoutTask::factory()->make([
        'status' => PayoutTask::STATUS_COMPLETED,
        'retry_count' => 0,
    ]);
    expect($resolved->canRetry())->toBeFalse();
});

// ==========================================
// Formatting Methods
// ==========================================

it('formats amount with currency', function () {
    $task = PayoutTask::factory()->make(['amount' => 45000, 'currency' => 'XAF']);
    expect($task->formattedAmount())->toBe('45,000 XAF');

    $task->amount = 1500000;
    expect($task->formattedAmount())->toBe('1,500,000 XAF');
});

it('returns payment method label', function () {
    $mtn = PayoutTask::factory()->make(['payment_method' => 'mtn_mobile_money']);
    expect($mtn->paymentMethodLabel())->toBe('MTN Mobile Money');

    $orange = PayoutTask::factory()->make(['payment_method' => 'orange_money']);
    expect($orange->paymentMethodLabel())->toBe('Orange Money');
});

it('returns status labels', function () {
    $pending = PayoutTask::factory()->make(['status' => PayoutTask::STATUS_PENDING]);
    expect($pending->statusLabel())->toBe(__('Pending'));

    $completed = PayoutTask::factory()->make(['status' => PayoutTask::STATUS_COMPLETED]);
    expect($completed->statusLabel())->toBe(__('Completed'));

    $manual = PayoutTask::factory()->make(['status' => PayoutTask::STATUS_MANUALLY_COMPLETED]);
    expect($manual->statusLabel())->toBe(__('Manually Completed'));
});

// ==========================================
// Relationship Tests
// ==========================================

it('belongs to a cook user', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $task = PayoutTask::factory()->create([
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($task->cook)->toBeInstanceOf(User::class);
    expect($task->cook->id)->toBe($cook->id);
});

it('belongs to a tenant', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $task = PayoutTask::factory()->create([
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($task->tenant)->toBeInstanceOf(Tenant::class);
    expect($task->tenant->id)->toBe($tenant->id);
});

it('belongs to completedByUser when resolved', function () {
    $admin = User::factory()->create();
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $task = PayoutTask::factory()->manuallyCompleted()->create([
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'completed_by' => $admin->id,
    ]);

    expect($task->completedByUser)->toBeInstanceOf(User::class);
    expect($task->completedByUser->id)->toBe($admin->id);
});

// ==========================================
// Scope Tests
// ==========================================

it('filters pending tasks with scope', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create();

    PayoutTask::factory()->pending()->create(['cook_id' => $cook->id, 'tenant_id' => $tenant->id]);
    PayoutTask::factory()->pending()->create(['cook_id' => $cook->id, 'tenant_id' => $tenant->id]);
    PayoutTask::factory()->completed()->create(['cook_id' => $cook->id, 'tenant_id' => $tenant->id]);

    expect(PayoutTask::pending()->count())->toBe(2);
});

it('filters resolved tasks with scope', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create();

    PayoutTask::factory()->pending()->create(['cook_id' => $cook->id, 'tenant_id' => $tenant->id]);
    PayoutTask::factory()->completed()->create(['cook_id' => $cook->id, 'tenant_id' => $tenant->id]);
    PayoutTask::factory()->manuallyCompleted()->create(['cook_id' => $cook->id, 'tenant_id' => $tenant->id]);

    expect(PayoutTask::resolved()->count())->toBe(2);
});

it('searches by cook name', function () {
    $cook = User::factory()->create(['name' => 'Chef Amara']);
    $otherCook = User::factory()->create(['name' => 'Chef Bih']);
    $tenant = Tenant::factory()->create();

    PayoutTask::factory()->create(['cook_id' => $cook->id, 'tenant_id' => $tenant->id]);
    PayoutTask::factory()->create(['cook_id' => $otherCook->id, 'tenant_id' => $tenant->id]);

    expect(PayoutTask::search('Amara')->count())->toBe(1);
});

it('searches by mobile money number', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create();

    PayoutTask::factory()->create([
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'mobile_money_number' => '+237670123456',
    ]);
    PayoutTask::factory()->create([
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'mobile_money_number' => '+237690987654',
    ]);

    expect(PayoutTask::search('670123')->count())->toBe(1);
});

it('returns all tasks when search is empty', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create();

    PayoutTask::factory()->count(3)->create(['cook_id' => $cook->id, 'tenant_id' => $tenant->id]);

    expect(PayoutTask::search('')->count())->toBe(3);
    expect(PayoutTask::search(null)->count())->toBe(3);
});

// ==========================================
// Factory State Tests
// ==========================================

it('creates pending task by default', function () {
    $task = PayoutTask::factory()->make();
    expect($task->status)->toBe(PayoutTask::STATUS_PENDING);
    expect($task->completed_at)->toBeNull();
    expect($task->completed_by)->toBeNull();
});

it('creates completed task with factory state', function () {
    $task = PayoutTask::factory()->completed()->make();
    expect($task->status)->toBe(PayoutTask::STATUS_COMPLETED);
    expect($task->completed_at)->not->toBeNull();
    expect($task->retry_count)->toBeGreaterThanOrEqual(1);
});

it('creates manually completed task with factory state', function () {
    $task = PayoutTask::factory()->manuallyCompleted()->make();
    expect($task->status)->toBe(PayoutTask::STATUS_MANUALLY_COMPLETED);
    expect($task->reference_number)->not->toBeNull();
    expect($task->resolution_notes)->not->toBeNull();
});

it('creates retries exhausted task with factory state', function () {
    $task = PayoutTask::factory()->retriesExhausted()->make();
    expect($task->status)->toBe(PayoutTask::STATUS_PENDING);
    expect($task->retry_count)->toBe(PayoutTask::MAX_RETRIES);
    expect($task->canRetry())->toBeFalse();
});

// ==========================================
// PayoutService Tests
// ==========================================

it('marks task as manually completed', function () {
    $admin = User::factory()->create();
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $task = PayoutTask::factory()->pending()->create([
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new PayoutService;
    $result = $service->markAsManuallyCompleted($task, [
        'reference_number' => 'MAN-123456',
        'resolution_notes' => 'Sent via bank app',
    ], $admin);

    expect($result->status)->toBe(PayoutTask::STATUS_MANUALLY_COMPLETED);
    expect($result->reference_number)->toBe('MAN-123456');
    expect($result->resolution_notes)->toBe('Sent via bank app');
    expect($result->completed_by)->toBe($admin->id);
    expect($result->completed_at)->not->toBeNull();
});

it('marks task as manually completed with only reference number', function () {
    $admin = User::factory()->create();
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $task = PayoutTask::factory()->pending()->create([
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new PayoutService;
    $result = $service->markAsManuallyCompleted($task, [
        'reference_number' => 'REF-789',
    ], $admin);

    expect($result->status)->toBe(PayoutTask::STATUS_MANUALLY_COMPLETED);
    expect($result->reference_number)->toBe('REF-789');
    expect($result->resolution_notes)->toBeNull();
});

it('prevents retry when max retries reached', function () {
    $admin = User::factory()->create();
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $task = PayoutTask::factory()->retriesExhausted()->create([
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new PayoutService;
    $result = $service->retryTransfer($task, $admin);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('Maximum retry attempts');
});

it('returns pending count correctly', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create();

    PayoutTask::factory()->pending()->count(3)->create(['cook_id' => $cook->id, 'tenant_id' => $tenant->id]);
    PayoutTask::factory()->completed()->create(['cook_id' => $cook->id, 'tenant_id' => $tenant->id]);
    PayoutTask::factory()->manuallyCompleted()->create(['cook_id' => $cook->id, 'tenant_id' => $tenant->id]);

    $service = new PayoutService;
    expect($service->getPendingCount())->toBe(3);
});

// ==========================================
// Activity Logging Tests
// ==========================================

it('logs activity when manually completing a task', function () {
    $admin = User::factory()->create();
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $task = PayoutTask::factory()->pending()->create([
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new PayoutService;
    $service->markAsManuallyCompleted($task, [
        'reference_number' => 'MAN-LOG-TEST',
    ], $admin);

    $activities = \Spatie\Activitylog\Models\Activity::where('subject_type', PayoutTask::class)
        ->where('subject_id', $task->id)
        ->where('log_name', 'payout_tasks')
        ->get();

    // Should have at least the explicit log entry and the model update log
    expect($activities->count())->toBeGreaterThanOrEqual(1);

    $manualLog = $activities->first(fn ($a) => ($a->properties['action'] ?? '') === 'manually_completed');
    expect($manualLog)->not->toBeNull();
    expect($manualLog->properties['reference_number'])->toBe('MAN-LOG-TEST');
    expect($manualLog->causer_id)->toBe($admin->id);
});

// ==========================================
// Validation Tests
// ==========================================

it('validates reference number is required for manual completion', function () {
    $rules = (new \App\Http\Requests\Admin\MarkPayoutCompleteRequest)->rules();

    expect($rules['reference_number'])->toContain('required');
    expect($rules['reference_number'])->toContain('string');
    expect($rules['reference_number'])->toContain('min:3');
    expect($rules['reference_number'])->toContain('max:255');
});

it('validates resolution notes are optional', function () {
    $rules = (new \App\Http\Requests\Admin\MarkPayoutCompleteRequest)->rules();

    expect($rules['resolution_notes'])->toContain('nullable');
    expect($rules['resolution_notes'])->toContain('max:2000');
});

// ==========================================
// Additional Excluded Attributes for Logging
// ==========================================

it('excludes flutterwave_response from activity logging', function () {
    $task = new PayoutTask;
    expect($task->getAdditionalExcludedAttributes())->toContain('flutterwave_response');
});
