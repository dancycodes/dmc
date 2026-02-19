{{--
    Meal Component Requirement Rules Section
    -----------------------------------------
    F-122: Meal Component Requirement Rules

    Displays existing rules and provides a form to add new rules.
    This partial is included per-component inside _components.blade.php.

    Business Rules:
    BR-316: Three rule types: requires_any_of, requires_all_of, incompatible_with
    BR-317: Rules reference other components within the same meal only
    BR-318: A component can have multiple rules
    BR-320: Circular dependencies detected and prevented
    BR-321: Cleanup on target component deletion
    BR-323: Only manage-meals permission
    BR-324: Rule changes logged
    BR-325: Each rule must reference at least one target component

    Expected variables: $component, $rulesInfo (from $componentRulesData[$component->id])
--}}
@php
    $rules = $rulesInfo['rules'] ?? collect();
    $availableTargets = $rulesInfo['available_targets'] ?? collect();
    $hasTargets = $availableTargets->count() > 0;
@endphp

<div class="mt-3 border-t border-outline/50 dark:border-outline/50 pt-3">
    <div class="flex items-center justify-between mb-2">
        <h5 class="text-xs font-semibold text-on-surface/70 uppercase tracking-wide flex items-center gap-1.5">
            {{-- Lucide: link (xs=14) --}}
            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            {{ __('Requirement Rules') }}
        </h5>
        @if($hasTargets)
            <button
                type="button"
                @click="showRuleForm_{{ $component->id }} = !showRuleForm_{{ $component->id }}"
                class="text-xs font-medium text-primary hover:text-primary-hover transition-colors duration-200 flex items-center gap-1"
            >
                <svg x-show="!showRuleForm_{{ $component->id }}" class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <svg x-show="showRuleForm_{{ $component->id }}" x-cloak class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                <span x-text="showRuleForm_{{ $component->id }} ? '{{ __('Cancel') }}' : '{{ __('Add Rule') }}'"></span>
            </button>
        @endif
    </div>

    {{-- Existing rules display --}}
    @if($rules->count() > 0)
        <div class="space-y-2 mb-3">
            @foreach($rules as $rule)
                <div class="flex items-start justify-between gap-2 px-3 py-2 rounded-lg bg-surface-alt dark:bg-surface-alt border border-outline/30 dark:border-outline/30">
                    <div class="flex-1 min-w-0">
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide
                            @if($rule->rule_type === 'requires_any_of')
                                bg-info-subtle text-info
                            @elseif($rule->rule_type === 'requires_all_of')
                                bg-warning-subtle text-warning
                            @else
                                bg-danger-subtle text-danger
                            @endif
                        ">
                            {{ $rule->rule_type_label }}
                        </span>
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            @foreach($rule->targetComponents as $target)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-secondary-subtle text-secondary border border-secondary/20">
                                    {{ $target->name }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="$action('{{ url('/dashboard/meals/' . $component->meal_id . '/components/' . $component->id . '/rules/' . $rule->id) }}', { method: 'DELETE' })"
                        class="shrink-0 p-1 rounded text-on-surface/40 hover:text-danger hover:bg-danger-subtle transition-colors duration-200"
                        title="{{ __('Remove rule') }}"
                    >
                        {{-- Lucide: x (xs=14) --}}
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-xs text-on-surface/40 mb-2">{{ __('No requirement rules defined.') }}</p>
    @endif

    {{-- Add rule form --}}
    @if($hasTargets)
        <div
            x-show="showRuleForm_{{ $component->id }}"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
            class="p-3 rounded-lg border border-primary/20 bg-primary-subtle/20"
        >
            <form @submit.prevent="$action('{{ url('/dashboard/meals/' . $component->meal_id . '/components/' . $component->id . '/rules') }}')">
                {{-- Rule type selector --}}
                <div class="mb-3">
                    <label class="block text-xs font-medium text-on-surface-strong mb-1.5">
                        {{ __('Rule Type') }} <span class="text-danger">*</span>
                    </label>
                    <select
                        x-model="rule_type"
                        x-name="rule_type"
                        class="w-full px-3 py-1.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                    >
                        <option value="requires_any_of">{{ __('Requires any of') }}</option>
                        <option value="requires_all_of">{{ __('Requires all of') }}</option>
                        <option value="incompatible_with">{{ __('Incompatible with') }}</option>
                    </select>
                    <p x-message="rule_type" class="text-xs text-danger mt-1"></p>
                </div>

                {{-- Target components multi-select --}}
                <div class="mb-3">
                    <label class="block text-xs font-medium text-on-surface-strong mb-1.5">
                        {{ __('Target Components') }} <span class="text-danger">*</span>
                    </label>
                    <div class="space-y-1.5 max-h-40 overflow-y-auto rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface p-2">
                        @foreach($availableTargets as $target)
                            <label class="flex items-center gap-2 p-1.5 rounded hover:bg-surface-alt dark:hover:bg-surface-alt cursor-pointer transition-colors duration-200">
                                <input
                                    type="checkbox"
                                    value="{{ $target->id }}"
                                    x-model="rule_target_ids"
                                    class="w-4 h-4 rounded border-outline text-primary focus:ring-primary/30"
                                >
                                <span class="text-xs text-on-surface">{{ $target->name }}</span>
                                <span class="text-[10px] text-on-surface/40 ml-auto">{{ $target->formatted_price }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p x-message="rule_target_ids" class="text-xs text-danger mt-1"></p>
                </div>

                {{-- Rule type description --}}
                <div class="mb-3 p-2 rounded bg-info-subtle/30 border border-info/10">
                    <p class="text-[11px] text-info" x-show="rule_type === 'requires_any_of'">
                        {{-- Lucide: info (xs=14) --}}
                        <svg class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                        {{ __('Client must have at least one of the selected components in their order.') }}
                    </p>
                    <p class="text-[11px] text-info" x-show="rule_type === 'requires_all_of'" x-cloak>
                        <svg class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                        {{ __('Client must have all of the selected components in their order.') }}
                    </p>
                    <p class="text-[11px] text-info" x-show="rule_type === 'incompatible_with'" x-cloak>
                        <svg class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                        {{ __('Client cannot have any of the selected components if they want to order this component.') }}
                    </p>
                </div>

                {{-- Submit button --}}
                <div class="flex items-center justify-end">
                    <button
                        type="submit"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium bg-primary text-on-primary hover:bg-primary-hover shadow-sm transition-colors duration-200 flex items-center gap-1.5"
                    >
                        <span x-show="!$fetching()">
                            <svg class="w-3.5 h-3.5 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            {{ __('Add Rule') }}
                        </span>
                        <span x-show="$fetching()" x-cloak class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            {{ __('Adding...') }}
                        </span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
