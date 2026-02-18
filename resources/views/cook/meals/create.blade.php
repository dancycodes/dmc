{{--
    Meal Creation Form
    ------------------
    F-108: Meal Creation Form

    Allows a cook or manager with can-manage-meals permission to create
    a new meal with bilingual name and description.

    Business Rules:
    BR-187: Meal name required in both EN and FR
    BR-188: Meal description required in both EN and FR
    BR-189: Meal name unique within tenant (per language)
    BR-190: New meals default to "draft" status
    BR-191: New meals default to "available" availability
    BR-194: Only users with can-manage-meals permission
    BR-195: Creation logged via Spatie Activitylog
    BR-196: Meal name max 150 characters
    BR-197: Meal description max 2000 characters
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Add Meal'))
@section('page-title', __('Add Meal'))

@section('content')
<div
    class="max-w-4xl mx-auto"
    x-data="{
        name_en: '',
        name_fr: '',
        description_en: '',
        description_fr: '',
        activeTab: 'en'
    }"
    x-sync="['name_en', 'name_fr', 'description_en', 'description_fr']"
>
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-6" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ url('/dashboard') }}" class="hover:text-primary transition-colors duration-200">
            {{ __('Dashboard') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <a href="{{ url('/dashboard/meals') }}" class="hover:text-primary transition-colors duration-200">
            {{ __('Meals') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <span class="text-on-surface-strong font-medium">{{ __('Add Meal') }}</span>
    </nav>

    {{-- Page header --}}
    <div class="mb-6">
        <h2 class="text-2xl font-display font-bold text-on-surface-strong">{{ __('Create New Meal') }}</h2>
        <p class="mt-1 text-sm text-on-surface/70">{{ __('Fill in the meal details in both English and French. You can add images, components, and tags after creation.') }}</p>
    </div>

    {{-- Form card --}}
    <div class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-card p-6">
        <form @submit.prevent="$action('{{ url('/dashboard/meals') }}')">

            {{-- Language tabs for mobile --}}
            <div class="flex gap-1 p-1 bg-surface dark:bg-surface rounded-lg mb-6 sm:hidden">
                <button
                    type="button"
                    @click="activeTab = 'en'"
                    :class="activeTab === 'en' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface hover:bg-surface-alt'"
                    class="flex-1 px-3 py-2 rounded-md text-sm font-medium transition-all duration-200"
                >
                    {{ __('English') }}
                </button>
                <button
                    type="button"
                    @click="activeTab = 'fr'"
                    :class="activeTab === 'fr' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface hover:bg-surface-alt'"
                    class="flex-1 px-3 py-2 rounded-md text-sm font-medium transition-all duration-200"
                >
                    {{ __('French') }}
                </button>
            </div>

            {{-- Two-column layout on desktop, tabbed on mobile --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

                {{-- English section --}}
                <div :class="{ 'hidden sm:block': activeTab !== 'en' }">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center">
                            <span class="text-xs font-bold text-primary">EN</span>
                        </span>
                        <h3 class="text-base font-semibold text-on-surface-strong">{{ __('English') }}</h3>
                    </div>

                    {{-- Name EN --}}
                    <div class="mb-4">
                        <label for="name_en" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                            {{ __('Meal Name') }} <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            id="name_en"
                            x-model="name_en"
                            x-name="name_en"
                            maxlength="150"
                            placeholder="{{ __('e.g. Jollof Rice') }}"
                            class="w-full px-3 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                        >
                        <div class="flex items-center justify-between mt-1">
                            <p x-message="name_en" class="text-sm text-danger"></p>
                            <span class="text-xs text-on-surface/50" x-text="name_en.length + '/150'"></span>
                        </div>
                    </div>

                    {{-- Description EN --}}
                    <div class="mb-4">
                        <label for="description_en" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                            {{ __('Description') }} <span class="text-danger">*</span>
                        </label>
                        <textarea
                            id="description_en"
                            x-model="description_en"
                            x-name="description_en"
                            maxlength="2000"
                            rows="5"
                            placeholder="{{ __('Describe your meal, ingredients, and what makes it special...') }}"
                            class="w-full px-3 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 resize-y"
                        ></textarea>
                        <div class="flex items-center justify-between mt-1">
                            <p x-message="description_en" class="text-sm text-danger"></p>
                            <span class="text-xs text-on-surface/50" x-text="description_en.length + '/2000'"></span>
                        </div>
                    </div>
                </div>

                {{-- French section --}}
                <div :class="{ 'hidden sm:block': activeTab !== 'fr' }">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="w-8 h-8 rounded-full bg-secondary-subtle flex items-center justify-center">
                            <span class="text-xs font-bold text-secondary">FR</span>
                        </span>
                        <h3 class="text-base font-semibold text-on-surface-strong">{{ __('French') }}</h3>
                    </div>

                    {{-- Name FR --}}
                    <div class="mb-4">
                        <label for="name_fr" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                            {{ __('Meal Name') }} <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            id="name_fr"
                            x-model="name_fr"
                            x-name="name_fr"
                            maxlength="150"
                            placeholder="{{ __('e.g. Riz Jollof') }}"
                            class="w-full px-3 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                        >
                        <div class="flex items-center justify-between mt-1">
                            <p x-message="name_fr" class="text-sm text-danger"></p>
                            <span class="text-xs text-on-surface/50" x-text="name_fr.length + '/150'"></span>
                        </div>
                    </div>

                    {{-- Description FR --}}
                    <div class="mb-4">
                        <label for="description_fr" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                            {{ __('Description') }} <span class="text-danger">*</span>
                        </label>
                        <textarea
                            id="description_fr"
                            x-model="description_fr"
                            x-name="description_fr"
                            maxlength="2000"
                            rows="5"
                            placeholder="{{ __('Decrivez votre plat, les ingredients et ce qui le rend special...') }}"
                            class="w-full px-3 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 resize-y"
                        ></textarea>
                        <div class="flex items-center justify-between mt-1">
                            <p x-message="description_fr" class="text-sm text-danger"></p>
                            <span class="text-xs text-on-surface/50" x-text="description_fr.length + '/2000'"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Info box --}}
            <div class="mt-4 p-4 rounded-lg bg-info-subtle border border-info/20">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                    <p class="text-sm text-on-surface">
                        {{ __('Your meal will be saved as a draft. After creation, you can add images, components, set pricing, and manage availability from the edit page.') }}
                    </p>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 mt-6 pt-6 border-t border-outline dark:border-outline">
                <a
                    href="{{ url('/dashboard/meals') }}"
                    class="px-4 py-2.5 rounded-lg text-sm font-medium text-on-surface hover:bg-surface dark:hover:bg-surface transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </a>
                <button
                    type="submit"
                    class="px-6 py-2.5 rounded-lg text-sm font-medium bg-primary text-on-primary hover:bg-primary-hover shadow-sm transition-colors duration-200 flex items-center gap-2"
                >
                    <span x-show="!$fetching()">
                        <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        {{ __('Save Meal') }}
                    </span>
                    <span x-show="$fetching()" x-cloak class="flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        {{ __('Saving...') }}
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
