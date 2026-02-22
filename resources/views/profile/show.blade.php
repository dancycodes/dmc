{{--
    Profile View Page (F-030)
    -------------------------
    Displays the authenticated user's profile information:
    - Profile photo or default avatar
    - Name, email (with verification badge), phone
    - Preferred language, member since date
    - Action links to edit profile, addresses, payment methods, etc.

    BR-097: Auth middleware enforced in route.
    BR-098: Shows only the authenticated user's own data.
    BR-099: Email verification badge (verified/unverified).
    BR-100: Accessible from any domain.
    BR-101: All text localized via __().
    BR-102: Member since date formatted per user locale.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Profile'))

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    {{-- Profile Card --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl shadow-card border border-outline overflow-hidden">
        {{-- Header Banner --}}
        <div class="h-24 sm:h-32 bg-gradient-to-r from-primary to-primary-hover relative"></div>

        {{-- Profile Info Section --}}
        <div class="px-4 sm:px-8 pb-6 sm:pb-8">
            {{-- Avatar (overlapping banner) --}}
            <div class="-mt-12 sm:-mt-14 mb-4 sm:mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div class="flex flex-col sm:flex-row items-center sm:items-end gap-2 sm:gap-4">
                    <a href="{{ url('/profile/photo') }}" class="block relative group shrink-0" title="{{ __('Change Photo') }}">
                        @if($user->profile_photo_path)
                            <img
                                src="{{ asset('storage/' . $user->profile_photo_path) }}"
                                alt="{{ $user->name }}"
                                class="w-24 h-24 sm:w-28 sm:h-28 rounded-full object-cover border-4 border-surface-alt dark:border-surface-alt shadow-md"
                            >
                        @else
                            <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-primary-subtle border-4 border-surface-alt dark:border-surface-alt shadow-md flex items-center justify-center">
                                <span class="text-3xl sm:text-4xl font-bold text-primary">
                                    {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                                </span>
                            </div>
                        @endif
                        {{-- Camera overlay on hover --}}
                        <div class="absolute inset-0 rounded-full bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center">
                            {{-- Camera icon (Lucide) --}}
                            <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path>
                                <circle cx="12" cy="13" r="3"></circle>
                            </svg>
                        </div>
                    </a>
                    <div class="pb-1 text-center sm:text-left">
                        <h1 class="text-xl sm:text-2xl font-bold text-on-surface-strong font-display truncate max-w-[280px] sm:max-w-md">
                            {{ $user->name }}
                        </h1>
                        <p class="text-sm text-on-surface mt-0.5">
                            {{-- Calendar icon (Lucide) --}}
                            <svg class="w-3.5 h-3.5 inline-block mr-1 -mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M8 2v4"></path>
                                <path d="M16 2v4"></path>
                                <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                                <path d="M3 10h18"></path>
                            </svg>
                            {{ __('Member since :date', ['date' => $user->created_at->translatedFormat('F Y')]) }}
                        </p>
                    </div>
                </div>

                {{-- Edit Profile Button (desktop) --}}
                <div class="hidden sm:block">
                    <a href="{{ url('/profile/edit') }}" class="inline-flex items-center gap-2 h-10 px-5 rounded-lg text-sm font-semibold border border-outline text-on-surface hover:bg-surface dark:hover:bg-surface transition-all duration-200">
                        {{-- Pencil icon (Lucide) --}}
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path>
                            <path d="m15 5 4 4"></path>
                        </svg>
                        {{ __('Edit Profile') }}
                    </a>
                </div>
            </div>

            {{-- Info Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mt-2">
                {{-- Email --}}
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0 mt-0.5">
                        {{-- Mail icon (Lucide) --}}
                        <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-on-surface uppercase tracking-wide">{{ __('Email') }}</p>
                        <p class="text-sm text-on-surface-strong mt-0.5 truncate" title="{{ $user->email }}">{{ $user->email }}</p>
                        <div class="mt-1">
                            @if($user->hasVerifiedEmail())
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-success bg-success-subtle px-2 py-0.5 rounded-full">
                                    {{-- Check circle icon --}}
                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <path d="m9 11 3 3L22 4"></path>
                                    </svg>
                                    {{ __('Verified') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-warning bg-warning-subtle px-2 py-0.5 rounded-full">
                                    {{-- Alert triangle icon --}}
                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                                        <path d="M12 9v4"></path>
                                        <path d="M12 17h.01"></path>
                                    </svg>
                                    {{ __('Unverified') }}
                                </span>
                                <a href="{{ route('verification.notice') }}" class="text-xs text-primary hover:text-primary-hover font-medium ml-1.5 hover:underline">
                                    {{ __('Verify now') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Phone --}}
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-full bg-secondary-subtle flex items-center justify-center shrink-0 mt-0.5">
                        {{-- Phone icon (Lucide) --}}
                        <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-on-surface uppercase tracking-wide">{{ __('Phone') }}</p>
                        <p class="text-sm text-on-surface-strong mt-0.5">
                            @if($user->phone)
                                +237 {{ $user->phone }}
                            @else
                                <span class="text-on-surface italic">{{ __('Not set') }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Preferred Language --}}
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center shrink-0 mt-0.5">
                        {{-- Globe icon (Lucide) --}}
                        <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path>
                            <path d="M2 12h20"></path>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-on-surface uppercase tracking-wide">{{ __('Language') }}</p>
                        <p class="text-sm text-on-surface-strong mt-0.5">
                            {{ $user->preferred_language === 'fr' ? __('French') : __('English') }}
                        </p>
                    </div>
                </div>

                {{-- Member Since --}}
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-full bg-success-subtle flex items-center justify-center shrink-0 mt-0.5">
                        {{-- Clock icon (Lucide) --}}
                        <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-on-surface uppercase tracking-wide">{{ __('Member Since') }}</p>
                        <p class="text-sm text-on-surface-strong mt-0.5">
                            {{ $user->created_at->translatedFormat('F Y') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Edit Profile Button (mobile only) --}}
            <div class="sm:hidden mt-6">
                <a href="{{ url('/profile/edit') }}" class="w-full inline-flex items-center justify-center gap-2 h-10 px-5 rounded-lg text-sm font-semibold border border-outline text-on-surface hover:bg-surface dark:hover:bg-surface transition-all duration-200">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path>
                        <path d="m15 5 4 4"></path>
                    </svg>
                    {{ __('Edit Profile') }}
                </a>
            </div>
        </div>
    </div>

    {{-- Action Links Grid --}}
    <div class="mt-8">
        <h2 class="text-lg font-semibold text-on-surface-strong mb-4">{{ __('Account Settings') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" x-data x-navigate>
            {{-- Edit Profile (F-032) --}}
            <a href="{{ url('/profile/edit') }}" class="flex items-center gap-4 p-4 bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline hover:border-primary hover:shadow-card transition-all duration-200 group">
                <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0 group-hover:bg-primary group-hover:text-on-primary transition-colors duration-200">
                    {{-- User Pen icon (Lucide) --}}
                    <svg class="w-5 h-5 text-primary group-hover:text-on-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11.5 15H7a4 4 0 0 0-4 4v2"></path>
                        <path d="M21.378 16.626a1 1 0 0 0-3.004-3.004l-4.01 4.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z"></path>
                        <circle cx="10" cy="7" r="4"></circle>
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-on-surface-strong">{{ __('Edit Profile') }}</p>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Update your name, phone, and more') }}</p>
                </div>
                {{-- Chevron right --}}
                <svg class="w-4 h-4 text-on-surface ml-auto shrink-0 opacity-50 group-hover:opacity-100 transition-opacity" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m9 18 6-6-6-6"></path>
                </svg>
            </a>

            {{-- Change Photo (F-031) --}}
            <a href="{{ url('/profile/photo') }}" class="flex items-center gap-4 p-4 bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline hover:border-primary hover:shadow-card transition-all duration-200 group">
                <span class="w-10 h-10 rounded-full bg-secondary-subtle flex items-center justify-center shrink-0 group-hover:bg-secondary group-hover:text-on-secondary transition-colors duration-200">
                    {{-- Camera icon --}}
                    <svg class="w-5 h-5 text-secondary group-hover:text-on-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path>
                        <circle cx="12" cy="13" r="3"></circle>
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-on-surface-strong">{{ __('Change Photo') }}</p>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Upload or update your profile photo') }}</p>
                </div>
                <svg class="w-4 h-4 text-on-surface ml-auto shrink-0 opacity-50 group-hover:opacity-100 transition-opacity" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m9 18 6-6-6-6"></path>
                </svg>
            </a>

            {{-- Delivery Addresses (F-034) --}}
            <a href="{{ url('/profile/addresses') }}" class="flex items-center gap-4 p-4 bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline hover:border-primary hover:shadow-card transition-all duration-200 group">
                <span class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center shrink-0 group-hover:bg-info group-hover:text-on-info transition-colors duration-200">
                    {{-- MapPin icon --}}
                    <svg class="w-5 h-5 text-info group-hover:text-on-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-on-surface-strong">{{ __('Delivery Addresses') }}</p>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Manage your delivery locations') }}</p>
                </div>
                <svg class="w-4 h-4 text-on-surface ml-auto shrink-0 opacity-50 group-hover:opacity-100 transition-opacity" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m9 18 6-6-6-6"></path>
                </svg>
            </a>

            {{-- Payment Methods (F-038) --}}
            <a href="{{ url('/profile/payment-methods') }}" class="flex items-center gap-4 p-4 bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline hover:border-primary hover:shadow-card transition-all duration-200 group">
                <span class="w-10 h-10 rounded-full bg-success-subtle flex items-center justify-center shrink-0 group-hover:bg-success group-hover:text-on-success transition-colors duration-200">
                    {{-- CreditCard icon --}}
                    <svg class="w-5 h-5 text-success group-hover:text-on-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="14" x="2" y="5" rx="2"></rect>
                        <line x1="2" x2="22" y1="10" y2="10"></line>
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-on-surface-strong">{{ __('Payment Methods') }}</p>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Manage your payment options') }}</p>
                </div>
                <svg class="w-4 h-4 text-on-surface ml-auto shrink-0 opacity-50 group-hover:opacity-100 transition-opacity" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m9 18 6-6-6-6"></path>
                </svg>
            </a>

            {{-- Notification Preferences (F-041) --}}
            <a href="{{ url('/profile/notifications') }}" class="flex items-center gap-4 p-4 bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline hover:border-primary hover:shadow-card transition-all duration-200 group">
                <span class="w-10 h-10 rounded-full bg-warning-subtle flex items-center justify-center shrink-0 group-hover:bg-warning group-hover:text-on-warning transition-colors duration-200">
                    {{-- Bell icon --}}
                    <svg class="w-5 h-5 text-warning group-hover:text-on-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
                        <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-on-surface-strong">{{ __('Notification Preferences') }}</p>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Choose how you receive alerts') }}</p>
                </div>
                <svg class="w-4 h-4 text-on-surface ml-auto shrink-0 opacity-50 group-hover:opacity-100 transition-opacity" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m9 18 6-6-6-6"></path>
                </svg>
            </a>

            {{-- Language Preference (F-042) --}}
            <a href="{{ url('/profile/language') }}" class="flex items-center gap-4 p-4 bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline hover:border-primary hover:shadow-card transition-all duration-200 group">
                <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0 group-hover:bg-primary group-hover:text-on-primary transition-colors duration-200">
                    {{-- Languages icon --}}
                    <svg class="w-5 h-5 text-primary group-hover:text-on-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m5 8 6 6"></path>
                        <path d="m4 14 6-6 2-3"></path>
                        <path d="M2 5h12"></path>
                        <path d="M7 2h1"></path>
                        <path d="m22 22-5-10-5 10"></path>
                        <path d="M14 18h6"></path>
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-on-surface-strong">{{ __('Language Preference') }}</p>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Set your preferred language') }}</p>
                </div>
                <svg class="w-4 h-4 text-on-surface ml-auto shrink-0 opacity-50 group-hover:opacity-100 transition-opacity" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m9 18 6-6-6-6"></path>
                </svg>
            </a>

            {{-- Favorites (F-198) --}}
            <a href="{{ url('/my-favorites') }}" class="flex items-center gap-4 p-4 bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline hover:border-primary hover:shadow-card transition-all duration-200 group">
                <span class="w-10 h-10 rounded-full bg-danger-subtle flex items-center justify-center shrink-0 group-hover:bg-danger group-hover:text-on-danger transition-colors duration-200">
                    {{-- Heart icon (Lucide md=20) --}}
                    <svg class="w-5 h-5 text-danger group-hover:text-on-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path>
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-on-surface-strong">{{ __('Favorites') }}</p>
                    <p class="text-xs text-on-surface mt-0.5">{{ __('Your saved cooks and meals') }}</p>
                </div>
                <svg class="w-4 h-4 text-on-surface ml-auto shrink-0 opacity-50 group-hover:opacity-100 transition-opacity" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m9 18 6-6-6-6"></path>
                </svg>
            </a>
        </div>
    </div>
</div>
@endsection
