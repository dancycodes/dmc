<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', __('Authentication')) - {{ config('app.name', 'DancyMeals') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @gale
</head>
<body class="bg-surface text-on-surface font-sans min-h-screen flex flex-col items-center justify-center px-4 py-8 sm:px-6">
    <div class="w-full max-w-md">
        {{-- Branding --}}
        <div class="text-center mb-8">
            @if(isset($tenant) && $tenant)
                <div class="mb-4">
                    <h2 class="text-2xl font-bold text-on-surface-strong">{{ $tenant->name }}</h2>
                </div>
                <p class="text-sm text-on-surface">
                    {{ __('Powered by') }}
                    <a href="{{ config('app.url') }}" class="text-primary hover:text-primary-hover font-medium">
                        {{ config('app.name', 'DancyMeals') }}
                    </a>
                </p>
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
</body>
</html>
