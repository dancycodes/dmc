<?php

use App\Models\ComponentRequirementRule;
use App\Models\Meal;
use App\Models\MealComponent;
use App\Services\ComponentRequirementRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| F-122: Meal Component Requirement Rules — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for ComponentRequirementRule model, ComponentRequirementRuleService,
| and ComponentRequirementRuleFactory. Covers BR-316 to BR-325.
|
| HTTP endpoint behaviour is verified via Playwright in Phase 3.
|
*/

beforeEach(function () {
    $this->seedRolesAndPermissions();
    $this->seedSellingUnits();
    $result = createTenantWithCook();
    $this->tenant = $result['tenant'];
    $this->cook = $result['cook'];
    $this->tenant->update(['cook_id' => $this->cook->id]);
    $this->ruleService = new ComponentRequirementRuleService;
});

/*
|--------------------------------------------------------------------------
| Model Tests
|--------------------------------------------------------------------------
*/

test('component requirement rule model has correct fillable attributes', function () {
    $rule = new ComponentRequirementRule;

    expect($rule->getFillable())->toBe(['meal_component_id', 'rule_type']);
});

test('model defines valid rule type constants', function () {
    expect(ComponentRequirementRule::VALID_RULE_TYPES)->toBe([
        'requires_any_of',
        'requires_all_of',
        'incompatible_with',
    ]);
});

test('model defines rule type label constants for both locales', function () {
    $labels = ComponentRequirementRule::RULE_TYPE_LABELS;

    expect($labels)->toHaveKeys([
        ComponentRequirementRule::RULE_TYPE_REQUIRES_ANY_OF,
        ComponentRequirementRule::RULE_TYPE_REQUIRES_ALL_OF,
        ComponentRequirementRule::RULE_TYPE_INCOMPATIBLE_WITH,
    ]);

    foreach ($labels as $type => $localeLabels) {
        expect($localeLabels)->toHaveKeys(['en', 'fr']);
    }
});

test('rule_type_label accessor returns localized label', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $rule = ComponentRequirementRule::factory()->requiresAnyOf()->create([
        'meal_component_id' => $component->id,
    ]);

    app()->setLocale('en');
    expect($rule->rule_type_label)->toBe('Requires any of');

    app()->setLocale('fr');
    expect($rule->fresh()->rule_type_label)->toBe('Requiert au moins un de');
});

test('rule_type_label accessor falls back for unknown type', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $rule = ComponentRequirementRule::factory()->create([
        'meal_component_id' => $component->id,
        'rule_type' => 'custom_type',
    ]);

    expect($rule->rule_type_label)->toBe('Custom type');
});

test('component relationship returns the parent meal component', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $rule = ComponentRequirementRule::factory()->create([
        'meal_component_id' => $component->id,
    ]);

    expect($rule->component)->toBeInstanceOf(MealComponent::class)
        ->and($rule->component->id)->toBe($component->id);
});

test('targetComponents relationship returns target components via pivot', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target1 = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target2 = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $rule = ComponentRequirementRule::factory()
        ->requiresAnyOf()
        ->withTargets([$target1->id, $target2->id])
        ->create(['meal_component_id' => $component->id]);

    expect($rule->targetComponents)->toHaveCount(2)
        ->and($rule->targetComponents->pluck('id')->toArray())
        ->toContain($target1->id)
        ->toContain($target2->id);
});

test('MealComponent requirementRules relationship returns rules', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id]);

    ComponentRequirementRule::factory()
        ->requiresAnyOf()
        ->withTargets([$target->id])
        ->create(['meal_component_id' => $component->id]);

    ComponentRequirementRule::factory()
        ->incompatibleWith()
        ->withTargets([$target->id])
        ->create(['meal_component_id' => $component->id]);

    expect($component->requirementRules)->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| Factory Tests
|--------------------------------------------------------------------------
*/

