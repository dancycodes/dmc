<?php

use App\Mail\ComplaintResolvedMail;
use App\Models\Complaint;
use App\Models\ComplaintResponse;
use App\Models\Order;
use App\Models\Tenant;
use App\Notifications\ComplaintEscalatedAdminNotification;
use App\Notifications\ComplaintEscalatedClientNotification;
use App\Notifications\ComplaintEscalatedCookNotification;
use App\Notifications\ComplaintResolvedNotification;
use App\Notifications\ComplaintResponseNotification;
use App\Notifications\ComplaintSubmittedNotification;
use App\Services\ComplaintNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
});

// =============================================
// ComplaintSubmittedNotification Tests
// =============================================

test('ComplaintSubmittedNotification uses push and database channels', function () {
    $order = Order::factory()->create();
    $complaint = Complaint::factory()->create(['order_id' => $order->id]);

    $notification = new ComplaintSubmittedNotification($complaint, $order);
    $channels = $notification->via(new stdClass);

    expect($channels)->toContain(WebPushChannel::class)
        ->toContain('database');
});

test('ComplaintSubmittedNotification title is localized', function () {
    $order = Order::factory()->create();
    $complaint = Complaint::factory()->create(['order_id' => $order->id]);

    $notification = new ComplaintSubmittedNotification($complaint, $order);
    $title = $notification->getTitle(new stdClass);

    expect($title)->toBe('New Complaint on Order');
});

test('ComplaintSubmittedNotification body contains order number and category', function () {
    $order = Order::factory()->create(['order_number' => 'DMC-260221-0001']);
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'category' => 'food_quality',
    ]);

    $notification = new ComplaintSubmittedNotification($complaint, $order);
    $body = $notification->getBody(new stdClass);

    expect($body)
        ->toContain('DMC-260221-0001')
        ->toContain('Food Quality');
});

test('ComplaintSubmittedNotification action URL links to cook dashboard order', function () {
    $order = Order::factory()->create(['id' => 42]);
    $complaint = Complaint::factory()->create(['order_id' => $order->id]);

    $notification = new ComplaintSubmittedNotification($complaint, $order);
    $url = $notification->getActionUrl(new stdClass);

    expect($url)->toContain('/dashboard/orders/42');
});

test('ComplaintSubmittedNotification getData includes type and complaint id', function () {
    $order = Order::factory()->create();
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'category' => 'wrong_order',
    ]);

    $notification = new ComplaintSubmittedNotification($complaint, $order);
    $data = $notification->getData(new stdClass);

    expect($data)
        ->toHaveKey('complaint_id', $complaint->id)
        ->toHaveKey('order_id', $order->id)
        ->toHaveKey('type', 'complaint_submitted');
});

// =============================================
// ComplaintResponseNotification Tests
// =============================================

test('ComplaintResponseNotification uses push and database channels', function () {
    $complaint = Complaint::factory()->create();
    $response = ComplaintResponse::factory()->create(['complaint_id' => $complaint->id]);

    $notification = new ComplaintResponseNotification($complaint, $response);
    $channels = $notification->via(new stdClass);

    expect($channels)->toContain(WebPushChannel::class)
        ->toContain('database');
});

test('ComplaintResponseNotification action URL links to client complaint page', function () {
    $order = Order::factory()->create();
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'id' => 7,
    ]);
    $response = ComplaintResponse::factory()->create(['complaint_id' => $complaint->id]);

    $notification = new ComplaintResponseNotification($complaint, $response);
    $url = $notification->getActionUrl(new stdClass);

    expect($url)->toContain('/my-orders/')
        ->toContain('/complaint/7');
});

test('ComplaintResponseNotification getData has type complaint_response', function () {
    $complaint = Complaint::factory()->create();
    $response = ComplaintResponse::factory()->create([
        'complaint_id' => $complaint->id,
        'resolution_type' => 'partial_refund',
    ]);

    $notification = new ComplaintResponseNotification($complaint, $response);
    $data = $notification->getData(new stdClass);

    expect($data)
        ->toHaveKey('type', 'complaint_response')
        ->toHaveKey('resolution_type', 'partial_refund')
        ->toHaveKey('complaint_id', $complaint->id);
});

// =============================================
// ComplaintResolvedNotification Tests
// =============================================

test('ComplaintResolvedNotification uses push and database channels', function () {
    $complaint = Complaint::factory()->create();

    $notification = new ComplaintResolvedNotification($complaint);
    $channels = $notification->via(new stdClass);

    expect($channels)->toContain(WebPushChannel::class)
        ->toContain('database');
});

test('ComplaintResolvedNotification title is Complaint Resolved', function () {
    $complaint = Complaint::factory()->create();

    $notification = new ComplaintResolvedNotification($complaint);
    $title = $notification->getTitle(new stdClass);

    expect($title)->toBe('Complaint Resolved');
});

