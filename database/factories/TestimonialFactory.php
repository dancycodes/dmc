<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * F-180: Testimonial factory for testing.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    /**
     * Sample testimonial texts with Cameroonian context.
     *
     * @var array<string>
     */
    private const TESTIMONIAL_TEXTS = [
        "I've been ordering from this cook for months. The ndole is the best in town! Always on time, always fresh. Highly recommended!",
        'Amazing food and excellent service. The eru and fufu are perfectly prepared. My whole family loves it. Will keep ordering!',
        'Best home-cooked meals in the neighbourhood. The jollof rice is out of this world. Delivery is always punctual.',
        'I was skeptical at first but now I am a loyal customer. The quality is consistent and the portions are generous.',
        'Excellent cook! The okok with plantains is incredible. Very professional, always communicates about delivery time.',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'text' => fake()->randomElement(self::TESTIMONIAL_TEXTS),
            'status' => Testimonial::STATUS_PENDING,
        ];
    }

    /**
     * State for an approved testimonial.
     */
    public function approved(): static
    {
        return $this->state(['status' => Testimonial::STATUS_APPROVED]);
    }

    /**
     * State for a rejected testimonial.
     */
    public function rejected(): static
    {
        return $this->state(['status' => Testimonial::STATUS_REJECTED]);
    }

    /**
     * State for a pending testimonial.
     */
    public function pending(): static
    {
        return $this->state(['status' => Testimonial::STATUS_PENDING]);
    }
}
