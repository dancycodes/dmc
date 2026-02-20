<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * F-150: Flutterwave Payment Initiation
 *
 * Handles all communication with the Flutterwave v3 API.
 * BR-354: Payment initiated via Flutterwave v3 mobile money charge API.
 * BR-355: Charge payload includes amount, currency (XAF), customer data, payment type.
 * BR-356: Split payment configured with cook's Flutterwave subaccount.
 */
class FlutterwaveService
{
    /**
     * Base URL for Flutterwave v3 API.
     */
    private const API_BASE_URL = 'https://api.flutterwave.com/v3';

    /**
     * Payment type for Cameroon mobile money.
     */
    public const PAYMENT_TYPE = 'mobilemoneycameroon';

    /**
     * Initiate a mobile money charge via Flutterwave.
     *
     * BR-354: Payment is initiated via Flutterwave v3 mobile money charge API.
     * BR-355: Charge payload includes amount, currency, customer info, payment type, callback URL.
     * BR-356: Split payment configured for cook's subaccount.
     *
     * @param  array{amount: int, currency: string, phone: string, email: string, name: string, tx_ref: string, callback_url: string, subaccount_id: string|null, commission_rate: float}  $params
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function initiateCharge(array $params): array
    {
        $payload = [
            'tx_ref' => $params['tx_ref'],
            'amount' => $params['amount'],
            'currency' => $params['currency'] ?? config('flutterwave.currency', 'XAF'),
            'payment_type' => self::PAYMENT_TYPE,
            'email' => $params['email'],
            'phone_number' => $params['phone'],
            'fullname' => $params['name'],
            'meta' => [
                'source' => 'DancyMeals',
                'platform' => 'web',
            ],
            'redirect_url' => $params['callback_url'] ?? null,
        ];

        // BR-356: Split payment configuration
        if (! empty($params['subaccount_id'])) {
            $commissionRate = $params['commission_rate'] ?? config('flutterwave.default_commission_percentage', 10);
            $payload['subaccounts'] = [
                [
                    'id' => $params['subaccount_id'],
                    'transaction_split_ratio' => 1,
                    'transaction_charge_type' => 'percentage',
                    'transaction_charge' => $commissionRate,
                ],
            ];
        }

        try {
            $response = Http::withToken($this->getSecretKey())
                ->timeout(30)
                ->retry(1, 2000)
                ->post(self::API_BASE_URL.'/charges?type=mobile_money_franco', $payload);

            if ($response->successful()) {
                $data = $response->json();

                if (($data['status'] ?? '') === 'success') {
                    return [
                        'success' => true,
                        'data' => $data['data'] ?? [],
                        'error' => null,
                    ];
                }

                return [
                    'success' => false,
                    'data' => $data['data'] ?? null,
                    'error' => $data['message'] ?? __('Payment could not be initiated. Please try again.'),
                ];
            }

            $errorData = $response->json();
            $errorMessage = $errorData['message'] ?? __('Payment service error. Please try again later.');

            Log::warning('Flutterwave charge failed', [
                'status' => $response->status(),
                'response' => $errorData,
                'tx_ref' => $params['tx_ref'],
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $this->mapFlutterwaveError($errorMessage),
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Flutterwave connection error', [
                'error' => $e->getMessage(),
                'tx_ref' => $params['tx_ref'],
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => __('Payment service is temporarily unavailable. Please try again later.'),
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave unexpected error', [
                'error' => $e->getMessage(),
                'tx_ref' => $params['tx_ref'],
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => __('An unexpected error occurred. Please try again.'),
            ];
        }
    }

    /**
     * Verify a transaction by its reference.
     *
     * Used to check payment status when webhook is delayed.
     *
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function verifyTransaction(string $transactionId): array
    {
        try {
            $response = Http::withToken($this->getSecretKey())
                ->timeout(15)
                ->get(self::API_BASE_URL.'/transactions/'.$transactionId.'/verify');

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => ($data['status'] ?? '') === 'success',
                    'data' => $data['data'] ?? null,
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'data' => null,
                'error' => __('Could not verify payment status.'),
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave verification error', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => __('Could not verify payment status.'),
            ];
        }
    }

    /**
     * Generate a unique transaction reference.
     *
     * BR-359: A transaction reference is generated and stored with the order.
     */
    public function generateTxRef(int $orderId): string
    {
        return 'DMC-TX-'.$orderId.'-'.time().'-'.strtoupper(substr(md5(uniqid()), 0, 6));
    }

    /**
     * Get the Flutterwave secret key.
     */
    private function getSecretKey(): string
    {
        return config('flutterwave.secret_key', '');
    }

    /**
     * Map Flutterwave error messages to user-friendly localized messages.
     *
     * BR-362: Initiation errors are displayed with actionable messages.
     */
    private function mapFlutterwaveError(string $flutterwaveMessage): string
    {
        $lower = strtolower($flutterwaveMessage);

        if (str_contains($lower, 'invalid phone') || str_contains($lower, 'phone number')) {
            return __('Payment could not be initiated. Please check your phone number and try again.');
        }

        if (str_contains($lower, 'insufficient')) {
            return __('Insufficient funds. Please top up your mobile money account and try again.');
        }

        if (str_contains($lower, 'timeout') || str_contains($lower, 'timed out')) {
            return __('The payment request timed out. Please try again.');
        }

        if (str_contains($lower, 'service') || str_contains($lower, 'unavailable')) {
            return __('Payment service is temporarily unavailable. Please try again later.');
        }

        return __('Payment could not be initiated. Please try again.');
    }
}
