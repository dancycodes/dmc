<?php

namespace Database\Factories;

use App\Models\PromoCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PromoCode>
 */
class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $discountType = fake()->randomElement([PromoCode::TYPE_PERCENTAGE, PromoCode::TYPE_FIXED]);

        return [
            'tenant_id' => Tenant::factory(),
            'created_by' => User::factory(),
            'code' => strtoupper(fake()->unique()->bothify('????##')),
            'discount_type' => $discountType,
            'discount_value' => $discountType === PromoCode::TYPE_PERCENTAGE
                ? fake()->numberBetween(1, 100)
                : fake()->numberBetween(100, 5000),
            'minimum_order_amount' => fake()->randomElement([0, 500, 1000, 2000]),
            'max_uses' => fake()->randomElement([0, 10, 50, 100]),
            'max_uses_per_client' => fake()->randomElement([0, 1, 2, 5]),
            'times_used' => 0,
            'starts_at' => now()->toDateString(),
            'ends_at' => fake()->optional(0.7)->dateTimeBetween('+1 day', '+30 days')?->format('Y-m-d'),
            'status' => PromoCode::STATUS_ACTIVE,
        ];
    }

    /**
     * Active percentage promo code.
     */
    public function percentage(int $value = 10): static
    {
        return $this->state([
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => $value,
        ]);
    }

    /**
     * Fixed amount promo code.
     */
    public function fixed(int $value = 500): static
    {
        return $this->state([
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => $value,
        ]);
    }

    /**
     * Unlimited usage promo code.
     */
    public function unlimited(): static
    {
        return $this->state([
            'max_uses' => 0,
            'max_uses_per_client' => 0,
            'ends_at' => null,
        ]);
    }

    /**
     * Inactive promo code.
     */
    public function inactive(): static
    {
        return $this->state([
            'status' => PromoCode::STATUS_INACTIVE,
        ]);
    }
}
