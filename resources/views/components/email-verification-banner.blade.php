{{--
    Email Verification Banner (BR-044)

    Persistent banner shown to authenticated but unverified users on all pages.
    Features:
    - Shows user's email address so they know which inbox to check
    - Resend button with 60-second cooldown timer (BR-041)
    - Dismissible per-session but reappears on next page load if still unverified
    - Non-obstructive on mobile (compact layout)
    - Light/dark mode support with semantic tokens
--}}
@auth
    @if(!auth()->user()->hasVerifiedEmail())
        <div
            x-data="{
                dismissed: false,
                cooldownActive: false,
                cooldownSeconds: 0,
                resent: false,
                sending: false,
                cooldownTimer: null,
                startCooldown() {
                    this.cooldownActive = true;
                    this.cooldownSeconds = 60;
                    this.cooldownTimer = setInterval(() => {
                        this.cooldownSeconds--;
                        if (this.cooldownSeconds <= 0) {
                            this.cooldownActive = false;
                            clearInterval(this.cooldownTimer);
                        }
                    }, 1000);
                },
                async resendEmail() {
                    if (this.cooldownActive || this.sending) return;
                    this.sending = true;
                    await $action('{{ route('verification.send') }}');
                    this.sending = false;
                    this.resent = true;
                    this.startCooldown();
                    setTimeout(() => { this.resent = false; }, 4000);
                }
            }"
            x-show="!dismissed"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            class="bg-warning-subtle dark:bg-warning-subtle border-b border-warning/30 dark:border-warning/30"
            role="alert"
            aria-live="polite"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 sm:gap-4">
                    {{-- Message --}}
                    <div class="flex items-start sm:items-center gap-3 min-w-0 flex-1">
                        {{-- Mail icon (Lucide, sm=16px) --}}
                        <svg class="w-5 h-5 text-warning shrink-0 mt-0.5 sm:mt-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                        </svg>
                        <p class="text-sm text-on-surface-strong dark:text-on-surface-strong">
                            {{ __('Please verify your email address.') }}
                            <span class="text-on-surface dark:text-on-surface">
                                {{ __('We sent a verification link to') }}
                                <strong class="font-medium text-on-surface-strong dark:text-on-surface-strong">{{ auth()->user()->email }}</strong>
                            </span>
                        </p>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2 shrink-0 ml-8 sm:ml-0">
                        {{-- Resend button with cooldown --}}
                        <button
                            @click="resendEmail()"
                            :disabled="cooldownActive || sending"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200"
                            :class="cooldownActive || sending
                                ? 'bg-surface-alt dark:bg-surface-alt text-on-surface/50 dark:text-on-surface/50 cursor-not-allowed'
                                : 'bg-warning text-on-warning hover:opacity-90 cursor-pointer'"
                        >
                            {{-- Loader icon --}}
                            <template x-if="sending">
                                <svg class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </template>
                            <span x-show="!cooldownActive && !sending">{{ __('Resend') }}</span>
                            <span x-show="cooldownActive" x-text="'{{ __('Resend in') }} ' + cooldownSeconds + 's'"></span>
                            <span x-show="sending && !cooldownActive">{{ __('Sending...') }}</span>
                        </button>

                        {{-- Dismiss button --}}
                        <button
                            @click="dismissed = true"
                            class="w-7 h-7 rounded-lg flex items-center justify-center text-on-surface/60 hover:text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors duration-200"
                            :title="'{{ __('Dismiss') }}'"
                            :aria-label="'{{ __('Dismiss') }}'"
                        >
                            {{-- X icon (Lucide, xs=14px) --}}
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6 6 18"></path>
                                <path d="m6 6 12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Success toast inline (shown briefly after resend) --}}
                <div
                    x-show="resent"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="mt-2 text-xs text-success dark:text-success font-medium flex items-center gap-1.5"
                >
                    {{-- Check icon --}}
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5"></path>
                    </svg>
                    {{ __('Verification email sent.') }}
                </div>
            </div>
        </div>
    @endif
@endauth
