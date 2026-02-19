<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreSellingUnitRequest;
use App\Http\Requests\Cook\UpdateSellingUnitRequest;
use App\Models\SellingUnit;
use App\Services\SellingUnitService;
use Illuminate\Http\Request;

class SellingUnitController extends Controller
{
    /**
     * Display the selling units management page.
     *
     * F-121: Custom Selling Unit Definition
     * Scenario 1: View standard and custom units
     * BR-312: Only users with manage-meals permission
     */
    public function index(Request $request, SellingUnitService $unitService): mixed
    {
        $user = $request->user();

        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $tenant = tenant();
        $units = $unitService->getUnitsForTenant($tenant);

        // Pre-compute delete info for each unit
        $deleteInfo = [];
        foreach ($units as $unit) {
            $deleteInfo[$unit->id] = $unitService->canDeleteUnit($unit);
        }

        return gale()->view('cook.selling-units.index', [
            'units' => $units,
            'deleteInfo' => $deleteInfo,
        ], web: true);
    }

    /**
     * Store a new custom selling unit.
     *
     * Scenario 2: Create a custom unit
     * BR-308: Custom units are tenant-scoped
     * BR-309: Name required in both EN and FR
     * BR-310: Name unique within tenant and against standard units
     * BR-312: Only users with manage-meals permission
     * BR-313: CRUD logged via Spatie Activitylog
     * BR-314: Name max 50 characters
     */
    public function store(Request $request, SellingUnitService $unitService): mixed
    {
        $user = $request->user();

        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $tenant = tenant();

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'unit_name_en' => ['required', 'string', 'max:'.SellingUnit::NAME_MAX_LENGTH],
                'unit_name_fr' => ['required', 'string', 'max:'.SellingUnit::NAME_MAX_LENGTH],
            ], [
                'unit_name_en.required' => __('Unit name is required in English.'),
                'unit_name_en.max' => __('Unit name must not exceed :max characters.'),
                'unit_name_fr.required' => __('Unit name is required in French.'),
                'unit_name_fr.max' => __('Unit name must not exceed :max characters.'),
            ]);

            $data = [
                'name_en' => $validated['unit_name_en'],
                'name_fr' => $validated['unit_name_fr'],
            ];
        } else {
            $formRequest = app(StoreSellingUnitRequest::class);
            $data = $formRequest->validated();
        }

        $result = $unitService->createUnit($tenant, $data);

        if (! $result['success']) {
            $errorField = $result['error_field'] ?? 'unit_name_en';

            if ($request->isGale()) {
                return gale()->messages([
                    'unit_'.$errorField => $result['error'],
                ]);
            }

            return redirect()->back()
                ->withErrors([$errorField => $result['error']])
                ->withInput();
        }

        $unit = $result['unit'];

        // BR-313: Activity logging
        activity('selling_units')
            ->performedOn($unit)
            ->causedBy($user)
            ->withProperties([
                'action' => 'selling_unit_created',
                'name_en' => $unit->name_en,
                'name_fr' => $unit->name_fr,
                'tenant_id' => $tenant->id,
            ])
            ->log('Custom selling unit created');

        $redirectUrl = url('/dashboard/selling-units');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', __('Selling unit created.'));
        }

        return redirect($redirectUrl)
            ->with('success', __('Selling unit created.'));
    }

    /**
     * Update a custom selling unit.
     *
     * Scenario 3: Edit a custom unit
     * BR-307: Standard units cannot be edited
     * BR-310: Name uniqueness enforced on update
     * BR-312: Only users with manage-meals permission
     * BR-313: CRUD logged via Spatie Activitylog
     */
    public function update(Request $request, int $unitId, SellingUnitService $unitService): mixed
    {
        $user = $request->user();

        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $tenant = tenant();

        // Unit must belong to current tenant (custom units only)
        $unit = SellingUnit::where('tenant_id', $tenant->id)
            ->custom()
            ->findOrFail($unitId);

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'edit_name_en' => ['required', 'string', 'max:'.SellingUnit::NAME_MAX_LENGTH],
                'edit_name_fr' => ['required', 'string', 'max:'.SellingUnit::NAME_MAX_LENGTH],
            ], [
                'edit_name_en.required' => __('Unit name is required in English.'),
                'edit_name_en.max' => __('Unit name must not exceed :max characters.'),
                'edit_name_fr.required' => __('Unit name is required in French.'),
                'edit_name_fr.max' => __('Unit name must not exceed :max characters.'),
            ]);

            $data = [
                'name_en' => $validated['edit_name_en'],
                'name_fr' => $validated['edit_name_fr'],
            ];
        } else {
            $formRequest = app(UpdateSellingUnitRequest::class);
            $data = $formRequest->validated();
        }

        $result = $unitService->updateUnit($unit, $data);

        if (! $result['success']) {
            $errorField = $result['error_field'] ?? 'edit_name_en';

            if ($request->isGale()) {
                return gale()->messages([
                    $errorField => $result['error'],
                ]);
            }

            return redirect()->back()
                ->withErrors([$errorField => $result['error']])
                ->withInput();
        }

        $updatedUnit = $result['unit'];
        $oldValues = $result['old_values'];

        // BR-313: Activity logging with old/new values
        $newValues = [
            'name_en' => $updatedUnit->name_en,
            'name_fr' => $updatedUnit->name_fr,
        ];

        $changes = array_diff_assoc($newValues, $oldValues);

        if (! empty($changes)) {
            activity('selling_units')
                ->performedOn($updatedUnit)
                ->causedBy($user)
                ->withProperties([
                    'action' => 'selling_unit_updated',
                    'tenant_id' => $tenant->id,
                    'old' => array_intersect_key($oldValues, $changes),
                    'new' => array_intersect_key($newValues, $changes),
                ])
                ->log('Custom selling unit updated');
        }

        $redirectUrl = url('/dashboard/selling-units');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', __('Selling unit updated.'));
        }

        return redirect($redirectUrl)
            ->with('success', __('Selling unit updated.'));
    }

    /**
     * Delete a custom selling unit.
     *
     * Scenario 4/5: Delete an unused/used custom unit
     * BR-307: Standard units cannot be deleted
     * BR-311: Cannot delete if used by any meal component
     * BR-312: Only users with manage-meals permission
     * BR-313: CRUD logged via Spatie Activitylog
     */
    public function destroy(Request $request, int $unitId, SellingUnitService $unitService): mixed
    {
        $user = $request->user();

        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $tenant = tenant();

        // Unit must belong to current tenant (custom units only)
        $unit = SellingUnit::where('tenant_id', $tenant->id)
            ->custom()
            ->findOrFail($unitId);

        // Capture data for activity logging before deletion
        $unitData = [
            'name_en' => $unit->name_en,
            'name_fr' => $unit->name_fr,
        ];

        $result = $unitService->deleteUnit($unit);

        $redirectUrl = url('/dashboard/selling-units');

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()
                    ->redirect($redirectUrl)
                    ->with('error', $result['error']);
            }

            return redirect($redirectUrl)
                ->with('error', $result['error']);
        }

        // BR-313: Activity logging
        activity('selling_units')
            ->causedBy($user)
            ->withProperties([
                'action' => 'selling_unit_deleted',
                'name_en' => $unitData['name_en'],
                'name_fr' => $unitData['name_fr'],
                'tenant_id' => $tenant->id,
            ])
            ->log('Custom selling unit deleted');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', __('Selling unit deleted.'));
        }

        return redirect($redirectUrl)
            ->with('success', __('Selling unit deleted.'));
    }
}
