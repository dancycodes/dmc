<?php

namespace App\Services;

use App\Models\Address;
use App\Models\DeliveryArea;
use App\Models\DeliveryAreaQuarter;
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
 *
 * Manages checkout session state including delivery method, location, and pickup selection.
 * BR-264: Client must choose delivery or pickup to proceed.
 * BR-271: Choice persists across navigation via session.
 * BR-274-BR-283: Delivery location selection rules.
 * BR-284-BR-291: Pickup location selection rules.
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
     * @return array{delivery_method: string|null, delivery_location: array|null, pickup_location_id: int|null, phone: string|null}
     */
    public function getCheckoutData(int $tenantId): array
    {
        return session($this->getSessionKey($tenantId), [
            'delivery_method' => null,
            'delivery_location' => null,
            'pickup_location_id' => null,
            'phone' => null,
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
     *
     * BR-281: All fields (town, quarter, neighbourhood) required.
     *
     * @param  array{town_id: int, quarter_id: int, neighbourhood: string}  $location
     */
    public function setDeliveryLocation(int $tenantId, array $location): void
    {
        $data = $this->getCheckoutData($tenantId);
        $data['delivery_location'] = [
            'town_id' => (int) $location['town_id'],
            'quarter_id' => (int) $location['quarter_id'],
            'neighbourhood' => trim($location['neighbourhood']),
        ];

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
     * @return Collection<int, array{id: int, name: string, delivery_fee: int}>
     */
    public function getDeliveryQuarters(int $tenantId, int $townId): Collection
    {
        $locale = app()->getLocale();
        $nameColumn = 'name_'.$locale;

        $deliveryArea = DeliveryArea::query()
            ->where('tenant_id', $tenantId)
            ->where('town_id', $townId)
            ->first();

        if (! $deliveryArea) {
            return collect();
        }

        return DeliveryAreaQuarter::query()
            ->where('delivery_area_id', $deliveryArea->id)
            ->with('quarter')
            ->get()
            ->map(function (DeliveryAreaQuarter $daq) use ($nameColumn, $tenantId) {
                $quarter = $daq->quarter;

                // Check if quarter belongs to a group — group fee overrides individual fee (F-090)
                $groupFee = $this->getGroupFeeForQuarter($tenantId, $quarter->id);
                $effectiveFee = $groupFee !== null ? $groupFee : $daq->delivery_fee;

                return [
                    'id' => $quarter->id,
                    'name' => $quarter->{$nameColumn} ?? $quarter->name_en,
                    'delivery_fee' => $effectiveFee,
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
     * Clear checkout session data for a tenant.
     */
    public function clearCheckoutData(int $tenantId): void
    {
        session()->forget($this->getSessionKey($tenantId));
    }
}
