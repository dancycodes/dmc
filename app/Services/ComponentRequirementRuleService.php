<?php

namespace App\Services;

use App\Models\ComponentRequirementRule;
use App\Models\MealComponent;
use Illuminate\Support\Facades\DB;

class ComponentRequirementRuleService
{
    /**
     * Get all rules for a component with their target components eagerly loaded.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ComponentRequirementRule>
     */
    public function getRulesForComponent(MealComponent $component): \Illuminate\Database\Eloquent\Collection
    {
        return $component->requirementRules()
            ->with('targetComponents')
            ->get();
    }

    /**
     * Get available target components for a rule (same meal, excluding self).
     *
     * BR-317: Rules reference other components within the same meal only.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, MealComponent>
     */
    public function getAvailableTargets(MealComponent $component): \Illuminate\Database\Eloquent\Collection
    {
        return MealComponent::where('meal_id', $component->meal_id)
            ->where('id', '!=', $component->id)
            ->ordered()
            ->get();
    }

    /**
     * Create a new requirement rule.
     *
     * BR-316: Three rule types
     * BR-317: Rules reference other components within the same meal only
     * BR-320: Circular dependencies are detected and prevented
     * BR-325: Each rule must reference at least one target component
     *
     * @param  array{rule_type: string, target_component_ids: array<int>}  $data
     * @return array{success: bool, rule?: ComponentRequirementRule, error?: string}
     */
    public function createRule(MealComponent $component, array $data): array
    {
        $ruleType = $data['rule_type'];
        $targetIds = array_map('intval', $data['target_component_ids']);

        // Validate rule type
        if (! in_array($ruleType, ComponentRequirementRule::VALID_RULE_TYPES)) {
            return [
                'success' => false,
                'error' => __('Invalid rule type.'),
            ];
        }

        // BR-325: At least one target
        if (empty($targetIds)) {
            return [
                'success' => false,
                'error' => __('At least one target component is required.'),
            ];
        }

        // Validate self-reference
        if (in_array($component->id, $targetIds)) {
            return [
                'success' => false,
                'error' => __('A component cannot reference itself in a rule.'),
            ];
        }

        // BR-317: All targets must be from the same meal
        $validTargetCount = MealComponent::where('meal_id', $component->meal_id)
            ->whereIn('id', $targetIds)
            ->count();

        if ($validTargetCount !== count($targetIds)) {
            return [
                'success' => false,
                'error' => __('All target components must belong to the same meal.'),
            ];
        }

        // BR-320: Circular dependency detection
        $circularCheck = $this->detectCircularDependency($component, $targetIds, $ruleType);
        if ($circularCheck !== null) {
            return [
                'success' => false,
                'error' => $circularCheck,
            ];
        }

        // Create rule within a transaction
        $rule = DB::transaction(function () use ($component, $ruleType, $targetIds) {
            $rule = ComponentRequirementRule::create([
                'meal_component_id' => $component->id,
                'rule_type' => $ruleType,
            ]);

            $rule->targetComponents()->attach($targetIds);

            return $rule;
        });

        $rule->load('targetComponents');

        return [
            'success' => true,
            'rule' => $rule,
        ];
    }

    /**
     * Delete a requirement rule.
     *
     * @return array{success: bool, error?: string}
     */
    public function deleteRule(ComponentRequirementRule $rule): array
    {
        DB::transaction(function () use ($rule) {
            // Pivot records cascade via FK, but explicit is clearer
            $rule->targetComponents()->detach();
            $rule->delete();
        });

        return ['success' => true];
    }

    /**
     * Clean up all rules related to a component (both as subject and as target).
     *
     * BR-321: If a referenced component is deleted, rules referencing it are auto-removed.
     * Called from MealComponentService::deleteComponent().
     */
    public function cleanupRulesForComponent(MealComponent $component): void
    {
        DB::transaction(function () use ($component) {
            // Remove rules where this component is the subject
            $subjectRules = ComponentRequirementRule::where('meal_component_id', $component->id)->get();
            foreach ($subjectRules as $rule) {
                $rule->targetComponents()->detach();
                $rule->delete();
            }

            // Remove pivot entries where this component is a target
            DB::table('component_requirement_rule_targets')
                ->where('target_component_id', $component->id)
                ->delete();

            // BR-321: Remove rules that now have no targets
            $orphanedRules = ComponentRequirementRule::whereDoesntHave('targetComponents')->get();
            foreach ($orphanedRules as $rule) {
                $rule->delete();
            }
        });
    }

    /**
     * Detect circular dependencies.
     *
     * BR-320: Prevents cases like A requires B and B requires A.
     * Checks if any of the target components have rules that reference
     * the source component (direct circular dependency).
     */
    private function detectCircularDependency(MealComponent $component, array $targetIds, string $ruleType): ?string
    {
        // Only check for "requires" rules (incompatible_with is symmetric, not circular)
        if ($ruleType === ComponentRequirementRule::RULE_TYPE_INCOMPATIBLE_WITH) {
            return null;
        }

        // Check if any target component has a requires rule that references this component
        $circularRules = ComponentRequirementRule::whereIn('meal_component_id', $targetIds)
            ->whereIn('rule_type', [
                ComponentRequirementRule::RULE_TYPE_REQUIRES_ANY_OF,
                ComponentRequirementRule::RULE_TYPE_REQUIRES_ALL_OF,
            ])
            ->whereHas('targetComponents', function ($query) use ($component) {
                $query->where('target_component_id', $component->id);
            })
            ->with('component')
            ->first();

        if ($circularRules) {
            $targetName = $circularRules->component->name ?? 'Unknown';

            return __('Circular dependency detected: :target already requires this component.', [
                'target' => $targetName,
            ]);
        }

        return null;
    }
}
