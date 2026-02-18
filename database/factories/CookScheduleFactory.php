<?php

namespace Database\Factories;

use App\Models\CookSchedule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * F-098: Cook Day Schedule Creation
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CookSchedule>
 */
class CookScheduleFactory extends Factory
{
    /**
     * Common slot labels for Cameroonian cook schedules.
     *
     * @var list<string>
     */
    private const SLOT_LABELS = [
        'Breakfast',
        'Lunch',
        'Dinner',
        'Morning',
        'Afternoon',
        'Evening',
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
            'day_of_week' => fake()->randomElement(CookSchedule::DAYS_OF_WEEK),
            'is_available' => true,
            'label' => fake()->randomElement(self::SLOT_LABELS),
            'position' => 1,
        ];
    }

    /**
     * Set the schedule for a specific day.
     */
    public function forDay(string $day): static
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

    /**
     * Set a specific position.
     */
    public function atPosition(int $position): static
    {
        return $this->state(fn () => [
            'position' => $position,
        ]);
    }

    /**
     * Set a specific label.
     */
    public function withLabel(string $label): static
    {
        return $this->state(fn () => [
            'label' => $label,
        ]);
    }

    /**
     * Create without a label (will default to "Slot N").
     */
    public function withoutLabel(): static
    {
        return $this->state(fn () => [
            'label' => null,
        ]);
    }
}
