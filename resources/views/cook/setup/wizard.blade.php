{{--
    Cook Setup Wizard Shell
    -----------------------
    F-071: Cook Setup Wizard Shell

    The wizard container with step progress bar, step navigation,
    step content area (placeholder for F-072 through F-075), and "Go Live" button.

    BR-108: 4 steps — Brand Info, Cover Images, Delivery Areas, Schedule & First Meal
    BR-109: Minimum requirements for Go Live: brand info + delivery area + active meal
    BR-111: Go Live button only enabled when requirements met
    BR-112: Completed steps have checkmarks and are clickable
    BR-114: Step progress persists across sessions
    BR-116: Wizard accessible after Go Live via settings
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Setup Wizard'))
@section('page-title', __('Setup Wizard'))

@section('content')
<div
    x-data="{
        activeStep: {{ $activeStep }},
        setupComplete: {{ $setupComplete ? 'true' : 'false' }},
        canGoLive: {{ $requirements['can_go_live'] ? 'true' : 'false' }},
        goLiveError: '',
        showGoLiveConfirm: false
    }"
    class="max-w-4xl mx-auto space-y-6"
>
    {{-- Wizard Header --}}
    <div class="text-center space-y-2">
        <h2 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
            @if($setupComplete)
                {{ __('Edit Your Setup') }}
            @else
                {{ __('Set Up Your Store') }}
            @endif
        </h2>
        <p class="text-on-surface text-sm sm:text-base">
            @if($setupComplete)
                {{ __('Your store is live. You can update any step below.') }}
            @else
                {{ __('Complete the steps below to get your store ready for customers.') }}
            @endif
        </p>
    </div>

    {{-- Step Progress Bar --}}
    @fragment('wizard-progress')
    <div id="wizard-progress" x-data x-navigate>
        {{-- Desktop progress bar --}}
        <nav class="hidden sm:flex items-center justify-between relative" aria-label="{{ __('Setup progress') }}">
            {{-- Connecting line --}}
            <div class="absolute top-6 left-0 right-0 h-0.5 bg-outline dark:bg-outline mx-16"></div>

            @foreach($steps as $step)
                @php
                    $isComplete = $step['complete'];
                    $isActive = $step['active'];
                    $isFuture = !$isComplete && !$isActive;
                    $isNavigable = $isComplete || $isActive || $setupComplete;
                @endphp
                <div class="relative flex flex-col items-center flex-1 z-10">
                    @if($isNavigable)
                        <a
                            href="{{ url('/dashboard/setup?step=' . $step['number']) }}"
                            x-navigate.key.wizard-step
                            class="flex flex-col items-center group"
                            aria-label="{{ __($step['title']) }}"
                        >
                    @else
                        <div class="flex flex-col items-center opacity-50 cursor-not-allowed">
                    @endif
                        {{-- Step circle --}}
                        <div class="w-12 h-12 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300
                            {{ $isComplete
                                ? 'bg-success text-on-success shadow-md'
                                : ($isActive
                                    ? 'bg-primary text-on-primary shadow-md ring-4 ring-primary/20'
                                    : 'bg-surface-alt text-on-surface border-2 border-outline dark:border-outline') }}
                        ">
                            @if($isComplete)
                                {{-- Checkmark icon (Lucide: check) --}}
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                            @else
                                {{ $step['number'] }}
                            @endif
                        </div>

                        {{-- Step title --}}
                        <span class="mt-2 text-xs sm:text-sm font-medium text-center leading-tight
                            {{ $isActive ? 'text-primary font-semibold' : ($isComplete ? 'text-on-surface-strong' : 'text-on-surface') }}
                        ">
                            {{ __($step['title']) }}
                        </span>
                    @if($isNavigable)
                        </a>
                    @else
                        </div>
                    @endif
                </div>
            @endforeach
        </nav>

        {{-- Mobile compact stepper --}}
        <nav class="sm:hidden" aria-label="{{ __('Setup progress') }}">
            <div class="flex items-center justify-center gap-2">
                @foreach($steps as $step)
                    @php
                        $isComplete = $step['complete'];
                        $isActive = $step['active'];
                        $isNavigable = $isComplete || $isActive || $setupComplete;
                    @endphp
                    @if($isNavigable)
                        <a
                            href="{{ url('/dashboard/setup?step=' . $step['number']) }}"
                            x-navigate.key.wizard-step
                            class="flex items-center gap-1"
                            aria-label="{{ __($step['title']) }}"
                        >
                    @else
                        <span class="flex items-center gap-1 opacity-50">
                    @endif
                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300
                            {{ $isComplete
                                ? 'bg-success text-on-success'
                                : ($isActive
                                    ? 'bg-primary text-on-primary ring-2 ring-primary/20'
                                    : 'bg-surface-alt text-on-surface border border-outline dark:border-outline') }}
                        ">
                            @if($isComplete)
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                            @else
                                {{ $step['number'] }}
                            @endif
                        </div>
                    @if($isNavigable)
                        </a>
                    @else
                        </span>
                    @endif

                    @if($step['number'] < 4)
                        <div class="w-6 h-0.5 {{ $isComplete ? 'bg-success' : 'bg-outline dark:bg-outline' }}"></div>
                    @endif
                @endforeach
            </div>

            {{-- Active step title on mobile --}}
            <p class="text-center mt-3 text-sm font-semibold text-primary">
                {{ __('Step') }} {{ $activeStep }}: {{ __($steps[$activeStep]['title']) }}
            </p>
        </nav>
    </div>
    @endfragment

    {{-- Step Content Area --}}
    @fragment('wizard-content')
    <div id="wizard-content" class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card p-4 sm:p-6 lg:p-8 min-h-[300px]">
        @switch($activeStep)
            @case(1)
                {{-- Step 1: Brand Info — Content provided by F-072 --}}
                <div class="text-center py-12 space-y-4">
                    <div class="w-16 h-16 mx-auto rounded-full bg-primary-subtle flex items-center justify-center">
                        {{-- Lucide: store --}}
                        <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"></path><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"></path><path d="M2 7h20"></path><path d="M22 7v3a2 2 0 0 1-2 2a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"></path></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-on-surface-strong">{{ __('Brand Info') }}</h3>
                    <p class="text-on-surface text-sm max-w-md mx-auto">
                        {{ __('Tell your customers about your brand. Add your store name and description in English and French.') }}
                    </p>
                    <p class="text-xs text-on-surface/60 italic">
                        {{ __('This step will be available in a future update.') }}
                    </p>
                </div>
                @break

            @case(2)
                {{-- Step 2: Cover Images — Content provided by F-073 --}}
                <div class="text-center py-12 space-y-4">
                    <div class="w-16 h-16 mx-auto rounded-full bg-secondary-subtle flex items-center justify-center">
                        {{-- Lucide: image --}}
                        <svg class="w-8 h-8 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-on-surface-strong">{{ __('Cover Images') }}</h3>
                    <p class="text-on-surface text-sm max-w-md mx-auto">
                        {{ __('Add attractive photos to showcase your food and kitchen. Great images help attract more customers.') }}
                    </p>
                    <p class="text-xs text-on-surface/60 italic">
                        {{ __('This step will be available in a future update.') }}
                    </p>
                </div>
                @break

            @case(3)
                {{-- Step 3: Delivery Areas — Content provided by F-074 --}}
                <div class="text-center py-12 space-y-4">
                    <div class="w-16 h-16 mx-auto rounded-full bg-info-subtle flex items-center justify-center">
                        {{-- Lucide: map-pin --}}
                        <svg class="w-8 h-8 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-on-surface-strong">{{ __('Delivery Areas') }}</h3>
                    <p class="text-on-surface text-sm max-w-md mx-auto">
                        {{ __('Set up the towns and quarters where you can deliver food, along with delivery fees.') }}
                    </p>
                    <p class="text-xs text-on-surface/60 italic">
                        {{ __('This step will be available in a future update.') }}
                    </p>
                </div>
                @break

            @case(4)
                {{-- Step 4: Schedule & First Meal — Content provided by F-075 --}}
                <div class="text-center py-12 space-y-4">
                    <div class="w-16 h-16 mx-auto rounded-full bg-warning-subtle flex items-center justify-center">
                        {{-- Lucide: calendar-plus --}}
                        <svg class="w-8 h-8 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><path d="M21 13V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8"></path><path d="M3 10h18"></path><path d="M16 19h6"></path><path d="M19 16v6"></path></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-on-surface-strong">{{ __('Schedule & First Meal') }}</h3>
                    <p class="text-on-surface text-sm max-w-md mx-auto">
                        {{ __('Set your operating schedule and create your first meal to start receiving orders.') }}
                    </p>
                    <p class="text-xs text-on-surface/60 italic">
                        {{ __('This step will be available in a future update.') }}
                    </p>
                </div>
                @break
        @endswitch

        {{-- Step Navigation Buttons --}}
        <div class="flex items-center justify-between mt-8 pt-6 border-t border-outline dark:border-outline">
            {{-- Previous / Skip --}}
            <div>
                @if($activeStep > 1)
                    <a
                        href="{{ url('/dashboard/setup?step=' . ($activeStep - 1)) }}"
                        x-navigate.key.wizard-step
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-on-surface hover:text-on-surface-strong hover:bg-surface rounded-lg transition-colors duration-200"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                        {{ __('Previous') }}
                    </a>
                @endif
            </div>

            <div class="flex items-center gap-3">
                {{-- Skip button (only for non-last steps and when not complete) --}}
                @if($activeStep < 4 && !$steps[$activeStep]['complete'])
                    <a
                        href="{{ url('/dashboard/setup?step=' . ($activeStep + 1)) }}"
                        x-navigate.key.wizard-step
                        class="inline-flex items-center gap-1 px-4 py-2.5 text-sm font-medium text-on-surface hover:text-on-surface-strong hover:bg-surface rounded-lg transition-colors duration-200"
                    >
                        {{ __('Skip') }}
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                    </a>
                @endif

                {{-- Next / Continue button (for steps that aren't the last step) --}}
                @if($activeStep < 4)
                    <a
                        href="{{ url('/dashboard/setup?step=' . ($activeStep + 1)) }}"
                        x-navigate.key.wizard-step
                        class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors duration-200 shadow-sm"
                    >
                        {{ __('Continue') }}
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                    </a>
                @endif
            </div>
        </div>
    </div>
    @endfragment

    {{-- Go Live Section --}}
    @if(!$setupComplete)
        <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card p-4 sm:p-6">
            {{-- Requirements Checklist --}}
            <h3 class="text-base font-semibold text-on-surface-strong mb-4">{{ __('Go Live Requirements') }}</h3>
            <div class="space-y-3 mb-6">
                {{-- Brand Info --}}
                <div class="flex items-center gap-3">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0
                        {{ $requirements['brand_info'] ? 'bg-success text-on-success' : 'bg-surface border border-outline dark:border-outline text-on-surface/40' }}">
                        @if($requirements['brand_info'])
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        @else
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle></svg>
                        @endif
                    </div>
                    <span class="text-sm {{ $requirements['brand_info'] ? 'text-on-surface-strong' : 'text-on-surface' }}">
                        {{ __('Brand info saved (name in English and French)') }}
                    </span>
                </div>

                {{-- Delivery Area --}}
                <div class="flex items-center gap-3">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0
                        {{ $requirements['delivery_area'] ? 'bg-success text-on-success' : 'bg-surface border border-outline dark:border-outline text-on-surface/40' }}">
                        @if($requirements['delivery_area'])
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        @else
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle></svg>
                        @endif
                    </div>
                    <span class="text-sm {{ $requirements['delivery_area'] ? 'text-on-surface-strong' : 'text-on-surface' }}">
                        {{ __('At least 1 delivery area (town with quarter and fee)') }}
                    </span>
                </div>

                {{-- Active Meal --}}
                <div class="flex items-center gap-3">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0
                        {{ $requirements['active_meal'] ? 'bg-success text-on-success' : 'bg-surface border border-outline dark:border-outline text-on-surface/40' }}">
                        @if($requirements['active_meal'])
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        @else
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle></svg>
                        @endif
                    </div>
                    <span class="text-sm {{ $requirements['active_meal'] ? 'text-on-surface-strong' : 'text-on-surface' }}">
                        {{ __('At least 1 active meal with components') }}
                    </span>
                </div>
            </div>

            {{-- Go Live Error Message --}}
            <template x-if="goLiveError">
                <div class="mb-4 p-3 rounded-lg bg-danger-subtle text-danger text-sm" x-text="goLiveError"></div>
            </template>

            {{-- Go Live Button --}}
            <button
                @click="canGoLive ? showGoLiveConfirm = true : null"
                :disabled="!canGoLive"
                :class="canGoLive
                    ? 'bg-primary text-on-primary hover:bg-primary-hover cursor-pointer shadow-md hover:shadow-lg'
                    : 'bg-surface text-on-surface/40 cursor-not-allowed border border-outline dark:border-outline'"
                class="w-full py-3.5 rounded-xl text-base font-bold transition-all duration-200 flex items-center justify-center gap-2"
            >
                {{-- Lucide: rocket --}}
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"></path><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"></path></svg>
                {{ __('Go Live') }}
            </button>

            @if(!$requirements['can_go_live'])
                <p class="mt-3 text-center text-xs text-on-surface/60">
                    {{ __('Complete all requirements above to enable the Go Live button.') }}
                </p>
            @endif
        </div>

        {{-- Go Live Confirmation Modal --}}
        <div
            x-show="showGoLiveConfirm"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
            @keydown.escape.window="showGoLiveConfirm = false"
            x-cloak
        >
            <div
                x-show="showGoLiveConfirm"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                @click.outside="showGoLiveConfirm = false"
                class="bg-surface dark:bg-surface rounded-xl shadow-dropdown border border-outline dark:border-outline p-6 max-w-md w-full"
            >
                <div class="text-center space-y-4">
                    {{-- Rocket icon --}}
                    <div class="w-14 h-14 mx-auto rounded-full bg-primary-subtle flex items-center justify-center">
                        <svg class="w-7 h-7 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"></path><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"></path></svg>
                    </div>

                    <h3 class="text-lg font-bold text-on-surface-strong">{{ __('Ready to Go Live?') }}</h3>
                    <p class="text-sm text-on-surface">
                        {{ __('Your store will become visible to customers. You can always update your setup later from the settings page.') }}
                    </p>

                    <div class="flex gap-3 pt-2">
                        <button
                            @click="showGoLiveConfirm = false"
                            class="flex-1 py-2.5 px-4 text-sm font-medium text-on-surface bg-surface-alt hover:bg-surface border border-outline dark:border-outline rounded-lg transition-colors duration-200"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            @click="showGoLiveConfirm = false; $action('{{ url('/dashboard/setup/go-live') }}')"
                            class="flex-1 py-2.5 px-4 text-sm font-bold text-on-primary bg-primary hover:bg-primary-hover rounded-lg transition-colors duration-200 shadow-sm"
                        >
                            <span x-show="!$fetching()">{{ __('Go Live') }}</span>
                            <span x-show="$fetching()" x-cloak>{{ __('Going Live...') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- Setup complete message --}}
        <div class="bg-success-subtle dark:bg-success-subtle rounded-xl border border-success/20 p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-success/20 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-on-surface-strong">{{ __('Your store is live!') }}</h3>
                    <p class="text-sm text-on-surface">{{ __('You can update any step above to make changes to your store setup.') }}</p>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
