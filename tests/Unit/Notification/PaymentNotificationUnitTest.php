<?php

use App\Mail\PaymentReceiptMail;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentFailedNotification;
use App\Services\PaymentNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
});

// =============================================
// PaymentFailedNotification Tests
// =============================================

test('PaymentFailedNotification uses push and database channels only', function () {
    $order = Order::factory()->create();

    $notification = new PaymentFailedNotification($order, 'Insufficient funds');
    $channels = $notification->via(new stdClass);

    expect($channels)->toContain(WebPushChannel::class)
        ->toContain('database')
        ->not->toContain('mail');
});

test('PaymentFailedNotification title is localized', function () {
    $order = Order::factory()->create();

    $notification = new PaymentFailedNotification($order);
    $title = $notification->getTitle(new stdClass);

    expect($title)->toBe('Payment Failed');
});

test('PaymentFailedNotification body contains order number', function () {
    $order = Order::factory()->create(['order_number' => 'DMC-260221-0042']);

    $notification = new PaymentFailedNotification($order);
    $body = $notification->getBody(new stdClass);

    expect($body)->toContain('DMC-260221-0042');
});

test('PaymentFailedNotification action URL links to payment retry page', function () {
    $tenant = Tenant::factory()->create(['slug' => 'testcook']);
    $order = Order::factory()->create([
        'id' => 99,
        'tenant_id' => $tenant->id,
    ]);

    $notification = new PaymentFailedNotification($order);
    $url = $notification->getActionUrl(new stdClass);

    expect($url)->toContain('/checkout/payment/retry/99');
});

test('PaymentFailedNotification getData includes correct type and order info', function () {
    $order = Order::factory()->create([
        'order_number' => 'DMC-260221-0001',
        'grand_total' => 5000,
    ]);

    $notification = new PaymentFailedNotification($order, 'Card declined');
    $data = $notification->getData(new stdClass);

    expect($data)
        ->toHaveKey('type', 'payment_failed')
        ->toHaveKey('order_id', $order->id)
        ->toHaveKey('order_number', 'DMC-260221-0001')
        ->toHaveKey('failure_reason', 'Card declined');
});

test('PaymentFailedNotification uses unique tag per order', function () {
    $order = Order::factory()->create(['id' => 77]);

    $notification = new PaymentFailedNotification($order);
    $tag = $notification->getTag(new stdClass);

    expect($tag)->toBe('payment-failed-77');
});

test('PaymentFailedNotification toArray includes all notification fields', function () {
    $order = Order::factory()->create();

    $notification = new PaymentFailedNotification($order);
    $array = $notification->toArray(new stdClass);

    expect($array)
        ->toHaveKey('title')
        ->toHaveKey('body')
        ->toHaveKey('icon')
        ->toHaveKey('action_url')
        ->toHaveKey('data');
});

// =============================================
// PaymentNotificationService Tests
// =============================================

test('notifyPaymentSuccess sends push+DB notification to client', function () {
    Notification::fake();
    Mail::fake();

    $client = createUser();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'grand_total' => 3000,
    ]);

    $service = new PaymentNotificationService;
    $service->notifyPaymentSuccess($order, $tenant, $client);

    Notification::assertSentTo($client, PaymentConfirmedNotification::class);
});

test('notifyPaymentSuccess queues receipt email to client', function () {
    Notification::fake();
    Mail::fake();

    $client = createUser();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new PaymentNotificationService;
    $service->notifyPaymentSuccess($order, $tenant, $client);

    Mail::assertQueued(PaymentReceiptMail::class, fn ($mail) => $mail->hasTo($client->email));
});

test('notifyPaymentSuccess with alreadyNotified=true skips all notifications', function () {
    Notification::fake();
    Mail::fake();

    $client = createUser();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new PaymentNotificationService;
    $service->notifyPaymentSuccess($order, $tenant, $client, null, alreadyNotified: true);

    Notification::assertNothingSent();
    Mail::assertNothingQueued();
});

test('notifyPaymentFailed sends push+DB notification to client', function () {
    Notification::fake();
    Mail::fake();

    $client = createUser();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new PaymentNotificationService;
    $service->notifyPaymentFailed($order, $client, 'Insufficient funds');

    Notification::assertSentTo($client, PaymentFailedNotification::class);
});

test('notifyPaymentFailed does not send email', function () {
    Notification::fake();
    Mail::fake();

    $client = createUser();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new PaymentNotificationService;
    $service->notifyPaymentFailed($order, $client);

    // BR-300: No email for payment failure
    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

test('notifyPaymentSuccess passes transaction to receipt mail', function () {
    Notification::fake();
    Mail::fake();

    $client = createUser();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);
    $transaction = PaymentTransaction::factory()->create(['order_id' => $order->id, 'status' => 'successful']);

    $service = new PaymentNotificationService;
    $service->notifyPaymentSuccess($order, $tenant, $client, $transaction);

    Mail::assertQueued(PaymentReceiptMail::class);
});

test('notifyPaymentSuccess does not send email when client email is empty', function () {
    Notification::fake();
    Mail::fake();

    // Create user with an empty string email — empty('') is truthy so sendReceiptEmail skips it
    $client = User::factory()->create(['email' => 'placeholder@example.com']);
    // Force email to empty string on the model in-memory (DB has NOT NULL, service checks empty())
    $client->setRawAttributes(array_merge($client->getAttributes(), ['email' => '']));

    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new PaymentNotificationService;
    $service->notifyPaymentSuccess($order, $tenant, $client);

    // Push/DB still sent, email skipped because email is empty
    Notification::assertSentTo($client, PaymentConfirmedNotification::class);
    Mail::assertNothingQueued();
});
