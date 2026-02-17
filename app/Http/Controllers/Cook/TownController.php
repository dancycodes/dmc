<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreTownRequest;
use App\Http\Requests\Cook\UpdateTownRequest;
use App\Services\DeliveryAreaService;
use Illuminate\Http\Request;

class TownController extends Controller
{
    /**
     * Display the locations page with town list and add town form.
     *
     * F-082: Add Town
     * F-083: Town List View (stub for future feature)
     * BR-212: Only users with location management permission can access
     */
    public function index(Request $request, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-212: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        $deliveryAreas = $deliveryAreaService->getDeliveryAreasData($tenant);
        $quarterGroups = $deliveryAreaService->getQuarterGroupsData($tenant);
        $quartersForGroupAssignment = $deliveryAreaService->getQuartersForGroupAssignment($tenant);

        return gale()->view('cook.locations.index', [
            'deliveryAreas' => $deliveryAreas,
            'quarterGroups' => $quarterGroups,
            'quartersForGroupAssignment' => $quartersForGroupAssignment,
        ], web: true);
    }

    /**
     * Store a new town in the cook's delivery areas.
     *
     * F-082: Add Town
     * BR-207: Town name required in both EN and FR
     * BR-208: Town name must be unique within this cook's towns (per language)
     * BR-209: Town is scoped to the current tenant (via delivery_areas junction)
     * BR-210: Save via Gale; town appears in list without page reload
     * BR-211: All validation messages use __() localization
     * BR-212: Only users with location management permission can add towns
     */
    public function store(Request $request, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-212: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'name_en' => ['required', 'string', 'max:255'],
                'name_fr' => ['required', 'string', 'max:255'],
            ], [
                'name_en.required' => __('Town name is required in English.'),
                'name_en.max' => __('Town name must not exceed 255 characters.'),
                'name_fr.required' => __('Town name is required in French.'),
                'name_fr.max' => __('Town name must not exceed 255 characters.'),
            ]);
        } else {
            $formRequest = app(StoreTownRequest::class);
            $validated = $formRequest->validated();
        }

        // BR-208: Trim whitespace before storage and uniqueness check
        $nameEn = trim($validated['name_en']);
        $nameFr = trim($validated['name_fr']);

        // Use DeliveryAreaService for business logic (creates town + delivery area link)
        $result = $deliveryAreaService->addTown($tenant, $nameEn, $nameFr);

        if (! $result['success']) {
            // BR-208: Uniqueness violation
            if ($request->isGale()) {
                return gale()->messages([
                    'name_en' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['name_en' => $result['error']])->withInput();
        }

        // Activity logging
        activity('delivery_areas')
            ->performedOn($result['delivery_area'])
            ->causedBy($user)
            ->withProperties([
                'action' => 'town_added',
                'town_name_en' => $nameEn,
                'town_name_fr' => $nameFr,
                'tenant_id' => $tenant->id,
            ])
            ->log('Town added to delivery areas');

        // BR-210: Gale redirect with toast (town appears in list without page reload)
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/locations'))
                ->with('success', __('Town added successfully.'));
        }

        return redirect()->route('cook.locations.index')
            ->with('success', __('Town added successfully.'));
    }

    /**
     * Update a town's name in the cook's delivery areas.
     *
     * F-084: Edit Town
     * BR-219: Town name required in both EN and FR
     * BR-220: Edited town name must remain unique within this cook's towns (excluding the current town)
     * BR-221: Save via Gale; list updates without page reload
     * BR-223: Edit action requires location management permission
     * BR-224: All validation messages use __() localization
     */
    public function update(Request $request, int $deliveryArea, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-223: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'edit_name_en' => ['required', 'string', 'max:255'],
                'edit_name_fr' => ['required', 'string', 'max:255'],
            ], [
                'edit_name_en.required' => __('Town name is required in English.'),
                'edit_name_en.max' => __('Town name must not exceed 255 characters.'),
                'edit_name_fr.required' => __('Town name is required in French.'),
                'edit_name_fr.max' => __('Town name must not exceed 255 characters.'),
            ]);
        } else {
            $formRequest = app(UpdateTownRequest::class);
            $validated = $formRequest->validated();
            // Map standard keys for HTTP path
            $validated['edit_name_en'] = $validated['name_en'];
            $validated['edit_name_fr'] = $validated['name_fr'];
        }

        // BR-219: Trim whitespace before storage and uniqueness check
        $nameEn = trim($validated['edit_name_en']);
        $nameFr = trim($validated['edit_name_fr']);

        // Use DeliveryAreaService for business logic
        $result = $deliveryAreaService->updateTown($tenant, $deliveryArea, $nameEn, $nameFr);

        if (! $result['success']) {
            // BR-220: Uniqueness violation
            if ($request->isGale()) {
                return gale()->messages([
                    'edit_name_en' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['name_en' => $result['error']])->withInput();
        }

        // Activity logging
        activity('delivery_areas')
            ->causedBy($user)
            ->withProperties([
                'action' => 'town_updated',
                'town_name_en' => $nameEn,
                'town_name_fr' => $nameFr,
                'tenant_id' => $tenant->id,
                'delivery_area_id' => $deliveryArea,
            ])
            ->log('Town name updated');

        // BR-221: Gale redirect with toast (list updates without page reload)
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/locations'))
                ->with('success', __('Town updated successfully.'));
        }

        return redirect()->route('cook.locations.index')
            ->with('success', __('Town updated successfully.'));
    }

    /**
     * Delete a town from the cook's delivery areas.
     *
     * F-085: Delete Town
     * BR-225: Cannot delete a town with active (non-completed, non-cancelled) orders
     * BR-226: Deleting a town cascade-deletes all its quarters and their delivery fees
     * BR-227: Deleting a town cascade-removes quarters from any quarter groups
     * BR-228: Confirmation dialog must be shown before deletion (handled in blade)
     * BR-229: On success, toast notification confirms "Town deleted successfully"
     * BR-230: Delete action requires location management permission
     * BR-231: Town list updates via Gale without page reload after deletion
     */
    public function destroy(Request $request, int $deliveryArea, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-230: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        $result = $deliveryAreaService->removeTown($tenant, $deliveryArea);

        if (! $result['success']) {
            // BR-225: Active orders block deletion
            if ($request->isGale()) {
                return gale()
                    ->redirect(url('/dashboard/locations'))
                    ->with('error', $result['error']);
            }

            return redirect()->route('cook.locations.index')
                ->with('error', $result['error']);
        }

        // Activity logging
        activity('delivery_areas')
            ->causedBy($user)
            ->withProperties([
                'action' => 'town_deleted',
                'town_name' => $result['town_name'],
                'quarters_deleted' => $result['quarter_count'],
                'tenant_id' => $tenant->id,
                'delivery_area_id' => $deliveryArea,
            ])
            ->log('Town deleted from delivery areas');

        // BR-229 + BR-231: Toast notification and list update via Gale
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/locations'))
                ->with('success', __('Town deleted successfully.'));
        }

        return redirect()->route('cook.locations.index')
            ->with('success', __('Town deleted successfully.'));
    }
}
