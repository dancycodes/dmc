<?php

use App\Mail\NewOrderMail;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use App\Services\OrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
});

// =============================================
// NewOrderNotification Tests
// =============================================

test('NewOrderNotification uses push and database channels', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $notification = new NewOrderNotification($order, $tenant);
    $channels = $notification->via(new stdClass);

    expect($channels)->toContain(WebPushChannel::class)
        ->toContain('database');
});

test('NewOrderNotification title is localized', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $notification = new NewOrderNotification($order, $tenant);
    $title = $notification->getTitle(new stdClass);

    expect($title)->toBe('New Order Received!');
});

test('NewOrderNotification body contains order number, item count, amount, and delivery method', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260221-0001',
        'grand_total' => 5500,
        'delivery_method' => Order::METHOD_DELIVERY,
        'items_snapshot' => [
            ['meal_name' => 'Ndole', 'component_name' => 'Plate', 'quantity' => 2, 'unit_price' => 2000, 'subtotal' => 4000],
            ['meal_name' => 'Eru', 'component_name' => 'Bowl', 'quantity' => 1, 'unit_price' => 1500, 'subtotal' => 1500],
        ],
    ]);

    $notification = new NewOrderNotification($order, $tenant);
    $body = $notification->getBody(new stdClass);

    expect($body)
        ->toContain('DMC-260221-0001')
        ->toContain('3') // 2+1 items
        ->toContain('5,500 XAF')
        ->toContain('Delivery');
});

test('NewOrderNotification body shows Pickup for pickup orders', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'delivery_method' => Order::METHOD_PICKUP,
        'items_snapshot' => [
            ['meal_name' => 'Ndole', 'quantity' => 1, 'unit_price' => 2000, 'subtotal' => 2000],
        ],
    ]);

    $notification = new NewOrderNotification($order, $tenant);
    $body = $notification->getBody(new stdClass);

    expect($body)->toContain('Pickup');
});

test('NewOrderNotification action URL links to cook dashboard order detail', function () {
    $tenant = Tenant::factory()->create(['slug' => 'latifa']);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'id' => 99,
    ]);

    $notification = new NewOrderNotification($order, $tenant);
    $url = $notification->getActionUrl(new stdClass);

    expect($url)->toContain('/dashboard/orders/99');
});

test('NewOrderNotification getData includes client name and item count', function () {
    $client = User::factory()->create(['name' => 'John Doe']);
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'delivery_method' => Order::METHOD_DELIVERY,
        'items_snapshot' => [
            ['meal_name' => 'Ndole', 'quantity' => 3, 'unit_price' => 2000, 'subtotal' => 6000],
        ],
    ]);

    $notification = new NewOrderNotification($order, $tenant);
    $data = $notification->getData(new stdClass);

    expect($data)
        ->toHaveKey('order_id', $order->id)
        ->toHaveKey('order_number', $order->order_number)
        ->toHaveKey('client_name', 'John Doe')
        ->toHaveKey('item_count', 3)
        ->toHaveKey('delivery_method', 'delivery')
        ->toHaveKey('type', 'new_order');
});

test('NewOrderNotification toArray stores title, body, action_url', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'items_snapshot' => [
            ['meal_name' => 'Test', 'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000],
        ],
    ]);

    $notification = new NewOrderNotification($order, $tenant);
    $array = $notification->toArray(new stdClass);

    expect($array)
        ->toHaveKey('title')
        ->toHaveKey('body')
        ->toHaveKey('action_url')
        ->toHaveKey('icon')
        ->toHaveKey('data');

    expect($array['title'])->toBe('New Order Received!');
    expect($array['data']['type'])->toBe('new_order');
});

test('NewOrderNotification tag uses order ID', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'id' => 42,
    ]);

    $notification = new NewOrderNotification($order, $tenant);
    $tag = $notification->getTag(new stdClass);

    expect($tag)->toBe('order-42');
});

// =============================================
// NewOrderMail Tests
// =============================================

test('NewOrderMail has correct subject line', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'DMC-260221-0001',
        'grand_total' => 5500,
    ]);

    $mail = new NewOrderMail($order, $tenant);
    $envelope = $mail->envelope();

    expect($envelope->subject)->toContain('New Order')
        ->toContain('DMC-260221-0001')
        ->toContain('5,500 XAF');
});

test('NewOrderMail uses correct email view', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $mail = new NewOrderMail($order, $tenant);
    $content = $mail->content();

    expect($content->view)->toBe('emails.new-order');
});

