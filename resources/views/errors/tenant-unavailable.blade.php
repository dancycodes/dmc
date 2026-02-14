<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Site Unavailable') }} - {{ config('app.name', 'DancyMeals') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface text-on-surface font-sans antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full text-center">
            <div class="mb-8">
                <span class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-info-subtle mb-6">
                    <svg class="w-10 h-10 text-info" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </span>
                <h1 class="text-3xl font-bold text-on-surface-strong mb-2">{{ __('Site Unavailable') }}</h1>
                <p class="text-on-surface text-base leading-relaxed">
                    {{ __(':name\'s site is temporarily unavailable. Please check back later.', ['name' => $tenantName]) }}
                </p>
            </div>
            <a href="{{ $mainUrl }}"
               class="inline-flex items-center justify-center px-6 py-3 rounded-lg bg-primary text-on-primary font-medium text-sm hover:bg-primary-hover transition-colors duration-200">
                <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                </svg>
                {{ __('Visit DancyMeals') }}
            </a>
        </div>
    </div>
</body>
</html>
