<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreMealComponentRequest;
use App\Http\Requests\Cook\UpdateMealComponentRequest;
use App\Services\MealComponentService;
use Illuminate\Http\Request;

class MealComponentController extends Controller
{
    /**
     * Store a new meal component.
     *
     * F-118: Meal Component Creation
     * BR-278: Component name required in both EN and FR
     * BR-279: Name max 150 characters per language
     * BR-280: Price required, minimum 1 XAF
     * BR-281: Selling unit required
     * BR-287: Components are meal-scoped and tenant-scoped
     * BR-288: Only users with manage-meals permission
     * BR-289: Component creation logged via Spatie Activitylog
     */
    public function store(Request $request, int $mealId, MealComponentService $componentService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-288: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        // Meal must belong to current tenant
        $meal = $tenant->meals()->findOrFail($mealId);

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'comp_name_en' => ['required', 'string', 'max:150'],
                'comp_name_fr' => ['required', 'string', 'max:150'],
                'comp_price' => ['required', 'integer', 'min:1'],
                'comp_selling_unit' => ['required', 'string', 'max:50'],
                'comp_min_quantity' => ['nullable', 'integer', 'min:0'],
                'comp_max_quantity' => ['nullable', 'integer', 'min:1'],
                'comp_available_quantity' => ['nullable', 'integer', 'min:0'],
            ], [
                'comp_name_en.required' => __('Component name is required in English.'),
                'comp_name_en.max' => __('Component name must not exceed :max characters.'),
                'comp_name_fr.required' => __('Component name is required in French.'),
                'comp_name_fr.max' => __('Component name must not exceed :max characters.'),
                'comp_price.required' => __('Price is required.'),
                'comp_price.integer' => __('Price must be a whole number.'),
                'comp_price.min' => __('Price must be at least 1 XAF.'),
                'comp_selling_unit.required' => __('Selling unit is required.'),
                'comp_min_quantity.integer' => __('Minimum quantity must be a whole number.'),
                'comp_min_quantity.min' => __('Minimum quantity cannot be negative.'),
                'comp_max_quantity.integer' => __('Maximum quantity must be a whole number.'),
                'comp_max_quantity.min' => __('Maximum quantity must be at least 1.'),
                'comp_available_quantity.integer' => __('Available quantity must be a whole number.'),
                'comp_available_quantity.min' => __('Available quantity cannot be negative.'),
            ]);

            // Map prefixed keys to service data format
            $data = [
                'name_en' => $validated['comp_name_en'],
                'name_fr' => $validated['comp_name_fr'],
                'price' => $validated['comp_price'],
                'selling_unit' => $validated['comp_selling_unit'],
                'min_quantity' => $validated['comp_min_quantity'] ?? 0,
                'max_quantity' => $validated['comp_max_quantity'] ?? null,
                'available_quantity' => $validated['comp_available_quantity'] ?? null,
            ];
        } else {
            $formRequest = app(StoreMealComponentRequest::class);
            $data = $formRequest->validated();
        }

        // Use service for business logic
        $result = $componentService->createComponent($meal, $data);

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'comp_price' => $result['error'],
                ]);
            }

            return redirect()->back()
                ->withErrors(['price' => $result['error']])
                ->withInput();
        }

        $component = $result['component'];

        // BR-289: Activity logging
        activity('meal_components')
            ->performedOn($component)
            ->causedBy($user)
            ->withProperties([
                'action' => 'component_created',
                'name_en' => $component->name_en,
                'name_fr' => $component->name_fr,
                'price' => $component->price,
                'selling_unit' => $component->selling_unit,
                'meal_id' => $meal->id,
                'meal_name' => $meal->name_en,
                'tenant_id' => $tenant->id,
            ])
            ->log('Meal component created');

        $redirectUrl = url('/dashboard/meals/'.$meal->id.'/edit');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', __('Component added.'));
        }

        return redirect($redirectUrl)
            ->with('success', __('Component added.'));
    }

    /**
     * Update an existing meal component.
     *
     * F-119: Meal Component Edit
     * BR-292: All validation rules from F-118 apply to edits
     * BR-293: Price changes apply to new orders only
     * BR-294: Name, selling unit, and quantity changes take effect immediately
     * BR-295: Component edits are logged via Spatie Activitylog with old and new values
     * BR-296: Only users with manage-meals permission
     * BR-297: If available quantity is edited to 0, auto-toggle to unavailable
     */
    public function update(Request $request, int $mealId, int $componentId, MealComponentService $componentService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-296: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        // Meal must belong to current tenant
        $meal = $tenant->meals()->findOrFail($mealId);

        // Component must belong to meal
        $component = $meal->components()->findOrFail($componentId);

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'edit_comp_name_en' => ['required', 'string', 'max:150'],
                'edit_comp_name_fr' => ['required', 'string', 'max:150'],
                'edit_comp_price' => ['required', 'integer', 'min:1'],
                'edit_comp_selling_unit' => ['required', 'string', 'max:50'],
                'edit_comp_min_quantity' => ['nullable', 'integer', 'min:0'],
                'edit_comp_max_quantity' => ['nullable', 'integer', 'min:1'],
                'edit_comp_available_quantity' => ['nullable', 'integer', 'min:0'],
            ], [
                'edit_comp_name_en.required' => __('Component name is required in English.'),
                'edit_comp_name_en.max' => __('Component name must not exceed :max characters.'),
                'edit_comp_name_fr.required' => __('Component name is required in French.'),
                'edit_comp_name_fr.max' => __('Component name must not exceed :max characters.'),
                'edit_comp_price.required' => __('Price is required.'),
                'edit_comp_price.integer' => __('Price must be a whole number.'),
                'edit_comp_price.min' => __('Price must be at least 1 XAF.'),
                'edit_comp_selling_unit.required' => __('Selling unit is required.'),
                'edit_comp_min_quantity.integer' => __('Minimum quantity must be a whole number.'),
                'edit_comp_min_quantity.min' => __('Minimum quantity cannot be negative.'),
                'edit_comp_max_quantity.integer' => __('Maximum quantity must be a whole number.'),
                'edit_comp_max_quantity.min' => __('Maximum quantity must be at least 1.'),
                'edit_comp_available_quantity.integer' => __('Available quantity must be a whole number.'),
                'edit_comp_available_quantity.min' => __('Available quantity cannot be negative.'),
            ]);

            // Map prefixed keys to service data format
            $data = [
                'name_en' => $validated['edit_comp_name_en'],
                'name_fr' => $validated['edit_comp_name_fr'],
                'price' => $validated['edit_comp_price'],
                'selling_unit' => $validated['edit_comp_selling_unit'],
                'min_quantity' => $validated['edit_comp_min_quantity'] ?? 0,
                'max_quantity' => $validated['edit_comp_max_quantity'] ?? null,
                'available_quantity' => $validated['edit_comp_available_quantity'] ?? null,
            ];
        } else {
            $formRequest = app(UpdateMealComponentRequest::class);
            $data = $formRequest->validated();
        }

        // Use service for business logic
        $result = $componentService->updateComponent($component, $data);

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'edit_comp_price' => $result['error'],
                ]);
            }

            return redirect()->back()
                ->withErrors(['price' => $result['error']])
                ->withInput();
        }

        $updatedComponent = $result['component'];
        $oldValues = $result['old_values'];

        // BR-295: Activity logging with old and new values
        $newValues = [
            'name_en' => $updatedComponent->name_en,
            'name_fr' => $updatedComponent->name_fr,
            'price' => $updatedComponent->price,
            'selling_unit' => $updatedComponent->selling_unit,
            'min_quantity' => $updatedComponent->min_quantity,
            'max_quantity' => $updatedComponent->max_quantity,
            'available_quantity' => $updatedComponent->available_quantity,
            'is_available' => $updatedComponent->is_available,
        ];

        // Only log if there are actual changes
        $changes = array_diff_assoc(
            array_map('strval', $newValues),
            array_map('strval', array_map(fn ($v) => $v ?? '', $oldValues))
        );

        if (! empty($changes)) {
            $logProperties = [
                'action' => 'component_updated',
                'meal_id' => $meal->id,
                'meal_name' => $meal->name_en,
                'tenant_id' => $tenant->id,
                'old' => array_intersect_key($oldValues, $changes),
                'new' => array_intersect_key($newValues, $changes),
            ];

            activity('meal_components')
                ->performedOn($updatedComponent)
                ->causedBy($user)
                ->withProperties($logProperties)
                ->log('Meal component updated');
        }

        $redirectUrl = url('/dashboard/meals/'.$meal->id.'/edit');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', __('Component updated.'));
        }

        return redirect($redirectUrl)
            ->with('success', __('Component updated.'));
    }

    /**
     * Toggle a meal component's availability.
     *
     * F-123: Meal Component Availability Toggle
     * BR-326: Component availability is independent of meal availability (F-113)
     * BR-327: Unavailable components display a "Sold Out" badge to clients
     * BR-329: Toggling availability does not affect pending orders
     * BR-330: Availability toggle has immediate effect
     * BR-331: Availability changes are logged via Spatie Activitylog
     * BR-332: Only users with manage-meals permission can toggle availability
     */
    public function toggleAvailability(Request $request, int $mealId, int $componentId, MealComponentService $componentService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-332: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        // Meal must belong to current tenant
        $meal = $tenant->meals()->findOrFail($mealId);

        // Component must belong to meal
        $component = $meal->components()->findOrFail($componentId);

        // Use service for business logic
        $result = $componentService->toggleAvailability($component);

        // BR-331: Activity logging
        activity('meal_components')
            ->performedOn($result['component'])
            ->causedBy($user)
            ->withProperties([
                'action' => 'component_availability_toggled',
                'old_availability' => $result['old_availability'],
                'new_availability' => $result['new_availability'],
                'name_en' => $result['component']->name_en,
                'name_fr' => $result['component']->name_fr,
                'meal_id' => $meal->id,
                'meal_name' => $meal->name_en,
                'tenant_id' => $tenant->id,
            ])
            ->log('Component availability changed from '.($result['old_availability'] ? 'available' : 'unavailable').' to '.($result['new_availability'] ? 'available' : 'unavailable'));

        // Toast message based on new availability
        $componentName = $result['component']->name;
        $toastMessage = $result['new_availability']
            ? __('Component ":name" is now available.', ['name' => $componentName])
            : __('Component ":name" is now unavailable.', ['name' => $componentName]);

        $redirectUrl = url('/dashboard/meals/'.$meal->id.'/edit');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', $toastMessage);
        }

        return redirect($redirectUrl)
            ->with('success', $toastMessage);
    }

    /**
     * Delete a meal component.
     *
     * F-120: Meal Component Delete
     * BR-298: Cannot delete the last component of a live meal
     * BR-299: Cannot delete a component if pending orders include it
     * BR-300: Components are hard-deleted
     * BR-301: Confirmation dialog is shown on the frontend
     * BR-302: Component deletion is logged via Spatie Activitylog
     * BR-303: Only users with manage-meals permission
     * BR-304: Remaining positions are recalculated (service layer)
     * BR-305: Requirement rules are cleaned up (service layer)
     */
    public function destroy(Request $request, int $mealId, int $componentId, MealComponentService $componentService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-303: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        // Meal must belong to current tenant
        $meal = $tenant->meals()->findOrFail($mealId);

        // Component must belong to meal
        $component = $meal->components()->findOrFail($componentId);

        // Capture data for activity logging before deletion
        $componentData = [
            'name_en' => $component->name_en,
            'name_fr' => $component->name_fr,
            'price' => $component->price,
            'selling_unit' => $component->selling_unit,
            'position' => $component->position,
        ];

        // Use service for business logic
        $result = $componentService->deleteComponent($component);

        if (! $result['success']) {
            $redirectUrl = url('/dashboard/meals/'.$meal->id.'/edit');

            if ($request->isGale()) {
                return gale()
                    ->redirect($redirectUrl)
                    ->with('error', $result['error']);
            }

            return redirect($redirectUrl)
                ->with('error', $result['error']);
        }

        // BR-302: Activity logging
        activity('meal_components')
            ->causedBy($user)
            ->withProperties([
                'action' => 'component_deleted',
                'name_en' => $componentData['name_en'],
                'name_fr' => $componentData['name_fr'],
                'price' => $componentData['price'],
                'selling_unit' => $componentData['selling_unit'],
                'meal_id' => $meal->id,
                'meal_name' => $meal->name_en,
                'tenant_id' => $tenant->id,
            ])
            ->log('Meal component deleted');

        $redirectUrl = url('/dashboard/meals/'.$meal->id.'/edit');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', __('Component deleted.'));
        }

        return redirect($redirectUrl)
            ->with('success', __('Component deleted.'));
    }
}
