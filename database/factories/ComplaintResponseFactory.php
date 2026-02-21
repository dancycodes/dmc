<?php

namespace Database\Factories;

use App\Models\Complaint;
use App\Models\ComplaintResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * F-184: Factory for ComplaintResponse model.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComplaintResponse>
 */
class ComplaintResponseFactory extends Factory
{
    protected $model = ComplaintResponse::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'complaint_id' => Complaint::factory(),
            'user_id' => User::factory(),
            'message' => fake()->paragraph(3),
            'resolution_type' => fake()->randomElement(ComplaintResponse::RESOLUTION_TYPES),
            'refund_amount' => null,
        ];
    }

    /**
     * State: apology only resolution.
     */
    public function apologyOnly(): static
    {
        return $this->state(fn () => [
            'resolution_type' => ComplaintResponse::RESOLUTION_APOLOGY_ONLY,
            'refund_amount' => null,
        ]);
    }

    /**
     * State: partial refund offer.
     */
    public function partialRefund(int $amount = 2000): static
    {
        return $this->state(fn () => [
            'resolution_type' => ComplaintResponse::RESOLUTION_PARTIAL_REFUND,
            'refund_amount' => $amount,
        ]);
    }

    /**
     * State: full refund offer.
     */
    public function fullRefund(int $amount = 5000): static
    {
        return $this->state(fn () => [
            'resolution_type' => ComplaintResponse::RESOLUTION_FULL_REFUND,
            'refund_amount' => $amount,
        ]);
    }
}
