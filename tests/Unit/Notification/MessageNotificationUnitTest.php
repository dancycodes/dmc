<?php

use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Services\MessageNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
});

// =============================================
// NewMessageNotification Tests
// =============================================

test('NewMessageNotification uses push and database channels by default', function () {
    $order = Order::factory()->create();
    $message = OrderMessage::factory()->create(['order_id' => $order->id]);

    $notification = new NewMessageNotification($order, $message);
    $channels = $notification->via(new stdClass);

    expect($channels)
        ->toContain(WebPushChannel::class)
        ->toContain('database');
});

test('NewMessageNotification uses only database channel when push is suppressed', function () {
    $order = Order::factory()->create();
    $message = OrderMessage::factory()->create(['order_id' => $order->id]);

    $notification = new NewMessageNotification($order, $message, suppressPush: true);
    $channels = $notification->via(new stdClass);

    expect($channels)
        ->toContain('database')
        ->not->toContain(WebPushChannel::class);
});

test('NewMessageNotification title is localized', function () {
    $order = Order::factory()->create();
    $message = OrderMessage::factory()->create(['order_id' => $order->id]);

    $notification = new NewMessageNotification($order, $message);
    $title = $notification->getTitle(new stdClass);

    expect($title)->toBe('New Message');
});

test('NewMessageNotification body includes sender name and preview', function () {
    $order = Order::factory()->create();
    $sender = User::factory()->create(['name' => 'Amara Diallo']);
    $message = OrderMessage::factory()->create([
        'order_id' => $order->id,
        'sender_id' => $sender->id,
        'body' => 'Can I add extra pepper to my order?',
    ]);

    $notification = new NewMessageNotification($order, $message);
    $body = $notification->getBody(new stdClass);

    expect($body)
        ->toContain('Amara Diallo')
        ->toContain('Can I add extra pepper to my order?');
});

test('NewMessageNotification preview is truncated at 100 characters', function () {
    $order = Order::factory()->create();
    $sender = User::factory()->create(['name' => 'Sender']);
    $longBody = str_repeat('A', 150);
    $message = OrderMessage::factory()->create([
        'order_id' => $order->id,
        'sender_id' => $sender->id,
        'body' => $longBody,
    ]);

    $notification = new NewMessageNotification($order, $message);
    $body = $notification->getBody(new stdClass);

    // Preview should be at most 100 chars + "..."
    expect(mb_strlen($body))->toBeLessThan(130);
    expect($body)->toContain('...');
});

test('NewMessageNotification action URL for client recipient points to client thread', function () {
    $client = User::factory()->create();
    $order = Order::factory()->create(['client_id' => $client->id]);
    $message = OrderMessage::factory()->create(['order_id' => $order->id]);

    $notification = new NewMessageNotification($order, $message);
    $url = $notification->getActionUrl($client);

    expect($url)->toContain('/my-orders/'.$order->id.'/messages');
});

test('NewMessageNotification action URL for cook recipient points to dashboard thread', function () {
    $cook = User::factory()->create();
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);
    $message = OrderMessage::factory()->create(['order_id' => $order->id]);

    $notification = new NewMessageNotification($order, $message);
    $url = $notification->getActionUrl($cook);

    expect($url)->toContain('/dashboard/orders/'.$order->id.'/messages');
});

test('NewMessageNotification data array contains required fields', function () {
    $order = Order::factory()->create(['order_number' => 'DMC-260101-0001']);
    $message = OrderMessage::factory()->create(['order_id' => $order->id]);

    $notification = new NewMessageNotification($order, $message);
    $data = $notification->getData(new stdClass);

    expect($data)
        ->toHaveKey('type', NewMessageNotification::TYPE)
        ->toHaveKey('order_id', $order->id)
        ->toHaveKey('order_number', 'DMC-260101-0001')
        ->toHaveKey('message_id', $message->id)
        ->toHaveKey('sender_name')
        ->toHaveKey('preview');
});

test('NewMessageNotification tag is order-specific', function () {
    $order = Order::factory()->create();
    $message = OrderMessage::factory()->create(['order_id' => $order->id]);

    $notification = new NewMessageNotification($order, $message);
    $tag = $notification->getTag(new stdClass);

    expect($tag)->toBe('message-order-'.$order->id);
});

