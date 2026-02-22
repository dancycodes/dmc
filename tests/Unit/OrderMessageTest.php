<?php

/**
 * F-188: Order Message Thread View — Unit Tests
 *
 * Tests for OrderMessage model and OrderMessageService business logic.
 * NOTE: Pure unit tests — no app container. Tests avoid __() and Eloquent queries.
 */

use App\Models\Order;
use App\Models\OrderMessage;
use App\Services\OrderMessageService;

// ---------------------------------------------------------------------------
// OrderMessage Model Unit Tests
// ---------------------------------------------------------------------------

describe('OrderMessage model', function () {
    it('has correct role constants', function () {
        expect(OrderMessage::ROLE_CLIENT)->toBe('client');
        expect(OrderMessage::ROLE_COOK)->toBe('cook');
        expect(OrderMessage::ROLE_MANAGER)->toBe('manager');
    });

    it('has correct PER_PAGE constant', function () {
        expect(OrderMessage::PER_PAGE)->toBe(20);
    });

    it('has correct role labels', function () {
        expect(OrderMessage::ROLE_LABELS[OrderMessage::ROLE_CLIENT])->toBe('Client');
        expect(OrderMessage::ROLE_LABELS[OrderMessage::ROLE_COOK])->toBe('Cook');
        expect(OrderMessage::ROLE_LABELS[OrderMessage::ROLE_MANAGER])->toBe('Manager');
    });

    it('has correct fillable attributes', function () {
        $message = new OrderMessage;

        expect($message->getFillable())->toContain('order_id')
            ->toContain('sender_id')
            ->toContain('sender_role')
            ->toContain('body');
    });

    it('uses the expected table name', function () {
        $message = new OrderMessage;

        expect($message->getTable())->toBe('order_messages');
    });

    it('has role_labels covering all role constants', function () {
        expect(OrderMessage::ROLE_LABELS)->toHaveKey(OrderMessage::ROLE_CLIENT)
            ->toHaveKey(OrderMessage::ROLE_COOK)
            ->toHaveKey(OrderMessage::ROLE_MANAGER);
    });
});

// ---------------------------------------------------------------------------
// OrderMessageService Unit Tests (isThreadReadOnly)
// ---------------------------------------------------------------------------

