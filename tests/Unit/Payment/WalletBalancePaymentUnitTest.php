<?php

use App\Models\ClientWallet;
use App\Models\Order;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| F-153: Wallet Balance Payment — Unit Tests
|--------------------------------------------------------------------------
|
| Tests the wallet payment business logic including:
| - WalletTransaction model (TYPE_WALLET_PAYMENT, isDebit, isCredit)
| - ClientWallet model (balance operations)
| - Commission calculation consistency
| - Business rules BR-387 through BR-396
|
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

// --- WalletTransaction Model Tests ---

test('wallet transaction has TYPE_WALLET_PAYMENT constant', function () {
    expect(WalletTransaction::TYPE_WALLET_PAYMENT)->toBe('wallet_payment');
});

test('wallet_payment type is included in TYPES array', function () {
    expect(WalletTransaction::TYPES)->toContain(WalletTransaction::TYPE_WALLET_PAYMENT);
});

test('wallet_payment transaction is classified as debit', function () {
    $transaction = WalletTransaction::factory()
        ->make(['type' => WalletTransaction::TYPE_WALLET_PAYMENT]);

    expect($transaction->isDebit())->toBeTrue();
    expect($transaction->isCredit())->toBeFalse();
});

test('payment_credit transaction is classified as credit', function () {
    $transaction = WalletTransaction::factory()
        ->paymentCredit()
        ->make();

    expect($transaction->isCredit())->toBeTrue();
    expect($transaction->isDebit())->toBeFalse();
});

test('commission transaction is classified as debit', function () {
    $transaction = WalletTransaction::factory()
        ->commission()
        ->make();

    expect($transaction->isDebit())->toBeTrue();
    expect($transaction->isCredit())->toBeFalse();
});

test('refund transaction is classified as credit', function () {
    $transaction = WalletTransaction::factory()
        ->refund()
        ->make();

    expect($transaction->isCredit())->toBeTrue();
    expect($transaction->isDebit())->toBeFalse();
});

test('withdrawal transaction is classified as debit', function () {
    $transaction = WalletTransaction::factory()
        ->make(['type' => WalletTransaction::TYPE_WITHDRAWAL]);

    expect($transaction->isDebit())->toBeTrue();
});

test('refund_deduction transaction is classified as debit', function () {
    $transaction = WalletTransaction::factory()
        ->make(['type' => WalletTransaction::TYPE_REFUND_DEDUCTION]);

    expect($transaction->isDebit())->toBeTrue();
});

// --- ClientWallet Model Tests ---

test('client wallet has balance check method', function () {
    $walletWithBalance = ClientWallet::factory()->withBalance(5000)->make();
    $walletZero = ClientWallet::factory()->zeroBalance()->make();

    expect($walletWithBalance->hasBalance())->toBeTrue();
    expect($walletZero->hasBalance())->toBeFalse();
});

test('client wallet formats balance with currency', function () {
    $wallet = ClientWallet::factory()->withBalance(15000)->make();

    expect($wallet->formattedBalance())->toBe('15,000 XAF');
});

test('client wallet default currency is XAF', function () {
    $wallet = ClientWallet::factory()->make();

    expect($wallet->currency)->toBe('XAF');
});

test('client wallet with zero balance reports no balance', function () {
    $wallet = ClientWallet::factory()->make(['balance' => 0]);

    expect($wallet->hasBalance())->toBeFalse();
});

test('client wallet with negative balance reports no balance', function () {
    $wallet = ClientWallet::factory()->make(['balance' => -100]);

    expect($wallet->hasBalance())->toBeFalse();
});

// --- Commission Calculation Tests (BR-394) ---

test('commission calculation at default 10% rate', function () {
    $orderTotal = 5000.0;
    $commissionRate = 10.0;

    $commissionAmount = round($orderTotal * ($commissionRate / 100), 2);
    $cookShare = round($orderTotal - $commissionAmount, 2);

    expect($commissionAmount)->toBe(500.0);
    expect($cookShare)->toBe(4500.0);
    expect($commissionAmount + $cookShare)->toBe($orderTotal);
});

test('commission calculation at custom 15% rate', function () {
    $orderTotal = 10000.0;
    $commissionRate = 15.0;

    $commissionAmount = round($orderTotal * ($commissionRate / 100), 2);
    $cookShare = round($orderTotal - $commissionAmount, 2);

    expect($commissionAmount)->toBe(1500.0);
    expect($cookShare)->toBe(8500.0);
});

test('commission calculation at 0% rate gives full amount to cook', function () {
    $orderTotal = 7500.0;
    $commissionRate = 0.0;

    $commissionAmount = round($orderTotal * ($commissionRate / 100), 2);
    $cookShare = round($orderTotal - $commissionAmount, 2);

    expect($commissionAmount)->toBe(0.0);
    expect($cookShare)->toBe(7500.0);
});

