<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- PWA: manifest link and meta tags --}}
    {!! app(\App\Services\PwaService::class)->getMetaTags() !!}

    {{-- FOIT prevention: apply theme from localStorage before any CSS renders --}}
    <script>{!! app(\App\Services\ThemeService::class)->getInlineScript() !!}</script>

    <title>@yield('title', __('Authentication')) - {{ config('app.name', 'DancyMeals') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- Tenant theme customization: font link + CSS variable overrides --}}
    <x-tenant-theme-styles />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @gale
</head>
<body
    x-data="{!! app(\App\Services\ThemeService::class)->getAlpineInitScript() !!}"
    x-init="$nextTick(() => document.documentElement.classList.add('theme-transition'))"
    class="bg-surface text-on-surface font-sans min-h-screen flex flex-col items-center justify-center px-4 py-8 sm:px-6"
>
    {{-- Theme & Language Switchers (top-right) --}}
    <div class="fixed top-4 right-4 z-50 flex items-center gap-2">
        <x-theme-switcher />
        <x-language-switcher />
    </div>

    <div class="w-full max-w-md">
        {{-- Branding --}}
        <div class="text-center mb-8">
            @if(isset($tenant) && $tenant)
                <div class="mb-3">
                    @if(isset($tenant->logo_path) && $tenant->logo_path)
                        <img
                            src="{{ asset('storage/' . $tenant->logo_path) }}"
                            alt="{{ $tenant->name }}"
                            class="h-14 w-14 rounded-full object-cover mx-auto mb-3 border border-outline"
                        >
                    @else
                        <div class="h-14 w-14 rounded-full bg-primary-subtle flex items-center justify-center mx-auto mb-3 border border-outline">
                            <span class="text-xl font-bold text-primary">{{ mb_substr($tenant->name, 0, 1) }}</span>
                        </div>
                    @endif
                    <h2 class="text-2xl font-bold text-on-surface-strong font-display">{{ $tenant->name }}</h2>
                </div>
                @hasSection('tenant-notice')
                    @yield('tenant-notice')
                @else
                    <p class="text-sm text-on-surface mt-2">
                        {{ __('Powered by') }}
                        <a href="{{ config('app.url') }}" class="text-primary hover:text-primary-hover font-medium">
                            {{ config('app.name', 'DancyMeals') }}
                        </a>
                    </p>
                @endif
            @else
                <h1 class="text-3xl font-bold text-on-surface-strong font-display">
                    {{ config('app.name', 'DancyMeals') }}
                </h1>
                <p class="mt-2 text-sm text-on-surface">
                    {{ __('Your favorite home-cooked meals, delivered.') }}
                </p>
            @endif
        </div>

        {{-- Auth Card --}}
        <div class="bg-surface dark:bg-surface-alt rounded-xl shadow-card border border-outline p-6 sm:p-8">
            @yield('content')
        </div>

        {{-- Footer Links --}}
        <div class="mt-6 text-center text-sm text-on-surface">
            @yield('footer')
        </div>
    </div>

    {{-- PWA: Install prompt banner --}}
    <x-pwa-install-prompt />

    {{-- Push notification permission prompt --}}
    <x-push-notification-prompt />

    {{-- PWA: Service worker registration --}}
    <script>{!! app(\App\Services\PwaService::class)->getRegistrationScript() !!}</script>
</body>
</html>
