<?php

use App\Mail\OrderStatusUpdateMail;
use App\Models\Order;
use App\Models\PickupLocation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OrderStatusUpdateNotification;
use App\Services\OrderStatusNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
});

// =============================================
// OrderStatusUpdateNotification Tests
// =============================================

test('OrderStatusUpdateNotification uses push and database channels', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $notification = new OrderStatusUpdateNotification(
        $order, $tenant, Order::STATUS_PAID, Order::STATUS_CONFIRMED
    );

    $channels = $notification->via(new stdClass);

    expect($channels)->toContain(WebPushChannel::class)
        ->toContain('database');
});

test('OrderStatusUpdateNotification getTitle returns status-specific title', function (string $status, string $expected) {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $notification = new OrderStatusUpdateNotification(
        $order, $tenant, Order::STATUS_PAID, $status
    );

    expect($notification->getTitle(new stdClass))->toBe($expected);
})->with([
    [Order::STATUS_CONFIRMED,        'Order Confirmed!'],
    [Order::STATUS_PREPARING,        'Order Being Prepared'],
    [Order::STATUS_OUT_FOR_DELIVERY, 'Order Out for Delivery'],
    [Order::STATUS_READY_FOR_PICKUP, 'Ready for Pickup!'],
    [Order::STATUS_DELIVERED,        'Order Delivered!'],
    [Order::STATUS_COMPLETED,        'Order Completed!'],
    [Order::STATUS_CANCELLED,        'Order Cancelled'],
]);

test('OrderStatusUpdateNotification getBody contains order number for Confirmed', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260221-0001',
    ]);

    $notification = new OrderStatusUpdateNotification(
        $order, $tenant, Order::STATUS_PAID, Order::STATUS_CONFIRMED
    );

    $body = $notification->getBody(new stdClass);

    expect($body)
        ->toContain('DMC-260221-0001')
        ->toContain('Confirmed');
});

test('OrderStatusUpdateNotification getBody for Preparing contains order number', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260221-0042',
    ]);

    $notification = new OrderStatusUpdateNotification(
        $order, $tenant, Order::STATUS_CONFIRMED, Order::STATUS_PREPARING
    );

    $body = $notification->getBody(new stdClass);

    expect($body)->toContain('DMC-260221-0042')
        ->toContain('prepared');
});

test('OrderStatusUpdateNotification getBody for Ready for Pickup includes location', function () {
    $tenant = Tenant::factory()->create();
    $pickup = PickupLocation::factory()->create([
        'tenant_id' => $tenant->id,
        'name_en' => 'Market Square Pickup',
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260221-0007',
        'delivery_method' => Order::METHOD_PICKUP,
        'pickup_location_id' => $pickup->id,
    ]);
    $order->load('pickupLocation');

    $notification = new OrderStatusUpdateNotification(
        $order, $tenant, Order::STATUS_READY, Order::STATUS_READY_FOR_PICKUP
    );

    $body = $notification->getBody(new stdClass);

    expect($body)
        ->toContain('DMC-260221-0007')
        ->toContain('Market Square Pickup');
});

test('OrderStatusUpdateNotification getBody for Delivered mentions meal enjoyment', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260221-0099',
    ]);

    $notification = new OrderStatusUpdateNotification(
        $order, $tenant, Order::STATUS_OUT_FOR_DELIVERY, Order::STATUS_DELIVERED
    );

    $body = $notification->getBody(new stdClass);

    expect($body)
        ->toContain('DMC-260221-0099')
        ->toContain('Enjoy');
});

test('OrderStatusUpdateNotification getBody for Cancelled mentions cancellation', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260221-0055',
    ]);

    $notification = new OrderStatusUpdateNotification(
        $order, $tenant, Order::STATUS_CONFIRMED, Order::STATUS_CANCELLED
    );

    $body = $notification->getBody(new stdClass);

    expect($body)
        ->toContain('DMC-260221-0055')
        ->toContain('cancelled');
});

test('OrderStatusUpdateNotification getActionUrl links to client order detail', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'id' => 88,
    ]);

    $notification = new OrderStatusUpdateNotification(
        $order, $tenant, Order::STATUS_PAID, Order::STATUS_CONFIRMED
    );

    $url = $notification->getActionUrl(new stdClass);

    expect($url)->toContain('/my-orders/88');
});

test('OrderStatusUpdateNotification getData includes expected keys', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $notification = new OrderStatusUpdateNotification(
        $order, $tenant, Order::STATUS_PAID, Order::STATUS_CONFIRMED
    );

    $data = $notification->getData(new stdClass);

    expect($data)
        ->toHaveKey('order_id', $order->id)
        ->toHaveKey('order_number', $order->order_number)
        ->toHaveKey('tenant_id', $tenant->id)
        ->toHaveKey('previous_status', Order::STATUS_PAID)
        ->toHaveKey('new_status', Order::STATUS_CONFIRMED)
        ->toHaveKey('type', 'order_status_update');
});

