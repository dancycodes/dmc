<?php

namespace App\Services;

use App\Models\ComponentRequirementRule;
use App\Models\Meal;
use App\Models\MealComponent;

/**
 * F-138: Meal Component Selection & Cart Add
 *
 * Manages server-side session cart for ordering on tenant domains.
 * BR-246: Cart state maintained in session, accessible across tenant site.
 * BR-247: Guest carts work via session without authentication.
 * BR-249: Cart items grouped by meal for display.
 * BR-250: All prices in XAF (integer, no decimals).
 */
class CartService
{
    /**
     * Session key prefix for tenant-scoped carts.
     */
    private const SESSION_KEY_PREFIX = 'dmc-cart-';

    /**
     * Maximum number of distinct items in the cart.
     * Edge case: practical maximum enforced.
     */
    public const MAX_CART_ITEMS = 50;

    /**
     * Get the session key for the current tenant's cart.
     */
    private function getSessionKey(int $tenantId): string
    {
        return self::SESSION_KEY_PREFIX.$tenantId;
    }

    /**
     * Get the full cart data for a tenant.
     *
     * BR-249: Items grouped by meal.
     *
     * @return array{items: array, meals: array, summary: array{count: int, total: int}}
     */
    public function getCart(int $tenantId): array
    {
        $items = session($this->getSessionKey($tenantId), []);

        return [
            'items' => $items,
            'meals' => $this->groupByMeal($items),
            'summary' => $this->calculateSummary($items),
        ];
    }

    /**
     * Add a component to the cart or update its quantity.
     *
     * BR-243: Quantity min is 1, max is lesser of stock or cook-defined max.
     * BR-244: Requirement rules enforced.
     * BR-248: Same component updates quantity, no duplicates.
     *
     * @return array{success: bool, error: string|null, cart: array}
     */
    public function addToCart(
        int $tenantId,
        int $mealId,
        int $componentId,
        int $quantity,
    ): array {
        $meal = Meal::query()
            ->where('id', $mealId)
            ->where('tenant_id', $tenantId)
            ->where('status', Meal::STATUS_LIVE)
            ->where('is_available', true)
            ->first();

        if (! $meal) {
            return [
                'success' => false,
                'error' => __('This meal is no longer available.'),
                'cart' => $this->getCart($tenantId),
            ];
        }

        $component = MealComponent::query()
            ->where('id', $componentId)
            ->where('meal_id', $mealId)
            ->first();

        if (! $component) {
            return [
                'success' => false,
                'error' => __('This item is no longer available.'),
                'cart' => $this->getCart($tenantId),
            ];
        }

        // BR-162: Cannot add sold-out components
        if (! $component->is_available || $component->isOutOfStock()) {
            return [
                'success' => false,
                'error' => __('This item is sold out.'),
                'cart' => $this->getCart($tenantId),
            ];
        }

        // BR-243: Enforce quantity limits
        $maxSelectable = $this->getMaxSelectableQuantity($component);
        $quantity = max(1, min($quantity, $maxSelectable));

        // BR-244: Enforce requirement rules
        $requirementError = $this->validateRequirementRules($component, $tenantId, $mealId);
        if ($requirementError) {
            return [
                'success' => false,
                'error' => $requirementError,
                'cart' => $this->getCart($tenantId),
            ];
        }

        // Check cart item limit
        $items = session($this->getSessionKey($tenantId), []);
        $key = (string) $componentId;

        if (! isset($items[$key]) && count($items) >= self::MAX_CART_ITEMS) {
            return [
                'success' => false,
                'error' => __('Cart is full. Maximum :max items allowed.', ['max' => self::MAX_CART_ITEMS]),
                'cart' => $this->getCart($tenantId),
            ];
        }

        $locale = app()->getLocale();

        // BR-248: Update existing or create new entry
        if (isset($items[$key])) {
            $items[$key]['quantity'] = $quantity;
            $items[$key]['unit_price'] = $component->price;
        } else {
            $items[$key] = [
                'component_id' => $component->id,
                'meal_id' => $mealId,
                'meal_name' => $meal->{'name_'.$locale} ?? $meal->name_en,
                'name' => $component->{'name_'.$locale} ?? $component->name_en,
                'unit_price' => $component->price,
                'unit' => $component->unit_label,
                'quantity' => $quantity,
            ];
        }

        session([$this->getSessionKey($tenantId) => $items]);

        return [
            'success' => true,
            'error' => null,
            'cart' => $this->getCart($tenantId),
        ];
    }