// =============================================
// MessageNotificationService Tests
// =============================================

test('MessageNotificationService marks user as viewing thread in cache', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create();

    $service = new MessageNotificationService;
    $service->markUserViewingThread($order, $user);

    expect($service->isUserViewingThread($user->id, $order->id))->toBeTrue();
});

test('MessageNotificationService returns false when user is not viewing thread', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create();

    Cache::flush();

    $service = new MessageNotificationService;

    expect($service->isUserViewingThread($user->id, $order->id))->toBeFalse();
});

test('MessageNotificationService resolves cook and managers as recipients when client sends', function () {
    $cook = User::factory()->create();
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new MessageNotificationService;
    $recipients = $service->resolveCookAndManagerRecipients($order);

    $recipientIds = array_map(fn ($r) => $r->id, $recipients);
    expect($recipientIds)->toContain($cook->id);
});

test('MessageNotificationService resolves client as recipient when cook sends', function () {
    $cook = User::factory()->create();
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new MessageNotificationService;
    $recipients = $service->resolveClientRecipient($order);

    expect($recipients)->toHaveCount(1);
    expect($recipients[0]->id)->toBe($client->id);
});

test('MessageNotificationService notifies cook when client sends a message', function () {
    Notification::fake();

    $cook = User::factory()->create();
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);
    $message = OrderMessage::factory()->create([
        'order_id' => $order->id,
        'sender_id' => $client->id,
        'sender_role' => OrderMessage::ROLE_CLIENT,
    ]);

    $service = new MessageNotificationService;
    $service->notifyNewMessage($order, $message);

    Notification::assertSentTo($cook, NewMessageNotification::class);
    Notification::assertNotSentTo($client, NewMessageNotification::class);
});

test('MessageNotificationService notifies client when cook sends a message', function () {
    Notification::fake();

    $cook = User::factory()->create();
    $client = User::factory()->create();
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
    ]);

    $service = new MessageNotificationService;
    $service->notifyNewMessage($order, $message);

    Notification::assertSentTo($client, NewMessageNotification::class);
    Notification::assertNotSentTo($cook, NewMessageNotification::class);
});

test('MessageNotificationService does not notify the sender', function () {
    Notification::fake();

    $cook = User::factory()->create();
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);
    // Client sends to cook, cook also happens to be own sender (edge-case guard check)
    $message = OrderMessage::factory()->create([
        'order_id' => $order->id,
        'sender_id' => $client->id,
        'sender_role' => OrderMessage::ROLE_CLIENT,
    ]);

    $service = new MessageNotificationService;
    $service->notifyNewMessage($order, $message);

    // Client should not receive notification for their own message
    Notification::assertNotSentTo($client, NewMessageNotification::class);
});

test('MessageNotificationService suppresses push when recipient is viewing thread', function () {
    Notification::fake();

    $cook = User::factory()->create();
    $client = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
    ]);
    $message = OrderMessage::factory()->create([
        'order_id' => $order->id,
        'sender_id' => $client->id,
        'sender_role' => OrderMessage::ROLE_CLIENT,
    ]);

    $service = new MessageNotificationService;
    // Mark the cook as currently viewing the thread
    $service->markUserViewingThread($order, $cook);

    $service->notifyNewMessage($order, $message);

    // Notification sent but with push suppressed — check notification was dispatched
    Notification::assertSentTo($cook, NewMessageNotification::class, function ($notification) {
        // suppressPush is true → only database channel
        $channels = $notification->via(new stdClass);

        return ! in_array(WebPushChannel::class, $channels, true) && in_array('database', $channels, true);
    });
});

test('MessageNotificationService marks notifications as read in database', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create();

    // Insert a fake unread notification
    DB::table('notifications')->insert([
        'id' => \Illuminate\Support\Str::uuid(),
        'type' => NewMessageNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode([
            'type' => NewMessageNotification::TYPE,
            'order_id' => $order->id,
        ]),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = new MessageNotificationService;
    $service->markThreadNotificationsRead($order, $user);

    $unread = DB::table('notifications')
        ->where('notifiable_id', $user->id)
        ->whereNull('read_at')
        ->count();

    expect($unread)->toBe(0);
});
