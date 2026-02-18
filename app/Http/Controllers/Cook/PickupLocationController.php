<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StorePickupLocationRequest;
use App\Services\DeliveryAreaService;
use Illuminate\Http\Request;

class PickupLocationController extends Controller
{
    /**
     * Display the pickup locations page.
     *
     * F-092: Add Pickup Location
     * BR-286: Pickup location is scoped to the current tenant
     * BR-288: Only users with location management permission can access
     */
    public function index(Request $request, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-288: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        $pickupLocations = $deliveryAreaService->getPickupLocationsData($tenant);
        $deliveryAreas = $deliveryAreaService->getDeliveryAreasData($tenant);

        return gale()->view('cook.locations.pickup', [
            'pickupLocations' => $pickupLocations,
            'deliveryAreas' => $deliveryAreas,
        ], web: true);
    }

    /**
     * Store a new pickup location.
     *
     * F-092: Add Pickup Location
     * BR-281: Location name is required in both English and French
     * BR-282: Town selection is required (from cook's existing towns)
     * BR-283: Quarter selection is required (from quarters within the selected town)
     * BR-284: Address/description is required (free text, max 500 characters)
     * BR-285: Pickup locations have no delivery fee (fee is always 0/N/A)
     * BR-286: Pickup location is scoped to the current tenant
     * BR-287: Save via Gale; location appears in list without page reload
     * BR-288: Only users with location management permission can add pickup locations
     */
    public function store(Request $request, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-288: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'pickup_name_en' => ['required', 'string', 'max:255'],
                'pickup_name_fr' => ['required', 'string', 'max:255'],
                'pickup_town_id' => ['required', 'integer', 'exists:towns,id'],
                'pickup_quarter_id' => ['required', 'integer', 'exists:quarters,id'],
                'pickup_address' => ['required', 'string', 'max:500'],
            ], [
                'pickup_name_en.required' => __('Location name is required in English.'),
                'pickup_name_en.max' => __('Location name must not exceed 255 characters.'),
                'pickup_name_fr.required' => __('Location name is required in French.'),
                'pickup_name_fr.max' => __('Location name must not exceed 255 characters.'),
                'pickup_town_id.required' => __('Please select a town.'),
                'pickup_town_id.exists' => __('The selected town is invalid.'),
                'pickup_quarter_id.required' => __('Please select a quarter.'),
                'pickup_quarter_id.exists' => __('The selected quarter is invalid.'),
                'pickup_address.required' => __('Address is required.'),
                'pickup_address.max' => __('Address must not exceed 500 characters.'),
            ]);
        } else {
            $formRequest = app(StorePickupLocationRequest::class);
            $validated = $formRequest->validated();
            // Map standard keys for HTTP path
            $validated['pickup_name_en'] = $validated['name_en'];
            $validated['pickup_name_fr'] = $validated['name_fr'];
            $validated['pickup_town_id'] = $validated['town_id'];
            $validated['pickup_quarter_id'] = $validated['quarter_id'];
            $validated['pickup_address'] = $validated['address'];
        }

        // BR-281: Trim whitespace before storage
        $nameEn = trim($validated['pickup_name_en']);
        $nameFr = trim($validated['pickup_name_fr']);
        $townId = (int) $validated['pickup_town_id'];
        $quarterId = (int) $validated['pickup_quarter_id'];
        $address = trim($validated['pickup_address']);

        // Use DeliveryAreaService for business logic
        $result = $deliveryAreaService->addPickupLocation(
            $tenant,
            $nameEn,
            $nameFr,
            $townId,
            $quarterId,
            $address,
        );

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'pickup_town_id' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['town_id' => $result['error']])->withInput();
        }

        // Activity logging
        activity('pickup_locations')
            ->performedOn($result['pickup_model'])
            ->causedBy($user)
            ->withProperties([
                'action' => 'pickup_location_added',
                'name_en' => $nameEn,
                'name_fr' => $nameFr,
                'town_id' => $townId,
                'quarter_id' => $quarterId,
                'address' => $address,
                'tenant_id' => $tenant->id,
            ])
            ->log('Pickup location added');

        // BR-287: Gale redirect with toast (location appears in list without page reload)
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/locations/pickup'))
                ->with('success', __('Pickup location added successfully.'));
        }

        return redirect()->route('cook.locations.pickup.index')
            ->with('success', __('Pickup location added successfully.'));
    }

}
