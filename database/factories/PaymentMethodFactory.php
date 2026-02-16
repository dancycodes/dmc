<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /**
     * Common payment method labels.
     */
    private const LABELS = [
        'MTN Main',
        'Orange Personal',
        'MTN Business',
        'Orange Family',
        'MTN Secondary',
    ];

    /**
     * MTN MoMo phone number prefixes (Cameroon).
     */
    private const MTN_PREFIXES = ['67', '68', '650', '651', '652', '653', '654'];

    /**
     * Orange Money phone number prefixes (Cameroon).
     */
    private const ORANGE_PREFIXES = ['69', '655', '656', '657', '658', '659'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider = fake()->randomElement(PaymentMethod::PROVIDERS);

        return [
            'user_id' => User::factory(),
            'label' => fake()->unique()->randomElement(self::LABELS),
            'provider' => $provider,
            'phone' => $this->generatePhoneForProvider($provider),
            'is_default' => false,
        ];
    }

    /**
     * Set the payment method as default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Set the payment method user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Set provider to MTN MoMo.
     */
    public function mtnMomo(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => PaymentMethod::PROVIDER_MTN_MOMO,
            'phone' => $this->generatePhoneForProvider(PaymentMethod::PROVIDER_MTN_MOMO),
        ]);
    }

    /**
     * Set provider to Orange Money.
     */
    public function orangeMoney(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => PaymentMethod::PROVIDER_ORANGE_MONEY,
            'phone' => $this->generatePhoneForProvider(PaymentMethod::PROVIDER_ORANGE_MONEY),
        ]);
    }

    /**
     * Generate a valid Cameroon phone number for the given provider.
     */
    private function generatePhoneForProvider(string $provider): string
    {
        if ($provider === PaymentMethod::PROVIDER_MTN_MOMO) {
            $prefix = fake()->randomElement(self::MTN_PREFIXES);
        } else {
            $prefix = fake()->randomElement(self::ORANGE_PREFIXES);
        }

        // Generate remaining digits to make a 9-digit local number
        $remainingDigits = 9 - strlen($prefix);
        $suffix = '';
        for ($i = 0; $i < $remainingDigits; $i++) {
            $suffix .= fake()->randomDigit();
        }

        return '+237'.$prefix.$suffix;
    }
}
