<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * F-101: Create Schedule Template
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduleTemplate>
 */
class ScheduleTemplateFactory extends Factory
{
    /**
     * Common template names for Cameroonian cook schedules.
     *
     * @var list<string>
     */
    private const TEMPLATE_NAMES = [
        'Lunch Service',
        'Dinner Service',
        'Breakfast Service',
        'Morning Rush',
        'Afternoon Special',
        'Evening Menu',
        'Weekend Brunch',
        'Late Night',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->randomElement(self::TEMPLATE_NAMES),
            'order_start_time' => '18:00',
            'order_start_day_offset' => 1,
            'order_end_time' => '08:00',
            'order_end_day_offset' => 0,
            'delivery_enabled' => true,
            'delivery_start_time' => '11:00',
            'delivery_end_time' => '14:00',
            'pickup_enabled' => true,
            'pickup_start_time' => '10:30',
            'pickup_end_time' => '15:00',
        ];
    }

    /**
     * Create a delivery-only template.
     */
    public function deliveryOnly(): static
    {
        return $this->state(fn () => [
            'delivery_enabled' => true,
            'delivery_start_time' => '11:00',
            'delivery_end_time' => '14:00',
            'pickup_enabled' => false,
            'pickup_start_time' => null,
            'pickup_end_time' => null,
        ]);
    }

    /**
     * Create a pickup-only template.
     */
    public function pickupOnly(): static
    {
        return $this->state(fn () => [
            'delivery_enabled' => false,
            'delivery_start_time' => null,
            'delivery_end_time' => null,
            'pickup_enabled' => true,
            'pickup_start_time' => '10:30',
            'pickup_end_time' => '15:00',
        ]);
    }

    /**
     * Create a same-day order interval template.
     */
    public function sameDayOrders(): static
    {
        return $this->state(fn () => [
            'order_start_time' => '06:00',
            'order_start_day_offset' => 0,
            'order_end_time' => '10:00',
            'order_end_day_offset' => 0,
        ]);
    }

    /**
     * Create with a specific name.
     */
    public function withName(string $name): static
    {
        return $this->state(fn () => [
            'name' => $name,
        ]);
    }
}
