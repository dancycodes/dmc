<?php

use App\Models\CommissionChange;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\FlutterwaveService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
});

/* ─────────────────────────────────────────────────────────────────────────
 *  Order Model Tests
 * ───────────────────────────────────────────────────────────────────────── */

describe('Order Model', function () {
    test('generates unique order numbers', function () {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $number1 = Order::generateOrderNumber();

        // Create an order with the first number so the next call returns a different one
        Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'cook_id' => $user->id,
            'order_number' => $number1,
        ]);

        $number2 = Order::generateOrderNumber();

        expect($number1)->toStartWith('DMC-')
            ->and($number1)->not->toBe($number2);
    });

    test('order number format is DMC-YYMMDD-NNNN', function () {
        $number = Order::generateOrderNumber();
        $prefix = 'DMC-'.now()->format('ymd').'-';

        expect($number)->toStartWith($prefix)
            ->and(strlen($number))->toBe(strlen($prefix) + 4);
    });

    test('order number sequence increments', function () {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $firstNumber = Order::generateOrderNumber();
        Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'cook_id' => $user->id,
            'order_number' => $firstNumber,
        ]);

        $nextNumber = Order::generateOrderNumber();
        $firstSequence = (int) substr($firstNumber, -4);
        $nextSequence = (int) substr($nextNumber, -4);

        expect($nextSequence)->toBe($firstSequence + 1);
    });

    test('isPendingPayment returns true for pending payment status', function () {
        $order = Order::factory()->pendingPayment()->make();

        expect($order->isPendingPayment())->toBeTrue();
    });

    test('isPendingPayment returns false for paid status', function () {
        $order = Order::factory()->paid()->make();

        expect($order->isPendingPayment())->toBeFalse();
    });

    test('isPaymentTimedOut returns true after 15 minutes', function () {
        $order = Order::factory()->timedOut()->make();

        expect($order->isPaymentTimedOut())->toBeTrue();
    });

    test('isPaymentTimedOut returns false within 15 minutes', function () {
        $order = Order::factory()->pendingPayment()->make([
            'created_at' => now()->subMinutes(5),
        ]);

        expect($order->isPaymentTimedOut())->toBeFalse();
    });

    test('getPaymentTimeoutRemainingSeconds returns correct value', function () {
        $order = Order::factory()->pendingPayment()->make([
            'created_at' => now()->subMinutes(10),
        ]);

        $remaining = $order->getPaymentTimeoutRemainingSeconds();

        // Should be approximately 5 minutes (300 seconds)
        expect($remaining)->toBeGreaterThan(295)
            ->and($remaining)->toBeLessThan(305);
    });

    test('getPaymentTimeoutRemainingSeconds returns 0 when expired', function () {
        $order = Order::factory()->timedOut()->make();

        expect($order->getPaymentTimeoutRemainingSeconds())->toBe(0);
    });

    test('formattedGrandTotal formats with thousands separator', function () {
        $order = Order::factory()->make(['grand_total' => 15000]);

        expect($order->formattedGrandTotal())->toBe('15,000 XAF');
    });

    test('order has required status constants', function () {
        expect(Order::STATUS_PENDING_PAYMENT)->toBe('pending_payment')
            ->and(Order::STATUS_PAID)->toBe('paid')
            ->and(Order::STATUS_CONFIRMED)->toBe('confirmed')
            ->and(Order::STATUS_CANCELLED)->toBe('cancelled')
            ->and(Order::STATUS_PAYMENT_FAILED)->toBe('payment_failed')
            ->and(Order::PAYMENT_TIMEOUT_MINUTES)->toBe(15);
    });

    test('order factory creates valid order', function () {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'cook_id' => $user->id,
        ]);

        expect($order)->toBeInstanceOf(Order::class)
            ->and($order->status)->toBe(Order::STATUS_PENDING_PAYMENT)
            ->and($order->items_snapshot)->toBeArray();
    });

    test('order has payment transactions relationship', function () {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'cook_id' => $user->id,
        ]);

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'client_id' => $user->id,
            'cook_id' => $user->id,
            'tenant_id' => $tenant->id,
        ]);

        expect($order->paymentTransactions)->toHaveCount(1);
    });

    test('items_snapshot is cast to array', function () {
        $order = Order::factory()->make();

        expect($order->items_snapshot)->toBeArray();
    });
});

