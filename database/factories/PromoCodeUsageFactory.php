<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PromoCodeUsage>
 */
class PromoCodeUsageFactory extends Factory
{
    protected $model = PromoCodeUsage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promo_code_id' => PromoCode::factory(),
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'discount_amount' => fake()->numberBetween(100, 5000),
        ];
    }
}
