{{--
    Profile Basic Info Edit (F-032)
    --------------------------------
    Allows authenticated users to edit their basic profile info:
    name, phone, and preferred language. Email is read-only (BR-114).
    Form submits via Gale without page reload (BR-116).
    Toast notification on success. Activity logged (BR-117).

    BR-112: Name required, 2-255 characters.
    BR-113: Phone must match Cameroon format (+237XXXXXXXXX).
    BR-114: Email is read-only.
    BR-115: Preferred language: en or fr.
    BR-118: All messages localized via __().
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Edit Profile'))

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

    {{-- Edit Card --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl shadow-card border border-outline overflow-hidden">
        {{-- Card Header --}}
        <div class="px-4 sm:px-6 py-5 border-b border-outline">
            <h1 class="text-lg sm:text-xl font-bold text-on-surface-strong font-display">
                {{ __('Edit Profile') }}
            </h1>
            <p class="text-sm text-on-surface mt-1">
                {{ __('Update your personal information.') }}
            </p>
        </div>

        {{-- Form --}}
        <div class="px-4 sm:px-6 py-6"
            x-data="{
                name: @js($user->name),
                phone: @js($user->phone ?? ''),
                preferred_language: @js($user->preferred_language ?? 'en'),
            }"
            x-sync
        >
            <form @submit.prevent="$action('{{ route('profile.update') }}')" class="space-y-5">

                {{-- Email (Read-only) --}}
                <div class="space-y-1.5">
                    <label for="edit-email" class="block text-sm font-medium text-on-surface-strong">
                        {{ __('Email Address') }}
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50">
                            {{-- Lock icon (Lucide) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input
                            id="edit-email"
                            type="email"
                            value="{{ $user->email }}"
                            disabled
                            readonly
                            class="w-full h-11 pl-10 pr-3 border border-outline rounded-lg text-sm text-on-surface/60 bg-surface dark:bg-surface cursor-not-allowed opacity-70"
                        >
                    </div>
                    <p class="text-xs text-on-surface/60 flex items-center gap-1">
                        {{-- Info icon (Lucide) --}}
                        <svg class="w-3.5 h-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 16v-4"></path>
                            <path d="M12 8h.01"></path>
                        </svg>
                        {{ __('Contact support to change your email.') }}
                    </p>
                </div>

                {{-- Name --}}
                <div class="space-y-1.5">
                    <label for="edit-name" class="block text-sm font-medium text-on-surface-strong">
                        {{ __('Full Name') }}
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50">
                            {{-- User icon (Lucide) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <input
                            id="edit-name"
                            type="text"
                            x-name="name"
                            x-model="name"
                            required
                            minlength="2"
                            maxlength="255"
                            autocomplete="name"
                            class="w-full h-11 pl-10 pr-3 border border-outline rounded-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="{{ __('Enter your full name') }}"
                        >
                    </div>
                    <p x-message="name" class="text-xs text-danger"></p>
                </div>

                {{-- Phone --}}
                <div class="space-y-1.5">
                    <label for="edit-phone" class="block text-sm font-medium text-on-surface-strong">
                        {{ __('Phone Number') }}
                    </label>
                    <div class="flex">
                        <span class="inline-flex items-center gap-1.5 px-3 border border-r-0 border-outline rounded-l-lg bg-surface-alt text-sm text-on-surface font-medium shrink-0">
                            {{-- Phone icon (Lucide) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-on-surface/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                            +237
                        </span>
                        <input
                            id="edit-phone"
                            type="tel"
                            x-name="phone"
                            x-model="phone"
                            required
                            autocomplete="tel"
                            class="w-full h-11 px-3 border border-outline rounded-r-lg text-sm text-on-surface-strong placeholder:text-on-surface/50 bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="{{ __('6XXXXXXXX') }}"
                        >
                    </div>
                    <p x-message="phone" class="text-xs text-danger"></p>
                </div>

                {{-- Preferred Language --}}
                <div class="space-y-1.5">
                    <label for="edit-language" class="block text-sm font-medium text-on-surface-strong">
                        {{ __('Preferred Language') }}
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface/50 pointer-events-none">
                            {{-- Globe icon (Lucide) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path>
                                <path d="M2 12h20"></path>
                            </svg>
                        </span>
                        <select
                            id="edit-language"
                            x-name="preferred_language"
                            x-model="preferred_language"
                            class="w-full h-11 pl-10 pr-8 border border-outline rounded-lg text-sm text-on-surface-strong bg-surface dark:bg-surface-alt transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer"
                        >
                            <option value="en">{{ __('English') }}</option>
                            <option value="fr">{{ __('French') }}</option>
                        </select>
                        {{-- Chevron down icon --}}
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface/50 pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m6 9 6 6 6-6"></path>
                            </svg>
                        </span>
                    </div>
                    <p x-message="preferred_language" class="text-xs text-danger"></p>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
                    <a href="{{ url('/profile') }}" x-data x-navigate class="inline-flex items-center justify-center h-10 px-5 rounded-lg text-sm font-semibold border border-outline text-on-surface hover:bg-surface dark:hover:bg-surface transition-all duration-200">
                        {{ __('Cancel') }}
                    </a>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center h-10 px-6 rounded-lg text-sm font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
                    >
                        <span x-show="!$fetching()">{{ __('Save Changes') }}</span>
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
