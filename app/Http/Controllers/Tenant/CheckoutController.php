<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Quarter;
use App\Models\Tenant;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderNotificationService;
use App\Services\PaymentNotificationService;
use App\Services\PaymentReceiptService;
use App\Services\PaymentRetryService;
use App\Services\PaymentService;
use App\Services\WalletPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * F-140: Delivery/Pickup Choice Selection
 * F-141: Delivery Location Selection
 * F-142: Pickup Location Selection
 * F-143: Order Phone Number
 * F-146: Order Total Calculation & Summary
 * F-147: Location Not Available Flow
 * F-149: Payment Method Selection
 * F-150: Flutterwave Payment Initiation
 * F-152: Payment Retry with Timeout
 * F-153: Wallet Balance Payment
 * F-154: Payment Receipt & Confirmation
 *
 * Handles checkout flow on tenant domains via Gale SSE.
 * BR-264: Client must choose delivery or pickup to proceed.
 * BR-272: Requires authentication.
 * BR-273/BR-282/BR-290/BR-297/BR-326/BR-353: All text localized via __().
 * BR-327-BR-334: Location not available messaging and contact options.
 * BR-345-BR-352: Payment method selection rules.
 * BR-387-BR-397: Wallet balance payment rules.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutService $checkoutService,
        private CartService $cartService,
        private PaymentService $paymentService,
        private PaymentRetryService $paymentRetryService,
        private PaymentReceiptService $paymentReceiptService,
        private WalletPaymentService $walletPaymentService,
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

    /**
     * F-149: Display the payment method selection step.
     *
     * BR-345: Available payment methods: MTN Mobile Money, Orange Money, Wallet Balance.
     * BR-346: Wallet Balance only if admin enabled AND balance >= total.
     * BR-347: Wallet visible but disabled if balance < total.
     * BR-348: Mobile money requires phone number, pre-filled from profile or saved methods.
     * BR-349: Previously used payment methods offered as saved options.
     * BR-350: Total to pay displayed prominently.
     * BR-353: All text localized via __().
     */
    public function paymentMethod(Request $request): mixed
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
        $cart = $this->cartService->getCartWithAvailability($tenant->id);
        if (empty($cart['items'])) {
            return gale()->redirect(url('/cart'))
                ->with('message', __('Your cart is empty.'));
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

        // Build order summary for grand total
        $orderSummary = $this->checkoutService->getOrderSummary($tenant->id, $cart);
        $grandTotal = $orderSummary['grand_total'];

        // BR-345-BR-349: Get payment options
        $user = auth()->user();
        $paymentOptions = $this->checkoutService->getPaymentOptions(
            $tenant->id,
            $user->id,
            $grandTotal
        );

        // Get current payment selection from session
        $currentProvider = $this->checkoutService->getPaymentProvider($tenant->id);
        $currentPaymentPhone = $this->checkoutService->getPaymentPhone($tenant->id);

        // BR-348: Pre-fill phone for mobile money
        $prefillPhone = $this->checkoutService->getPaymentPrefillPhone(
            $tenant->id,
            $user->phone,
            $currentProvider,
            $paymentOptions['saved_methods']
        );

        // Strip +237 prefix for input field
        $phoneDigits = $prefillPhone;
        if (str_starts_with($phoneDigits, '+237')) {
            $phoneDigits = substr($phoneDigits, 4);
        }

        $backUrl = $this->checkoutService->getPaymentBackUrl();

        // F-168: Persist use_wallet toggle state
        $useWallet = $this->checkoutService->getUseWallet($tenant->id);

        return gale()->view('tenant.checkout.payment', [
            'tenant' => $tenant,
            'grandTotal' => $grandTotal,
            'paymentOptions' => $paymentOptions,
            'currentProvider' => $currentProvider,
            'phoneDigits' => $phoneDigits,
            'backUrl' => $backUrl,
            'useWallet' => $useWallet,
        ], web: true);
    }

    /**
     * F-149: Save payment method selection.
     *
     * BR-352: Pay Now triggers F-150 (Flutterwave) for mobile money or F-153 for wallet.
     * BR-351: Phone number for mobile money must match Cameroon format.
     */
    public function savePaymentMethod(Request $request): mixed
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
        $cart = $this->cartService->getCartWithAvailability($tenant->id);
        if (empty($cart['items'])) {
            return gale()->redirect(url('/cart'))
                ->with('message', __('Your cart is empty.'));
        }

        // Verify checkout steps are complete
        $deliveryMethod = $this->checkoutService->getDeliveryMethod($tenant->id);
        if (! $deliveryMethod) {
            return gale()->redirect(url('/checkout/delivery-method'))
                ->with('message', __('Please select a delivery method first.'));
        }

        // F-168: Validate provider selection (use_wallet flag for partial payments)
        $validated = $request->validateState([
            'provider' => 'required|string|in:mtn_momo,orange_money,wallet',
            'payment_phone_digits' => 'nullable|string',
            'use_wallet' => 'nullable|boolean',
        ]);

        $provider = $validated['provider'];
        $useWallet = (bool) ($validated['use_wallet'] ?? false);
        $paymentPhone = null;

        // For mobile money, normalize the phone number
        if ($provider !== 'wallet') {
            $rawPhone = $validated['payment_phone_digits'] ?? '';
            $paymentPhone = PaymentMethod::normalizePhone($rawPhone);
        }

        // Build order summary for total
        $orderSummary = $this->checkoutService->getOrderSummary($tenant->id, $cart);
        $grandTotal = $orderSummary['grand_total'];

        // Validate payment selection (BR-302: partial wallet validation)
        $validation = $this->checkoutService->validatePaymentSelection(
            $provider,
            $paymentPhone,
            auth()->id(),
            $grandTotal,
            $useWallet
        );

        if (! $validation['valid']) {
            return gale()->messages([
                'payment_phone_digits' => $validation['error'],
            ]);
        }

        // F-168: Save to checkout session with use_wallet flag
        $this->checkoutService->setPaymentMethod($tenant->id, $provider, $paymentPhone, $useWallet);

        // BR-352: Redirect to payment initiation
        // F-153 for full wallet, F-150 for mobile money (with or without partial wallet)
        if ($provider === 'wallet') {
            // F-153: Full Wallet Balance Payment
            return gale()->redirect(url('/checkout/payment/wallet'))
                ->with('message', __('Processing wallet payment...'));
        }

        // F-150/F-168: Flutterwave Payment Initiation (with optional partial wallet)
        return gale()->redirect(url('/checkout/payment/initiate'))
            ->with('message', __('Initiating payment...'));
    }

    /**
     * F-150: Initiate Flutterwave payment.
     *
     * BR-354: Payment initiated via Flutterwave v3 mobile money charge API.
     * BR-358: Order status is set to "Pending Payment" upon initiation.
     * BR-359: A transaction reference is generated and stored with the order.
     * BR-360: The UI shows a "Waiting for payment" loading state after initiation.
     * BR-362: Initiation errors are displayed to the client with actionable messages.
     */
    public function initiatePayment(Request $request): mixed
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
        $cart = $this->cartService->getCartWithAvailability($tenant->id);
        if (empty($cart['items'])) {
            return gale()->redirect(url('/cart'))
                ->with('message', __('Your cart is empty.'));
        }

        // Verify complete checkout data
        $deliveryMethod = $this->checkoutService->getDeliveryMethod($tenant->id);
        if (! $deliveryMethod) {
            return gale()->redirect(url('/checkout/delivery-method'))
                ->with('message', __('Please select a delivery method first.'));
        }

        $paymentProvider = $this->checkoutService->getPaymentProvider($tenant->id);
        if (! $paymentProvider || $paymentProvider === 'wallet') {
            return gale()->redirect(url('/checkout/payment'))
                ->with('message', __('Please select a payment method first.'));
        }

        $user = auth()->user();

        // Check for existing pending order to prevent duplicates
        // BR-363/Edge case: Duplicate payment attempts
        $existingOrder = Order::query()
            ->where('client_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->where('status', Order::STATUS_PENDING_PAYMENT)
            ->where('created_at', '>=', now()->subMinutes(Order::PAYMENT_TIMEOUT_MINUTES))
            ->first();

        if ($existingOrder) {
            // Redirect to existing waiting page instead of creating duplicate
            return gale()->redirect(url('/checkout/payment/waiting/'.$existingOrder->id));
        }

        // F-168: Check if partial wallet payment is requested
        $useWallet = $this->checkoutService->getUseWallet($tenant->id);

        // Create the order
        $orderResult = $this->paymentService->createOrder($tenant, $user);

        if (! $orderResult['success']) {
            return gale()->redirect(url('/checkout/payment'))
                ->with('error', $orderResult['error']);
        }

        $order = $orderResult['order'];

        // F-168 BR-302: Deduct wallet portion before Flutterwave initiation
        if ($useWallet) {
            $walletResult = $this->walletPaymentService->deductWalletForPartialPayment($order, $user, $tenant);

            if (! $walletResult['success']) {
                // Wallet deduction failed — proceed without wallet (pure mobile money)
                Log::warning('F-168: Wallet deduction failed, proceeding without wallet', [
                    'order_id' => $order->id,
                    'error' => $walletResult['error'],
                ]);
            } else {
                // F-168: Update order payment_provider to reflect partial wallet
                $currentProvider = $order->payment_provider;
                $order->update([
                    'payment_provider' => 'wallet_'.$currentProvider,
                ]);
            }
        }

        // F-168 BR-305: Initiate Flutterwave for the remainder amount
        $paymentResult = $this->paymentService->initiatePayment($order, $user);

        if (! $paymentResult['success']) {
            // BR-362: Show error to client
            // F-168 BR-308: Reverse wallet deduction if Flutterwave initiation fails
            if ($useWallet && (float) $order->wallet_amount > 0) {
                $this->walletPaymentService->reverseWalletDeduction($order, $user);
            }

            // Mark order as payment_failed so it can be retried
            $order->update(['status' => Order::STATUS_PAYMENT_FAILED]);

            return gale()->redirect(url('/checkout/payment'))
                ->with('error', $paymentResult['error']);
        }

        // F-152 BR-377: Set retry window
        $this->paymentRetryService->initRetryWindow($order);

        // Clear cart after successful order creation + payment initiation
        $this->cartService->clearCart($tenant->id);

        // BR-360: Redirect to waiting page
        return gale()->redirect(url('/checkout/payment/waiting/'.$order->id));
    }

    /**
     * F-153: Process wallet balance payment.
     *
     * BR-387: Only available when admin has enabled wallet payments globally.
     * BR-388: Wallet balance must be >= order total.
     * BR-389: Deduct order total from wallet balance.
     * BR-390: Instant deduction; no external payment gateway involved.
     * BR-391: Order status immediately changes to "Paid".
     * BR-392: Wallet transaction record created.
     * BR-393: Cook wallet credited with (order amount - commission).
     * BR-394: Commission calculated identically to Flutterwave payments.
     * BR-395: Logged via Spatie Activitylog.
     * BR-396: No partial wallet payment supported.
     * BR-397: All text localized via __().
     */
    public function processWalletPayment(Request $request): mixed
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
        $cart = $this->cartService->getCartWithAvailability($tenant->id);
        if (empty($cart['items'])) {
            return gale()->redirect(url('/cart'))
                ->with('message', __('Your cart is empty.'));
        }

        // Verify complete checkout data
        $deliveryMethod = $this->checkoutService->getDeliveryMethod($tenant->id);
        if (! $deliveryMethod) {
            return gale()->redirect(url('/checkout/delivery-method'))
                ->with('message', __('Please select a delivery method first.'));
        }

        $paymentProvider = $this->checkoutService->getPaymentProvider($tenant->id);
        if ($paymentProvider !== 'wallet') {
            return gale()->redirect(url('/checkout/payment'))
                ->with('message', __('Please select wallet as your payment method.'));
        }

        $user = auth()->user();

        // Process the wallet payment
        $result = $this->walletPaymentService->processWalletPayment($tenant, $user);

        if (! $result['success']) {
            return gale()->redirect(url('/checkout/payment'))
                ->with('error', $result['error']);
        }

        $order = $result['order'];

        // Clear cart after successful payment
        $this->cartService->clearCart($tenant->id);

        // Clear checkout session data
        $this->checkoutService->clearCheckoutData($tenant->id);

        // Redirect to receipt page (F-154)
        return gale()->redirect(url('/checkout/payment/receipt/'.$order->id))
            ->with('message', __('Payment successful!'));
    }

    /**
     * F-150: Display the payment waiting page.
     *
     * BR-360: The UI shows a "Waiting for payment" loading state after initiation.
     * BR-361: Timeout after 15 minutes if no webhook confirmation received.
     * F-152: Sets retry window on initial payment and redirects to retry page on failure.
     */
    public function paymentWaiting(Request $request, int $orderId): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        if (! auth()->check()) {
            return gale()->redirect(route('login'));
        }

        // Load order and verify ownership
        $order = Order::query()
            ->where('id', $orderId)
            ->where('client_id', auth()->id())
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $order) {
            return gale()->redirect(url('/'))
                ->with('error', __('Order not found.'));
        }

        // F-152 BR-377: Init retry window on first visit
        $this->paymentRetryService->initRetryWindow($order);

        // Check current payment status
        $paymentStatus = $this->paymentService->checkPaymentStatus($order);

        // If payment is already complete, redirect to receipt (F-154)
        if ($paymentStatus['is_complete']) {
            return gale()->redirect(url('/checkout/payment/receipt/'.$order->id))
                ->with('message', __('Payment successful!'));
        }

        // F-152: If payment failed or timed out, redirect to retry page
        if ($paymentStatus['is_failed'] || $paymentStatus['is_timed_out']) {
            return gale()->redirect(url('/checkout/payment/retry/'.$order->id));
        }

        $remainingSeconds = $order->getPaymentTimeoutRemainingSeconds();

        return gale()->view('tenant.checkout.payment-waiting', [
            'tenant' => $tenant,
            'order' => $order,
            'paymentStatus' => $paymentStatus,
            'remainingSeconds' => $remainingSeconds,
            'errorMessage' => null,
        ], web: true);
    }

    /**
     * F-150: Check payment status (polled by the waiting page).
     *
     * Returns current payment status as Gale state update.
     */
    public function checkPaymentStatus(Request $request, int $orderId): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        if (! auth()->check()) {
            return gale()->state('paymentStatus', 'error');
        }

        $order = Order::query()
            ->where('id', $orderId)
            ->where('client_id', auth()->id())
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $order) {
            return gale()->state('paymentStatus', 'error');
        }

        $paymentStatus = $this->paymentService->checkPaymentStatus($order);

        if ($paymentStatus['is_complete']) {
            // Redirect to receipt page (F-154 forward-compatible)
            return gale()->redirect(url('/checkout/payment/receipt/'.$order->id))
                ->with('message', __('Payment successful!'));
        }

        // F-152: Redirect to retry page on failure/timeout
        if ($paymentStatus['is_timed_out'] || $paymentStatus['is_failed']) {
            return gale()->redirect(url('/checkout/payment/retry/'.$order->id));
        }

        $remainingSeconds = $order->getPaymentTimeoutRemainingSeconds();

        return gale()
            ->state('paymentStatus', 'pending')
            ->state('remainingSeconds', $remainingSeconds);
    }

    /**
     * F-150: Cancel payment and return to payment method selection.
     *
     * UI/UX: "Cancel" option available during waiting state.
     */
    public function cancelPayment(Request $request, int $orderId): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        if (! auth()->check()) {
            return gale()->redirect(route('login'));
        }

        $order = Order::query()
            ->where('id', $orderId)
            ->where('client_id', auth()->id())
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED])
            ->first();

        if ($order) {
            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            activity('orders')
                ->performedOn($order)
                ->causedBy(auth()->user())
                ->withProperties(['order_number' => $order->order_number])
                ->log('Payment cancelled by client');
        }

        return gale()->redirect(url('/'))
            ->with('message', __('Order cancelled.'));
    }

    /**
     * F-152: Display the payment retry page.
     *
     * BR-376: On payment failure, order remains in "Pending Payment" for retry window.
     * BR-378: A visible countdown timer shows remaining retry time.
     * BR-383: Failure reason from Flutterwave displayed to client.
     * BR-384: After retry limit, "Retry" button is disabled.
     */
    public function paymentRetry(Request $request, int $orderId): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        if (! auth()->check()) {
            return gale()->redirect(route('login'));
        }

        $order = Order::query()
            ->where('id', $orderId)
            ->where('client_id', auth()->id())
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $order) {
            return gale()->redirect(url('/'))
                ->with('error', __('Order not found.'));
        }

        // If order is already paid, redirect to receipt
        if ($order->status === Order::STATUS_PAID) {
            return gale()->redirect(url('/checkout/payment/receipt/'.$order->id))
                ->with('message', __('Payment successful!'));
        }

        // If order is cancelled (not pending_payment/payment_failed), redirect home
        if (! in_array($order->status, [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED], true)) {
            return gale()->redirect(url('/'))
                ->with('message', __('This order is no longer active.'));
        }

        // Init retry window if not set
        $this->paymentRetryService->initRetryWindow($order);
        $order->refresh();

        // Check for auto-expiration
        if ($order->isPaymentTimedOut() && ! $order->hasExhaustedRetries()) {
            $this->paymentRetryService->cancelExpiredOrders();
            $order->refresh();
        }

        $retryData = $this->paymentRetryService->getRetryData($order);

        // Get saved payment methods for the user
        $user = auth()->user();
        $savedMethods = PaymentMethod::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->get();

        // Strip +237 prefix for display
        $currentPaymentPhone = $order->payment_phone ?? $order->phone;
        $phoneDigits = $currentPaymentPhone;
        if (str_starts_with($phoneDigits, '+237')) {
            $phoneDigits = substr($phoneDigits, 4);
        }

        return gale()->view('tenant.checkout.payment-retry', [
            'tenant' => $tenant,
            'order' => $order,
            'retryData' => $retryData,
            'savedMethods' => $savedMethods,
            'phoneDigits' => $phoneDigits,
            'currentProvider' => $order->payment_provider,
        ], web: true);
    }

    /**
     * F-152: Process a payment retry attempt.
     *
     * BR-379: Maximum 3 retry attempts allowed per order.
     * BR-380: Each retry creates a new Flutterwave charge.
     */
    public function processRetryPayment(Request $request, int $orderId): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        if (! auth()->check()) {
            return gale()->redirect(route('login'));
        }

        $order = Order::query()
            ->where('id', $orderId)
            ->where('client_id', auth()->id())
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $order) {
            return gale()->redirect(url('/'))
                ->with('error', __('Order not found.'));
        }

        // Validate retry payment selection
        $validated = $request->validateState([
            'provider' => 'required|string|in:mtn_momo,orange_money',
            'payment_phone_digits' => 'nullable|string',
        ]);

        $provider = $validated['provider'];
        $rawPhone = $validated['payment_phone_digits'] ?? '';
        $paymentPhone = PaymentMethod::normalizePhone($rawPhone);

        // Validate phone format
        if (! preg_match(RegisterRequest::CAMEROON_PHONE_REGEX, $paymentPhone)) {
            return gale()->messages([
                'payment_phone_digits' => __('Please enter a valid Cameroon phone number (+237 followed by 9 digits).'),
            ]);
        }

        $user = auth()->user();

        // Attempt retry
        $result = $this->paymentRetryService->retryPayment($order, $user, $provider, $paymentPhone);

        if (! $result['success']) {
            // Refresh order for latest state
            $order->refresh();

            // If order got cancelled (max retries or expired), reload the page
            if ($order->status === Order::STATUS_CANCELLED) {
                return gale()->redirect(url('/checkout/payment/retry/'.$order->id));
            }

            return gale()->messages([
                'retry_error' => $result['error'],
            ]);
        }

        // On successful initiation, redirect to waiting page
        return gale()->redirect(url('/checkout/payment/waiting/'.$order->id));
    }

    /**
     * F-154: Display the payment receipt and confirmation page.
     *
     * BR-398: Displays order number, item summary, total amount, payment method,
     *         transaction reference, order status.
     * BR-400: Push notification sent to client confirming payment.
     * BR-401: Push notification sent to cook about new order.
     * BR-402: Email receipt sent to client's registered email.
     * BR-403: Receipt can be downloaded as PDF (via browser print).
     * BR-404: Track Order links to order tracking page.
     * BR-405: Order status is "Paid" at this point.
     * BR-406: Confirmation page is accessible only to the order's owner.
     * BR-407: Notifications use all three channels: push, database, email.
     * BR-408: All text localized via __().
     */
    public function paymentReceipt(Request $request, int $orderId): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        // Require authentication
        if (! auth()->check()) {
            return gale()->redirect(route('login'))
                ->with('message', __('Please login to view your receipt.'));
        }

        // Load the order
        $order = Order::query()
            ->where('id', $orderId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $order) {
            return gale()->redirect(url('/'))
                ->with('error', __('Order not found.'));
        }

        // BR-406: Verify order ownership
        if (! $this->paymentReceiptService->isOrderOwner($order, auth()->id())) {
            abort(403, __('You are not authorized to view this receipt.'));
        }

        // Verify order is paid (or a later status)
        if ($order->status === Order::STATUS_PENDING_PAYMENT || $order->status === Order::STATUS_PAYMENT_FAILED) {
            return gale()->redirect(url('/checkout/payment/waiting/'.$order->id))
                ->with('message', __('Payment is still being processed.'));
        }

        // Get receipt data
        $receiptData = $this->paymentReceiptService->getReceiptData($order);
        $deliveryLabel = $this->paymentReceiptService->getDeliveryMethodLabel($order);
        $shareText = $this->paymentReceiptService->getShareText($order, $tenant);

        // BR-404: Track Order URL (F-161 forward-compatible)
        $trackOrderUrl = url('/orders/'.$order->id);

        // Send notifications (idempotent — only send once per order)
        $this->sendPaymentNotifications($order, $tenant);

        return gale()->view('tenant.checkout.payment-receipt', [
            'tenant' => $tenant,
            'order' => $receiptData['order'],
            'items' => $receiptData['items'],
            'paymentLabel' => $receiptData['payment_label'],
            'transactionReference' => $receiptData['transaction_reference'],
            'deliveryLabel' => $deliveryLabel,
            'shareText' => $shareText,
            'trackOrderUrl' => $trackOrderUrl,
        ], web: true);
    }

    /**
     * Send payment notifications (push, database, email).
     *
     * F-194 BR-299: Client receives push + DB + email receipt on payment success.
     * F-191 BR-269/BR-270: Cook + managers receive push + DB + email for new order.
     *
     * Uses a session flag to prevent duplicate notifications on page refresh.
     * The webhook (WebhookService) also dispatches the client notification to cover
     * clients who navigate away before reaching the receipt page.
     *
     * Edge case: If email/push fails, order is still confirmed (BR-299 edge case).
     */
    private function sendPaymentNotifications(Order $order, Tenant $tenant): void
    {
        // Prevent duplicate notifications on page refresh
        $notificationKey = 'payment_notified_'.$order->id;
        if (session()->has($notificationKey)) {
            return;
        }

        // Mark as sent (even if sending fails — idempotent)
        session()->put($notificationKey, true);

        $client = auth()->user();

        // F-194 BR-299: Push + DB + Email receipt to client (N-006)
        try {
            $transaction = $order->paymentTransactions()
                ->where('status', 'successful')
                ->latest()
                ->first();

            $paymentNotificationService = app(PaymentNotificationService::class);
            $paymentNotificationService->notifyPaymentSuccess(
                order: $order,
                tenant: $tenant,
                client: $client,
                transaction: $transaction,
            );
        } catch (\Exception $e) {
            Log::warning('F-194: Client payment success notification failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        // F-191 BR-269/BR-270: Push + DB + Email to cook AND all managers (N-001)
        try {
            $notificationService = app(OrderNotificationService::class);
            $notificationService->notifyNewOrder($order, $tenant);
        } catch (\Exception $e) {
            Log::warning('F-191: Cook/manager order notification failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
