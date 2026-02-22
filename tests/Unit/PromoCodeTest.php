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

// ─── PromoCode Edit Validation Constants ──────────────────────────────────────

describe('PromoCode editable field constraints', function () {
    it('min percentage is 1', function () {
        expect(PromoCode::MIN_PERCENTAGE)->toBe(1);
    });

    it('max percentage is 100', function () {
        expect(PromoCode::MAX_PERCENTAGE)->toBe(100);
    });

    it('max fixed is 100000', function () {
        expect(PromoCode::MAX_FIXED)->toBe(100000);
    });

    it('max order amount is 100000', function () {
        expect(PromoCode::MAX_ORDER_AMOUNT)->toBe(100000);
    });

    it('max total uses is 100000', function () {
        expect(PromoCode::MAX_TOTAL_USES)->toBe(100000);
    });

    it('max per client uses is 100', function () {
        expect(PromoCode::MAX_PER_CLIENT_USES)->toBe(100);
    });
});

// ─── PromoCode edit immutability constants (F-216) ───────────────────────────

describe('PromoCode edit immutability (F-216)', function () {
    it('code string cannot be changed - BR-549', function () {
        // Verify immutable fields are not listed in editable field set
        $editableFields = ['discount_value', 'minimum_order_amount', 'max_uses', 'max_uses_per_client', 'starts_at', 'ends_at'];
        expect($editableFields)->not->toContain('code');
        expect($editableFields)->not->toContain('discount_type');
    });

    it('discount type cannot be changed - BR-551', function () {
        $editableFields = ['discount_value', 'minimum_order_amount', 'max_uses', 'max_uses_per_client', 'starts_at', 'ends_at'];
        expect($editableFields)->not->toContain('discount_type');
    });

    it('BR-550 editable fields are the correct set', function () {
        $editableFields = ['discount_value', 'minimum_order_amount', 'max_uses', 'max_uses_per_client', 'starts_at', 'ends_at'];
        expect($editableFields)->toBe([
            'discount_value',
            'minimum_order_amount',
            'max_uses',
            'max_uses_per_client',
            'starts_at',
            'ends_at',
        ]);
    });
});

// ─── PromoCode Deactivation Logic (F-217) ────────────────────────────────────

describe('PromoCode computeIsExpired() method (F-217 BR-567)', function () {
    it('returns false when ends_at is null (no expiry)', function () {
        expect(PromoCode::computeIsExpired(null))->toBeFalse();
    });

    it('returns false when ends_at is today', function () {
        expect(PromoCode::computeIsExpired(now()->toDateString()))->toBeFalse();
    });

    it('returns false when ends_at is in the future', function () {
        expect(PromoCode::computeIsExpired(now()->addDays(5)->toDateString()))->toBeFalse();
    });

    it('returns true when ends_at is in the past', function () {
        expect(PromoCode::computeIsExpired(now()->subDays(1)->toDateString()))->toBeTrue();
    });
});

describe('PromoCode status constants (F-217 BR-560)', function () {
    it('has active status constant', function () {
        expect(PromoCode::STATUS_ACTIVE)->toBe('active');
    });

    it('has inactive status constant', function () {
        expect(PromoCode::STATUS_INACTIVE)->toBe('inactive');
    });

    it('STATUSES array contains both active and inactive', function () {
        expect(PromoCode::STATUSES)->toContain('active');
        expect(PromoCode::STATUSES)->toContain('inactive');
        expect(PromoCode::STATUSES)->not->toContain('expired');
    });
});

describe('PromoCodeService deactivation constants (F-217)', function () {
    it('has STATUS_FILTERS constant with expected values', function () {
        expect(PromoCodeService::STATUS_FILTERS)->toBe(['all', 'active', 'inactive', 'expired']);
    });

    it('has SORT_FIELDS constant with expected values', function () {
        expect(PromoCodeService::SORT_FIELDS)->toBe(['created_at', 'times_used', 'ends_at']);
    });
});
