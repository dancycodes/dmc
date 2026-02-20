<?php

/**
 * F-152: Payment Retry with Timeout — Unit Tests
 *
 * Tests for PaymentRetryService, Order retry methods,
 * and the CancelExpiredOrders command.
 */

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\PaymentRetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
});

// -----------------------------------------------
// Order Model Retry Methods
// -----------------------------------------------

describe('Order::canRetryPayment', function () {
    it('returns true for payment_failed order within retry window with retries remaining', function () {
        $order = Order::factory()->withRetryWindow()->create();

        expect($order->canRetryPayment())->toBeTrue();
    });

    it('returns true for pending_payment order within retry window', function () {
        $order = Order::factory()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'retry_count' => 0,
            'payment_retry_expires_at' => now()->addMinutes(10),
        ]);

        expect($order->canRetryPayment())->toBeTrue();
    });

    it('returns false when max retries exhausted', function () {
        $order = Order::factory()->retriesExhausted()->create();

        expect($order->canRetryPayment())->toBeFalse();
    });

    it('returns false when retry window expired', function () {
        $order = Order::factory()->timedOut()->create();

        expect($order->canRetryPayment())->toBeFalse();
    });

    it('returns false for paid order', function () {
        $order = Order::factory()->paid()->create();

        expect($order->canRetryPayment())->toBeFalse();
    });

    it('returns false for cancelled order', function () {
        $order = Order::factory()->cancelled()->create();

        expect($order->canRetryPayment())->toBeFalse();
    });
});

describe('Order::hasExhaustedRetries', function () {
    it('returns true when retry count equals max', function () {
        $order = Order::factory()->create(['retry_count' => Order::MAX_RETRY_ATTEMPTS]);

        expect($order->hasExhaustedRetries())->toBeTrue();
    });

    it('returns true when retry count exceeds max', function () {
        $order = Order::factory()->create(['retry_count' => Order::MAX_RETRY_ATTEMPTS + 1]);

        expect($order->hasExhaustedRetries())->toBeTrue();
    });

    it('returns false when retries remain', function () {
        $order = Order::factory()->create(['retry_count' => 1]);

        expect($order->hasExhaustedRetries())->toBeFalse();
    });

    it('returns false when no retries attempted', function () {
        $order = Order::factory()->create(['retry_count' => 0]);

        expect($order->hasExhaustedRetries())->toBeFalse();
    });
});

describe('Order::isPaymentTimedOut with retry_expires_at', function () {
    it('uses payment_retry_expires_at when set', function () {
        $order = Order::factory()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_retry_expires_at' => now()->subMinutes(1),
        ]);

        expect($order->isPaymentTimedOut())->toBeTrue();
    });

    it('is not timed out when retry_expires_at is in the future', function () {
        $order = Order::factory()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_retry_expires_at' => now()->addMinutes(10),
        ]);

        expect($order->isPaymentTimedOut())->toBeFalse();
    });

    it('falls back to created_at when retry_expires_at is null', function () {
        $order = Order::factory()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_retry_expires_at' => null,
            'created_at' => now()->subMinutes(20),
        ]);

        expect($order->isPaymentTimedOut())->toBeTrue();
    });
});

describe('Order::getPaymentTimeoutRemainingSeconds with retry_expires_at', function () {
    it('returns correct remaining seconds from retry_expires_at', function () {
        $order = Order::factory()->create([
            'payment_retry_expires_at' => now()->addMinutes(5),
        ]);

        $remaining = $order->getPaymentTimeoutRemainingSeconds();

        expect($remaining)->toBeGreaterThan(290)
            ->and($remaining)->toBeLessThanOrEqual(300);
    });

    it('returns 0 when retry_expires_at is in the past', function () {
        $order = Order::factory()->create([
            'payment_retry_expires_at' => now()->subMinutes(1),
        ]);

        expect($order->getPaymentTimeoutRemainingSeconds())->toBe(0);
    });
});

