<?php

namespace App\Services;

use App\Models\DeliveryArea;
use App\Models\DeliveryAreaQuarter;
use App\Models\PickupLocation;
use App\Models\Quarter;
use App\Models\QuarterGroup;
use App\Models\Tenant;
use App\Models\Town;
use Illuminate\Support\Facades\DB;

class DeliveryAreaService
{
    /**
     * Soft warning threshold for delivery fee.
     */
    public const HIGH_FEE_THRESHOLD = 10000;

    /**
     * Get all delivery areas for a tenant, eagerly loading towns and quarters.
     *
     * F-083: BR-215 — Towns displayed in alphabetical order by name in the current locale.
     *
     * @return array<int, array{id: int, town: array, quarters: array}>
     */
    public function getDeliveryAreasData(Tenant $tenant): array
    {
        $locale = app()->getLocale();
        $orderColumn = 'name_'.$locale;

        $deliveryAreas = DeliveryArea::query()
            ->where('tenant_id', $tenant->id)
            ->with(['town', 'deliveryAreaQuarters.quarter'])
            ->join('towns', 'delivery_areas.town_id', '=', 'towns.id')
            ->orderBy('towns.'.$orderColumn)
            ->select('delivery_areas.*')
            ->get();

        return $deliveryAreas->map(function (DeliveryArea $area) {
            $locale = app()->getLocale();

            return [
                'id' => $area->id,
                'town_id' => $area->town_id,
                'town_name' => $area->town->{'name_'.$locale} ?? $area->town->name_en,
                'town_name_en' => $area->town->name_en,
                'town_name_fr' => $area->town->name_fr,
                'quarters' => $area->deliveryAreaQuarters
                    ->sortBy(fn (DeliveryAreaQuarter $daq) => mb_strtolower($daq->quarter->{'name_'.$locale} ?? $daq->quarter->name_en))
                    ->map(function (DeliveryAreaQuarter $daq) use ($locale) {
                        $quarterData = [
                            'id' => $daq->id,
                            'quarter_id' => $daq->quarter_id,
                            'quarter_name' => $daq->quarter->{'name_'.$locale} ?? $daq->quarter->name_en,
                            'quarter_name_en' => $daq->quarter->name_en,
                            'quarter_name_fr' => $daq->quarter->name_fr,
                            'delivery_fee' => $daq->delivery_fee,
                            'group_id' => null,
                            'group_name' => null,
                            'group_fee' => null,
                        ];

                        // F-090: Quarter group data via Eloquent
                        $group = QuarterGroup::query()
                            ->whereHas('quarters', function ($q) use ($daq) {
                                $q->where('quarters.id', $daq->quarter_id);
                            })
                            ->first();

                        if ($group) {
                            $quarterData['group_id'] = $group->id;
                            $quarterData['group_name'] = $group->name;
                            $quarterData['group_fee'] = $group->delivery_fee;
                        }

                        return $quarterData;
                    })->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Get all pickup locations for a tenant.
     *
     * F-093: Pickup Location List View
     * BR-289: List shows all pickup locations for the current tenant
     * BR-290: Each entry displays: location name (current locale), town name, quarter name, address
     * BR-291: Locations sorted alphabetically by name in current locale
     *
     * @return array<int, array{id: int, name: string, name_en: string, name_fr: string, town_name: string, quarter_name: string, address: string, town_id: int, quarter_id: int}>
     */
    public function getPickupLocationsData(Tenant $tenant): array
    {
        $locale = app()->getLocale();
        $nameColumn = 'name_'.$locale;

        // BR-291: Sort alphabetically by name in current locale
        $locations = PickupLocation::query()
            ->where('tenant_id', $tenant->id)
            ->with(['town', 'quarter'])
            ->orderBy($nameColumn)
            ->get();

        return $locations->map(function (PickupLocation $loc) use ($locale) {
            // Edge case: town or quarter was deleted — show fallback
            $townName = $loc->town
                ? ($loc->town->{'name_'.$locale} ?? $loc->town->name_en)
                : __('Location unavailable');
            $quarterName = $loc->quarter
                ? ($loc->quarter->{'name_'.$locale} ?? $loc->quarter->name_en)
                : __('Location unavailable');

            return [
                'id' => $loc->id,
                'name' => $loc->{'name_'.$locale} ?? $loc->name_en,
                'name_en' => $loc->name_en,
                'name_fr' => $loc->name_fr,
                'town_name' => $townName,
                'quarter_name' => $quarterName,
                'address' => $loc->address,
                'town_id' => $loc->town_id,
                'quarter_id' => $loc->quarter_id,
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
     * Update a town's name in the cook's delivery areas.
     *
     * BR-220: Edited town name must remain unique within this cook's towns (excluding the current town).
     * BR-222: Changes to town name do not affect existing order records (orders reference town by ID).
     *
     * @return array{success: bool, error: string}
     */
    public function updateTown(Tenant $tenant, int $deliveryAreaId, string $nameEn, string $nameFr): array
    {
        $deliveryArea = DeliveryArea::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $deliveryAreaId)
            ->with('town')
            ->first();

        if (! $deliveryArea) {
            return [
                'success' => false,
                'error' => __('Delivery area not found.'),
            ];
        }

        // BR-220: Check uniqueness excluding the current town
        $existingTownIds = DeliveryArea::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', '!=', $deliveryAreaId)
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
                'error' => __('A town with this name already exists in your delivery areas.'),
            ];
        }

        // Update the town record
        $deliveryArea->town->update([
            'name_en' => $nameEn,
            'name_fr' => $nameFr,
        ]);

        return [
            'success' => true,
            'error' => '',
        ];
    }

    /**
     * Remove a town from the tenant's delivery areas (cascade deletes quarters).
     *
     * F-085: Delete Town
     * BR-225: Cannot delete a town with active (non-completed, non-cancelled) orders.
     * BR-226: Deleting a town cascade-deletes all its quarters and their delivery fees.
     * BR-227: Deleting a town cascade-removes quarters from any quarter groups.
     *
     * @return array{success: bool, error: string, town_name: string, quarter_count: int}
     */
    public function removeTown(Tenant $tenant, int $deliveryAreaId): array
    {
        $deliveryArea = DeliveryArea::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $deliveryAreaId)
            ->with(['town', 'deliveryAreaQuarters'])
            ->first();

        if (! $deliveryArea) {
            return [
                'success' => false,
                'error' => __('Delivery area not found.'),
                'town_name' => '',
                'quarter_count' => 0,
            ];
        }

        $locale = app()->getLocale();
        $townName = $deliveryArea->town->{'name_'.$locale} ?? $deliveryArea->town->name_en;
        $quarterCount = $deliveryArea->deliveryAreaQuarters->count();

        // BR-225: Check for active orders referencing this town (forward-compatible)
        if (\Illuminate\Support\Facades\Schema::hasTable('orders')) {
            $activeOrderCount = \Illuminate\Database\Eloquent\Model::resolveConnection()
                ->table('orders')
                ->where('tenant_id', $tenant->id)
                ->where('town_id', $deliveryArea->town_id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();

            if ($activeOrderCount > 0) {
                return [
                    'success' => false,
                    'error' => __('Cannot delete :town because it has active orders. Complete or cancel the orders first.', ['town' => $townName]),
                    'town_name' => $townName,
                    'quarter_count' => $quarterCount,
                ];
            }
        }

        // BR-226: Cascade delete quarters (FK on_delete cascade handles delivery_area_quarters)
        // BR-227: Remove quarters from any quarter groups before deletion
        $quarterIds = $deliveryArea->deliveryAreaQuarters->pluck('quarter_id')->all();
        if (! empty($quarterIds)) {
            DB::table('quarter_group_quarter')
                ->whereIn('quarter_id', $quarterIds)
                ->delete();
        }

        // Delete the delivery area (FK cascade handles delivery_area_quarters)
        $deliveryArea->delete();

        return [
            'success' => true,
            'error' => '',
            'town_name' => $townName,
            'quarter_count' => $quarterCount,
        ];
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
     * Update a quarter in a delivery area.
     *
     * F-088: Edit Quarter
     * BR-250: Quarter name required in both EN and FR.
     * BR-251: Quarter name must remain unique within the parent town (excluding current quarter).
     * BR-252: Delivery fee required; must be >= 0 XAF.
     * BR-253: Group assignment can be changed or removed.
     * BR-254: When in a group, the group's fee overrides the individual fee.
     * BR-255: Fee changes apply to new orders only.
     *
     * @return array{success: bool, error: string, warning: string}
     */
    public function updateQuarter(
        Tenant $tenant,
        int $deliveryAreaQuarterId,
        string $nameEn,
        string $nameFr,
        int $deliveryFee,
    ): array {
        $daq = DeliveryAreaQuarter::query()
            ->whereHas('deliveryArea', function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id);
            })
            ->where('id', $deliveryAreaQuarterId)
            ->with(['quarter', 'deliveryArea'])
            ->first();

        if (! $daq) {
            return [
                'success' => false,
                'error' => __('Quarter not found.'),
                'warning' => '',
            ];
        }

        // BR-251: Check uniqueness within the parent town (excluding current quarter)
        $existingQuarterIds = DeliveryAreaQuarter::query()
            ->where('delivery_area_id', $daq->delivery_area_id)
            ->where('id', '!=', $deliveryAreaQuarterId)
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
                'error' => __('A quarter with this name already exists in this town.'),
                'warning' => '',
            ];
        }

        // Update the quarter's global record (name)
        $daq->quarter->update([
            'name_en' => $nameEn,
            'name_fr' => $nameFr,
        ]);

        // BR-252: Update the delivery fee on the junction record
        $daq->update([
            'delivery_fee' => $deliveryFee,
        ]);

        $warning = '';
        if ($deliveryFee > self::HIGH_FEE_THRESHOLD) {
            $warning = __('This delivery fee seems high. Please verify it is correct.');
        }

        return [
            'success' => true,
            'error' => '',
            'warning' => $warning,
        ];
    }

    /**
     * Remove a quarter from a delivery area.
     *
     * F-089: Delete Quarter
     * BR-258: Cannot delete a quarter with active (non-completed, non-cancelled) orders.
     * BR-259: Deleting a quarter removes it from any quarter group it belongs to.
     *
     * @return array{success: bool, error: string, quarter_name: string}
     */
    public function removeQuarter(Tenant $tenant, int $deliveryAreaQuarterId): array
    {
        $daq = DeliveryAreaQuarter::query()
            ->whereHas('deliveryArea', function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id);
            })
            ->where('id', $deliveryAreaQuarterId)
            ->with(['quarter', 'deliveryArea'])
            ->first();

        if (! $daq) {
            return [
                'success' => false,
                'error' => __('Quarter not found.'),
                'quarter_name' => '',
            ];
        }

        $locale = app()->getLocale();
        $quarterName = $daq->quarter->{'name_'.$locale} ?? $daq->quarter->name_en;

        // BR-258: Check for active orders referencing this quarter (forward-compatible)
        if (\Illuminate\Support\Facades\Schema::hasTable('orders')) {
            $activeOrderCount = \Illuminate\Database\Eloquent\Model::resolveConnection()
                ->table('orders')
                ->where('tenant_id', $tenant->id)
                ->where('quarter_id', $daq->quarter_id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();

            if ($activeOrderCount > 0) {
                return [
                    'success' => false,
                    'error' => trans_choice(
                        '{1} Cannot delete :quarter because it has :count active order.|[2,*] Cannot delete :quarter because it has :count active orders.',
                        $activeOrderCount,
                        ['quarter' => $quarterName, 'count' => $activeOrderCount]
                    ),
                    'quarter_name' => $quarterName,
                ];
            }
        }

        // BR-259: Remove from quarter groups
        DB::table('quarter_group_quarter')
            ->where('quarter_id', $daq->quarter_id)
            ->delete();

        // Delete the delivery area quarter junction record
        $daq->delete();

        return [
            'success' => true,
            'error' => '',
            'quarter_name' => $quarterName,
        ];
    }

    /**
     * Add a pickup location for the tenant.
     *
     * @return array{success: bool, pickup: ?array, pickup_model: ?PickupLocation, error: string}
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
                'pickup_model' => null,
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
                'pickup_model' => null,
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
            'pickup_model' => $pickup,
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
     * Get quarter groups for a specific delivery area (town) for filter dropdown.
     *
     * F-087: BR-245 — Filter by group available if quarter groups exist.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function getQuarterGroupsForArea(Tenant $tenant, int $deliveryAreaId): array
    {
        $quarterIds = DeliveryAreaQuarter::query()
            ->where('delivery_area_id', $deliveryAreaId)
            ->pluck('quarter_id');

        if ($quarterIds->isEmpty()) {
            return [];
        }

        $groups = QuarterGroup::query()
            ->where('tenant_id', $tenant->id)
            ->whereHas('quarters', function ($q) use ($quarterIds) {
                $q->whereIn('quarters.id', $quarterIds);
            })
            ->orderBy('name')
            ->get();

        return $groups->map(fn (QuarterGroup $g) => ['id' => $g->id, 'name' => $g->name])->values()->all();
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

    /**
     * Create a quarter group with optional quarter assignments.
     *
     * F-090: Quarter Group Creation
     * BR-264: Group name is required (plain text, not translatable)
     * BR-265: Group name must be unique within this tenant
     * BR-266: Delivery fee is required and must be >= 0 XAF
     * BR-267: Group fee overrides individual quarter fees
     * BR-268: A quarter can belong to at most one group at a time
     * BR-269: Quarters from any town under this tenant can be assigned
     * BR-270: Groups are tenant-scoped
     * BR-271: Creating without assigning quarters is allowed
     *
     * @param  list<int>  $quarterIds  Quarter IDs to assign to the group
     * @return array{success: bool, group: ?QuarterGroup, error: string}
     */
    public function createQuarterGroup(Tenant $tenant, string $name, int $deliveryFee, array $quarterIds = []): array
    {
        // BR-265: Check uniqueness within tenant
        $duplicate = QuarterGroup::query()
            ->where('tenant_id', $tenant->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($duplicate) {
            return [
                'success' => false,
                'group' => null,
                'error' => __('A group with this name already exists.'),
            ];
        }

        // BR-269: Validate that all quarter IDs belong to this tenant's delivery areas
        if (! empty($quarterIds)) {
            $tenantQuarterIds = DeliveryAreaQuarter::query()
                ->whereHas('deliveryArea', function ($q) use ($tenant) {
                    $q->where('tenant_id', $tenant->id);
                })
                ->pluck('quarter_id')
                ->all();

            $invalidIds = array_diff($quarterIds, $tenantQuarterIds);
            if (! empty($invalidIds)) {
                return [
                    'success' => false,
                    'group' => null,
                    'error' => __('Some selected quarters do not belong to your delivery areas.'),
                ];
            }
        }

        return DB::transaction(function () use ($tenant, $name, $deliveryFee, $quarterIds) {
            // Create the group
            $group = QuarterGroup::create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'delivery_fee' => $deliveryFee,
            ]);

            // BR-268: Assign quarters (remove from old groups first)
            if (! empty($quarterIds)) {
                // Remove quarters from any existing groups
                DB::table('quarter_group_quarter')
                    ->whereIn('quarter_id', $quarterIds)
                    ->delete();

                // Assign to the new group
                $group->quarters()->attach($quarterIds);
            }

            return [
                'success' => true,
                'group' => $group,
                'error' => '',
            ];
        });
    }

