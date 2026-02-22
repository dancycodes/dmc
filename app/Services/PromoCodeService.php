<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * F-215: Cook Promo Code Creation
 *
 * Handles all promo code business logic for the cook dashboard.
 *
 * BR-533: Code stored uppercase, alphanumeric, 3-20 chars.
 * BR-534: Code unique within tenant.
 * BR-544: New codes created as active.
 * BR-546: All creation logged via Spatie Activitylog.
 */
class PromoCodeService
{
    /**
     * Promo codes per page on the index list.
     */
    public const PER_PAGE = 15;

    /**
     * Get paginated promo codes for a tenant, newest first.
     *
     * @return LengthAwarePaginator<PromoCode>
     */
    public function getPromoCodesForTenant(Tenant $tenant, int $page = 1): LengthAwarePaginator
    {
        return PromoCode::query()
            ->forTenant($tenant->id)
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);
    }

    /**
     * Create a new promo code for the tenant.
     *
     * BR-533: Stores code in uppercase.
     * BR-534: Validates uniqueness within tenant.
     * BR-544: Status defaults to active.
     * BR-546: Logs creation via Spatie Activitylog.
     *
     * @param  array{
     *   code: string,
     *   discount_type: string,
     *   discount_value: int,
     *   minimum_order_amount: int,
     *   max_uses: int,
     *   max_uses_per_client: int,
     *   starts_at: string,
     *   ends_at: string|null,
     * } $data
     */
    public function createPromoCode(Tenant $tenant, User $creator, array $data): PromoCode
    {
        $promoCode = PromoCode::create([
            'tenant_id' => $tenant->id,
            'created_by' => $creator->id,
            'code' => strtoupper($data['code']),
            'discount_type' => $data['discount_type'],
            'discount_value' => (int) $data['discount_value'],
            'minimum_order_amount' => (int) $data['minimum_order_amount'],
            'max_uses' => (int) $data['max_uses'],
            'max_uses_per_client' => (int) $data['max_uses_per_client'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?: null,
            'status' => PromoCode::STATUS_ACTIVE,
        ]);

        return $promoCode;
    }

    /**
     * Check whether a code already exists for the tenant (case-insensitive).
     *
     * BR-534: Unique within tenant only.
     */
    public function codeExistsForTenant(Tenant $tenant, string $code, ?int $excludeId = null): bool
    {
        $query = PromoCode::query()
            ->forTenant($tenant->id)
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)]);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Update an existing promo code with new values.
     *
     * BR-550: Only editable fields are updated (code, discount_type are immutable).
     * BR-553: Changes apply to future uses only; past orders retain original values.
     * BR-557: All edits are logged via Spatie Activitylog with before/after values.
     * BR-558: dontSubmitEmptyLogs prevents spurious log entries when no changes occur.
     *
     * @param  array{
     *   discount_value: int,
     *   minimum_order_amount: int,
     *   max_uses: int,
     *   max_uses_per_client: int,
     *   starts_at: string,
     *   ends_at: string|null,
     * } $data
     */
    public function updatePromoCode(PromoCode $promoCode, array $data): PromoCode
    {
        $promoCode->fill([
            'discount_value' => (int) $data['discount_value'],
            'minimum_order_amount' => (int) $data['minimum_order_amount'],
            'max_uses' => (int) $data['max_uses'],
            'max_uses_per_client' => (int) $data['max_uses_per_client'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?: null,
        ]);

        $promoCode->save();

        return $promoCode;
    }

    /**
     * Format a promo code's discount value for display.
     */
    public function formatDiscountLabel(PromoCode $promoCode): string
    {
        if ($promoCode->discount_type === PromoCode::TYPE_PERCENTAGE) {
            return $promoCode->discount_value.'%';
        }

        return number_format($promoCode->discount_value).' XAF';
    }
}
