<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StorePickupLocationRequest;
use App\Http\Requests\Cook\UpdatePickupLocationRequest;
use App\Models\PickupLocation;
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

    /**
     * Show the edit form for a pickup location (rendered inline on the same page).
     *
     * F-094: Edit Pickup Location
     * BR-300: Edit action requires location management permission
     */
    public function edit(Request $request, int $pickupLocation, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-300: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        $pickup = PickupLocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $pickupLocation)
            ->first();

        if (! $pickup) {
            abort(404);
        }

        $pickupLocations = $deliveryAreaService->getPickupLocationsData($tenant);
        $deliveryAreas = $deliveryAreaService->getDeliveryAreasData($tenant);

        return gale()->view('cook.locations.pickup', [
            'pickupLocations' => $pickupLocations,
            'deliveryAreas' => $deliveryAreas,
            'editingPickup' => [
                'id' => $pickup->id,
                'name_en' => $pickup->name_en,
                'name_fr' => $pickup->name_fr,
                'town_id' => $pickup->town_id,
                'quarter_id' => $pickup->quarter_id,
                'address' => $pickup->address,
            ],
        ], web: true);
    }

    /**
     * Delete a pickup location.
     *
     * F-095: Delete Pickup Location
     * BR-301: Cannot delete a pickup location with active (non-completed, non-cancelled) orders
     * BR-302: Confirmation dialog must show the location name
     * BR-303: On success, toast notification: "Pickup location deleted successfully"
     * BR-304: Delete action requires location management permission
     * BR-305: List updates via Gale without page reload
     */
    public function destroy(Request $request, int $pickupLocation, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-304: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        // Use DeliveryAreaService for business logic (BR-301 active order check)
        $result = $deliveryAreaService->removePickupLocation($tenant, $pickupLocation);

        if (! $result['success']) {
            // BR-301: Deletion blocked — show error message
            if ($request->isGale()) {
                return gale()
                    ->redirect(url('/dashboard/locations/pickup'))
                    ->with('error', $result['error']);
            }

            return redirect()->route('cook.locations.pickup.index')
                ->with('error', $result['error']);
        }

        // Activity logging
        activity('pickup_locations')
            ->causedBy($user)
            ->withProperties([
                'action' => 'pickup_location_deleted',
                'name' => $result['pickup_name'],
                'tenant_id' => $tenant->id,
            ])
            ->log('Pickup location deleted');

        // BR-303: Success toast, BR-305: Gale redirect updates list without page reload
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/locations/pickup'))
                ->with('success', __('Pickup location deleted successfully.'));
        }

        return redirect()->route('cook.locations.pickup.index')
            ->with('success', __('Pickup location deleted successfully.'));
    }

    /**
     * Update an existing pickup location.
     *
     * F-094: Edit Pickup Location
     * BR-295: Location name required in both English and French
     * BR-296: Town and quarter selection required
     * BR-297: Address/description required; max 500 characters
     * BR-298: Save via Gale; list updates without page reload
     * BR-299: Changes apply to new orders; existing orders retain original data
     * BR-300: Edit action requires location management permission
     */
    public function update(Request $request, int $pickupLocation, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-300: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        $pickup = PickupLocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $pickupLocation)
            ->first();

        if (! $pickup) {
            abort(404);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'edit_name_en' => ['required', 'string', 'max:255'],
                'edit_name_fr' => ['required', 'string', 'max:255'],
                'edit_town_id' => ['required', 'integer', 'exists:towns,id'],
                'edit_quarter_id' => ['required', 'integer', 'exists:quarters,id'],
                'edit_address' => ['required', 'string', 'max:500'],
            ], [
                'edit_name_en.required' => __('Location name is required in English.'),
                'edit_name_en.max' => __('Location name must not exceed 255 characters.'),
                'edit_name_fr.required' => __('Location name is required in French.'),
                'edit_name_fr.max' => __('Location name must not exceed 255 characters.'),
                'edit_town_id.required' => __('Please select a town.'),
                'edit_town_id.exists' => __('The selected town is invalid.'),
                'edit_quarter_id.required' => __('Please select a quarter.'),
                'edit_quarter_id.exists' => __('The selected quarter is invalid.'),
                'edit_address.required' => __('Address is required.'),
                'edit_address.max' => __('Address must not exceed 500 characters.'),
            ]);
        } else {
            $formRequest = app(UpdatePickupLocationRequest::class);
            $validated = $formRequest->validated();
            // Map standard keys for HTTP path
            $validated['edit_name_en'] = $validated['name_en'];
            $validated['edit_name_fr'] = $validated['name_fr'];
            $validated['edit_town_id'] = $validated['town_id'];
            $validated['edit_quarter_id'] = $validated['quarter_id'];
            $validated['edit_address'] = $validated['address'];
        }

        // Trim whitespace and cast types
        $nameEn = trim($validated['edit_name_en']);
        $nameFr = trim($validated['edit_name_fr']);
        $townId = (int) $validated['edit_town_id'];
        $quarterId = (int) $validated['edit_quarter_id'];
        $address = trim($validated['edit_address']);

        // Track old values for activity logging
        $oldValues = [
            'name_en' => $pickup->name_en,
            'name_fr' => $pickup->name_fr,
            'town_id' => $pickup->town_id,
            'quarter_id' => $pickup->quarter_id,
            'address' => $pickup->address,
        ];

        // Use DeliveryAreaService for business logic
        $result = $deliveryAreaService->updatePickupLocation(
            $tenant,
            $pickupLocation,
            $nameEn,
            $nameFr,
            $townId,
            $quarterId,
            $address,
        );

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'edit_town_id' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['town_id' => $result['error']])->withInput();
        }

        // Activity logging with old/new value comparison
        $newValues = [
            'name_en' => $nameEn,
            'name_fr' => $nameFr,
            'town_id' => $townId,
            'quarter_id' => $quarterId,
            'address' => $address,
        ];

        $changes = array_filter($newValues, fn ($value, $key) => $oldValues[$key] != $value, ARRAY_FILTER_USE_BOTH);

        if (! empty($changes)) {
            activity('pickup_locations')
                ->performedOn($result['pickup_model'])
                ->causedBy($user)
                ->withProperties([
                    'action' => 'pickup_location_updated',
                    'old' => array_intersect_key($oldValues, $changes),
                    'new' => $changes,
                    'tenant_id' => $tenant->id,
                ])
                ->log('Pickup location updated');
        }

        // BR-298: Gale redirect with toast (list updates without page reload)
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/locations/pickup'))
                ->with('success', __('Pickup location updated successfully.'));
        }

        return redirect()->route('cook.locations.pickup.index')
            ->with('success', __('Pickup location updated successfully.'));
    }
}