describe('Order::MAX_RETRY_ATTEMPTS', function () {
    it('is set to 3', function () {
        expect(Order::MAX_RETRY_ATTEMPTS)->toBe(3);
    });
});

// -----------------------------------------------
// PaymentRetryService
// -----------------------------------------------

describe('PaymentRetryService::getRetryData', function () {
    it('returns correct data structure for failed order with retries remaining', function () {
        $order = Order::factory()->withRetryWindow()->create();

        $service = app(PaymentRetryService::class);
        $data = $service->getRetryData($order);

        expect($data)->toHaveKeys([
            'order', 'can_retry', 'retry_count', 'max_retries',
            'remaining_seconds', 'failure_reason', 'is_expired', 'is_retries_exhausted',
        ])
            ->and($data['can_retry'])->toBeTrue()
            ->and($data['retry_count'])->toBe(1)
            ->and($data['max_retries'])->toBe(3)
            ->and($data['remaining_seconds'])->toBeGreaterThan(0)
            ->and($data['is_expired'])->toBeFalse()
            ->and($data['is_retries_exhausted'])->toBeFalse();
    });

    it('returns can_retry false for exhausted retries', function () {
        $order = Order::factory()->retriesExhausted()->create();

        $service = app(PaymentRetryService::class);
        $data = $service->getRetryData($order);

        expect($data['can_retry'])->toBeFalse()
            ->and($data['is_retries_exhausted'])->toBeTrue();
    });

    it('returns can_retry false for expired orders', function () {
        $order = Order::factory()->timedOut()->create();

        $service = app(PaymentRetryService::class);
        $data = $service->getRetryData($order);

        expect($data['can_retry'])->toBeFalse()
            ->and($data['is_expired'])->toBeTrue();
    });
});

describe('PaymentRetryService::getFailureReason', function () {
    it('returns response_message from failed transaction', function () {
        $order = Order::factory()->paymentFailed()->create();
        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'status' => 'failed',
            'response_message' => 'Insufficient funds',
        ]);

        $service = app(PaymentRetryService::class);
        $reason = $service->getFailureReason($transaction);

        expect($reason)->toBe('Insufficient funds');
    });

    it('returns generic message when response_message is generic', function () {
        $order = Order::factory()->paymentFailed()->create();
        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'status' => 'failed',
            'response_message' => 'Payment failed',
        ]);

        $service = app(PaymentRetryService::class);
        $reason = $service->getFailureReason($transaction);

        expect($reason)->toBe(__('Payment could not be completed. Please try again.'));
    });

    it('returns null for successful transaction', function () {
        $order = Order::factory()->paid()->create();
        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'status' => 'successful',
        ]);

        $service = app(PaymentRetryService::class);
        $reason = $service->getFailureReason($transaction);

        expect($reason)->toBeNull();
    });

    it('returns null for null transaction', function () {
        $service = app(PaymentRetryService::class);
        $reason = $service->getFailureReason(null);

        expect($reason)->toBeNull();
    });
});

describe('PaymentRetryService::initRetryWindow', function () {
    it('sets payment_retry_expires_at when not already set', function () {
        $order = Order::factory()->create([
            'payment_retry_expires_at' => null,
        ]);

        $service = app(PaymentRetryService::class);
        $service->initRetryWindow($order);

        $order->refresh();
        expect($order->payment_retry_expires_at)->not->toBeNull();
    });

    it('does not overwrite existing payment_retry_expires_at', function () {
        $existingExpiry = now()->addMinutes(5);
        $order = Order::factory()->create([
            'payment_retry_expires_at' => $existingExpiry,
        ]);

        $service = app(PaymentRetryService::class);
        $service->initRetryWindow($order);

        $order->refresh();
        expect($order->payment_retry_expires_at->diffInSeconds($existingExpiry))->toBeLessThan(2);
    });
});