/* ─────────────────────────────────────────────────────────────────────────
 *  FlutterwaveService Tests
 * ───────────────────────────────────────────────────────────────────────── */

describe('FlutterwaveService', function () {
    test('generateTxRef creates unique reference with order ID', function () {
        $service = new FlutterwaveService;

        $ref = $service->generateTxRef(42);

        expect($ref)->toStartWith('DMC-TX-42-')
            ->and(strlen($ref))->toBeGreaterThan(15);
    });

    test('generateTxRef creates different references for different orders', function () {
        $service = new FlutterwaveService;

        $ref1 = $service->generateTxRef(1);
        $ref2 = $service->generateTxRef(2);

        expect($ref1)->not->toBe($ref2);
    });

    test('initiateCharge returns success on successful API response', function () {
        Http::fake([
            'api.flutterwave.com/*' => Http::response([
                'status' => 'success',
                'message' => 'Charge initiated',
                'data' => [
                    'id' => 123456,
                    'tx_ref' => 'DMC-TX-1-1234567890-ABC123',
                    'flw_ref' => 'FLW-MOCK-12345',
                    'charge_response_message' => 'Pending Payment Validation',
                ],
            ], 200),
        ]);

        $service = new FlutterwaveService;

        $result = $service->initiateCharge([
            'amount' => 5000,
            'currency' => 'XAF',
            'phone' => '+237612345678',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'tx_ref' => 'DMC-TX-1-1234567890-ABC123',
            'callback_url' => 'https://example.com/callback',
            'subaccount_id' => null,
            'commission_rate' => 10,
        ]);

        expect($result['success'])->toBeTrue()
            ->and($result['data'])->toBeArray()
            ->and($result['data']['flw_ref'])->toBe('FLW-MOCK-12345')
            ->and($result['error'])->toBeNull();
    });

    test('initiateCharge returns error on failed API response', function () {
        Http::fake([
            'api.flutterwave.com/*' => Http::response([
                'status' => 'error',
                'message' => 'Invalid phone number',
            ], 400),
        ]);

        $service = new FlutterwaveService;

        $result = $service->initiateCharge([
            'amount' => 5000,
            'currency' => 'XAF',
            'phone' => 'invalid',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'tx_ref' => 'DMC-TX-1-12345-ABC',
            'callback_url' => null,
            'subaccount_id' => null,
            'commission_rate' => 10,
        ]);

        expect($result['success'])->toBeFalse()
            ->and($result['error'])->not->toBeNull();
    });

    test('initiateCharge handles connection errors gracefully', function () {
        Http::fake([
            'api.flutterwave.com/*' => Http::response(null, 500),
        ]);

        $service = new FlutterwaveService;

        $result = $service->initiateCharge([
            'amount' => 5000,
            'currency' => 'XAF',
            'phone' => '+237612345678',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'tx_ref' => 'DMC-TX-1-12345-ABC',
            'callback_url' => null,
            'subaccount_id' => null,
            'commission_rate' => 10,
        ]);

        expect($result['success'])->toBeFalse()
            ->and($result['error'])->not->toBeNull();
    });

    test('initiateCharge includes split payment when subaccount provided', function () {
        Http::fake([
            'api.flutterwave.com/*' => Http::response([
                'status' => 'success',
                'data' => ['flw_ref' => 'FLW-123'],
            ], 200),
        ]);

        $service = new FlutterwaveService;

        $service->initiateCharge([
            'amount' => 10000,
            'currency' => 'XAF',
            'phone' => '+237612345678',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'tx_ref' => 'DMC-TX-1-12345-ABC',
            'callback_url' => null,
            'subaccount_id' => 'RS_SUBACCOUNT_123',
            'commission_rate' => 15,
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return isset($body['subaccounts'])
                && $body['subaccounts'][0]['id'] === 'RS_SUBACCOUNT_123'
                && $body['subaccounts'][0]['transaction_charge'] === 15;
        });
    });

    test('initiateCharge does not include split when no subaccount', function () {
        Http::fake([
            'api.flutterwave.com/*' => Http::response([
                'status' => 'success',
                'data' => ['flw_ref' => 'FLW-123'],
            ], 200),
        ]);

        $service = new FlutterwaveService;

        $service->initiateCharge([
            'amount' => 5000,
            'currency' => 'XAF',
            'phone' => '+237612345678',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'tx_ref' => 'DMC-TX-1-12345-ABC',
            'callback_url' => null,
            'subaccount_id' => null,
            'commission_rate' => 10,
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ! isset($body['subaccounts']);
        });
    });

    test('verifyTransaction returns success for successful verification', function () {
        Http::fake([
            'api.flutterwave.com/v3/transactions/*/verify' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 123456,
                    'status' => 'successful',
                    'amount' => 5000,
                ],
            ], 200),
        ]);

        $service = new FlutterwaveService;

        $result = $service->verifyTransaction('123456');

        expect($result['success'])->toBeTrue()
            ->and($result['data']['status'])->toBe('successful');
    });
});

