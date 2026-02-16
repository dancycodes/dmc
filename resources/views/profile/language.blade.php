{{--
    Language Preference Setting (F-042)
    ------------------------------------
    Allows authenticated users to set their preferred language (English or French).
    The preference is persisted to the user's database record and applied on the
    next page load. Radio button selection with auto-save via Gale.

    BR-184: Supported languages: "en" (English) and "fr" (French).
    BR-185: Stored in user's preferred_language field.
    BR-186: Application locale set on each request via middleware.
    BR-190: Default to English if not set.
    BR-191: Must stay in sync with language switcher component (F-008).
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Language Preference'))

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    {{-- Back Link --}}
    <div class="mb-6" x-data x-navigate>
        <a href="{{ url('/profile') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-on-surface hover:text-primary transition-colors">
            {{-- Arrow left icon (Lucide) --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m12 19-7-7 7-7"></path>
                <path d="M19 12H5"></path>
            </svg>
            {{ __('Back to Profile') }}
        </a>
    </div>

    {{-- Language Preference Card --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl shadow-card border border-outline overflow-hidden">
        {{-- Card Header --}}
        <div class="px-4 sm:px-6 py-5 border-b border-outline">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                    {{-- Languages icon (Lucide) --}}
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m5 8 6 6"></path>
                        <path d="m4 14 6-6 2-3"></path>
                        <path d="M2 5h12"></path>
                        <path d="M7 2h1"></path>
                        <path d="m22 22-5-10-5 10"></path>
                        <path d="M14 18h6"></path>
                    </svg>
                </span>
                <div>
                    <h1 class="text-lg sm:text-xl font-bold text-on-surface-strong font-display">
                        {{ __('Language Preference') }}
                    </h1>
                    <p class="text-sm text-on-surface mt-0.5">
                        {{ __('Choose the language for the entire application interface.') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Form --}}
        <div class="px-4 sm:px-6 py-6"
            x-data="{
                preferred_language: @js($currentLanguage),
                originalLanguage: @js($currentLanguage),
                get hasChanged() { return this.preferred_language !== this.originalLanguage; }
            }"
            x-sync
        >
            <form @submit.prevent="$action('{{ route('language.update') }}')" class="space-y-6">

                {{-- Current Language Info --}}
                <div class="flex items-center gap-2 px-3 py-2.5 rounded-lg bg-info-subtle border border-info/20">
                    {{-- Info icon (Lucide) --}}
                    <svg class="w-4 h-4 text-info shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 16v-4"></path>
                        <path d="M12 8h.01"></path>
                    </svg>
                    <p class="text-sm text-info">
                        {{ __('Your current language is :language. Changes will apply on the next page load.', ['language' => $currentLanguage === 'fr' ? __('French') : __('English')]) }}
                    </p>
                </div>

                {{-- Language Options --}}
                <fieldset>
                    <legend class="text-sm font-medium text-on-surface-strong mb-3">
                        {{ __('Select your preferred language') }}
                    </legend>
                    <div class="space-y-3">
                        {{-- English Option --}}
                        <label
                            class="flex items-center gap-4 p-4 rounded-xl border cursor-pointer transition-all duration-200"
                            :class="preferred_language === 'en'
                                ? 'border-primary bg-primary-subtle/50 shadow-sm'
                                : 'border-outline hover:border-primary/50 hover:bg-surface dark:hover:bg-surface'"
                        >
                            <input
                                type="radio"
                                name="preferred_language"
                                value="en"
                                x-model="preferred_language"
                                class="w-4.5 h-4.5 text-primary border-outline focus:ring-primary focus:ring-offset-0"
                            >
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <span class="w-10 h-10 rounded-full bg-surface border border-outline flex items-center justify-center shrink-0 text-sm font-bold text-on-surface-strong">
                                    EN
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-on-surface-strong">
                                        {{ __('English') }}
                                    </p>
                                    <p class="text-xs text-on-surface mt-0.5">
                                        {{ __('Display the application in English') }}
                                    </p>
                                </div>
                            </div>
                            {{-- Check icon when selected --}}
                            <div x-show="preferred_language === 'en'" x-cloak>
                                <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 6 9 17l-5-5"></path>
                                </svg>
                            </div>
                        </label>

                        {{-- French Option --}}
                        <label
                            class="flex items-center gap-4 p-4 rounded-xl border cursor-pointer transition-all duration-200"
                            :class="preferred_language === 'fr'
                                ? 'border-primary bg-primary-subtle/50 shadow-sm'
                                : 'border-outline hover:border-primary/50 hover:bg-surface dark:hover:bg-surface'"
                        >
                            <input
                                type="radio"
                                name="preferred_language"
                                value="fr"
                                x-model="preferred_language"
                                class="w-4.5 h-4.5 text-primary border-outline focus:ring-primary focus:ring-offset-0"
                            >
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <span class="w-10 h-10 rounded-full bg-surface border border-outline flex items-center justify-center shrink-0 text-sm font-bold text-on-surface-strong">
                                    FR
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-on-surface-strong">
                                        {{ __('French') }}
                                    </p>
                                    <p class="text-xs text-on-surface mt-0.5">
                                        {{ __('Display the application in French') }}
                                    </p>
                                </div>
                            </div>
                            {{-- Check icon when selected --}}
                            <div x-show="preferred_language === 'fr'" x-cloak>
                                <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 6 9 17l-5-5"></path>
                                </svg>
                            </div>
                        </label>
                    </div>
                    <p x-message="preferred_language" class="text-xs text-danger mt-2"></p>
                </fieldset>

                {{-- Sync Notice --}}
                <div class="flex items-start gap-2 px-3 py-2.5 rounded-lg bg-surface dark:bg-surface border border-outline">
                    {{-- Link icon (Lucide) --}}
                    <svg class="w-4 h-4 text-on-surface shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                    </svg>
                    <p class="text-xs text-on-surface">
                        {{ __('This setting stays in sync with the language switcher in the navigation bar. Changing one will update the other.') }}
                    </p>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
                    <a href="{{ url('/profile') }}" x-data x-navigate class="inline-flex items-center justify-center h-10 px-5 rounded-lg text-sm font-semibold border border-outline text-on-surface hover:bg-surface dark:hover:bg-surface transition-all duration-200">
                        {{ __('Cancel') }}
                    </a>
                    <button
                        type="submit"
                        :disabled="!hasChanged"
                        class="inline-flex items-center justify-center h-10 px-6 rounded-lg text-sm font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
                    >
                        <span x-show="!$fetching()">{{ __('Save Language') }}</span>
                        <span x-show="$fetching()" x-cloak class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin-slow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ __('Saving...') }}
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
