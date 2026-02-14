<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Cameroonian cook names for realistic test data.
     */
    private const COOK_NAMES = [
        'Latifa Kitchen',
        'Mama Ngono Dishes',
        'Chef Powel',
        'Auntie Bih Cuisine',
        'Iya Bastos Foods',
        'Ndoleh Queen',
        'Chef Tabi',
        'Mama Caro Eats',
        'Burning Tongue',
        'Chez Mariette',
        'Kondre Palace',
        'Eru Masters',
        'Beignet House',
        'Soya Republic',
        'Mbongo Chop House',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(self::COOK_NAMES);
        $slug = Str::slug($name);

        return [
            'name' => $name,
            'slug' => $slug,
            'custom_domain' => null,
            'is_active' => true,
            'settings' => [],
        ];
    }

    /**
     * Indicate the tenant is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a custom domain for the tenant.
     */
    public function withCustomDomain(string $domain): static
    {
        return $this->state(fn (array $attributes) => [
            'custom_domain' => $domain,
        ]);
    }

    /**
     * Set specific slug and name.
     */
    public function withSlug(string $slug, ?string $name = null): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => $slug,
            'name' => $name ?? Str::headline($slug),
        ]);
    }
}
