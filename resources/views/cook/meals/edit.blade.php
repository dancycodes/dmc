{{--
    Meal Edit (Stub)
    ----------------
    F-110: Meal Edit — placeholder page.
    Full implementation in F-110.

    Provides basic redirect target after meal creation (F-108).
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

    {{-- Meal info header --}}
    <div class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-card p-6 mb-6">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-2xl font-display font-bold text-on-surface-strong">{{ $meal->name }}</h2>
                @if($meal->description)
                    <p class="mt-2 text-sm text-on-surface/70">{{ $meal->description }}</p>
                @endif
            </div>
            <span class="shrink-0 px-3 py-1 rounded-full text-xs font-medium {{ $meal->status === 'draft' ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success' }}">
                {{ $meal->status === 'draft' ? __('Draft') : __('Live') }}
            </span>
        </div>

        {{-- Bilingual details --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 pt-4 border-t border-outline dark:border-outline">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-6 h-6 rounded-full bg-primary-subtle flex items-center justify-center">
                        <span class="text-[10px] font-bold text-primary">EN</span>
                    </span>
                    <span class="text-sm font-medium text-on-surface-strong">{{ __('English') }}</span>
                </div>
                <p class="text-sm font-medium text-on-surface-strong">{{ $meal->name_en }}</p>
                <p class="text-sm text-on-surface/70 mt-1">{{ $meal->description_en }}</p>
            </div>
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-6 h-6 rounded-full bg-secondary-subtle flex items-center justify-center">
                        <span class="text-[10px] font-bold text-secondary">FR</span>
                    </span>
                    <span class="text-sm font-medium text-on-surface-strong">{{ __('French') }}</span>
                </div>
                <p class="text-sm font-medium text-on-surface-strong">{{ $meal->name_fr }}</p>
                <p class="text-sm text-on-surface/70 mt-1">{{ $meal->description_fr }}</p>
            </div>
        </div>
    </div>

    {{-- F-096: Location Override Section --}}
    @if($canManageLocations && $locationData)
        @include('cook.meals._location-override')
    @endif

    {{-- Placeholder sections for future features --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
        {{-- Images section (F-109) --}}
        <div class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-card p-6 opacity-60">
            <h3 class="text-base font-semibold text-on-surface-strong mb-2">{{ __('Images') }}</h3>
            <p class="text-sm text-on-surface/70">{{ __('Image upload will be available soon.') }}</p>
        </div>

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

        {{-- Schedule section (F-106) --}}
        <div class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-card p-6 opacity-60">
            <h3 class="text-base font-semibold text-on-surface-strong mb-2">{{ __('Schedule') }}</h3>
            <p class="text-sm text-on-surface/70">{{ __('Schedule overrides will be available soon.') }}</p>
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
