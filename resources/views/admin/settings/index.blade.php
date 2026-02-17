{{--
    Platform Settings Management
    ----------------------------
    F-063: Global platform settings page within the admin panel.

    Settings organized in logical groups:
    - General: platform name
    - Feature Toggles: wallet enabled
    - Order Settings: default cancellation window
    - Support: support email, support phone
    - System: maintenance mode, maintenance reason

    BR-189: All settings changes are logged in the activity log
    BR-190: Critical settings (maintenance mode, wallet toggle) require confirmation dialog
    BR-191: Settings save without full page reload (via Gale)
    BR-187: Maintenance mode is accessible only to super-admin
--}}
@extends('layouts.admin')

@section('title', __('Platform Settings'))
@section('page-title', __('Settings'))

@section('content')
<div
    x-data="{
        platform_name: {{ json_encode($settings['platform_name'] ?? 'DancyMeals') }},
        wallet_enabled: {{ ($settings['wallet_enabled'] ?? true) ? 'true' : 'false' }},
        default_cancellation_window: {{ (int) ($settings['default_cancellation_window'] ?? 30) }},
        support_email: {{ json_encode($settings['support_email'] ?? '') }},
        support_phone: {{ json_encode($settings['support_phone'] ?? '') }},
        maintenance_mode: {{ ($settings['maintenance_mode'] ?? false) ? 'true' : 'false' }},
        maintenance_reason: {{ json_encode($settings['maintenance_reason'] ?? '') }},
        setting_key: '',
        setting_value: '',
        error: '',
        success: '',
        confirmAction: null,
        confirmTitle: '',
        confirmMessage: '',
        showConfirm: false,

        saveSetting(key) {
            this.setting_key = key;
            this.setting_value = String(this[key]);
            this.error = '';
            this.success = '';
            $action('{{ url('/vault-entry/settings') }}', {
                include: ['setting_key', 'setting_value']
            });
        },

        saveWithConfirm(key, title, message) {
            this.confirmAction = key;
            this.confirmTitle = title;
            this.confirmMessage = message;
            this.showConfirm = true;
        },

        executeConfirm() {
            this.showConfirm = false;
            this.saveSetting(this.confirmAction);
            this.confirmAction = null;
        },

        cancelConfirm() {
            this.showConfirm = false;
            // Revert the toggle if cancelled
            if (this.confirmAction === 'wallet_enabled') {
                this.wallet_enabled = !this.wallet_enabled;
            } else if (this.confirmAction === 'maintenance_mode') {
                this.maintenance_mode = !this.maintenance_mode;
            }
            this.confirmAction = null;
        },

        incrementWindow() {
            if (this.default_cancellation_window < 120) {
                this.default_cancellation_window = Math.min(120, this.default_cancellation_window + 5);
                this.saveSetting('default_cancellation_window');
            }
        },

        decrementWindow() {
            if (this.default_cancellation_window > 0) {
                this.default_cancellation_window = Math.max(0, this.default_cancellation_window - 5);
                this.saveSetting('default_cancellation_window');
            }
        },

        updateWindow(val) {
            let num = parseInt(val);
            if (isNaN(num)) return;
            this.default_cancellation_window = Math.max(0, Math.min(120, num));
            this.saveSetting('default_cancellation_window');
        }
    }"
    x-sync="['setting_key', 'setting_value']"
    @setting-saved.window="
        let detail = $event.detail;
        if (detail && detail.key) {
            let el = document.getElementById('saved-' + detail.key);
            if (el) {
                el.classList.remove('opacity-0');
                el.classList.add('opacity-100');
                setTimeout(() => {
                    el.classList.remove('opacity-100');
                    el.classList.add('opacity-0');
                }, 2000);
            }
        }
    "
    class="space-y-6"
