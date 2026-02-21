<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderClearance;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderClearance>
 */
class OrderClearanceFactory extends Factory
{
    protected $model = OrderClearance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $completedAt = now()->subHours(fake()->numberBetween(1, 48));
        $holdHours = 3;

        return [
            'order_id' => Order::factory(),
            'tenant_id' => Tenant::factory(),
            'cook_id' => User::factory(),
            'amount' => fake()->numberBetween(500, 50000),
            'hold_hours' => $holdHours,
            'completed_at' => $completedAt,
            'withdrawable_at' => $completedAt->copy()->addHours($holdHours),
            'paused_at' => null,
            'remaining_seconds_at_pause' => null,
            'cleared_at' => null,
            'is_cleared' => false,
            'is_paused' => false,
            'is_cancelled' => false,
        ];
    }

    /**
     * State: clearance is eligible (hold period expired, not cleared).
     */
    public function eligible(): static
    {
        return $this->state(function (array $attributes) {
            $completedAt = now()->subHours(4);

            return [
                'completed_at' => $completedAt,
                'withdrawable_at' => $completedAt->copy()->addHours(3),
                'is_cleared' => false,
                'is_paused' => false,
                'is_cancelled' => false,
            ];
        });
    }

    /**
     * State: already cleared.
     */
    public function cleared(): static
    {
        return $this->state(function (array $attributes) {
            $completedAt = now()->subHours(6);

            return [
                'completed_at' => $completedAt,
                'withdrawable_at' => $completedAt->copy()->addHours(3),
                'cleared_at' => $completedAt->copy()->addHours(3)->addMinutes(5),
                'is_cleared' => true,
                'is_paused' => false,
                'is_cancelled' => false,
            ];
        });
    }

    /**
     * State: paused by complaint.
     */
    public function paused(): static
    {
        return $this->state(function (array $attributes) {
            $completedAt = now()->subHours(2);

            return [
                'completed_at' => $completedAt,
                'withdrawable_at' => $completedAt->copy()->addHours(3),
                'paused_at' => now()->subHour(),
                'remaining_seconds_at_pause' => 3600,
                'is_cleared' => false,
                'is_paused' => true,
                'is_cancelled' => false,
            ];
        });
    }

    /**
     * State: cancelled due to refund.
     */
    public function cancelled(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_cleared' => false,
                'is_paused' => false,
                'is_cancelled' => true,
            ];
        });
    }

    /**
     * State: still in hold period (not yet eligible).
     */
    public function inHoldPeriod(): static
    {
        return $this->state(function (array $attributes) {
            $completedAt = now()->subHour();

            return [
                'completed_at' => $completedAt,
                'withdrawable_at' => $completedAt->copy()->addHours(3),
                'is_cleared' => false,
                'is_paused' => false,
                'is_cancelled' => false,
            ];
        });
    }
}
