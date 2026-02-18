<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreMealComponentRequest;
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
}
