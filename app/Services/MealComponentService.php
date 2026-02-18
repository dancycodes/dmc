<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

class MealComponentService
{
    /**
     * Create a new meal component.
     *
     * F-118: Meal Component Creation
     * BR-278: Name required in both EN and FR
     * BR-280: Price required, minimum 1 XAF
     * BR-281: Selling unit required
     * BR-283: Min quantity defaults to 0
     * BR-284: Max quantity defaults to unlimited (null)
     * BR-285: Available quantity defaults to unlimited (null)
     * BR-287: Components are meal-scoped and tenant-scoped
     * BR-290: Position for display ordering
     * BR-291: Default availability is true
     *
     * @param  array{name_en: string, name_fr: string, price: int, selling_unit: string, min_quantity?: int, max_quantity?: int|null, available_quantity?: int|null}  $data
     * @return array{success: bool, component?: MealComponent, error?: string}
     */
    public function createComponent(Meal $meal, array $data): array
    {
        $nameEn = trim($data['name_en']);
        $nameFr = trim($data['name_fr']);
        $price = (int) $data['price'];
        $sellingUnit = $data['selling_unit'];
        $minQuantity = (int) ($data['min_quantity'] ?? 0);
        $maxQuantity = isset($data['max_quantity']) && $data['max_quantity'] !== '' && $data['max_quantity'] !== null
            ? (int) $data['max_quantity']
            : null;
        $availableQuantity = isset($data['available_quantity']) && $data['available_quantity'] !== '' && $data['available_quantity'] !== null
            ? (int) $data['available_quantity']
            : null;

        // Edge case: min quantity > max quantity
        if ($maxQuantity !== null && $minQuantity > $maxQuantity) {
            return [
                'success' => false,
                'error' => __('Minimum quantity cannot be greater than maximum quantity.'),
            ];
        }

        // Validate selling unit is in allowed list
        $allowedUnits = $this->getAvailableUnits($meal->tenant);
        if (! in_array($sellingUnit, $allowedUnits)) {
            return [
                'success' => false,
                'error' => __('Invalid selling unit selected.'),
            ];
        }

        // BR-290: Calculate next position
        $position = MealComponent::nextPositionForMeal($meal->id);

        $component = MealComponent::create([
            'meal_id' => $meal->id,
            'name_en' => $nameEn,
            'name_fr' => $nameFr,
            'price' => $price,
            'selling_unit' => $sellingUnit,
            'min_quantity' => $minQuantity,
            'max_quantity' => $maxQuantity,
            'available_quantity' => $availableQuantity,
            'is_available' => true,
            'position' => $position,
        ]);

        return [
            'success' => true,
            'component' => $component,
        ];
    }

    /**
     * Get available selling units (standard + custom from F-121).
     *
     * @return array<string>
     */
    public function getAvailableUnits(Tenant $tenant): array
    {
        $units = MealComponent::STANDARD_UNITS;

        // Forward-compatible: merge custom units from F-121 when available
        if (Schema::hasTable('selling_units')) {
            $customUnits = \App\Models\SellingUnit::where('tenant_id', $tenant->id)
                ->pluck('slug')
                ->toArray();
            $units = array_merge($units, $customUnits);
        }

        return $units;
    }

    /**
     * Get available selling units with labels for display in forms.
     *
     * @return array<array{value: string, label: string}>
     */
    public function getAvailableUnitsWithLabels(Tenant $tenant): array
    {
        $locale = app()->getLocale();
        $units = [];

        // Standard units with translated labels
        foreach (MealComponent::STANDARD_UNITS as $unit) {
            $labels = MealComponent::UNIT_LABELS[$unit] ?? ['en' => ucfirst($unit), 'fr' => ucfirst($unit)];
            $units[] = [
                'value' => $unit,
                'label' => $labels[$locale] ?? $labels['en'],
            ];
        }

        // Forward-compatible: custom units from F-121
        if (Schema::hasTable('selling_units')) {
            $customUnits = \App\Models\SellingUnit::where('tenant_id', $tenant->id)
                ->get();
            foreach ($customUnits as $customUnit) {
                $units[] = [
                    'value' => $customUnit->slug,
                    'label' => $customUnit->{'name_'.$locale} ?? $customUnit->name_en,
                ];
            }
        }

        return $units;
    }

    /**
     * Get components data for a meal (ordered by position).
     *
     * @return array{components: \Illuminate\Database\Eloquent\Collection, count: int}
     */
    public function getComponentsData(Meal $meal): array
    {
        $components = $meal->components()->ordered()->get();

        return [
            'components' => $components,
            'count' => $components->count(),
        ];
    }
}
