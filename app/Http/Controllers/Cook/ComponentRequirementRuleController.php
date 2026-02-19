<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Services\ComponentRequirementRuleService;
use Illuminate\Http\Request;

class ComponentRequirementRuleController extends Controller
{
    /**
     * Store a new requirement rule.
     *
     * F-122: Meal Component Requirement Rules
     * BR-316: Three rule types
     * BR-317: Rules reference same-meal components only
     * BR-320: Circular dependency prevention
     * BR-323: Only users with manage-meals permission
     * BR-324: Rule changes logged
     * BR-325: At least one target required
     */
    public function store(Request $request, int $mealId, int $componentId, ComponentRequirementRuleService $ruleService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-323: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        // Meal must belong to current tenant
        $meal = $tenant->meals()->findOrFail($mealId);

        // Component must belong to meal
        $component = $meal->components()->findOrFail($componentId);

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'rule_type' => ['required', 'string', 'in:requires_any_of,requires_all_of,incompatible_with'],
                'rule_target_ids' => ['required', 'array', 'min:1'],
                'rule_target_ids.*' => ['required', 'integer'],
            ], [
                'rule_type.required' => __('Rule type is required.'),
                'rule_type.in' => __('Invalid rule type.'),
                'rule_target_ids.required' => __('At least one target component is required.'),
                'rule_target_ids.min' => __('At least one target component is required.'),
            ]);

            $data = [
                'rule_type' => $validated['rule_type'],
                'target_component_ids' => $validated['rule_target_ids'],
            ];
        } else {
            $validated = $request->validate([
                'rule_type' => ['required', 'string', 'in:requires_any_of,requires_all_of,incompatible_with'],
                'target_component_ids' => ['required', 'array', 'min:1'],
                'target_component_ids.*' => ['required', 'integer'],
            ]);

            $data = $validated;
        }

        $result = $ruleService->createRule($component, $data);

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'rule_type' => $result['error'],
                ]);
            }

            return redirect()->back()
                ->withErrors(['rule_type' => $result['error']])
                ->withInput();
        }

        $rule = $result['rule'];

        // BR-324: Activity logging
        $targetNames = $rule->targetComponents->map(fn ($c) => $c->name_en)->implode(', ');
        activity('meal_components')
            ->performedOn($component)
            ->causedBy($user)
            ->withProperties([
                'action' => 'requirement_rule_created',
                'rule_type' => $rule->rule_type,
                'target_components' => $targetNames,
                'component_name' => $component->name_en,
                'meal_id' => $meal->id,
                'tenant_id' => $tenant->id,
            ])
            ->log('Requirement rule created');

        $redirectUrl = url('/dashboard/meals/'.$meal->id.'/edit');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', __('Requirement rule added.'));
        }

        return redirect($redirectUrl)
            ->with('success', __('Requirement rule added.'));
    }

    /**
     * Delete a requirement rule.
     *
     * F-122: Meal Component Requirement Rules
     * BR-323: Only users with manage-meals permission
     * BR-324: Rule changes logged
     */
    public function destroy(Request $request, int $mealId, int $componentId, int $ruleId, ComponentRequirementRuleService $ruleService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-323: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        // Meal must belong to current tenant
        $meal = $tenant->meals()->findOrFail($mealId);

        // Component must belong to meal
        $component = $meal->components()->findOrFail($componentId);

        // Rule must belong to component
        $rule = $component->requirementRules()->with('targetComponents')->findOrFail($ruleId);

        // Capture data for activity logging before deletion
        $ruleData = [
            'rule_type' => $rule->rule_type,
            'target_components' => $rule->targetComponents->map(fn ($c) => $c->name_en)->implode(', '),
        ];

        $result = $ruleService->deleteRule($rule);

        if (! $result['success']) {
            $redirectUrl = url('/dashboard/meals/'.$meal->id.'/edit');

            if ($request->isGale()) {
                return gale()
                    ->redirect($redirectUrl)
                    ->with('error', $result['error']);
            }

            return redirect($redirectUrl)
                ->with('error', $result['error']);
        }

        // BR-324: Activity logging
        activity('meal_components')
            ->performedOn($component)
            ->causedBy($user)
            ->withProperties([
                'action' => 'requirement_rule_deleted',
                'rule_type' => $ruleData['rule_type'],
                'target_components' => $ruleData['target_components'],
                'component_name' => $component->name_en,
                'meal_id' => $meal->id,
                'tenant_id' => $tenant->id,
            ])
            ->log('Requirement rule deleted');

        $redirectUrl = url('/dashboard/meals/'.$meal->id.'/edit');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', __('Requirement rule removed.'));
        }

        return redirect($redirectUrl)
            ->with('success', __('Requirement rule removed.'));
    }
}
