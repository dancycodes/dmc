<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuarterGroup>
 */
class QuarterGroupFactory extends Factory
{
    /**
     * Realistic Cameroonian zone group names.
     */
    private const GROUP_NAMES = [
        'Central Douala',
        'South Douala',
        'North Douala',
        'Premium Zone',
        'Nearby Areas',
        'Downtown',
        'Industrial Zone',
        'University Area',
        'Market Zone',
        'Residential West',
        'Residential East',
        'Airport Area',
        'Port Zone',
        'Commercial District',
        'Coastal Zone',
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
            'name' => fake()->unique()->randomElement(self::GROUP_NAMES),
            'delivery_fee' => fake()->randomElement([0, 100, 200, 300, 500, 800, 1000, 1500]),
        ];
    }

    /**
     * State: free delivery group.
     */
    public function freeDelivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_fee' => 0,
        ]);
    }

    /**
     * State: high fee group.
     */
    public function highFee(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_fee' => 5000,
        ]);
    }
}
