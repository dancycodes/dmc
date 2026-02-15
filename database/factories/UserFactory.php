<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Cameroonian first names for realistic test data.
     */
    private const FIRST_NAMES = [
        'Amina', 'Bih', 'Caro', 'Derick', 'Emmanuel',
        'Fanka', 'Grace', 'Haman', 'Irene', 'Jean-Pierre',
        'Kenfack', 'Latifa', 'Marie', 'Ngono', 'Oumarou',
        'Powel', 'Ruth', 'Serge', 'Tabi', 'Yvette',
    ];

    /**
     * Cameroonian last names for realistic test data.
     */
    private const LAST_NAMES = [
        'Atangana', 'Biya', 'Che', 'Djoumessi', 'Etundi',
        'Fon', 'Guimdo', 'Happi', 'Issa', 'Jomba',
        'Kamga', 'Lobe', 'Mbarga', 'Ndam', 'Onana',
        'Pokam', 'Tchoupo', 'Wamba', 'Yemga', 'Zogo',
    ];

    /**
     * Valid Cameroonian phone prefixes (9 digits starting with 6).
     */
    private const PHONE_PREFIXES = [
        '65', '66', '67', '68', '69',
        '62', '65', '67', '69',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->randomElement(self::FIRST_NAMES);
        $lastName = fake()->randomElement(self::LAST_NAMES);
        $prefix = fake()->randomElement(self::PHONE_PREFIXES);

        return [
            'name' => $firstName.' '.$lastName,
            'email' => fake()->unique()->safeEmail(),
            'phone' => $prefix.fake()->numerify('#######'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'profile_photo_path' => null,
            'preferred_language' => 'en',
            'theme_preference' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user account is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set the user's preferred language.
     */
    public function withLanguage(string $language): static
    {
        return $this->state(fn (array $attributes) => [
            'preferred_language' => $language,
        ]);
    }

    /**
     * Set the user's phone number.
     */
    public function withPhone(string $phone): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => $phone,
        ]);
    }

    /**
     * Set the user's theme preference.
     */
    public function withTheme(?string $theme): static
    {
        return $this->state(fn (array $attributes) => [
            'theme_preference' => $theme,
        ]);
    }
}