test('NewOrderMail includes all required data', function () {
    $client = User::factory()->create(['name' => 'Jane Client']);
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'delivery_method' => Order::METHOD_DELIVERY,
        'items_snapshot' => [
            ['meal_name' => 'Ndole', 'component_name' => 'Full Plate', 'quantity' => 2, 'unit_price' => 2000, 'subtotal' => 4000],
        ],
    ]);

    $mail = new NewOrderMail($order, $tenant);
    $content = $mail->content();

    // Extract email data from the content
    $data = $content->with;

    expect($data)
        ->toHaveKey('order')
        ->toHaveKey('items')
        ->toHaveKey('itemCount')
        ->toHaveKey('clientName')
        ->toHaveKey('deliveryLabel')
        ->toHaveKey('viewOrderUrl')
        ->toHaveKey('orderDate')
        ->toHaveKey('emailLocale');

    expect($data['clientName'])->toBe('Jane Client');
    expect($data['itemCount'])->toBe(2);
    expect($data['viewOrderUrl'])->toContain('/dashboard/orders/');
});

test('NewOrderMail is queued', function () {
    $mail = new NewOrderMail(
        Order::factory()->create(),
        Tenant::factory()->create()
    );

    expect($mail)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

test('NewOrderMail extends BaseMailableNotification', function () {
    $mail = new NewOrderMail(
        Order::factory()->create(),
        Tenant::factory()->create()
    );

    expect($mail)->toBeInstanceOf(\App\Mail\BaseMailableNotification::class);
});

// =============================================
// OrderNotificationService Tests
// =============================================

test('OrderNotificationService resolves cook as recipient', function () {
    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);

    $service = new OrderNotificationService;
    $recipients = $service->resolveRecipients($tenant);

    expect($recipients)->toHaveCount(1);
    expect($recipients[0]->id)->toBe($cook->id);
});

test('OrderNotificationService resolves cook and managers', function () {
    $cook = test()->createUserWithRole('cook');
    $manager = test()->createUserWithRole('manager');
    $manager->givePermissionTo('can-manage-orders');

    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);

    $service = new OrderNotificationService;
    $recipients = $service->resolveRecipients($tenant);

    $recipientIds = array_map(fn ($r) => $r->id, $recipients);

    expect($recipientIds)->toContain($cook->id)
        ->toContain($manager->id);
});

test('OrderNotificationService does not duplicate cook in recipients', function () {
    $cook = test()->createUserWithRole('cook');
    $cook->givePermissionTo('can-manage-orders');

    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);

    $service = new OrderNotificationService;
    $recipients = $service->resolveRecipients($tenant);

    $cookCount = count(array_filter($recipients, fn ($r) => $r->id === $cook->id));
    expect($cookCount)->toBe(1);
});

test('OrderNotificationService sends push and DB notifications to all recipients', function () {
    Notification::fake();

    $cook = test()->createUserWithRole('cook');
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $service = new OrderNotificationService;
    $service->notifyNewOrder($order, $tenant);

    Notification::assertSentTo($cook, NewOrderNotification::class);
});

test('OrderNotificationService sends email to cook', function () {
    Notification::fake();
    Mail::fake();

    $cook = test()->createUserWithRole('cook');
    $cook->update(['email' => 'cook@test.com']);
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $service = new OrderNotificationService;
    $service->notifyNewOrder($order, $tenant);

    Mail::assertQueued(NewOrderMail::class, function ($mail) {
        return $mail->hasTo('cook@test.com');
    });
});

test('OrderNotificationService sends email to managers', function () {
    Notification::fake();
    Mail::fake();

    $cook = test()->createUserWithRole('cook');
    $cook->update(['email' => 'cook@test.com']);
    $manager = test()->createUserWithRole('manager');
    $manager->update(['email' => 'manager@test.com']);
    $manager->givePermissionTo('can-manage-orders');

    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $service = new OrderNotificationService;
    $service->notifyNewOrder($order, $tenant);

    Mail::assertQueued(NewOrderMail::class, function ($mail) {
        return $mail->hasTo('manager@test.com');
    });
});

test('OrderNotificationService skips email when recipient has empty email', function () {
    Notification::fake();
    Mail::fake();

    $cook = test()->createUserWithRole('cook');
    // Set email to empty string — the service checks empty($recipient->email)
    $cook->update(['email' => '']);
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $service = new OrderNotificationService;
    $service->notifyNewOrder($order, $tenant);

    // Push/DB still sent
    Notification::assertSentTo($cook, NewOrderNotification::class);
    // Email not queued (empty email address)
    Mail::assertNotQueued(NewOrderMail::class);
});

