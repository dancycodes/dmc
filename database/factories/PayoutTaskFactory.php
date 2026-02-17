<?php

namespace Database\Factories;

use App\Models\PayoutTask;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayoutTask>
 */
class PayoutTaskFactory extends Factory
{
    protected $model = PayoutTask::class;

    /**
     * Realistic Cameroonian cook names for test data.
     */
    private const COOK_NAMES = [
        'Chef Amara', 'Chef Nkemdi', 'Chef Fomena', 'Chef Bih',
        'Chef Njoya', 'Chef Kamga', 'Chef Tchoupo', 'Chef Atabong',
    ];

    /**
     * Common Flutterwave failure reasons.
     */
    private const FAILURE_REASONS = [
        'Transfer failed: insufficient balance',
        'Transfer failed: invalid mobile money number',
        'Transfer failed: recipient not found',
        'Transfer failed: network timeout',
        'Transfer failed: service temporarily unavailable',
        'Transfer failed: daily transfer limit exceeded',
        'Transfer failed: duplicate transfer detected',
        'Transfer failed: provider system error',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paymentMethod = fake()->randomElement(PayoutTask::PAYMENT_METHODS);
        $prefix = $paymentMethod === 'mtn_mobile_money' ? '67' : '69';

        return [
            'cook_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'amount' => fake()->randomElement([5000, 10000, 15000, 25000, 45000, 75000, 100000]),
            'currency' => 'XAF',
            'mobile_money_number' => '+237'.$prefix.fake()->numerify('#######'),
            'payment_method' => $paymentMethod,
            'failure_reason' => fake()->randomElement(self::FAILURE_REASONS),
            'flutterwave_reference' => 'FLW-'.fake()->unique()->numerify('########'),
            'flutterwave_transfer_id' => 'TRF-'.fake()->unique()->numerify('######'),
            'flutterwave_response' => [
                'status' => 'error',
                'message' => fake()->randomElement(self::FAILURE_REASONS),
                'data' => [
                    'id' => fake()->randomNumber(6),
                    'status' => 'FAILED',
                ],
            ],
            'status' => PayoutTask::STATUS_PENDING,
            'retry_count' => 0,
            'reference_number' => null,
            'resolution_notes' => null,
            'completed_by' => null,
            'completed_at' => null,
            'requested_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'last_retry_at' => null,
        ];
    }

    /**
     * State: pending task (default).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutTask::STATUS_PENDING,
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }

    /**
     * State: completed via automatic retry.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutTask::STATUS_COMPLETED,
            'completed_at' => now(),
            'retry_count' => fake()->numberBetween(1, 3),
        ]);
    }

    /**
     * State: manually completed by admin.
     */
    public function manuallyCompleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutTask::STATUS_MANUALLY_COMPLETED,
            'reference_number' => 'MAN-'.fake()->numerify('######'),
            'resolution_notes' => 'Payment sent via mobile money portal',
            'completed_by' => User::factory(),
            'completed_at' => now(),
        ]);
    }

    /**
     * State: task with max retries exhausted.
     */
    public function retriesExhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutTask::STATUS_PENDING,
            'retry_count' => PayoutTask::MAX_RETRIES,
            'last_retry_at' => now()->subHours(1),
        ]);
    }

    /**
     * State: task with some retries.
     */
    public function withRetries(int $count = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'retry_count' => min($count, PayoutTask::MAX_RETRIES),
            'last_retry_at' => now()->subMinutes(30),
        ]);
    }

    /**
     * State: old task (requested more than 7 days ago).
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'requested_at' => fake()->dateTimeBetween('-30 days', '-7 days'),
        ]);
    }
}