test('ComplaintResolvedNotification body mentions full refund for full_refund type', function () {
    $order = Order::factory()->create(['order_number' => 'DMC-260221-9999']);
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'resolution_type' => 'full_refund',
    ]);

    $notification = new ComplaintResolvedNotification($complaint);
    $body = $notification->getBody(new stdClass);

    expect($body)->toContain('full refund');
});

test('ComplaintResolvedNotification body mentions partial refund for partial_refund type', function () {
    $order = Order::factory()->create(['order_number' => 'DMC-260221-9998']);
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'resolution_type' => 'partial_refund',
    ]);

    $notification = new ComplaintResolvedNotification($complaint);
    $body = $notification->getBody(new stdClass);

    expect($body)->toContain('partial refund');
});

test('ComplaintResolvedNotification body for dismiss says complaint reviewed', function () {
    $order = Order::factory()->create(['order_number' => 'DMC-260221-0002']);
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'resolution_type' => 'dismiss',
    ]);

    $notification = new ComplaintResolvedNotification($complaint);
    $body = $notification->getBody(new stdClass);

    expect($body)->toContain('reviewed');
});

test('ComplaintResolvedNotification action URL links to client complaint page', function () {
    $order = Order::factory()->create(['id' => 55]);
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'id' => 12,
    ]);

    $notification = new ComplaintResolvedNotification($complaint);
    $url = $notification->getActionUrl(new stdClass);

    expect($url)->toContain('/my-orders/55/complaint/12');
});

test('ComplaintResolvedNotification getData includes resolution_type and refund_amount', function () {
    $order = Order::factory()->create();
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'resolution_type' => 'partial_refund',
        'refund_amount' => '5000.00',
    ]);

    $notification = new ComplaintResolvedNotification($complaint);
    $data = $notification->getData(new stdClass);

    expect($data)
        ->toHaveKey('type', 'complaint_resolved')
        ->toHaveKey('resolution_type', 'partial_refund')
        ->toHaveKey('complaint_id', $complaint->id);
});

test('ComplaintResolvedNotification tag uses complaint ID', function () {
    $complaint = Complaint::factory()->create(['id' => 99]);

    $notification = new ComplaintResolvedNotification($complaint);
    $tag = $notification->getTag(new stdClass);

    expect($tag)->toBe('complaint-resolved-99');
});

// =============================================
// ComplaintResolvedMail Tests
// =============================================

test('ComplaintResolvedMail has correct subject line', function () {
    $order = Order::factory()->create(['order_number' => 'DMC-260221-0001']);
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'resolution_type' => 'dismiss',
    ]);

    $mail = new ComplaintResolvedMail($complaint);
    $envelope = $mail->envelope();

    expect($envelope->subject)
        ->toContain('Complaint Resolved')
        ->toContain('DMC-260221-0001');
});

test('ComplaintResolvedMail uses correct email view', function () {
    $complaint = Complaint::factory()->create();
    $mail = new ComplaintResolvedMail($complaint);
    $content = $mail->content();

    expect($content->view)->toBe('emails.complaint-resolved');
});

test('ComplaintResolvedMail includes all required BR-294 data', function () {
    $order = Order::factory()->create(['order_number' => 'DMC-260221-0001']);
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'category' => 'food_quality',
        'resolution_type' => 'partial_refund',
        'refund_amount' => '3500.00',
        'resolution_notes' => 'Partial refund granted for food quality issue.',
    ]);

    $mail = new ComplaintResolvedMail($complaint);
    $content = $mail->content();
    $data = $content->with;

    expect($data)
        ->toHaveKey('complaint')
        ->toHaveKey('orderNumber', 'DMC-260221-0001')
        ->toHaveKey('category', 'food_quality')
        ->toHaveKey('resolutionType', 'partial_refund')
        ->toHaveKey('resolutionNotes', 'Partial refund granted for food quality issue.')
        ->toHaveKey('refundAmount')
        ->toHaveKey('viewComplaintUrl')
        ->toHaveKey('emailLocale');

    expect($data['refundAmount'])->toContain('3,500')->toContain('XAF');
});

test('ComplaintResolvedMail refund amount is null when no refund', function () {
    $complaint = Complaint::factory()->create([
        'resolution_type' => 'dismiss',
        'refund_amount' => null,
    ]);

    $mail = new ComplaintResolvedMail($complaint);
    $content = $mail->content();
    $data = $content->with;

    expect($data['refundAmount'])->toBeNull();
});

