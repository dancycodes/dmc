<?php

namespace App\Services;

use App\Models\DeliveryArea;
use App\Models\DeliveryAreaQuarter;
use App\Models\PickupLocation;
use App\Models\Quarter;
use App\Models\Tenant;
use App\Models\Town;

class DeliveryAreaService
{
    /**
     * Soft warning threshold for delivery fee.
     */
    public const HIGH_FEE_THRESHOLD = 10000;

    /**
     * Get all delivery areas for a tenant, eagerly loading towns and quarters.
     *
     * @return array<int, array{id: int, town: array, quarters: array}>
     */
    public function getDeliveryAreasData(Tenant $tenant): array
    {
        $deliveryAreas = DeliveryArea::query()
            ->where('tenant_id', $tenant->id)
            ->with(['town', 'deliveryAreaQuarters.quarter'])
            ->orderBy('created_at')
            ->get();

        return $deliveryAreas->map(function (DeliveryArea $area) {
            $locale = app()->getLocale();

            return [
                'id' => $area->id,
                'town_id' => $area->town_id,
                'town_name' => $area->town->{'name_'.$locale} ?? $area->town->name_en,
                'town_name_en' => $area->town->name_en,
                'town_name_fr' => $area->town->name_fr,
                'quarters' => $area->deliveryAreaQuarters->map(function (DeliveryAreaQuarter $daq) use ($locale) {
                    return [
                        'id' => $daq->id,
                        'quarter_id' => $daq->quarter_id,
                        'quarter_name' => $daq->quarter->{'name_'.$locale} ?? $daq->quarter->name_en,
                        'quarter_name_en' => $daq->quarter->name_en,
                        'quarter_name_fr' => $daq->quarter->name_fr,
                        'delivery_fee' => $daq->delivery_fee,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Get all pickup locations for a tenant.
     *
     * @return array<int, array{id: int, name: string, town_name: string, quarter_name: string, address: string}>
     */
    public function getPickupLocationsData(Tenant $tenant): array
    {
        $locations = PickupLocation::query()
            ->where('tenant_id', $tenant->id)
            ->with(['town', 'quarter'])
            ->orderBy('created_at')
            ->get();

        $locale = app()->getLocale();

        return $locations->map(function (PickupLocation $loc) use ($locale) {
            return [
                'id' => $loc->id,
                'name' => $loc->{'name_'.$locale} ?? $loc->name_en,
                'name_en' => $loc->name_en,
                'name_fr' => $loc->name_fr,
                'town_name' => $loc->town->{'name_'.$locale} ?? $loc->town->name_en,
                'quarter_name' => $loc->quarter->{'name_'.$locale} ?? $loc->quarter->name_en,
                'address' => $loc->address,
            ];
        })->values()->all();
    }

    /**
     * Add a town to the tenant's delivery areas.
     *
     * BR-137: Town name required in EN and FR.
     * BR-138: Town name must be unique within this cook's towns.
     *
     * @return array{success: bool, delivery_area: ?DeliveryArea, error: string}
     */
    public function addTown(Tenant $tenant, string $nameEn, string $nameFr): array
    {
        // BR-138: Check uniqueness within this cook's delivery area towns
        $existingTownIds = DeliveryArea::query()
            ->where('tenant_id', $tenant->id)
            ->pluck('town_id');

        $duplicate = Town::query()
            ->whereIn('id', $existingTownIds)
            ->where(function ($q) use ($nameEn, $nameFr) {
                $q->whereRaw('LOWER(name_en) = ?', [mb_strtolower($nameEn)])
                    ->orWhereRaw('LOWER(name_fr) = ?', [mb_strtolower($nameFr)]);
            })
            ->exists();

        if ($duplicate) {
            return [
                'success' => false,
                'delivery_area' => null,
                'error' => __('A town with this name already exists in your delivery areas.'),
            ];
        }

        // Create or find the town in the global reference table
        $town = Town::query()
            ->whereRaw('LOWER(name_en) = ?', [mb_strtolower($nameEn)])
            ->whereRaw('LOWER(name_fr) = ?', [mb_strtolower($nameFr)])
            ->first();

        if (! $town) {
            $town = Town::create([
                'name_en' => $nameEn,
                'name_fr' => $nameFr,
                'is_active' => true,
            ]);
        }

        // Create the delivery area link
        $deliveryArea = DeliveryArea::create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
        ]);

        $deliveryArea->load('town');

        return [
            'success' => true,
            'delivery_area' => $deliveryArea,
            'error' => '',
        ];
    }

    /**
     * Remove a town from the tenant's delivery areas (cascade deletes quarters).
     */
    public function removeTown(Tenant $tenant, int $deliveryAreaId): bool
    {
        $deliveryArea = DeliveryArea::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $deliveryAreaId)
            ->first();

        if (! $deliveryArea) {
            return false;
        }

        $deliveryArea->delete();

        return true;
    }

    /**
     * Add a quarter to a delivery area with a delivery fee.
     *
     * BR-139: Quarter name required in EN and FR.
     * BR-140: Quarter name must be unique within its parent town.
     * BR-141: Delivery fee >= 0.
     * BR-142: Delivery fee stored as integer.
     *
     * @return array{success: bool, quarter_data: ?array, error: string, warning: string}
     */
    public function addQuarter(
        Tenant $tenant,
        int $deliveryAreaId,
        string $nameEn,
        string $nameFr,
        int $deliveryFee,
    ): array {
        $deliveryArea = DeliveryArea::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $deliveryAreaId)
            ->first();

        if (! $deliveryArea) {
            return [
                'success' => false,
                'quarter_data' => null,
                'error' => __('Delivery area not found.'),
                'warning' => '',
            ];
        }

        // BR-140: Check uniqueness within the parent town
        $existingQuarterIds = DeliveryAreaQuarter::query()
            ->where('delivery_area_id', $deliveryAreaId)
            ->pluck('quarter_id');

        $duplicate = Quarter::query()
            ->whereIn('id', $existingQuarterIds)
            ->where(function ($q) use ($nameEn, $nameFr) {
                $q->whereRaw('LOWER(name_en) = ?', [mb_strtolower($nameEn)])
                    ->orWhereRaw('LOWER(name_fr) = ?', [mb_strtolower($nameFr)]);
            })
            ->exists();

        if ($duplicate) {
            return [
                'success' => false,
                'quarter_data' => null,
                'error' => __('A quarter with this name already exists in this town.'),
                'warning' => '',
            ];
        }

        // Create or find the quarter in the global reference table
        $quarter = Quarter::query()
            ->where('town_id', $deliveryArea->town_id)
            ->whereRaw('LOWER(name_en) = ?', [mb_strtolower($nameEn)])
            ->whereRaw('LOWER(name_fr) = ?', [mb_strtolower($nameFr)])
            ->first();

        if (! $quarter) {
            $quarter = Quarter::create([
                'town_id' => $deliveryArea->town_id,
                'name_en' => $nameEn,
                'name_fr' => $nameFr,
                'is_active' => true,
            ]);
        }

        // Create the delivery area quarter link
        $daq = DeliveryAreaQuarter::create([
            'delivery_area_id' => $deliveryAreaId,
            'quarter_id' => $quarter->id,
            'delivery_fee' => $deliveryFee,
        ]);

        $daq->load('quarter');
        $locale = app()->getLocale();

        $warning = '';
        if ($deliveryFee > self::HIGH_FEE_THRESHOLD) {
            $warning = __('This delivery fee seems high. Please verify it is correct.');
        }

        return [
            'success' => true,
            'quarter_data' => [
                'id' => $daq->id,
                'quarter_id' => $daq->quarter_id,
                'quarter_name' => $daq->quarter->{'name_'.$locale} ?? $daq->quarter->name_en,
                'quarter_name_en' => $daq->quarter->name_en,
                'quarter_name_fr' => $daq->quarter->name_fr,
                'delivery_fee' => $daq->delivery_fee,
            ],
            'error' => '',
            'warning' => $warning,
        ];
    }

    /**
     * Remove a quarter from a delivery area.
     */
    public function removeQuarter(Tenant $tenant, int $deliveryAreaQuarterId): bool
    {
        $daq = DeliveryAreaQuarter::query()
            ->whereHas('deliveryArea', function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id);
            })
            ->where('id', $deliveryAreaQuarterId)
            ->first();

        if (! $daq) {
            return false;
        }

        $daq->delete();

        return true;
    }

    /**
     * Add a pickup location for the tenant.
     *
     * @return array{success: bool, pickup: ?array, error: string}
     */
    public function addPickupLocation(
        Tenant $tenant,
        string $nameEn,
        string $nameFr,
        int $townId,
        int $quarterId,
        string $address,
    ): array {
        // Verify the town belongs to the cook's delivery areas
        $deliveryArea = DeliveryArea::query()
            ->where('tenant_id', $tenant->id)
            ->where('town_id', $townId)
            ->first();

        if (! $deliveryArea) {
            return [
                'success' => false,
                'pickup' => null,
                'error' => __('Please select a town from your delivery areas.'),
            ];
        }

        // Verify the quarter belongs to this town
        $quarter = Quarter::query()
            ->where('id', $quarterId)
            ->where('town_id', $townId)
            ->first();

        if (! $quarter) {
            return [
                'success' => false,
                'pickup' => null,
                'error' => __('The selected quarter does not belong to this town.'),
            ];
        }

        $pickup = PickupLocation::create([
            'tenant_id' => $tenant->id,
            'town_id' => $townId,
            'quarter_id' => $quarterId,
            'name_en' => $nameEn,
            'name_fr' => $nameFr,
            'address' => $address,
        ]);

        $pickup->load(['town', 'quarter']);
        $locale = app()->getLocale();

        return [
            'success' => true,
            'pickup' => [
                'id' => $pickup->id,
                'name' => $pickup->{'name_'.$locale} ?? $pickup->name_en,
                'name_en' => $pickup->name_en,
                'name_fr' => $pickup->name_fr,
                'town_name' => $pickup->town->{'name_'.$locale} ?? $pickup->town->name_en,
                'quarter_name' => $pickup->quarter->{'name_'.$locale} ?? $pickup->quarter->name_en,
                'address' => $pickup->address,
            ],
            'error' => '',
        ];
    }

    /**
     * Remove a pickup location.
     */
    public function removePickupLocation(Tenant $tenant, int $pickupLocationId): bool
    {
        $pickup = PickupLocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $pickupLocationId)
            ->first();

        if (! $pickup) {
            return false;
        }

        $pickup->delete();

        return true;
    }

    /**
     * Check if the tenant has the minimum delivery area setup (1 town + 1 quarter).
     *
     * BR-136: At least 1 town with 1 quarter and a delivery fee is required.
     */
    public function hasMinimumSetup(Tenant $tenant): bool
    {
        return DeliveryArea::query()
            ->where('tenant_id', $tenant->id)
            ->whereHas('deliveryAreaQuarters')
            ->exists();
    }
}