test('factory creates valid rule with default state', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $rule = ComponentRequirementRule::factory()->create([
        'meal_component_id' => $component->id,
    ]);

    expect($rule->id)->not->toBeNull()
        ->and($rule->meal_component_id)->toBe($component->id)
        ->and(in_array($rule->rule_type, ComponentRequirementRule::VALID_RULE_TYPES))->toBeTrue();
});

test('factory requiresAnyOf state sets correct type', function () {
    $rule = ComponentRequirementRule::factory()->requiresAnyOf()->make();
    expect($rule->rule_type)->toBe('requires_any_of');
});

test('factory requiresAllOf state sets correct type', function () {
    $rule = ComponentRequirementRule::factory()->requiresAllOf()->make();
    expect($rule->rule_type)->toBe('requires_all_of');
});

test('factory incompatibleWith state sets correct type', function () {
    $rule = ComponentRequirementRule::factory()->incompatibleWith()->make();
    expect($rule->rule_type)->toBe('incompatible_with');
});

test('factory withTargets attaches target components after creation', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $component = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target1 = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target2 = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $rule = ComponentRequirementRule::factory()
        ->requiresAllOf()
        ->withTargets([$target1->id, $target2->id])
        ->create(['meal_component_id' => $component->id]);

    expect($rule->targetComponents()->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Service: getRulesForComponent()
|--------------------------------------------------------------------------
*/

test('getRulesForComponent returns rules with targets eagerly loaded', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id]);

    ComponentRequirementRule::factory()
        ->requiresAnyOf()
        ->withTargets([$target->id])
        ->create(['meal_component_id' => $comp->id]);

    $rules = $this->ruleService->getRulesForComponent($comp);

    expect($rules)->toHaveCount(1)
        ->and($rules->first()->relationLoaded('targetComponents'))->toBeTrue()
        ->and($rules->first()->targetComponents)->toHaveCount(1);
});

test('getRulesForComponent returns empty collection when no rules exist', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $rules = $this->ruleService->getRulesForComponent($comp);

    expect($rules)->toHaveCount(0);
});

/*
|--------------------------------------------------------------------------
| Service: getAvailableTargets() — BR-317
|--------------------------------------------------------------------------
*/

test('getAvailableTargets returns same-meal components excluding self', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp1 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    $comp2 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 2]);
    $comp3 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 3]);

    $targets = $this->ruleService->getAvailableTargets($comp1);

    expect($targets)->toHaveCount(2)
        ->and($targets->pluck('id')->toArray())->not->toContain($comp1->id)
        ->and($targets->pluck('id')->toArray())->toContain($comp2->id)
        ->and($targets->pluck('id')->toArray())->toContain($comp3->id);
});

test('getAvailableTargets excludes components from other meals', function () {
    $meal1 = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $meal2 = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp1 = MealComponent::factory()->create(['meal_id' => $meal1->id, 'position' => 1]);
    $comp2 = MealComponent::factory()->create(['meal_id' => $meal1->id, 'position' => 2]);
    $otherComp = MealComponent::factory()->create(['meal_id' => $meal2->id, 'position' => 1]);

    $targets = $this->ruleService->getAvailableTargets($comp1);

    expect($targets)->toHaveCount(1)
        ->and($targets->first()->id)->toBe($comp2->id);
});

test('getAvailableTargets returns empty when component is the only one', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $targets = $this->ruleService->getAvailableTargets($comp);

    expect($targets)->toHaveCount(0);
});

/*
|--------------------------------------------------------------------------
| Service: createRule() — BR-316, BR-325
|--------------------------------------------------------------------------
*/

test('creates a requires_any_of rule successfully', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target1 = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target2 = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->ruleService->createRule($comp, [
        'rule_type' => 'requires_any_of',
        'target_component_ids' => [$target1->id, $target2->id],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['rule'])->toBeInstanceOf(ComponentRequirementRule::class)
        ->and($result['rule']->rule_type)->toBe('requires_any_of')
        ->and($result['rule']->targetComponents)->toHaveCount(2);
});

