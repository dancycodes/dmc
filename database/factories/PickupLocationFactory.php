<?php

namespace Database\Factories;

use App\Models\PickupLocation;
use App\Models\Quarter;
use App\Models\Tenant;
use App\Models\Town;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PickupLocation>
 */
class PickupLocationFactory extends Factory
{
    protected $model = PickupLocation::class;

    /**
     * Cameroonian location names for realistic test data.
     */
    private const NAMES_EN = ['My Kitchen', 'Main Office', 'Market Stand', 'Central Kitchen', 'Pickup Point'];

    private const NAMES_FR = ['Ma Cuisine', 'Bureau Principal', 'Stand du Marche', 'Cuisine Centrale', 'Point de Retrait'];

    private const ADDRESSES = [
        'Behind Akwa Palace Hotel',
        'Next to Mokolo Market',
        'Opposite Total Station Bastos',
        'Near Biyem-Assi Crossroads',
        'Along Bonamoussadi Main Road',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $index = fake()->numberBetween(0, count(self::NAMES_EN) - 1);

        return [
            'tenant_id' => Tenant::factory(),
            'town_id' => Town::factory(),
            'quarter_id' => Quarter::factory(),
            'name_en' => self::NAMES_EN[$index],
            'name_fr' => self::NAMES_FR[$index],
            'address' => fake()->randomElement(self::ADDRESSES),
        ];
    }
}
