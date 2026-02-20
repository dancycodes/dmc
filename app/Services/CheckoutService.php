<?php

namespace App\Services;

use App\Models\Address;
use App\Models\DeliveryArea;
use App\Models\DeliveryAreaQuarter;
use App\Models\Meal;
use App\Models\MealComponent;
use App\Models\PaymentMethod;
use App\Models\PickupLocation;
use App\Models\Quarter;
use App\Models\QuarterGroup;
use App\Models\Tenant;
use App\Models\Town;
use Illuminate\Support\Collection;

/**
 * F-140: Delivery/Pickup Choice Selection
 * F-141: Delivery Location Selection
 * F-142: Pickup Location Selection
 * F-145: Delivery Fee Calculation
 *
 * Manages checkout session state including delivery method, location, pickup selection,
 * and delivery fee calculation.
 * BR-264: Client must choose delivery or pickup to proceed.
 * BR-271: Choice persists across navigation via session.
 * BR-274-BR-283: Delivery location selection rules.
 * BR-284-BR-291: Pickup location selection rules.
 * BR-307-BR-315: Delivery fee calculation rules.
 */
class CheckoutService
{
    /**
     * Session key prefix for checkout data.
     */
    private const SESSION_KEY_PREFIX = 'dmc-checkout-';

    /**
     * Delivery method constants.
     */
    public const METHOD_DELIVERY = 'delivery';

    public const METHOD_PICKUP = 'pickup';

    /**
     * Get the session key for a tenant's checkout data.
     */
    private function getSessionKey(int $tenantId): string
    {
        return self::SESSION_KEY_PREFIX.$tenantId;
    }

    /**
     * Get checkout session data for a tenant.
     *
     * @return array{delivery_method: string|null, delivery_location: array|null, delivery_fee: int|null, pickup_location_id: int|null, phone: string|null, payment_provider: string|null, payment_phone: string|null}
     */
    public function getCheckoutData(int $tenantId): array
    {
        return session($this->getSessionKey($tenantId), [
            'delivery_method' => null,
            'delivery_location' => null,
            'delivery_fee' => null,
            'pickup_location_id' => null,
            'phone' => null,
            'payment_provider' => null,
            'payment_phone' => null,
        ]);
    }

    /**
     * Set the delivery method in the checkout session.
     *
     * BR-264: Must be 'delivery' or 'pickup'.
     */
    public function setDeliveryMethod(int $tenantId, string $method): void
    {
        $data = $this->getCheckoutData($tenantId);
        $data['delivery_method'] = $method;

        session([$this->getSessionKey($tenantId) => $data]);
    }

    /**
     * Get the currently selected delivery method.
     */
    public function getDeliveryMethod(int $tenantId): ?string
    {
        $data = $this->getCheckoutData($tenantId);

        return $data['delivery_method'] ?? null;
    }

    /**
     * F-141: Save delivery location to checkout session.
     * F-145: Also calculates and stores the delivery fee.
     *
     * BR-281: All fields (town, quarter, neighbourhood) required.
     * BR-307: Delivery fee is determined by the selected quarter.
     * BR-308: If the quarter belongs to a fee group, the group fee is used.
     * BR-309: If the quarter has an individual fee (no group), the individual fee is used.
     *
     * @param  array{town_id: int, quarter_id: int, neighbourhood: string}  $location
     */
    public function setDeliveryLocation(int $tenantId, array $location): void
    {
        $data = $this->getCheckoutData($tenantId);
        $quarterId = (int) $location['quarter_id'];

        $data['delivery_location'] = [
            'town_id' => (int) $location['town_id'],
            'quarter_id' => $quarterId,
            'neighbourhood' => trim($location['neighbourhood']),
        ];

        // F-145: Calculate and store the delivery fee alongside the location
        $data['delivery_fee'] = $this->calculateDeliveryFee($tenantId, $quarterId);

        session([$this->getSessionKey($tenantId) => $data]);
    }

    /**
     * F-141: Get the saved delivery location from checkout session.
     *
     * @return array{town_id: int, quarter_id: int, neighbourhood: string}|null
     */
    public function getDeliveryLocation(int $tenantId): ?array
    {
        $data = $this->getCheckoutData($tenantId);

        return $data['delivery_location'] ?? null;
    }

