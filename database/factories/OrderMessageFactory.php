<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderMessage>
 */
class OrderMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'sender_id' => User::factory(),
            'sender_role' => fake()->randomElement([
                OrderMessage::ROLE_CLIENT,
                OrderMessage::ROLE_COOK,
                OrderMessage::ROLE_MANAGER,
            ]),
            'body' => fake()->sentence(fake()->numberBetween(5, 20)),
        ];
    }

    /**
     * Message from client.
     */
    public function fromClient(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_role' => OrderMessage::ROLE_CLIENT,
        ]);
    }

    /**
     * Message from cook.
     */
    public function fromCook(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_role' => OrderMessage::ROLE_COOK,
        ]);
    }

    /**
     * Message from manager.
     */
    public function fromManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_role' => OrderMessage::ROLE_MANAGER,
        ]);
    }

    /**
     * Message from deleted user.
     */
    public function fromDeletedUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_id' => null,
        ]);
    }
}
