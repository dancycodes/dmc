<?php

namespace Database\Factories;

use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    /**
     * Cameroonian names for realistic test data.
     */
    private const CAMEROON_NAMES = [
        'Ngono Marie', 'Nkwenti Paul', 'Fotso Jean', 'Mbarga Pierre',
        'Tchinda Rose', 'Kamga Fabrice', 'Njoya Amina', 'Etundi Samuel',
        'Biya Grace', 'Mendo Alain', 'Owona Celine', 'Talla Emmanuel',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(self::CAMEROON_NAMES);
        $status = fake()->randomElement(PaymentTransaction::STATUSES);
        $method = fake()->randomElement(PaymentTransaction::PAYMENT_METHODS);
        $amount = fake()->randomElement([500, 1000, 1500, 2000, 2500, 3000, 5000, 7500, 10000, 15000, 20000, 25000]);

        return [
            'order_id' => fake()->numberBetween(1000, 9999),
            'client_id' => User::factory(),
            'cook_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'amount' => $amount,
            'currency' => 'XAF',
            'payment_method' => $method,
            'status' => $status,
            'flutterwave_reference' => 'FLW-'.fake()->unique()->numerify('########'),
            'flutterwave_tx_ref' => 'DMC-TX-'.fake()->unique()->numerify('######'),
            'flutterwave_fee' => round($amount * 0.014, 2),
            'settlement_amount' => round($amount * 0.986, 2),
            'payment_channel' => $method === 'mtn_mobile_money' ? 'mobilemoneymtn' : 'mobilemoneyorange',
            'webhook_payload' => [
                'id' => fake()->randomNumber(8),
                'tx_ref' => 'DMC-TX-'.fake()->numerify('######'),
                'flw_ref' => 'FLW-'.fake()->numerify('########'),
                'amount' => $amount,
                'currency' => 'XAF',
                'status' => $status,
                'payment_type' => 'mobilemoneycm',
            ],
            'status_history' => [
                ['status' => 'pending', 'timestamp' => now()->subMinutes(30)->toIso8601String()],
                ['status' => $status, 'timestamp' => now()->toIso8601String()],
            ],
            'response_code' => $status === 'successful' ? '00' : ($status === 'failed' ? '99' : null),
            'response_message' => $status === 'successful' ? 'Transaction successful' : ($status === 'failed' ? 'Transaction failed' : null),
            'customer_name' => $name,
            'customer_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'customer_phone' => '+237'.fake()->randomElement(['6', '6', '6', '2'])
                .fake()->numerify('########'),
        ];
    }

    /**
     * State: successful payment.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'successful',
            'response_code' => '00',
            'response_message' => 'Transaction successful',
        ]);
    }

    /**
     * State: failed payment.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'response_code' => '99',
            'response_message' => 'Insufficient funds',
        ]);
    }

    /**
     * State: pending payment.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'flutterwave_reference' => null,
            'response_code' => null,
            'response_message' => null,
        ]);
    }

    /**
     * State: pending too long (>15 minutes).
     */
    public function pendingTooLong(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'flutterwave_reference' => null,
            'response_code' => null,
            'response_message' => null,
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);
    }

    /**
     * State: refunded payment.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'refund_reason' => fake()->randomElement([
                'Customer requested cancellation',
                'Order could not be fulfilled',
                'Complaint resolution refund',
                'Duplicate payment',
            ]),
            'refund_amount' => $attributes['amount'] ?? 5000,
            'response_code' => '00',
            'response_message' => 'Refund processed',
        ]);
    }

    /**
     * State: MTN Mobile Money payment.
     */
    public function mtn(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'mtn_mobile_money',
            'payment_channel' => 'mobilemoneymtn',
        ]);
    }

    /**
     * State: Orange Money payment.
     */
    public function orange(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'orange_money',
            'payment_channel' => 'mobilemoneyorange',
        ]);
    }

    /**
     * State: zero amount (free order/promo).
     */
    public function freeOrder(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => 0,
            'flutterwave_fee' => 0,
            'settlement_amount' => 0,
        ]);
    }

    /**
     * State: missing webhook data.
     */
    public function missingWebhook(): static
    {
        return $this->state(fn (array $attributes) => [
            'webhook_payload' => null,
            'status_history' => null,
            'flutterwave_reference' => null,
            'flutterwave_fee' => null,
            'settlement_amount' => null,
            'payment_channel' => null,
            'response_code' => null,
            'response_message' => null,
        ]);
    }
}
