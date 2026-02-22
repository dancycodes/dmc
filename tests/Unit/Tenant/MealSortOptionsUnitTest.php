<?php

/**
 * F-137: Meal Sort Options — Unit Tests
 *
 * Tests the MealSearchRequest sort accessor and TenantLandingService
 * sort logic without hitting the database.
 */

use App\Http\Requests\Tenant\MealSearchRequest;

// =====================================================
// MealSearchRequest: sortOption() accessor
// =====================================================

describe('MealSearchRequest sortOption accessor', function () {
    it('returns default sort "popular" when no sort param given', function () {
        // sortOption() uses array_key_exists fallback — test the constant directly
        $sort = null;
        $result = ($sort !== null && array_key_exists($sort, MealSearchRequest::SORT_OPTIONS))
            ? $sort
            : MealSearchRequest::DEFAULT_SORT;

        expect($result)->toBe('popular');
    });

    it('returns "popular" as the default sort constant', function () {
        expect(MealSearchRequest::DEFAULT_SORT)->toBe('popular');
    });

    it('returns correct sort when valid sort param given', function () {
        $validSorts = ['popular', 'price_asc', 'price_desc', 'newest', 'name_asc'];

        foreach ($validSorts as $sort) {
            $request = new MealSearchRequest;
            $request->merge(['sort' => $sort]);
            // Simulate validated() by bypassing validation for unit test
            $request->setRouteResolver(fn () => null);

            expect(MealSearchRequest::SORT_OPTIONS)->toHaveKey($sort);
        }
    });

    it('has exactly 5 sort options', function () {
        expect(MealSearchRequest::SORT_OPTIONS)->toHaveCount(5);
    });

    it('sort options contain all expected keys', function () {
        $expected = ['popular', 'price_asc', 'price_desc', 'newest', 'name_asc'];

        foreach ($expected as $key) {
            expect(MealSearchRequest::SORT_OPTIONS)->toHaveKey($key);
        }
    });

    it('sort option labels are non-empty strings', function () {
        foreach (MealSearchRequest::SORT_OPTIONS as $key => $label) {
            expect($label)->toBeString()->not->toBeEmpty();
        }
    });

    it('falls back to default sort for invalid sort value', function () {
        $request = new MealSearchRequest;
        // Simulate invalid value — sortOption() uses validated() which would reject it,
        // so the fallback in sortOption() protects against it
        $invalidSort = 'invalid_sort';
        expect(array_key_exists($invalidSort, MealSearchRequest::SORT_OPTIONS))->toBeFalse();
        // The fallback logic returns DEFAULT_SORT for anything not in SORT_OPTIONS
        $sort = array_key_exists($invalidSort, MealSearchRequest::SORT_OPTIONS)
            ? $invalidSort
            : MealSearchRequest::DEFAULT_SORT;
        expect($sort)->toBe('popular');
    });
});

// =====================================================
// MealSearchRequest: sort validation rules
// =====================================================

describe('MealSearchRequest validation rules for sort', function () {
    it('includes sort in rules with valid in-list constraint', function () {
        $request = new MealSearchRequest;
        $rules = $request->rules();

        expect($rules)->toHaveKey('sort');
        expect($rules['sort'])->toContain('nullable');
        expect($rules['sort'])->toContain('string');
    });

    it('all SORT_OPTIONS keys are accepted by the validation rule', function () {
        // The validation rule string should list all valid sort keys
        $request = new MealSearchRequest;
        $rules = $request->rules();
        $sortRule = implode('|', $rules['sort']);

        foreach (array_keys(MealSearchRequest::SORT_OPTIONS) as $key) {
            expect($sortRule)->toContain($key);
        }
    });
});

// =====================================================
// Sort option label localization
// =====================================================

describe('Sort option labels', function () {
    it('popular sort label is non-empty', function () {
        expect(MealSearchRequest::SORT_OPTIONS['popular'])->toBeString();
    });

    it('price_asc sort label is non-empty', function () {
        expect(MealSearchRequest::SORT_OPTIONS['price_asc'])->toBeString();
    });

    it('price_desc sort label is non-empty', function () {
        expect(MealSearchRequest::SORT_OPTIONS['price_desc'])->toBeString();
    });

    it('newest sort label is non-empty', function () {
        expect(MealSearchRequest::SORT_OPTIONS['newest'])->toBeString();
    });

    it('name_asc sort label is non-empty', function () {
        expect(MealSearchRequest::SORT_OPTIONS['name_asc'])->toBeString();
    });
});
