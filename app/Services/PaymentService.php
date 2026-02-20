<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * F-150: Flutterwave Payment Initiation
 *
 * Orchestrates the payment flow: creates orders, initiates Flutterwave charges,
 * and manages payment transaction records.
 *
 * BR-354: Payment initiated via Flutterwave v3 mobile money charge API.
 * BR-358: Order status is set to "Pending Payment" upon initiation.
 * BR-359: A transaction reference is generated and stored with the order.
 */
class PaymentService
{
    public function __construct(
        private FlutterwaveService $flutterwaveService,
        private CheckoutService $checkoutService,
        private CartService $cartService,
    ) {}

    /**
     * Create an order from the checkout session data.
     *
     * BR-358: Order status is set to "Pending Payment" upon initiation.
     * BR-359: A transaction reference is generated and stored with the order.
     *
     * @return array{success: bool, order: Order|null, error: string|null}
     */
    public function createOrder(Tenant $tenant, User $client): array
    {
        $checkoutData = $this->checkoutService->getCheckoutData($tenant->id);

        // Validate all checkout data is present
        $validation = $this->validateCheckoutData($checkoutData, $tenant->id);
        if (! $validation['valid']) {
            return [
                'success' => false,
                'order' => null,
                'error' => $validation['error'],
            ];
        }

        // Get cart with availability check
        $cart = $this->cartService->getCartWithAvailability($tenant->id);
        if (empty($cart['items'])) {
            return [
                'success' => false,
                'order' => null,
                'error' => __('Your cart is empty.'),
            ];
        }

        // Build order summary for totals
        $orderSummary = $this->checkoutService->getOrderSummary($tenant->id, $cart);

        if ($orderSummary['grand_total'] <= 0) {
            return [
                'success' => false,
                'order' => null,
                'error' => __('Order total must be greater than zero.'),
            ];
        }

        try {
            $order = DB::transaction(function () use ($tenant, $client, $checkoutData, $orderSummary, $cart) {
                $order = Order::create([
                    'client_id' => $client->id,
                    'tenant_id' => $tenant->id,
                    'cook_id' => $tenant->cook_id,
                    'order_number' => Order::generateOrderNumber(),
                    'status' => Order::STATUS_PENDING_PAYMENT,
                    'delivery_method' => $checkoutData['delivery_method'],
                    'town_id' => $checkoutData['delivery_location']['town_id'] ?? null,
                    'quarter_id' => $checkoutData['delivery_location']['quarter_id'] ?? null,
                    'neighbourhood' => $checkoutData['delivery_location']['neighbourhood'] ?? null,
                    'pickup_location_id' => $checkoutData['pickup_location_id'] ?? null,
                    'subtotal' => $orderSummary['subtotal'],
                    'delivery_fee' => $orderSummary['delivery_fee'],
                    'promo_discount' => $orderSummary['promo_discount'],
                    'grand_total' => $orderSummary['grand_total'],
                    'phone' => $checkoutData['phone'],
                    'payment_provider' => $checkoutData['payment_provider'],
                    'payment_phone' => $checkoutData['payment_phone'],
                    'items_snapshot' => $this->buildItemsSnapshot($cart),
                ]);

                return $order;
            });

            return [
                'success' => true,
                'order' => $order,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Order creation failed', [
                'tenant_id' => $tenant->id,
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'order' => null,
                'error' => __('Failed to create order. Please try again.'),
            ];
        }
    }

