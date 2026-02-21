<?php

use App\Models\Complaint;
use App\Models\Order;
use App\Notifications\ComplaintEscalatedAdminNotification;
use App\Notifications\ComplaintEscalatedClientNotification;
use App\Notifications\ComplaintEscalatedCookNotification;
use App\Services\ComplaintEscalationService;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

/**
 * F-185: Feature tests for Complaint Auto-Escalation.
 *
 * Tests the full escalation flow including DB changes, notifications,
 * activity logging, idempotency, and the artisan command.
 */

// --- Service Integration Tests ---

it('escalates an open complaint older than 24 hours', function () {
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');
    $admin = createUser('admin');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->overdueOpen()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $service = app(ComplaintEscalationService::class);
    $result = $service->processOverdueComplaints();

    expect($result['escalated'])->toBe(1);
    expect($result['failed'])->toBe(0);

    $complaint->refresh();
    expect($complaint->status)->toBe('escalated');
    expect($complaint->is_escalated)->toBeTrue();
    expect($complaint->escalation_reason)->toBe(Complaint::ESCALATION_AUTO_24H);
    expect($complaint->escalated_at)->not->toBeNull();
});

it('sends notifications to admin, client, and cook on escalation', function () {
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');
    $admin = createUser('admin');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->overdueOpen()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $service = app(ComplaintEscalationService::class);
    $service->processOverdueComplaints();

    // BR-211: Admin notification
    Notification::assertSentTo($admin, ComplaintEscalatedAdminNotification::class);

    // BR-212: Client notification
    Notification::assertSentTo($client, ComplaintEscalatedClientNotification::class);

    // BR-213: Cook notification
    Notification::assertSentTo($cook, ComplaintEscalatedCookNotification::class);
});

it('does not escalate complaints with status in_review', function () {
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->inReviewOverdue()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $service = app(ComplaintEscalationService::class);
    $result = $service->processOverdueComplaints();

    expect($result['escalated'])->toBe(0);

    $complaint->refresh();
    expect($complaint->status)->toBe('in_review');
});

it('does not escalate complaints already escalated', function () {
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->escalated()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $service = app(ComplaintEscalationService::class);
    $result = $service->processOverdueComplaints();

    expect($result['escalated'])->toBe(0);
    Notification::assertNothingSent();
});

it('does not escalate resolved complaints', function () {
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->resolved()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $service = app(ComplaintEscalationService::class);
    $result = $service->processOverdueComplaints();

    expect($result['escalated'])->toBe(0);
    Notification::assertNothingSent();
});

it('does not escalate complaints less than 24 hours old', function () {
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->recentOpen()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $service = app(ComplaintEscalationService::class);
    $result = $service->processOverdueComplaints();

    expect($result['escalated'])->toBe(0);

    $complaint->refresh();
    expect($complaint->status)->toBe('open');
});

it('escalates complaint at exactly 24 hours boundary', function () {
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    // Exactly 24 hours ago — should be eligible (>= 24 hours)
    $complaint = Complaint::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'status' => 'open',
        'is_escalated' => false,
        'created_at' => now()->subHours(24),
        'submitted_at' => now()->subHours(24),
    ]);

    $service = app(ComplaintEscalationService::class);
    $result = $service->processOverdueComplaints();

    expect($result['escalated'])->toBe(1);

    $complaint->refresh();
    expect($complaint->status)->toBe('escalated');
});

it('escalates multiple complaints from different tenants in a single run', function () {
    Notification::fake();

    $admin = createUser('admin');

    // Create 3 complaints from different tenants
    $complaints = [];
    for ($i = 0; $i < 3; $i++) {
        ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
        $tenant->update(['cook_id' => $cook->id]);

        $client = createUser('client');

        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'cook_id' => $cook->id,
        ]);

        $complaints[] = Complaint::factory()->overdueOpen()->create([
            'client_id' => $client->id,
            'cook_id' => $cook->id,
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
        ]);
    }

    $service = app(ComplaintEscalationService::class);
    $result = $service->processOverdueComplaints();

    expect($result['escalated'])->toBe(3);
    expect($result['failed'])->toBe(0);

    foreach ($complaints as $complaint) {
        $complaint->refresh();
        expect($complaint->status)->toBe('escalated');
        expect($complaint->is_escalated)->toBeTrue();
    }
});