    /**
     * F-145: Calculate the delivery fee for a given quarter.
     *
     * BR-307: Delivery fee is determined by the selected quarter.
     * BR-308: If the quarter belongs to a fee group, the group fee is used.
     * BR-309: If the quarter has an individual fee (no group), the individual fee is used.
     * BR-314: Pickup orders have no delivery fee (always 0).
     */
    public function calculateDeliveryFee(int $tenantId, int $quarterId): int
    {
        $validation = $this->validateDeliveryQuarter($tenantId, $quarterId);

        if (! $validation['valid']) {
            return 0;
        }

        return $validation['delivery_fee'] ?? 0;
    }

    /**
     * F-145: Get the stored delivery fee from checkout session.
     *
     * BR-313: The delivery fee is added to the order total (F-146).
     * BR-314: Pickup orders have no delivery fee (always 0).
     *
     * Returns 0 for pickup orders and when no fee is stored.
     */
    public function getStoredDeliveryFee(int $tenantId): int
    {
        $data = $this->getCheckoutData($tenantId);
        $method = $data['delivery_method'] ?? null;

        // BR-314: Pickup orders have no delivery fee
        if ($method === self::METHOD_PICKUP) {
            return 0;
        }

        return $data['delivery_fee'] ?? 0;
    }

    /**
     * F-145: Get delivery fee display data for the checkout UI.
     *
     * BR-310: A fee of 0 XAF is displayed as "Free delivery".
     * BR-311: The fee is displayed in the format: "Delivery to {quarter}: {fee} XAF".
     * BR-314: Pickup orders have no delivery fee.
     *
     * @return array{fee: int, quarter_name: string|null, is_free: bool, display_text: string}
     */
    public function getDeliveryFeeDisplayData(int $tenantId): array
    {
        $data = $this->getCheckoutData($tenantId);
        $method = $data['delivery_method'] ?? null;

        // BR-314: Pickup orders always show 0 fee
        if ($method === self::METHOD_PICKUP) {
            return [
                'fee' => 0,
                'quarter_name' => null,
                'is_free' => true,
                'display_text' => __('Pickup - No delivery fee'),
            ];
        }

        $fee = $data['delivery_fee'] ?? 0;
        $location = $data['delivery_location'] ?? null;
        $quarterName = null;

        if ($location && isset($location['quarter_id'])) {
            $locale = app()->getLocale();
            $nameColumn = 'name_'.$locale;
            $quarter = Quarter::find($location['quarter_id']);
            $quarterName = $quarter ? ($quarter->{$nameColumn} ?? $quarter->name_en) : null;
        }

        $isFree = $fee === 0;

        // BR-311: Format display text
        if ($quarterName) {
            $displayText = $isFree
                ? __('Delivery to :quarter: Free delivery', ['quarter' => $quarterName])
                : __('Delivery to :quarter: :fee XAF', ['quarter' => $quarterName, 'fee' => number_format($fee, 0, '.', ',')]);
        } else {
            $displayText = $isFree
                ? __('Free delivery')
                : number_format($fee, 0, '.', ',').' XAF';
        }

        return [
            'fee' => $fee,
            'quarter_name' => $quarterName,
            'is_free' => $isFree,
            'display_text' => $displayText,
        ];
    }

