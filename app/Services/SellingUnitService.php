<?php

namespace App\Services;

use App\Models\MealComponent;
use App\Models\SellingUnit;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class SellingUnitService
{
    /**
     * Get all selling units for a tenant (standard + custom).
     *
     * BR-306: Standard units always included.
     * BR-308: Custom units are tenant-scoped.
     *
     * @return Collection<int, SellingUnit>
     */
    public function getUnitsForTenant(Tenant $tenant): Collection
    {
        return SellingUnit::forTenant($tenant->id)
            ->orderByDesc('is_standard')
            ->orderBy('name_en')
            ->get();
    }

    /**
     * Get only custom units for a tenant.
     *
     * @return Collection<int, SellingUnit>
     */
    public function getCustomUnitsForTenant(Tenant $tenant): Collection
    {
        return SellingUnit::where('tenant_id', $tenant->id)
            ->custom()
            ->orderBy('name_en')
            ->get();
    }

    /**
     * Get available units with labels for form dropdowns.
     *
     * @return array<array{value: string, label: string, is_standard: bool}>
     */
    public function getUnitsWithLabels(Tenant $tenant): array
    {
        $locale = app()->getLocale();
        $units = [];

        $allUnits = $this->getUnitsForTenant($tenant);

        foreach ($allUnits as $unit) {
            $units[] = [
                'value' => (string) $unit->id,
                'label' => $unit->{'name_'.$locale} ?? $unit->name_en,
                'is_standard' => $unit->is_standard,
            ];
        }

        return $units;
    }

    /**
     * Get list of valid unit IDs for a tenant (for validation).
     *
     * @return array<string>
     */
    public function getValidUnitIds(Tenant $tenant): array
    {
        return SellingUnit::forTenant($tenant->id)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    /**
     * Create a custom selling unit.
     *
     * BR-308: Custom units are tenant-scoped.
     * BR-309: Name required in both EN and FR.
     * BR-310: Name must be unique within tenant and against standard units.
     * BR-314: Name max 50 characters per language.
     *
     * @param  array{name_en: string, name_fr: string}  $data
     * @return array{success: bool, unit?: SellingUnit, error?: string, error_field?: string}
     */
    public function createUnit(Tenant $tenant, array $data): array
    {
        $nameEn = trim($data['name_en']);
        $nameFr = trim($data['name_fr']);

        // BR-310: Check uniqueness against standard units (case-insensitive)
        $standardDuplicateEn = SellingUnit::standard()
            ->whereRaw('LOWER(name_en) = ?', [mb_strtolower($nameEn)])
            ->exists();

        if ($standardDuplicateEn) {
            return [
                'success' => false,
                'error' => __('This name matches a standard unit. Please choose a different name.'),
                'error_field' => 'name_en',
            ];
        }

        $standardDuplicateFr = SellingUnit::standard()
            ->whereRaw('LOWER(name_fr) = ?', [mb_strtolower($nameFr)])
            ->exists();

        if ($standardDuplicateFr) {
            return [
                'success' => false,
                'error' => __('This name matches a standard unit. Please choose a different name.'),
                'error_field' => 'name_fr',
            ];
        }

        // BR-310: Check uniqueness within tenant (case-insensitive)
        $tenantDuplicateEn = SellingUnit::where('tenant_id', $tenant->id)
            ->whereRaw('LOWER(name_en) = ?', [mb_strtolower($nameEn)])
            ->exists();

        if ($tenantDuplicateEn) {
            return [
                'success' => false,
                'error' => __('A custom unit with this English name already exists.'),
                'error_field' => 'name_en',
            ];
        }

        $tenantDuplicateFr = SellingUnit::where('tenant_id', $tenant->id)
            ->whereRaw('LOWER(name_fr) = ?', [mb_strtolower($nameFr)])
            ->exists();

        if ($tenantDuplicateFr) {
            return [
                'success' => false,
                'error' => __('A custom unit with this French name already exists.'),
                'error_field' => 'name_fr',
            ];
        }

        $unit = SellingUnit::create([
            'tenant_id' => $tenant->id,
            'name_en' => $nameEn,
            'name_fr' => $nameFr,
            'is_standard' => false,
        ]);

        return [
            'success' => true,
            'unit' => $unit,
        ];
    }

    /**
     * Update a custom selling unit.
     *
     * BR-307: Standard units cannot be edited.
     * BR-310: Name uniqueness enforced on update.
     *
     * @param  array{name_en: string, name_fr: string}  $data
     * @return array{success: bool, unit?: SellingUnit, error?: string, error_field?: string, old_values?: array<string, mixed>}
     */
    public function updateUnit(SellingUnit $unit, array $data): array
    {
        // BR-307: Standard units cannot be edited
        if ($unit->isStandard()) {
            return [
                'success' => false,
                'error' => __('Standard units cannot be edited.'),
            ];
        }

        $nameEn = trim($data['name_en']);
        $nameFr = trim($data['name_fr']);

        // BR-310: Check uniqueness against standard units (case-insensitive)
        $standardDuplicateEn = SellingUnit::standard()
            ->whereRaw('LOWER(name_en) = ?', [mb_strtolower($nameEn)])
            ->exists();

        if ($standardDuplicateEn) {
            return [
                'success' => false,
                'error' => __('This name matches a standard unit. Please choose a different name.'),
                'error_field' => 'name_en',
            ];
        }

        $standardDuplicateFr = SellingUnit::standard()
            ->whereRaw('LOWER(name_fr) = ?', [mb_strtolower($nameFr)])
            ->exists();

        if ($standardDuplicateFr) {
            return [
                'success' => false,
                'error' => __('This name matches a standard unit. Please choose a different name.'),
                'error_field' => 'name_fr',
            ];
        }

        // BR-310: Check uniqueness within tenant, excluding current unit
        $tenantDuplicateEn = SellingUnit::where('tenant_id', $unit->tenant_id)
            ->where('id', '!=', $unit->id)
            ->whereRaw('LOWER(name_en) = ?', [mb_strtolower($nameEn)])
            ->exists();

        if ($tenantDuplicateEn) {
            return [
                'success' => false,
                'error' => __('A custom unit with this English name already exists.'),
                'error_field' => 'name_en',
            ];
        }

        $tenantDuplicateFr = SellingUnit::where('tenant_id', $unit->tenant_id)
            ->where('id', '!=', $unit->id)
            ->whereRaw('LOWER(name_fr) = ?', [mb_strtolower($nameFr)])
            ->exists();

        if ($tenantDuplicateFr) {
            return [
                'success' => false,
                'error' => __('A custom unit with this French name already exists.'),
                'error_field' => 'name_fr',
            ];
        }

        // Capture old values for activity logging
        $oldValues = [
            'name_en' => $unit->name_en,
            'name_fr' => $unit->name_fr,
        ];

        $unit->update([
            'name_en' => $nameEn,
            'name_fr' => $nameFr,
        ]);

        return [
            'success' => true,
            'unit' => $unit->fresh(),
            'old_values' => $oldValues,
        ];
    }

    /**
     * Delete a custom selling unit.
     *
     * BR-307: Standard units cannot be deleted.
     * BR-311: Cannot delete if used by any meal component.
     *
     * @return array{success: bool, error?: string, entity_name?: string}
     */
    public function deleteUnit(SellingUnit $unit): array
    {
        // BR-307: Standard units cannot be deleted
        if ($unit->isStandard()) {
            return [
                'success' => false,
                'error' => __('Standard units cannot be deleted.'),
            ];
        }

        // BR-311: Check if unit is in use by any meal component
        $usageCount = $unit->getUsageCount();
        if ($usageCount > 0) {
            return [
                'success' => false,
                'error' => trans_choice(
                    'Cannot delete — this unit is used by :count component.|Cannot delete — this unit is used by :count components.',
                    $usageCount,
                    ['count' => $usageCount]
                ),
            ];
        }

        $entityName = $unit->name;

        $unit->delete();

        return [
            'success' => true,
            'entity_name' => $entityName,
        ];
    }

    /**
     * Check if a selling unit can be deleted.
     *
     * Used by the view to disable/enable delete buttons.
     *
     * @return array{can_delete: bool, reason?: string}
     */
    public function canDeleteUnit(SellingUnit $unit): array
    {
        if ($unit->isStandard()) {
            return [
                'can_delete' => false,
                'reason' => __('Standard units cannot be deleted.'),
            ];
        }

        $usageCount = $unit->getUsageCount();
        if ($usageCount > 0) {
            return [
                'can_delete' => false,
                'reason' => trans_choice(
                    'Used by :count component.|Used by :count components.',
                    $usageCount,
                    ['count' => $usageCount]
                ),
            ];
        }

        return ['can_delete' => true];
    }
}
