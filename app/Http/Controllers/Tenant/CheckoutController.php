<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\Request;

/**
 * F-140: Delivery/Pickup Choice Selection
 *
 * Handles checkout flow on tenant domains via Gale SSE.
 * BR-264: Client must choose delivery or pickup to proceed.
 * BR-272: Requires authentication.
 * BR-273: All text localized via __().
 */
class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutService $checkoutService,
        private CartService $cartService,
    ) {}

    /**
     * F-140: Display the delivery/pickup choice step.
     *
     * BR-264: Client must choose delivery or pickup.
     * BR-267: If no pickup locations, hide pickup option.
     * BR-268: If no delivery areas, hide delivery option.
     * BR-271: Choice persists via session.
     * BR-272: Requires authentication.
     */
    public function deliveryMethod(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        // BR-272: Require authentication
        if (! auth()->check()) {
            return gale()->redirect(route('login'))->with('message', __('Please login to proceed with your order.'));
        }

        // Verify cart is not empty
        $cart = $this->cartService->getCart($tenant->id);
        if (empty($cart['items'])) {
            return gale()->redirect(url('/cart'))->with('message', __('Your cart is empty.'));
        }

        $options = $this->checkoutService->getAvailableOptions($tenant->id);
        $currentMethod = $this->checkoutService->getDeliveryMethod($tenant->id);

        // Edge case: If previously selected method is no longer available, reset it
        if ($currentMethod === CheckoutService::METHOD_DELIVERY && ! $options['has_delivery']) {
            $currentMethod = null;
            $this->checkoutService->setDeliveryMethod($tenant->id, '');
        } elseif ($currentMethod === CheckoutService::METHOD_PICKUP && ! $options['has_pickup']) {
            $currentMethod = null;
            $this->checkoutService->setDeliveryMethod($tenant->id, '');
        }

        // Auto-select when only one option available and no valid selection
        if (! $currentMethod && $options['has_delivery'] && ! $options['has_pickup']) {
            $currentMethod = CheckoutService::METHOD_DELIVERY;
            $this->checkoutService->setDeliveryMethod($tenant->id, $currentMethod);
        } elseif (! $currentMethod && ! $options['has_delivery'] && $options['has_pickup']) {
            $currentMethod = CheckoutService::METHOD_PICKUP;
            $this->checkoutService->setDeliveryMethod($tenant->id, $currentMethod);
        }

        return gale()->view('tenant.checkout.delivery-method', [
            'tenant' => $tenant,
            'options' => $options,
            'currentMethod' => $currentMethod,
            'cartSummary' => $cart['summary'],
        ], web: true);
    }

    /**
     * F-140: Save delivery method selection and proceed.
     *
     * BR-264: Must choose delivery or pickup.
     * BR-265: Delivery leads to F-141.
     * BR-266: Pickup leads to F-142.
     */
    public function saveDeliveryMethod(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        // BR-272: Require authentication
        if (! auth()->check()) {
            return gale()->redirect(route('login'))->with('message', __('Please login to proceed with your order.'));
        }

        // Verify cart is not empty
        $cart = $this->cartService->getCart($tenant->id);
        if (empty($cart['items'])) {
            return gale()->redirect(url('/cart'))->with('message', __('Your cart is empty.'));
        }

        $validated = $request->validateState([
            'delivery_method' => 'required|in:delivery,pickup',
        ]);

        $method = $validated['delivery_method'];

        // Validate against available options
        $validation = $this->checkoutService->validateMethodSelection($tenant->id, $method);

        if (! $validation['valid']) {
            return gale()
                ->state('checkoutError', $validation['error']);
        }

        // Save selection to session
        $this->checkoutService->setDeliveryMethod($tenant->id, $method);

        // BR-265/BR-266: Redirect to next step (stubs for F-141/F-142)
        if ($method === CheckoutService::METHOD_DELIVERY) {
            // F-141 will provide the delivery location selection route
            return gale()->redirect(url('/checkout/delivery-location'))
                ->with('message', __('Choose your delivery location.'));
        }

        // F-142 will provide the pickup location selection route
        return gale()->redirect(url('/checkout/pickup-location'))
            ->with('message', __('Choose your pickup location.'));
    }
}
