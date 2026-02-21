<?php

/**
 * F-183: Client Complaint Submission — Unit Tests
 *
 * Tests the ComplaintSubmissionService business logic.
 */

use App\Models\Complaint;
use App\Models\Order;
use App\Services\ComplaintSubmissionService;
use Illuminate\Support\Facades\Notification;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
    Notification::fake();
    $this->service = new ComplaintSubmissionService;
});

// --- canSubmitComplaint() ---

test('can submit complaint on delivered order', function () {
    ['tenant' => $tenant, 'cook' => $cook] = test()->createTenantWithCook();
    $client = test()->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_DELIVERED,
    ]);

    $result = $this->service->canSubmitComplaint($order, $client);

    expect($result['can_complain'])->toBeTrue()
        ->and($result['reason'])->toBeNull();
});

test('can submit complaint on completed order', function () {
    ['tenant' => $tenant, 'cook' => $cook] = test()->createTenantWithCook();
    $client = test()->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
    ]);

    $result = $this->service->canSubmitComplaint($order, $client);

    expect($result['can_complain'])->toBeTrue();
});

test('can submit complaint on picked_up order', function () {
    ['tenant' => $tenant, 'cook' => $cook] = test()->createTenantWithCook();
    $client = test()->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_PICKED_UP,
    ]);

    $result = $this->service->canSubmitComplaint($order, $client);

    expect($result['can_complain'])->toBeTrue();
});

test('cannot submit complaint on preparing order (BR-183)', function () {
    ['tenant' => $tenant, 'cook' => $cook] = test()->createTenantWithCook();
    $client = test()->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_PREPARING,
    ]);

    $result = $this->service->canSubmitComplaint($order, $client);

    expect($result['can_complain'])->toBeFalse()
        ->and($result['reason'])->toBe('invalid_status');
});

test('cannot submit complaint on paid order (BR-183)', function () {
    ['tenant' => $tenant, 'cook' => $cook] = test()->createTenantWithCook();
    $client = test()->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_PAID,
    ]);

    $result = $this->service->canSubmitComplaint($order, $client);

    expect($result['can_complain'])->toBeFalse()
        ->and($result['reason'])->toBe('invalid_status');
});

test('cannot submit complaint on another clients order', function () {
    ['tenant' => $tenant, 'cook' => $cook] = test()->createTenantWithCook();
    $client = test()->createUserWithRole('client');
    $otherClient = test()->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $otherClient->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_DELIVERED,
    ]);

    $result = $this->service->canSubmitComplaint($order, $client);

    expect($result['can_complain'])->toBeFalse()
        ->and($result['reason'])->toBe('not_owner');
});

test('cannot submit duplicate complaint on same order (BR-184)', function () {
    ['tenant' => $tenant, 'cook' => $cook] = test()->createTenantWithCook();
    $client = test()->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
    ]);

    // Create existing complaint
    Complaint::factory()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $result = $this->service->canSubmitComplaint($order, $client);

    expect($result['can_complain'])->toBeFalse()
        ->and($result['reason'])->toBe('already_complained');
});

// --- submitComplaint() ---

test('submits complaint with status open (BR-189)', function () {
    ['tenant' => $tenant, 'cook' => $cook] = test()->createTenantWithCook();
    $client = test()->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_DELIVERED,
    ]);

    $complaint = $this->service->submitComplaint($order, $client, [
        'category' => 'food_quality',
        'description' => 'The rice was undercooked and cold when it arrived.',
    ]);

    expect($complaint)->toBeInstanceOf(Complaint::class)
        ->and($complaint->status)->toBe('open')
        ->and($complaint->category)->toBe('food_quality')
        ->and($complaint->description)->toBe('The rice was undercooked and cold when it arrived.')
        ->and($complaint->client_id)->toBe($client->id)
        ->and($complaint->cook_id)->toBe($cook->id)
        ->and($complaint->tenant_id)->toBe($tenant->id)
        ->and($complaint->order_id)->toBe($order->id)
        ->and($complaint->is_escalated)->toBeFalse()
        ->and($complaint->submitted_at)->not->toBeNull();
});

