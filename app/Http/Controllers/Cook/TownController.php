<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreTownRequest;
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

        return gale()->view('cook.locations.index', [
            'deliveryAreas' => $deliveryAreas,
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
}
