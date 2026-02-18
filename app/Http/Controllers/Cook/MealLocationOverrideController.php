<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\UpdateMealLocationOverrideRequest;
use App\Services\MealLocationOverrideService;
use Illuminate\Http\Request;

class MealLocationOverrideController extends Controller
{
    public function __construct(
        private MealLocationOverrideService $overrideService,
    ) {}

    /**
     * Get location override data for a meal (used by the edit page).
     *
     * F-096: Meal-Specific Location Override
     * Returns JSON data for the Alpine.js component to build the override UI.
     */
    public function getData(Request $request, int $mealId): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // Permission check: needs both meal and location management
        if (! $user->can('can-manage-meals') || ! $user->can('can-manage-delivery-areas')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);

        $data = $this->overrideService->getLocationOverrideData($tenant, $meal);

        return response()->json($data);
    }

    /**
     * Save location override for a meal.
     *
     * F-096: Meal-Specific Location Override
     * BR-307: When "Use custom locations" is enabled, the meal uses only the selected locations.
     * BR-310: A meal with custom locations must have at least one location selected.
     * BR-311: Toggling off removes all overrides and reverts to global settings.
     * BR-312: Location override changes are logged via Spatie Activitylog.
     */
    public function update(Request $request, int $mealId): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // Permission check
        if (! $user->can('can-manage-meals') || ! $user->can('can-manage-delivery-areas')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'has_custom_locations' => ['required', 'boolean'],
                'quarters' => ['array'],
                'quarters.*.quarter_id' => ['required_with:quarters', 'integer'],
                'quarters.*.custom_fee' => ['nullable', 'integer', 'min:0'],
                'pickup_location_ids' => ['array'],
                'pickup_location_ids.*' => ['integer'],
            ], [
                'quarters.*.custom_fee.min' => __('Delivery fee must be at least :min XAF.'),
            ]);
        } else {
            $formRequest = app(UpdateMealLocationOverrideRequest::class);
            $validated = $formRequest->validated();
        }

        $hasCustom = (bool) $validated['has_custom_locations'];

        // BR-311: If toggling off, remove all overrides
        if (! $hasCustom) {
            $oldOverrideCount = $meal->locationOverrides()->count();
            $result = $this->overrideService->removeOverrides($meal);

            // BR-312: Activity logging
            if ($oldOverrideCount > 0) {
                activity('meals')
                    ->performedOn($meal)
                    ->causedBy($user)
                    ->withProperties([
                        'action' => 'location_override_disabled',
                        'removed_override_count' => $oldOverrideCount,
                        'tenant_id' => $tenant->id,
                    ])
                    ->log('Location overrides disabled');
            }

            return $this->successResponse($request, __('Location settings reverted to default.'));
        }

        // BR-307: Enable custom locations and save selections
        $quarters = $validated['quarters'] ?? [];
        $pickupIds = $validated['pickup_location_ids'] ?? [];

        // Convert to proper types
        $quarterData = [];
        foreach ($quarters as $q) {
            $quarterData[] = [
                'quarter_id' => (int) $q['quarter_id'],
                'custom_fee' => isset($q['custom_fee']) && $q['custom_fee'] !== '' ? (int) $q['custom_fee'] : null,
            ];
        }
        $pickupIds = array_map('intval', $pickupIds);

        $result = $this->overrideService->saveOverrides($tenant, $meal, $quarterData, $pickupIds);

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'locations' => $result['error'],
                ]);
            }

            return redirect()->back()
                ->withErrors(['locations' => $result['error']])
                ->withInput();
        }

        // BR-312: Activity logging
        activity('meals')
            ->performedOn($meal)
            ->causedBy($user)
            ->withProperties([
                'action' => 'location_override_updated',
                'quarter_count' => count($quarterData),
                'pickup_count' => count($pickupIds),
                'has_custom_fees' => collect($quarterData)->whereNotNull('custom_fee')->isNotEmpty(),
                'tenant_id' => $tenant->id,
            ])
            ->log('Location overrides updated');

        return $this->successResponse($request, __('Custom locations saved successfully.'));
    }

    /**
     * Return a success response for both Gale and HTTP.
     */
    private function successResponse(Request $request, string $message): mixed
    {
        if ($request->isGale()) {
            return gale()
                ->redirect($request->header('Referer', url('/dashboard/meals')))
                ->back()
                ->with('success', $message);
        }

        return redirect()->back()
            ->with('success', $message);
    }
}
