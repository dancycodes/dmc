<?php

use App\Mail\PaymentReceiptMail;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentFailedNotification;
use App\Services\PaymentNotificationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    test()->seedRolesAndPermissions();
});

// =============================================
// F-194 BR-299: Webhook success → push+DB+email
// =============================================

test('PaymentNotificationService notifyPaymentSuccess dispatches push+DB to client', function () {
    Notification::fake();
    Mail::fake();

    $client = createUser();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'grand_total' => 8000,
    ]);

    $service = app(PaymentNotificationService::class);
    $service->notifyPaymentSuccess($order, $tenant, $client);

    // N-006: Push + DB notification sent to client
    Notification::assertSentTo($client, PaymentConfirmedNotification::class);
});

test('PaymentNotificationService notifyPaymentSuccess queues receipt email to client', function () {
    Notification::fake();
    Mail::fake();

    $client = createUser();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'grand_total' => 8000,
    ]);
    $transaction = PaymentTransaction::factory()->create([
        'order_id' => $order->id,
        'status' => 'successful',
    ]);

    $service = app(PaymentNotificationService::class);
    $service->notifyPaymentSuccess($order, $tenant, $client, $transaction);

    // BR-299: Email receipt queued to client
    Mail::assertQueued(PaymentReceiptMail::class, fn ($mail) => $mail->hasTo($client->email));
});

// =============================================
// F-194 BR-300: Webhook failure → push+DB only
// =============================================

test('PaymentNotificationService notifyPaymentFailed dispatches push+DB to client', function () {
    Notification::fake();
    Mail::fake();

    $client = createUser();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = app(PaymentNotificationService::class);
    $service->notifyPaymentFailed($order, $client, 'Card declined');

    // N-007: Push + DB notification sent to client
    Notification::assertSentTo($client, PaymentFailedNotification::class);
});

test('PaymentNotificationService notifyPaymentFailed never sends email', function () {
    Notification::fake();
    Mail::fake();

    $client = createUser();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = app(PaymentNotificationService::class);
    $service->notifyPaymentFailed($order, $client, 'Insufficient funds');

    // BR-300: No email on payment failure
    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

// =============================================
// F-194 BR-307: alreadyNotified guard
// =============================================

test('PaymentNotificationService skips all notifications when alreadyNotified is true', function () {
    Notification::fake();
    Mail::fake();

    $client = createUser();
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = app(PaymentNotificationService::class);
    $service->notifyPaymentSuccess($order, $tenant, $client, null, alreadyNotified: true);

    // Guard prevents duplicate notifications
    Notification::assertNothingSent();
    Mail::assertNothingQueued();
});
