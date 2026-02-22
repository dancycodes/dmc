<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Support\Facades\Session;

/**
 * F-199: Reorder from Past Order
 *
 * Handles all logic for copying past order items into a new session cart,
 * checking item availability, price changes, and tenant status.
 */
class ReorderService
{
    /**
     * Session key prefix (mirrors CartService).
     */
    private const SESSION_KEY_PREFIX = 'dmc-cart-';

    /**
     * Status codes that qualify for reorder.
     *
     * BR-356: Only Completed, Delivered, or Picked Up orders can be reordered.
     *
     * @var array<string>
     */
    public const REORDER_ELIGIBLE_STATUSES = [
        Order::STATUS_COMPLETED,
        Order::STATUS_DELIVERED,
        Order::STATUS_PICKED_UP,
    ];

    /**
     * Prepare a reorder from a past order.
     *
     * Returns a structured result indicating success/failure and any
     * warnings about unavailable or price-changed items.
     *
     * @return array{
     *   success: bool,
     *   error: string|null,
     *   warnings: array<string>,
     *   price_changes: array<array{component_name: string, old_price: int, new_price: int}>,
     *   items_added: int,
     *   redirect_url: string|null,
     *   cart_conflict: bool,
     *   conflict_tenant_name: string|null
     * }
     */
    public function prepareReorder(Order $order, ?int $existingCartTenantId = null): array
    {
        // BR-363: Tenant must be active
        $tenant = $order->tenant;

        if (! $tenant || ! $tenant->is_active) {
            return [
                'success' => false,
                'error' => __('This cook is no longer available on DancyMeals.'),
                'warnings' => [],
                'price_changes' => [],
                'items_added' => 0,
                'redirect_url' => null,
                'cart_conflict' => false,
                'conflict_tenant_name' => null,
            ];
        }

        // Parse items from snapshot
        $snapshotItems = $this->parseItemsSnapshot($order->items_snapshot);

        if (empty($snapshotItems)) {
            return [
                'success' => false,
                'error' => __('Sorry, the meals from this order are no longer available.'),
                'warnings' => [],
                'price_changes' => [],
                'items_added' => 0,
                'redirect_url' => null,
                'cart_conflict' => false,
                'conflict_tenant_name' => null,
            ];
        }

        // Collect unique component IDs from snapshot
        $componentIds = collect($snapshotItems)
            ->pluck('component_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $mealIds = collect($snapshotItems)
            ->pluck('meal_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // BR-361: Load current meals (with soft-delete check)
        $currentMeals = Meal::query()
            ->whereIn('id', $mealIds)
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('id');

        // BR-360: Load current components
        $currentComponents = MealComponent::query()
            ->whereIn('id', $componentIds)
            ->get()
            ->keyBy('id');

        $locale = app()->getLocale();
        $cartItems = [];
        $warnings = [];
        $priceChanges = [];
        $unavailableCount = 0;

        foreach ($snapshotItems as $snapshotItem) {
            $componentId = $snapshotItem['component_id'] ?? null;
            $mealId = $snapshotItem['meal_id'] ?? null;
            $quantity = (int) ($snapshotItem['quantity'] ?? 1);
            $originalUnitPrice = (int) ($snapshotItem['unit_price'] ?? 0);
            $snapshotComponentName = $snapshotItem['component_name'] ?? ($snapshotItem['name'] ?? '');
            $snapshotMealName = $snapshotItem['meal_name'] ?? '';

            // BR-361: Meal has been deleted (soft-deleted) or doesn't exist
            if (! $componentId || ! $mealId) {
                $unavailableCount++;
                $warnings[] = __(':item is no longer available.', ['item' => $snapshotMealName ?: __('An item')]);

                continue;
            }

            $meal = $currentMeals->get($mealId);

            if (! $meal) {
                // Meal deleted or not found
                $unavailableCount++;
                $warnings[] = __(':item has been removed from the menu.', ['item' => $snapshotMealName ?: __('A meal')]);

                continue;
            }

            // BR-360: Meal must be live and available
            if ($meal->status !== Meal::STATUS_LIVE || ! $meal->is_available) {
                $unavailableCount++;
                $mealDisplayName = $meal->{'name_'.$locale} ?? $meal->name_en ?? $snapshotMealName;
                $warnings[] = __(':item is currently unavailable.', ['item' => $mealDisplayName]);

                continue;
            }

            $component = $currentComponents->get($componentId);

            if (! $component) {
                // Component deleted
                $unavailableCount++;
                $itemDisplayName = $snapshotComponentName ?: $snapshotMealName;
                $warnings[] = __(':item is no longer available.', ['item' => $itemDisplayName]);

                continue;
            }

            // BR-360: Component must be available
            if (! $component->is_available || $component->isOutOfStock()) {
                $unavailableCount++;
                $componentDisplayName = $component->{'name_'.$locale} ?? $component->name_en ?? $snapshotComponentName;
                $warnings[] = __(':item is currently unavailable.', ['item' => $componentDisplayName]);

                continue;
            }

            // BR-358: Use current price
            $currentPrice = $component->price;

            // BR-359: Track price changes
            if ($originalUnitPrice > 0 && $currentPrice !== $originalUnitPrice) {
                $priceChanges[] = [
                    'component_name' => $component->{'name_'.$locale} ?? $component->name_en ?? $snapshotComponentName,
                    'old_price' => $originalUnitPrice,
                    'new_price' => $currentPrice,
                ];
            }

            // Cap quantity to available stock
            $maxQty = $this->getMaxSelectableQuantity($component);
            $actualQuantity = min($quantity, $maxQty);

            if ($actualQuantity < $quantity) {
                $componentDisplayName = $component->{'name_'.$locale} ?? $component->name_en ?? $snapshotComponentName;
                $warnings[] = __(
                    'Quantity for :item was reduced from :old to :new (limited availability).',
                    [
                        'item' => $componentDisplayName,
                        'old' => $quantity,
                        'new' => $actualQuantity,
                    ]
                );
            }

            // BR-357: Add to cart items
            $mealDisplayName = $meal->{'name_'.$locale} ?? $meal->name_en ?? $snapshotMealName;
            $componentDisplayName = $component->{'name_'.$locale} ?? $component->name_en ?? $snapshotComponentName;

            $cartItems[(string) $componentId] = [
                'component_id' => $component->id,
                'meal_id' => $meal->id,
                'meal_name' => $mealDisplayName,
                'name' => $componentDisplayName,
                'unit_price' => $currentPrice,
                'unit' => $component->unit_label,
                'quantity' => $actualQuantity,
            ];
        }

        // BR-362: If all items are unavailable, fail gracefully
        if (empty($cartItems)) {
            return [
                'success' => false,
                'error' => __('Sorry, the meals from this order are no longer available.'),
                'warnings' => $warnings,
                'price_changes' => [],
                'items_added' => 0,
                'redirect_url' => null,
                'cart_conflict' => false,
                'conflict_tenant_name' => null,
            ];
        }

        // BR-365: Check for cart conflict (existing cart for different tenant)
        if ($existingCartTenantId !== null && $existingCartTenantId !== $tenant->id) {
            $conflictTenantName = $this->getConflictTenantName($existingCartTenantId, $locale);

            return [
                'success' => false,
                'error' => null,
                'warnings' => $warnings,
                'price_changes' => $priceChanges,
                'items_added' => count($cartItems),
                'redirect_url' => null,
                'cart_conflict' => true,
                'conflict_tenant_name' => $conflictTenantName,
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'warnings' => $warnings,
            'price_changes' => $priceChanges,
            'items_added' => count($cartItems),
            'redirect_url' => $tenant->getUrl().'/cart',
            'cart_conflict' => false,
            'conflict_tenant_name' => null,
            '_cart_items' => $cartItems,
            '_tenant_id' => $tenant->id,
        ];
    }

    /**
     * Write cart items to the session and return the redirect URL.
     *
     * BR-357: Replaces any existing cart for the target tenant.
     *
     * @param  array<string, array<string, mixed>>  $cartItems
     */
    public function writeCartToSession(int $tenantId, array $cartItems): void
    {
        Session::put(self::SESSION_KEY_PREFIX.$tenantId, $cartItems);
    }

    /**
     * Clear all existing carts (used when replacing a conflict cart).
     */
    public function clearAllCarts(): void
    {
        $keys = Session::all();
        foreach (array_keys($keys) as $key) {
            if (str_starts_with((string) $key, self::SESSION_KEY_PREFIX)) {
                Session::forget($key);
            }
        }
    }

    /**
     * Get the session key for a tenant's cart.
     */
    public function getSessionKey(int $tenantId): string
    {
        return self::SESSION_KEY_PREFIX.$tenantId;
    }

    /**
     * Get the currently active cart tenant ID from the session.
     *
     * Returns the tenant ID of the first non-empty cart found.
     */
    public function getActiveCartTenantId(): ?int
    {
        $sessionData = Session::all();

        foreach ($sessionData as $key => $value) {
            if (str_starts_with((string) $key, self::SESSION_KEY_PREFIX) && ! empty($value)) {
                $tenantId = (int) str_replace(self::SESSION_KEY_PREFIX, '', (string) $key);

                if ($tenantId > 0) {
                    return $tenantId;
                }
            }
        }

        return null;
    }

    /**
     * Check if an order is eligible for reorder.
     *
     * BR-356: Only Completed, Delivered, or Picked Up orders qualify.
     */
    public function isEligibleForReorder(Order $order): bool
    {
        return in_array($order->status, self::REORDER_ELIGIBLE_STATUSES, true);
    }

    /**
     * Parse items_snapshot from the order.
     *
     * Handles both array and JSON-string representations.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseItemsSnapshot(mixed $snapshot): array
    {
        if (is_array($snapshot)) {
            return $snapshot;
        }

        if (is_string($snapshot)) {
            $decoded = json_decode($snapshot, true);

            if (is_array($decoded)) {
                // Handle double-encoded JSON
                if (is_string($decoded[0] ?? null)) {
                    $decoded = json_decode($decoded[0], true);
                }

                return is_array($decoded) ? $decoded : [];
            }
        }

        return [];
    }

    /**
     * Get the maximum selectable quantity for a component.
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
     * Get a human-readable tenant name for cart conflict display.
     */
    private function getConflictTenantName(int $tenantId, string $locale): ?string
    {
        $tenant = Tenant::query()->select(['id', 'name_en', 'name_fr'])->find($tenantId);

        if (! $tenant) {
            return null;
        }

        return $tenant->{'name_'.$locale} ?? $tenant->name_en;
    }
}
