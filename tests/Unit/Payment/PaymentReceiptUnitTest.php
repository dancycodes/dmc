<?php

use App\Mail\PaymentReceiptMail;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Notifications\NewOrderNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Services\PaymentReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
    $this->receiptService = app(PaymentReceiptService::class);
});

/*
|--------------------------------------------------------------------------
| PaymentReceiptService - Receipt Data Tests
|--------------------------------------------------------------------------
*/

test('getReceiptData returns correct structure for paid order', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();

    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_PAID,
        'payment_provider' => 'mtn_momo',
        'grand_total' => 6500,
        'subtotal' => 6000,
        'delivery_fee' => 500,
        'items_snapshot' => [
            ['meal_id' => 1, 'meal_name' => 'Ndole', 'component_id' => 1, 'component_name' => 'Main Dish', 'quantity' => 2, 'unit_price' => 3000, 'subtotal' => 6000],
        ],
        'paid_at' => now(),
    ]);

    PaymentTransaction::factory()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'status' => 'successful',
        'flutterwave_reference' => 'FLW-12345',
        'flutterwave_tx_ref' => 'TX-REF-001',
    ]);

    $data = $this->receiptService->getReceiptData($order);

    expect($data)->toHaveKeys(['order', 'transaction', 'cook', 'tenant', 'items', 'payment_label', 'transaction_reference'])
        ->and($data['order']->id)->toBe($order->id)
        ->and($data['transaction'])->not->toBeNull()
        ->and($data['transaction']->flutterwave_reference)->toBe('FLW-12345')
        ->and($data['payment_label'])->toBe(__('MTN Mobile Money'))
        ->and($data['transaction_reference'])->toBe('FLW-12345');
});

test('getReceiptData falls back to tx_ref when flutterwave_reference is empty', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_PAID,
        'payment_provider' => 'orange_money',
        'items_snapshot' => [],
    ]);

    PaymentTransaction::factory()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'status' => 'successful',
        'flutterwave_reference' => null,
        'flutterwave_tx_ref' => 'TX-REF-002',
    ]);

    $data = $this->receiptService->getReceiptData($order);

    expect($data['transaction_reference'])->toBe('TX-REF-002');
});

test('getReceiptData uses internal reference for wallet payments without transaction', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'order_number' => 'DMC-260220-0001',
        'status' => Order::STATUS_PAID,
        'payment_provider' => 'wallet',
        'items_snapshot' => [],
    ]);

    $data = $this->receiptService->getReceiptData($order);

    expect($data['transaction_reference'])->toBe('INT-DMC-260220-0001');
});

/*
|--------------------------------------------------------------------------
| Items Snapshot Parsing Tests
|--------------------------------------------------------------------------
*/

test('parseItemsSnapshot groups items by meal', function () {
    $snapshot = [
        ['meal_id' => 1, 'meal_name' => 'Ndole', 'component_id' => 1, 'component_name' => 'Main', 'quantity' => 1, 'unit_price' => 3000, 'subtotal' => 3000],
        ['meal_id' => 1, 'meal_name' => 'Ndole', 'component_id' => 2, 'component_name' => 'Side', 'quantity' => 2, 'unit_price' => 500, 'subtotal' => 1000],
        ['meal_id' => 2, 'meal_name' => 'Eru', 'component_id' => 3, 'component_name' => 'Main', 'quantity' => 1, 'unit_price' => 4000, 'subtotal' => 4000],
    ];

    $result = $this->receiptService->parseItemsSnapshot($snapshot);

    expect($result)->toHaveCount(2)
        ->and($result[0]['meal_name'])->toBe('Ndole')
        ->and($result[0]['components'])->toHaveCount(2)
        ->and($result[1]['meal_name'])->toBe('Eru')
        ->and($result[1]['components'])->toHaveCount(1);
});

test('parseItemsSnapshot handles empty snapshot', function () {
    $result = $this->receiptService->parseItemsSnapshot([]);

    expect($result)->toBeEmpty();
});

test('parseItemsSnapshot handles missing fields gracefully', function () {
    $snapshot = [
        ['meal_id' => 0],
    ];

    $result = $this->receiptService->parseItemsSnapshot($snapshot);

    expect($result)->toHaveCount(1)
        ->and($result[0]['meal_name'])->toBe(__('Unknown Meal'))
        ->and($result[0]['components'][0]['name'])->toBe(__('Unknown Item'));
});

