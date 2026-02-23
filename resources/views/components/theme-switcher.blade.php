{{--
    Theme Switcher Component
    -------------------------
    Segmented control to toggle between Light, Dark, and System theme modes.
    Works with the body-level Alpine theme manager (from ThemeService).
    Persists choice to localStorage immediately and to the database for
    authenticated users via Gale $action.
    Uses semantic color tokens for light/dark mode compatibility.
--}}
@php
    $themes = [
        'light' => ['label' => __('Light'), 'title' => __('Light mode')],
        'dark' => ['label' => __('Dark'), 'title' => __('Dark mode')],
        'system' => ['label' => __('System'), 'title' => __('System default')],
    ];
    $isAuthenticated = auth()->check();
    $themeUpdateUrl = route('theme.update');
@endphp

<div
    x-data="{
        current: $root.preference || localStorage.getItem('dmc-theme') || 'system',
        theme: $root.preference || localStorage.getItem('dmc-theme') || 'system',
        isAuthenticated: {{ $isAuthenticated ? 'true' : 'false' }},
        switchTheme(newTheme) {
            if (newTheme === this.current || this.saving) return;
            this.current = newTheme;
            this.theme = newTheme;
            if ($root.setTheme) {
                $root.setTheme(newTheme);
            } else {
                try { localStorage.setItem('dmc-theme', newTheme); } catch(e) {}
                let resolved = newTheme;
                if (resolved === 'system') {
                    resolved = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', resolved);
            }
            window.dispatchEvent(new CustomEvent('dmc:theme-changed', { detail: { theme: newTheme } }));
            if (this.isAuthenticated) {
                $action('{{ $themeUpdateUrl }}', { include: ['theme'] })
            }
        }
    }"
    x-sync="['theme']"
    x-indicator="saving"
    x-init="current = localStorage.getItem('dmc-theme') || 'system'; theme = current;"
    @dmc:theme-changed.window="if ($event.detail.theme !== current) { current = $event.detail.theme; theme = $event.detail.theme; }"
    class="relative"
    role="radiogroup"
    aria-label="{{ __('Theme') }}"
>
    <div class="inline-flex items-center rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface-alt p-0.5 gap-0.5">
        {{-- Light mode button --}}
        <button
            type="button"
            role="radio"
            :aria-checked="current === 'light'"
            @click="switchTheme('light')"
            :class="current === 'light'
                ? 'bg-primary-subtle dark:bg-primary-subtle text-primary dark:text-primary shadow-sm'
                : 'text-on-surface dark:text-on-surface hover:bg-surface-alt dark:hover:bg-surface'"
            class="relative inline-flex items-center justify-center w-8 h-8 rounded-md transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1"
            title="{{ __('Light mode') }}"
            aria-label="{{ __('Light mode') }}"
        >
            {{-- Sun icon (Lucide) --}}
            <svg class="w-4 h-4 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M12 2v2"></path>
                <path d="M12 20v2"></path>
                <path d="m4.93 4.93 1.41 1.41"></path>
                <path d="m17.66 17.66 1.41 1.41"></path>
                <path d="M2 12h2"></path>
                <path d="M20 12h2"></path>
                <path d="m6.34 17.66-1.41 1.41"></path>
                <path d="m19.07 4.93-1.41 1.41"></path>
            </svg>
        </button>

        {{-- Dark mode button --}}
        <button
            type="button"
            role="radio"
            :aria-checked="current === 'dark'"
            @click="switchTheme('dark')"
            :class="current === 'dark'
                ? 'bg-primary-subtle dark:bg-primary-subtle text-primary dark:text-primary shadow-sm'
                : 'text-on-surface dark:text-on-surface hover:bg-surface-alt dark:hover:bg-surface'"
            class="relative inline-flex items-center justify-center w-8 h-8 rounded-md transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1"
            title="{{ __('Dark mode') }}"
            aria-label="{{ __('Dark mode') }}"
        >
            {{-- Moon icon (Lucide) --}}
            <svg class="w-4 h-4 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path>
            </svg>
        </button>

        {{-- System mode button --}}
        <button
            type="button"
            role="radio"
            :aria-checked="current === 'system'"
            @click="switchTheme('system')"
            :class="current === 'system'
                ? 'bg-primary-subtle dark:bg-primary-subtle text-primary dark:text-primary shadow-sm'
                : 'text-on-surface dark:text-on-surface hover:bg-surface-alt dark:hover:bg-surface'"
            class="relative inline-flex items-center justify-center w-8 h-8 rounded-md transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1"
            title="{{ __('System default') }}"
            aria-label="{{ __('System default') }}"
        >
            {{-- Monitor icon (Lucide) --}}
            <svg class="w-4 h-4 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="20" height="14" x="2" y="3" rx="2"></rect>
                <line x1="8" x2="16" y1="21" y2="21"></line>
                <line x1="12" x2="12" y1="17" y2="21"></line>
            </svg>
        </button>
    </div>

    {{-- No-JS Fallback --}}
    <noscript>
        @auth
            <form action="{{ $themeUpdateUrl }}" method="POST" class="inline-flex gap-1">
                @csrf
                <button type="submit" name="theme" value="light" class="text-xs text-primary hover:text-primary-hover underline">{{ __('Light') }}</button>
                <button type="submit" name="theme" value="dark" class="text-xs text-primary hover:text-primary-hover underline">{{ __('Dark') }}</button>
            </form>
        @endauth
    </noscript>
</div>
