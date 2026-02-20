<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    protected $model = WalletTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomElement([1350, 2700, 4500, 6750, 9000, 13500]);

        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'order_id' => null,
            'payment_transaction_id' => null,
            'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
            'amount' => $amount,
            'currency' => 'XAF',
            'balance_before' => 0,
            'balance_after' => $amount,
            'is_withdrawable' => false,
            'withdrawable_at' => now()->addHours(WalletTransaction::DEFAULT_WITHDRAWABLE_DELAY_HOURS),
            'status' => 'completed',
            'description' => 'Payment credit for order',
            'metadata' => null,
        ];
    }

    /**
     * State: payment credit (cook receives money from order).
     */
    public function paymentCredit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => WalletTransaction::TYPE_PAYMENT_CREDIT,
            'is_withdrawable' => false,
            'withdrawable_at' => now()->addHours(WalletTransaction::DEFAULT_WITHDRAWABLE_DELAY_HOURS),
        ]);
    }

    /**
     * State: commission deduction.
     */
    public function commission(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => WalletTransaction::TYPE_COMMISSION,
            'is_withdrawable' => false,
            'withdrawable_at' => null,
            'description' => 'Platform commission',
        ]);
    }

    /**
     * State: withdrawable (delay has passed).
     */
    public function withdrawable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_withdrawable' => true,
            'withdrawable_at' => now()->subHour(),
        ]);
    }

    /**
     * State: refund.
     */
    public function refund(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => WalletTransaction::TYPE_REFUND,
            'is_withdrawable' => true,
            'withdrawable_at' => null,
            'description' => 'Refund credit',
        ]);
    }
}