test('OrderStatusUpdateNotification tag uses order ID', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $notification = new OrderStatusUpdateNotification(
        $order, $tenant, Order::STATUS_PAID, Order::STATUS_CONFIRMED
    );

    expect($notification->getTag(new stdClass))->toBe('order-status-'.$order->id);
});

test('OrderStatusUpdateNotification toArray stores title, body, action_url', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $notification = new OrderStatusUpdateNotification(
        $order, $tenant, Order::STATUS_PAID, Order::STATUS_CONFIRMED
    );

    $array = $notification->toArray(new stdClass);

    expect($array)
        ->toHaveKey('title')
        ->toHaveKey('body')
        ->toHaveKey('action_url')
        ->toHaveKey('icon')
        ->toHaveKey('data');

    expect($array['data']['type'])->toBe('order_status_update');
});

// =============================================
// OrderStatusUpdateMail Tests
// =============================================

test('OrderStatusUpdateMail shouldSendEmailForStatus returns true for key statuses', function (string $status) {
    expect(OrderStatusUpdateMail::shouldSendEmailForStatus($status))->toBeTrue();
})->with([
    Order::STATUS_CONFIRMED,
    Order::STATUS_READY_FOR_PICKUP,
    Order::STATUS_OUT_FOR_DELIVERY,
    Order::STATUS_DELIVERED,
    Order::STATUS_PICKED_UP,
    Order::STATUS_COMPLETED,
]);

test('OrderStatusUpdateMail shouldSendEmailForStatus returns false for non-key statuses', function (string $status) {
    expect(OrderStatusUpdateMail::shouldSendEmailForStatus($status))->toBeFalse();
})->with([
    Order::STATUS_PAID,
    Order::STATUS_PREPARING,
    Order::STATUS_READY,
    Order::STATUS_CANCELLED,
    Order::STATUS_REFUNDED,
]);

test('OrderStatusUpdateMail has status-specific subject for Confirmed', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260221-0001',
    ]);

    $mail = new OrderStatusUpdateMail($order, $tenant, Order::STATUS_CONFIRMED);
    $envelope = $mail->envelope();

    expect($envelope->subject)
        ->toContain('DMC-260221-0001')
        ->toContain('Confirmed');
});

test('OrderStatusUpdateMail has status-specific subject for Delivered', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260221-0002',
    ]);

    $mail = new OrderStatusUpdateMail($order, $tenant, Order::STATUS_DELIVERED);
    $envelope = $mail->envelope();

    expect($envelope->subject)
        ->toContain('DMC-260221-0002')
        ->toContain('Delivered');
});

test('OrderStatusUpdateMail uses correct email view', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $mail = new OrderStatusUpdateMail($order, $tenant, Order::STATUS_CONFIRMED);
    $content = $mail->content();

    expect($content->view)->toBe('emails.order-status-update');
});

test('OrderStatusUpdateMail includes all required data keys', function () {
    $client = User::factory()->create(['name' => 'Jane Client']);
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    $mail = new OrderStatusUpdateMail($order, $tenant, Order::STATUS_CONFIRMED);
    $content = $mail->content();
    $data = $content->with;

    expect($data)
        ->toHaveKey('order')
        ->toHaveKey('newStatus')
        ->toHaveKey('statusLabel')
        ->toHaveKey('clientName')
        ->toHaveKey('viewOrderUrl')
        ->toHaveKey('isRateable')
        ->toHaveKey('emailLocale');
});

test('OrderStatusUpdateMail isRateable true for Delivered status', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $mail = new OrderStatusUpdateMail($order, $tenant, Order::STATUS_DELIVERED);
    $content = $mail->content();

    expect($content->with['isRateable'])->toBeTrue();
    expect($content->with['rateOrderUrl'])->not->toBeNull();
});

test('OrderStatusUpdateMail isRateable true for Completed status', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $mail = new OrderStatusUpdateMail($order, $tenant, Order::STATUS_COMPLETED);
    $content = $mail->content();

    expect($content->with['isRateable'])->toBeTrue();
});

test('OrderStatusUpdateMail isRateable false for Confirmed status', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $mail = new OrderStatusUpdateMail($order, $tenant, Order::STATUS_CONFIRMED);
    $content = $mail->content();

    expect($content->with['isRateable'])->toBeFalse();
    expect($content->with['rateOrderUrl'])->toBeNull();
});

test('OrderStatusUpdateMail viewOrderUrl points to client order detail', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $mail = new OrderStatusUpdateMail($order, $tenant, Order::STATUS_CONFIRMED);
    $content = $mail->content();

    expect($content->with['viewOrderUrl'])
        ->toContain('/my-orders/'.$order->id);
});

