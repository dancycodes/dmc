<?php

namespace Database\Factories;

use App\Models\Quarter;
use App\Models\Town;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quarter>
 */
class QuarterFactory extends Factory
{
    protected $model = Quarter::class;

    /**
     * Cameroonian quarter names with English and French variants.
     */
    private const QUARTERS = [
        ['name_en' => 'Bonamoussadi', 'name_fr' => 'Bonamoussadi'],
        ['name_en' => 'Akwa', 'name_fr' => 'Akwa'],
        ['name_en' => 'Deido', 'name_fr' => 'Deido'],
        ['name_en' => 'Bonaberi', 'name_fr' => 'Bonabéri'],
        ['name_en' => 'Makepe', 'name_fr' => 'Makepe'],
        ['name_en' => 'Bastos', 'name_fr' => 'Bastos'],
        ['name_en' => 'Nlongkak', 'name_fr' => 'Nlongkak'],
        ['name_en' => 'Essos', 'name_fr' => 'Essos'],
        ['name_en' => 'Melen', 'name_fr' => 'Melen'],
        ['name_en' => 'Biyem-Assi', 'name_fr' => 'Biyem-Assi'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quarter = fake()->randomElement(self::QUARTERS);

        return [
            'town_id' => Town::factory(),
            'name_en' => $quarter['name_en'],
            'name_fr' => $quarter['name_fr'],
            'is_active' => true,
        ];
    }

    /**
     * Set the quarter's town.
     */
    public function forTown(Town $town): static
    {
        return $this->state(fn (array $attributes) => [
            'town_id' => $town->id,
        ]);
    }

    /**
     * Indicate that the quarter is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
