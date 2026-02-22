<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StorePromoCodeRequest;
use App\Models\PromoCode;
use App\Services\PromoCodeService;
use Illuminate\Http\Request;

/**
 * F-215: Cook Promo Code Creation
 *
 * Handles cook promo code management in the tenant dashboard.
 *
 * BR-545: Only the cook can create promo codes (enforced via cook_reserved + EnsureCookAccess).
 * BR-548: Gale handles form and list interactions without page reloads.
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
     */
    public function index(Request $request): mixed
    {
        $tenant = tenant();
        $page = (int) $request->get('page', 1);

        $promoCodes = $this->promoCodeService->getPromoCodesForTenant($tenant, $page);

        return gale()->view('cook.promo-codes.index', compact('promoCodes'), web: true);
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
}
