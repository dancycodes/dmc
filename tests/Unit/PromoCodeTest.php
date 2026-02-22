<?php

/**
 * F-215: Cook Promo Code Creation — Unit Tests
 *
 * Tests the PromoCode model and PromoCodeService business logic.
 */

use App\Models\PromoCode;
use App\Services\PromoCodeService;

// ─── PromoCode Model ──────────────────────────────────────────────────────────

describe('PromoCode model constants', function () {
    it('has correct discount type constants', function () {
        expect(PromoCode::TYPE_PERCENTAGE)->toBe('percentage');
        expect(PromoCode::TYPE_FIXED)->toBe('fixed');
        expect(PromoCode::DISCOUNT_TYPES)->toBe(['percentage', 'fixed']);
    });

    it('has correct status constants', function () {
        expect(PromoCode::STATUS_ACTIVE)->toBe('active');
        expect(PromoCode::STATUS_INACTIVE)->toBe('inactive');
    });

    it('has correct validation range constants', function () {
        expect(PromoCode::MIN_PERCENTAGE)->toBe(1);
        expect(PromoCode::MAX_PERCENTAGE)->toBe(100);
        expect(PromoCode::MIN_FIXED)->toBe(1);
        expect(PromoCode::MAX_FIXED)->toBe(100000);
        expect(PromoCode::MIN_ORDER_AMOUNT)->toBe(0);
        expect(PromoCode::MAX_ORDER_AMOUNT)->toBe(100000);
        expect(PromoCode::MAX_TOTAL_USES)->toBe(100000);
        expect(PromoCode::MAX_PER_CLIENT_USES)->toBe(100);
    });
});

describe('PromoCode discount_label accessor', function () {
    it('returns percentage format for percentage type', function () {
        $promo = new PromoCode([
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
        ]);
        expect($promo->discount_label)->toBe('10%');
    });

    it('returns XAF format for fixed type', function () {
        $promo = new PromoCode([
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 500,
        ]);
        expect($promo->discount_label)->toBe('500 XAF');
    });

    it('formats large fixed values with number_format', function () {
        $promo = new PromoCode([
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 5000,
        ]);
        expect($promo->discount_label)->toBe('5,000 XAF');
    });
});

describe('PromoCode max_uses_label accessor', function () {
    it('returns infinity symbol for unlimited (0)', function () {
        $promo = new PromoCode(['max_uses' => 0]);
        expect($promo->max_uses_label)->toBe('∞');
    });

    it('returns the count for limited uses', function () {
        $promo = new PromoCode(['max_uses' => 50]);
        expect($promo->max_uses_label)->toBe('50');
    });
});

describe('PromoCode max_uses_per_client_label accessor', function () {
    it('returns infinity symbol for unlimited (0)', function () {
        $promo = new PromoCode(['max_uses_per_client' => 0]);
        expect($promo->max_uses_per_client_label)->toBe('∞');
    });

    it('returns the count for limited per-client uses', function () {
        $promo = new PromoCode(['max_uses_per_client' => 2]);
        expect($promo->max_uses_per_client_label)->toBe('2');
    });
});

// ─── PromoCodeService ──────────────────────────────────────────────────────────

describe('PromoCodeService', function () {
    it('exists and can be instantiated', function () {
        $service = new PromoCodeService;
        expect($service)->toBeInstanceOf(PromoCodeService::class);
    });

    it('has correct per page constant', function () {
        expect(PromoCodeService::PER_PAGE)->toBe(15);
    });
});
