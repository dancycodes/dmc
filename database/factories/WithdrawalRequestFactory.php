<?php

namespace Database\Factories;

use App\Models\CookWallet;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WithdrawalRequest>
 */
class WithdrawalRequestFactory extends Factory
{
    protected $model = WithdrawalRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cook_wallet_id' => CookWallet::factory(),
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->numberBetween(1000, 50000),
            'currency' => 'XAF',
            'mobile_money_number' => '6'.fake()->numerify('########'),
            'mobile_money_provider' => fake()->randomElement([
                WithdrawalRequest::PROVIDER_MTN_MOMO,
                WithdrawalRequest::PROVIDER_ORANGE_MONEY,
            ]),
            'status' => WithdrawalRequest::STATUS_PENDING,
            'requested_at' => now(),
        ];
    }

    /**
     * State: pending withdrawal.
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => WithdrawalRequest::STATUS_PENDING,
        ]);
    }

    /**
     * State: processing withdrawal.
     */
    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => WithdrawalRequest::STATUS_PROCESSING,
        ]);
    }

    /**
     * State: completed withdrawal.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => WithdrawalRequest::STATUS_COMPLETED,
            'processed_at' => now(),
            'flutterwave_reference' => 'FLW-'.fake()->unique()->numerify('##########'),
        ]);
    }

    /**
     * State: failed withdrawal.
     */
    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => WithdrawalRequest::STATUS_FAILED,
            'processed_at' => now(),
            'failure_reason' => 'Insufficient funds on provider side',
        ]);
    }
}
