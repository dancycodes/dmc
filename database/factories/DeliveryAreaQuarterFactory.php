<?php

namespace Database\Factories;

use App\Models\DeliveryArea;
use App\Models\DeliveryAreaQuarter;
use App\Models\Quarter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryAreaQuarter>
 */
class DeliveryAreaQuarterFactory extends Factory
{
    protected $model = DeliveryAreaQuarter::class;

    /**
     * Cameroonian-realistic delivery fee values in XAF.
     */
    private const DELIVERY_FEES = [0, 200, 300, 500, 750, 1000, 1500, 2000];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_area_id' => DeliveryArea::factory(),
            'quarter_id' => Quarter::factory(),
            'delivery_fee' => fake()->randomElement(self::DELIVERY_FEES),
        ];
    }

    /**
     * Set free delivery (0 XAF).
     */
    public function freeDelivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_fee' => 0,
        ]);
    }
}