/*
|--------------------------------------------------------------------------
| Payment Method Label Tests
|--------------------------------------------------------------------------
*/

test('getPaymentMethodLabel returns correct labels for all providers', function () {
    expect($this->receiptService->getPaymentMethodLabel('mtn_momo'))->toBe(__('MTN Mobile Money'))
        ->and($this->receiptService->getPaymentMethodLabel('orange_money'))->toBe(__('Orange Money'))
        ->and($this->receiptService->getPaymentMethodLabel('wallet'))->toBe(__('Wallet Balance'))
        ->and($this->receiptService->getPaymentMethodLabel(null))->toBe(__('Mobile Money'))
        ->and($this->receiptService->getPaymentMethodLabel('unknown'))->toBe(__('Mobile Money'));
});

/*
|--------------------------------------------------------------------------
| Order Ownership Tests (BR-406)
|--------------------------------------------------------------------------
*/

test('isOrderOwner returns true for matching user', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    expect($this->receiptService->isOrderOwner($order, $client->id))->toBeTrue();
});

test('isOrderOwner returns false for different user', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = createUser('client');
    $otherUser = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    expect($this->receiptService->isOrderOwner($order, $otherUser->id))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Delivery Method Label Tests
|--------------------------------------------------------------------------
*/

test('getDeliveryMethodLabel returns correct label for delivery orders', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'delivery_method' => Order::METHOD_DELIVERY,
    ]);

    $label = $this->receiptService->getDeliveryMethodLabel($order);

    expect($label)->toBe(__('Delivery'));
});

test('getDeliveryMethodLabel returns correct label for pickup orders', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'delivery_method' => Order::METHOD_PICKUP,
    ]);

    $label = $this->receiptService->getDeliveryMethodLabel($order);

    expect($label)->toBe(__('Pickup'));
});

/*
|--------------------------------------------------------------------------
| Format Amount Tests
|--------------------------------------------------------------------------
*/

test('formatAmount formats numbers with XAF currency', function () {
    expect($this->receiptService->formatAmount(6500))->toBe('6,500 XAF')
        ->and($this->receiptService->formatAmount(0))->toBe('0 XAF')
        ->and($this->receiptService->formatAmount(1000000))->toBe('1,000,000 XAF');
});

/*
|--------------------------------------------------------------------------
| Share Text Tests
|--------------------------------------------------------------------------
*/

test('getShareText generates formatted share message', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'order_number' => 'DMC-260220-0001',
        'grand_total' => 6500,
    ]);

    $shareText = $this->receiptService->getShareText($order, $tenant);

    expect($shareText)->toContain($tenant->name)
        ->and($shareText)->toContain('DMC-260220-0001')
        ->and($shareText)->toContain('6,500 XAF');
});

/*
|--------------------------------------------------------------------------
| Transaction Reference Tests
|--------------------------------------------------------------------------
*/

test('getTransactionReference prefers flutterwave_reference', function () {
    $transaction = new PaymentTransaction([
        'flutterwave_reference' => 'FLW-REF-123',
        'flutterwave_tx_ref' => 'TX-REF-456',
    ]);

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $order = Order::factory()->create([
        'client_id' => createUser('client')->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'order_number' => 'DMC-260220-0001',
    ]);

    $ref = $this->receiptService->getTransactionReference($transaction, $order);

    expect($ref)->toBe('FLW-REF-123');
});

test('getTransactionReference falls back to tx_ref', function () {
    $transaction = new PaymentTransaction([
        'flutterwave_reference' => null,
        'flutterwave_tx_ref' => 'TX-REF-456',
    ]);

    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $order = Order::factory()->create([
        'client_id' => createUser('client')->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'order_number' => 'DMC-260220-0001',
    ]);

    $ref = $this->receiptService->getTransactionReference($transaction, $order);

    expect($ref)->toBe('TX-REF-456');
});

test('getTransactionReference returns internal ref when no transaction', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $order = Order::factory()->create([
        'client_id' => createUser('client')->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'order_number' => 'DMC-260220-0099',
    ]);

    $ref = $this->receiptService->getTransactionReference(null, $order);

    expect($ref)->toBe('INT-DMC-260220-0099');
});

/*
|--------------------------------------------------------------------------
| Notification Class Tests
|--------------------------------------------------------------------------
*/

