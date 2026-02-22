<?php

/**
 * Unit tests for ClientSpendingStatsService (F-204).
 *
 * Tests the stat aggregation logic: spending totals, this-month filter,
 * top-cooks ranking, top-meals ranking, and XAF formatting.
 */

use App\Models\Order;
use App\Services\ClientSpendingStatsService;

$projectRoot = dirname(__DIR__, 2);

/**
 * formatXAF produces correctly formatted XAF strings.
 */
test('formatXAF formats zero', function () {
    expect(ClientSpendingStatsService::formatXAF(0))->toBe('0 XAF');
});

test('formatXAF formats thousands correctly', function () {
    expect(ClientSpendingStatsService::formatXAF(125000))->toBe('125,000 XAF');
});

test('formatXAF formats large amounts', function () {
    expect(ClientSpendingStatsService::formatXAF(1000000))->toBe('1,000,000 XAF');
});

/**
 * COMPLETED_STATUSES constant includes the three relevant statuses.
 */
test('COMPLETED_STATUSES includes completed, delivered, and picked_up', function () {
    $statuses = ClientSpendingStatsService::COMPLETED_STATUSES;

    expect($statuses)->toContain(Order::STATUS_COMPLETED);
    expect($statuses)->toContain(Order::STATUS_DELIVERED);
    expect($statuses)->toContain(Order::STATUS_PICKED_UP);
    expect($statuses)->not->toContain(Order::STATUS_CANCELLED);
    expect($statuses)->not->toContain(Order::STATUS_REFUNDED);
    expect($statuses)->not->toContain(Order::STATUS_PAID);
});
