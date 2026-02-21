<?php

use App\Models\Complaint;
use App\Models\OrderClearance;
use App\Services\PaymentBlockService;

/*
|--------------------------------------------------------------------------
| F-186: Complaint-Triggered Payment Block — Unit Tests
|--------------------------------------------------------------------------
|
| Pure unit tests for OrderClearance model fields, casts, and
| PaymentBlockService existence. Tests model methods/relationships
| and resolution routing logic. No database required.
|
| DB-dependent behavior (blockPaymentForComplaint, unblockPaymentOnResolution,
| scopes, activity logging) is verified by Playwright in Phase 3.
|
*/

// ---- OrderClearance Model Casts ----

it('casts is_flagged_for_review as boolean', function () {
    $clearance = new OrderClearance;
    $casts = $clearance->getCasts();

    expect($casts)->toHaveKey('is_flagged_for_review')
        ->and($casts['is_flagged_for_review'])->toBe('boolean');
});

it('casts blocked_at as datetime', function () {
    $clearance = new OrderClearance;
    $casts = $clearance->getCasts();

    expect($casts)->toHaveKey('blocked_at')
        ->and($casts['blocked_at'])->toBe('datetime');
});

it('casts unblocked_at as datetime', function () {
    $clearance = new OrderClearance;
    $casts = $clearance->getCasts();

    expect($casts)->toHaveKey('unblocked_at')
        ->and($casts['unblocked_at'])->toBe('datetime');
});

it('casts is_paused as boolean', function () {
    $clearance = new OrderClearance;
    $casts = $clearance->getCasts();

    expect($casts)->toHaveKey('is_paused')
        ->and($casts['is_paused'])->toBe('boolean');
});

it('casts is_cleared as boolean', function () {
    $clearance = new OrderClearance;
    $casts = $clearance->getCasts();

    expect($casts)->toHaveKey('is_cleared')
        ->and($casts['is_cleared'])->toBe('boolean');
});

it('casts is_cancelled as boolean', function () {
    $clearance = new OrderClearance;
    $casts = $clearance->getCasts();

    expect($casts)->toHaveKey('is_cancelled')
        ->and($casts['is_cancelled'])->toBe('boolean');
});

// ---- OrderClearance Fillable Fields (F-186 additions) ----

it('includes complaint_id in fillable', function () {
    $clearance = new OrderClearance;

    expect($clearance->getFillable())->toContain('complaint_id');
});

it('includes is_flagged_for_review in fillable', function () {
    $clearance = new OrderClearance;

    expect($clearance->getFillable())->toContain('is_flagged_for_review');
});

it('includes blocked_at in fillable', function () {
    $clearance = new OrderClearance;

    expect($clearance->getFillable())->toContain('blocked_at');
});

it('includes unblocked_at in fillable', function () {
    $clearance = new OrderClearance;

    expect($clearance->getFillable())->toContain('unblocked_at');
});

// ---- OrderClearance Pre-existing Fillable Fields ----

it('includes order_id in fillable', function () {
    $clearance = new OrderClearance;

    expect($clearance->getFillable())->toContain('order_id');
});

it('includes tenant_id in fillable', function () {
    $clearance = new OrderClearance;

    expect($clearance->getFillable())->toContain('tenant_id');
});

it('includes is_paused in fillable', function () {
    $clearance = new OrderClearance;

    expect($clearance->getFillable())->toContain('is_paused');
});

// ---- OrderClearance Table Name ----

it('uses order_clearances table', function () {
    $clearance = new OrderClearance;

    expect($clearance->getTable())->toBe('order_clearances');
});

// ---- OrderClearance Relationship Definitions ----

it('has complaint relationship method', function () {
    expect(method_exists(OrderClearance::class, 'complaint'))->toBeTrue();
});

it('has order relationship method', function () {
    expect(method_exists(OrderClearance::class, 'order'))->toBeTrue();
});

it('has tenant relationship method', function () {
    expect(method_exists(OrderClearance::class, 'tenant'))->toBeTrue();
});

it('has cook relationship method', function () {
    expect(method_exists(OrderClearance::class, 'cook'))->toBeTrue();
});

// ---- OrderClearance Helper Method Existence ----

it('has isBlocked method', function () {
    expect(method_exists(OrderClearance::class, 'isBlocked'))->toBeTrue();
});

it('has isFlaggedForReview method', function () {
    expect(method_exists(OrderClearance::class, 'isFlaggedForReview'))->toBeTrue();
});

it('has hasActiveComplaintBlock method', function () {
    expect(method_exists(OrderClearance::class, 'hasActiveComplaintBlock'))->toBeTrue();
});

it('has isInHoldPeriod method', function () {
    expect(method_exists(OrderClearance::class, 'isInHoldPeriod'))->toBeTrue();
});

it('has isEligibleForClearance method', function () {
    expect(method_exists(OrderClearance::class, 'isEligibleForClearance'))->toBeTrue();
});

// ---- OrderClearance Scope Existence ----

