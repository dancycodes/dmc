<?php

use App\Models\Complaint;
use App\Models\ComplaintResponse;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ComplaintResponseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
    Notification::fake();
});

// ========== Model Tests ==========

it('creates a complaint response with correct attributes', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $user->id]);
    $complaint = Complaint::factory()->clientSubmitted()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $user->id,
    ]);

    $response = ComplaintResponse::factory()->apologyOnly()->create([
        'complaint_id' => $complaint->id,
        'user_id' => $user->id,
        'message' => 'We sincerely apologize for the inconvenience.',
    ]);

    expect($response)->toBeInstanceOf(ComplaintResponse::class)
        ->and($response->complaint_id)->toBe($complaint->id)
        ->and($response->user_id)->toBe($user->id)
        ->and($response->resolution_type)->toBe(ComplaintResponse::RESOLUTION_APOLOGY_ONLY)
        ->and($response->refund_amount)->toBeNull();
});

it('has correct resolution type constants', function () {
    expect(ComplaintResponse::RESOLUTION_TYPES)->toBe([
        'apology_only',
        'partial_refund_offer',
        'full_refund_offer',
    ]);
});

it('has correct message length constants', function () {
    expect(ComplaintResponse::MIN_MESSAGE_LENGTH)->toBe(10)
        ->and(ComplaintResponse::MAX_MESSAGE_LENGTH)->toBe(2000);
});

it('belongs to a complaint', function () {
    $response = ComplaintResponse::factory()->create();

    expect($response->complaint)->toBeInstanceOf(Complaint::class);
});

it('belongs to a user', function () {
    $response = ComplaintResponse::factory()->create();

    expect($response->user)->toBeInstanceOf(User::class);
});

it('returns correct resolution type label for apology only', function () {
    $response = ComplaintResponse::factory()->apologyOnly()->create();

    expect($response->resolutionTypeLabel())->toBe(__('Apology Only'));
});

it('returns correct resolution type label for partial refund', function () {
    $response = ComplaintResponse::factory()->partialRefund(2000)->create();

    expect($response->resolutionTypeLabel())->toBe(__('Partial Refund Offer'));
});

it('returns correct resolution type label for full refund', function () {
    $response = ComplaintResponse::factory()->fullRefund(5000)->create();

    expect($response->resolutionTypeLabel())->toBe(__('Full Refund Offer'));
});

// ========== Complaint Model Updates ==========

it('has in_review in ALL_STATUSES', function () {
    expect(Complaint::ALL_STATUSES)->toContain('in_review');
});

it('has cook-facing statuses', function () {
    expect(Complaint::COOK_STATUSES)->toBe([
        'open',
        'in_review',
        'escalated',
        'resolved',
        'dismissed',
    ]);
});

it('complaint has many responses', function () {
    $complaint = Complaint::factory()->clientSubmitted()->create();
    ComplaintResponse::factory()->count(3)->create([
        'complaint_id' => $complaint->id,
    ]);

    expect($complaint->responses)->toHaveCount(3);
});

it('returns in_review status label', function () {
    $complaint = Complaint::factory()->clientSubmitted()->create(['status' => 'in_review']);

    expect($complaint->statusLabel())->toBe(__('In Review'));
});

// ========== Service Tests ==========

it('gets complaints for tenant', function () {
    $tenant = Tenant::factory()->create();
    $service = app(ComplaintResponseService::class);

    Complaint::factory()->clientSubmitted()->count(3)->create(['tenant_id' => $tenant->id]);
    Complaint::factory()->clientSubmitted()->count(2)->create(); // different tenant

    $result = $service->getComplaintsForTenant($tenant->id);

    expect($result)->toHaveCount(3);
});

it('filters complaints by status', function () {
    $tenant = Tenant::factory()->create();
    $service = app(ComplaintResponseService::class);

    Complaint::factory()->clientSubmitted()->count(2)->create([
        'tenant_id' => $tenant->id,
        'status' => 'open',
    ]);
    Complaint::factory()->clientSubmitted()->create([
        'tenant_id' => $tenant->id,
        'status' => 'in_review',
    ]);

    $result = $service->getComplaintsForTenant($tenant->id, ['status' => 'open']);

    expect($result)->toHaveCount(2);
});

it('gets complaint summary', function () {
    $tenant = Tenant::factory()->create();
    $service = app(ComplaintResponseService::class);

    Complaint::factory()->clientSubmitted()->count(2)->create([
        'tenant_id' => $tenant->id,
        'status' => 'open',
    ]);
    Complaint::factory()->clientSubmitted()->create([
        'tenant_id' => $tenant->id,
        'status' => 'in_review',
    ]);
    Complaint::factory()->clientSubmitted()->create([
        'tenant_id' => $tenant->id,
        'status' => 'escalated',
        'is_escalated' => true,
    ]);

    $summary = $service->getComplaintSummary($tenant->id);

    expect($summary['total'])->toBe(4)
        ->and($summary['open'])->toBe(2)
        ->and($summary['in_review'])->toBe(1)
        ->and($summary['escalated'])->toBe(1)
        ->and($summary['resolved'])->toBe(0);
});

