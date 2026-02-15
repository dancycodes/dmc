{{-- PWA Install Prompt Banner --}}
{{-- Slides in from bottom after a delay. Captures beforeinstallprompt for Chrome/Edge/etc. --}}
{{-- Shows manual instructions for iOS Safari. --}}
<div
    x-data="{!! app(\App\Services\PwaService::class)->getInstallPromptAlpineData() !!}"
    x-cloak
    class="fixed bottom-0 inset-x-0 z-50 pointer-events-none"
    aria-live="polite"
>
    <template x-if="showBanner">
        <div
            x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="translate-y-full opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="translate-y-full opacity-0"
            class="pointer-events-auto mx-4 mb-4 sm:mx-auto sm:max-w-lg"
        >
            <div class="bg-surface dark:bg-surface-alt border border-outline rounded-xl shadow-dropdown p-4 flex items-start gap-3">
                {{-- App Icon --}}
                <div class="shrink-0 w-12 h-12 rounded-xl bg-primary-subtle flex items-center justify-center">
                    <img
                        src="/icons/icon-192x192.png"
                        alt="{{ config('app.name', 'DancyMeals') }}"
                        class="w-10 h-10 rounded-lg"
                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"
                    >
                    {{-- Fallback icon if image fails --}}
                    <svg style="display:none" class="w-6 h-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                    </svg>
                </div>

                {{-- Message --}}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-on-surface-strong">
                        {{ __('Install DancyMeals') }}
                    </p>

                    {{-- Standard message for browsers with beforeinstallprompt --}}
                    <template x-if="!isIos">
                        <p class="text-xs text-on-surface mt-0.5">
                            {{ __('Add DancyMeals to your home screen for quick access.') }}
                        </p>
                    </template>

                    {{-- iOS Safari manual instruction --}}
                    <template x-if="isIos">
                        <p class="text-xs text-on-surface mt-0.5">
                            {{ __('Tap the share button and select "Add to Home Screen".') }}
                        </p>
                    </template>

                    {{-- Action Buttons --}}
                    <div class="flex items-center gap-2 mt-3">
                        {{-- Install button (hidden on iOS since it is manual) --}}
                        <template x-if="!isIos">
                            <button
                                @click="installApp()"
                                type="button"
                                class="h-8 px-4 text-xs rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98]"
                            >
                                {{ __('Install') }}
                            </button>
                        </template>

                        {{-- Dismiss button --}}
                        <button
                            @click="dismissPrompt()"
                            type="button"
                            class="h-8 px-3 text-xs rounded-lg font-medium text-on-surface hover:bg-surface-alt dark:hover:bg-surface transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-outline focus:ring-offset-2"
                        >
                            {{ __('Not now') }}
                        </button>
                    </div>
                </div>

                {{-- Close button --}}
                <button
                    @click="dismissPrompt()"
                    type="button"
                    class="shrink-0 p-1 rounded-md text-on-surface hover:bg-surface-alt dark:hover:bg-surface transition-colors focus:outline-none focus:ring-2 focus:ring-outline"
                    aria-label="{{ __('Close') }}"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </template>
</div>
