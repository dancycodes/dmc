{{--
    Cook Brand Profile View
    -----------------------
    F-079: Cook Brand Profile View
    Read-only view of the cook's brand profile as it appears publicly.

    Features:
    - Cover images carousel at the top (BR-183)
    - Brand name in current locale (BR-180)
    - Bio/description paragraph (BR-181)
    - WhatsApp and phone contact info (BR-186)
    - Social media links as platform icons (BR-184)
    - Edit links per section for authorized users (BR-182, BR-185)
    - Incomplete profile prompts for missing data
    - Mobile-first responsive layout
    - Light/dark mode support
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Brand Profile'))
@section('page-title', __('Profile'))

@section('content')
@php
    $locale = app()->getLocale();
    $nameField = 'name_' . $locale;
    $bioField = 'description_' . $locale;
    $altNameField = $locale === 'en' ? 'name_fr' : 'name_en';
    $altBioField = $locale === 'en' ? 'description_fr' : 'description_en';

    $brandName = $tenant->$nameField;
    $brandBio = $tenant->$bioField;
    $hasName = !empty($brandName);
    $hasBio = !empty($brandBio);

    // Check translation coverage
    $hasAltName = !empty($tenant->$altNameField);
    $hasAltBio = !empty($tenant->$altBioField);

    $whatsapp = $tenant->whatsapp;
    $phone = $tenant->phone;
    $hasWhatsapp = !empty($whatsapp);
    $hasPhone = !empty($phone);

    $socialFacebook = $tenant->social_facebook;
    $socialInstagram = $tenant->social_instagram;
    $socialTiktok = $tenant->social_tiktok;
    $hasSocialLinks = !empty($socialFacebook) || !empty($socialInstagram) || !empty($socialTiktok);

    $images = $coverImages->toArray();
    $hasImages = count($images) > 0;
@endphp

<div class="max-w-3xl mx-auto space-y-6">
    {{-- Cover Images Carousel Section (BR-183) --}}
    <div class="bg-surface dark:bg-surface rounded-xl shadow-card overflow-hidden">
        {{-- Section header with edit link --}}
        <div class="flex items-center justify-between px-5 pt-5 pb-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-on-surface/60">
                {{ __('Cover Images') }}
            </h2>
            @if($canEdit)
                <a
                    href="{{ url('/dashboard/setup?step=2') }}"
                    class="inline-flex items-center gap-1.5 text-sm text-primary hover:text-primary-hover transition-colors duration-200"
                    title="{{ __('Edit cover images') }}"
                >
                    {{-- Lucide: pencil (sm=16) --}}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                    <span class="hidden sm:inline">{{ __('Edit') }}</span>
                </a>
            @endif
        </div>

        @if($hasImages)
            {{-- Carousel --}}
            <div
                x-data="{
                    images: @js($images),
                    current: 0,
                    autoplay: null,
                    startAutoplay() {
                        if (this.images.length <= 1) return;
                        this.autoplay = setInterval(() => this.next(), 5000);
                    },
                    stopAutoplay() {
                        if (this.autoplay) clearInterval(this.autoplay);
                    },
                    next() {
                        this.current = (this.current + 1) % this.images.length;
                    },
                    prev() {
                        this.current = (this.current - 1 + this.images.length) % this.images.length;
                    }
                }"
                x-init="startAutoplay()"
                @mouseenter="stopAutoplay()"
                @mouseleave="startAutoplay()"
                class="relative"
            >
                {{-- Image container --}}
                <div class="relative aspect-[16/9] bg-surface-alt dark:bg-surface-alt overflow-hidden">
                    <template x-for="(image, index) in images" :key="image.id">
                        <img
                            :src="image.url"
                            :alt="image.alt"
                            x-show="current === index"
                            x-transition:enter="transition ease-out duration-500"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-300"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="absolute inset-0 w-full h-full object-cover"
                            loading="lazy"
                            x-on:error="$el.src=''; $el.alt='{{ __('Image failed to load') }}'; $el.classList.add('hidden')"
                        >
                    </template>

                    {{-- Fallback for failed images --}}
                    <div
                        x-show="images.length > 0"
                        class="absolute inset-0 flex items-center justify-center bg-surface-alt dark:bg-surface-alt -z-10"
                    >
                        {{-- Lucide: image (lg=24) --}}
                        <svg class="w-12 h-12 text-on-surface/20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path></svg>
                    </div>
                </div>

                {{-- Navigation arrows (only if multiple images) --}}
                <template x-if="images.length > 1">
                    <div>
                        <button
                            @click="prev()"
                            class="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center transition-colors duration-200"
                            aria-label="{{ __('Previous image') }}"
                        >
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                        </button>
                        <button
                            @click="next()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center transition-colors duration-200"
                            aria-label="{{ __('Next image') }}"
                        >
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                        </button>
                    </div>
                </template>

                {{-- Dot indicators --}}
                <template x-if="images.length > 1">
                    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center gap-2">
                        <template x-for="(image, index) in images" :key="'dot-' + image.id">
                            <button
                                @click="current = index"
                                :class="current === index ? 'bg-white scale-110' : 'bg-white/50 hover:bg-white/75'"
                                class="w-2.5 h-2.5 rounded-full transition-all duration-200"
                                :aria-label="'{{ __('Go to image') }} ' + (index + 1)"
                            ></button>
                        </template>
                    </div>
                </template>
            </div>
        @else
            {{-- No images placeholder --}}
            <div class="aspect-[16/9] bg-surface-alt dark:bg-surface-alt flex flex-col items-center justify-center gap-3 px-6">
                {{-- Lucide: image-plus (xl=32) --}}
                <svg class="w-12 h-12 text-on-surface/20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 5h6"></path><path d="M19 2v6"></path><path d="M21 11.5V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7.5"></path><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path><circle cx="9" cy="9" r="2"></circle></svg>
                <p class="text-sm text-on-surface/60 text-center">
                    {{ __('Add cover images to make your profile stand out.') }}
                </p>
                @if($canEdit)
                    <a
                        href="{{ url('/dashboard/setup?step=2') }}"
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:text-primary-hover transition-colors duration-200"
                    >
                        {{-- Lucide: plus (sm=16) --}}
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                        {{ __('Add Cover Images') }}
                    </a>
                @endif
            </div>
        @endif
    </div>

    {{-- Brand Name Section --}}
    <div class="bg-surface dark:bg-surface rounded-xl shadow-card p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-on-surface/60">
                {{ __('Brand Name') }}
            </h2>
            @if($canEdit)
                <a
                    href="{{ url('/dashboard/setup?step=1') }}"
                    class="inline-flex items-center gap-1.5 text-sm text-primary hover:text-primary-hover transition-colors duration-200"
                    title="{{ __('Edit brand name') }}"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                    <span class="hidden sm:inline">{{ __('Edit') }}</span>
                </a>
            @endif
        </div>

        @if($hasName)
            <h1 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
                {{ $brandName }}
            </h1>
            @if(!$hasAltName)
                <p class="mt-1.5 text-xs text-warning flex items-center gap-1">
                    {{-- Lucide: alert-triangle (xs=14) --}}
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                    {{ $locale === 'en' ? __('French translation missing') : __('English translation missing') }}
                </p>
            @endif
        @else
            <p class="text-on-surface/60 italic">{{ __('No brand name set') }}</p>
        @endif
    </div>

    {{-- Bio / Description Section --}}
    <div class="bg-surface dark:bg-surface rounded-xl shadow-card p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-on-surface/60">
                {{ __('Bio') }}
            </h2>
            @if($canEdit)
                <a
                    href="{{ url('/dashboard/setup?step=1') }}"
                    class="inline-flex items-center gap-1.5 text-sm text-primary hover:text-primary-hover transition-colors duration-200"
                    title="{{ __('Edit bio') }}"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                    <span class="hidden sm:inline">{{ __('Edit') }}</span>
                </a>
            @endif
        </div>

        @if($hasBio)
            <p class="text-on-surface leading-relaxed whitespace-pre-line">{{ $brandBio }}</p>
            @if(!$hasAltBio)
                <p class="mt-2 text-xs text-warning flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                    {{ $locale === 'en' ? __('French translation missing') : __('English translation missing') }}
                </p>
            @endif
        @else
            <p class="text-on-surface/60 italic">{{ __('No bio added yet') }}</p>
        @endif
    </div>

    {{-- Contact Information Section --}}
    <div class="bg-surface dark:bg-surface rounded-xl shadow-card p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-on-surface/60">
                {{ __('Contact Information') }}
            </h2>
            @if($canEdit)
                <a
                    href="{{ url('/dashboard/setup?step=1') }}"
                    class="inline-flex items-center gap-1.5 text-sm text-primary hover:text-primary-hover transition-colors duration-200"
                    title="{{ __('Edit contact info') }}"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                    <span class="hidden sm:inline">{{ __('Edit') }}</span>
                </a>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-4">
            @if($hasWhatsapp)
                {{-- BR-186: WhatsApp link to wa.me --}}
                @php
                    $whatsappClean = preg_replace('/[^0-9]/', '', $whatsapp);
                @endphp
                <a
                    href="https://wa.me/{{ $whatsappClean }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[#25D366]/10 dark:bg-[#25D366]/20 text-[#25D366] hover:bg-[#25D366]/20 dark:hover:bg-[#25D366]/30 transition-colors duration-200"
                    title="{{ __('Chat on WhatsApp') }}"
                >
                    {{-- WhatsApp icon --}}
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ $whatsapp }}</span>
                </a>
            @endif

            @if($hasPhone)
                <a
                    href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-primary-subtle dark:bg-primary-subtle text-primary hover:bg-primary/10 dark:hover:bg-primary/20 transition-colors duration-200"
                    title="{{ __('Call phone number') }}"
                >
                    {{-- Lucide: phone (md=20) --}}
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    <span class="text-sm font-medium">{{ $phone }}</span>
                </a>
            @endif

            @if(!$hasWhatsapp && !$hasPhone)
                <p class="text-on-surface/60 italic">{{ __('No contact information added') }}</p>
            @endif
        </div>
    </div>

    {{-- Social Links Section (BR-184) --}}
    <div class="bg-surface dark:bg-surface rounded-xl shadow-card p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-on-surface/60">
                {{ __('Social Links') }}
            </h2>
            @if($canEdit)
                <a
                    href="{{ url('/dashboard/setup?step=1') }}"
                    class="inline-flex items-center gap-1.5 text-sm text-primary hover:text-primary-hover transition-colors duration-200"
                    title="{{ __('Edit social links') }}"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                    <span class="hidden sm:inline">{{ __('Edit') }}</span>
                </a>
            @endif
        </div>

        @if($hasSocialLinks)
            <div class="flex flex-wrap items-center gap-3">
                @if(!empty($socialFacebook))
                    <a
                        href="{{ $socialFacebook }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[#1877F2]/10 dark:bg-[#1877F2]/20 text-[#1877F2] hover:bg-[#1877F2]/20 dark:hover:bg-[#1877F2]/30 transition-colors duration-200"
                        title="{{ __('Visit Facebook page') }}"
                    >
                        {{-- Facebook icon --}}
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                        <span class="text-sm font-medium">Facebook</span>
                    </a>
                @endif

                @if(!empty($socialInstagram))
                    <a
                        href="{{ $socialInstagram }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[#E4405F]/10 dark:bg-[#E4405F]/20 text-[#E4405F] hover:bg-[#E4405F]/20 dark:hover:bg-[#E4405F]/30 transition-colors duration-200"
                        title="{{ __('Visit Instagram profile') }}"
                    >
                        {{-- Instagram icon --}}
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                        </svg>
                        <span class="text-sm font-medium">Instagram</span>
                    </a>
                @endif

                @if(!empty($socialTiktok))
                    <a
                        href="{{ $socialTiktok }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-on-surface/5 dark:bg-on-surface/10 text-on-surface-strong hover:bg-on-surface/10 dark:hover:bg-on-surface/20 transition-colors duration-200"
                        title="{{ __('Visit TikTok profile') }}"
                    >
                        {{-- TikTok icon --}}
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
                        </svg>
                        <span class="text-sm font-medium">TikTok</span>
                    </a>
                @endif
            </div>
        @else
            <p class="text-on-surface/60 italic">{{ __('No social links') }}</p>
        @endif
    </div>
</div>
@endsection
