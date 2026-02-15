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

    <title>@yield('title', config('app.name', 'DancyMeals')) - {{ config('app.name', 'DancyMeals') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">

    {{-- Tenant theme customization: font link + CSS variable overrides --}}
    <x-tenant-theme-styles />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @gale
</head>
<body
    x-data="{!! app(\App\Services\ThemeService::class)->getAlpineInitScript() !!}"
    x-init="$nextTick(() => document.documentElement.classList.add('theme-transition'))"
    class="bg-surface text-on-surface font-sans min-h-screen"
>
    {{-- Global Gale Navigation Loading Bar --}}
    <x-nav.loading-bar />

    {{-- Email Verification Banner (BR-044: shown on all pages for unverified users) --}}
    <x-email-verification-banner />

    @yield('body')

    {{-- PWA: Install prompt banner --}}
    <x-pwa-install-prompt />

    {{-- Push notification permission prompt --}}
    <x-push-notification-prompt />

    {{-- PWA: Service worker registration --}}
    <script>{!! app(\App\Services\PwaService::class)->getRegistrationScript() !!}</script>
</body>
</html>
