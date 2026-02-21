<?php

namespace Database\Factories;

use App\Models\CookWallet;
use App\Models\Order;
use App\Models\PendingDeduction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * F-174: Cook Auto-Deduction for Refunds
 *
 * @extends Factory<PendingDeduction>
 */
class PendingDeductionFactory extends Factory
{
    protected $model = PendingDeduction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomElement([1000, 2000, 3000, 5000, 7500, 10000]);

        return [
            'cook_wallet_id' => CookWallet::factory(),
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'order_id' => null,
            'original_amount' => $amount,
            'remaining_amount' => $amount,
            'reason' => __('Refund for cancelled order'),
            'source' => PendingDeduction::SOURCE_COMPLAINT_REFUND,
            'metadata' => null,
            'settled_at' => null,
        ];
    }

    /**
     * State: partially settled deduction.
     */
    public function partiallySettled(float $settledAmount = 0): static
    {
        return $this->state(function (array $attributes) use ($settledAmount) {
            $original = (float) $attributes['original_amount'];
            $settled = $settledAmount > 0 ? $settledAmount : $original * 0.5;
            $remaining = max(0, $original - $settled);

            return [
                'remaining_amount' => $remaining,
            ];
        });
    }

    /**
     * State: fully settled deduction.
     */
    public function settled(): static
    {
        return $this->state(fn () => [
            'remaining_amount' => 0,
            'settled_at' => now(),
        ]);
    }

    /**
     * State: from complaint refund.
     */
    public function fromComplaint(): static
    {
        return $this->state(fn () => [
            'source' => PendingDeduction::SOURCE_COMPLAINT_REFUND,
            'reason' => __('Complaint resolution refund'),
        ]);
    }

    /**
     * State: from cancellation refund.
     */
    public function fromCancellation(): static
    {
        return $this->state(fn () => [
            'source' => PendingDeduction::SOURCE_CANCELLATION_REFUND,
            'reason' => __('Order cancellation refund'),
        ]);
    }

    /**
     * State: with specific amount.
     */
    public function withAmount(float $amount): static
    {
        return $this->state(fn () => [
            'original_amount' => $amount,
            'remaining_amount' => $amount,
        ]);
    }

    /**
     * State: with associated order.
     */
    public function withOrder(Order $order): static
    {
        return $this->state(fn () => [
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
        ]);
    }

    /**
     * State: for specific cook wallet.
     */
    public function forWallet(CookWallet $wallet): static
    {
        return $this->state(fn () => [
            'cook_wallet_id' => $wallet->id,
            'tenant_id' => $wallet->tenant_id,
            'user_id' => $wallet->user_id,
        ]);
    }
}