it('submits response and changes open complaint to in_review', function () {
    $cook = User::factory()->create();
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'grand_total' => 5000,
    ]);
    $complaint = Complaint::factory()->clientSubmitted()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'order_id' => $order->id,
        'status' => 'open',
    ]);

    $service = app(ComplaintResponseService::class);
    $response = $service->submitResponse($complaint, $cook, [
        'message' => 'We sincerely apologize for the inconvenience.',
        'resolution_type' => ComplaintResponse::RESOLUTION_APOLOGY_ONLY,
    ]);

    $complaint->refresh();

    expect($response)->toBeInstanceOf(ComplaintResponse::class)
        ->and($response->message)->toBe('We sincerely apologize for the inconvenience.')
        ->and($response->resolution_type)->toBe(ComplaintResponse::RESOLUTION_APOLOGY_ONLY)
        ->and($response->refund_amount)->toBeNull()
        ->and($complaint->status)->toBe('in_review')
        ->and($complaint->cook_response)->toBe('We sincerely apologize for the inconvenience.')
        ->and($complaint->cook_responded_at)->not->toBeNull();
});

it('submits partial refund response with amount', function () {
    $cook = User::factory()->create();
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'grand_total' => 5000,
    ]);
    $complaint = Complaint::factory()->clientSubmitted()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'order_id' => $order->id,
        'status' => 'open',
    ]);

    $service = app(ComplaintResponseService::class);
    $response = $service->submitResponse($complaint, $cook, [
        'message' => 'We are sorry about the missing item. Here is a partial refund.',
        'resolution_type' => ComplaintResponse::RESOLUTION_PARTIAL_REFUND,
        'refund_amount' => 2000,
    ]);

    expect($response->resolution_type)->toBe(ComplaintResponse::RESOLUTION_PARTIAL_REFUND)
        ->and($response->refund_amount)->toBe(2000);
});

it('submits full refund response', function () {
    $cook = User::factory()->create();
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'grand_total' => 5000,
    ]);
    $complaint = Complaint::factory()->clientSubmitted()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'order_id' => $order->id,
        'status' => 'open',
    ]);

    $service = app(ComplaintResponseService::class);
    $response = $service->submitResponse($complaint, $cook, [
        'message' => 'We are terribly sorry. Here is a full refund for your order.',
        'resolution_type' => ComplaintResponse::RESOLUTION_FULL_REFUND,
        'refund_amount' => 5000,
    ]);

    expect($response->resolution_type)->toBe(ComplaintResponse::RESOLUTION_FULL_REFUND)
        ->and($response->refund_amount)->toBe(5000);
});

it('does not change status on subsequent responses (BR-203)', function () {
    $cook = User::factory()->create();
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'grand_total' => 5000,
    ]);
    $complaint = Complaint::factory()->clientSubmitted()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'order_id' => $order->id,
        'status' => 'in_review',
        'cook_response' => 'First response.',
        'cook_responded_at' => now()->subHours(1),
    ]);

    $service = app(ComplaintResponseService::class);
    $response = $service->submitResponse($complaint, $cook, [
        'message' => 'Additional context: we have investigated further.',
        'resolution_type' => ComplaintResponse::RESOLUTION_APOLOGY_ONLY,
    ]);

    $complaint->refresh();

    expect($complaint->status)->toBe('in_review')
        ->and($complaint->cook_response)->toBe('First response.');
});

it('allows response on escalated complaint', function () {
    $cook = User::factory()->create();
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'grand_total' => 5000,
    ]);
    $complaint = Complaint::factory()->clientSubmitted()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'order_id' => $order->id,
        'status' => 'escalated',
        'is_escalated' => true,
    ]);

    $service = app(ComplaintResponseService::class);
    $response = $service->submitResponse($complaint, $cook, [
        'message' => 'We apologize for the delay in responding.',
        'resolution_type' => ComplaintResponse::RESOLUTION_APOLOGY_ONLY,
    ]);

    $complaint->refresh();

    // Status stays escalated — admin handles resolution
    expect($response)->toBeInstanceOf(ComplaintResponse::class)
        ->and($complaint->status)->toBe('escalated');
});