test('submits complaint without photo (BR-187)', function () {
    ['tenant' => $tenant, 'cook' => $cook] = test()->createTenantWithCook();
    $client = test()->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
    ]);

    $complaint = $this->service->submitComplaint($order, $client, [
        'category' => 'wrong_order',
        'description' => 'I ordered poulet DG but received grilled fish instead.',
    ]);

    expect($complaint->photo_path)->toBeNull();
});

test('creates activity log on complaint submission (BR-194)', function () {
    ['tenant' => $tenant, 'cook' => $cook] = test()->createTenantWithCook();
    $client = test()->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_DELIVERED,
    ]);

    $complaint = $this->service->submitComplaint($order, $client, [
        'category' => 'missing_item',
        'description' => 'Side dishes were missing from my order.',
    ]);

    $activity = \Spatie\Activitylog\Models\Activity::query()
        ->where('subject_type', Complaint::class)
        ->where('subject_id', $complaint->id)
        ->where('description', 'complaint_submitted')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($client->id)
        ->and($activity->properties['category'])->toBe('missing_item')
        ->and($activity->properties['order_id'])->toBe($order->id);
});

// --- hasExistingComplaint() ---

test('detects existing complaint on order', function () {
    ['tenant' => $tenant, 'cook' => $cook] = test()->createTenantWithCook();
    $client = test()->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
    ]);

    expect($this->service->hasExistingComplaint($order))->toBeFalse();

    Complaint::factory()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($this->service->hasExistingComplaint($order))->toBeTrue();
});

// --- Category constants ---

test('client categories match spec (BR-185)', function () {
    expect(ComplaintSubmissionService::CLIENT_CATEGORIES)->toBe([
        'food_quality',
        'delivery_issue',
        'missing_item',
        'wrong_order',
        'other',
    ]);
});

test('complainable statuses match spec (BR-183)', function () {
    expect(ComplaintSubmissionService::COMPLAINABLE_STATUSES)->toContain(Order::STATUS_DELIVERED)
        ->toContain(Order::STATUS_COMPLETED);
});

test('description length constraints match spec (BR-186)', function () {
    expect(ComplaintSubmissionService::MIN_DESCRIPTION_LENGTH)->toBe(10)
        ->and(ComplaintSubmissionService::MAX_DESCRIPTION_LENGTH)->toBe(1000);
});

// --- Complaint model ---

test('complaint model has client category labels', function () {
    $labels = Complaint::getClientCategoryLabels();

    expect($labels)->toHaveCount(5)
        ->toHaveKeys(['food_quality', 'delivery_issue', 'missing_item', 'wrong_order', 'other']);
});

test('complaint model categoryLabel returns correct label for new categories', function () {
    $complaint = new Complaint(['category' => 'delivery_issue']);
    expect($complaint->categoryLabel())->toBe('Delivery Issue');

    $complaint = new Complaint(['category' => 'missing_item']);
    expect($complaint->categoryLabel())->toBe('Missing Item');
});

test('photo_path is fillable on complaint model', function () {
    $complaint = new Complaint(['photo_path' => 'complaints/tenant-1/photo.jpg']);
    expect($complaint->photo_path)->toBe('complaints/tenant-1/photo.jpg');
});

// --- Order model ---

test('order has hasOne complaint relationship', function () {
    ['tenant' => $tenant, 'cook' => $cook] = test()->createTenantWithCook();
    $client = test()->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_COMPLETED,
    ]);

    expect($order->complaint)->toBeNull();

    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $order->refresh();
    expect($order->complaint)->not->toBeNull()
        ->and($order->complaint->id)->toBe($complaint->id);
});

// --- Factory states ---

test('complaint factory clientSubmitted state uses client categories', function () {
    ['tenant' => $tenant, 'cook' => $cook] = test()->createTenantWithCook();
    $client = test()->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->clientSubmitted()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect(Complaint::CLIENT_CATEGORIES)->toContain($complaint->category)
        ->and($complaint->status)->toBe('open');
});

test('complaint factory withPhoto state sets photo_path', function () {
    ['tenant' => $tenant, 'cook' => $cook] = test()->createTenantWithCook();
    $client = test()->createUserWithRole('client');
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->withPhoto()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    expect($complaint->photo_path)->not->toBeNull()
        ->and($complaint->photo_path)->toStartWith('complaints/');
});
