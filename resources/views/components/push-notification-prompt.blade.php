{{-- Push Notification Permission Prompt --}}
{{-- Non-blocking banner that appears after a meaningful user interaction. --}}
{{-- Shows only for authenticated users when push is supported and permission is default. --}}
@auth
@if(app(\App\Services\PushNotificationService::class)->isConfigured())
<div
    x-data="{!! app(\App\Services\PushNotificationService::class)->getPromptAlpineData() !!}"
    x-cloak
    class="fixed bottom-0 inset-x-0 z-50 pointer-events-none"
    aria-live="polite"
    data-testid="push-notification-prompt"
>
    <template x-if="showPrompt && supported && !permissionGranted && !permissionDenied">
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
                {{-- Bell Icon --}}
                <div class="shrink-0 w-12 h-12 rounded-xl bg-primary-subtle flex items-center justify-center">
                    <svg class="w-6 h-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                </div>

                {{-- Message --}}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-on-surface-strong">
                        {{ __('Stay updated on your orders') }}
                    </p>
                    <p class="text-xs text-on-surface mt-0.5">
                        {{ __('Get real-time updates when your order status changes, payments are confirmed, and more.') }}
                    </p>

                    {{-- Action Buttons --}}
                    <div class="flex items-center gap-2 mt-3">
                        <button
                            @click="requestPermission()"
                            :disabled="subscribing"
                            type="button"
                            class="h-8 px-4 text-xs rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98] disabled:opacity-60"
                            data-testid="push-allow-btn"
                        >
                            <span x-show="!subscribing">{{ __('Allow') }}</span>
                            <span x-show="subscribing" x-cloak class="flex items-center gap-1">
                                <svg class="w-3 h-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                {{ __('Enabling...') }}
                            </span>
                        </button>

                        <button
                            @click="dismissPrompt()"
                            type="button"
                            class="h-8 px-3 text-xs rounded-lg font-medium text-on-surface hover:bg-surface-alt dark:hover:bg-surface transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-outline focus:ring-offset-2"
                            data-testid="push-later-btn"
                        >
                            {{ __('Maybe Later') }}
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
@endif
@endauth
