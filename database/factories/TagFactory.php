<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    /**
     * Cameroonian food category tags for realistic test data.
     */
    private const TAGS_EN = [
        'Spicy', 'Vegetarian', 'Popular', 'New', 'Breakfast',
        'Lunch', 'Dinner', 'Grilled', 'Traditional', 'Street Food',
        'Family Size', 'Quick Bite', 'Healthy', 'Sweet', 'Savory',
        'Ndole Special', 'Eru Classic', 'Koki', 'Achu', 'Plantain',
    ];

    private const TAGS_FR = [
        'Epice', 'Vegetarien', 'Populaire', 'Nouveau', 'Petit-dejeuner',
        'Dejeuner', 'Diner', 'Grille', 'Traditionnel', 'Cuisine de rue',
        'Format familial', 'En-cas rapide', 'Sain', 'Sucre', 'Sale',
        'Special Ndole', 'Eru Classique', 'Koki', 'Achu', 'Plantain',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $index = fake()->unique()->numberBetween(0, count(self::TAGS_EN) - 1);

        return [
            'tenant_id' => Tenant::factory(),
            'name_en' => self::TAGS_EN[$index] ?? fake()->unique()->word(),
            'name_fr' => self::TAGS_FR[$index] ?? fake()->unique()->word(),
        ];
    }

    /**
     * Create a tag with a specific tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
