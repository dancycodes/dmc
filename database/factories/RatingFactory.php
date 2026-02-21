<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Rating;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rating>
 */
class RatingFactory extends Factory
{
    protected $model = Rating::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->completed(),
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'stars' => fake()->numberBetween(Rating::MIN_STARS, Rating::MAX_STARS),
            'review' => null,
        ];
    }

    /**
     * State: with review text.
     */
    public function withReview(?string $review = null): static
    {
        return $this->state(fn (array $attributes) => [
            'review' => $review ?? fake()->randomElement([
                'Excellent food, will order again!',
                'Very tasty, delivery was fast.',
                'Good portions, reasonable price.',
                'Amazing ndole, just like mama makes.',
                'The jollof rice was delicious.',
            ]),
        ]);
    }

    /**
     * State: specific star rating.
     */
    public function stars(int $stars): static
    {
        return $this->state(fn (array $attributes) => [
            'stars' => max(Rating::MIN_STARS, min(Rating::MAX_STARS, $stars)),
        ]);
    }

    /**
     * State: high rating (4-5 stars).
     */
    public function highRating(): static
    {
        return $this->state(fn (array $attributes) => [
            'stars' => fake()->randomElement([4, 5]),
        ]);
    }

    /**
     * State: low rating (1-2 stars).
     */
    public function lowRating(): static
    {
        return $this->state(fn (array $attributes) => [
            'stars' => fake()->randomElement([1, 2]),
        ]);
    }
}
