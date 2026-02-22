<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\PromoCodeUsage;

/**
 * F-219: Promo Code Validation Rules
 * F-218: Promo Code Application at Checkout
 *
 * Single source of truth for all promo code validation logic.
 * Runs checks sequentially; returns the first failure (BR-593).
 *
 * BR-586: Check 1 — Code exists and belongs to the current tenant.
 * BR-587: Check 2 — Code status is "active".
 * BR-588: Check 3 — Current date is on or after start date.
 * BR-589: Check 4 — Current date is on or before end date (if set).
 * BR-590: Check 5 — Total usage count is below max_uses (if > 0).
 * BR-591: Check 6 — Client's personal usage count is below max_uses_per_client (if > 0).
 * BR-592: Check 7 — Cart food subtotal meets or exceeds promo's minimum_order_amount.
 * BR-593: Checks run in order; first failure stops evaluation.
 * BR-594: Non-existent and cross-tenant codes return same generic error.
 * BR-595: Code normalized to uppercase before lookup.
 * BR-596: Each failure returns a specific localized error message.
 * BR-597: All errors use __() localization.
 */
class PromoCodeValidationService
{
    /**
     * Validate a promo code for application at checkout.
     *
     * Returns null on success, or a user-facing error string on failure.
     *
     * @return array{valid: bool, error: string|null, promoCode: PromoCode|null}
     */
    public function validate(
        string $code,
        int $tenantId,
        int $userId,
        int $foodSubtotal,
    ): array {
        // BR-595: Normalize code to uppercase; trim whitespace
        $normalizedCode = strtoupper(trim($code));

        // BR-586: Check 1 — Code exists and belongs to the current tenant
        $promoCode = PromoCode::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('UPPER(code) = ?', [$normalizedCode])
            ->first();

        // BR-594: Same generic error for non-existent and cross-tenant codes
        if (! $promoCode) {
            return $this->fail(__('This promo code is not valid.'));
        }

        // BR-587: Check 2 — Status must be "active"
        if ($promoCode->status !== PromoCode::STATUS_ACTIVE) {
            return $this->fail(__('This promo code is currently inactive.'));
        }

        $today = now()->toDateString();

        // BR-588: Check 3 — Current date is on or after start date
        if ($promoCode->starts_at->toDateString() > $today) {
            $startDate = $promoCode->starts_at->isoFormat('MMMM D, YYYY');

            return $this->fail(__('This promo code is not yet active. It starts on :date.', ['date' => $startDate]));
        }

        // BR-589: Check 4 — Current date is on or before end date (if set)
        if ($promoCode->ends_at !== null && $promoCode->ends_at->toDateString() < $today) {
            return $this->fail(__('This promo code has expired.'));
        }

        // BR-590: Check 5 — Total usage count is below max_uses (if max_uses > 0)
        if ($promoCode->max_uses > 0 && $promoCode->times_used >= $promoCode->max_uses) {
            return $this->fail(__('This promo code has been fully redeemed.'));
        }

        // BR-591: Check 6 — Client personal usage count is below max_uses_per_client (if > 0)
        if ($promoCode->max_uses_per_client > 0) {
            $clientUsageCount = PromoCodeUsage::query()
                ->where('promo_code_id', $promoCode->id)
                ->where('user_id', $userId)
                ->count();

            if ($clientUsageCount >= $promoCode->max_uses_per_client) {
                return $this->fail(__('You have already used this promo code the maximum number of times.'));
            }
        }

        // BR-592: Check 7 — Cart food subtotal meets or exceeds promo's minimum_order_amount
        if ($promoCode->minimum_order_amount > 0 && $foodSubtotal < $promoCode->minimum_order_amount) {
            $amountNeeded = $promoCode->minimum_order_amount - $foodSubtotal;

            return $this->fail(
                __('This promo code requires a minimum order of :minimum XAF. Add :needed XAF more to qualify.', [
                    'minimum' => number_format($promoCode->minimum_order_amount),
                    'needed' => number_format($amountNeeded),
                ])
            );
        }

        // All checks passed
        return [
            'valid' => true,
            'error' => null,
            'promoCode' => $promoCode,
        ];
    }

    /**
     * Calculate the discount amount for a promo code applied to a food subtotal.
     *
     * BR-575: Percentage discount is applied to the food subtotal (before delivery fee).
     * BR-576: Fixed discount is subtracted from the food subtotal (before delivery fee).
     * BR-577: Discount cannot exceed the food subtotal (food portion minimum is 0 XAF).
     */
    public function calculateDiscount(PromoCode $promoCode, int $foodSubtotal): int
    {
        if ($promoCode->discount_type === PromoCode::TYPE_PERCENTAGE) {
            $discount = (int) floor($foodSubtotal * $promoCode->discount_value / 100);
        } else {
            $discount = $promoCode->discount_value;
        }

        // BR-577: Cap discount at food subtotal — never go negative
        return min($discount, $foodSubtotal);
    }

    /**
     * Build the discount label for display in the order summary.
     *
     * BR-578: Displayed as a negative line item with the code name.
     */
    public function buildDiscountLabel(PromoCode $promoCode, int $discountAmount): string
    {
        if ($promoCode->discount_type === PromoCode::TYPE_PERCENTAGE) {
            return __('Promo :code (-:pct%): -:amount XAF', [
                'code' => $promoCode->code,
                'pct' => $promoCode->discount_value,
                'amount' => number_format($discountAmount),
            ]);
        }

        return __('Promo :code: -:amount XAF', [
            'code' => $promoCode->code,
            'amount' => number_format($discountAmount),
        ]);
    }

    /**
     * Record promo code usage after a successful order placement.
     *
     * BR-582: Usage recorded only when order is successfully placed.
     */
    public function recordUsage(PromoCode $promoCode, int $orderId, int $userId, int $discountAmount): PromoCodeUsage
    {
        $usage = PromoCodeUsage::create([
            'promo_code_id' => $promoCode->id,
            'order_id' => $orderId,
            'user_id' => $userId,
            'discount_amount' => $discountAmount,
        ]);

        // Increment the times_used counter on the promo code
        $promoCode->increment('times_used');

        return $usage;
    }

    /**
     * Build a failure result array.
     *
     * @return array{valid: bool, error: string, promoCode: null}
     */
    private function fail(string $error): array
    {
        return [
            'valid' => false,
            'error' => $error,
            'promoCode' => null,
        ];
    }
}
