<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderStatusTransition>
 */
class OrderStatusTransitionFactory extends Factory
{
    protected $model = OrderStatusTransition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'triggered_by' => User::factory(),
            'previous_status' => Order::STATUS_PAID,
            'new_status' => Order::STATUS_CONFIRMED,
            'is_admin_override' => false,
            'override_reason' => null,
        ];
    }

    /**
     * Transition from paid to confirmed.
     */
    public function paidToConfirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_status' => Order::STATUS_PAID,
            'new_status' => Order::STATUS_CONFIRMED,
        ]);
    }

    /**
     * Transition from confirmed to preparing.
     */
    public function confirmedToPreparing(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_status' => Order::STATUS_CONFIRMED,
            'new_status' => Order::STATUS_PREPARING,
        ]);
    }

    /**
     * Transition to completed.
     */
    public function toCompleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_status' => Order::STATUS_DELIVERED,
            'new_status' => Order::STATUS_COMPLETED,
        ]);
    }

    /**
     * F-159: Admin override transition.
     */
    public function adminOverride(string $reason = 'Dispute resolution'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin_override' => true,
            'override_reason' => $reason,
        ]);
    }

    /**
     * F-159: Transition to cancelled.
     */
    public function toCancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_status' => Order::STATUS_PAID,
            'new_status' => Order::STATUS_CANCELLED,
        ]);
    }

    /**
     * F-159: Transition to refunded.
     */
    public function toRefunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_status' => Order::STATUS_CANCELLED,
            'new_status' => Order::STATUS_REFUNDED,
        ]);
    }
}