describe('PaymentRetryService::cancelExpiredOrders', function () {
    it('cancels orders past retry window', function () {
        $expired = Order::factory()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_retry_expires_at' => now()->subMinutes(1),
        ]);

        $active = Order::factory()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_retry_expires_at' => now()->addMinutes(10),
        ]);

        $service = app(PaymentRetryService::class);
        $cancelled = $service->cancelExpiredOrders();

        expect($cancelled)->toBe(1)
            ->and($expired->fresh()->status)->toBe(Order::STATUS_CANCELLED)
            ->and($active->fresh()->status)->toBe(Order::STATUS_PENDING_PAYMENT);
    });

    it('cancels orders past created_at when no retry_expires_at set', function () {
        $expired = Order::factory()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_retry_expires_at' => null,
            'created_at' => now()->subMinutes(20),
        ]);

        $service = app(PaymentRetryService::class);
        $cancelled = $service->cancelExpiredOrders();

        expect($cancelled)->toBe(1)
            ->and($expired->fresh()->status)->toBe(Order::STATUS_CANCELLED);
    });

    it('cancels payment_failed orders that are expired', function () {
        $expired = Order::factory()->create([
            'status' => Order::STATUS_PAYMENT_FAILED,
            'payment_retry_expires_at' => now()->subMinutes(1),
        ]);

        $service = app(PaymentRetryService::class);
        $cancelled = $service->cancelExpiredOrders();

        expect($cancelled)->toBe(1);
    });

    it('does not cancel paid or cancelled orders', function () {
        Order::factory()->paid()->create();
        Order::factory()->cancelled()->create();

        $service = app(PaymentRetryService::class);
        $cancelled = $service->cancelExpiredOrders();

        expect($cancelled)->toBe(0);
    });

    it('returns 0 when no expired orders exist', function () {
        $service = app(PaymentRetryService::class);
        $cancelled = $service->cancelExpiredOrders();

        expect($cancelled)->toBe(0);
    });
});

describe('PaymentRetryService::cancelOrderAfterMaxRetries', function () {
    it('sets order status to cancelled', function () {
        $order = Order::factory()->retriesExhausted()->create();

        $service = app(PaymentRetryService::class);
        $service->cancelOrderAfterMaxRetries($order);

        $order->refresh();
        expect($order->status)->toBe(Order::STATUS_CANCELLED)
            ->and($order->cancelled_at)->not->toBeNull();
    });
});

// -----------------------------------------------
// CancelExpiredOrders Command
// -----------------------------------------------

describe('CancelExpiredOrders command', function () {
    it('cancels expired orders via artisan command', function () {
        $order = Order::factory()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_retry_expires_at' => now()->subMinutes(1),
        ]);

        $this->artisan('dancymeals:cancel-expired-orders')
            ->expectsOutput('Cancelled 1 expired order(s).')
            ->assertExitCode(0);

        expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED);
    });

    it('outputs no expired orders message when none found', function () {
        $this->artisan('dancymeals:cancel-expired-orders')
            ->expectsOutput('No expired orders found.')
            ->assertExitCode(0);
    });
});

// -----------------------------------------------
// OrderFactory States
// -----------------------------------------------

describe('OrderFactory retry states', function () {
    it('creates order with active retry window', function () {
        $order = Order::factory()->withRetryWindow()->create();

        expect($order->status)->toBe(Order::STATUS_PAYMENT_FAILED)
            ->and($order->retry_count)->toBe(1)
            ->and($order->payment_retry_expires_at)->not->toBeNull()
            ->and($order->payment_retry_expires_at->isFuture())->toBeTrue();
    });

    it('creates order with exhausted retries', function () {
        $order = Order::factory()->retriesExhausted()->create();

        expect($order->status)->toBe(Order::STATUS_PAYMENT_FAILED)
            ->and($order->retry_count)->toBe(Order::MAX_RETRY_ATTEMPTS)
            ->and($order->hasExhaustedRetries())->toBeTrue();
    });
});
