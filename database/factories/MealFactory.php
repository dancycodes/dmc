<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Meal>
 */
class MealFactory extends Factory
{
    /**
     * Realistic Cameroonian meal names.
     *
     * @var array<array{en: string, fr: string}>
     */
    private const MEAL_NAMES = [
        ['en' => 'Ndole & Plantain', 'fr' => 'Ndole et Plantain'],
        ['en' => 'Eru & Water Fufu', 'fr' => 'Eru et Water Fufu'],
        ['en' => 'Achu Soup', 'fr' => 'Soupe Achu'],
        ['en' => 'Koki Beans', 'fr' => 'Koki de Haricots'],
        ['en' => 'Jollof Rice', 'fr' => 'Riz Jollof'],
        ['en' => 'Pepper Soup', 'fr' => 'Soupe Poivre'],
        ['en' => 'Poulet DG', 'fr' => 'Poulet DG'],
        ['en' => 'Grilled Fish', 'fr' => 'Poisson Braise'],
        ['en' => 'Soya (Grilled Meat)', 'fr' => 'Soya (Viande Grillee)'],
        ['en' => 'Ekwang', 'fr' => 'Ekwang'],
        ['en' => 'Mbongo Tchobi', 'fr' => 'Mbongo Tchobi'],
        ['en' => 'Okok', 'fr' => 'Okok'],
    ];

    /**
     * Realistic Cameroonian meal descriptions.
     *
     * @var array<array{en: string, fr: string}>
     */
    private const DESCRIPTIONS = [
        ['en' => 'Traditional Cameroonian dish with rich flavors', 'fr' => 'Plat camerounais traditionnel aux saveurs riches'],
        ['en' => 'Homemade with fresh local ingredients', 'fr' => 'Fait maison avec des ingredients locaux frais'],
        ['en' => 'A hearty meal perfect for lunch or dinner', 'fr' => 'Un repas copieux parfait pour le dejeuner ou le diner'],
        ['en' => 'Served with your choice of sides', 'fr' => 'Servi avec vos accompagnements au choix'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(self::MEAL_NAMES);
        $description = fake()->optional(0.7)->randomElement(self::DESCRIPTIONS);

        return [
            'tenant_id' => Tenant::factory(),
            'name_en' => $name['en'],
            'name_fr' => $name['fr'],
            'description_en' => $description['en'] ?? null,
            'description_fr' => $description['fr'] ?? null,
            'price' => fake()->randomElement([500, 1000, 1500, 2000, 2500, 3000, 3500, 5000]),
            'is_active' => true,
        ];
    }

    /**
     * Set the meal as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a specific price.
     */
    public function priced(int $price): static
    {
        return $this->state(fn () => [
            'price' => $price,
        ]);
    }
}
