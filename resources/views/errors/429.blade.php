<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Too Many Requests') }} - {{ config('app.name', 'DancyMeals') }}</title>
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
                {{-- Clock/timer icon in a warning-subtle circle --}}
                <span class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-warning-subtle dark:bg-warning-subtle mb-6">
                    <svg class="w-10 h-10 text-warning dark:text-warning" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </span>

                <h1 class="text-6xl font-bold text-on-surface-strong dark:text-on-surface-strong mb-2 font-display">429</h1>
                <h2 class="text-xl font-semibold text-on-surface-strong dark:text-on-surface-strong mb-4">{{ __('Too Many Requests') }}</h2>
                <p class="text-on-surface dark:text-on-surface text-base leading-relaxed mb-6">
                    {{ __("You're making requests a bit too quickly. Please wait a moment and try again.") }}
                </p>

                {{-- Retry-After countdown --}}
                @if(isset($exception) && $exception->getHeaders() && isset($exception->getHeaders()['Retry-After']))
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline mb-6">
                        <svg class="w-4 h-4 text-on-surface dark:text-on-surface" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.992 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                        </svg>
                        <span
                            x-data="{ seconds: {{ (int) $exception->getHeaders()['Retry-After'] }} }"
                            x-init="
                                let interval = setInterval(() => {
                                    seconds--;
                                    if (seconds <= 0) {
                                        clearInterval(interval);
                                        window.location.reload();
                                    }
                                }, 1000);
                            "
                            class="text-sm font-medium text-on-surface dark:text-on-surface"
                        >
                            {{ __('You can try again in') }} <span x-text="seconds" class="font-mono font-bold text-primary dark:text-primary"></span> {{ __('seconds') }}
                        </span>
                    </div>
                @endif
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="{{ url('/') }}"
                   class="inline-flex items-center justify-center px-6 py-3 rounded-lg bg-primary dark:bg-primary text-on-primary dark:text-on-primary font-medium text-sm hover:bg-primary-hover dark:hover:bg-primary-hover transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    {{ __('Go Home') }}
                </a>
                <button
                    onclick="window.location.reload()"
                    class="inline-flex items-center justify-center px-6 py-3 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface dark:text-on-surface font-medium text-sm hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.992 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                    </svg>
                    {{ __('Try Again') }}
                </button>
            </div>

            <p class="mt-8 text-sm text-on-surface/60 dark:text-on-surface/60">
                {{ config('app.name', 'DancyMeals') }}
            </p>
        </div>
    </div>
</body>
</html>