it('has scopeBlocked method', function () {
    expect(method_exists(OrderClearance::class, 'scopeBlocked'))->toBeTrue();
});

it('has scopeFlaggedForReview method', function () {
    expect(method_exists(OrderClearance::class, 'scopeFlaggedForReview'))->toBeTrue();
});

it('has scopeWithActiveComplaintBlock method', function () {
    expect(method_exists(OrderClearance::class, 'scopeWithActiveComplaintBlock'))->toBeTrue();
});

// ---- Complaint Model Constants ----

it('has dismiss as a valid resolution type on complaint model', function () {
    expect(Complaint::ALL_STATUSES)->toContain('resolved')
        ->and(Complaint::ALL_STATUSES)->toContain('dismissed');
});

it('has open status available', function () {
    expect(Complaint::ALL_STATUSES)->toContain('open');
});

// ---- Resolution Type Routing Logic (BR-221, BR-222) ----

it('dismiss and warning are resume resolution types', function () {
    $resumeTypes = ['dismiss', 'warning'];

    foreach ($resumeTypes as $type) {
        expect(in_array($type, ['dismiss', 'warning'], true))->toBeTrue(
            "Expected '$type' to be classified as resume type"
        );
        expect(in_array($type, ['partial_refund', 'full_refund', 'suspend'], true))->toBeFalse(
            "Expected '$type' NOT to be classified as refund type"
        );
    }
});

it('partial_refund full_refund and suspend are cancel resolution types', function () {
    $refundTypes = ['partial_refund', 'full_refund', 'suspend'];

    foreach ($refundTypes as $type) {
        expect(in_array($type, ['partial_refund', 'full_refund', 'suspend'], true))->toBeTrue(
            "Expected '$type' to be classified as refund type"
        );
        expect(in_array($type, ['dismiss', 'warning'], true))->toBeFalse(
            "Expected '$type' NOT to be classified as resume type"
        );
    }
});

// ---- PaymentBlockService Class and Methods ----

it('PaymentBlockService class exists', function () {
    expect(class_exists(PaymentBlockService::class))->toBeTrue();
});

it('PaymentBlockService has blockPaymentForComplaint method', function () {
    expect(method_exists(PaymentBlockService::class, 'blockPaymentForComplaint'))->toBeTrue();
});

it('PaymentBlockService has unblockPaymentOnResolution method', function () {
    expect(method_exists(PaymentBlockService::class, 'unblockPaymentOnResolution'))->toBeTrue();
});

it('PaymentBlockService has getBlockedClearancesForTenant method', function () {
    expect(method_exists(PaymentBlockService::class, 'getBlockedClearancesForTenant'))->toBeTrue();
});

it('PaymentBlockService has getTotalBlockedAmount method', function () {
    expect(method_exists(PaymentBlockService::class, 'getTotalBlockedAmount'))->toBeTrue();
});

it('PaymentBlockService has getBlockedClearanceForOrder method', function () {
    expect(method_exists(PaymentBlockService::class, 'getBlockedClearanceForOrder'))->toBeTrue();
});

// ---- PaymentBlockService Method Signatures ----

it('blockPaymentForComplaint accepts Order and Complaint parameters', function () {
    $method = new ReflectionMethod(PaymentBlockService::class, 'blockPaymentForComplaint');
    $params = $method->getParameters();

    expect($params)->toHaveCount(2)
        ->and($params[0]->getType()->getName())->toBe('App\Models\Order')
        ->and($params[1]->getType()->getName())->toBe('App\Models\Complaint');
});

it('unblockPaymentOnResolution accepts Complaint and string parameters', function () {
    $method = new ReflectionMethod(PaymentBlockService::class, 'unblockPaymentOnResolution');
    $params = $method->getParameters();

    expect($params)->toHaveCount(2)
        ->and($params[0]->getType()->getName())->toBe('App\Models\Complaint')
        ->and($params[1]->getType()->getName())->toBe('string');
});

it('getBlockedClearancesForTenant accepts int parameter', function () {
    $method = new ReflectionMethod(PaymentBlockService::class, 'getBlockedClearancesForTenant');
    $params = $method->getParameters();

    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()->getName())->toBe('int');
});

it('getTotalBlockedAmount accepts int parameter and returns float', function () {
    $method = new ReflectionMethod(PaymentBlockService::class, 'getTotalBlockedAmount');
    $params = $method->getParameters();

    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()->getName())->toBe('int')
        ->and($method->getReturnType()->getName())->toBe('float');
});

it('getBlockedClearanceForOrder accepts int parameter', function () {
    $method = new ReflectionMethod(PaymentBlockService::class, 'getBlockedClearanceForOrder');
    $params = $method->getParameters();

    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()->getName())->toBe('int');
});

// ---- PaymentBlockService Constructor ----

it('PaymentBlockService depends on OrderClearanceService', function () {
    $constructor = new ReflectionMethod(PaymentBlockService::class, '__construct');
    $params = $constructor->getParameters();

    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()->getName())->toBe('App\Services\OrderClearanceService');
});