test('OrderStatusUpdateMail is queued', function () {
    $mail = new OrderStatusUpdateMail(
        Order::factory()->create(),
        Tenant::factory()->create(),
        Order::STATUS_CONFIRMED
    );

    expect($mail)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

test('OrderStatusUpdateMail extends BaseMailableNotification', function () {
    $mail = new OrderStatusUpdateMail(
        Order::factory()->create(),
        Tenant::factory()->create(),
        Order::STATUS_CONFIRMED
    );

    expect($mail)->toBeInstanceOf(\App\Mail\BaseMailableNotification::class);
});

// =============================================
// OrderStatusNotificationService Tests
// =============================================

test('OrderStatusNotificationService resolves client from order', function () {
    $client = test()->createUserWithRole('client');
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    $service = new OrderStatusNotificationService;
    $resolved = $service->resolveClient($order);

    expect($resolved->id)->toBe($client->id);
});

test('OrderStatusNotificationService sends push and DB notification for every status change', function (string $status) {
    Notification::fake();

    $client = test()->createUserWithRole('client');
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    $service = new OrderStatusNotificationService;
    $service->notifyStatusUpdate($order, $tenant, Order::STATUS_PAID, $status);

    Notification::assertSentTo($client, OrderStatusUpdateNotification::class);
})->with([
    Order::STATUS_CONFIRMED,
    Order::STATUS_PREPARING,
    Order::STATUS_OUT_FOR_DELIVERY,
    Order::STATUS_READY_FOR_PICKUP,
    Order::STATUS_DELIVERED,
    Order::STATUS_COMPLETED,
    Order::STATUS_CANCELLED,
]);

test('OrderStatusNotificationService sends email for key statuses', function (string $status) {
    Notification::fake();
    Mail::fake();

    $client = test()->createUserWithRole('client');
    $client->update(['email' => 'client@test.com']);
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    $service = new OrderStatusNotificationService;
    $service->notifyStatusUpdate($order, $tenant, Order::STATUS_PAID, $status);

    Mail::assertQueued(OrderStatusUpdateMail::class, function ($mail) {
        return $mail->hasTo('client@test.com');
    });
})->with([
    Order::STATUS_CONFIRMED,
    Order::STATUS_READY_FOR_PICKUP,
    Order::STATUS_OUT_FOR_DELIVERY,
    Order::STATUS_DELIVERED,
    Order::STATUS_COMPLETED,
]);

test('OrderStatusNotificationService does not send email for non-key statuses', function (string $status) {
    Notification::fake();
    Mail::fake();

    $client = test()->createUserWithRole('client');
    $client->update(['email' => 'client@test.com']);
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    $service = new OrderStatusNotificationService;
    $service->notifyStatusUpdate($order, $tenant, Order::STATUS_PAID, $status);

    // Push + DB always sent
    Notification::assertSentTo($client, OrderStatusUpdateNotification::class);
    // Email NOT sent for non-key statuses
    Mail::assertNotQueued(OrderStatusUpdateMail::class);
})->with([
    Order::STATUS_PREPARING,
    Order::STATUS_CANCELLED,
]);

test('OrderStatusNotificationService skips email when client has no email', function () {
    Notification::fake();
    Mail::fake();

    $client = test()->createUserWithRole('client');
    $client->update(['email' => '']);
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    $service = new OrderStatusNotificationService;
    $service->notifyStatusUpdate($order, $tenant, Order::STATUS_PAID, Order::STATUS_CONFIRMED);

    // Push + DB still sent
    Notification::assertSentTo($client, OrderStatusUpdateNotification::class);
    // Email skipped
    Mail::assertNotQueued(OrderStatusUpdateMail::class);
});

test('OrderStatusNotificationService handles missing client gracefully', function () {
    Notification::fake();
    Mail::fake();

    $tenant = Tenant::factory()->create();
    // Order with no client (edge case — should not normally happen but be safe)
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
    ]);
    // Detach client relationship by setting null
    $order->setRelation('client', null);

    $service = new OrderStatusNotificationService;

    // Should not throw
    $service->notifyStatusUpdate($order, $tenant, Order::STATUS_PAID, Order::STATUS_CONFIRMED);

    Notification::assertNothingSent();
    Mail::assertNothingSent();
});

test('OrderStatusNotificationService handles email failure gracefully', function () {
    Notification::fake();
    Mail::shouldReceive('to')->andThrow(new \Exception('SMTP error'));

    $client = test()->createUserWithRole('client');
    $client->update(['email' => 'client@test.com']);
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    $service = new OrderStatusNotificationService;

    // Should not throw
    $service->notifyStatusUpdate($order, $tenant, Order::STATUS_PAID, Order::STATUS_CONFIRMED);

    // Push + DB still sent
    Notification::assertSentTo($client, OrderStatusUpdateNotification::class);
});