    /**
     * Remove a component from the cart.
     *
     * @return array{success: bool, cart: array}
     */
    public function removeFromCart(int $tenantId, int $componentId): array
    {
        $items = session($this->getSessionKey($tenantId), []);
        $key = (string) $componentId;

        unset($items[$key]);

        session([$this->getSessionKey($tenantId) => $items]);

        return [
            'success' => true,
            'cart' => $this->getCart($tenantId),
        ];
    }

    /**
     * Update quantity for an existing cart item.
     *
     * @return array{success: bool, error: string|null, cart: array}
     */
    public function updateQuantity(int $tenantId, int $componentId, int $quantity): array
    {
        $items = session($this->getSessionKey($tenantId), []);
        $key = (string) $componentId;

        if (! isset($items[$key])) {
            return [
                'success' => false,
                'error' => __('Item not found in cart.'),
                'cart' => $this->getCart($tenantId),
            ];
        }

        $component = MealComponent::find($componentId);
        if (! $component) {
            // Clean up stale cart entry
            unset($items[$key]);
            session([$this->getSessionKey($tenantId) => $items]);

            return [
                'success' => false,
                'error' => __('This item is no longer available.'),
                'cart' => $this->getCart($tenantId),
            ];
        }

        $maxSelectable = $this->getMaxSelectableQuantity($component);
        $quantity = max(1, min($quantity, $maxSelectable));

        $items[$key]['quantity'] = $quantity;
        $items[$key]['unit_price'] = $component->price;

        session([$this->getSessionKey($tenantId) => $items]);

        return [
            'success' => true,
            'error' => null,
            'cart' => $this->getCart($tenantId),
        ];
    }

    /**
     * Clear the entire cart for a tenant.
     *
     * @return array{success: bool, cart: array}
     */
    public function clearCart(int $tenantId): array
    {
        session()->forget($this->getSessionKey($tenantId));

        return [
            'success' => true,
            'cart' => $this->getCart($tenantId),
        ];
    }

    /**
     * Get the cart summary (count + total).
     *
     * @return array{count: int, total: int}
     */
    public function getCartSummary(int $tenantId): array
    {
        $items = session($this->getSessionKey($tenantId), []);

        return $this->calculateSummary($items);
    }

    /**
     * Calculate summary from cart items.
     *
     * BR-245: Running total updates reactively.
     * BR-250: All prices in XAF (integer).
     *
     * @return array{count: int, total: int}
     */
    private function calculateSummary(array $items): array
    {
        $count = 0;
        $total = 0;

        foreach ($items as $item) {
            $count += $item['quantity'];
            $total += $item['unit_price'] * $item['quantity'];
        }

        return [
            'count' => $count,
            'total' => $total,
        ];
    }

    /**
     * Group cart items by meal.
     *
     * BR-249: Cart items grouped by meal for display.
     *
     * @return array<int, array{meal_id: int, meal_name: string, items: array, subtotal: int}>
     */
    private function groupByMeal(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $mealId = $item['meal_id'];

            if (! isset($grouped[$mealId])) {
                $grouped[$mealId] = [
                    'meal_id' => $mealId,
                    'meal_name' => $item['meal_name'],
                    'items' => [],
                    'subtotal' => 0,
                ];
            }

            $grouped[$mealId]['items'][] = $item;
            $grouped[$mealId]['subtotal'] += $item['unit_price'] * $item['quantity'];
        }

