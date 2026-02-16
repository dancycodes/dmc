<?php

namespace Database\Factories;

use App\Models\Town;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Town>
 */
class TownFactory extends Factory
{
    protected $model = Town::class;

    /**
     * Cameroonian towns with English and French names.
     */
    private const TOWNS = [
        ['name_en' => 'Douala', 'name_fr' => 'Douala'],
        ['name_en' => 'Yaounde', 'name_fr' => 'Yaoundé'],
        ['name_en' => 'Bamenda', 'name_fr' => 'Bamenda'],
        ['name_en' => 'Bafoussam', 'name_fr' => 'Bafoussam'],
        ['name_en' => 'Buea', 'name_fr' => 'Buéa'],
        ['name_en' => 'Limbe', 'name_fr' => 'Limbé'],
        ['name_en' => 'Maroua', 'name_fr' => 'Maroua'],
        ['name_en' => 'Garoua', 'name_fr' => 'Garoua'],
        ['name_en' => 'Kumba', 'name_fr' => 'Kumba'],
        ['name_en' => 'Bertoua', 'name_fr' => 'Bertoua'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $town = fake()->randomElement(self::TOWNS);

        return [
            'name_en' => $town['name_en'],
            'name_fr' => $town['name_fr'],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the town is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
