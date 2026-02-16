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
    /**
     * French translations for cook names.
     */
    private const COOK_NAMES_FR = [
        'La Cuisine de Latifa',
        'Les Plats de Mama Ngono',
        'Chef Powel Cuisine',
        'Cuisine de Tante Bih',
        'Iya Bastos Repas',
        'Reine du Ndoleh',
        'Chef Tabi Cuisine',
        'Mama Caro Repas',
        'Langue de Feu',
        'Chez Mariette Cuisine',
        'Palais du Kondre',
        'Maîtres du Eru',
        'Maison du Beignet',
        'République du Soya',
        'Chop House Mbongo',
    ];

    public function definition(): array
    {
        $index = array_rand(self::COOK_NAMES);
        $name = self::COOK_NAMES[$index];
        $slug = Str::slug($name);

        return [
            'name_en' => $name,
            'name_fr' => self::COOK_NAMES_FR[$index] ?? $name,
            'slug' => $slug,
            'custom_domain' => null,
            'description_en' => null,
            'description_fr' => null,
            'is_active' => true,
            'settings' => [],
        ];
    }

    /**
     * Assign a cook (user) to the tenant.
     */
    public function withCook(int $cookId): static
    {
        return $this->state(fn (array $attributes) => [
            'cook_id' => $cookId,
        ]);
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
            'name_en' => $name ?? Str::headline($slug),
            'name_fr' => $name ?? Str::headline($slug),
        ]);
    }

    /**
     * Set the tenant's theme preset.
     */
    public function withThemePreset(string $preset): static
    {
        return $this->state(function (array $attributes) use ($preset) {
            $settings = $attributes['settings'] ?? [];
            $settings['theme'] = $preset;

            return ['settings' => $settings];
        });
    }

    /**
     * Set the tenant's font.
     */
    public function withFont(string $font): static
    {
        return $this->state(function (array $attributes) use ($font) {
            $settings = $attributes['settings'] ?? [];
            $settings['font'] = $font;

            return ['settings' => $settings];
        });
    }

    /**
     * Set the tenant's border radius.
     */
    public function withBorderRadius(string $radius): static
    {
        return $this->state(function (array $attributes) use ($radius) {
            $settings = $attributes['settings'] ?? [];
            $settings['border_radius'] = $radius;

            return ['settings' => $settings];
        });
    }

    /**
     * Set a complete theme customization (preset, font, and radius).
     */
    public function withTheme(string $preset, string $font = 'inter', string $radius = 'medium'): static
    {
        return $this->state(function (array $attributes) use ($preset, $font, $radius) {
            $settings = $attributes['settings'] ?? [];
            $settings['theme'] = $preset;
            $settings['font'] = $font;
            $settings['border_radius'] = $radius;

            return ['settings' => $settings];
        });
    }
}
