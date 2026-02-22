<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StorePromoCodeRequest;
use App\Http\Requests\Cook\UpdatePromoCodeRequest;
use App\Models\PromoCode;
use App\Services\PromoCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * F-215: Cook Promo Code Creation
 * F-216: Cook Promo Code Edit
 * F-217: Cook Promo Code Deactivation
 *
 * Handles cook promo code management in the tenant dashboard.
 *
 * BR-545: Only the cook can create promo codes (enforced via cook_reserved + EnsureCookAccess).
 * BR-548: Gale handles form and list interactions without page reloads.
 * BR-560: Status: active, inactive (manual), expired (computed from end date).
 * BR-565: Bulk deactivation of multiple codes in one action.
 * BR-568: Only the cook can toggle promo code status.
 * BR-571: Gale handles all toggle and bulk actions without page reloads.
 */
class PromoCodeController extends Controller
{
    public function __construct(
        private PromoCodeService $promoCodeService,
    ) {}

    /**
     * Display the promo code list with creation form.
     *
     * BR-543: Only shows promo codes for the current tenant.
     * BR-564: List shows code, discount type/value, status, usage count, dates.
     * BR-567: Expired status is computed: current date > end date.
     */
    public function index(Request $request): mixed
    {
        $tenant = tenant();
        $page = (int) $request->get('page', 1);
        $statusFilter = $request->get('status', 'all');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');

        // Validate filter/sort params
        if (! in_array($statusFilter, PromoCodeService::STATUS_FILTERS, true)) {
            $statusFilter = 'all';
        }

        if (! in_array($sortBy, PromoCodeService::SORT_FIELDS, true)) {
            $sortBy = 'created_at';
        }

        $promoCodes = $this->promoCodeService->getPromoCodesForTenant(
            $tenant,
            $page,
            $statusFilter,
            $sortBy,
            $sortDir,
        );

        $data = compact('promoCodes', 'statusFilter', 'sortBy', 'sortDir');

        if ($request->isGaleNavigate('promo-list')) {
            return gale()->fragment('cook.promo-codes.index', 'promo-list', $data);
        }

        return gale()->view('cook.promo-codes.index', $data, web: true);
    }

