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
     * F-122/BR-321: Delegates to ComponentRequirementRuleService for proper cleanup.
     */
    private function cleanupRequirementRules(MealComponent $component): void
    {
        app(ComponentRequirementRuleService::class)->cleanupRulesForComponent($component);
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
     * Toggle a meal component's availability.
     *
     * F-123: Meal Component Availability Toggle
     * BR-326: Component availability is independent of meal availability
     * BR-329: Toggling availability does not affect pending orders
     * BR-330: Availability toggle has immediate effect
     *
     * @return array{success: bool, old_availability: bool, new_availability: bool, component: MealComponent}
     */
    public function toggleAvailability(MealComponent $component): array
    {
        $oldAvailability = $component->is_available;
        $newAvailability = ! $oldAvailability;

        $component->update(['is_available' => $newAvailability]);

        return [
            'success' => true,
            'old_availability' => $oldAvailability,
            'new_availability' => $newAvailability,
            'component' => $component->fresh(),
        ];
    }

    /**
     * Update only the quantity settings of a meal component.
     *
     * F-124: Meal Component Quantity Settings
     * BR-334: Minimum quantity default is 0
     * BR-335: Maximum quantity default is null (unlimited)
     * BR-336: Available quantity default is null (unlimited)
     * BR-337: When available quantity reaches 0, auto-toggle to is_available = false
     * BR-339: Minimum quantity must be >= 0
     * BR-340: Maximum quantity must be >= minimum quantity (when both are set)
     * BR-341: Available quantity must be >= 0
     * BR-345: Quantity changes take immediate effect for new orders
     * BR-347: Quantity changes are logged via Spatie Activitylog (in controller)
     *
     * @param  array{min_quantity?: int|null, max_quantity?: int|null, available_quantity?: int|null}  $data
     * @return array{success: bool, component?: MealComponent, error?: string, old_values?: array<string, mixed>}
     */
    public function updateQuantitySettings(MealComponent $component, array $data): array
    {
        $minQuantity = (int) ($data['min_quantity'] ?? 0);
        $maxQuantity = isset($data['max_quantity']) && $data['max_quantity'] !== '' && $data['max_quantity'] !== null
            ? (int) $data['max_quantity']
            : null;
        $availableQuantity = isset($data['available_quantity']) && $data['available_quantity'] !== '' && $data['available_quantity'] !== null
            ? (int) $data['available_quantity']
            : null;

        // BR-340: Max quantity must be >= min quantity when both are set
        if ($maxQuantity !== null && $minQuantity > $maxQuantity) {
            return [
                'success' => false,
                'error' => __('Minimum quantity cannot be greater than maximum quantity.'),
            ];
        }

        // Capture old values for activity logging (BR-347)
        $oldValues = [
            'min_quantity' => $component->min_quantity,
            'max_quantity' => $component->max_quantity,
            'available_quantity' => $component->available_quantity,
            'is_available' => $component->is_available,
        ];

        // BR-337: Auto-toggle to unavailable if available_quantity reaches 0
        $isAvailable = $component->is_available;
        if ($availableQuantity !== null && $availableQuantity <= 0) {
            $availableQuantity = 0;
            $isAvailable = false;
        }

        $component->update([
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
     * Reorder meal components by updating their positions.
     *
     * F-125: Meal Component List View
     * BR-350: Drag-and-drop reordering updates the `position` field on all affected components
     * BR-354: Position changes are persisted immediately via Gale
     *
     * @param  array<int>  $componentIds  Ordered array of component IDs
     * @return array{success: bool, error?: string}
     */
    public function reorderComponents(Meal $meal, array $componentIds): array
    {
        // Validate that all provided IDs belong to this meal
        $mealComponentIds = $meal->components()->pluck('id')->toArray();
        $invalidIds = array_diff($componentIds, $mealComponentIds);

        if (! empty($invalidIds)) {
            return [
                'success' => false,
                'error' => __('Invalid component IDs provided.'),
            ];
        }

        // Validate that all meal components are included
        $missingIds = array_diff($mealComponentIds, $componentIds);
        if (! empty($missingIds)) {
            return [
                'success' => false,
                'error' => __('All components must be included in the reorder.'),
            ];
        }

        // Update positions in order
        foreach ($componentIds as $position => $componentId) {
            MealComponent::where('id', $componentId)
                ->where('meal_id', $meal->id)
                ->update(['position' => $position + 1]);
        }

        return ['success' => true];
    }

    /**
     * Decrement the available quantity of a component after order placement.
     *
     * F-124/BR-338: Available quantity decrements on successful order placement
     * (not on cart add, but on payment confirmation).
     * BR-337: When available quantity reaches 0, auto-toggle to is_available = false.
     *
     * @return array{success: bool, new_quantity: int|null, auto_unavailable: bool}
     */
    public function decrementAvailableQuantity(MealComponent $component, int $quantity = 1): array
    {
        // Unlimited stock — no decrement needed
        if ($component->hasUnlimitedAvailableQuantity()) {
            return [
                'success' => true,
                'new_quantity' => null,
                'auto_unavailable' => false,
            ];
        }

        $newQuantity = max(0, $component->available_quantity - $quantity);
        $autoUnavailable = $newQuantity === 0;

        $updateData = ['available_quantity' => $newQuantity];
        if ($autoUnavailable) {
            $updateData['is_available'] = false;
        }

        $component->update($updateData);

        return [
            'success' => true,
            'new_quantity' => $newQuantity,
            'auto_unavailable' => $autoUnavailable,
        ];
    }

    /**
     * Increment the available quantity of a component (e.g. on order cancellation).
     *
     * F-124: Forward-compatible for order cancellation stock restoration.
     */
    public function incrementAvailableQuantity(MealComponent $component, int $quantity = 1): array
    {
        // Unlimited stock — no increment needed
        if ($component->hasUnlimitedAvailableQuantity()) {
            return [
                'success' => true,
                'new_quantity' => null,
            ];
        }

        $newQuantity = $component->available_quantity + $quantity;
        $component->update(['available_quantity' => $newQuantity]);

        return [
            'success' => true,
            'new_quantity' => $newQuantity,
        ];
    }

    /**
     * Check if this component has low stock (under threshold).
     */
    public function isLowStock(MealComponent $component, int $threshold = 5): bool
    {
        if ($component->hasUnlimitedAvailableQuantity()) {
            return false;
        }

        return $component->available_quantity > 0 && $component->available_quantity <= $threshold;
    }

    /**
     * Get the stock status label for a component.
     *
     * @return array{label: string, type: string}
     */
    public function getStockStatus(MealComponent $component): array
    {
        if ($component->hasUnlimitedAvailableQuantity()) {
            return ['label' => __('Unlimited'), 'type' => 'unlimited'];
        }

        if ($component->available_quantity === 0) {
            return ['label' => __('Out of stock'), 'type' => 'out_of_stock'];
        }

        if ($this->isLowStock($component)) {
            return [
                'label' => trans_choice(':count left|:count left', $component->available_quantity, ['count' => $component->available_quantity]),
                'type' => 'low_stock',
            ];
        }

        return [
            'label' => __(':count in stock', ['count' => $component->available_quantity]),
            'type' => 'in_stock',
        ];
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
