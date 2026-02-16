<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Quarter;
use App\Models\Town;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    /**
     * Common address labels.
     */
    private const LABELS = [
        'Home',
        'Office',
        'Work',
        'School',
        'Gym',
    ];

    /**
     * Cameroonian neighbourhood names.
     */
    private const NEIGHBOURHOODS = [
        'Carrefour Agip',
        'Carrefour Emia',
        'Total Makepe',
        'Rond-Point Deido',
        'Santa Barbara',
        'Cité SIC',
        'Camp Sonel',
        'Marché Central',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->unique()->randomElement(self::LABELS),
            'town_id' => Town::factory(),
            'quarter_id' => Quarter::factory(),
            'neighbourhood' => fake()->optional(0.7)->randomElement(self::NEIGHBOURHOODS),
            'additional_directions' => fake()->optional(0.5)->sentence(),
            'is_default' => false,
            'latitude' => fake()->optional(0.3)->latitude(3.5, 6.0),
            'longitude' => fake()->optional(0.3)->longitude(8.0, 14.0),
        ];
    }

    /**
     * Set the address as default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Set the address user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Set the address town and quarter consistently.
     */
    public function forTownAndQuarter(Town $town, Quarter $quarter): static
    {
        return $this->state(fn (array $attributes) => [
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
        ]);
    }
}
