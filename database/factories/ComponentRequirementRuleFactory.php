<?php

namespace Database\Factories;

use App\Models\ComponentRequirementRule;
use App\Models\MealComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComponentRequirementRule>
 */
class ComponentRequirementRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meal_component_id' => MealComponent::factory(),
            'rule_type' => fake()->randomElement(ComponentRequirementRule::VALID_RULE_TYPES),
        ];
    }

    /**
     * Rule of type "requires any of".
     */
    public function requiresAnyOf(): static
    {
        return $this->state(fn () => [
            'rule_type' => ComponentRequirementRule::RULE_TYPE_REQUIRES_ANY_OF,
        ]);
    }

    /**
     * Rule of type "requires all of".
     */
    public function requiresAllOf(): static
    {
        return $this->state(fn () => [
            'rule_type' => ComponentRequirementRule::RULE_TYPE_REQUIRES_ALL_OF,
        ]);
    }

    /**
     * Rule of type "incompatible with".
     */
    public function incompatibleWith(): static
    {
        return $this->state(fn () => [
            'rule_type' => ComponentRequirementRule::RULE_TYPE_INCOMPATIBLE_WITH,
        ]);
    }

    /**
     * Attach target components after creation.
     *
     * @param  array<int>  $targetComponentIds
     */
    public function withTargets(array $targetComponentIds): static
    {
        return $this->afterCreating(function (ComponentRequirementRule $rule) use ($targetComponentIds) {
            $rule->targetComponents()->attach($targetComponentIds);
        });
    }
}