    /**
     * Get all quarter groups for a tenant.
     *
     * @return array<int, array{id: int, name: string, delivery_fee: int, quarter_count: int}>
     */
    public function getQuarterGroupsData(Tenant $tenant): array
    {
        $groups = QuarterGroup::query()
            ->where('tenant_id', $tenant->id)
            ->withCount('quarters')
            ->orderBy('name')
            ->get();

        return $groups->map(fn (QuarterGroup $group) => [
            'id' => $group->id,
            'name' => $group->name,
            'delivery_fee' => $group->delivery_fee,
            'quarter_count' => $group->quarters_count,
        ])->values()->all();
    }

    /**
     * Update the delivery fee for an individual quarter.
     *
     * F-091: Delivery Fee Configuration
     * BR-273: Delivery fee must be >= 0 XAF
     * BR-276: Fee changes apply to new orders only
     * BR-277: Fees are stored as integers in XAF
     *
     * @return array{success: bool, error: string, warning: string}
     */
    public function updateQuarterFee(Tenant $tenant, int $deliveryAreaQuarterId, int $fee): array
    {
        $daq = DeliveryAreaQuarter::query()
            ->whereHas('deliveryArea', function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id);
            })
            ->where('id', $deliveryAreaQuarterId)
            ->first();

        if (! $daq) {
            return [
                'success' => false,
                'error' => __('Quarter not found.'),
                'warning' => '',
            ];
        }

        $daq->update([
            'delivery_fee' => $fee,
        ]);

        $warning = '';
        if ($fee > self::HIGH_FEE_THRESHOLD) {
            $warning = __('This delivery fee seems high. Please verify it is correct.');
        }

        return [
            'success' => true,
            'error' => '',
            'warning' => $warning,
        ];
    }

    /**
     * Update the delivery fee for a quarter group.
     *
     * F-091: Delivery Fee Configuration
     * BR-273: Delivery fee must be >= 0 XAF
     * BR-275: Group fee overrides individual quarter fees for all quarters in the group
     * BR-276: Fee changes apply to new orders only
     * BR-277: Fees are stored as integers in XAF
     *
     * @return array{success: bool, error: string, warning: string}
     */
    public function updateGroupFee(Tenant $tenant, int $groupId, int $fee): array
    {
        $group = QuarterGroup::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $groupId)
            ->first();

        if (! $group) {
            return [
                'success' => false,
                'error' => __('Quarter group not found.'),
                'warning' => '',
            ];
        }

        $group->update([
            'delivery_fee' => $fee,
        ]);

        $warning = '';
        if ($fee > self::HIGH_FEE_THRESHOLD) {
            $warning = __('This delivery fee seems high. Please verify it is correct.');
        }

        return [
            'success' => true,
            'error' => '',
            'warning' => $warning,
        ];
    }

    /**
     * Get delivery fee summary data for the centralized fee configuration view.
     *
     * F-091: Delivery Fee Configuration
     * Returns all quarters organized by town with fee and group information.
     *
     * @return array{areas: array, groups: array, summary: array}
     */
    public function getDeliveryFeeSummary(Tenant $tenant): array
    {
        $locale = app()->getLocale();
        $orderColumn = 'name_'.$locale;

        $deliveryAreas = DeliveryArea::query()
            ->where('tenant_id', $tenant->id)
            ->with(['town', 'deliveryAreaQuarters.quarter'])
            ->join('towns', 'delivery_areas.town_id', '=', 'towns.id')
            ->orderBy('towns.'.$orderColumn)
            ->select('delivery_areas.*')
            ->get();

        $totalQuarters = 0;
        $freeDeliveryCount = 0;
        $groupedCount = 0;

        $areas = $deliveryAreas->map(function (DeliveryArea $area) use ($locale, &$totalQuarters, &$freeDeliveryCount, &$groupedCount) {
            $quarters = $area->deliveryAreaQuarters
                ->sortBy(fn (DeliveryAreaQuarter $daq) => mb_strtolower($daq->quarter->{'name_'.$locale} ?? $daq->quarter->name_en))
                ->map(function (DeliveryAreaQuarter $daq) use ($locale, &$totalQuarters, &$freeDeliveryCount, &$groupedCount) {
                    $totalQuarters++;

                    $group = QuarterGroup::query()
                        ->whereHas('quarters', function ($q) use ($daq) {
                            $q->where('quarters.id', $daq->quarter_id);
                        })
                        ->first();

                    $isGrouped = $group !== null;
                    $effectiveFee = $isGrouped ? $group->delivery_fee : $daq->delivery_fee;

                    if ($effectiveFee === 0) {
                        $freeDeliveryCount++;
                    }
                    if ($isGrouped) {
                        $groupedCount++;
                    }

                    return [
                        'id' => $daq->id,
                        'quarter_id' => $daq->quarter_id,
                        'quarter_name' => $daq->quarter->{'name_'.$locale} ?? $daq->quarter->name_en,
                        'delivery_fee' => $daq->delivery_fee,
                        'group_id' => $group?->id,
                        'group_name' => $group?->name,
                        'group_fee' => $group?->delivery_fee,
                        'effective_fee' => $effectiveFee,
                        'is_grouped' => $isGrouped,
                    ];
                })->values()->all();

            return [
                'id' => $area->id,
                'town_name' => $area->town->{'name_'.$locale} ?? $area->town->name_en,
                'quarters' => $quarters,
            ];
        })->values()->all();

        $groups = QuarterGroup::query()
            ->where('tenant_id', $tenant->id)
            ->withCount('quarters')
            ->orderBy('name')
            ->get()
            ->map(fn (QuarterGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'delivery_fee' => $group->delivery_fee,
                'quarter_count' => $group->quarters_count,
            ])->values()->all();

        return [
            'areas' => $areas,
            'groups' => $groups,
            'summary' => [
                'total_quarters' => $totalQuarters,
                'free_delivery_count' => $freeDeliveryCount,
                'grouped_count' => $groupedCount,
            ],
        ];
    }

    /**
     * Get all available quarters for a tenant (for group assignment multi-select).
     *
     * Returns quarters grouped by town, with current group assignment info.
     *
     * @return array<int, array{town_name: string, quarters: array}>
     */
    public function getQuartersForGroupAssignment(Tenant $tenant): array
    {
        $locale = app()->getLocale();

        $deliveryAreas = DeliveryArea::query()
            ->where('tenant_id', $tenant->id)
            ->with(['town', 'deliveryAreaQuarters.quarter'])
            ->join('towns', 'delivery_areas.town_id', '=', 'towns.id')
            ->orderBy('towns.name_'.$locale)
            ->select('delivery_areas.*')
            ->get();

        return $deliveryAreas->map(function (DeliveryArea $area) use ($locale) {
            return [
                'town_name' => $area->town->{'name_'.$locale} ?? $area->town->name_en,
                'quarters' => $area->deliveryAreaQuarters
                    ->sortBy(fn (DeliveryAreaQuarter $daq) => mb_strtolower($daq->quarter->{'name_'.$locale} ?? $daq->quarter->name_en))
                    ->map(function (DeliveryAreaQuarter $daq) use ($locale) {
                        // Check current group membership
                        $currentGroup = QuarterGroup::query()
                            ->whereHas('quarters', function ($q) use ($daq) {
                                $q->where('quarters.id', $daq->quarter_id);
                            })
                            ->first();

                        return [
                            'quarter_id' => $daq->quarter_id,
                            'quarter_name' => $daq->quarter->{'name_'.$locale} ?? $daq->quarter->name_en,
                            'current_group_id' => $currentGroup?->id,
                            'current_group_name' => $currentGroup?->name,
                        ];
                    })->values()->all(),
            ];
        })->filter(fn ($area) => count($area['quarters']) > 0)->values()->all();
    }
}
