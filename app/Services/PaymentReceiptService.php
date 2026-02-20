<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\User;

/**
 * F-154: Payment Receipt & Confirmation
 *
 * Aggregates receipt data, generates PDF receipts, and sends notifications
 * after successful payment.
 *
 * BR-398: Confirmation page displays order number, item summary, total amount,
 *         payment method, transaction reference, order status.
 * BR-399: Order number format: DM-{YEAR}-{SEQUENTIAL} (displayed as DMC-YYMMDD-NNNN).
 * BR-403: Receipt can be downloaded as PDF.
 * BR-406: Confirmation page is accessible only to the order's owner.
 */
class PaymentReceiptService
{
    /**
     * Get the confirmation page data for a paid order.
     *
     * BR-398: Displays order number, item summary, total, payment method,
     *         transaction reference, order status.
     * BR-405: Order status is "Paid" at this point.
     * BR-406: Only accessible to the order's owner.
     *
     * @return array{order: Order, transaction: PaymentTransaction|null, cook: User|null, tenant: Tenant, items: array, payment_label: string, transaction_reference: string}
     */
    public function getReceiptData(Order $order): array
    {
        $order->load(['tenant', 'cook', 'town', 'quarter', 'pickupLocation']);

        // Get the successful payment transaction
        $transaction = PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->where('status', 'successful')
            ->orderByDesc('created_at')
            ->first();

        // Fallback: for wallet payments, get the latest transaction
        if (! $transaction) {
            $transaction = PaymentTransaction::query()
                ->where('order_id', $order->id)
                ->orderByDesc('created_at')
                ->first();
        }

        // Parse items from snapshot
        $items = $this->parseItemsSnapshot($order->items_snapshot ?? []);

        // Get human-readable payment label
        $paymentLabel = $this->getPaymentMethodLabel($order->payment_provider);

        // Get transaction reference (Flutterwave or internal)
        $transactionReference = $this->getTransactionReference($transaction, $order);

        return [
            'order' => $order,
            'transaction' => $transaction,
            'cook' => $order->cook,
            'tenant' => $order->tenant,
            'items' => $items,
            'payment_label' => $paymentLabel,
            'transaction_reference' => $transactionReference,
        ];
    }

    /**
     * Parse the items snapshot into grouped display data.
     *
     * BR-398: Item summary shows meals with their components.
     *
     * @param  array<int, array{meal_id: int, meal_name: string, component_id: int, component_name: string, quantity: int, unit_price: int, subtotal: int}>  $snapshot
     * @return array<int, array{meal_name: string, components: array}>
     */
    public function parseItemsSnapshot(array $snapshot): array
    {
        $grouped = [];

        foreach ($snapshot as $item) {
            $mealId = $item['meal_id'] ?? 0;
            $mealName = $item['meal_name'] ?? __('Unknown Meal');

            if (! isset($grouped[$mealId])) {
                $grouped[$mealId] = [
                    'meal_name' => $mealName,
                    'components' => [],
                ];
            }

            $grouped[$mealId]['components'][] = [
                'name' => $item['component_name'] ?? __('Unknown Item'),
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['unit_price'] ?? 0,
                'subtotal' => $item['subtotal'] ?? 0,
            ];
        }

        return array_values($grouped);
    }

    /**
     * Get a human-readable payment method label.
     */
    public function getPaymentMethodLabel(?string $provider): string
    {
        return match ($provider) {
            'mtn_momo' => __('MTN Mobile Money'),
            'orange_money' => __('Orange Money'),
            'wallet' => __('Wallet Balance'),
            default => __('Mobile Money'),
        };
    }

    /**
     * Get the transaction reference for display.
     *
     * Edge case: Wallet payments have no Flutterwave reference;
     * show internal reference instead.
     */
    public function getTransactionReference(?PaymentTransaction $transaction, Order $order): string
    {
        if ($transaction) {
            // Prefer Flutterwave reference
            if (! empty($transaction->flutterwave_reference)) {
                return $transaction->flutterwave_reference;
            }

            // Fallback to tx_ref
            if (! empty($transaction->flutterwave_tx_ref)) {
                return $transaction->flutterwave_tx_ref;
            }
        }

        // Internal reference for wallet or missing transactions
        return 'INT-'.$order->order_number;
    }

    /**
     * Generate the PDF receipt content as HTML.
     *
     * BR-403: Receipt can be downloaded as PDF.
     * The HTML is rendered by the browser's print-to-PDF functionality.
     *
     * @return array{html: string, filename: string}
     */
    public function generateReceiptHtml(Order $order): array
    {
        $receiptData = $this->getReceiptData($order);
        $filename = 'receipt-'.$order->order_number.'.pdf';

        return [
            'data' => $receiptData,
            'filename' => $filename,
        ];
    }

    /**
     * Format an amount with XAF currency.
     */
    public function formatAmount(int|float $amount): string
    {
        return number_format((float) $amount, 0, '.', ',').' XAF';
    }

    /**
     * Check if the authenticated user is the order owner.
     *
     * BR-406: Confirmation page is accessible only to the order's owner.
     */
    public function isOrderOwner(Order $order, int $userId): bool
    {
        return $order->client_id === $userId;
    }

    /**
     * Get the delivery method display text.
     */
    public function getDeliveryMethodLabel(Order $order): string
    {
        if ($order->delivery_method === Order::METHOD_DELIVERY) {
            $parts = [];
            if ($order->quarter) {
                $locale = app()->getLocale();
                $quarterName = $order->quarter->{'name_'.$locale} ?? $order->quarter->name_en;
                $parts[] = $quarterName;
            }
            if ($order->town) {
                $locale = app()->getLocale();
                $townName = $order->town->{'name_'.$locale} ?? $order->town->name_en;
                $parts[] = $townName;
            }

            if (! empty($parts)) {
                return __('Delivery to :location', ['location' => implode(', ', $parts)]);
            }

            return __('Delivery');
        }

        if ($order->delivery_method === Order::METHOD_PICKUP) {
            if ($order->pickupLocation) {
                $locale = app()->getLocale();
                $locationName = $order->pickupLocation->{'name_'.$locale} ?? $order->pickupLocation->name_en;

                return __('Pickup at :location', ['location' => $locationName]);
            }

            return __('Pickup');
        }

        return __('N/A');
    }

    /**
     * Get the share text for the order confirmation.
     *
     * Scenario 5: Formatted message for sharing via WhatsApp, SMS, etc.
     */
    public function getShareText(Order $order, Tenant $tenant): string
    {
        $cookName = $tenant->name ?? 'DancyMeals';

        return __('I just ordered from :cook on DancyMeals! Order :number - :total', [
            'cook' => $cookName,
            'number' => $order->order_number,
            'total' => $order->formattedGrandTotal(),
        ]);
    }
}
