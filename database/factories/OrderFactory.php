<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomElement([1500, 2000, 3000, 5000, 7500, 10000, 15000]);
        $deliveryFee = fake()->randomElement([0, 200, 500, 1000]);
        $grandTotal = $subtotal + $deliveryFee;

        return [
            'client_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'cook_id' => User::factory(),
            'order_number' => 'DMC-'.now()->format('ymd').'-'.fake()->unique()->numerify('####'),
            'status' => Order::STATUS_PENDING_PAYMENT,
            'delivery_method' => Order::METHOD_DELIVERY,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'promo_discount' => 0,
            'grand_total' => $grandTotal,
            'phone' => '+237'.fake()->randomElement(['6']).fake()->numerify('########'),
            'payment_provider' => fake()->randomElement(['mtn_momo', 'orange_money']),
            'payment_phone' => '+237'.fake()->randomElement(['6']).fake()->numerify('########'),
            'items_snapshot' => [
                [
                    'meal_id' => 1,
                    'meal_name' => 'Jollof Rice',
                    'component_id' => 1,
                    'component_name' => 'Standard Plate',
                    'quantity' => 2,
                    'unit_price' => 1500,
                    'subtotal' => 3000,
                ],
            ],
        ];
    }

    /**
     * State: pending payment.
     */
    public function pendingPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);
    }

    /**
     * State: paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    /**
     * State: payment failed.
     */
    public function paymentFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PAYMENT_FAILED,
        ]);
    }

    /**
     * State: cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * State: delivery order.
     */
    public function delivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_method' => Order::METHOD_DELIVERY,
            'delivery_fee' => 500,
            'grand_total' => ($attributes['subtotal'] ?? 3000) + 500,
        ]);
    }

    /**
     * State: pickup order.
     */
    public function pickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_method' => Order::METHOD_PICKUP,
            'delivery_fee' => 0,
            'grand_total' => $attributes['subtotal'] ?? 3000,
        ]);
    }

    /**
     * State: MTN MoMo payment.
     */
    public function mtn(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_provider' => 'mtn_momo',
        ]);
    }

    /**
     * State: Orange Money payment.
     */
    public function orange(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_provider' => 'orange_money',
        ]);
    }

    /**
     * State: created more than 15 minutes ago (for timeout testing).
     */
    public function timedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PENDING_PAYMENT,
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
            'payment_retry_expires_at' => now()->subMinutes(5),
        ]);
    }

    /**
     * F-152: State: with active retry window.
     */
    public function withRetryWindow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PAYMENT_FAILED,
            'retry_count' => 1,
            'payment_retry_expires_at' => now()->addMinutes(10),
        ]);
    }

    /**
     * F-152: State: max retries exhausted.
     */
    public function retriesExhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PAYMENT_FAILED,
            'retry_count' => Order::MAX_RETRY_ATTEMPTS,
            'payment_retry_expires_at' => now()->addMinutes(5),
        ]);
    }

    /**
     * F-155: State: confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CONFIRMED,
            'paid_at' => now()->subMinutes(30),
            'confirmed_at' => now(),
        ]);
    }

    /**
     * F-155: State: preparing.
     */
    public function preparing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PREPARING,
            'paid_at' => now()->subHour(),
            'confirmed_at' => now()->subMinutes(30),
        ]);
    }

    /**
     * F-155: State: ready.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_READY,
            'paid_at' => now()->subHours(2),
            'confirmed_at' => now()->subHour(),
        ]);
    }

    /**
     * F-155: State: completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_COMPLETED,
            'paid_at' => now()->subHours(3),
            'confirmed_at' => now()->subHours(2),
            'completed_at' => now(),
        ]);
    }

    /**
     * F-155: State: with multi-item snapshot.
     */
    public function withMultipleItems(): static
    {
        return $this->state(fn (array $attributes) => [
            'items_snapshot' => [
                ['meal_id' => 1, 'meal_name' => 'Ndole', 'component_name' => 'Standard', 'quantity' => 2, 'unit_price' => 1500, 'subtotal' => 3000],
                ['meal_id' => 2, 'meal_name' => 'Eru', 'component_name' => 'Large', 'quantity' => 1, 'unit_price' => 2000, 'subtotal' => 2000],
                ['meal_id' => 3, 'meal_name' => 'Jollof Rice', 'component_name' => 'Family', 'quantity' => 3, 'unit_price' => 2500, 'subtotal' => 7500],
            ],
            'subtotal' => 12500,
            'grand_total' => 13000,
        ]);
    }
}
