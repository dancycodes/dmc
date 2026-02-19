<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Quarter;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\Request;

/**
 * F-140: Delivery/Pickup Choice Selection
 * F-141: Delivery Location Selection
 * F-142: Pickup Location Selection
 * F-143: Order Phone Number
 * F-146: Order Total Calculation & Summary
 * F-147: Location Not Available Flow
 *
 * Handles checkout flow on tenant domains via Gale SSE.
 * BR-264: Client must choose delivery or pickup to proceed.
 * BR-272: Requires authentication.
 * BR-273/BR-282/BR-290/BR-297/BR-326: All text localized via __().
 * BR-327-BR-334: Location not available messaging and contact options.
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

    /**
     * F-141: Display the delivery location selection step.
     *
     * BR-274: Town dropdown shows only towns where cook has delivery areas.
     * BR-275: Quarter dropdown filtered by selected town.
     * BR-276: Neighbourhood is a free-text field with OSM autocomplete.
     * BR-278: Saved addresses matching cook's areas shown as quick-select.
     * BR-279: If saved address in available area, pre-selected by default.
     * BR-281: All fields (town, quarter, neighbourhood) required.
     * BR-282: All form labels and text localized via __().
     * BR-283: Town/quarter names displayed in user's current language.
     */
    public function deliveryLocation(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        // Require authentication
        if (! auth()->check()) {
            return gale()->redirect(route('login'))
                ->with('message', __('Please login to proceed with your order.'));
        }

        // Verify cart is not empty
        $cart = $this->cartService->getCart($tenant->id);
        if (empty($cart['items'])) {
            return gale()->redirect(url('/cart'))
                ->with('message', __('Your cart is empty.'));
        }

        // Verify delivery method is selected
        $deliveryMethod = $this->checkoutService->getDeliveryMethod($tenant->id);
        if ($deliveryMethod !== CheckoutService::METHOD_DELIVERY) {
            return gale()->redirect(url('/checkout/delivery-method'))
                ->with('message', __('Please select a delivery method first.'));
        }

        // BR-274: Get cook's delivery towns
        $towns = $this->checkoutService->getDeliveryTowns($tenant->id);

        // BR-278/BR-279: Get matching saved addresses
        $savedAddresses = $this->checkoutService->getMatchingSavedAddresses(
            $tenant->id,
            auth()->id()
        );

        // Get current delivery location from session (for persistence)
        $currentLocation = $this->checkoutService->getDeliveryLocation($tenant->id);

        // BR-279: Pre-select first matching saved address if no location saved yet
        $preSelectedAddress = null;
        if (! $currentLocation && $savedAddresses->isNotEmpty()) {
            $preSelectedAddress = $savedAddresses->first();
            $currentLocation = [
                'town_id' => $preSelectedAddress->town_id,
                'quarter_id' => $preSelectedAddress->quarter_id,
                'neighbourhood' => $preSelectedAddress->neighbourhood ?? '',
            ];
        }

        // Edge case: If only 1 town, pre-select it
        if (! $currentLocation && $towns->count() === 1) {
            $currentLocation = [
                'town_id' => $towns->first()->id,
                'quarter_id' => null,
                'neighbourhood' => '',
            ];
        }

        // Load quarters for currently selected town (if any)
        $quarters = collect();
        if ($currentLocation && $currentLocation['town_id']) {
            $quarters = $this->checkoutService->getDeliveryQuarters(
                $tenant->id,
                $currentLocation['town_id']
            );

            // Edge case: If only 1 quarter in town, pre-select it
            if (! ($currentLocation['quarter_id'] ?? null) && $quarters->count() === 1) {
                $currentLocation['quarter_id'] = $quarters->first()['id'];
            }
        }

        // F-147: Get cook contact info for "location not available" flow
        $cookContact = $this->checkoutService->getCookContactInfo($tenant);
        $hasPickupLocations = $this->checkoutService->hasPickupLocations($tenant->id);

        return gale()->view('tenant.checkout.delivery-location', [
            'tenant' => $tenant,
            'towns' => $towns,
            'quarters' => $quarters,
            'savedAddresses' => $savedAddresses,
            'currentLocation' => $currentLocation,
            'preSelectedAddress' => $preSelectedAddress,
            'cartSummary' => $cart['summary'],
            'cookContact' => $cookContact,
            'hasPickupLocations' => $hasPickupLocations,
        ], web: true);
    }

    /**
     * F-141: Load quarters for a selected town (Gale action endpoint).
     *
     * BR-275: Quarter dropdown filtered by selected town.
     * Returns quarters as JSON via Gale state update.
     */
    public function loadQuarters(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        if (! auth()->check()) {
            return gale()->redirect(route('login'));
        }

        $validated = $request->validateState([
            'town_id' => 'required|integer|exists:towns,id',
        ]);

        $quarters = $this->checkoutService->getDeliveryQuarters(
            $tenant->id,
            (int) $validated['town_id']
        );

        return gale()
            ->state('quarters', $quarters->toArray())
            ->state('quarter_id', $quarters->count() === 1 ? (string) $quarters->first()['id'] : '')
            ->state('neighbourhood', '');
    }

    /**
     * F-141: Save delivery location and proceed to next step.
     *
     * BR-281: All fields (town, quarter, neighbourhood) are required.
     * BR-280: Selected quarter determines delivery fee (F-145).
     */
    public function saveDeliveryLocation(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        if (! auth()->check()) {
            return gale()->redirect(route('login'))
                ->with('message', __('Please login to proceed with your order.'));
        }

        // Verify cart is not empty
        $cart = $this->cartService->getCart($tenant->id);
        if (empty($cart['items'])) {
            return gale()->redirect(url('/cart'))
                ->with('message', __('Your cart is empty.'));
        }

        // Verify delivery method is selected
        $deliveryMethod = $this->checkoutService->getDeliveryMethod($tenant->id);
        if ($deliveryMethod !== CheckoutService::METHOD_DELIVERY) {
            return gale()->redirect(url('/checkout/delivery-method'))
                ->with('message', __('Please select a delivery method first.'));
        }

        // BR-281: Validate all required fields
        $validated = $request->validateState([
            'town_id' => 'required|integer|exists:towns,id',
            'quarter_id' => 'required|integer|exists:quarters,id',
            'neighbourhood' => 'required|string|max:500',
        ]);

        // Validate that the quarter is in the cook's delivery areas
        $validation = $this->checkoutService->validateDeliveryQuarter(
            $tenant->id,
            (int) $validated['quarter_id']
        );

        if (! $validation['valid']) {
            return gale()->messages([
                'quarter_id' => $validation['error'],
            ]);
        }

        // Validate that the quarter belongs to the selected town
        $quarter = Quarter::find((int) $validated['quarter_id']);
        if ($quarter && $quarter->town_id !== (int) $validated['town_id']) {
            return gale()->messages([
                'quarter_id' => __('The selected quarter does not belong to the selected town.'),
            ]);
        }

        // Save delivery location to session
        $this->checkoutService->setDeliveryLocation($tenant->id, [
            'town_id' => (int) $validated['town_id'],
            'quarter_id' => (int) $validated['quarter_id'],
            'neighbourhood' => trim($validated['neighbourhood']),
        ]);

        // F-143 will provide the next checkout step (Order Phone Number)
        return gale()->redirect(url('/checkout/phone'))
            ->with('message', __('Delivery location saved.'));
    }

    /**
     * F-147: Switch delivery method to pickup and redirect to pickup location selection.
     *
     * BR-331: A "Switch to Pickup" option is provided if the cook has pickup locations.
     * This is triggered from the "location not available" warning card.
     */
    public function switchToPickup(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        if (! auth()->check()) {
            return gale()->redirect(route('login'))
                ->with('message', __('Please login to proceed with your order.'));
        }

        // Verify cook has pickup locations
        if (! $this->checkoutService->hasPickupLocations($tenant->id)) {
            return gale()->redirect(url('/checkout/delivery-method'))
                ->with('message', __('Pickup is not available for this cook.'));
        }

        // Switch delivery method to pickup
        $this->checkoutService->setDeliveryMethod($tenant->id, CheckoutService::METHOD_PICKUP);

        return gale()->redirect(url('/checkout/pickup-location'))
            ->with('message', __('Switched to pickup. Choose your pickup location.'));
    }

    /**
     * F-142: Display the pickup location selection step.
     *
     * BR-284: All configured pickup locations for the cook are displayed.
     * BR-285: Each pickup location shows name, full address, special instructions.
     * BR-287: If only one pickup location exists, it is pre-selected automatically.
     * BR-288: Pickup is always free.
     * BR-290: All text localized via __().
     * BR-291: Location names displayed in user's current language.
     */
    public function pickupLocation(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        // Require authentication
        if (! auth()->check()) {
            return gale()->redirect(route('login'))
                ->with('message', __('Please login to proceed with your order.'));
        }

        // Verify cart is not empty
        $cart = $this->cartService->getCart($tenant->id);
        if (empty($cart['items'])) {
            return gale()->redirect(url('/cart'))
                ->with('message', __('Your cart is empty.'));
        }

        // Verify pickup method is selected
        $deliveryMethod = $this->checkoutService->getDeliveryMethod($tenant->id);
        if ($deliveryMethod !== CheckoutService::METHOD_PICKUP) {
            return gale()->redirect(url('/checkout/delivery-method'))
                ->with('message', __('Please select a delivery method first.'));
        }

        // BR-284: Get all pickup locations for this cook
        $pickupLocations = $this->checkoutService->getPickupLocations($tenant->id);

        // Edge case: No pickup locations (should not be reachable via F-140)
        if ($pickupLocations->isEmpty()) {
            return gale()->redirect(url('/checkout/delivery-method'))
                ->with('message', __('No pickup locations available. Please choose a different delivery method.'));
        }

        // Get current selection from session
        $currentPickupLocationId = $this->checkoutService->getPickupLocationId($tenant->id);

        // BR-287: If only one pickup location, pre-select it automatically
        if (! $currentPickupLocationId && $pickupLocations->count() === 1) {
            $currentPickupLocationId = $pickupLocations->first()->id;
        }

        // Validate that previously stored selection is still valid
        if ($currentPickupLocationId && ! $pickupLocations->contains('id', $currentPickupLocationId)) {
            $currentPickupLocationId = $pickupLocations->count() === 1 ? $pickupLocations->first()->id : null;
        }

        return gale()->view('tenant.checkout.pickup-location', [
            'tenant' => $tenant,
            'pickupLocations' => $pickupLocations,
            'currentPickupLocationId' => $currentPickupLocationId,
            'cartSummary' => $cart['summary'],
        ], web: true);
    }

    /**
     * F-142: Save pickup location selection and proceed to next step.
     *
     * BR-286: Client must select exactly one pickup location.
     * BR-289: The selected pickup location is stored in the checkout session.
     */
    public function savePickupLocation(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        if (! auth()->check()) {
            return gale()->redirect(route('login'))
                ->with('message', __('Please login to proceed with your order.'));
        }

        // Verify cart is not empty
        $cart = $this->cartService->getCart($tenant->id);
        if (empty($cart['items'])) {
            return gale()->redirect(url('/cart'))
                ->with('message', __('Your cart is empty.'));
        }

        // Verify pickup method is selected
        $deliveryMethod = $this->checkoutService->getDeliveryMethod($tenant->id);
        if ($deliveryMethod !== CheckoutService::METHOD_PICKUP) {
            return gale()->redirect(url('/checkout/delivery-method'))
                ->with('message', __('Please select a delivery method first.'));
        }

        // BR-286: Validate pickup location selection
        $validated = $request->validateState([
            'pickup_location_id' => 'required|integer',
        ]);

        $pickupLocationId = (int) $validated['pickup_location_id'];

        // Validate that the pickup location belongs to this cook
        $validation = $this->checkoutService->validatePickupLocation(
            $tenant->id,
            $pickupLocationId
        );

        if (! $validation['valid']) {
            return gale()->redirect(url('/checkout/delivery-method'))
                ->with('message', $validation['error']);
        }

        // BR-289: Save selection to session
        $this->checkoutService->setPickupLocation($tenant->id, $pickupLocationId);

        // F-143: Proceed to phone number step
        return gale()->redirect(url('/checkout/phone'))
            ->with('message', __('Pickup location saved.'));
    }

    /**
     * F-143: Display the order phone number step.
     *
     * BR-292: Phone number is pre-filled from user's profile phone number.
     * BR-293: Client can override the phone number per order.
     * BR-296: Phone number is a required field.
     * BR-297: Validation error messages must be localized via __().
     */
    public function phoneNumber(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        // Require authentication
        if (! auth()->check()) {
            return gale()->redirect(route('login'))
                ->with('message', __('Please login to proceed with your order.'));
        }

        // Verify cart is not empty
        $cart = $this->cartService->getCart($tenant->id);
        if (empty($cart['items'])) {
            return gale()->redirect(url('/cart'))
                ->with('message', __('Your cart is empty.'));
        }

        // Verify a delivery method has been selected
        $deliveryMethod = $this->checkoutService->getDeliveryMethod($tenant->id);
        if (! $deliveryMethod) {
            return gale()->redirect(url('/checkout/delivery-method'))
                ->with('message', __('Please select a delivery method first.'));
        }

        // BR-292: Pre-fill from profile or session
        $user = auth()->user();
        $prefilledPhone = $this->checkoutService->getPrefilledPhone($tenant->id, $user->phone);

        // Strip the +237 prefix for the input field (shown as non-editable prefix)
        $phoneDigits = $prefilledPhone;
        if (str_starts_with($phoneDigits, '+237')) {
            $phoneDigits = substr($phoneDigits, 4);
        }

        $backUrl = $this->checkoutService->getPhoneStepBackUrl($tenant->id);

        return gale()->view('tenant.checkout.phone', [
            'tenant' => $tenant,
            'phoneDigits' => $phoneDigits,
            'profilePhone' => $user->phone ?? '',
            'deliveryMethod' => $deliveryMethod,
            'cartSummary' => $cart['summary'],
            'backUrl' => $backUrl,
        ], web: true);
    }

    /**
     * F-143: Save order phone number and proceed to next step.
     *
     * BR-295: Phone must match Cameroon format: +237 followed by 9 digits starting with 6, 7, or 2.
     * BR-296: Phone number is a required field.
     * BR-294: Overriding the phone number does NOT update user's profile.
     * BR-298: Phone number is stored with the order for delivery/communication purposes.
     */
    public function savePhoneNumber(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        if (! auth()->check()) {
            return gale()->redirect(route('login'))
                ->with('message', __('Please login to proceed with your order.'));
        }

        // Verify cart is not empty
        $cart = $this->cartService->getCart($tenant->id);
        if (empty($cart['items'])) {
            return gale()->redirect(url('/cart'))
                ->with('message', __('Your cart is empty.'));
        }

        // Verify delivery method is selected
        $deliveryMethod = $this->checkoutService->getDeliveryMethod($tenant->id);
        if (! $deliveryMethod) {
            return gale()->redirect(url('/checkout/delivery-method'))
                ->with('message', __('Please select a delivery method first.'));
        }

        // BR-296: Required + BR-295: Cameroon format validation
        $validated = $request->validateState([
            'phone_digits' => 'required|string',
        ]);

        // Normalize: prepend +237 and strip spaces/dashes
        $normalizedPhone = RegisterRequest::normalizePhone($validated['phone_digits']);

        // BR-295: Validate Cameroon phone format
        if (! preg_match(RegisterRequest::CAMEROON_PHONE_REGEX, $normalizedPhone)) {
            return gale()->messages([
                'phone_digits' => __('Please enter a valid Cameroon phone number (+237 followed by 9 digits).'),
            ]);
        }

        // BR-298: Save phone to checkout session (BR-294: does NOT update profile)
        $this->checkoutService->setPhone($tenant->id, $normalizedPhone);

        // F-146: Proceed to Order Total & Summary
        return gale()->redirect(url('/checkout/summary'))
            ->with('message', __('Phone number saved.'));
    }

    /**
     * F-146: Display the order total calculation and summary.
     *
     * BR-316: Itemized list shows meal name, component name, quantity, unit price, line subtotal.
     * BR-317: Items are grouped by meal.
     * BR-318: Subtotal is the sum of all food item line subtotals.
     * BR-319: Delivery fee shown as separate line item; pickup shows "Pickup - Free".
     * BR-321: Grand total = subtotal + delivery fee - promo discount.
     * BR-322: All amounts in XAF with thousand separators.
     * BR-323: Summary updates reactively via Gale.
     * BR-324: Edit Cart link returns to cart without losing checkout progress.
     * BR-325: Proceed to Payment leads to F-149.
     * BR-326: All text localized via __().
     */
    public function summary(Request $request): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        // Require authentication
        if (! auth()->check()) {
            return gale()->redirect(route('login'))
                ->with('message', __('Please login to proceed with your order.'));
        }

        // Edge case: Cart is empty when reaching summary
        $cart = $this->cartService->getCartWithAvailability($tenant->id);
        if (empty($cart['items'])) {
            return gale()->redirect(url('/cart'))
                ->with('message', __('Your cart is empty. Add items before reviewing your order.'));
        }

        // Verify checkout steps are complete
        $deliveryMethod = $this->checkoutService->getDeliveryMethod($tenant->id);
        if (! $deliveryMethod) {
            return gale()->redirect(url('/checkout/delivery-method'))
                ->with('message', __('Please select a delivery method first.'));
        }

        $phone = $this->checkoutService->getPhone($tenant->id);
        if (! $phone) {
            return gale()->redirect(url('/checkout/phone'))
                ->with('message', __('Please provide your phone number first.'));
        }

        // BR-316 through BR-322: Build order summary
        $orderSummary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

        $backUrl = $this->checkoutService->getSummaryBackUrl();

        return gale()->view('tenant.checkout.summary', [
            'tenant' => $tenant,
            'orderSummary' => $orderSummary,
            'deliveryMethod' => $deliveryMethod,
            'phone' => $phone,
            'backUrl' => $backUrl,
        ], web: true);
    }
}