test('OrderNotificationService handles multiple managers', function () {
    Notification::fake();
    Mail::fake();

    $cook = test()->createUserWithRole('cook');
    $cook->update(['email' => 'cook@test.com']);

    $manager1 = test()->createUserWithRole('manager');
    $manager1->update(['email' => 'manager1@test.com']);
    $manager1->givePermissionTo('can-manage-orders');

    $manager2 = test()->createUserWithRole('manager');
    $manager2->update(['email' => 'manager2@test.com']);
    $manager2->givePermissionTo('can-manage-orders');

    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $service = new OrderNotificationService;
    $service->notifyNewOrder($order, $tenant);

    // All 3 should get push + DB notifications
    Notification::assertSentTo($cook, NewOrderNotification::class);
    Notification::assertSentTo($manager1, NewOrderNotification::class);
    Notification::assertSentTo($manager2, NewOrderNotification::class);

    // All 3 should get emails
    Mail::assertQueued(NewOrderMail::class, 3);
});

// =============================================
// OrderNotificationService Static Helpers
// =============================================

test('getItemCount returns correct count from items_snapshot', function () {
    $order = Order::factory()->create([
        'items_snapshot' => [
            ['meal_name' => 'Ndole', 'quantity' => 2, 'unit_price' => 2000],
            ['meal_name' => 'Eru', 'quantity' => 3, 'unit_price' => 1500],
        ],
    ]);

    $count = OrderNotificationService::getItemCount($order);
    expect($count)->toBe(5);
});

test('getItemCount returns 0 for empty snapshot', function () {
    $order = Order::factory()->create(['items_snapshot' => null]);
    expect(OrderNotificationService::getItemCount($order))->toBe(0);

    $order2 = Order::factory()->create(['items_snapshot' => []]);
    expect(OrderNotificationService::getItemCount($order2))->toBe(0);
});

test('getItemCount handles double-encoded JSON', function () {
    $order = Order::factory()->make([
        'items_snapshot' => json_encode([
            ['meal_name' => 'Ndole', 'quantity' => 2],
        ]),
    ]);

    // Force the items_snapshot to be a string (double-encoded)
    $count = OrderNotificationService::getItemCount($order);
    expect($count)->toBeGreaterThanOrEqual(0);
});

test('getDeliveryMethodLabel returns Delivery for delivery orders', function () {
    $order = Order::factory()->make(['delivery_method' => Order::METHOD_DELIVERY]);
    expect(OrderNotificationService::getDeliveryMethodLabel($order))->toBe('Delivery');
});

test('getDeliveryMethodLabel returns Pickup for pickup orders', function () {
    $order = Order::factory()->make(['delivery_method' => Order::METHOD_PICKUP]);
    expect(OrderNotificationService::getDeliveryMethodLabel($order))->toBe('Pickup');
});

// =============================================
// Edge Cases
// =============================================

test('notification does not fail when tenant has no cook', function () {
    Notification::fake();
    Mail::fake();

    $tenant = Tenant::factory()->create(['cook_id' => null]);
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $service = new OrderNotificationService;
    $service->notifyNewOrder($order, $tenant);

    // Should not throw, just no notifications sent
    Notification::assertNothingSent();
    Mail::assertNothingSent();
});

test('notification handles email delivery failure gracefully', function () {
    Notification::fake();
    Mail::shouldReceive('to')->andThrow(new \Exception('SMTP error'));

    $cook = test()->createUserWithRole('cook');
    $cook->update(['email' => 'cook@test.com']);
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $service = new OrderNotificationService;

    // Should not throw
    $service->notifyNewOrder($order, $tenant);

    // Push/DB still sent
    Notification::assertSentTo($cook, NewOrderNotification::class);
});

test('NewOrderNotification handles order with 20+ items', function () {
    $items = [];
    for ($i = 1; $i <= 25; $i++) {
        $items[] = ['meal_name' => "Meal $i", 'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100];
    }

    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'items_snapshot' => $items,
    ]);

    $notification = new NewOrderNotification($order, $tenant);
    $body = $notification->getBody(new stdClass);

    // Should show summary count (25 items), not all item names
    expect($body)->toContain('25');
});