    /**
     * F-141: Get cook's delivery towns.
     *
     * BR-274: Town dropdown shows only towns where the cook has delivery areas configured.
     * BR-283: Town names displayed in the user's current language.
     */
    public function getDeliveryTowns(int $tenantId): Collection
    {
        $locale = app()->getLocale();
        $nameColumn = 'name_'.$locale;

        return Town::query()
            ->whereHas('deliveryAreas', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->orderBy($nameColumn)
            ->get();
    }

    /**
     * F-141: Get quarters for a specific town that the cook delivers to.
     *
     * BR-275: Quarter dropdown is filtered by the selected town.
     * BR-283: Quarter names displayed in the user's current language.
     * BR-280: The selected quarter determines the delivery fee.
     *
     * F-147: Now includes ALL quarters for the town, with an 'available' flag
     * indicating whether the cook delivers to that quarter. Non-available quarters
     * are shown so clients can select them and see the "not available" message.
     *
     * @return Collection<int, array{id: int, name: string, delivery_fee: int, available: bool}>
     */
    public function getDeliveryQuarters(int $tenantId, int $townId): Collection
    {
        $locale = app()->getLocale();
        $nameColumn = 'name_'.$locale;

        $deliveryArea = DeliveryArea::query()
            ->where('tenant_id', $tenantId)
            ->where('town_id', $townId)
            ->first();

        // Build a map of available quarter IDs with their delivery fees
        $availableQuarters = [];
        if ($deliveryArea) {
            $deliveryAreaQuarters = DeliveryAreaQuarter::query()
                ->where('delivery_area_id', $deliveryArea->id)
                ->with('quarter')
                ->get();

            foreach ($deliveryAreaQuarters as $daq) {
                $groupFee = $this->getGroupFeeForQuarter($tenantId, $daq->quarter_id);
                $availableQuarters[$daq->quarter_id] = $groupFee !== null ? $groupFee : $daq->delivery_fee;
            }
        }

        // F-147: Get ALL active quarters for this town
        return Quarter::query()
            ->where('town_id', $townId)
            ->active()
            ->get()
            ->map(function (Quarter $quarter) use ($nameColumn, $availableQuarters) {
                $isAvailable = isset($availableQuarters[$quarter->id]);

                return [
                    'id' => $quarter->id,
                    'name' => $quarter->{$nameColumn} ?? $quarter->name_en,
                    'delivery_fee' => $isAvailable ? $availableQuarters[$quarter->id] : 0,
                    'available' => $isAvailable,
                ];
            })
            ->sortBy('name')
            ->values();
    }

    /**
     * Get the group delivery fee for a quarter if it belongs to a group.
     *
     * F-090: Group fee overrides individual quarter fee.
     */
    private function getGroupFeeForQuarter(int $tenantId, int $quarterId): ?int
    {
        $group = QuarterGroup::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('quarters', function ($query) use ($quarterId) {
                $query->where('quarters.id', $quarterId);
            })
            ->first();

        return $group?->delivery_fee;
    }

    /**
     * F-141: Get saved addresses that match the cook's delivery areas.
     *
     * BR-278: Saved addresses matching the cook's delivery areas are offered as quick-select options.
     * BR-279: If the client has a saved address in an available area, it is pre-selected by default.
     *
     * @return Collection<int, Address>
     */
    public function getMatchingSavedAddresses(int $tenantId, int $userId): Collection
    {
        // Get all quarter IDs the cook delivers to
        $deliverableQuarterIds = DeliveryAreaQuarter::query()
            ->whereHas('deliveryArea', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->pluck('quarter_id')
            ->toArray();

        if (empty($deliverableQuarterIds)) {
            return collect();
        }

        return Address::query()
            ->where('user_id', $userId)
            ->whereIn('quarter_id', $deliverableQuarterIds)
            ->with(['town', 'quarter'])
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();
    }

    /**
     * F-141: Validate that a quarter belongs to the cook's delivery areas.
     *
     * @return array{valid: bool, error: string|null, delivery_fee: int|null}
     */
    public function validateDeliveryQuarter(int $tenantId, int $quarterId): array
    {
        $deliveryAreaQuarter = DeliveryAreaQuarter::query()
            ->whereHas('deliveryArea', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->where('quarter_id', $quarterId)
            ->first();

        if (! $deliveryAreaQuarter) {
            return [
                'valid' => false,
                'error' => __('The selected quarter is not in the delivery area.'),
                'delivery_fee' => null,
            ];
        }

        // Check group fee override
        $groupFee = $this->getGroupFeeForQuarter($tenantId, $quarterId);
        $effectiveFee = $groupFee !== null ? $groupFee : $deliveryAreaQuarter->delivery_fee;

        return [
            'valid' => true,
            'error' => null,
            'delivery_fee' => $effectiveFee,
        ];
    }

    /**
     * Determine available delivery options for a tenant.
     *
     * BR-267: No pickup locations = pickup hidden.
     * BR-268: No delivery areas = delivery hidden.
     *
     * @return array{has_delivery: bool, has_pickup: bool, delivery_area_count: int, pickup_location_count: int}
     */
    public function getAvailableOptions(int $tenantId): array
    {
        $deliveryAreaCount = DeliveryArea::query()
            ->where('tenant_id', $tenantId)
            ->count();

        $pickupLocationCount = PickupLocation::query()
            ->where('tenant_id', $tenantId)
            ->count();

        return [
            'has_delivery' => $deliveryAreaCount > 0,
            'has_pickup' => $pickupLocationCount > 0,
            'delivery_area_count' => $deliveryAreaCount,
            'pickup_location_count' => $pickupLocationCount,
        ];
    }

    /**
     * Validate a delivery method selection against available options.
     *
     * @return array{valid: bool, error: string|null}
     */
    public function validateMethodSelection(int $tenantId, string $method): array
    {
        if (! in_array($method, [self::METHOD_DELIVERY, self::METHOD_PICKUP])) {
            return [
                'valid' => false,
                'error' => __('Invalid delivery method selected.'),
            ];
        }

        $options = $this->getAvailableOptions($tenantId);

        if ($method === self::METHOD_DELIVERY && ! $options['has_delivery']) {
            return [
                'valid' => false,
                'error' => __('Delivery is not available for this cook.'),
            ];
        }

        if ($method === self::METHOD_PICKUP && ! $options['has_pickup']) {
            return [
                'valid' => false,
                'error' => __('Pickup is not available for this cook.'),
            ];
        }

        return [
            'valid' => true,
            'error' => null,
        ];
    }

    /**
     * F-142: Get cook's pickup locations.
     *
     * BR-284: All configured pickup locations for the cook are displayed.
     * BR-285: Each pickup location shows name, full address (quarter, town), special instructions.
     * BR-291: Location names and addresses displayed in the user's current language.
     *
     * @return Collection<int, PickupLocation>
     */
    public function getPickupLocations(int $tenantId): Collection
    {
        $locale = app()->getLocale();
        $nameColumn = 'name_'.$locale;

        return PickupLocation::query()
            ->where('tenant_id', $tenantId)
            ->with(['town', 'quarter'])
            ->get()
            ->sortBy(fn (PickupLocation $p) => $p->{$nameColumn} ?? $p->name_en)
            ->values();
    }

    /**
     * F-142: Save selected pickup location to checkout session.
     *
     * BR-289: The selected pickup location is stored in the checkout session/order draft.
     */
    public function setPickupLocation(int $tenantId, int $pickupLocationId): void
    {
        $data = $this->getCheckoutData($tenantId);
        $data['pickup_location_id'] = $pickupLocationId;

        session([$this->getSessionKey($tenantId) => $data]);
    }

    /**
     * F-142: Get the saved pickup location ID from checkout session.
     */
    public function getPickupLocationId(int $tenantId): ?int
    {
        $data = $this->getCheckoutData($tenantId);

        return $data['pickup_location_id'] ?? null;
    }

    /**
     * F-142: Validate that a pickup location exists and belongs to the cook.
     *
     * BR-286: Client must select exactly one pickup location.
     *
     * @return array{valid: bool, error: string|null, pickup_location: PickupLocation|null}
     */
    public function validatePickupLocation(int $tenantId, int $pickupLocationId): array
    {
        $pickupLocation = PickupLocation::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $pickupLocationId)
            ->first();

        if (! $pickupLocation) {
            return [
                'valid' => false,
                'error' => __('The selected pickup location is no longer available.'),
                'pickup_location' => null,
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'pickup_location' => $pickupLocation,
        ];
    }

    /**
     * F-143: Save phone number to checkout session.
     *
     * BR-292: Pre-filled from user profile.
     * BR-293: Client can override per order.
     * BR-298: Phone stored with the order for delivery/communication.
     */
    public function setPhone(int $tenantId, string $phone): void
    {
        $data = $this->getCheckoutData($tenantId);
        $data['phone'] = $phone;

        session([$this->getSessionKey($tenantId) => $data]);
    }

    /**
     * F-143: Get the saved phone number from checkout session.
     */
    public function getPhone(int $tenantId): ?string
    {
        $data = $this->getCheckoutData($tenantId);

        return $data['phone'] ?? null;
    }

    /**
     * F-143: Get the phone number to pre-fill the form.
     *
     * BR-292: Pre-filled from the authenticated user's profile phone number.
     * Returns the session-stored phone if already set, otherwise the user's profile phone.
     */
    public function getPrefilledPhone(int $tenantId, ?string $userPhone): string
    {
        $sessionPhone = $this->getPhone($tenantId);

        if ($sessionPhone) {
            return $sessionPhone;
        }

        return $userPhone ?? '';
    }

    /**
     * F-143: Get the appropriate back URL based on the selected delivery method.
     *
     * Phone step comes after delivery location (F-141) or pickup location (F-142).
     */
    public function getPhoneStepBackUrl(int $tenantId): string
    {
        $method = $this->getDeliveryMethod($tenantId);

        if ($method === self::METHOD_PICKUP) {
            return url('/checkout/pickup-location');
        }

        return url('/checkout/delivery-location');
    }

    /**
     * F-146: Build the complete order summary for the review step.
     *
     * BR-316: Itemized list shows meal name, component name, quantity, unit price, line subtotal.
     * BR-317: Items are grouped by meal.
     * BR-318: Subtotal is the sum of all food item line subtotals.
     * BR-319: Delivery fee is shown as a separate line item; pickup shows "Pickup - Free".
     * BR-320: Promo discount (if applicable) is shown as a negative line item with code name.
     * BR-321: Grand total = subtotal + delivery fee - promo discount.
     * BR-322: All amounts displayed in XAF (integer, formatted with thousand separators).
     *
     * @return array{meals: array, subtotal: int, delivery_fee: int, delivery_display: array, promo_discount: int, promo_code: string|null, grand_total: int, item_count: int, price_changes: array}
     */
    public function getOrderSummary(int $tenantId, array $cartData): array
    {
        $meals = $cartData['meals'] ?? [];
        $items = $cartData['items'] ?? [];

        // BR-318: Calculate food subtotal from current cart data
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['unit_price'] * $item['quantity'];
        }

        // BR-319: Get delivery fee based on method
        $deliveryMethod = $this->getDeliveryMethod($tenantId);
        $deliveryFee = $this->getStoredDeliveryFee($tenantId);
        $deliveryDisplay = $this->getDeliveryFeeDisplayData($tenantId);

        // BR-320: Promo discount (F-215 - forward-compatible stub)
        $promoDiscount = 0;
        $promoCode = null;

        // BR-321: Grand total = subtotal + delivery fee - promo discount
        // Edge case: promo discount exceeding subtotal means food portion is free
        $effectiveDiscount = min($promoDiscount, $subtotal);
        $grandTotal = max(0, $subtotal + $deliveryFee - $effectiveDiscount);

        // Edge case: Check for price changes since items were added to cart
        $priceChanges = $this->detectPriceChanges($items);

        // Count total items (sum of quantities)
        $itemCount = 0;
        foreach ($items as $item) {
            $itemCount += $item['quantity'];
        }

        return [
            'meals' => $meals,
            'subtotal' => $subtotal,
            'delivery_method' => $deliveryMethod,
            'delivery_fee' => $deliveryFee,
            'delivery_display' => $deliveryDisplay,
            'promo_discount' => $effectiveDiscount,
            'promo_code' => $promoCode,
            'grand_total' => $grandTotal,
            'item_count' => $itemCount,
            'price_changes' => $priceChanges,
        ];
    }

    /**
     * F-146: Detect price changes between cart stored prices and current DB prices.
     *
     * Edge case: Component price changed since cart add.
     *
     * @return array<int, array{component_id: int, name: string, old_price: int, new_price: int}>
     */
    private function detectPriceChanges(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $componentIds = array_column($items, 'component_id');
        $components = MealComponent::query()
            ->whereIn('id', $componentIds)
            ->get()
            ->keyBy('id');

        $changes = [];
        $locale = app()->getLocale();

        foreach ($items as $item) {
            $component = $components->get($item['component_id']);

            if (! $component) {
                continue;
            }

            if ($component->price !== $item['unit_price']) {
                $changes[] = [
                    'component_id' => $item['component_id'],
                    'name' => $component->{'name_'.$locale} ?? $component->name_en,
                    'old_price' => $item['unit_price'],
                    'new_price' => $component->price,
                ];
            }
        }

        return $changes;
    }

    /**
     * F-146: Get the back URL for the summary step.
     *
     * Summary step comes after phone number (F-143).
     */
    public function getSummaryBackUrl(): string
    {
        return url('/checkout/phone');
    }

    /**
     * F-147: Get cook contact info for the "location not available" flow.
     *
     * BR-329: Cook's WhatsApp number and phone number are displayed with action buttons.
     * BR-330: WhatsApp message is pre-filled with the client's location and inquiry text.
     *
     * @return array{whatsapp: string|null, phone: string|null, brand_name: string, has_contact: bool}
     */
    public function getCookContactInfo(Tenant $tenant): array
    {
        $locale = app()->getLocale();
        $nameColumn = 'name_'.$locale;
        $brandName = $tenant->{$nameColumn} ?? $tenant->name_en ?? $tenant->slug;

        return [
            'whatsapp' => $tenant->whatsapp ?: null,
            'phone' => $tenant->phone ?: null,
            'brand_name' => $brandName,
            'has_contact' => ! empty($tenant->whatsapp) || ! empty($tenant->phone),
        ];
    }

    /**
     * F-147: Build the pre-filled WhatsApp message for the "location not available" flow.
     *
     * BR-330: WhatsApp message is pre-filled with the client's location and inquiry text (localized).
     */
    public function buildWhatsAppMessage(string $brandName, string $quarterName, string $townName): string
    {
        return __("Hi :brand, I'd like to order from DancyMeals but I'm in :quarter, :town. Is delivery to my area possible?", [
            'brand' => $brandName,
            'quarter' => $quarterName,
            'town' => $townName,
        ]);
    }

    /**
     * F-147: Check if a tenant has pickup locations available.
     *
     * BR-331: A "Switch to Pickup" option is provided if the cook has pickup locations.
     */
    public function hasPickupLocations(int $tenantId): bool
    {
        return PickupLocation::query()
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    /**
     * F-149: Save the selected payment method to checkout session.
     *
     * BR-345: Available payment methods: MTN Mobile Money, Orange Money, Wallet Balance.
     * BR-352: Pay Now triggers F-150 (Flutterwave) for mobile money or F-153 for wallet.
     */
    public function setPaymentMethod(int $tenantId, string $provider, ?string $phone = null): void
    {
        $data = $this->getCheckoutData($tenantId);
        $data['payment_provider'] = $provider;
        $data['payment_phone'] = $phone;

        session([$this->getSessionKey($tenantId) => $data]);
    }

    /**
     * F-149: Get the saved payment provider from checkout session.
     */
    public function getPaymentProvider(int $tenantId): ?string
    {
        $data = $this->getCheckoutData($tenantId);

        return $data['payment_provider'] ?? null;
    }

    /**
     * F-149: Get the saved payment phone from checkout session.
     */
    public function getPaymentPhone(int $tenantId): ?string
    {
        $data = $this->getCheckoutData($tenantId);

        return $data['payment_phone'] ?? null;
    }

    /**
     * F-149: Get available payment options for the payment step.
     *
     * BR-345: MTN Mobile Money and Orange Money are always available.
     * BR-346: Wallet Balance is available if admin has enabled it AND balance >= order total.
     * BR-347: If wallet balance < order total, wallet is visible but disabled.
     * BR-349: Previously used payment methods are offered as saved options.
     *
     * @return array{providers: array, wallet: array, saved_methods: Collection}
     */
    public function getPaymentOptions(int $tenantId, int $userId, int $orderTotal): array
    {
        // BR-345: Mobile money providers are always available
        $providers = [
            [
                'id' => PaymentMethod::PROVIDER_MTN_MOMO,
                'label' => 'MTN Mobile Money',
                'short_label' => 'MTN MoMo',
                'color' => '#ffcc00',
                'text_color' => '#000000',
            ],
            [
                'id' => PaymentMethod::PROVIDER_ORANGE_MONEY,
                'label' => 'Orange Money',
                'short_label' => 'Orange Money',
                'color' => '#ff6600',
                'text_color' => '#ffffff',
            ],
        ];

        // BR-346/BR-347: Wallet Balance (forward-compatible with F-166)
        $wallet = $this->getWalletOption($userId, $orderTotal);

        // BR-349: Get saved payment methods for this user
        $savedMethods = PaymentMethod::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();

        return [
            'providers' => $providers,
            'wallet' => $wallet,
            'saved_methods' => $savedMethods,
        ];
    }

    /**
     * F-149/F-153: Build wallet payment option data.
     *
     * BR-346: Wallet Balance only available if admin enabled AND balance >= total.
     * BR-347: If balance < total, visible but disabled with balance shown.
     * BR-387: Wallet payment is available only when admin has enabled it globally.
     * BR-388: Wallet balance must be >= order total for the option to be selectable.
     *
     * @return array{enabled: bool, visible: bool, balance: int, sufficient: bool}
     */
    private function getWalletOption(int $userId, int $orderTotal): array
    {
        $platformSettingService = app(PlatformSettingService::class);
        $walletEnabled = $platformSettingService->isWalletEnabled();

        // BR-387: If admin has disabled wallet payments, hide the option entirely
        if (! $walletEnabled) {
            return [
                'enabled' => false,
                'visible' => false,
                'balance' => 0,
                'sufficient' => false,
            ];
        }

        // F-153: Use ClientWallet model for balance lookup
        $balance = 0;
        $wallet = \App\Models\ClientWallet::query()
            ->where('user_id', $userId)
            ->first();

        if ($wallet) {
            $balance = (int) $wallet->balance;
        }

        // BR-388: Wallet balance must be >= order total for the option to be selectable
        $sufficient = $balance >= $orderTotal;

        return [
            'enabled' => $sufficient,
            'visible' => true,
            'balance' => $balance,
            'sufficient' => $sufficient,
        ];
    }

    /**
     * F-149: Get the pre-filled phone for the payment step.
     *
     * BR-348: Mobile money options require a phone number, pre-filled from profile or saved methods.
     * Priority: checkout session payment phone > user's profile phone.
     */
    public function getPaymentPrefillPhone(int $tenantId, ?string $userPhone, ?string $provider = null, ?Collection $savedMethods = null): string
    {
        // First priority: already saved payment phone in session
        $sessionPhone = $this->getPaymentPhone($tenantId);
        if ($sessionPhone) {
            return $sessionPhone;
        }

        // Second priority: default saved method for the selected provider
        if ($provider && $savedMethods && $savedMethods->isNotEmpty()) {
            $defaultMethod = $savedMethods
                ->where('provider', $provider)
                ->sortByDesc('is_default')
                ->first();

            if ($defaultMethod) {
                return $defaultMethod->phone;
            }
        }

        // Third priority: user's profile phone
        return $userPhone ?? '';
    }

    /**
     * F-149: Get the back URL for the payment step.
     *
     * Payment step comes after order summary (F-146).
     */
    public function getPaymentBackUrl(): string
    {
        return url('/checkout/summary');
    }

    /**
     * F-149: Validate the payment method selection.
     *
     * BR-345: Provider must be mtn_momo, orange_money, or wallet.
     * BR-346: Wallet requires admin enablement and sufficient balance.
     * BR-351: Phone must match Cameroon format for mobile money.
     *
     * @return array{valid: bool, error: string|null}
     */
    public function validatePaymentSelection(string $provider, ?string $phone, int $userId, int $orderTotal): array
    {
        $validProviders = [PaymentMethod::PROVIDER_MTN_MOMO, PaymentMethod::PROVIDER_ORANGE_MONEY, 'wallet'];

        if (! in_array($provider, $validProviders)) {
            return [
                'valid' => false,
                'error' => __('Invalid payment method selected.'),
            ];
        }

        // Wallet validation
        if ($provider === 'wallet') {
            $walletOption = $this->getWalletOption($userId, $orderTotal);

            if (! $walletOption['visible']) {
                return [
                    'valid' => false,
                    'error' => __('Wallet payments are not available.'),
                ];
            }

            if (! $walletOption['sufficient']) {
                return [
                    'valid' => false,
                    'error' => __('Insufficient wallet balance.'),
                ];
            }

            return ['valid' => true, 'error' => null];
        }

        // Mobile money: phone is required
        if (! $phone || trim($phone) === '') {
            return [
                'valid' => false,
                'error' => __('Please enter your mobile money phone number.'),
            ];
        }

        // BR-351: Validate Cameroon phone format
        if (! PaymentMethod::isValidCameroonPhone($phone)) {
            return [
                'valid' => false,
                'error' => __('Please enter a valid Cameroon phone number (+237 followed by 9 digits).'),
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Clear checkout session data for a tenant.
     */
    public function clearCheckoutData(int $tenantId): void
    {
        session()->forget($this->getSessionKey($tenantId));
    }
}
