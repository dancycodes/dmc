<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Page Expired') }} - {{ config('app.name', 'DancyMeals') }}</title>
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
                {{-- Clock icon in a warning-subtle circle --}}
                <span class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-warning-subtle dark:bg-warning-subtle mb-6">
                    <svg class="w-10 h-10 text-warning dark:text-warning" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </span>

                <h1 class="text-6xl font-bold text-on-surface-strong dark:text-on-surface-strong mb-2 font-display">419</h1>
                <h2 class="text-xl font-semibold text-on-surface-strong dark:text-on-surface-strong mb-4">{{ __('Page Expired') }}</h2>
                <p class="text-on-surface dark:text-on-surface text-base leading-relaxed mb-6">
                    {{ __('Your session has expired. Please go back and try again.') }}
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
                <button
                    onclick="window.history.back()"
                    class="inline-flex items-center justify-center px-6 py-3 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface dark:text-on-surface font-medium text-sm hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m15 18-6-6 6-6"></path>
                    </svg>
                    {{ __('Go Back') }}
                </button>
            </div>

            <p class="mt-8 text-sm text-on-surface/60 dark:text-on-surface/60">
                {{ config('app.name', 'DancyMeals') }}
            </p>
        </div>
    </div>
</body>
</html>
