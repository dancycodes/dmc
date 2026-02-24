<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Not Found') }} - {{ config('app.name', 'DancyMeals') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // Dark mode detection from localStorage or system preference
        (function() {
            var theme = localStorage.getItem('dmc-theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body class="bg-surface dark:bg-surface text-on-surface dark:text-on-surface font-sans antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full text-center">
            <div class="mb-8">
                {{-- Search/map icon in a primary-subtle circle --}}
                <span class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-primary/10 dark:bg-primary/10 mb-6">
                    <svg class="w-10 h-10 text-primary dark:text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        <line x1="11" y1="8" x2="11" y2="14"></line>
                        <line x1="8" y1="11" x2="14" y2="11"></line>
                    </svg>
                </span>

                <h1 class="text-6xl font-bold text-on-surface-strong dark:text-on-surface-strong mb-2 font-display">404</h1>
                <h2 class="text-xl font-semibold text-on-surface-strong dark:text-on-surface-strong mb-4">{{ __('Page Not Found') }}</h2>
                <p class="text-on-surface dark:text-on-surface text-base leading-relaxed mb-6">
                    {{ __("The page you're looking for doesn't exist or has been moved.") }}
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                @if(tenant() && auth()->check())
                    <a href="{{ url('/dashboard') }}"
                       class="inline-flex items-center justify-center px-6 py-3 rounded-lg bg-primary dark:bg-primary text-on-primary dark:text-on-primary font-medium text-sm hover:bg-primary-hover dark:hover:bg-primary-hover transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        {{ __('Go back to dashboard') }}
                    </a>
                @else
                    <a href="{{ url('/') }}"
                       class="inline-flex items-center justify-center px-6 py-3 rounded-lg bg-primary dark:bg-primary text-on-primary dark:text-on-primary font-medium text-sm hover:bg-primary-hover dark:hover:bg-primary-hover transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        {{ __('Return to Homepage') }}
                    </a>
                @endif
            </div>

            <p class="mt-8 text-sm text-on-surface/60 dark:text-on-surface/60">
                {{ config('app.name', 'DancyMeals') }}
            </p>
        </div>
    </div>
</body>
</html>