test('PaymentConfirmedNotification has correct content', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'order_number' => 'DMC-260220-0001',
        'grand_total' => 6500,
    ]);

    $notification = new PaymentConfirmedNotification($order, $tenant);

    expect($notification->getTitle($client))->toBe(__('Payment Confirmed!'))
        ->and($notification->getBody($client))->toContain('DMC-260220-0001')
        ->and($notification->getBody($client))->toContain($tenant->name)
        ->and($notification->via($client))->toContain('database')
        ->and($notification->getTag($client))->toBe('payment-'.$order->id);
});

test('NewOrderNotification has correct content', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'order_number' => 'DMC-260220-0002',
        'grand_total' => 8000,
    ]);

    $notification = new NewOrderNotification($order, $tenant);

    expect($notification->getTitle($cook))->toBe(__('New Order Received!'))
        ->and($notification->getBody($cook))->toContain('DMC-260220-0002')
        ->and($notification->getBody($cook))->toContain('8,000 XAF')
        ->and($notification->via($cook))->toContain('database')
        ->and($notification->getTag($cook))->toBe('order-'.$order->id);
});

test('PaymentConfirmedNotification toArray returns correct structure', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'order_number' => 'DMC-260220-0003',
    ]);

    $notification = new PaymentConfirmedNotification($order, $tenant);
    $array = $notification->toArray($client);

    expect($array)->toHaveKeys(['title', 'body', 'icon', 'action_url', 'data'])
        ->and($array['data']['type'])->toBe('payment_confirmed')
        ->and($array['data']['order_id'])->toBe($order->id);
});

test('NewOrderNotification toArray returns correct structure', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $notification = new NewOrderNotification($order, $tenant);
    $array = $notification->toArray($cook);

    expect($array)->toHaveKeys(['title', 'body', 'icon', 'action_url', 'data'])
        ->and($array['data']['type'])->toBe('new_order')
        ->and($array['data']['order_id'])->toBe($order->id);
});

/*
|--------------------------------------------------------------------------
| PaymentReceiptMail Tests
|--------------------------------------------------------------------------
*/

test('PaymentReceiptMail can be instantiated and built', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'order_number' => 'DMC-260220-0010',
        'status' => Order::STATUS_PAID,
        'payment_provider' => 'mtn_momo',
        'grand_total' => 5000,
        'items_snapshot' => [
            ['meal_id' => 1, 'meal_name' => 'Test Meal', 'component_id' => 1, 'component_name' => 'Main', 'quantity' => 1, 'unit_price' => 5000, 'subtotal' => 5000],
        ],
    ]);

    $transaction = PaymentTransaction::factory()->create([
        'order_id' => $order->id,
        'client_id' => $client->id,
        'cook_id' => $cook->id,
        'tenant_id' => $tenant->id,
        'status' => 'successful',
        'flutterwave_reference' => 'FLW-TEST-001',
    ]);

    $mail = new PaymentReceiptMail($order, $tenant, $transaction);
    $mail->forRecipient($client);

    // Verify the mail can build successfully
    $built = $mail->build();

    expect($built)->toBeInstanceOf(PaymentReceiptMail::class);
});

/*
|--------------------------------------------------------------------------
| Receipt Data with Multiple Items
|--------------------------------------------------------------------------
*/

test('getReceiptData correctly groups multiple meal items', function () {
    ['tenant' => $tenant, 'cook' => $cook] = createTenantWithCook();
    $client = createUser('client');

    $order = Order::factory()->create([
        'client_id' => $client->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
        'status' => Order::STATUS_PAID,
        'items_snapshot' => [
            ['meal_id' => 1, 'meal_name' => 'Ndole', 'component_id' => 1, 'component_name' => 'Plate', 'quantity' => 2, 'unit_price' => 2500, 'subtotal' => 5000],
            ['meal_id' => 1, 'meal_name' => 'Ndole', 'component_id' => 2, 'component_name' => 'Drink', 'quantity' => 1, 'unit_price' => 500, 'subtotal' => 500],
            ['meal_id' => 2, 'meal_name' => 'Eru', 'component_id' => 3, 'component_name' => 'Plate', 'quantity' => 1, 'unit_price' => 3000, 'subtotal' => 3000],
        ],
    ]);

    $data = $this->receiptService->getReceiptData($order);

    expect($data['items'])->toHaveCount(2)
        ->and($data['items'][0]['meal_name'])->toBe('Ndole')
        ->and($data['items'][0]['components'])->toHaveCount(2)
        ->and($data['items'][1]['meal_name'])->toBe('Eru')
        ->and($data['items'][1]['components'])->toHaveCount(1);
});
