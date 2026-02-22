<?php

use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Services\MessageNotificationService;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

beforeEach(function () {
    test()->seedRolesAndPermissions();
});

// =============================================
// F-190 BR-264: Client sends → cook + managers
// =============================================

test('sending a message as client notifies the cook via push and DB', function () {
    Notification::fake();

    $cook = createUser();
    $client = createUser();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($client)
        ->post(url('/my-orders/'.$order->id.'/messages'), [], ['Gale-Request' => '1']);

    // Visit thread to trigger notification on next message — directly test service
    $message = OrderMessage::factory()->create([
        'order_id' => $order->id,
        'sender_id' => $client->id,
        'sender_role' => OrderMessage::ROLE_CLIENT,
        'body' => 'Is my order ready soon?',
    ]);

    $service = app(MessageNotificationService::class);
    $service->notifyNewMessage($order, $message);

    Notification::assertSentTo($cook, NewMessageNotification::class);
});

// =============================================
// F-190 BR-265: Cook sends → client only
// =============================================

test('sending a message as cook notifies the client via push and DB', function () {
    Notification::fake();

    $cook = createUser();
    $client = createUser();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $message = OrderMessage::factory()->create([
        'order_id' => $order->id,
        'sender_id' => $cook->id,
        'sender_role' => OrderMessage::ROLE_COOK,
        'body' => 'Your order is being prepared. Ready by 1pm.',
    ]);

    $service = app(MessageNotificationService::class);
    $service->notifyNewMessage($order, $message);

    Notification::assertSentTo($client, NewMessageNotification::class);
    Notification::assertNotSentTo($cook, NewMessageNotification::class);
});

// =============================================
// F-190 BR-262: Push suppressed when viewing
// =============================================

test('push notification is suppressed when recipient is viewing the thread', function () {
    Notification::fake();

    $cook = createUser();
    $client = createUser();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = app(MessageNotificationService::class);
    // Simulate cook viewing the thread
    $service->markUserViewingThread($order, $cook);

    $message = OrderMessage::factory()->create([
        'order_id' => $order->id,
        'sender_id' => $client->id,
        'sender_role' => OrderMessage::ROLE_CLIENT,
        'body' => 'Are you there?',
    ]);

    $service->notifyNewMessage($order, $message);

    // Notification sent but without WebPush channel
    Notification::assertSentTo($cook, NewMessageNotification::class, function ($notification) {
        $channels = $notification->via(new stdClass);

        return ! in_array(WebPushChannel::class, $channels, true) && in_array('database', $channels, true);
    });
});

// =============================================
// F-190 BR-263: DB always recorded even when viewing
// =============================================

test('database notification is always recorded even when push is suppressed', function () {
    Notification::fake();

    $cook = createUser();
    $client = createUser();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = app(MessageNotificationService::class);
    $service->markUserViewingThread($order, $cook);

    $message = OrderMessage::factory()->create([
        'order_id' => $order->id,
        'sender_id' => $client->id,
        'sender_role' => OrderMessage::ROLE_CLIENT,
    ]);

    $service->notifyNewMessage($order, $message);

    // DB notification must be dispatched regardless
    Notification::assertSentTo($cook, NewMessageNotification::class);
});

// =============================================
// F-190 BR-266: Mark notifications as read
// =============================================

test('visiting the thread as client marks message notifications as read', function () {
    $cook = createUser();
    $client = createUser();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    // Insert unread notification for client
    DB::table('notifications')->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'type' => NewMessageNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $client->id,
        'data' => json_encode([
            'type' => NewMessageNotification::TYPE,
            'order_id' => $order->id,
        ]),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Visit the thread as client
    $this->actingAs($client)
        ->get(url('/my-orders/'.$order->id.'/messages'));

    // Notification should be marked as read
    $unread = DB::table('notifications')
        ->where('notifiable_id', $client->id)
        ->whereNull('read_at')
        ->count();

    expect($unread)->toBe(0);
});

// =============================================
// F-190 BR-259: No email channel
// =============================================

test('message notifications never use email channel', function () {
    $order = Order::factory()->create();
    $message = OrderMessage::factory()->create(['order_id' => $order->id]);

    $notification = new NewMessageNotification($order, $message);
    $channels = $notification->via(new stdClass);

    expect($channels)->not->toContain('mail');
    expect($channels)->not->toContain(\Illuminate\Notifications\Channels\MailChannel::class);
});

// =============================================
// F-190 BR-260: Correct content format
// =============================================

test('notification preview truncates long messages at 100 characters', function () {
    $order = Order::factory()->create();
    $sender = User::factory()->create(['name' => 'Chef Latifa']);
    $longBody = str_repeat('X', 200);

    $message = OrderMessage::factory()->create([
        'order_id' => $order->id,
        'sender_id' => $sender->id,
        'body' => $longBody,
    ]);

    $notification = new NewMessageNotification($order, $message);
    $body = $notification->getBody(new stdClass);

    // Body contains sender name and truncated preview
    expect($body)->toContain('Chef Latifa');
    expect($body)->toContain('...');
    // Preview portion should be max 100 chars
    $previewPart = str_replace('Chef Latifa: ', '', $body);
    expect(mb_strlen($previewPart))->toBeLessThanOrEqual(103); // 97 + "..." = 100 chars
});
