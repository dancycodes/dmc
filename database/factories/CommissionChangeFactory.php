<?php

namespace Database\Factories;

use App\Models\CommissionChange;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CommissionChange>
 */
class CommissionChangeFactory extends Factory
{
    protected $model = CommissionChange::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $oldRate = $this->faker->randomFloat(1, CommissionChange::MIN_RATE, CommissionChange::MAX_RATE);
        $newRate = $this->faker->randomFloat(1, CommissionChange::MIN_RATE, CommissionChange::MAX_RATE);

        // Snap to 0.5 increments
        $oldRate = round($oldRate * 2) / 2;
        $newRate = round($newRate * 2) / 2;

        return [
            'tenant_id' => Tenant::factory(),
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
            'changed_by' => User::factory(),
            'reason' => $this->faker->optional(0.7)->sentence(),
        ];
    }

    /**
     * State: reset to default rate.
     */
    public function resetToDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'new_rate' => CommissionChange::DEFAULT_RATE,
            'reason' => 'Reset to platform default',
        ]);
    }

    /**
     * State: from default rate.
     */
    public function fromDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'old_rate' => CommissionChange::DEFAULT_RATE,
        ]);
    }
}
