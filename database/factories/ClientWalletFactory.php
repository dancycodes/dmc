<?php

namespace Database\Factories;

use App\Models\ClientWallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientWallet>
 */
class ClientWalletFactory extends Factory
{
    protected $model = ClientWallet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'balance' => 0,
            'currency' => 'XAF',
        ];
    }

    /**
     * Wallet with positive balance (has received refunds).
     */
    public function withBalance(float $amount = 5000): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $amount,
        ]);
    }

    /**
     * Wallet with zero balance (never received a refund).
     */
    public function zeroBalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => 0,
        ]);
    }
}