    /**
     * Initiate Flutterwave payment for an order.
     *
     * BR-354: Payment initiated via Flutterwave v3 mobile money charge API.
     * BR-355: Charge payload includes amount, currency (XAF), customer data.
     * BR-356: Split payment configured with cook's Flutterwave subaccount.
     * BR-359: Transaction reference generated and stored.
     *
     * @return array{success: bool, transaction: PaymentTransaction|null, error: string|null}
     */
    public function initiatePayment(Order $order, User $client): array
    {
        $tenant = $order->tenant;

        // Generate transaction reference
        $txRef = $this->flutterwaveService->generateTxRef($order->id);

        // Create payment transaction record
        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'client_id' => $client->id,
            'cook_id' => $order->cook_id,
            'tenant_id' => $order->tenant_id,
            'amount' => $order->grand_total,
            'currency' => config('flutterwave.currency', 'XAF'),
            'payment_method' => $this->mapProviderToPaymentMethod($order->payment_provider),
            'status' => 'pending',
            'flutterwave_tx_ref' => $txRef,
            'customer_name' => $client->name,
            'customer_email' => $client->email,
            'customer_phone' => $order->payment_phone ?? $order->phone,
            'status_history' => [
                ['status' => 'pending', 'timestamp' => now()->toIso8601String()],
            ],
        ]);

        // BR-356: Get cook's Flutterwave subaccount and commission rate
        $subaccountId = $tenant->getSetting('flutterwave_subaccount_id');
        $commissionRate = $tenant->getCommissionRate();

        // Build callback URL (webhook handled by F-151)
        $callbackUrl = url('/checkout/payment/callback');

        // Initiate charge via Flutterwave
        $chargeResult = $this->flutterwaveService->initiateCharge([
            'amount' => $order->grand_total,
            'currency' => config('flutterwave.currency', 'XAF'),
            'phone' => $order->payment_phone ?? $order->phone,
            'email' => $client->email,
            'name' => $client->name,
            'tx_ref' => $txRef,
            'callback_url' => $callbackUrl,
            'subaccount_id' => $subaccountId,
            'commission_rate' => $commissionRate,
        ]);

        if ($chargeResult['success']) {
            // Update transaction with Flutterwave reference
            $flwRef = $chargeResult['data']['flw_ref'] ?? null;
            $transaction->update([
                'flutterwave_reference' => $flwRef,
                'response_message' => $chargeResult['data']['charge_response_message'] ?? 'Charge initiated',
            ]);

            Log::info('Payment initiated successfully', [
                'order_id' => $order->id,
                'tx_ref' => $txRef,
                'flw_ref' => $flwRef,
            ]);

            return [
                'success' => true,
                'transaction' => $transaction->fresh(),
                'error' => null,
            ];
        }

        // BR-362: Initiation failure
        $transaction->update([
            'status' => 'failed',
            'response_message' => $chargeResult['error'],
            'status_history' => array_merge($transaction->status_history ?? [], [
                ['status' => 'failed', 'timestamp' => now()->toIso8601String(), 'reason' => $chargeResult['error']],
            ]),
        ]);

        return [
            'success' => false,
            'transaction' => $transaction,
            'error' => $chargeResult['error'],
        ];
    }

    /**
     * Check the current payment status of an order.
     *
     * Used by the waiting page to poll for payment completion.
     *
     * @return array{status: string, is_complete: bool, is_failed: bool, is_timed_out: bool}
     */
    public function checkPaymentStatus(Order $order): array
    {
        $latestTransaction = PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->orderByDesc('created_at')
            ->first();

        $isTimedOut = $order->isPaymentTimedOut();

        if (! $latestTransaction) {
            return [
                'status' => 'pending',
                'is_complete' => false,
                'is_failed' => false,
                'is_timed_out' => $isTimedOut,
            ];
        }

        $isComplete = $latestTransaction->status === 'successful';
        $isFailed = $latestTransaction->status === 'failed';

        // BR-361: Auto-timeout after 15 minutes
        if (! $isComplete && ! $isFailed && $isTimedOut) {
            $order->update(['status' => Order::STATUS_PAYMENT_FAILED]);
            $latestTransaction->update([
                'status' => 'failed',
                'response_message' => 'Payment timed out',
                'status_history' => array_merge($latestTransaction->status_history ?? [], [
                    ['status' => 'timed_out', 'timestamp' => now()->toIso8601String()],
                ]),
            ]);

            return [
                'status' => 'timed_out',
                'is_complete' => false,
                'is_failed' => true,
                'is_timed_out' => true,
            ];
        }

        return [
            'status' => $latestTransaction->status,
            'is_complete' => $isComplete,
            'is_failed' => $isFailed,
            'is_timed_out' => $isTimedOut,
        ];
    }

    /**
     * Validate checkout data completeness.
     *
     * @return array{valid: bool, error: string|null}
     */
    private function validateCheckoutData(array $data, int $tenantId): array
    {
        if (empty($data['delivery_method'])) {
            return ['valid' => false, 'error' => __('Please select a delivery method first.')];
        }

        if ($data['delivery_method'] === 'delivery' && empty($data['delivery_location'])) {
            return ['valid' => false, 'error' => __('Please select a delivery location first.')];
        }

        if ($data['delivery_method'] === 'pickup' && empty($data['pickup_location_id'])) {
            return ['valid' => false, 'error' => __('Please select a pickup location first.')];
        }

        if (empty($data['phone'])) {
            return ['valid' => false, 'error' => __('Please provide your phone number first.')];
        }

        if (empty($data['payment_provider'])) {
            return ['valid' => false, 'error' => __('Please select a payment method first.')];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Build the items snapshot for the order.
     *
     * @return array<int, array{meal_id: int, meal_name: string, component_id: int, component_name: string, quantity: int, unit_price: int, subtotal: int}>
     */
    private function buildItemsSnapshot(array $cart): array
    {
        $locale = app()->getLocale();
        $snapshot = [];

        foreach ($cart['items'] ?? [] as $item) {
            $snapshot[] = [
                'meal_id' => $item['meal_id'] ?? 0,
                'meal_name' => $item['meal_name'] ?? '',
                'component_id' => $item['component_id'] ?? 0,
                'component_name' => $item['component_name'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['unit_price'] ?? 0,
                'subtotal' => ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1),
            ];
        }

        return $snapshot;
    }

    /**
     * Map checkout provider to payment transaction method.
     */
    private function mapProviderToPaymentMethod(string $provider): string
    {
        return match ($provider) {
            'mtn_momo' => 'mtn_mobile_money',
            'orange_money' => 'orange_money',
            default => $provider,
        };
    }
}