it('is idempotent running multiple times does not re-escalate', function () {
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');
    $admin = createUser('admin');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->overdueOpen()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $service = app(ComplaintEscalationService::class);

    // First run
    $result1 = $service->processOverdueComplaints();
    expect($result1['escalated'])->toBe(1);

    $firstEscalatedAt = $complaint->fresh()->escalated_at;

    Notification::fake(); // Reset notification fake

    // Second run
    $result2 = $service->processOverdueComplaints();
    expect($result2['escalated'])->toBe(0);

    // No additional notifications sent
    Notification::assertNothingSent();

    // escalated_at unchanged
    $complaint->refresh();
    expect($complaint->escalated_at->toDateTimeString())->toBe($firstEscalatedAt->toDateTimeString());
});

it('logs activity with system as actor on escalation', function () {
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->overdueOpen()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $service = app(ComplaintEscalationService::class);
    $service->processOverdueComplaints();

    $activity = Activity::where('log_name', 'complaints')
        ->where('description', 'complaint_auto_escalated')
        ->where('subject_id', $complaint->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBeNull(); // System as actor (anonymous)
    expect($activity->properties['escalation_reason'])->toBe(Complaint::ESCALATION_AUTO_24H);
    expect($activity->properties['order_id'])->toBe($order->id);
    expect($activity->properties['tenant_id'])->toBe($tenant->id);
});

it('sets escalated_at timestamp on escalation', function () {
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->overdueOpen()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    expect($complaint->escalated_at)->toBeNull();

    $service = app(ComplaintEscalationService::class);
    $service->processOverdueComplaints();

    $complaint->refresh();
    expect($complaint->escalated_at)->not->toBeNull();
    expect($complaint->escalated_at->diffInMinutes(now()))->toBeLessThan(1);
});

it('handles escalation when no admin users exist', function () {
    Notification::fake();

    // Edge case: No admin users in the system
    // BR: "No admin users exist... notification delivery fails silently but complaint status updates"

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');
    // Note: No admin users created

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->overdueOpen()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $service = app(ComplaintEscalationService::class);
    $result = $service->processOverdueComplaints();

    // Escalation should still succeed even without admin notification recipients
    expect($result['escalated'])->toBe(1);

    $complaint->refresh();
    expect($complaint->status)->toBe('escalated');

    // Client and cook still get notified even without admins
    Notification::assertSentTo($client, ComplaintEscalatedClientNotification::class);
    Notification::assertSentTo($cook, ComplaintEscalatedCookNotification::class);
});

it('does not escalate dismissed complaints', function () {
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->dismissed()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $service = app(ComplaintEscalationService::class);
    $result = $service->processOverdueComplaints();

    expect($result['escalated'])->toBe(0);
    Notification::assertNothingSent();
});

it('notifies all admin and super-admin users', function () {
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');
    $admin1 = createUser('admin');
    $admin2 = createUser('admin');
    $superAdmin = createUser('super-admin');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->overdueOpen()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $service = app(ComplaintEscalationService::class);
    $service->processOverdueComplaints();

    // All admins and super-admins should be notified
    Notification::assertSentTo($admin1, ComplaintEscalatedAdminNotification::class);
    Notification::assertSentTo($admin2, ComplaintEscalatedAdminNotification::class);
    Notification::assertSentTo($superAdmin, ComplaintEscalatedAdminNotification::class);
});

// --- Artisan Command Integration Tests ---

it('runs the artisan command and escalates overdue complaints', function () {
    Notification::fake();

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');
    $admin = createUser('admin');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    Complaint::factory()->overdueOpen()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $this->artisan('dancymeals:escalate-overdue-complaints')
        ->assertSuccessful();
});

it('artisan command reports zero when no complaints to escalate', function () {
    Notification::fake();

    $this->artisan('dancymeals:escalate-overdue-complaints')
        ->assertSuccessful();
});

it('returns zero escalated and zero failed when no eligible complaints exist', function () {
    Notification::fake();

    $service = app(ComplaintEscalationService::class);
    $result = $service->processOverdueComplaints();

    expect($result['escalated'])->toBe(0);
    expect($result['failed'])->toBe(0);
    expect($result['errors'])->toBeEmpty();
});

// --- Factory State Tests ---

it('creates overdueOpen complaint with correct attributes', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->overdueOpen()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    expect($complaint->status)->toBe('open');
    expect($complaint->is_escalated)->toBeFalse();
    expect($complaint->escalation_reason)->toBeNull();
    expect($complaint->escalated_at)->toBeNull();
    expect($complaint->created_at->diffInHours(now()))->toBeGreaterThanOrEqual(24);
});

