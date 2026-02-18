{{--
    Meal Edit
    ---------
    F-110: Meal Edit

    Hub page for meal management with editable basic info (name, description)
    and sections for images (F-109), location override (F-096),
    schedule override (F-106), and future features.

    Business Rules:
    BR-210: Meal name required in both EN and FR
    BR-211: Meal description required in both EN and FR
    BR-212: Meal name unique within tenant per language
    BR-213: Name max 150 characters
    BR-214: Description max 2000 characters
    BR-215: Only users with can-manage-meals permission
    BR-216: Edits logged via Spatie Activitylog with old and new values
    BR-217: Editing does not change status or availability
--}}
@extends('layouts.cook-dashboard')

@section('title', $meal->name)
@section('page-title', __('Edit Meal'))

@section('content')
<div class="max-w-4xl mx-auto">
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
        <span class="text-on-surface-strong font-medium truncate">{{ $meal->name }}</span>
    </nav>

    {{-- Toast notification --}}
    @if(session('success'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="mb-6 p-4 rounded-lg bg-success-subtle border border-success/20 flex items-center gap-3"
        >
            <svg class="w-5 h-5 text-success shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <span class="text-sm text-on-surface">{{ session('success') }}</span>
        </div>
    @endif

    {{-- Meal header with status badge --}}
    <div class="flex items-start justify-between mb-6">
        <div>
            <h2 class="text-2xl font-display font-bold text-on-surface-strong">{{ $meal->name }}</h2>
            <p class="mt-1 text-sm text-on-surface/70">{{ __('Manage your meal details, images, and settings.') }}</p>
        </div>
        <span class="shrink-0 px-3 py-1 rounded-full text-xs font-medium {{ $meal->status === 'draft' ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success' }}">
            {{ $meal->status === 'draft' ? __('Draft') : __('Live') }}
        </span>
    </div>

    {{-- Basic Info Section (F-110) --}}
    <div
        class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-card p-6 mb-6"
        x-data="{
            name_en: {{ json_encode($meal->name_en) }},
            name_fr: {{ json_encode($meal->name_fr) }},
            description_en: {{ json_encode($meal->description_en) }},
            description_fr: {{ json_encode($meal->description_fr) }},
            activeTab: 'en'
        }"
        x-sync="['name_en', 'name_fr', 'description_en', 'description_fr']"
    >
        <div class="flex items-center gap-3 mb-5">
            <span class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center">
                {{-- Lucide: file-pen (md=20) --}}
                <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22h6a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v10"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10.4 12.6a2 2 0 1 1 3 3L8 21l-4 1 1-4Z"/></svg>
            </span>
            <h3 class="text-lg font-semibold text-on-surface-strong">{{ __('Basic Info') }}</h3>
        </div>

        <form @submit.prevent="$action('{{ url('/dashboard/meals/' . $meal->id) }}', { method: 'PUT' })">

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
                        <span class="w-6 h-6 rounded-full bg-primary-subtle flex items-center justify-center">
                            <span class="text-[10px] font-bold text-primary">EN</span>
                        </span>
                        <span class="text-sm font-medium text-on-surface-strong">{{ __('English') }}</span>
                    </div>

                    {{-- Name EN --}}
                    <div class="mb-4">
                        <label for="edit_name_en" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                            {{ __('Meal Name') }} <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            id="edit_name_en"
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
                        <label for="edit_description_en" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                            {{ __('Description') }} <span class="text-danger">*</span>
                        </label>
                        <textarea
                            id="edit_description_en"
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
                        <span class="w-6 h-6 rounded-full bg-secondary-subtle flex items-center justify-center">
                            <span class="text-[10px] font-bold text-secondary">FR</span>
                        </span>
                        <span class="text-sm font-medium text-on-surface-strong">{{ __('French') }}</span>
                    </div>

                    {{-- Name FR --}}
                    <div class="mb-4">
                        <label for="edit_name_fr" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                            {{ __('Meal Name') }} <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            id="edit_name_fr"
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
                        <label for="edit_description_fr" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                            {{ __('Description') }} <span class="text-danger">*</span>
                        </label>
                        <textarea
                            id="edit_description_fr"
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

            {{-- Save button --}}
            <div class="flex items-center justify-end mt-6 pt-5 border-t border-outline dark:border-outline">
                <button
                    type="submit"
                    class="px-6 py-2.5 rounded-lg text-sm font-medium bg-primary text-on-primary hover:bg-primary-hover shadow-sm transition-colors duration-200 flex items-center gap-2"
                >
                    <span x-show="!$fetching()">
                        {{-- Lucide: save (sm=16) --}}
                        <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        {{ __('Save Changes') }}
                    </span>
                    <span x-show="$fetching()" x-cloak class="flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        {{ __('Saving...') }}
                    </span>
                </button>
            </div>
        </form>
    </div>

    {{-- F-096: Location Override Section --}}
    @if($canManageLocations && $locationData)
        @include('cook.meals._location-override')
    @endif

    {{-- F-106: Schedule Override Section --}}
    @if($canManageSchedules && $scheduleData)
        @include('cook.meals._schedule-override')
    @endif

    {{-- F-109: Meal Image Upload & Carousel --}}
    @if($canManageMeals)
        <div class="mt-6">
            @include('cook.meals._image-upload')
        </div>
    @endif

    {{-- Placeholder sections for future features --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
        {{-- Components section (F-118) --}}
        <div class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-card p-6 opacity-60">
            <h3 class="text-base font-semibold text-on-surface-strong mb-2">{{ __('Components') }}</h3>
            <p class="text-sm text-on-surface/70">{{ __('Meal components will be available soon.') }}</p>
        </div>

        {{-- Tags section (F-114) --}}
        <div class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-card p-6 opacity-60">
            <h3 class="text-base font-semibold text-on-surface-strong mb-2">{{ __('Tags') }}</h3>
            <p class="text-sm text-on-surface/70">{{ __('Tag assignment will be available soon.') }}</p>
        </div>
    </div>

    {{-- Back to meals --}}
    <div class="mt-6 flex items-center">
        <a
            href="{{ url('/dashboard/meals') }}"
            class="text-sm text-primary hover:text-primary-hover font-medium transition-colors duration-200 flex items-center gap-1"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
            {{ __('Back to Meals') }}
        </a>
    </div>
</div>
@endsection