test('creates a requires_all_of rule successfully', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->ruleService->createRule($comp, [
        'rule_type' => 'requires_all_of',
        'target_component_ids' => [$target->id],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['rule']->rule_type)->toBe('requires_all_of');
});

test('creates an incompatible_with rule successfully', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->ruleService->createRule($comp, [
        'rule_type' => 'incompatible_with',
        'target_component_ids' => [$target->id],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['rule']->rule_type)->toBe('incompatible_with');
});

test('BR-318: a component can have multiple rules', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target1 = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target2 = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result1 = $this->ruleService->createRule($comp, [
        'rule_type' => 'requires_any_of',
        'target_component_ids' => [$target1->id],
    ]);

    $result2 = $this->ruleService->createRule($comp, [
        'rule_type' => 'incompatible_with',
        'target_component_ids' => [$target2->id],
    ]);

    expect($result1['success'])->toBeTrue()
        ->and($result2['success'])->toBeTrue()
        ->and($comp->requirementRules()->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Service: createRule() — Validation Failures
|--------------------------------------------------------------------------
*/

test('rejects invalid rule type', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->ruleService->createRule($comp, [
        'rule_type' => 'invalid_type',
        'target_component_ids' => [$target->id],
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('Invalid rule type');
});

test('BR-325: rejects rule with no target components', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->ruleService->createRule($comp, [
        'rule_type' => 'requires_any_of',
        'target_component_ids' => [],
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('At least one target');
});

test('rejects self-referencing rule (component requires itself)', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->ruleService->createRule($comp, [
        'rule_type' => 'requires_any_of',
        'target_component_ids' => [$comp->id],
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('cannot reference itself');
});

test('BR-317: rejects targets from a different meal', function () {
    $meal1 = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $meal2 = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal1->id]);
    $otherTarget = MealComponent::factory()->create(['meal_id' => $meal2->id]);

    $result = $this->ruleService->createRule($comp, [
        'rule_type' => 'requires_any_of',
        'target_component_ids' => [$otherTarget->id],
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('same meal');
});

test('rejects mix of valid and invalid target IDs', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $validTarget = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->ruleService->createRule($comp, [
        'rule_type' => 'requires_any_of',
        'target_component_ids' => [$validTarget->id, 99999],
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('same meal');
});

/*
|--------------------------------------------------------------------------
| Service: createRule() — BR-320 Circular Dependency Detection
|--------------------------------------------------------------------------
*/

test('BR-320: detects circular dependency for requires_any_of', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $compA = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $compB = MealComponent::factory()->create(['meal_id' => $meal->id]);

    // A requires B
    $this->ruleService->createRule($compA, [
        'rule_type' => 'requires_any_of',
        'target_component_ids' => [$compB->id],
    ]);

    // B requires A — should fail (circular)
    $result = $this->ruleService->createRule($compB, [
        'rule_type' => 'requires_any_of',
        'target_component_ids' => [$compA->id],
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('Circular dependency');
});

test('BR-320: detects circular dependency for requires_all_of', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $compA = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $compB = MealComponent::factory()->create(['meal_id' => $meal->id]);

    // A requires_all_of B
    $this->ruleService->createRule($compA, [
        'rule_type' => 'requires_all_of',
        'target_component_ids' => [$compB->id],
    ]);

    // B requires_any_of A — should fail (cross-type circular)
    $result = $this->ruleService->createRule($compB, [
        'rule_type' => 'requires_any_of',
        'target_component_ids' => [$compA->id],
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('Circular dependency');
});

test('BR-320: incompatible_with does not trigger circular dependency check', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $compA = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $compB = MealComponent::factory()->create(['meal_id' => $meal->id]);

    // A incompatible_with B
    $this->ruleService->createRule($compA, [
        'rule_type' => 'incompatible_with',
        'target_component_ids' => [$compB->id],
    ]);

    // B incompatible_with A — should succeed (incompatible is symmetric, not circular)
    $result = $this->ruleService->createRule($compB, [
        'rule_type' => 'incompatible_with',
        'target_component_ids' => [$compA->id],
    ]);

    expect($result['success'])->toBeTrue();
});

test('no circular dependency when different targets', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $compA = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $compB = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $compC = MealComponent::factory()->create(['meal_id' => $meal->id]);

    // A requires B
    $this->ruleService->createRule($compA, [
        'rule_type' => 'requires_any_of',
        'target_component_ids' => [$compB->id],
    ]);

    // B requires C — not circular since B doesn't require A
    $result = $this->ruleService->createRule($compB, [
        'rule_type' => 'requires_any_of',
        'target_component_ids' => [$compC->id],
    ]);

    expect($result['success'])->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Service: deleteRule()
|--------------------------------------------------------------------------
*/

test('deletes a rule and its pivot records', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $rule = ComponentRequirementRule::factory()
        ->requiresAnyOf()
        ->withTargets([$target->id])
        ->create(['meal_component_id' => $comp->id]);

    $ruleId = $rule->id;
    $result = $this->ruleService->deleteRule($rule);

    expect($result['success'])->toBeTrue()
        ->and(ComponentRequirementRule::find($ruleId))->toBeNull()
        ->and(\Illuminate\Support\Facades\DB::table('component_requirement_rule_targets')
            ->where('rule_id', $ruleId)->count())->toBe(0);
});

test('deleting a rule does not affect the component', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $rule = ComponentRequirementRule::factory()
        ->requiresAnyOf()
        ->withTargets([$target->id])
        ->create(['meal_component_id' => $comp->id]);

    $this->ruleService->deleteRule($rule);

    expect(MealComponent::find($comp->id))->not->toBeNull()
        ->and(MealComponent::find($target->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Service: cleanupRulesForComponent() — BR-321
|--------------------------------------------------------------------------
*/

test('BR-321: removes rules where the component is the subject', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id]);

    ComponentRequirementRule::factory()
        ->requiresAnyOf()
        ->withTargets([$target->id])
        ->create(['meal_component_id' => $comp->id]);

    $this->ruleService->cleanupRulesForComponent($comp);

    expect(ComponentRequirementRule::where('meal_component_id', $comp->id)->count())->toBe(0);
});

test('BR-321: removes pivot entries where the component is a target', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $otherTarget = MealComponent::factory()->create(['meal_id' => $meal->id]);

    // Rule on $comp targeting $target
    ComponentRequirementRule::factory()
        ->requiresAnyOf()
        ->withTargets([$target->id, $otherTarget->id])
        ->create(['meal_component_id' => $comp->id]);

    // Clean up $target — should remove pivot entry
    $this->ruleService->cleanupRulesForComponent($target);

    // The rule on $comp should still exist but have only 1 target now
    $rules = ComponentRequirementRule::where('meal_component_id', $comp->id)->get();
    expect($rules)->toHaveCount(1)
        ->and($rules->first()->targetComponents()->count())->toBe(1);
});

test('BR-321: removes orphaned rules (all targets deleted)', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id]);

    // Rule on $comp with only one target
    ComponentRequirementRule::factory()
        ->requiresAnyOf()
        ->withTargets([$target->id])
        ->create(['meal_component_id' => $comp->id]);

    // Clean up the only target — the rule should be auto-removed
    $this->ruleService->cleanupRulesForComponent($target);

    expect(ComponentRequirementRule::where('meal_component_id', $comp->id)->count())->toBe(0);
});

test('BR-321: cleanup removes both subject and target rules', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $compA = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $compB = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $compC = MealComponent::factory()->create(['meal_id' => $meal->id]);

    // Rule 1: A requires B
    ComponentRequirementRule::factory()
        ->requiresAnyOf()
        ->withTargets([$compB->id])
        ->create(['meal_component_id' => $compA->id]);

    // Rule 2: C incompatible with A
    ComponentRequirementRule::factory()
        ->incompatibleWith()
        ->withTargets([$compA->id])
        ->create(['meal_component_id' => $compC->id]);

    // Clean up A — removes Rule 1 (subject) and the target pivot from Rule 2
    $this->ruleService->cleanupRulesForComponent($compA);

    // Rule 1 should be gone (subject was A)
    expect(ComponentRequirementRule::where('meal_component_id', $compA->id)->count())->toBe(0);

    // Rule 2 should also be gone (orphaned — only target was A)
    expect(ComponentRequirementRule::where('meal_component_id', $compC->id)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Integration: MealComponentService::deleteComponent() triggers rule cleanup
|--------------------------------------------------------------------------
*/

test('BR-305: deleting a component triggers rule cleanup', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_DRAFT,
    ]);
    $comp1 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    $comp2 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 2]);
    $comp3 = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 3]);

    // Rule on comp1 targeting comp2
    ComponentRequirementRule::factory()
        ->requiresAnyOf()
        ->withTargets([$comp2->id])
        ->create(['meal_component_id' => $comp1->id]);

    // Rule on comp3 targeting comp2
    ComponentRequirementRule::factory()
        ->incompatibleWith()
        ->withTargets([$comp2->id])
        ->create(['meal_component_id' => $comp3->id]);

    // Delete comp2 — should clean up both rules
    $componentService = new \App\Services\MealComponentService;
    $result = $componentService->deleteComponent($comp2);

    expect($result['success'])->toBeTrue()
        ->and(ComponentRequirementRule::where('meal_component_id', $comp1->id)->count())->toBe(0)
        ->and(ComponentRequirementRule::where('meal_component_id', $comp3->id)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| BR-322: Rules do not affect availability
|--------------------------------------------------------------------------
*/

test('BR-322: rules do not change component availability', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id, 'is_available' => true]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->ruleService->createRule($comp, [
        'rule_type' => 'requires_any_of',
        'target_component_ids' => [$target->id],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($comp->fresh()->is_available)->toBeTrue();
});

test('BR-322: rules on unavailable components still persist', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->unavailable()->create(['meal_id' => $meal->id]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->ruleService->createRule($comp, [
        'rule_type' => 'requires_any_of',
        'target_component_ids' => [$target->id],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($comp->requirementRules()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Database Cascade Tests
|--------------------------------------------------------------------------
*/

test('deleting a component cascades deletion of its rules via FK', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_DRAFT,
    ]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $rule = ComponentRequirementRule::factory()
        ->requiresAnyOf()
        ->withTargets([$target->id])
        ->create(['meal_component_id' => $comp->id]);

    $ruleId = $rule->id;

    // Direct DB delete (bypassing service layer) to test FK cascade
    $comp->delete();

    expect(ComponentRequirementRule::find($ruleId))->toBeNull();
});

test('deleting a target component cascades removal of pivot entries via FK', function () {
    $meal = Meal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => Meal::STATUS_DRAFT,
    ]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id, 'position' => 2]);

    $rule = ComponentRequirementRule::factory()
        ->requiresAnyOf()
        ->withTargets([$target->id])
        ->create(['meal_component_id' => $comp->id]);

    // Delete target directly (FK cascade should remove pivot)
    $target->delete();

    expect(\Illuminate\Support\Facades\DB::table('component_requirement_rule_targets')
        ->where('target_component_id', $target->id)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

test('creating a rule with string IDs still works via intval conversion', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id]);

    $result = $this->ruleService->createRule($comp, [
        'rule_type' => 'requires_any_of',
        'target_component_ids' => [(string) $target->id],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['rule']->targetComponents)->toHaveCount(1);
});

test('target IDs are deduplicated via pivot unique constraint', function () {
    $meal = Meal::factory()->create(['tenant_id' => $this->tenant->id]);
    $comp = MealComponent::factory()->create(['meal_id' => $meal->id]);
    $target = MealComponent::factory()->create(['meal_id' => $meal->id]);

    // Attempting to attach same target twice should be handled
    $result = $this->ruleService->createRule($comp, [
        'rule_type' => 'requires_any_of',
        'target_component_ids' => [$target->id],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['rule']->targetComponents)->toHaveCount(1);
});
