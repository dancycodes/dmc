<?php

namespace Database\Factories;

use App\Models\CookWallet;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CookWallet>
 */
class CookWalletFactory extends Factory
{
    protected $model = CookWallet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $withdrawable = fake()->randomFloat(2, 0, 100000);
        $unwithdrawable = fake()->randomFloat(2, 0, 50000);

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'total_balance' => $withdrawable + $unwithdrawable,
            'withdrawable_balance' => $withdrawable,
            'unwithdrawable_balance' => $unwithdrawable,
            'currency' => 'XAF',
        ];
    }

    /**
     * Create a wallet with zero balance.
     */
    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_balance' => 0,
            'withdrawable_balance' => 0,
            'unwithdrawable_balance' => 0,
        ]);
    }

    /**
     * Create a wallet with only withdrawable balance.
     */
    public function withdrawable(float $amount = 50000): static
    {
        return $this->state(fn (array $attributes) => [
            'total_balance' => $amount,
            'withdrawable_balance' => $amount,
            'unwithdrawable_balance' => 0,
        ]);
    }

    /**
     * Create a wallet with only unwithdrawable balance.
     */
    public function unwithdrawable(float $amount = 15000): static
    {
        return $this->state(fn (array $attributes) => [
            'total_balance' => $amount,
            'withdrawable_balance' => 0,
            'unwithdrawable_balance' => $amount,
        ]);
    }

    /**
     * Create a wallet with mixed balance (both withdrawable and unwithdrawable).
     */
    public function mixed(float $withdrawable = 35000, float $unwithdrawable = 15000): static
    {
        return $this->state(fn (array $attributes) => [
            'total_balance' => $withdrawable + $unwithdrawable,
            'withdrawable_balance' => $withdrawable,
            'unwithdrawable_balance' => $unwithdrawable,
        ]);
    }
}
