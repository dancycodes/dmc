<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\Request;

/**
 * F-138: Meal Component Selection & Cart Add
 * F-139: Order Cart Management
 *
 * Handles cart operations on tenant domains via Gale SSE.
 * BR-246: Cart state maintained in session.
 * BR-247: Guest carts work via session without authentication.
 * BR-251/BR-262: Cart updates use Gale (no page reload).
 * BR-259: Cart persists in server-side session across page navigations.
 */
class CartController extends Controller
{
    public function __construct(
        private CartService $cartService,
    ) {}

    /**
     * F-139: Display the cart page.
     * F-213: Passes minimum order amount for cart checkout enforcement.
     *
     * BR-253: Cart items displayed grouped by meal.
     * BR-254: Each item shows meal name, component name, quantity, unit price, line subtotal.
     * BR-259: Cart persists in session.
     * BR-262: All cart interactions use Gale.
     * BR-513: Minimum order amount is displayed in the cart view.
     */
    public function index(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        $cart = $this->cartService->getCartWithAvailability($tenant->id);

        // F-213 BR-513/BR-516: Minimum order amount for cart checkout enforcement
        $minimumOrderAmount = $tenant->getMinimumOrderAmount();

        return gale()->view('tenant.cart', [
            'tenant' => $tenant,
            'cart' => $cart,
            'minimumOrderAmount' => $minimumOrderAmount,
        ], web: true);
    }

    /**
     * F-139: Update quantity of a cart item.
     *
     * BR-255: Quantity adjustment respects component stock limits and minimum of 1.
     * BR-262: All cart interactions use Gale.
     */
    public function updateQuantity(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        $validated = $request->validateState([
            'component_id' => 'required|integer',
            'quantity' => 'required|integer|min:0',
        ]);

        $componentId = (int) $validated['component_id'];
        $quantity = (int) $validated['quantity'];

        // BR-255: Decrementing to 0 removes the item
        if ($quantity <= 0) {
            $result = $this->cartService->removeFromCart($tenant->id, $componentId);
        } else {
            // Cap at max per component
            $quantity = min($quantity, CartService::MAX_QUANTITY_PER_COMPONENT);
            $result = $this->cartService->updateQuantity($tenant->id, $componentId, $quantity);
        }

        if (! ($result['success'] ?? true)) {
            return gale()
                ->state('cartError', $result['error'] ?? '')
                ->state('cartCount', $result['cart']['summary']['count'])
                ->state('cartTotal', $result['cart']['summary']['total']);
        }

        $enrichedCart = $this->cartService->getCartWithAvailability($tenant->id);

        return gale()
            ->state('cartCount', $enrichedCart['summary']['count'])
            ->state('cartTotal', $enrichedCart['summary']['total'])
            ->state('cartMeals', $enrichedCart['meals'])
            ->state('cartError', '')
            ->state('cartSuccess', '');
    }

    /**
     * F-139: Clear the entire cart.
     *
     * BR-258: "Clear Cart" requires confirmation before executing.
     * BR-262: All cart interactions use Gale.
     */
    public function clearCart(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        $this->cartService->clearCart($tenant->id);
        $cart = $this->cartService->getCartWithAvailability($tenant->id);

        return gale()
            ->state('cartCount', $cart['summary']['count'])
            ->state('cartTotal', $cart['summary']['total'])
            ->state('cartMeals', $cart['meals'])
            ->state('cartError', '')
            ->state('cartSuccess', __('Cart cleared'));
    }

    /**
     * F-139: Proceed to checkout.
     *
     * BR-260: Requires authentication; guests are redirected to login.
     * BR-261: Cart cannot proceed to checkout if empty.
     */
    public function checkout(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        // BR-260: Require authentication
        if (! auth()->check()) {
            return gale()->redirect(route('login'))->with('message', __('Please login to proceed with your order.'));
        }

        // BR-261: Cannot checkout with empty cart
        $cart = $this->cartService->getCart($tenant->id);
        if (empty($cart['items'])) {
            return gale()
                ->state('cartError', __('Your cart is empty.'));
        }

        // F-140: Redirect to delivery method selection
        return gale()->redirect(url('/checkout/delivery-method'));
    }

    /**
     * F-138: Add a component to the cart.
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
     * F-138: Remove a component from the cart.
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
     * F-138: Get current cart state.
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