it('notifies client when cook responds', function () {
    $cook = User::factory()->create();
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'grand_total' => 5000,
    ]);
    $complaint = Complaint::factory()->clientSubmitted()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'order_id' => $order->id,
        'status' => 'open',
    ]);

    $service = app(ComplaintResponseService::class);
    $service->submitResponse($complaint, $cook, [
        'message' => 'We are sorry and will improve.',
        'resolution_type' => ComplaintResponse::RESOLUTION_APOLOGY_ONLY,
    ]);

    Notification::assertSentTo(
        $client,
        \App\Notifications\ComplaintResponseNotification::class
    );
});

// ========== Authorization Tests ==========

it('allows cook to respond to their tenant complaint', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $complaint = Complaint::factory()->clientSubmitted()->create([
        'tenant_id' => $tenant->id,
    ]);

    $service = app(ComplaintResponseService::class);

    expect($service->canRespond($complaint, $cook))->toBeTrue();
});

it('allows manager with can-manage-orders to respond', function () {
    $manager = test()->createUserWithRole('manager');
    $manager->givePermissionTo('can-manage-orders');
    $tenant = Tenant::factory()->create();
    $complaint = Complaint::factory()->clientSubmitted()->create([
        'tenant_id' => $tenant->id,
    ]);

    $service = app(ComplaintResponseService::class);

    expect($service->canRespond($complaint, $manager))->toBeTrue();
});

it('denies response from unauthorized user', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $complaint = Complaint::factory()->clientSubmitted()->create([
        'tenant_id' => $tenant->id,
    ]);

    $service = app(ComplaintResponseService::class);

    expect($service->canRespond($complaint, $user))->toBeFalse();
});

// ========== Helper Method Tests ==========

it('parses order items snapshot', function () {
    $service = app(ComplaintResponseService::class);

    $snapshot = [
        ['meal_name' => 'Ndole', 'quantity' => 2, 'unit_price' => 2500],
        ['meal_name' => 'Plantains', 'quantity' => 1, 'unit_price' => 500],
    ];

    $items = $service->parseOrderItems($snapshot);

    expect($items)->toHaveCount(2)
        ->and($items[0]['name'])->toBe('Ndole')
        ->and($items[0]['quantity'])->toBe(2)
        ->and($items[0]['price'])->toBe(2500);
});

it('handles string items snapshot', function () {
    $service = app(ComplaintResponseService::class);

    $snapshot = json_encode([
        ['meal_name' => 'Jollof Rice', 'quantity' => 1, 'unit_price' => 3000],
    ]);

    $items = $service->parseOrderItems($snapshot);

    expect($items)->toHaveCount(1)
        ->and($items[0]['name'])->toBe('Jollof Rice');
});

it('handles null items snapshot', function () {
    $service = app(ComplaintResponseService::class);

    $items = $service->parseOrderItems(null);

    expect($items)->toBeEmpty();
});

it('formats XAF amounts correctly', function () {
    expect(ComplaintResponseService::formatXAF(5000))->toBe('5,000 XAF')
        ->and(ComplaintResponseService::formatXAF(0))->toBe('0 XAF')
        ->and(ComplaintResponseService::formatXAF(12500))->toBe('12,500 XAF');
});

// ========== Notification Tests ==========

it('complaint response notification has correct title', function () {
    $complaint = Complaint::factory()->clientSubmitted()->create();
    $complaint->load('order');
    $response = ComplaintResponse::factory()->create(['complaint_id' => $complaint->id]);

    $notification = new \App\Notifications\ComplaintResponseNotification($complaint, $response);

    expect($notification->getTitle($complaint->client))->toBe(__('Response to Your Complaint'));
});

it('complaint response notification has correct action url', function () {
    $complaint = Complaint::factory()->clientSubmitted()->create();
    $complaint->load('order');
    $response = ComplaintResponse::factory()->create(['complaint_id' => $complaint->id]);

    $notification = new \App\Notifications\ComplaintResponseNotification($complaint, $response);

    expect($notification->getActionUrl($complaint->client))
        ->toBe('/my-orders/'.$complaint->order_id.'/complaint/'.$complaint->id);
});

it('complaint response notification has correct data payload', function () {
    $complaint = Complaint::factory()->clientSubmitted()->create();
    $complaint->load('order');
    $response = ComplaintResponse::factory()->partialRefund(2000)->create([
        'complaint_id' => $complaint->id,
    ]);

    $notification = new \App\Notifications\ComplaintResponseNotification($complaint, $response);
    $data = $notification->getData($complaint->client);

    expect($data['type'])->toBe('complaint_response')
        ->and($data['complaint_id'])->toBe($complaint->id)
        ->and($data['response_id'])->toBe($response->id)
        ->and($data['resolution_type'])->toBe('partial_refund_offer');
});
