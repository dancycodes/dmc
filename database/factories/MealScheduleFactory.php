<?php

namespace Database\Factories;

use App\Models\Meal;
use App\Models\MealSchedule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * F-106: Meal Schedule Override
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MealSchedule>
 */
class MealScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'meal_id' => Meal::factory(),
            'day_of_week' => fake()->randomElement(MealSchedule::DAYS_OF_WEEK),
            'is_available' => true,
            'label' => null,
            'position' => 1,
            'order_start_time' => null,
            'order_start_day_offset' => 0,
            'order_end_time' => null,
            'order_end_day_offset' => 0,
            'delivery_enabled' => false,
            'delivery_start_time' => null,
            'delivery_end_time' => null,
            'pickup_enabled' => false,
            'pickup_start_time' => null,
            'pickup_end_time' => null,
        ];
    }

    /**
     * Set the schedule as unavailable.
     */
    public function unavailable(): static
    {
        return $this->state(fn () => [
            'is_available' => false,
        ]);
    }

    /**
     * Set a specific day of the week.
     */
    public function forDay(string $day): static
    {
        return $this->state(fn () => [
            'day_of_week' => $day,
        ]);
    }

    /**
     * Set a label.
     */
    public function labeled(string $label): static
    {
        return $this->state(fn () => [
            'label' => $label,
        ]);
    }

    /**
     * Configure with order interval.
     */
    public function withOrderInterval(
        string $startTime = '06:00',
        int $startOffset = 0,
        string $endTime = '10:00',
        int $endOffset = 0,
    ): static {
        return $this->state(fn () => [
            'order_start_time' => $startTime,
            'order_start_day_offset' => $startOffset,
            'order_end_time' => $endTime,
            'order_end_day_offset' => $endOffset,
        ]);
    }

    /**
     * Configure with delivery interval.
     */
    public function withDelivery(
        string $startTime = '11:00',
        string $endTime = '14:00',
    ): static {
        return $this->state(fn () => [
            'delivery_enabled' => true,
            'delivery_start_time' => $startTime,
            'delivery_end_time' => $endTime,
        ]);
    }

    /**
     * Configure with pickup interval.
     */
    public function withPickup(
        string $startTime = '10:30',
        string $endTime = '15:00',
    ): static {
        return $this->state(fn () => [
            'pickup_enabled' => true,
            'pickup_start_time' => $startTime,
            'pickup_end_time' => $endTime,
        ]);
    }

    /**
     * Fully configured schedule entry with order + delivery + pickup.
     */
    public function fullyConfigured(): static
    {
        return $this->withOrderInterval()
            ->withDelivery()
            ->withPickup();
    }
}
