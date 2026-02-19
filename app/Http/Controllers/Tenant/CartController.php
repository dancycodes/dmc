<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\Request;

/**
 * F-138: Meal Component Selection & Cart Add
 *
 * Handles cart operations on tenant domains via Gale SSE.
 * BR-246: Cart state maintained in session.
 * BR-247: Guest carts work via session without authentication.
 * BR-251: Cart updates use Gale (no page reload).
 */
class CartController extends Controller
{
    public function __construct(
        private CartService $cartService,
    ) {}

    /**
     * Add a component to the cart.
     *
     * BR-243: Quantity capped at min/max limits.
     * BR-244: Requirement rules enforced.
     * BR-245: Running total updates reactively.
     * BR-248: Same component updates quantity (no duplicates).
     * BR-251: Gale SSE response.
     */
    public function addToCart(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        $validated = $request->validateState([
            'component_id' => 'required|integer',
            'meal_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        $result = $this->cartService->addToCart(
            $tenant->id,
            (int) $validated['meal_id'],
            (int) $validated['component_id'],
            (int) $validated['quantity'],
        );

        if (! $result['success']) {
            return gale()
                ->state('cartError', $result['error'])
                ->state('cartCount', $result['cart']['summary']['count'])
                ->state('cartTotal', $result['cart']['summary']['total']);
        }

        $componentName = '';
        $quantity = (int) $validated['quantity'];
        foreach ($result['cart']['items'] as $item) {
            if ($item['component_id'] === (int) $validated['component_id']) {
                $componentName = $item['name'];
                break;
            }
        }

        return gale()
            ->state('cartCount', $result['cart']['summary']['count'])
            ->state('cartTotal', $result['cart']['summary']['total'])
            ->state('cartItems', $result['cart']['items'])
            ->state('cartMeals', $result['cart']['meals'])
            ->state('cartError', '')
            ->state('cartSuccess', __(':qty x :name added to cart', [
                'qty' => $quantity,
                'name' => $componentName,
            ]));
    }

    /**
     * Remove a component from the cart.
     */
    public function removeFromCart(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        $validated = $request->validateState([
            'component_id' => 'required|integer',
        ]);

        $result = $this->cartService->removeFromCart(
            $tenant->id,
            (int) $validated['component_id'],
        );

        return gale()
            ->state('cartCount', $result['cart']['summary']['count'])
            ->state('cartTotal', $result['cart']['summary']['total'])
            ->state('cartItems', $result['cart']['items'])
            ->state('cartMeals', $result['cart']['meals'])
            ->state('cartError', '')
            ->state('cartSuccess', '');
    }

    /**
     * Get current cart state.
     * Used to hydrate cart state on page load.
     */
    public function getCart(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        $cart = $this->cartService->getCart($tenant->id);

        return gale()
            ->state('cartCount', $cart['summary']['count'])
            ->state('cartTotal', $cart['summary']['total'])
            ->state('cartItems', $cart['items'])
            ->state('cartMeals', $cart['meals']);
    }
}