test('ComplaintResolvedMail is queued', function () {
    $complaint = Complaint::factory()->create();
    $mail = new ComplaintResolvedMail($complaint);

    expect($mail)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

test('ComplaintResolvedMail extends BaseMailableNotification', function () {
    $complaint = Complaint::factory()->create();
    $mail = new ComplaintResolvedMail($complaint);

    expect($mail)->toBeInstanceOf(\App\Mail\BaseMailableNotification::class);
});

// =============================================
// ComplaintNotificationService Tests
// =============================================

test('ComplaintNotificationService resolves cook as recipient', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $complaint = Complaint::factory()->create([
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new ComplaintNotificationService;
    $recipients = $service->resolveCookAndManagers($complaint);

    expect($recipients)->toHaveCount(1);
    expect($recipients[0]->id)->toBe($cook->id);
});

test('ComplaintNotificationService resolves cook and managers with manage-complaints-escalated permission', function () {
    $cook = test()->createUserWithRole('cook');
    $manager = test()->createUserWithRole('manager');
    $manager->givePermissionTo('can-manage-complaints-escalated');

    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $complaint = Complaint::factory()->create([
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new ComplaintNotificationService;
    $recipients = $service->resolveCookAndManagers($complaint);

    $recipientIds = array_map(fn ($r) => $r->id, $recipients);

    expect($recipientIds)->toContain($cook->id)
        ->toContain($manager->id);
});

test('ComplaintNotificationService does not duplicate cook when cook has manage-complaints-escalated permission', function () {
    $cook = test()->createUserWithRole('cook');
    $cook->givePermissionTo('can-manage-complaints-escalated');

    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $complaint = Complaint::factory()->create([
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new ComplaintNotificationService;
    $recipients = $service->resolveCookAndManagers($complaint);

    $cookCount = count(array_filter($recipients, fn ($r) => $r->id === $cook->id));
    expect($cookCount)->toBe(1);
});

test('notifyComplaintSubmitted sends push and DB to cook and managers', function () {
    Notification::fake();

    $cook = test()->createUserWithRole('cook');
    $manager = test()->createUserWithRole('manager');
    $manager->givePermissionTo('can-manage-complaints-escalated');

    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);
    $complaint = Complaint::factory()->create([
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $service = new ComplaintNotificationService;
    $service->notifyComplaintSubmitted($complaint, $order);

    Notification::assertSentTo($cook, ComplaintSubmittedNotification::class);
    Notification::assertSentTo($manager, ComplaintSubmittedNotification::class);
});

test('notifyComplaintResponse sends push and DB to client', function () {
    Notification::fake();

    $client = test()->createUserWithRole('client');
    $order = Order::factory()->create(['client_id' => $client->id]);
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
    ]);
    $response = ComplaintResponse::factory()->create(['complaint_id' => $complaint->id]);

    $service = new ComplaintNotificationService;
    $service->notifyComplaintResponse($complaint, $response);

    Notification::assertSentTo($client, ComplaintResponseNotification::class);
});

test('notifyComplaintEscalated sends to admin, client, and cook', function () {
    Notification::fake();

    $admin = test()->createUserWithRole('admin');
    $client = test()->createUserWithRole('client');
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);
    $complaint = Complaint::factory()->create([
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $service = new ComplaintNotificationService;
    $service->notifyComplaintEscalated($complaint);

    Notification::assertSentTo($admin, ComplaintEscalatedAdminNotification::class);
    Notification::assertSentTo($client, ComplaintEscalatedClientNotification::class);
    Notification::assertSentTo($cook, ComplaintEscalatedCookNotification::class);
});

test('notifyComplaintResolved sends push, DB and email to client', function () {
    Notification::fake();
    Mail::fake();

    $client = test()->createUserWithRole('client');
    $client->update(['email' => 'client@test.com']);

    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'resolution_type' => 'full_refund',
        'refund_amount' => '5000.00',
        'resolution_notes' => 'Full refund granted.',
        'status' => 'resolved',
        'resolved_at' => now(),
    ]);

    $service = new ComplaintNotificationService;
    $service->notifyComplaintResolved($complaint);

    Notification::assertSentTo($client, ComplaintResolvedNotification::class);
    Mail::assertQueued(ComplaintResolvedMail::class, function ($mail) {
        return $mail->hasTo('client@test.com');
    });
});

test('notifyComplaintResolved skips email when client has empty email', function () {
    Notification::fake();
    Mail::fake();

    $client = test()->createUserWithRole('client');
    $client->update(['email' => '']);

    $order = Order::factory()->create(['client_id' => $client->id]);
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'resolution_type' => 'dismiss',
        'status' => 'dismissed',
        'resolved_at' => now(),
    ]);

    $service = new ComplaintNotificationService;
    $service->notifyComplaintResolved($complaint);

    // Push+DB still sent
    Notification::assertSentTo($client, ComplaintResolvedNotification::class);
    // Email not sent (no email address)
    Mail::assertNotQueued(ComplaintResolvedMail::class);
});

// =============================================
// Escalation Notification Tests
// =============================================

test('ComplaintEscalatedAdminNotification action URL links to admin complaint page', function () {
    $complaint = Complaint::factory()->create(['id' => 33]);

    $notification = new ComplaintEscalatedAdminNotification($complaint);
    $url = $notification->getActionUrl(new stdClass);

    expect($url)->toContain('/vault-entry/complaints/33');
});

test('ComplaintEscalatedClientNotification action URL links to client complaint page', function () {
    $order = Order::factory()->create(['id' => 20]);
    $complaint = Complaint::factory()->create(['order_id' => $order->id, 'id' => 5]);

    $notification = new ComplaintEscalatedClientNotification($complaint);
    $url = $notification->getActionUrl(new stdClass);

    expect($url)->toContain('/my-orders/20/complaint/5');
});

test('ComplaintEscalatedCookNotification action URL links to cook dashboard complaint page', function () {
    $complaint = Complaint::factory()->create(['id' => 18]);

    $notification = new ComplaintEscalatedCookNotification($complaint);
    $url = $notification->getActionUrl(new stdClass);

    expect($url)->toContain('/dashboard/complaints/18');
});

test('multiple admins all receive escalation notifications', function () {
    Notification::fake();

    $admin1 = test()->createUserWithRole('admin');
    $admin2 = test()->createUserWithRole('admin');
    $superAdmin = test()->createUserWithRole('super-admin');

    $client = test()->createUserWithRole('client');
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);
    $complaint = Complaint::factory()->create([
        'cook_id' => $cook->id,
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $service = new ComplaintNotificationService;
    $service->notifyComplaintEscalated($complaint);

    Notification::assertSentTo($admin1, ComplaintEscalatedAdminNotification::class);
    Notification::assertSentTo($admin2, ComplaintEscalatedAdminNotification::class);
    Notification::assertSentTo($superAdmin, ComplaintEscalatedAdminNotification::class);
});

// =============================================
// Edge Cases
// =============================================

test('notifyComplaintSubmitted handles missing cook gracefully', function () {
    Notification::fake();

    // Create a cook and complaint, then delete the cook user to simulate missing cook
    $cook = test()->createUserWithRole('cook');
    $order = Order::factory()->create();
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'cook_id' => $cook->id,
    ]);

    // Delete the cook — complaint.cook relationship will return null
    $cook->delete();
    $complaint->unsetRelation('cook');

    $service = new ComplaintNotificationService;

    expect(fn () => $service->notifyComplaintSubmitted($complaint, $order))
        ->not->toThrow(\Throwable::class);
});

test('notifyComplaintResponse handles missing client gracefully', function () {
    Notification::fake();

    // Create a client and complaint, then delete the client to simulate missing client
    $client = test()->createUserWithRole('client');
    $complaint = Complaint::factory()->create(['client_id' => $client->id]);
    $response = ComplaintResponse::factory()->create(['complaint_id' => $complaint->id]);

    // Delete the client — complaint.client relationship will return null
    $client->delete();
    $complaint->unsetRelation('client');

    $service = new ComplaintNotificationService;

    expect(fn () => $service->notifyComplaintResponse($complaint, $response))
        ->not->toThrow(\Throwable::class);
});

test('cook responding after escalation still notifies client', function () {
    Notification::fake();

    $client = test()->createUserWithRole('client');
    $order = Order::factory()->create(['client_id' => $client->id]);
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'status' => 'escalated',
        'is_escalated' => true,
    ]);
    $response = ComplaintResponse::factory()->create(['complaint_id' => $complaint->id]);

    $service = new ComplaintNotificationService;
    $service->notifyComplaintResponse($complaint, $response);

    // Client receives response notification even when complaint is escalated
    Notification::assertSentTo($client, ComplaintResponseNotification::class);
});

test('admin resolving without prior escalation still notifies client', function () {
    Notification::fake();
    Mail::fake();

    $client = test()->createUserWithRole('client');
    $client->update(['email' => 'client2@test.com']);

    $order = Order::factory()->create(['client_id' => $client->id]);
    // No escalation — complaint goes directly from open to resolved by admin
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'status' => 'resolved',
        'resolution_type' => 'dismiss',
        'is_escalated' => false,
        'resolved_at' => now(),
    ]);

    $service = new ComplaintNotificationService;
    $service->notifyComplaintResolved($complaint);

    // Client still gets notification
    Notification::assertSentTo($client, ComplaintResolvedNotification::class);
    Mail::assertQueued(ComplaintResolvedMail::class);
});
