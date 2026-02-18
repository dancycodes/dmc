<?php

namespace Database\Factories;

use App\Models\Meal;
use App\Models\MealLocationOverride;
use App\Models\PickupLocation;
use App\Models\Quarter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealLocationOverride>
 */
class MealLocationOverrideFactory extends Factory
{
    protected $model = MealLocationOverride::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meal_id' => Meal::factory(),
            'quarter_id' => null,
            'pickup_location_id' => null,
            'custom_delivery_fee' => null,
        ];
    }

    /**
     * Create a delivery quarter override.
     */
    public function forQuarter(?Quarter $quarter = null): static
    {
        return $this->state(fn (array $attributes) => [
            'quarter_id' => $quarter?->id ?? Quarter::factory(),
            'pickup_location_id' => null,
        ]);
    }

    /**
     * Create a pickup location override.
     */
    public function forPickup(?PickupLocation $pickup = null): static
    {
        return $this->state(fn (array $attributes) => [
            'quarter_id' => null,
            'pickup_location_id' => $pickup?->id ?? PickupLocation::factory(),
        ]);
    }

    /**
     * Set a custom delivery fee.
     */
    public function withCustomFee(int $fee = 500): static
    {
        return $this->state(fn (array $attributes) => [
            'custom_delivery_fee' => $fee,
        ]);
    }
}