/* ─────────────────────────────────────────────────────────────────────────
 *  PaymentService Tests
 * ───────────────────────────────────────────────────────────────────────── */

describe('PaymentService', function () {
    test('createOrder creates order with correct data from checkout session', function () {
        $cook = test()->createUserWithRole('cook');
        $tenant = Tenant::factory()->withCook($cook->id)->create();
        $user = test()->createUserWithRole('client');

        // Create a real pickup location to avoid FK constraint violation
        $pickupLocation = \App\Models\PickupLocation::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        // Setup checkout session
        $checkoutService = app(CheckoutService::class);
        $checkoutService->setDeliveryMethod($tenant->id, 'pickup');
        $checkoutService->setPickupLocation($tenant->id, $pickupLocation->id);
        $checkoutService->setPhone($tenant->id, '+237612345678');
        $checkoutService->setPaymentMethod($tenant->id, 'mtn_momo', '+237612345678');

        // Setup cart (session stores flat items array keyed by composite ID)
        session(['dmc-cart-'.$tenant->id => [
            '1-1' => [
                'meal_id' => 1,
                'meal_name' => 'Jollof Rice',
                'component_id' => 1,
                'component_name' => 'Standard',
                'quantity' => 2,
                'unit_price' => 1500,
            ],
        ]]);

        $paymentService = app(PaymentService::class);
        $result = $paymentService->createOrder($tenant, $user);

        expect($result['success'])->toBeTrue()
            ->and($result['order'])->toBeInstanceOf(Order::class)
            ->and($result['order']->status)->toBe(Order::STATUS_PENDING_PAYMENT)
            ->and($result['order']->client_id)->toBe($user->id)
            ->and($result['order']->tenant_id)->toBe($tenant->id)
            ->and($result['order']->delivery_method)->toBe('pickup')
            ->and($result['order']->phone)->toBe('+237612345678')
            ->and($result['order']->payment_provider)->toBe('mtn_momo')
            ->and($result['order']->order_number)->toStartWith('DMC-');
    });

    test('createOrder fails when delivery method missing', function () {
        $tenant = Tenant::factory()->create();
        $user = test()->createUserWithRole('client');

        $paymentService = app(PaymentService::class);
        $result = $paymentService->createOrder($tenant, $user);

        expect($result['success'])->toBeFalse()
            ->and($result['error'])->not->toBeNull();
    });

    test('initiatePayment creates transaction and calls Flutterwave', function () {
        Http::fake([
            'api.flutterwave.com/*' => Http::response([
                'status' => 'success',
                'data' => [
                    'flw_ref' => 'FLW-MOCK-99999',
                    'charge_response_message' => 'Pending',
                ],
            ], 200),
        ]);

        $tenant = Tenant::factory()->create();
        $user = test()->createUserWithRole('client');

        $order = Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'cook_id' => $tenant->cook_id,
            'grand_total' => 5000,
            'payment_provider' => 'mtn_momo',
            'payment_phone' => '+237612345678',
        ]);

        $paymentService = app(PaymentService::class);
        $result = $paymentService->initiatePayment($order, $user);

        expect($result['success'])->toBeTrue()
            ->and($result['transaction'])->toBeInstanceOf(PaymentTransaction::class)
            ->and($result['transaction']->flutterwave_reference)->toBe('FLW-MOCK-99999')
            ->and($result['transaction']->status)->toBe('pending');

        Http::assertSentCount(1);
    });

    test('initiatePayment marks transaction as failed on Flutterwave error', function () {
        Http::fake([
            'api.flutterwave.com/*' => Http::response([
                'status' => 'error',
                'message' => 'Invalid phone number',
            ], 400),
        ]);

        $tenant = Tenant::factory()->create();
        $user = test()->createUserWithRole('client');

        $order = Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'cook_id' => $tenant->cook_id,
            'grand_total' => 5000,
            'payment_provider' => 'mtn_momo',
            'payment_phone' => '+237612345678',
        ]);

        $paymentService = app(PaymentService::class);
        $result = $paymentService->initiatePayment($order, $user);

        expect($result['success'])->toBeFalse()
            ->and($result['error'])->not->toBeNull()
            ->and($result['transaction']->status)->toBe('failed');
    });

    test('checkPaymentStatus detects timeout after 15 minutes', function () {
        $tenant = Tenant::factory()->create();
        $user = test()->createUserWithRole('client');

        $order = Order::factory()->timedOut()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'cook_id' => $tenant->cook_id,
        ]);

        PaymentTransaction::factory()->pending()->create([
            'order_id' => $order->id,
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'cook_id' => $tenant->cook_id,
        ]);

        $paymentService = app(PaymentService::class);
        $status = $paymentService->checkPaymentStatus($order);

        expect($status['is_timed_out'])->toBeTrue()
            ->and($status['status'])->toBe('timed_out');

        // Verify order status was updated
        $order->refresh();
        expect($order->status)->toBe(Order::STATUS_PAYMENT_FAILED);
    });

    test('checkPaymentStatus detects successful payment', function () {
        $tenant = Tenant::factory()->create();
        $user = test()->createUserWithRole('client');

        $order = Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'cook_id' => $tenant->cook_id,
        ]);

        PaymentTransaction::factory()->successful()->create([
            'order_id' => $order->id,
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'cook_id' => $tenant->cook_id,
        ]);

        $paymentService = app(PaymentService::class);
        $status = $paymentService->checkPaymentStatus($order);

        expect($status['is_complete'])->toBeTrue()
            ->and($status['status'])->toBe('successful');
    });

    test('checkPaymentStatus returns pending for active payment', function () {
        $tenant = Tenant::factory()->create();
        $user = test()->createUserWithRole('client');

        $order = Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'cook_id' => $tenant->cook_id,
            'created_at' => now()->subMinutes(3),
        ]);

        PaymentTransaction::factory()->pending()->create([
            'order_id' => $order->id,
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'cook_id' => $tenant->cook_id,
        ]);

        $paymentService = app(PaymentService::class);
        $status = $paymentService->checkPaymentStatus($order);

        expect($status['status'])->toBe('pending')
            ->and($status['is_complete'])->toBeFalse()
            ->and($status['is_failed'])->toBeFalse()
            ->and($status['is_timed_out'])->toBeFalse();
    });
});

/* ─────────────────────────────────────────────────────────────────────────
 *  PaymentTransaction Relationship Tests
 * ───────────────────────────────────────────────────────────────────────── */

describe('PaymentTransaction Order Relationship', function () {
    test('payment transaction belongs to order', function () {
        $tenant = Tenant::factory()->create();
        $user = test()->createUserWithRole('client');

        $order = Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'cook_id' => $user->id,
        ]);

        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
        ]);

        expect($transaction->order)->toBeInstanceOf(Order::class)
            ->and($transaction->order->id)->toBe($order->id);
    });
});

/* ─────────────────────────────────────────────────────────────────────────
 *  Commission Rate Tests
 * ───────────────────────────────────────────────────────────────────────── */

describe('Commission Rate for Split Payment', function () {
    test('tenant returns default commission rate when not customized', function () {
        $tenant = Tenant::factory()->create();

        expect($tenant->getCommissionRate())->toBe(CommissionChange::DEFAULT_RATE);
    });

    test('tenant returns custom commission rate when set', function () {
        $tenant = Tenant::factory()->create([
            'settings' => ['commission_rate' => 15.0],
        ]);

        expect($tenant->getCommissionRate())->toBe(15.0);
    });
});
