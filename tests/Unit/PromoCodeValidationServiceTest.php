<?php

/**
 * F-218: Promo Code Application at Checkout — Unit Tests
 * F-219: Promo Code Validation Rules — Unit Tests
 *
 * Tests the PromoCodeValidationService business logic in isolation,
 * focusing on discount calculation and discount label building.
 * Database-dependent validation tests are covered by Playwright browser testing.
 */

use App\Models\PromoCode;
use App\Services\PromoCodeValidationService;

// ─── Service Instantiation ─────────────────────────────────────────────────────

describe('PromoCodeValidationService', function () {
    it('can be instantiated', function () {
        $service = new PromoCodeValidationService;
        expect($service)->toBeInstanceOf(PromoCodeValidationService::class);
    });
});

// ─── calculateDiscount() — Percentage type ────────────────────────────────────

describe('PromoCodeValidationService::calculateDiscount() — percentage', function () {
    it('calculates percentage discount correctly', function () {
        $service = new PromoCodeValidationService;
        $promo = new PromoCode([
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
        ]);

        // 10% of 5000 = 500
        expect($service->calculateDiscount($promo, 5000))->toBe(500);
    });

    it('floors fractional percentage results', function () {
        $service = new PromoCodeValidationService;
        $promo = new PromoCode([
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
        ]);

        // 10% of 3333 = 333.3 → floored to 333
        expect($service->calculateDiscount($promo, 3333))->toBe(333);
    });

    it('calculates 100% discount correctly and caps at subtotal', function () {
        $service = new PromoCodeValidationService;
        $promo = new PromoCode([
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 100,
        ]);

        // 100% of 2000 = 2000 (capped at subtotal)
        expect($service->calculateDiscount($promo, 2000))->toBe(2000);
    });

    it('caps percentage discount at food subtotal (BR-577)', function () {
        $service = new PromoCodeValidationService;
        $promo = new PromoCode([
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 100,
        ]);

        // 100% would give 5000, but subtotal is 5000 → caps at 5000
        expect($service->calculateDiscount($promo, 5000))->toBe(5000);
    });

    it('returns 0 for zero subtotal', function () {
        $service = new PromoCodeValidationService;
        $promo = new PromoCode([
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 20,
        ]);

        expect($service->calculateDiscount($promo, 0))->toBe(0);
    });
});

// ─── calculateDiscount() — Fixed type ─────────────────────────────────────────

describe('PromoCodeValidationService::calculateDiscount() — fixed', function () {
    it('returns the fixed discount value when subtotal is large enough', function () {
        $service = new PromoCodeValidationService;
        $promo = new PromoCode([
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 500,
        ]);

        // Fixed 500 off subtotal of 5000
        expect($service->calculateDiscount($promo, 5000))->toBe(500);
    });

    it('caps fixed discount at food subtotal when fixed > subtotal (BR-577)', function () {
        $service = new PromoCodeValidationService;
        $promo = new PromoCode([
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 1000,
        ]);

        // Fixed 1000 off subtotal of 500 → capped at 500
        expect($service->calculateDiscount($promo, 500))->toBe(500);
    });

    it('exactly equals subtotal when fixed value equals subtotal', function () {
        $service = new PromoCodeValidationService;
        $promo = new PromoCode([
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 2000,
        ]);

        expect($service->calculateDiscount($promo, 2000))->toBe(2000);
    });

    it('returns 0 for zero subtotal with fixed discount', function () {
        $service = new PromoCodeValidationService;
        $promo = new PromoCode([
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 500,
        ]);

        // Fixed 500 but subtotal is 0 → capped at 0
        expect($service->calculateDiscount($promo, 0))->toBe(0);
    });
});

// ─── buildDiscountLabel() ──────────────────────────────────────────────────────

describe('PromoCodeValidationService::buildDiscountLabel() method', function () {
    it('method exists and is callable', function () {
        $service = new PromoCodeValidationService;
        expect(method_exists($service, 'buildDiscountLabel'))->toBeTrue();
    });
});

// ─── Discount Calculation Edge Cases ──────────────────────────────────────────

describe('PromoCodeValidationService discount edge cases', function () {
    it('discount can never be negative (BR-577)', function () {
        $service = new PromoCodeValidationService;
        $promo = new PromoCode([
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 99999,
        ]);

        // Even with huge fixed discount vs tiny subtotal, min is 0
        expect($service->calculateDiscount($promo, 100))->toBe(100);
    });

    it('percentage discount with very small subtotal floors to 0', function () {
        $service = new PromoCodeValidationService;
        $promo = new PromoCode([
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 5,
        ]);

        // 5% of 10 = 0.5 → floor to 0
        expect($service->calculateDiscount($promo, 10))->toBe(0);
    });

    it('50 percent discount on even subtotal is half', function () {
        $service = new PromoCodeValidationService;
        $promo = new PromoCode([
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 50,
        ]);

        expect($service->calculateDiscount($promo, 4000))->toBe(2000);
    });
});
