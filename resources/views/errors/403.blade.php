<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Forbidden') }} - {{ config('app.name', 'DancyMeals') }}</title>
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
                {{-- Shield/lock icon in a danger-subtle circle --}}
                <span class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-danger-subtle dark:bg-danger-subtle mb-6">
                    <svg class="w-10 h-10 text-danger dark:text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                    </svg>
                </span>

                <h1 class="text-6xl font-bold text-on-surface-strong dark:text-on-surface-strong mb-2 font-display">403</h1>
                <h2 class="text-xl font-semibold text-on-surface-strong dark:text-on-surface-strong mb-4">{{ __('Forbidden') }}</h2>
                <p class="text-on-surface dark:text-on-surface text-base leading-relaxed mb-6">
                    {{ __('You do not have permission to access this page.') }}
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="{{ url('/') }}"
                   class="inline-flex items-center justify-center px-6 py-3 rounded-lg bg-primary dark:bg-primary text-on-primary dark:text-on-primary font-medium text-sm hover:bg-primary-hover dark:hover:bg-primary-hover transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    {{ __('Return to Homepage') }}
                </a>
            </div>

            <p class="mt-8 text-sm text-on-surface/60 dark:text-on-surface/60">
                {{ config('app.name', 'DancyMeals') }}
            </p>
        </div>
    </div>
</body>
</html>
