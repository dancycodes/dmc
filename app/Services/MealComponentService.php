<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\SellingUnit;
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
     * Update an existing meal component.
     *
     * F-119: Meal Component Edit
     * BR-292: All validation rules from F-118 apply to edits
     * BR-293: Price changes apply to new orders only (handled at order time)
     * BR-294: Name, selling unit, and quantity changes take effect immediately
     * BR-295: Component edits are logged via Spatie Activitylog (in controller)
     * BR-297: If available quantity is edited to 0, auto-toggle to unavailable
     *
     * @param  array{name_en: string, name_fr: string, price: int, selling_unit: string, min_quantity?: int, max_quantity?: int|null, available_quantity?: int|null}  $data
     * @return array{success: bool, component?: MealComponent, error?: string, old_values?: array<string, mixed>}
     */
    public function updateComponent(MealComponent $component, array $data): array
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
        $tenant = $component->meal->tenant ?? tenant();
        $allowedUnits = $this->getAvailableUnits($tenant);
        if (! in_array($sellingUnit, $allowedUnits)) {
            return [
                'success' => false,
                'error' => __('Invalid selling unit selected.'),
            ];
        }

        // Capture old values for activity logging (BR-295)
        $oldValues = [
            'name_en' => $component->name_en,
            'name_fr' => $component->name_fr,
            'price' => $component->price,
            'selling_unit' => $component->selling_unit,
            'min_quantity' => $component->min_quantity,
            'max_quantity' => $component->max_quantity,
            'available_quantity' => $component->available_quantity,
            'is_available' => $component->is_available,
        ];

        // BR-297: Auto-toggle to unavailable if available_quantity is 0
        $isAvailable = $component->is_available;
        if ($availableQuantity === 0) {
            $isAvailable = false;
        }

        $component->update([
            'name_en' => $nameEn,
            'name_fr' => $nameFr,
            'price' => $price,
            'selling_unit' => $sellingUnit,
            'min_quantity' => $minQuantity,
            'max_quantity' => $maxQuantity,
            'available_quantity' => $availableQuantity,
            'is_available' => $isAvailable,
        ]);

        return [
            'success' => true,
            'component' => $component->fresh(),
            'old_values' => $oldValues,
        ];
    }

    /**
     * Delete a meal component.
     *
     * F-120: Meal Component Delete
     * BR-298: Cannot delete the last component of a live meal
     * BR-299: Cannot delete a component if pending orders include it
     * BR-300: Hard delete (order line items store snapshot data)
     * BR-304: Remaining components' positions are recalculated after deletion
     * BR-305: Requirement rules referencing the deleted component are also removed
     *
     * @return array{success: bool, error?: string, entity_name?: string}
     */
    public function deleteComponent(MealComponent $component): array
    {
        $meal = $component->meal;

        // BR-298: Cannot delete the last component of a live meal
        $componentCount = $meal->components()->count();
        if ($componentCount <= 1 && $meal->isLive()) {
            return [
                'success' => false,
                'error' => __('Cannot delete the only component of a live meal. Add another component first, or switch the meal to draft.'),
            ];
        }

        // BR-299: Cannot delete a component if pending orders include it
        $pendingOrderCount = $this->getPendingOrderCountForComponent($component);
        if ($pendingOrderCount > 0) {
            return [
                'success' => false,
                'error' => trans_choice(
                    'Cannot delete — :count pending order includes this component.|Cannot delete — :count pending orders include this component.',
                    $pendingOrderCount,
                    ['count' => $pendingOrderCount]
                ),
            ];
        }

        $entityName = $component->name;
        $position = $component->position;
        $mealId = $component->meal_id;

        // BR-305: Remove requirement rules referencing the deleted component
        $this->cleanupRequirementRules($component);

        // BR-300: Hard delete
        $component->delete();

        // BR-304: Recalculate positions for remaining components
        $this->recalculatePositions($mealId, $position);

        return [
            'success' => true,
            'entity_name' => $entityName,
        ];
    }

    /**
     * Get the count of pending orders that include a specific component.
     *
     * Forward-compatible: Uses Schema::hasTable for orders table (created by F-151+).
     */
    public function getPendingOrderCountForComponent(MealComponent $component): int
    {
        // Forward-compatible: orders table created by future features
        if (! Schema::hasTable('orders')) {
            return 0;
        }

        // Forward-compatible: order_items table with component references
        if (! Schema::hasTable('order_items')) {
            return 0;
        }

        // When orders exist, check for pending orders that include this component
        return \Illuminate\Support\Facades\DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.meal_component_id', $component->id)
            ->whereIn('orders.status', ['pending_payment', 'paid', 'confirmed', 'preparing', 'ready'])
            ->count();
    }

    /**
     * Clean up requirement rules referencing the deleted component.
     *
     * F-120/BR-305: Forward-compatible with F-122 (Meal Component Requirement Rules).
     */
    private function cleanupRequirementRules(MealComponent $component): void
    {
        // Forward-compatible: component_requirement_rules table created by F-122
        if (! Schema::hasTable('component_requirement_rules')) {
            return;
        }

        // Remove rules where this component is the subject or the required reference
        \Illuminate\Support\Facades\DB::table('component_requirement_rules')
            ->where('meal_component_id', $component->id)
            ->orWhere('required_component_id', $component->id)
            ->delete();
    }

    /**
     * Recalculate positions for remaining components after a deletion.
     *
     * BR-304: Ensures contiguous position ordering.
     */
    private function recalculatePositions(int $mealId, int $deletedPosition): void
    {
        MealComponent::where('meal_id', $mealId)
            ->where('position', '>', $deletedPosition)
            ->decrement('position');
    }

    /**
     * Get available selling unit IDs (standard + custom from F-121).
     *
     * @return array<string>
     */
    public function getAvailableUnits(Tenant $tenant): array
    {
        return app(SellingUnitService::class)->getValidUnitIds($tenant);
    }

    /**
     * Get available selling units with labels for display in forms.
     *
     * @return array<array{value: string, label: string, is_standard: bool}>
     */
    public function getAvailableUnitsWithLabels(Tenant $tenant): array
    {
        return app(SellingUnitService::class)->getUnitsWithLabels($tenant);
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

    /**
     * Check if a component can be deleted and return the reason if not.
     *
     * F-120: Used by the view to disable the delete button with a tooltip.
     *
     * @return array{can_delete: bool, reason?: string}
     */
    public function canDeleteComponent(MealComponent $component, Meal $meal, int $componentCount): array
    {
        // BR-298: Cannot delete the last component of a live meal
        if ($componentCount <= 1 && $meal->isLive()) {
            return [
                'can_delete' => false,
                'reason' => __('Cannot delete the only component of a live meal.'),
            ];
        }

        // BR-299: Cannot delete a component if pending orders include it
        $pendingOrderCount = $this->getPendingOrderCountForComponent($component);
        if ($pendingOrderCount > 0) {
            return [
                'can_delete' => false,
                'reason' => trans_choice(
                    ':count pending order includes this component.|:count pending orders include this component.',
                    $pendingOrderCount,
                    ['count' => $pendingOrderCount]
                ),
            ];
        }

        return ['can_delete' => true];
    }
}
