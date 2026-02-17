<?php

namespace Database\Factories;

use App\Models\Meal;
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
        ['en' => 'Ndole with spinach and peanuts', 'fr' => 'Ndole aux epinards et arachides'],
        ['en' => 'Fried Plantain', 'fr' => 'Plantain frit'],
        ['en' => 'Steamed Rice', 'fr' => 'Riz vapeur'],
        ['en' => 'Grilled Chicken', 'fr' => 'Poulet grille'],
        ['en' => 'Fresh Fish', 'fr' => 'Poisson frais'],
        ['en' => 'Coleslaw Salad', 'fr' => 'Salade de chou'],
        ['en' => 'Spicy Sauce', 'fr' => 'Sauce piquante'],
        ['en' => 'Water Fufu', 'fr' => 'Water Fufu'],
        ['en' => 'Boiled Yam', 'fr' => 'Igname bouillie'],
        ['en' => 'Miondo', 'fr' => 'Miondo'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $component = fake()->randomElement(self::COMPONENT_NAMES);

        return [
            'meal_id' => Meal::factory(),
            'name_en' => $component['en'],
            'name_fr' => $component['fr'],
            'description_en' => null,
            'description_fr' => null,
        ];
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
