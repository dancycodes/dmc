<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * F-215: Cook Promo Code Creation
 * F-217: Cook Promo Code Deactivation
 *
 * Handles all promo code business logic for the cook dashboard.
 *
 * BR-533: Code stored uppercase, alphanumeric, 3-20 chars.
 * BR-534: Code unique within tenant.
 * BR-544: New codes created as active.
 * BR-546: All creation logged via Spatie Activitylog.
 * BR-560: Status: active, inactive (manual), expired (computed).
 * BR-561: Deactivated code cannot be applied at checkout.
 * BR-562: Deactivated code can be reactivated.
 * BR-563: Expired code cannot be reactivated via toggle.
 * BR-565: Bulk deactivation supported.
 * BR-567: Expired = current date > end date.
 * BR-569: All status changes logged via Spatie Activitylog.
 */
class PromoCodeService
{
    /**
     * Promo codes per page on the index list.
     */
    public const PER_PAGE = 15;

    /**
     * Valid sort fields for the promo code list.
     *
     * @var array<string>
     */
    public const SORT_FIELDS = ['created_at', 'times_used', 'ends_at'];

    /**
     * Valid status filter values (including computed "expired").
     *
     * @var array<string>
     */
    public const STATUS_FILTERS = ['all', 'active', 'inactive', 'expired'];

    /**
     * Get paginated promo codes for a tenant with optional filtering and sorting.
     *
     * BR-564: List shows code, discount type/value, status, usage count, dates.
     * BR-567: Expired status is computed: current date > end date.
     *
     * @return LengthAwarePaginator<PromoCode>
     */
    public function getPromoCodesForTenant(
        Tenant $tenant,
        int $page = 1,
        string $statusFilter = 'all',
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
    ): LengthAwarePaginator {
        $today = now()->toDateString();

        $query = PromoCode::query()
            ->forTenant($tenant->id)
            ->with('creator:id,name');

        // Apply status filter
        // BR-567: "expired" is computed (ends_at < today), not a DB column value
        if ($statusFilter === 'active') {
            $query->where('status', PromoCode::STATUS_ACTIVE)
                ->where(function ($q) use ($today) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today);
                });
        } elseif ($statusFilter === 'inactive') {
            $query->where('status', PromoCode::STATUS_INACTIVE)
                ->where(function ($q) use ($today) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today);
                });
        } elseif ($statusFilter === 'expired') {
            $query->whereNotNull('ends_at')->where('ends_at', '<', $today);
        }

        // Apply sorting
        $validSortField = in_array($sortBy, self::SORT_FIELDS, true) ? $sortBy : 'created_at';
        $validSortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        if ($validSortField === 'ends_at') {
            // NULLs last when sorting by end date
            $query->orderByRaw("ends_at {$validSortDir} NULLS LAST");
        } else {
            $query->orderBy($validSortField, $validSortDir);
        }

        return $query->paginate(self::PER_PAGE, ['*'], 'page', $page);
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
     * Toggle a promo code between active and inactive.
     *
     * BR-560: Valid status values are active and inactive.
     * BR-562: Inactive codes can be reactivated.
     * BR-563: Expired codes cannot be reactivated via toggle.
     * BR-569: All status changes logged via Spatie Activitylog.
     *
     * @return array{success: bool, message: string, status: string|null, is_expired: bool}
     */
    public function toggleStatus(PromoCode $promoCode): array
    {
        $today = now()->toDateString();
        $isExpired = $promoCode->ends_at !== null
            && $promoCode->ends_at->toDateString() < $today;

        // BR-563: Cannot reactivate expired codes via toggle
        if ($isExpired && $promoCode->status === PromoCode::STATUS_INACTIVE) {
            return [
                'success' => false,
                'message' => __('Expired codes cannot be reactivated. Extend the end date first.'),
                'status' => null,
                'is_expired' => true,
            ];
        }

        if ($isExpired && $promoCode->status === PromoCode::STATUS_ACTIVE) {
            return [
                'success' => false,
                'message' => __('This code has expired. Extend the end date to reactivate it.'),
                'status' => null,
                'is_expired' => true,
            ];
        }

        $newStatus = $promoCode->status === PromoCode::STATUS_ACTIVE
            ? PromoCode::STATUS_INACTIVE
            : PromoCode::STATUS_ACTIVE;

        $promoCode->status = $newStatus;
        $promoCode->save();

        $message = $newStatus === PromoCode::STATUS_INACTIVE
            ? __(':code deactivated.', ['code' => $promoCode->code])
            : __(':code activated.', ['code' => $promoCode->code]);

        return [
            'success' => true,
            'message' => $message,
            'status' => $newStatus,
            'is_expired' => false,
        ];
    }

    /**
     * Bulk deactivate multiple promo codes by their IDs.
     *
     * BR-565: Bulk deactivation: cook selects multiple codes and deactivates them in one action.
     * BR-566: Bulk reactivation is not supported (individual toggle only for reactivation).
     * BR-567: No-op for already-inactive and expired codes.
     * BR-569: All status changes logged via Spatie Activitylog.
     *
     * @param  array<int>  $ids
     * @return array{success: bool, count: int, message: string}
     */
    public function bulkDeactivate(Tenant $tenant, array $ids): array
    {
        if (empty($ids)) {
            return ['success' => false, 'count' => 0, 'message' => __('No promo codes selected.')];
        }

        // Fetch only codes that belong to this tenant AND are currently active
        // BR-567: Only active (non-expired) codes are toggled; already-inactive and expired are no-ops
        $today = now()->toDateString();

        $codes = PromoCode::query()
            ->forTenant($tenant->id)
            ->whereIn('id', $ids)
            ->where('status', PromoCode::STATUS_ACTIVE)
            ->get();

        $count = 0;

        foreach ($codes as $promoCode) {
            $promoCode->status = PromoCode::STATUS_INACTIVE;
            $promoCode->save();
            $count++;
        }

        if ($count === 0) {
            return ['success' => true, 'count' => 0, 'message' => __('No active promo codes were found to deactivate.')];
        }

        return [
            'success' => true,
            'count' => $count,
            'message' => trans_choice(
                ':count promo code deactivated.|:count promo codes deactivated.',
                $count,
                ['count' => $count]
            ),
        ];
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
