<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreQuarterRequest;
use App\Services\DeliveryAreaService;
use Illuminate\Http\Request;

class QuarterController extends Controller
{
    /**
     * Store a new quarter in a cook's delivery area (town).
     *
     * F-086: Add Quarter
     * BR-232: Quarter name required in both EN and FR
     * BR-233: Quarter name must be unique within its parent town
     * BR-234: Delivery fee is required and must be >= 0 XAF
     * BR-235: Delivery fee is stored as an integer in XAF
     * BR-236: A fee of 0 means free delivery to that quarter
     * BR-237: Quarter can optionally be assigned to a quarter group
     * BR-238: When assigned to a group, the group's delivery fee overrides the individual fee
     * BR-239: Quarter is scoped to its parent town (town_id foreign key)
     * BR-240: Save via Gale; quarter appears in list without page reload
     * BR-241: Only users with location management permission can add quarters
     */
    public function store(Request $request, int $deliveryArea, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-241: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'quarter_name_en' => ['required', 'string', 'max:255'],
                'quarter_name_fr' => ['required', 'string', 'max:255'],
                'quarter_delivery_fee' => ['required', 'integer', 'min:0'],
            ], [
                'quarter_name_en.required' => __('Quarter name is required in English.'),
                'quarter_name_en.max' => __('Quarter name must not exceed 255 characters.'),
                'quarter_name_fr.required' => __('Quarter name is required in French.'),
                'quarter_name_fr.max' => __('Quarter name must not exceed 255 characters.'),
                'quarter_delivery_fee.required' => __('Delivery fee is required.'),
                'quarter_delivery_fee.integer' => __('Delivery fee must be a whole number.'),
                'quarter_delivery_fee.min' => __('Delivery fee must be 0 or greater.'),
            ]);
        } else {
            $formRequest = app(StoreQuarterRequest::class);
            $validated = $formRequest->validated();
            // Map standard keys for HTTP path
            $validated['quarter_name_en'] = $validated['name_en'];
            $validated['quarter_name_fr'] = $validated['name_fr'];
            $validated['quarter_delivery_fee'] = $validated['delivery_fee'];
        }

        // BR-232: Trim whitespace before storage and uniqueness check
        $nameEn = trim($validated['quarter_name_en']);
        $nameFr = trim($validated['quarter_name_fr']);
        $fee = (int) $validated['quarter_delivery_fee'];

        // Use DeliveryAreaService for business logic
        $result = $deliveryAreaService->addQuarter($tenant, $deliveryArea, $nameEn, $nameFr, $fee);

        if (! $result['success']) {
            // BR-233: Uniqueness violation
            if ($request->isGale()) {
                return gale()->messages([
                    'quarter_name_en' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['name_en' => $result['error']])->withInput();
        }

        // Activity logging
        activity('delivery_areas')
            ->causedBy($user)
            ->withProperties([
                'action' => 'quarter_added',
                'quarter_name_en' => $nameEn,
                'quarter_name_fr' => $nameFr,
                'delivery_fee' => $fee,
                'delivery_area_id' => $deliveryArea,
                'tenant_id' => $tenant->id,
            ])
            ->log('Quarter added to delivery area');

        // Build success message
        $successMessage = __('Quarter added successfully.');

        // Include high fee warning if applicable
        if (! empty($result['warning'])) {
            $successMessage .= ' '.$result['warning'];
        }

        // BR-240: Gale redirect with toast (quarter appears in list without page reload)
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/locations'))
                ->with('success', $successMessage);
        }

        return redirect()->route('cook.locations.index')
            ->with('success', $successMessage);
    }
}
