<?php

namespace Database\Factories;

use App\Models\Meal;
use App\Models\MealComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MealComponent>
 */
class MealComponentFactory extends Factory
{
    /**
     * Realistic Cameroonian meal component names.
     *
     * @var array<array{en: string, fr: string}>
     */
    private const COMPONENT_NAMES = [
        ['en' => 'Ndole with Plantain', 'fr' => 'Ndole avec Plantain'],
        ['en' => 'Ndole with Rice', 'fr' => 'Ndole avec Riz'],
        ['en' => 'Eru with Water Fufu', 'fr' => 'Eru avec Water Fufu'],
        ['en' => 'Grilled Chicken', 'fr' => 'Poulet grille'],
        ['en' => 'Fried Fish', 'fr' => 'Poisson frit'],
        ['en' => 'Jollof Rice', 'fr' => 'Riz Jollof'],
        ['en' => 'Pepper Soup', 'fr' => 'Soupe au poivre'],
        ['en' => 'Boiled Yam', 'fr' => 'Igname bouillie'],
        ['en' => 'Plantain and Beans', 'fr' => 'Plantain et haricots'],
        ['en' => 'Achu Soup', 'fr' => 'Soupe Achu'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $component = fake()->randomElement(self::COMPONENT_NAMES);
        $suffix = fake()->unique()->numerify('###');

        return [
            'meal_id' => Meal::factory(),
            'name_en' => $component['en'].' '.$suffix,
            'name_fr' => $component['fr'].' '.$suffix,
            'description_en' => null,
            'description_fr' => null,
            'price' => fake()->randomElement([500, 750, 1000, 1500, 2000, 2500, 3000, 5000]),
            'selling_unit' => fake()->randomElement(MealComponent::STANDARD_UNITS),
            'min_quantity' => 0,
            'max_quantity' => null,
            'available_quantity' => null,
            'is_available' => true,
            'position' => 0,
        ];
    }

    /**
     * Component with a specific price.
     */
    public function withPrice(int $price): static
    {
        return $this->state(fn () => [
            'price' => $price,
        ]);
    }

    /**
     * Component with quantity limits.
     */
    public function withQuantityLimits(int $min = 1, ?int $max = 5, ?int $available = 20): static
    {
        return $this->state(fn () => [
            'min_quantity' => $min,
            'max_quantity' => $max,
            'available_quantity' => $available,
        ]);
    }

    /**
     * Unavailable component.
     */
    public function unavailable(): static
    {
        return $this->state(fn () => [
            'is_available' => false,
        ]);
    }

    /**
     * Component with a specific selling unit.
     */
    public function withUnit(string $unit): static
    {
        return $this->state(fn () => [
            'selling_unit' => $unit,
        ]);
    }

    /**
     * Component with a specific position.
     */
    public function withPosition(int $position): static
    {
        return $this->state(fn () => [
            'position' => $position,
        ]);
    }

    /**
     * Add a description to the component.
     */
    public function withDescription(): static
    {
        return $this->state(fn () => [
            'description_en' => 'Fresh and locally sourced',
            'description_fr' => 'Frais et d\'origine locale',
        ]);
    }
}
