<?php

namespace App\Services;

use App\Models\DeliveryArea;
use App\Models\PickupLocation;
use App\Models\Tenant;

/**
 * F-140: Delivery/Pickup Choice Selection
 *
 * Manages checkout session state including delivery method selection.
 * BR-264: Client must choose delivery or pickup to proceed.
 * BR-271: Choice persists across navigation via session.
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
     * @return array{delivery_method: string|null}
     */
    public function getCheckoutData(int $tenantId): array
    {
        return session($this->getSessionKey($tenantId), [
            'delivery_method' => null,
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
     * Clear checkout session data for a tenant.
     */
    public function clearCheckoutData(int $tenantId): void
    {
        session()->forget($this->getSessionKey($tenantId));
    }
}