>
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Settings')],
    ]" />

    {{-- Error alert --}}
    <div x-show="error" x-cloak x-transition class="rounded-lg border border-danger/30 bg-danger-subtle p-4">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-danger shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line></svg>
            <p class="text-sm font-medium text-danger" x-text="error"></p>
        </div>
    </div>

    {{-- Section: General --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
        <div class="flex items-center gap-3 mb-6">
            <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center">
                <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </span>
            <div>
                <h3 class="text-base font-semibold text-on-surface-strong">{{ __('General') }}</h3>
                <p class="text-sm text-on-surface/60">{{ __('Basic platform configuration') }}</p>
            </div>
        </div>

        {{-- Platform Name --}}
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <label for="platform_name" class="text-sm font-medium text-on-surface-strong">
                    {{ __('Platform Name') }}
                </label>
                <span id="saved-platform_name" class="text-xs font-semibold text-success opacity-0 transition-opacity duration-300">
                    <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                    {{ __('Saved') }}
                </span>
            </div>
            <p class="text-xs text-on-surface/60">{{ __('Used in emails, notifications, and the PWA manifest.') }}</p>
            <div class="flex gap-2">
                <input
                    type="text"
                    id="platform_name"
                    x-model="platform_name"
                    maxlength="255"
                    class="flex-1 h-10 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40
                           focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm px-3"
                    placeholder="{{ __('e.g., DancyMeals') }}"
                >
                <button
                    @click="saveSetting('platform_name')"
                    :disabled="$fetching()"
                    class="h-10 px-4 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 inline-flex items-center gap-2
                           focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2
                           disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
                >
                    <span x-show="$fetching() && setting_key === 'platform_name'" class="animate-spin-slow">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                    </span>
                    <span x-show="!($fetching() && setting_key === 'platform_name')">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    </span>
                    {{ __('Save') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Section: Feature Toggles --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
        <div class="flex items-center gap-3 mb-6">
            <span class="w-10 h-10 rounded-full bg-secondary-subtle flex items-center justify-center">
                <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V6a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v14"></path><path d="M2 20h20"></path><path d="M14 12v.01"></path></svg>
            </span>
            <div>
                <h3 class="text-base font-semibold text-on-surface-strong">{{ __('Feature Toggles') }}</h3>
                <p class="text-sm text-on-surface/60">{{ __('Enable or disable platform features') }}</p>
            </div>
        </div>

        {{-- Wallet Toggle --}}
        <div class="flex items-center justify-between py-3">
            <div class="flex-1 min-w-0 pr-4">
                <div class="flex items-center gap-2">
                    <p class="text-sm font-medium text-on-surface-strong">{{ __('Client Wallet for Payments') }}</p>
                    <span id="saved-wallet_enabled" class="text-xs font-semibold text-success opacity-0 transition-opacity duration-300">
                        <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        {{ __('Saved') }}
                    </span>
                </div>
                <p class="text-xs text-on-surface/60 mt-0.5">{{ __('Allow clients to use wallet balance to pay for orders. Refunds will still be credited to wallets regardless.') }}</p>
            </div>
            <button
                @click="
                    wallet_enabled = !wallet_enabled;
                    if (wallet_enabled) {
                        saveWithConfirm('wallet_enabled', '{{ __('Enable Wallet Payments?') }}', '{{ __('Enabling wallet payments means clients can use their wallet balance to pay for orders. Continue?') }}');
                    } else {
                        saveWithConfirm('wallet_enabled', '{{ __('Disable Wallet Payments?') }}', '{{ __('Disabling wallet payments means clients cannot use wallet balance to pay for orders. Refunds will still be credited to wallets. Continue?') }}');
                    }
                "
                type="button"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                :class="wallet_enabled ? 'bg-primary' : 'bg-outline'"
                role="switch"
                :aria-checked="wallet_enabled"
                aria-label="{{ __('Toggle wallet payments') }}"
            >
                <span
                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    :class="wallet_enabled ? 'translate-x-5' : 'translate-x-0'"
                ></span>
            </button>
        </div>
    </div>

    {{-- Section: Order Settings --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
        <div class="flex items-center gap-3 mb-6">
            <span class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center">
                <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line></svg>
            </span>
            <div>
                <h3 class="text-base font-semibold text-on-surface-strong">{{ __('Order Settings') }}</h3>
                <p class="text-sm text-on-surface/60">{{ __('Configure order-related behaviors') }}</p>
            </div>
        </div>

        {{-- Default Cancellation Window --}}
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <label class="text-sm font-medium text-on-surface-strong">
                    {{ __('Default Cancellation Window') }}
                </label>
                <span id="saved-default_cancellation_window" class="text-xs font-semibold text-success opacity-0 transition-opacity duration-300">
                    <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                    {{ __('Saved') }}
                </span>
            </div>
            <p class="text-xs text-on-surface/60">{{ __('Time in minutes that clients can cancel an order after payment. Applies to all cooks unless they have a custom override. Set to 0 to disable cancellation.') }}</p>

            <div class="flex items-center gap-3">
                {{-- Decrement button --}}
                <button
                    @click="decrementWindow()"
                    :disabled="default_cancellation_window <= 0"
                    class="w-10 h-10 rounded-lg border border-outline dark:border-outline flex items-center justify-center text-on-surface hover:bg-surface transition-colors
                           disabled:opacity-30 disabled:cursor-not-allowed"
                    aria-label="{{ __('Decrease') }}"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" x2="19" y1="12" y2="12"></line></svg>
                </button>

                {{-- Number input --}}
                <input
                    type="number"
                    x-model.number="default_cancellation_window"
                    @change="updateWindow($event.target.value)"
                    min="0"
                    max="120"
                    step="1"
                    class="w-24 h-10 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong text-center font-mono text-lg
                           focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                >

                {{-- Increment button --}}
                <button
                    @click="incrementWindow()"
                    :disabled="default_cancellation_window >= 120"
                    class="w-10 h-10 rounded-lg border border-outline dark:border-outline flex items-center justify-center text-on-surface hover:bg-surface transition-colors
                           disabled:opacity-30 disabled:cursor-not-allowed"
                    aria-label="{{ __('Increase') }}"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="5" y2="19"></line><line x1="5" x2="19" y1="12" y2="12"></line></svg>
                </button>

                <span class="text-sm text-on-surface/60">{{ __('minutes') }}</span>
            </div>

            <p class="text-xs text-on-surface/40">{{ __('Range: 0 to 120 minutes (0 = no cancellation allowed)') }}</p>
        </div>
    </div>

    {{-- Section: Support --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
        <div class="flex items-center gap-3 mb-6">
            <span class="w-10 h-10 rounded-full bg-success-subtle flex items-center justify-center">
                <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.2 8.4c.5.38.8.97.8 1.6v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V10a2 2 0 0 1 .8-1.6l8-6a2 2 0 0 1 2.4 0l8 6Z"></path><path d="m22 10-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 10"></path></svg>
            </span>
            <div>
                <h3 class="text-base font-semibold text-on-surface-strong">{{ __('Support') }}</h3>
                <p class="text-sm text-on-surface/60">{{ __('Contact information displayed in help sections and notification emails') }}</p>
            </div>
        </div>

        <div class="space-y-5">
            {{-- Support Email --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label for="support_email" class="text-sm font-medium text-on-surface-strong">
                        {{ __('Support Email') }}
                    </label>
                    <span id="saved-support_email" class="text-xs font-semibold text-success opacity-0 transition-opacity duration-300">
                        <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        {{ __('Saved') }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <input
                        type="email"
                        id="support_email"
                        x-model="support_email"
                        class="flex-1 h-10 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40
                               focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm px-3"
                        placeholder="{{ __('e.g., support@dancymeals.com') }}"
                    >
                    <button
                        @click="saveSetting('support_email')"
                        :disabled="$fetching()"
                        class="h-10 px-4 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 inline-flex items-center gap-2
                               focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2
                               disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
                    >
                        <span x-show="$fetching() && setting_key === 'support_email'" class="animate-spin-slow">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                        </span>
                        <span x-show="!($fetching() && setting_key === 'support_email')">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        </span>
                        {{ __('Save') }}
                    </button>
                </div>
            </div>

            {{-- Support Phone --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label for="support_phone" class="text-sm font-medium text-on-surface-strong">
                        {{ __('Support Phone') }}
                    </label>
                    <span id="saved-support_phone" class="text-xs font-semibold text-success opacity-0 transition-opacity duration-300">
                        <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        {{ __('Saved') }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <input
                        type="tel"
                        id="support_phone"
                        x-model="support_phone"
                        maxlength="20"
                        class="flex-1 h-10 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40
                               focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm px-3"
                        placeholder="{{ __('e.g., +237 6XX XXX XXX') }}"
                    >
                    <button
                        @click="saveSetting('support_phone')"
                        :disabled="$fetching()"
                        class="h-10 px-4 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 inline-flex items-center gap-2
                               focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2
                               disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
                    >
                        <span x-show="$fetching() && setting_key === 'support_phone'" class="animate-spin-slow">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                        </span>
                        <span x-show="!($fetching() && setting_key === 'support_phone')">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        </span>
                        {{ __('Save') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Section: System --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
        <div class="flex items-center gap-3 mb-6">
            <span class="w-10 h-10 rounded-full bg-warning-subtle flex items-center justify-center">
                <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" x2="12" y1="9" y2="13"></line><line x1="12" x2="12.01" y1="17" y2="17"></line></svg>
            </span>
            <div>
                <h3 class="text-base font-semibold text-on-surface-strong">{{ __('System') }}</h3>
                <p class="text-sm text-on-surface/60">{{ __('Critical system settings') }}</p>
            </div>
        </div>

        <div class="space-y-5">
            {{-- Maintenance Mode Toggle --}}
            <div class="flex items-center justify-between py-3 border-b border-outline/50 dark:border-outline/50">
                <div class="flex-1 min-w-0 pr-4">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-medium text-on-surface-strong">{{ __('Maintenance Mode') }}</p>
                        <span id="saved-maintenance_mode" class="text-xs font-semibold text-success opacity-0 transition-opacity duration-300">
                            <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                            {{ __('Saved') }}
                        </span>
                    </div>
                    <p class="text-xs text-on-surface/60 mt-0.5">{{ __('Show a maintenance page to all users except admins and super-admins.') }}</p>
                    @if(! $isSuperAdmin)
                        <p class="text-xs text-warning mt-1 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line></svg>
                            {{ __('Only super-admins can enable maintenance mode') }}
                        </p>
                    @endif
                </div>
                <button
                    @click="
                        maintenance_mode = !maintenance_mode;
                        if (maintenance_mode) {
                            saveWithConfirm('maintenance_mode', '{{ __('Enable Maintenance Mode?') }}', '{{ __('This will show a maintenance page to all users except admins. Continue?') }}');
                        } else {
                            saveWithConfirm('maintenance_mode', '{{ __('Disable Maintenance Mode?') }}', '{{ __('The platform will return to normal operation. Continue?') }}');
                        }
                    "
                    type="button"
                    @if(! $isSuperAdmin) disabled @endif
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2
                           disabled:opacity-40 disabled:cursor-not-allowed"
                    :class="maintenance_mode ? 'bg-warning' : 'bg-outline'"
                    role="switch"
                    :aria-checked="maintenance_mode"
                    aria-label="{{ __('Toggle maintenance mode') }}"
                    @if(! $isSuperAdmin)
                        title="{{ __('Only super-admins can enable maintenance mode') }}"
                    @endif
                >
                    <span
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                        :class="maintenance_mode ? 'translate-x-5' : 'translate-x-0'"
                    ></span>
                </button>
            </div>

            {{-- Maintenance Reason (visible when maintenance mode is on or super-admin can set preemptively) --}}
            @if($isSuperAdmin)
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label for="maintenance_reason" class="text-sm font-medium text-on-surface-strong">
                            {{ __('Maintenance Reason') }}
                        </label>
                        <span id="saved-maintenance_reason" class="text-xs font-semibold text-success opacity-0 transition-opacity duration-300">
                            <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                            {{ __('Saved') }}
                        </span>
                    </div>
                    <p class="text-xs text-on-surface/60">{{ __('Displayed on the maintenance page. Optional.') }}</p>
                    <div class="flex gap-2">
                        <textarea
                            id="maintenance_reason"
                            x-model="maintenance_reason"
                            rows="2"
                            maxlength="500"
                            class="flex-1 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40
                                   focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm px-3 py-2 resize-none"
                            placeholder="{{ __('e.g., Scheduled maintenance for payment system upgrade.') }}"
                        ></textarea>
                        <button
                            @click="saveSetting('maintenance_reason')"
                            :disabled="$fetching()"
                            class="h-10 px-4 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 inline-flex items-center gap-2
                                   focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2
                                   disabled:opacity-50 disabled:cursor-not-allowed shrink-0 self-start"
                        >
                            <span x-show="$fetching() && setting_key === 'maintenance_reason'" class="animate-spin-slow">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                            </span>
                            <span x-show="!($fetching() && setting_key === 'maintenance_reason')">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                            </span>
                            {{ __('Save') }}
                        </button>
                    </div>
                </div>
            @endif

            {{-- Maintenance mode active banner --}}
            <div
                x-show="maintenance_mode"
                x-cloak
                x-transition
                class="rounded-lg border border-warning/30 bg-warning-subtle p-4"
            >
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-warning shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" x2="12" y1="9" y2="13"></line><line x1="12" x2="12.01" y1="17" y2="17"></line></svg>
                    <div>
                        <p class="text-sm font-semibold text-warning">{{ __('Maintenance mode is active') }}</p>
                        <p class="text-xs text-on-surface/60 mt-0.5">{{ __('Non-admin users are currently seeing a maintenance page.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Confirmation Modal --}}
    <div
        x-show="showConfirm"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @keydown.escape.window="cancelConfirm()"
    >
        {{-- Backdrop --}}
        <div
            class="fixed inset-0 bg-black/50 dark:bg-black/70"
            @click="cancelConfirm()"
        ></div>

        {{-- Modal content --}}
        <div class="relative bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-dropdown p-6 w-full max-w-md animate-scale-in">
            <div class="flex items-start gap-4">
                <span class="w-10 h-10 rounded-full bg-warning-subtle flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" x2="12" y1="9" y2="13"></line><line x1="12" x2="12.01" y1="17" y2="17"></line></svg>
                </span>
                <div class="flex-1 min-w-0">
                    <h4 class="text-base font-semibold text-on-surface-strong" x-text="confirmTitle"></h4>
                    <p class="text-sm text-on-surface mt-2" x-text="confirmMessage"></p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button
                    @click="cancelConfirm()"
                    type="button"
                    class="h-9 px-4 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface transition-all duration-200
                           focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    @click="executeConfirm()"
                    type="button"
                    class="h-9 px-4 text-sm rounded-lg font-semibold bg-warning hover:bg-warning/90 text-on-warning transition-all duration-200
                           focus:outline-none focus:ring-2 focus:ring-warning focus:ring-offset-2"
                >
                    {{ __('Confirm') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