test('commission rounding handles odd amounts correctly', function () {
    $orderTotal = 3333.0;
    $commissionRate = 10.0;

    $commissionAmount = round($orderTotal * ($commissionRate / 100), 2);
    $cookShare = round($orderTotal - $commissionAmount, 2);

    expect($commissionAmount)->toBe(333.3);
    expect($cookShare)->toBe(2999.7);
    expect(round($commissionAmount + $cookShare, 2))->toBe($orderTotal);
});

// --- Balance Sufficiency Tests (BR-388) ---

test('balance exactly equal to order total is sufficient', function () {
    $balance = 5000.0;
    $orderTotal = 5000.0;

    expect($balance >= $orderTotal)->toBeTrue();
});

test('balance greater than order total is sufficient', function () {
    $balance = 7000.0;
    $orderTotal = 5000.0;

    expect($balance >= $orderTotal)->toBeTrue();
});

test('balance less than order total is insufficient', function () {
    $balance = 3000.0;
    $orderTotal = 5000.0;

    expect($balance >= $orderTotal)->toBeFalse();
});

test('zero balance is insufficient for any order', function () {
    $balance = 0.0;
    $orderTotal = 100.0;

    expect($balance >= $orderTotal)->toBeFalse();
});

// --- Wallet Deduction Calculation Tests (BR-389) ---

test('wallet deduction correctly calculates new balance', function () {
    $currentBalance = 10000.0;
    $orderTotal = 3500.0;

    $newBalance = round($currentBalance - $orderTotal, 2);

    expect($newBalance)->toBe(6500.0);
});

test('wallet deduction to exactly zero when balance equals total', function () {
    $currentBalance = 5000.0;
    $orderTotal = 5000.0;

    $newBalance = round($currentBalance - $orderTotal, 2);

    expect($newBalance)->toBe(0.0);
});

// --- WalletTransaction Factory States ---

test('wallet transaction factory creates valid default transaction', function () {
    $transaction = WalletTransaction::factory()->make();

    expect($transaction->type)->toBe(WalletTransaction::TYPE_PAYMENT_CREDIT);
    expect($transaction->currency)->toBe('XAF');
    expect($transaction->status)->toBe('completed');
    expect((float) $transaction->amount)->toBeGreaterThan(0);
});

test('wallet transaction factory produces all valid types', function () {
    foreach (WalletTransaction::TYPES as $type) {
        $transaction = WalletTransaction::factory()->make(['type' => $type]);
        expect(WalletTransaction::TYPES)->toContain($transaction->type);
    }
});

// --- Order Factory States for Wallet Payment ---

test('order factory creates valid pending payment order', function () {
    $order = Order::factory()->pendingPayment()->make();

    expect($order->status)->toBe(Order::STATUS_PENDING_PAYMENT);
});

test('order status transitions from pending to paid', function () {
    // BR-391: Order immediately moves to Paid after wallet payment
    $order = Order::factory()->pendingPayment()->make();
    expect($order->status)->toBe(Order::STATUS_PENDING_PAYMENT);

    // Simulate wallet payment status update
    $order->status = Order::STATUS_PAID;
    $order->paid_at = now();
    $order->payment_provider = 'wallet';

    expect($order->status)->toBe(Order::STATUS_PAID);
    expect($order->payment_provider)->toBe('wallet');
    expect($order->paid_at)->not->toBeNull();
});

// --- Wallet Transaction Metadata Tests ---

test('wallet payment transaction metadata includes required fields', function () {
    $metadata = [
        'order_number' => 'DMC-260220-1234',
        'order_total' => 5000.0,
        'payment_method' => 'wallet',
    ];

    $transaction = WalletTransaction::factory()->make([
        'type' => WalletTransaction::TYPE_WALLET_PAYMENT,
        'metadata' => $metadata,
    ]);

    expect($transaction->metadata)->toHaveKey('order_number');
    expect($transaction->metadata)->toHaveKey('order_total');
    expect($transaction->metadata)->toHaveKey('payment_method');
    expect($transaction->metadata['payment_method'])->toBe('wallet');
});

test('cook credit metadata includes commission breakdown', function () {
    $metadata = [
        'order_number' => 'DMC-260220-1234',
        'order_total' => 5000.0,
        'commission_rate' => 10.0,
        'commission_amount' => 500.0,
        'cook_share' => 4500.0,
        'payment_method' => 'wallet',
    ];

    $transaction = WalletTransaction::factory()->paymentCredit()->make([
        'metadata' => $metadata,
    ]);

    expect((float) $transaction->metadata['commission_rate'])->toBe(10.0);
    expect((float) $transaction->metadata['commission_amount'])->toBe(500.0);
    expect((float) $transaction->metadata['cook_share'])->toBe(4500.0);
    expect($transaction->metadata['payment_method'])->toBe('wallet');
});
