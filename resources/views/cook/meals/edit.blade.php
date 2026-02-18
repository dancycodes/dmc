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

    F-111: Added delete button with confirmation modal.
    F-112: Added status toggle button (Draft/Live).
--}}
@extends('layouts.cook-dashboard')

@section('title', $meal->name)
@section('page-title', __('Edit Meal'))

@section('content')
<div
    class="max-w-4xl mx-auto"
    x-data="{
        showDeleteModal: false,
        confirmDelete() {
            this.showDeleteModal = true;
        },
        cancelDelete() {
            this.showDeleteModal = false;
        },
        executeDelete() {
            $action('{{ url('/dashboard/meals/' . $meal->id) }}', { method: 'DELETE' });
            this.showDeleteModal = false;
        }
    }"
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
        <span class="text-on-surface-strong font-medium truncate">{{ $meal->name }}</span>
    </nav>

    {{-- Toast notifications --}}
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

    @if(session('error'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 7000)"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="mb-6 p-4 rounded-lg bg-danger-subtle border border-danger/20 flex items-center gap-3"
        >
            <svg class="w-5 h-5 text-danger shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <span class="text-sm text-on-surface">{{ session('error') }}</span>
        </div>
    @endif

    {{-- Meal header with status badge, toggle, and delete button --}}
    <div class="flex items-start justify-between mb-6">
        <div>
            <h2 class="text-2xl font-display font-bold text-on-surface-strong">{{ $meal->name }}</h2>
            <p class="mt-1 text-sm text-on-surface/70">{{ __('Manage your meal details, images, and settings.') }}</p>
        </div>
        <div class="flex items-center gap-3">
            {{-- F-112: Status toggle button --}}
            @if($meal->isDraft())
                <button
                    type="button"
                    @click="$action('{{ url('/dashboard/meals/' . $meal->id . '/toggle-status') }}', { method: 'PATCH' })"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium bg-success text-on-success hover:bg-success/90 shadow-sm transition-colors duration-200 flex items-center gap-1.5"
                    title="{{ __('Publish this meal to make it visible to clients') }}"
                >
                    {{-- Lucide: rocket (xs=14) --}}
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"></path><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"></path></svg>
                    {{ __('Go Live') }}
                </button>
            @else
                <button
                    type="button"
                    @click="$action('{{ url('/dashboard/meals/' . $meal->id . '/toggle-status') }}', { method: 'PATCH' })"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium bg-warning-subtle text-warning border border-warning/30 hover:bg-warning/10 transition-colors duration-200 flex items-center gap-1.5"
                    title="{{ __('Move this meal back to draft status') }}"
                >
                    {{-- Lucide: pencil-line (xs=14) --}}
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.855z"></path><path d="m15 5 3 3"></path></svg>
                    {{ __('Unpublish') }}
                </button>
            @endif

            <span class="shrink-0 px-3 py-1 rounded-full text-xs font-medium {{ $meal->status === 'draft' ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success' }}">
                {{ $meal->status === 'draft' ? __('Draft') : __('Live') }}
            </span>

            {{-- F-111: Delete button --}}
            @if($canDeleteInfo['can_delete'])
                <button
                    type="button"
                    @click="confirmDelete()"
                    class="p-2 rounded-lg text-on-surface/50 hover:text-danger hover:bg-danger-subtle transition-colors duration-200"
                    title="{{ __('Delete meal') }}"
                >
                    {{-- Lucide: trash-2 (md=20) --}}
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                </button>
            @else
                <span
                    class="p-2 rounded-lg text-on-surface/20 cursor-not-allowed"
                    title="{{ $canDeleteInfo['reason'] ?? __('Cannot delete this meal') }}"
                >
                    {{-- Lucide: trash-2 (md=20) --}}
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                </span>
            @endif
        </div>
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

    {{-- F-111: Delete Confirmation Modal --}}
    @if($canDeleteInfo['can_delete'])
        <div
            x-show="showDeleteModal"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Delete meal') }}"
        >
            {{-- Backdrop --}}
            <div
                x-show="showDeleteModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="cancelDelete()"
                class="absolute inset-0 bg-black/50"
            ></div>

            {{-- Modal content --}}
            <div
                x-show="showDeleteModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative w-full max-w-md bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-lg p-6"
            >
                {{-- Warning icon --}}
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-danger-subtle mx-auto mb-4">
                    <svg class="w-6 h-6 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                </div>

                <h3 class="text-lg font-semibold text-on-surface-strong text-center mb-2">
                    {{ __('Delete Meal') }}
                </h3>

                <p class="text-sm text-on-surface/70 text-center mb-2">
                    {{ __('Are you sure you want to delete') }}
                    <span class="font-semibold text-on-surface-strong">{{ $meal->name }}</span>?
                </p>

                <p class="text-sm text-on-surface/70 text-center mb-1">
                    {{ __('This will remove it from your menu.') }}
                </p>

                @if($completedOrders > 0)
                    <p class="text-sm text-info text-center mb-4">
                        {{ __('This meal has :count past orders. Order history will be preserved.', ['count' => $completedOrders]) }}
                    </p>
                @else
                    <div class="mb-4"></div>
                @endif

                {{-- Action buttons --}}
                <div class="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="cancelDelete()"
                        class="px-4 py-2 rounded-lg text-sm font-medium text-on-surface bg-surface dark:bg-surface border border-outline dark:border-outline hover:bg-surface-alt transition-colors duration-200"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="button"
                        @click="executeDelete()"
                        class="px-4 py-2 rounded-lg text-sm font-medium bg-danger text-on-danger hover:bg-danger/90 shadow-sm transition-colors duration-200 flex items-center gap-2"
                    >
                        {{-- Lucide: trash-2 (sm=16) --}}
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                        {{ __('Delete') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
