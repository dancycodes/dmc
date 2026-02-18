<?php

namespace App\Services;

use App\Models\DeliveryArea;
use App\Models\DeliveryAreaQuarter;
use App\Models\Meal;
use App\Models\MealLocationOverride;
use App\Models\PickupLocation;
use App\Models\QuarterGroup;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class MealLocationOverrideService
{
    /**
     * Get all location data for the override configuration UI.
     *
     * Returns the cook's delivery areas (towns > quarters) and pickup locations,
     * plus the current override selections for this meal.
     *
     * @return array{delivery_areas: array, pickup_locations: array, current_overrides: array}
     */
    public function getLocationOverrideData(Tenant $tenant, Meal $meal): array
    {
        $locale = app()->getLocale();

        // Get the cook's delivery areas with quarters
        $deliveryAreas = $this->getDeliveryAreasForOverride($tenant, $locale);

        // Get the cook's pickup locations
        $pickupLocations = $this->getPickupLocationsForOverride($tenant, $locale);

        // Get current overrides for this meal
        $currentOverrides = $this->getCurrentOverrides($meal, $locale);

        return [
            'delivery_areas' => $deliveryAreas,
            'pickup_locations' => $pickupLocations,
            'current_overrides' => $currentOverrides,
        ];
    }

    /**
     * Get delivery areas organized by town for override selection.
     *
     * @return array<int, array{id: int, town_name: string, quarters: array}>
     */
    private function getDeliveryAreasForOverride(Tenant $tenant, string $locale): array
    {
        $orderColumn = 'name_'.$locale;

        $deliveryAreas = DeliveryArea::query()
            ->where('tenant_id', $tenant->id)
            ->with(['town', 'deliveryAreaQuarters.quarter'])
            ->join('towns', 'delivery_areas.town_id', '=', 'towns.id')
            ->orderBy('towns.'.$orderColumn)
            ->select('delivery_areas.*')
            ->get();

        return $deliveryAreas->map(function (DeliveryArea $area) use ($locale) {
            return [
                'id' => $area->id,
                'town_name' => $area->town->{'name_'.$locale} ?? $area->town->name_en,
                'quarters' => $area->deliveryAreaQuarters
                    ->sortBy(fn (DeliveryAreaQuarter $daq) => mb_strtolower($daq->quarter->{'name_'.$locale} ?? $daq->quarter->name_en))
                    ->map(function (DeliveryAreaQuarter $daq) use ($locale) {
                        // Check group membership for effective fee
                        $group = QuarterGroup::query()
                            ->whereHas('quarters', function ($q) use ($daq) {
                                $q->where('quarters.id', $daq->quarter_id);
                            })
                            ->first();

                        $effectiveFee = $group ? $group->delivery_fee : $daq->delivery_fee;

                        return [
                            'quarter_id' => $daq->quarter_id,
                            'quarter_name' => $daq->quarter->{'name_'.$locale} ?? $daq->quarter->name_en,
                            'default_fee' => $effectiveFee,
                            'group_name' => $group?->name,
                        ];
                    })->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Get pickup locations for override selection.
     *
     * @return array<int, array{id: int, name: string, town_name: string, quarter_name: string, address: string}>
     */
    private function getPickupLocationsForOverride(Tenant $tenant, string $locale): array
    {
        $nameColumn = 'name_'.$locale;

        $locations = PickupLocation::query()
            ->where('tenant_id', $tenant->id)
            ->with(['town', 'quarter'])
            ->orderBy($nameColumn)
            ->get();

        return $locations->map(function (PickupLocation $loc) use ($locale) {
            return [
                'id' => $loc->id,
                'name' => $loc->{'name_'.$locale} ?? $loc->name_en,
                'town_name' => $loc->town ? ($loc->town->{'name_'.$locale} ?? $loc->town->name_en) : '',
                'quarter_name' => $loc->quarter ? ($loc->quarter->{'name_'.$locale} ?? $loc->quarter->name_en) : '',
                'address' => $loc->address,
            ];
        })->values()->all();
    }

    /**
     * Get current override selections for a meal.
     *
     * @return array{selected_quarters: array, selected_pickups: array}
     */
    private function getCurrentOverrides(Meal $meal, string $locale): array
    {
        $overrides = MealLocationOverride::query()
            ->where('meal_id', $meal->id)
            ->get();

        $selectedQuarters = [];
        $selectedPickups = [];

        foreach ($overrides as $override) {
            if ($override->quarter_id !== null) {
                $selectedQuarters[] = [
                    'quarter_id' => $override->quarter_id,
                    'custom_fee' => $override->custom_delivery_fee,
                ];
            }
            if ($override->pickup_location_id !== null) {
                $selectedPickups[] = $override->pickup_location_id;
            }
        }

        return [
            'selected_quarters' => $selectedQuarters,
            'selected_pickups' => $selectedPickups,
        ];
    }

    /**
     * Enable custom locations for a meal and save the override selections.
     *
     * BR-307: When "Use custom locations" is enabled, the meal uses only the selected locations.
     * BR-308: Custom locations can include a subset of the cook's delivery quarters and/or pickup locations.
     * BR-309: Custom delivery fees can be set per-quarter for this meal.
     * BR-310: A meal with custom locations must have at least one location selected.
     * BR-314: The meal must still be within the cook's existing locations.
     *
     * @param  array<int, array{quarter_id: int, custom_fee: int|null}>  $quarters
     * @param  array<int>  $pickupLocationIds
     * @return array{success: bool, error: string}
     */
    public function saveOverrides(Tenant $tenant, Meal $meal, array $quarters, array $pickupLocationIds): array
    {
        // BR-310: Must have at least one location
        if (empty($quarters) && empty($pickupLocationIds)) {
            return [
                'success' => false,
                'error' => __('At least one delivery quarter or pickup location must be selected.'),
            ];
        }

        // BR-314: Validate all quarter_ids belong to this tenant
        if (! empty($quarters)) {
            $tenantQuarterIds = DeliveryAreaQuarter::query()
                ->whereHas('deliveryArea', function ($q) use ($tenant) {
                    $q->where('tenant_id', $tenant->id);
                })
                ->pluck('quarter_id')
                ->all();

            $requestedQuarterIds = array_column($quarters, 'quarter_id');
            $invalidQuarters = array_diff($requestedQuarterIds, $tenantQuarterIds);

            if (! empty($invalidQuarters)) {
                return [
                    'success' => false,
                    'error' => __('Some selected quarters do not belong to your delivery areas.'),
                ];
            }
        }

        // BR-314: Validate all pickup location IDs belong to this tenant
        if (! empty($pickupLocationIds)) {
            $tenantPickupIds = PickupLocation::query()
                ->where('tenant_id', $tenant->id)
                ->pluck('id')
                ->all();

            $invalidPickups = array_diff($pickupLocationIds, $tenantPickupIds);

            if (! empty($invalidPickups)) {
                return [
                    'success' => false,
                    'error' => __('Some selected pickup locations do not belong to your business.'),
                ];
            }
        }

        DB::transaction(function () use ($meal, $quarters, $pickupLocationIds) {
            // Delete existing overrides
            MealLocationOverride::where('meal_id', $meal->id)->delete();

            // Create delivery quarter overrides
            foreach ($quarters as $quarterData) {
                MealLocationOverride::create([
                    'meal_id' => $meal->id,
                    'quarter_id' => $quarterData['quarter_id'],
                    'pickup_location_id' => null,
                    'custom_delivery_fee' => $quarterData['custom_fee'] ?? null,
                ]);
            }

            // Create pickup location overrides
            foreach ($pickupLocationIds as $pickupId) {
                MealLocationOverride::create([
                    'meal_id' => $meal->id,
                    'quarter_id' => null,
                    'pickup_location_id' => $pickupId,
                    'custom_delivery_fee' => null,
                ]);
            }

            // BR-307: Set flag on the meal
            $meal->update(['has_custom_locations' => true]);
        });

        return [
            'success' => true,
            'error' => '',
        ];
    }

    /**
     * Disable custom locations for a meal, reverting to global settings.
     *
     * BR-311: Toggling off removes all overrides and reverts to global settings.
     *
     * @return array{success: bool, error: string}
     */
    public function removeOverrides(Meal $meal): array
    {
        DB::transaction(function () use ($meal) {
            MealLocationOverride::where('meal_id', $meal->id)->delete();
            $meal->update(['has_custom_locations' => false]);
        });

        return [
            'success' => true,
            'error' => '',
        ];
    }

    /**
     * Get a summary of the meal's location override for display on the meal list.
     *
     * @return array{has_custom: bool, quarter_count: int, pickup_count: int}
     */
    public function getOverrideSummary(Meal $meal): array
    {
        if (! $meal->has_custom_locations) {
            return [
                'has_custom' => false,
                'quarter_count' => 0,
                'pickup_count' => 0,
            ];
        }

        $quarterCount = MealLocationOverride::where('meal_id', $meal->id)
            ->whereNotNull('quarter_id')
            ->count();

        $pickupCount = MealLocationOverride::where('meal_id', $meal->id)
            ->whereNotNull('pickup_location_id')
            ->count();

        return [
            'has_custom' => true,
            'quarter_count' => $quarterCount,
            'pickup_count' => $pickupCount,
        ];
    }
}
