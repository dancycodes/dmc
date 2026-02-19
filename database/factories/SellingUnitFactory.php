<?php

namespace Database\Factories;

use App\Models\SellingUnit;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellingUnit>
 */
class SellingUnitFactory extends Factory
{
    protected $model = SellingUnit::class;

    /**
     * Custom unit name examples for Cameroonian cuisine.
     *
     * @var array<array{en: string, fr: string}>
     */
    private const CUSTOM_UNIT_NAMES = [
        ['en' => 'Calabash', 'fr' => 'Calebasse'],
        ['en' => 'Wrap', 'fr' => 'Emballage'],
        ['en' => 'Bucket', 'fr' => 'Seau'],
        ['en' => 'Basket', 'fr' => 'Panier'],
        ['en' => 'Tray', 'fr' => 'Plateau'],
        ['en' => 'Gourd', 'fr' => 'Gourde'],
        ['en' => 'Bundle', 'fr' => 'Botte'],
        ['en' => 'Skewer', 'fr' => 'Brochette'],
        ['en' => 'Cone', 'fr' => 'Cornet'],
        ['en' => 'Scoop', 'fr' => 'Louche'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitName = fake()->unique()->randomElement(self::CUSTOM_UNIT_NAMES);

        return [
            'tenant_id' => Tenant::factory(),
            'name_en' => $unitName['en'],
            'name_fr' => $unitName['fr'],
            'is_standard' => false,
        ];
    }

    /**
     * State for standard units.
     */
    public function standard(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
            'is_standard' => true,
        ]);
    }

    /**
     * State for custom (tenant-scoped) units.
     */
    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_standard' => false,
        ]);
    }

    /**
     * State for a specific tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