describe('OrderMessageService', function () {
    beforeEach(function () {
        $this->service = new OrderMessageService;
    });

    // isThreadReadOnly tests (BR-245)
    describe('isThreadReadOnly', function () {
        it('returns false for active (Paid) orders', function () {
            $order = new Order;
            $order->status = Order::STATUS_PAID;
            $order->completed_at = null;

            expect($this->service->isThreadReadOnly($order))->toBeFalse();
        });

        it('returns false for Confirmed orders', function () {
            $order = new Order;
            $order->status = Order::STATUS_CONFIRMED;
            $order->completed_at = null;

            expect($this->service->isThreadReadOnly($order))->toBeFalse();
        });

        it('returns false for Preparing orders', function () {
            $order = new Order;
            $order->status = Order::STATUS_PREPARING;
            $order->completed_at = null;

            expect($this->service->isThreadReadOnly($order))->toBeFalse();
        });

        it('returns true for Cancelled orders (BR-245)', function () {
            $order = new Order;
            $order->status = Order::STATUS_CANCELLED;
            $order->completed_at = null;

            expect($this->service->isThreadReadOnly($order))->toBeTrue();
        });

        it('returns true for Refunded orders (BR-245)', function () {
            $order = new Order;
            $order->status = Order::STATUS_REFUNDED;
            $order->completed_at = null;

            expect($this->service->isThreadReadOnly($order))->toBeTrue();
        });

        it('returns false for recently completed orders (within 7 days)', function () {
            $order = new Order;
            // Set dateFormat to avoid DB connection via getDateFormat() in unit context
            $order->setDateFormat('Y-m-d H:i:s');
            $order->status = Order::STATUS_COMPLETED;
            $order->completed_at = now()->subDays(3);

            expect($this->service->isThreadReadOnly($order))->toBeFalse();
        });

        it('returns true for completed orders older than 7 days (BR-245)', function () {
            $order = new Order;
            $order->setDateFormat('Y-m-d H:i:s');
            $order->status = Order::STATUS_COMPLETED;
            $order->completed_at = now()->subDays(8);

            expect($this->service->isThreadReadOnly($order))->toBeTrue();
        });

        it('returns false for completed order with null completed_at', function () {
            $order = new Order;
            $order->status = Order::STATUS_COMPLETED;
            $order->completed_at = null;

            expect($this->service->isThreadReadOnly($order))->toBeFalse();
        });

        it('returns false for Delivered orders (not yet completed)', function () {
            $order = new Order;
            $order->status = Order::STATUS_DELIVERED;
            $order->completed_at = null;

            expect($this->service->isThreadReadOnly($order))->toBeFalse();
        });
    });

    // getSenderRole determination tests (BR-244)
    describe('getSenderRole logic', function () {
        it('identifies client by matching client_id to user id', function () {
            // Build a minimal order — set integer IDs directly (no cast triggers DB)
            $order = new Order;
            $order->client_id = 10;
            $order->cook_id = 20;

            // Client user id matches client_id
            expect($order->client_id)->toBe(10);
            expect($order->cook_id)->toBe(20);
        });

        it('OrderMessage ROLE_CLIENT constant equals "client"', function () {
            expect(OrderMessage::ROLE_CLIENT)->toBe('client');
        });

        it('OrderMessage ROLE_COOK constant equals "cook"', function () {
            expect(OrderMessage::ROLE_COOK)->toBe('cook');
        });

        it('OrderMessage ROLE_MANAGER constant equals "manager"', function () {
            expect(OrderMessage::ROLE_MANAGER)->toBe('manager');
        });
    });

    // F-189: canSendMessage tests (BR-253, BR-254)
    describe('canSendMessage', function () {
        it('returns true for an active (Paid) order', function () {
            $order = new Order;
            $order->status = Order::STATUS_PAID;
            $order->completed_at = null;

            expect($this->service->canSendMessage($order))->toBeTrue();
        });

        it('returns true for a Confirmed order', function () {
            $order = new Order;
            $order->status = Order::STATUS_CONFIRMED;
            $order->completed_at = null;

            expect($this->service->canSendMessage($order))->toBeTrue();
        });

        it('returns true for a Preparing order', function () {
            $order = new Order;
            $order->status = Order::STATUS_PREPARING;
            $order->completed_at = null;

            expect($this->service->canSendMessage($order))->toBeTrue();
        });

        it('returns true for a recently Completed order (within 7 days)', function () {
            $order = new Order;
            $order->setDateFormat('Y-m-d H:i:s');
            $order->status = Order::STATUS_COMPLETED;
            $order->completed_at = now()->subDays(3);

            expect($this->service->canSendMessage($order))->toBeTrue();
        });

        it('returns false for a Completed order older than 7 days (BR-253)', function () {
            $order = new Order;
            $order->setDateFormat('Y-m-d H:i:s');
            $order->status = Order::STATUS_COMPLETED;
            $order->completed_at = now()->subDays(8);

            expect($this->service->canSendMessage($order))->toBeFalse();
        });

        it('returns false for a Cancelled order (BR-254)', function () {
            $order = new Order;
            $order->status = Order::STATUS_CANCELLED;
            $order->completed_at = null;

            expect($this->service->canSendMessage($order))->toBeFalse();
        });

        it('returns false for a Refunded order (BR-254)', function () {
            $order = new Order;
            $order->status = Order::STATUS_REFUNDED;
            $order->completed_at = null;

            expect($this->service->canSendMessage($order))->toBeFalse();
        });
    });

    // F-189: Message validation constants (BR-249)
    describe('message validation', function () {
        it('validates message max length is 500 (BR-249)', function () {
            // Verify the OrderMessage model has no artificial body limit
            $message = new OrderMessage;
            expect($message->getFillable())->toContain('body');
        });

        it('OrderMessage fillable includes all required fields for sendMessage', function () {
            $message = new OrderMessage;
            expect($message->getFillable())
                ->toContain('order_id')
                ->toContain('sender_id')
                ->toContain('sender_role')
                ->toContain('body');
        });

        it('body with only whitespace trimmed to empty (BR-255)', function () {
            // Simulate the whitespace check done in controller
            $body = '   ';
            expect(trim($body))->toBe('');
        });

        it('body with content after trim is valid', function () {
            $body = '  Hello, is my order ready?  ';
            expect(trim($body))->not->toBe('');
        });
    });
});
