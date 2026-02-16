<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Account Deactivated') }} - {{ config('app.name', 'DancyMeals') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // Dark mode detection from localStorage or system preference
        // BR-096: No user preference available since logged out
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
                    <svg class="w-10 h-10 text-danger dark:text-danger" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </span>

                <h1 class="text-2xl font-bold text-on-surface-strong dark:text-on-surface-strong mb-4 font-display">
                    {{ __('Account Deactivated') }}
                </h1>
                <p class="text-on-surface dark:text-on-surface text-base leading-relaxed mb-6">
                    {{ __('Your account has been deactivated. Please contact support.') }}
                </p>
                <p class="text-on-surface/60 dark:text-on-surface/60 text-sm leading-relaxed mb-8">
                    {{ __('If you believe this is an error, please reach out to our support team and we will assist you.') }}
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="mailto:support@dancymeals.com"
                   class="inline-flex items-center justify-center px-6 py-3 rounded-lg bg-primary dark:bg-primary text-on-primary dark:text-on-primary font-medium text-sm hover:bg-primary-hover dark:hover:bg-primary-hover transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                    </svg>
                    {{ __('Contact Support') }}
                </a>
                <a href="{{ url('/') }}"
                   class="inline-flex items-center justify-center px-6 py-3 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface dark:text-on-surface font-medium text-sm hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    {{ __('Go Home') }}
                </a>
            </div>

            <p class="mt-8 text-sm text-on-surface/60 dark:text-on-surface/60">
                {{ config('app.name', 'DancyMeals') }}
            </p>
        </div>
    </div>
</body>
</html>