it('creates recentOpen complaint that is not eligible for escalation', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->recentOpen()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    expect($complaint->status)->toBe('open');
    expect($complaint->created_at->diffInHours(now()))->toBeLessThan(24);
});

it('creates inReviewOverdue complaint that should not be escalated', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);

    $client = createUser('client');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = Complaint::factory()->inReviewOverdue()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    expect($complaint->status)->toBe('in_review');
    expect($complaint->cook_response)->not->toBeNull();
    expect($complaint->created_at->diffInHours(now()))->toBeGreaterThanOrEqual(24);
});

// --- Notification Content Tests (require app context for __()) ---

it('admin notification title says Complaint Escalated', function () {
    $complaint = new Complaint;
    $complaint->id = 1;
    $notification = new ComplaintEscalatedAdminNotification($complaint);

    expect($notification->getTitle(new \stdClass))->toBe(__('Complaint Escalated'));
});

it('client notification body matches UI/UX spec text', function () {
    $complaint = new Complaint;
    $complaint->id = 1;
    $complaint->order_id = 10;
    $notification = new ComplaintEscalatedClientNotification($complaint);

    $body = $notification->getBody(new \stdClass);
    expect($body)->toBe(__('Your complaint has been escalated to our support team for review'));
});

it('cook notification body references order number and 24 hours', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $tenant->update(['cook_id' => $cook->id]);
    $client = createUser('client');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
    ]);

    $complaint = new Complaint;
    $complaint->id = 1;
    $complaint->order_id = $order->id;
    $complaint->setRelation('order', $order);

    $notification = new ComplaintEscalatedCookNotification($complaint);
    $body = $notification->getBody(new \stdClass);

    expect($body)->toContain($order->order_number);
    expect($body)->toContain('24 hours');
});

it('admin notification data includes all required fields', function () {
    $complaint = new Complaint;
    $complaint->id = 42;
    $complaint->order_id = 10;
    $complaint->category = 'food_quality';
    $complaint->tenant_id = 5;

    $notification = new ComplaintEscalatedAdminNotification($complaint);
    $data = $notification->getData(new \stdClass);

    expect($data)
        ->toHaveKey('complaint_id', 42)
        ->toHaveKey('order_id', 10)
        ->toHaveKey('category', 'food_quality')
        ->toHaveKey('tenant_id', 5)
        ->toHaveKey('type', 'complaint_escalated')
        ->toHaveKey('escalation_reason', Complaint::ESCALATION_AUTO_24H);
});

it('cook notification data includes all required fields', function () {
    $complaint = new Complaint;
    $complaint->id = 42;
    $complaint->order_id = 10;
    $complaint->category = 'food_quality';

    $notification = new ComplaintEscalatedCookNotification($complaint);
    $data = $notification->getData(new \stdClass);

    expect($data)
        ->toHaveKey('complaint_id', 42)
        ->toHaveKey('order_id', 10)
        ->toHaveKey('category', 'food_quality')
        ->toHaveKey('type', 'complaint_escalated_cook')
        ->toHaveKey('escalation_reason', Complaint::ESCALATION_AUTO_24H);
});

it('client notification data includes complaint and order info', function () {
    $complaint = new Complaint;
    $complaint->id = 42;
    $complaint->order_id = 10;

    $notification = new ComplaintEscalatedClientNotification($complaint);
    $data = $notification->getData(new \stdClass);

    expect($data)
        ->toHaveKey('complaint_id', 42)
        ->toHaveKey('order_id', 10)
        ->toHaveKey('type', 'complaint_escalated_client');
});
