<?php

namespace Database\Factories;

use App\Models\CookSchedule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * F-098: Cook Day Schedule Creation
 * F-100: Delivery/Pickup Time Interval Configuration
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

    /**
     * Create with an order interval configured.
     *
     * F-099: Order Time Interval Configuration
     */
    public function withOrderInterval(
        string $startTime = '18:00',
        int $startDayOffset = 1,
        string $endTime = '08:00',
        int $endDayOffset = 0,
    ): static {
        return $this->state(fn () => [
            'is_available' => true,
            'order_start_time' => $startTime,
            'order_start_day_offset' => $startDayOffset,
            'order_end_time' => $endTime,
            'order_end_day_offset' => $endDayOffset,
        ]);
    }

    /**
     * Create a same-day order interval.
     *
     * F-099: Order Time Interval Configuration
     */
    public function withSameDayInterval(string $startTime = '06:00', string $endTime = '10:00'): static
    {
        return $this->withOrderInterval($startTime, 0, $endTime, 0);
    }

    /**
     * Create with delivery interval configured.
     *
     * F-100: Delivery/Pickup Time Interval Configuration
     */
    public function withDeliveryInterval(string $startTime = '11:00', string $endTime = '14:00'): static
    {
        return $this->state(fn () => [
            'delivery_enabled' => true,
            'delivery_start_time' => $startTime,
            'delivery_end_time' => $endTime,
        ]);
    }

    /**
     * Create with pickup interval configured.
     *
     * F-100: Delivery/Pickup Time Interval Configuration
     */
    public function withPickupInterval(string $startTime = '10:30', string $endTime = '15:00'): static
    {
        return $this->state(fn () => [
            'pickup_enabled' => true,
            'pickup_start_time' => $startTime,
            'pickup_end_time' => $endTime,
        ]);
    }

    /**
     * Create with both delivery and pickup intervals configured.
     *
     * F-100: Delivery/Pickup Time Interval Configuration
     */
    public function withBothIntervals(
        string $deliveryStart = '11:00',
        string $deliveryEnd = '14:00',
        string $pickupStart = '10:30',
        string $pickupEnd = '15:00',
    ): static {
        return $this->withDeliveryInterval($deliveryStart, $deliveryEnd)
            ->withPickupInterval($pickupStart, $pickupEnd);
    }
}