    /**
     * Store a newly created promo code.
     *
     * BR-533: Code stored uppercase.
     * BR-534: Code must be unique within tenant.
     * BR-535: Validates discount type.
     * BR-536: Validates percentage range 1-100.
     * BR-537: Validates fixed range 1-100,000.
     * BR-538: Validates minimum order 0-100,000.
     * BR-539: Validates max uses.
     * BR-540: Validates max per client uses.
     * BR-541: Start date required, today or future.
     * BR-542: End date optional, must be after start date.
     * BR-544: Created as active.
     * BR-546: Logged via Spatie Activitylog.
     */
    public function store(Request $request): mixed
    {
        $tenant = tenant();

        if ($request->isGale()) {
            $validated = $request->validateState([
                'code' => [
                    'required',
                    'string',
                    'min:3',
                    'max:20',
                    'regex:/^[A-Za-z0-9]+$/',
                ],
                'discount_type' => [
                    'required',
                    'string',
                    'in:'.implode(',', PromoCode::DISCOUNT_TYPES),
                ],
                'discount_value' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:'.PromoCode::MAX_FIXED,
                ],
                'minimum_order_amount' => [
                    'required',
                    'integer',
                    'min:'.PromoCode::MIN_ORDER_AMOUNT,
                    'max:'.PromoCode::MAX_ORDER_AMOUNT,
                ],
                'max_uses' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:'.PromoCode::MAX_TOTAL_USES,
                ],
                'max_uses_per_client' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:'.PromoCode::MAX_PER_CLIENT_USES,
                ],
                'starts_at' => [
                    'required',
                    'date',
                    'date_format:Y-m-d',
                    'after_or_equal:today',
                ],
                'ends_at' => [
                    'nullable',
                    'date',
                    'date_format:Y-m-d',
                    'after_or_equal:starts_at',
                ],
            ], [
                'code.regex' => __('The promo code must contain only letters and numbers.'),
                'code.min' => __('The promo code must be between 3 and 20 characters.'),
                'code.max' => __('The promo code must be between 3 and 20 characters.'),
                'discount_value.min' => __('The discount value must be at least 1.'),
                'starts_at.after_or_equal' => __('The start date must be today or later.'),
                'ends_at.after_or_equal' => __('The end date must be on or after the start date.'),
            ]);
        } else {
            $validated = app(StorePromoCodeRequest::class)->validated();
        }

        // BR-534: Uniqueness check within tenant
        if ($this->promoCodeService->codeExistsForTenant($tenant, $validated['code'])) {
            $errorMessage = __('A promo code with this name already exists.');

            if ($request->isGale()) {
                return gale()->messages(['code' => [$errorMessage]]);
            }

            return back()->withErrors(['code' => $errorMessage])->withInput();
        }

        // BR-536: Percentage range validation
        if ($validated['discount_type'] === PromoCode::TYPE_PERCENTAGE
            && ($validated['discount_value'] < PromoCode::MIN_PERCENTAGE
                || $validated['discount_value'] > PromoCode::MAX_PERCENTAGE)
        ) {
            $errorMessage = __('Percentage discount must be between 1 and 100.');

            if ($request->isGale()) {
                return gale()->messages(['discount_value' => [$errorMessage]]);
            }

            return back()->withErrors(['discount_value' => $errorMessage])->withInput();
        }

        $promoCode = $this->promoCodeService->createPromoCode($tenant, $request->user(), $validated);

        if ($request->isGale()) {
            // Reload promo code list and close the modal
            $promoCodes = $this->promoCodeService->getPromoCodesForTenant($tenant);

            return gale()
                ->fragment('cook.promo-codes.index', 'promo-list', compact('promoCodes'))
                ->state('showCreateModal', false)
                ->state('code', '')
                ->state('discount_type', PromoCode::TYPE_PERCENTAGE)
                ->state('discount_value', '')
                ->state('minimum_order_amount', 0)
                ->state('max_uses', 0)
                ->state('max_uses_per_client', 0)
                ->state('starts_at', now()->toDateString())
                ->state('ends_at', '')
                ->state('no_end_date', true)
                ->dispatch('toast', [
                    'type' => 'success',
                    'message' => __('Promo code :code created.', ['code' => $promoCode->code]),
                ]);
        }

        return gale()->redirect(route('cook.promo-codes.index'))
            ->with('toast', ['type' => 'success', 'message' => __('Promo code :code created.', ['code' => $promoCode->code])]);
    }

    /**
     * Toggle a single promo code between active and inactive.
     *
     * BR-560: Status: active or inactive (not expired, which is computed).
     * BR-562: A deactivated code can be reactivated.
     * BR-563: An expired code cannot be reactivated via toggle.
     * BR-568: Only the cook can toggle promo code status.
     * BR-569: All status changes logged via Spatie Activitylog.
     * BR-571: Gale handles all toggle actions without page reloads.
     */
    public function toggleStatus(Request $request, PromoCode $promoCode): mixed
    {
        $tenant = tenant();

        if ($promoCode->tenant_id !== $tenant->id) {
            abort(404);
        }

        $result = $this->promoCodeService->toggleStatus($promoCode);

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->dispatch('toast', [
                    'type' => 'error',
                    'message' => $result['message'],
                ]);
            }

            return back()->with('toast', ['type' => 'error', 'message' => $result['message']]);
        }

        if ($request->isGale()) {
            $promoCodes = $this->promoCodeService->getPromoCodesForTenant(
                $tenant,
                (int) $request->get('page', 1),
                $request->get('status', 'all'),
                $request->get('sort_by', 'created_at'),
                $request->get('sort_dir', 'desc'),
            );

            $statusFilter = $request->get('status', 'all');
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDir = $request->get('sort_dir', 'desc');

            return gale()
                ->fragment('cook.promo-codes.index', 'promo-list', compact('promoCodes', 'statusFilter', 'sortBy', 'sortDir'))
                ->dispatch('toast', [
                    'type' => 'success',
                    'message' => $result['message'],
                ]);
        }

        return gale()->redirect(route('cook.promo-codes.index'))
            ->with('toast', ['type' => 'success', 'message' => $result['message']]);
    }

    /**
     * Bulk deactivate multiple promo codes.
     *
     * BR-565: Cook selects multiple codes and deactivates them in one action.
     * BR-566: Bulk reactivation is not supported.
     * BR-567: No-op for already-inactive and expired codes.
     * BR-569: All status changes logged via Spatie Activitylog.
     * BR-571: Gale handles all bulk actions without page reloads.
     */
    public function bulkDeactivate(Request $request): mixed
    {
        $tenant = tenant();

        if ($request->isGale()) {
            $validated = $request->validateState([
                'selectedIds' => ['required', 'array', 'min:1'],
                'selectedIds.*' => ['integer', 'min:1'],
            ], [
                'selectedIds.required' => __('Please select at least one promo code.'),
                'selectedIds.min' => __('Please select at least one promo code.'),
            ]);
            $ids = $validated['selectedIds'];
        } else {
            $ids = $request->input('selectedIds', []);
        }

        $result = $this->promoCodeService->bulkDeactivate($tenant, array_map('intval', $ids));

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->dispatch('toast', ['type' => 'warning', 'message' => $result['message']]);
            }

            return back()->with('toast', ['type' => 'warning', 'message' => $result['message']]);
        }

        if ($request->isGale()) {
            $promoCodes = $this->promoCodeService->getPromoCodesForTenant(
                $tenant,
                (int) $request->get('page', 1),
                $request->get('status', 'all'),
                $request->get('sort_by', 'created_at'),
                $request->get('sort_dir', 'desc'),
            );

            $statusFilter = $request->get('status', 'all');
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDir = $request->get('sort_dir', 'desc');

            return gale()
                ->fragment('cook.promo-codes.index', 'promo-list', compact('promoCodes', 'statusFilter', 'sortBy', 'sortDir'))
                ->state('selectedIds', [])
                ->state('selectAll', false)
                ->dispatch('toast', [
                    'type' => 'success',
                    'message' => $result['message'],
                ]);
        }

        return gale()->redirect(route('cook.promo-codes.index'))
            ->with('toast', ['type' => 'success', 'message' => $result['message']]);
    }

    /**
     * Show the edit form data for a promo code.
     *
     * BR-549: Code string is displayed read-only.
     * BR-551: Discount type is displayed but not editable.
     * BR-552: If used, a usage count warning is displayed.
     * BR-556: Only the cook can edit promo codes.
     */
    public function edit(Request $request, PromoCode $promoCode): mixed
    {
        $tenant = tenant();

        // Ensure this promo code belongs to the current tenant
        if ($promoCode->tenant_id !== $tenant->id) {
            abort(404);
        }

        // BR-552: Forward-compatible usage count — promo_code_usages table created in F-218
        $usageCount = Schema::hasTable('promo_code_usages') ? $promoCode->usages()->count() : 0;

        if ($request->isGale()) {
            // Return state to populate the edit modal
            return gale()
                ->state('showEditModal', true)
                ->state('editId', $promoCode->id)
                ->state('editCode', $promoCode->code)
                ->state('editDiscountType', $promoCode->discount_type)
                ->state('editDiscountValue', $promoCode->discount_value)
                ->state('editMinimumOrderAmount', $promoCode->minimum_order_amount)
                ->state('editMaxUses', $promoCode->max_uses)
                ->state('editMaxUsesPerClient', $promoCode->max_uses_per_client)
                ->state('editStartsAt', $promoCode->starts_at->toDateString())
                ->state('editEndsAt', $promoCode->ends_at ? $promoCode->ends_at->toDateString() : '')
                ->state('editNoEndDate', $promoCode->ends_at === null)
                ->state('editUsageCount', $usageCount)
                ->state('editTimesUsed', $promoCode->times_used);
        }

        return gale()->redirect(route('cook.promo-codes.index'));
    }

    /**
     * Update an existing promo code.
     *
     * BR-549: Code string cannot be changed after creation.
     * BR-550: Editable: discount_value, minimum_order_amount, max_uses,
     *         max_uses_per_client, starts_at, ends_at.
     * BR-551: Discount type cannot be changed after creation.
     * BR-554: Validation rules same as creation for editable fields.
     * BR-555: Setting max_uses below current usage is allowed (code becomes exhausted).
     * BR-556: Only the cook can edit promo codes.
     * BR-557: All edits logged via Spatie Activitylog.
     */
    public function update(Request $request, PromoCode $promoCode): mixed
    {
        $tenant = tenant();

        // Ensure this promo code belongs to the current tenant
        if ($promoCode->tenant_id !== $tenant->id) {
            abort(404);
        }

        $today = now()->toDateString();

        // Start date validation: allowed if code is already active and date is today or earlier
        $isAlreadyActive = $promoCode->starts_at->toDateString() <= $today;

        if ($request->isGale()) {
            $validated = $request->validateState([
                'editDiscountValue' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:'.PromoCode::MAX_FIXED,
                ],
                'editMinimumOrderAmount' => [
                    'required',
                    'integer',
                    'min:'.PromoCode::MIN_ORDER_AMOUNT,
                    'max:'.PromoCode::MAX_ORDER_AMOUNT,
                ],
                'editMaxUses' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:'.PromoCode::MAX_TOTAL_USES,
                ],
                'editMaxUsesPerClient' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:'.PromoCode::MAX_PER_CLIENT_USES,
                ],
                'editStartsAt' => [
                    'required',
                    'date',
                    'date_format:Y-m-d',
                    // Allow today or future; but also allow keeping or setting an earlier date
                    // when the code is already active (BR-554 edge case in F-216 spec)
                    $isAlreadyActive ? 'before_or_equal:today' : 'after_or_equal:today',
                ],
                'editEndsAt' => [
                    'nullable',
                    'date',
                    'date_format:Y-m-d',
                    'after_or_equal:editStartsAt',
                ],
            ], [
                'editDiscountValue.min' => __('The discount value must be at least 1.'),
                'editStartsAt.after_or_equal' => __('The start date must be today or later.'),
                'editStartsAt.before_or_equal' => __('The start date cannot be in the future for an active code.'),
                'editEndsAt.after_or_equal' => __('The end date must be on or after the start date.'),
            ]);
        } else {
            $httpValidated = app(UpdatePromoCodeRequest::class)->validated();
            $validated = [
                'editDiscountValue' => $httpValidated['discount_value'],
                'editMinimumOrderAmount' => $httpValidated['minimum_order_amount'],
                'editMaxUses' => $httpValidated['max_uses'],
                'editMaxUsesPerClient' => $httpValidated['max_uses_per_client'],
                'editStartsAt' => $httpValidated['starts_at'],
                'editEndsAt' => $httpValidated['ends_at'] ?? null,
            ];
        }

        // BR-554: Percentage range validation for editable discount value
        if ($promoCode->discount_type === PromoCode::TYPE_PERCENTAGE
            && ($validated['editDiscountValue'] < PromoCode::MIN_PERCENTAGE
                || $validated['editDiscountValue'] > PromoCode::MAX_PERCENTAGE)
        ) {
            $errorMessage = __('Percentage discount must be between 1 and 100.');

            if ($request->isGale()) {
                return gale()->messages(['editDiscountValue' => [$errorMessage]]);
            }

            return back()->withErrors(['discount_value' => $errorMessage])->withInput();
        }

        $data = [
            'discount_value' => $validated['editDiscountValue'],
            'minimum_order_amount' => $validated['editMinimumOrderAmount'],
            'max_uses' => $validated['editMaxUses'],
            'max_uses_per_client' => $validated['editMaxUsesPerClient'],
            'starts_at' => $validated['editStartsAt'],
            'ends_at' => $validated['editEndsAt'] ?: null,
        ];

        $this->promoCodeService->updatePromoCode($promoCode, $data);

        if ($request->isGale()) {
            $promoCodes = $this->promoCodeService->getPromoCodesForTenant($tenant);

            return gale()
                ->fragment('cook.promo-codes.index', 'promo-list', compact('promoCodes'))
                ->state('showEditModal', false)
                ->state('editId', 0)
                ->dispatch('toast', [
                    'type' => 'success',
                    'message' => __('Promo code :code updated.', ['code' => $promoCode->code]),
                ]);
        }

        return gale()->redirect(route('cook.promo-codes.index'))
            ->with('toast', ['type' => 'success', 'message' => __('Promo code :code updated.', ['code' => $promoCode->code])]);
    }
}