        return array_values($grouped);
    }

    /**
     * Get the maximum selectable quantity for a component.
     *
     * BR-243: Max is the lesser of available stock or cook-defined max.
     */
    private function getMaxSelectableQuantity(MealComponent $component): int
    {
        $limits = [];

        if (! $component->hasUnlimitedMaxQuantity()) {
            $limits[] = $component->max_quantity;
        }

        if (! $component->hasUnlimitedAvailableQuantity()) {
            $limits[] = $component->available_quantity;
        }

        if (empty($limits)) {
            return 99;
        }

        return max(1, min($limits));
    }

    /**
     * Validate requirement rules for adding a component.
     *
     * BR-244: Dependent components cannot be added without required counterpart.
     *
     * @return string|null Error message or null if valid.
     */
    private function validateRequirementRules(MealComponent $component, int $tenantId, int $mealId): ?string
    {
        $rules = $component->requirementRules()->with('targetComponents')->get();

        if ($rules->isEmpty()) {
            return null;
        }

        $cartItems = session($this->getSessionKey($tenantId), []);
        $cartComponentIds = collect($cartItems)
            ->filter(fn ($item) => $item['meal_id'] === $mealId)
            ->pluck('component_id')
            ->toArray();

        $locale = app()->getLocale();

        foreach ($rules as $rule) {
            $targetIds = $rule->targetComponents->pluck('id')->toArray();
            $targetNames = $rule->targetComponents->map(
                fn ($target) => $target->{'name_'.$locale} ?? $target->name_en
            )->toArray();

            switch ($rule->rule_type) {
                case ComponentRequirementRule::RULE_TYPE_REQUIRES_ANY_OF:
                    $hasAny = ! empty(array_intersect($targetIds, $cartComponentIds));
                    if (! $hasAny) {
                        return __(
                            ':component requires at least one of: :targets',
                            [
                                'component' => $component->{'name_'.$locale} ?? $component->name_en,
                                'targets' => implode(', ', $targetNames),
                            ]
                        );
                    }
                    break;

                case ComponentRequirementRule::RULE_TYPE_REQUIRES_ALL_OF:
                    $hasAll = empty(array_diff($targetIds, $cartComponentIds));
                    if (! $hasAll) {
                        $missingNames = $rule->targetComponents
                            ->filter(fn ($target) => ! in_array($target->id, $cartComponentIds))
                            ->map(fn ($target) => $target->{'name_'.$locale} ?? $target->name_en)
                            ->toArray();

                        return __(
                            ':component requires: :targets',
                            [
                                'component' => $component->{'name_'.$locale} ?? $component->name_en,
                                'targets' => implode(', ', $missingNames),
                            ]
                        );
                    }
                    break;

                case ComponentRequirementRule::RULE_TYPE_INCOMPATIBLE_WITH:
                    $hasIncompatible = ! empty(array_intersect($targetIds, $cartComponentIds));
                    if ($hasIncompatible) {
                        $conflictNames = $rule->targetComponents
                            ->filter(fn ($target) => in_array($target->id, $cartComponentIds))
                            ->map(fn ($target) => $target->{'name_'.$locale} ?? $target->name_en)
                            ->toArray();

                        return __(
                            ':component is incompatible with: :targets',
                            [
                                'component' => $component->{'name_'.$locale} ?? $component->name_en,
                                'targets' => implode(', ', $conflictNames),
                            ]
                        );
                    }
                    break;
            }
        }

        return null;
    }

    /**
     * Check if a specific component is in the cart for a given meal.
     *
     * Used by the meal detail view to determine requirement rule satisfaction.
     *
     * @return array<int, int> Map of component_id => quantity for the meal
     */
    public function getCartComponentsForMeal(int $tenantId, int $mealId): array
    {
        $items = session($this->getSessionKey($tenantId), []);
        $result = [];

        foreach ($items as $item) {
            if ($item['meal_id'] === $mealId) {
                $result[$item['component_id']] = $item['quantity'];
            }
        }

        return $result;
    }

    /**
     * Format price for display.
     */
    public static function formatPrice(int $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }
}
