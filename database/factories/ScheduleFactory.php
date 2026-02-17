<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Cameroonian business hours.
     *
     * @var array<string>
     */
    private const START_TIMES = ['07:00', '08:00', '09:00', '10:00', '11:00', '12:00'];

    private const END_TIMES = ['17:00', '18:00', '19:00', '20:00', '21:00', '22:00'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'day_of_week' => fake()->numberBetween(Schedule::SUNDAY, Schedule::SATURDAY),
            'start_time' => fake()->randomElement(self::START_TIMES),
            'end_time' => fake()->randomElement(self::END_TIMES),
            'is_available' => true,
        ];
    }

    /**
     * Set the schedule for a specific day.
     */
    public function forDay(int $day): static
    {
        return $this->state(fn () => [
            'day_of_week' => $day,
        ]);
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
}
