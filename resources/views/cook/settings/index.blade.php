{{--
    Cook Settings Page
    ------------------
    F-212: Cancellation Window Configuration
    F-213: Minimum Order Amount Configuration
    F-214: Cook Theme Selection

    The cook can configure the time window (in minutes) during which clients can
    cancel their orders for a full refund, the minimum order amount clients
    must meet before they can proceed to checkout, and the visual appearance
    of their tenant site (theme, font, border radius).

    BR-494: Default cancellation window is 15 minutes.
    BR-495: Allowed range: 5 to 120 minutes (inclusive).
    BR-496: Value must be a whole number (integer minutes).
    BR-497: Setting applies to all new orders from the moment it is saved.
    BR-503: Only the cook can modify cancellation window (not managers).
    BR-504: All changes are logged via Spatie Activitylog.
    BR-505: All user-facing text must use __() localization.
    BR-506: Gale handles the setting form interaction without page reloads.

    BR-507: Default minimum order amount is 0 XAF (no minimum).
    BR-508: Allowed range: 0 to 100,000 XAF (inclusive).
    BR-509: Value must be a whole number (integer XAF).
    BR-515: Only the cook can modify minimum order amount (not managers).
    BR-517: All changes logged via Spatie Activitylog.
    BR-518: All user-facing text must use __() localization.

    BR-520: Valid themes from TenantThemeService.
    BR-521: Valid fonts from TenantThemeService.
    BR-522: Valid border radius options from TenantThemeService.
    BR-523: Live preview updates via Gale as selections change.
    BR-524: Changes apply to tenant domain immediately on save.
    BR-525: Stored in tenant.settings JSON: theme, font, border_radius.
    BR-526: Only the cook can change theme settings (not managers).
    BR-527: Reset to Default = Modern + Inter + medium.
    BR-528: Preview shows light AND dark mode variants.
    BR-529: Theme applies to tenant public site ONLY (not dashboard).
    BR-530: All changes logged via Spatie Activitylog.
    BR-531: All user-facing text must use __() localization.
    BR-532: Gale handles all preview and save interactions without page reloads.
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Settings'))

@section('content')
<div class="max-w-3xl mx-auto py-6 sm:py-8 px-4 sm:px-6 lg:px-8">

    {{-- Page Header --}}
    <div class="mb-6 sm:mb-8">
        <h1 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
            {{ __('Settings') }}
        </h1>
        <p class="mt-1.5 text-sm text-on-surface">
            {{ __('Configure your store preferences.') }}
        </p>
    </div>

    <div class="space-y-6">

        {{-- Cancellation Window Card --}}
        <div
            x-data="{
                cancellation_window_minutes: {{ (int) $cancellationWindowMinutes }},
                cancellation_saving: false,
                cancellation_saved: false,
                error: '',

                increment() {
                    if (this.cancellation_window_minutes < {{ \App\Services\CookSettingsService::MAX_CANCELLATION_WINDOW }}) {
                        this.cancellation_window_minutes = Math.min({{ \App\Services\CookSettingsService::MAX_CANCELLATION_WINDOW }}, this.cancellation_window_minutes + 5);
                    }
                },

                decrement() {
                    if (this.cancellation_window_minutes > {{ \App\Services\CookSettingsService::MIN_CANCELLATION_WINDOW }}) {
                        this.cancellation_window_minutes = Math.max({{ \App\Services\CookSettingsService::MIN_CANCELLATION_WINDOW }}, this.cancellation_window_minutes - 5);
                    }
                },

                save() {
                    this.error = '';
                    this.cancellation_saved = false;
                    $action('{{ route('cook.settings.update-cancellation-window') }}', {
                        include: ['cancellation_window_minutes']
                    });
                }
            }"
            x-sync="['cancellation_window_minutes', 'cancellation_saved']"
            class="bg-surface dark:bg-surface rounded-2xl border border-outline dark:border-outline shadow-card overflow-hidden"
        >
            {{-- Card Header --}}
            <div class="px-5 sm:px-6 py-4 sm:py-5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-lg bg-primary-subtle flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-on-surface-strong">
                            {{ __('Cancellation Window') }}
                        </h2>
                        <p class="text-xs text-on-surface mt-0.5">
                            {{ __('How long after placing an order can a client cancel for a full refund?') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Card Body --}}
            <div class="px-5 sm:px-6 py-5 sm:py-6">

                {{-- Validation error --}}
                <div
                    x-show="error !== ''"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="mb-4 flex items-start gap-2 bg-danger-subtle dark:bg-danger-subtle border border-danger/30 rounded-lg px-4 py-3"
                    x-cloak
                >
                    <svg class="w-4 h-4 text-danger mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <p class="text-sm text-danger" x-text="error"></p>
                </div>

                {{-- Gale validation error (x-message pattern) --}}
                <div class="mb-4">
                    <p x-message="cancellation_window_minutes" class="text-sm text-danger"></p>
                </div>

                {{-- Number input with stepper controls --}}
                <div class="flex flex-col sm:flex-row sm:items-end gap-4 sm:gap-6">
                    <div class="flex-1">
                        <label for="cancellation_window_minutes" class="block text-sm font-medium text-on-surface-strong mb-2">
                            {{ __('Window Duration') }}
                        </label>
                        <div class="flex items-center gap-2">
                            {{-- Decrement button --}}
                            <button
                                type="button"
                                @click="decrement()"
                                :disabled="cancellation_window_minutes <= {{ \App\Services\CookSettingsService::MIN_CANCELLATION_WINDOW }}"
                                class="w-10 h-10 flex items-center justify-center rounded-lg border border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt text-on-surface hover:bg-primary-subtle hover:text-primary hover:border-primary/50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors duration-200"
                                :title="'{{ __('Decrease') }}'"
                            >
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            </button>

                            {{-- Number input --}}
                            <div class="relative flex items-center">
                                <input
                                    type="number"
                                    id="cancellation_window_minutes"
                                    x-model.number="cancellation_window_minutes"
                                    x-name="cancellation_window_minutes"
                                    min="{{ \App\Services\CookSettingsService::MIN_CANCELLATION_WINDOW }}"
                                    max="{{ \App\Services\CookSettingsService::MAX_CANCELLATION_WINDOW }}"
                                    step="1"
                                    class="w-24 h-10 text-center text-lg font-semibold text-on-surface-strong bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                >
                            </div>

                            {{-- Increment button --}}
                            <button
                                type="button"
                                @click="increment()"
                                :disabled="cancellation_window_minutes >= {{ \App\Services\CookSettingsService::MAX_CANCELLATION_WINDOW }}"
                                class="w-10 h-10 flex items-center justify-center rounded-lg border border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt text-on-surface hover:bg-primary-subtle hover:text-primary hover:border-primary/50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors duration-200"
                                :title="'{{ __('Increase') }}'"
                            >
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            </button>

                            {{-- Unit label --}}
                            <span class="text-sm text-on-surface font-medium ml-1">
                                {{ __('minutes') }}
                            </span>
                        </div>

                        {{-- Range hint --}}
                        <p class="mt-2 text-xs text-on-surface opacity-70">
                            {{ __('Between :min and :max minutes.', ['min' => \App\Services\CookSettingsService::MIN_CANCELLATION_WINDOW, 'max' => \App\Services\CookSettingsService::MAX_CANCELLATION_WINDOW]) }}
                        </p>
                    </div>

                    {{-- Save button --}}
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="save()"
                            :disabled="$fetching()"
                            class="flex items-center gap-2 px-5 py-2.5 bg-primary text-on-primary font-semibold text-sm rounded-lg hover:bg-primary-hover disabled:opacity-60 disabled:cursor-not-allowed transition-colors duration-200 shadow-sm"
                        >
                            <span x-show="!$fetching()">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                            </span>
                            <span x-show="$fetching()" x-cloak>
                                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                            </span>
                            <span x-show="!$fetching()">{{ __('Save') }}</span>
                            <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
                        </button>

                        {{-- Saved confirmation --}}
                        <span
                            x-show="cancellation_saved"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="flex items-center gap-1.5 text-sm font-medium text-success"
                            x-cloak
                        >
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            {{ __('Saved!') }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Info footer --}}
            <div class="px-5 sm:px-6 py-3 bg-surface-alt dark:bg-surface-alt border-t border-outline dark:border-outline">
                <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-info mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                    <p class="text-xs text-on-surface">
                        {{ __('Changes apply to new orders only. Existing orders keep the window that was active when they were placed.') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Minimum Order Amount Card --}}
        {{-- BR-507: Default 0 XAF (no minimum) --}}
        {{-- BR-508: Range 0–100,000 XAF inclusive --}}
        {{-- BR-509: Integer (whole number) only --}}
        {{-- BR-515: Only cook can modify --}}
        {{-- BR-517: Changes logged via Spatie Activitylog --}}
        <div
            x-data="{
                minimum_order_amount: {{ (int) $minimumOrderAmount }},
                minimum_saved: false,

                save() {
                    this.minimum_saved = false;
                    $action('{{ route('cook.settings.update-minimum-order-amount') }}', {
                        include: ['minimum_order_amount']
                    });
                }
            }"
            x-sync="['minimum_order_amount', 'minimum_saved']"
            class="bg-surface dark:bg-surface rounded-2xl border border-outline dark:border-outline shadow-card overflow-hidden"
        >
            {{-- Card Header --}}
            <div class="px-5 sm:px-6 py-4 sm:py-5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-lg bg-secondary-subtle flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-on-surface-strong">
                            {{ __('Minimum Order Amount') }}
                        </h2>
                        <p class="text-xs text-on-surface mt-0.5">
                            {{ __('Set the minimum amount clients must order. Set to 0 for no minimum.') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Card Body --}}
            <div class="px-5 sm:px-6 py-5 sm:py-6">

                {{-- Gale validation error --}}
                <div class="mb-4">
                    <p x-message="minimum_order_amount" class="text-sm text-danger"></p>
                </div>

                {{-- Input row --}}
                <div class="flex flex-col sm:flex-row sm:items-end gap-4 sm:gap-6">
                    <div class="flex-1">
                        <label for="minimum_order_amount" class="block text-sm font-medium text-on-surface-strong mb-2">
                            {{ __('Minimum Amount') }}
                        </label>
                        <div class="flex items-center gap-2">
                            {{-- Number input --}}
                            <div class="relative flex items-center flex-1 max-w-xs">
                                <input
                                    type="number"
                                    id="minimum_order_amount"
                                    x-model.number="minimum_order_amount"
                                    x-name="minimum_order_amount"
                                    min="{{ \App\Services\CookSettingsService::MIN_ORDER_AMOUNT }}"
                                    max="{{ \App\Services\CookSettingsService::MAX_ORDER_AMOUNT }}"
                                    step="1"
                                    placeholder="0"
                                    class="w-full h-10 px-3 text-left text-base font-semibold text-on-surface-strong bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                >
                            </div>

                            {{-- Unit label --}}
                            <span class="text-sm text-on-surface font-semibold shrink-0">
                                {{ __('XAF') }}
                            </span>
                        </div>

                        {{-- Range hint --}}
                        <p class="mt-2 text-xs text-on-surface opacity-70">
                            {{ __('Between :min and :max XAF. Use 0 to disable the minimum.', [
                                'min' => number_format(\App\Services\CookSettingsService::MIN_ORDER_AMOUNT),
                                'max' => number_format(\App\Services\CookSettingsService::MAX_ORDER_AMOUNT),
                            ]) }}
                        </p>
                    </div>

                    {{-- Save button --}}
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="save()"
                            :disabled="$fetching()"
                            class="flex items-center gap-2 px-5 py-2.5 bg-primary text-on-primary font-semibold text-sm rounded-lg hover:bg-primary-hover disabled:opacity-60 disabled:cursor-not-allowed transition-colors duration-200 shadow-sm"
                        >
                            <span x-show="!$fetching()">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                            </span>
                            <span x-show="$fetching()" x-cloak>
                                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                            </span>
                            <span x-show="!$fetching()">{{ __('Save') }}</span>
                            <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
                        </button>

                        {{-- Saved confirmation --}}
                        <span
                            x-show="minimum_saved"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="flex items-center gap-1.5 text-sm font-medium text-success"
                            x-cloak
                        >
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            {{ __('Saved!') }}
                        </span>
                    </div>
                </div>

                {{-- Live preview when minimum > 0 --}}
                <template x-if="minimum_order_amount > 0">
                    <div class="mt-4 flex items-center gap-2 bg-info-subtle dark:bg-info-subtle border border-info/30 rounded-lg px-4 py-3">
                        <svg class="w-4 h-4 text-info shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                        <p class="text-xs text-on-surface">
                            {{ __('Clients will need at least') }}
                            <strong class="font-semibold text-on-surface-strong" x-text="new Intl.NumberFormat('en').format(minimum_order_amount) + ' XAF'"></strong>
                            {{ __('in their cart to proceed to checkout.') }}
                        </p>
                    </div>
                </template>
            </div>
        </div>

        {{-- ================================================================= --}}
        {{-- Appearance Card (F-214: Cook Theme Selection) --}}
        {{-- ================================================================= --}}
        {{-- BR-523: Live preview updates via Gale as selections change --}}
        {{-- BR-526: Cook only; managers cannot access (COOK_RESERVED_PATHS) --}}
        {{-- BR-532: Gale handles all preview and save interactions --}}
        @php
        $presetColorsJson = json_encode($presetColors, JSON_HEX_APOS | JSON_HEX_QUOT);
        $presetFontFamilies = [];
        foreach ($availableFonts as $fontKey => $fontData) {
            $fontCfg = config("tenant-themes.fonts.{$fontKey}");
            $presetFontFamilies[$fontKey] = $fontCfg['family'] ?? "'Inter', sans-serif";
        }
        $fontFamiliesJson = json_encode($presetFontFamilies, JSON_HEX_APOS | JSON_HEX_QUOT);
        $presetNamesJson = json_encode(array_keys($availablePresets), JSON_HEX_APOS | JSON_HEX_QUOT);
        @endphp

        <div
            x-data="{
                theme: '{{ $appearance['theme'] }}',
                font: '{{ $appearance['font'] }}',
                border_radius: '{{ $appearance['border_radius'] }}',
                appearance_saved: false,
                previewDark: false,
                presetColors: {{ $presetColorsJson }},
                fontFamilies: {{ $fontFamiliesJson }},

                save() {
                    this.appearance_saved = false;
                    $action('{{ route('cook.settings.update-appearance') }}', {
                        include: ['theme', 'font', 'border_radius']
                    });
                },

                reset() {
                    $action('{{ route('cook.settings.reset-appearance') }}');
                },

                getSwatchBg(presetKey) {
                    return this.presetColors[presetKey]
                        ? this.presetColors[presetKey].primary
                        : '#0D9488';
                },

                getSwatchSecondary(presetKey) {
                    return this.presetColors[presetKey]
                        ? this.presetColors[presetKey].secondary
                        : '#F59E0B';
                },

                getSwatchSubtle(presetKey) {
                    return this.presetColors[presetKey]
                        ? this.presetColors[presetKey].primary_subtle
                        : '#F0FDFA';
                },

                getPreviewFontFamily() {
                    return this.fontFamilies[this.font] || 'system-ui, sans-serif';
                },

                getPreviewPrimary() {
                    if (!this.presetColors[this.theme]) { return '#0D9488'; }
                    return this.previewDark
                        ? (this.getPreviewDarkColor('primary') || this.presetColors[this.theme].primary)
                        : this.presetColors[this.theme].primary;
                },

                getPreviewRadius() {
                    const radiusMap = { none: '0px', small: '4px', medium: '8px', large: '12px', full: '16px' };
                    return radiusMap[this.border_radius] || '8px';
                },

                getPreviewDarkColor(key) {
                    const darkColors = {
                        modern: { primary: '#14B8A6' },
                        arctic: { primary: '#38BDF8' },
                        'high-contrast': { primary: '#FFFFFF' },
                        minimal: { primary: '#D4D4D4' },
                        'neo-brutalism': { primary: '#FB7185' },
                        ocean: { primary: '#3B82F6' },
                        forest: { primary: '#10B981' },
                        sunset: { primary: '#F43F5E' },
                        violet: { primary: '#8B5CF6' }
                    };
                    return darkColors[this.theme] ? darkColors[this.theme][key] : null;
                },

                getPreviewBg() {
                    return this.previewDark ? '#18181B' : '#FFFFFF';
                },

                getPreviewSurface() {
                    return this.previewDark ? '#27272A' : '#F4F4F5';
                },

                getPreviewText() {
                    return this.previewDark ? '#E4E4E7' : '#18181B';
                },

                getPreviewSubText() {
                    return this.previewDark ? '#A1A1AA' : '#71717A';
                }
            }"
            x-sync="['theme', 'font', 'border_radius', 'appearance_saved']"
            class="bg-surface dark:bg-surface rounded-2xl border border-outline dark:border-outline shadow-card overflow-hidden"
        >
            {{-- Card Header --}}
            <div class="px-5 sm:px-6 py-4 sm:py-5 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-lg bg-primary-subtle flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 2a10 10 0 0 1 9.8 8H2.2A10 10 0 0 1 12 2z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-on-surface-strong">
                            {{ __('Appearance') }}
                        </h2>
                        <p class="text-xs text-on-surface mt-0.5">
                            {{ __('Customize the visual style of your public site.') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Card Body --}}
            <div class="px-5 sm:px-6 py-5 sm:py-6 space-y-6">

                {{-- ============================================================ --}}
                {{-- Theme Grid (BR-520) --}}
                {{-- ============================================================ --}}
                <div>
                    <label class="block text-sm font-semibold text-on-surface-strong mb-3">
                        {{ __('Theme') }}
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                        @foreach($availablePresets as $presetKey => $presetData)
                        <button
                            type="button"
                            @click="theme = '{{ $presetKey }}'; appearance_saved = false"
                            :class="theme === '{{ $presetKey }}'
                                ? 'ring-2 ring-primary border-primary bg-primary-subtle dark:bg-primary-subtle'
                                : 'border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt hover:border-primary/50 hover:bg-primary-subtle/50'"
                            class="relative flex flex-col items-center gap-2.5 p-3 rounded-xl border-2 transition-all duration-200 cursor-pointer group text-left"
                            :title="'{{ $presetData['label'] }}'"
                        >
                            {{-- Selected checkmark --}}
                            <span
                                x-show="theme === '{{ $presetKey }}'"
                                class="absolute top-2 right-2 w-5 h-5 rounded-full bg-primary flex items-center justify-center"
                                x-cloak
                            >
                                <svg class="w-3 h-3 text-on-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            </span>

                            {{-- Color swatches --}}
                            <div class="flex items-center gap-1.5">
                                {{-- Primary swatch --}}
                                <span
                                    class="w-7 h-7 rounded-full border-2 border-white dark:border-zinc-800 shadow-sm shrink-0"
                                    style="background-color: {{ $presetColors[$presetKey]['primary'] ?? '#0D9488' }}"
                                ></span>
                                {{-- Secondary swatch --}}
                                <span
                                    class="w-5 h-5 rounded-full border-2 border-white dark:border-zinc-800 shadow-sm shrink-0"
                                    style="background-color: {{ $presetColors[$presetKey]['secondary'] ?? '#F59E0B' }}"
                                ></span>
                                {{-- Subtle swatch --}}
                                <span
                                    class="w-4 h-4 rounded-full border border-outline dark:border-outline shadow-sm shrink-0"
                                    style="background-color: {{ $presetColors[$presetKey]['primary_subtle'] ?? '#F0FDFA' }}"
                                ></span>
                            </div>

                            {{-- Theme name --}}
                            <span class="text-xs font-medium text-on-surface-strong text-center leading-tight">
                                {{ __($presetData['label']) }}
                            </span>
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- ============================================================ --}}
                {{-- Font Selector (BR-521) --}}
                {{-- ============================================================ --}}
                <div>
                    <label for="appearance_font" class="block text-sm font-semibold text-on-surface-strong mb-3">
                        {{ __('Font') }}
                    </label>
                    <div class="relative max-w-xs">
                        <select
                            id="appearance_font"
                            x-model="font"
                            @change="appearance_saved = false"
                            class="w-full h-11 pl-4 pr-10 bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl text-sm text-on-surface-strong focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200 cursor-pointer appearance-none"
                        >
                            @foreach($availableFonts as $fontKey => $fontData)
                            <option value="{{ $fontKey }}">{{ $fontData['label'] }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                            <svg class="w-4 h-4 text-on-surface" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                    </div>
                    {{-- Font preview text --}}
                    <p
                        class="mt-2 text-sm text-on-surface"
                        :style="'font-family: ' + getPreviewFontFamily()"
                    >
                        {{ __('The quick brown fox jumps over the lazy dog.') }}
                    </p>
                </div>

                {{-- ============================================================ --}}
                {{-- Border Radius Segmented Control (BR-522) --}}
                {{-- ============================================================ --}}
                <div>
                    <label class="block text-sm font-semibold text-on-surface-strong mb-3">
                        {{ __('Border Radius') }}
                    </label>
                    <div class="flex items-center gap-1 p-1 bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline w-fit">
                        @foreach($availableRadii as $radiusKey => $radiusData)
                        <button
                            type="button"
                            @click="border_radius = '{{ $radiusKey }}'; appearance_saved = false"
                            :class="border_radius === '{{ $radiusKey }}'
                                ? 'bg-primary text-on-primary shadow-sm'
                                : 'text-on-surface hover:text-on-surface-strong hover:bg-surface dark:hover:bg-surface'"
                            class="flex flex-col items-center gap-1 px-3 py-2 rounded-lg text-xs font-medium transition-all duration-200 min-w-[52px]"
                        >
                            {{-- Radius visual icon --}}
                            @if($radiusKey === 'none')
                            <span class="w-4 h-4 border-2 border-current" style="border-radius: 0px"></span>
                            @elseif($radiusKey === 'small')
                            <span class="w-4 h-4 border-2 border-current" style="border-radius: 3px"></span>
                            @elseif($radiusKey === 'medium')
                            <span class="w-4 h-4 border-2 border-current" style="border-radius: 6px"></span>
                            @elseif($radiusKey === 'large')
                            <span class="w-4 h-4 border-2 border-current" style="border-radius: 10px"></span>
                            @else
                            <span class="w-4 h-4 border-2 border-current rounded-full"></span>
                            @endif
                            {{ __($radiusData['label']) }}
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- ============================================================ --}}
                {{-- Live Preview Panel (BR-523, BR-528) --}}
                {{-- ============================================================ --}}
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <label class="block text-sm font-semibold text-on-surface-strong">
                            {{ __('Preview') }}
                        </label>
                        {{-- Light/Dark preview toggle (BR-528) --}}
                        <div class="flex items-center gap-1 p-1 bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline">
                            <button
                                type="button"
                                @click="previewDark = false"
                                :class="!previewDark ? 'bg-surface dark:bg-surface shadow-sm text-on-surface-strong' : 'text-on-surface hover:text-on-surface-strong'"
                                class="flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium transition-all duration-200"
                            >
                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                                {{ __('Light') }}
                            </button>
                            <button
                                type="button"
                                @click="previewDark = true"
                                :class="previewDark ? 'bg-surface dark:bg-surface shadow-sm text-on-surface-strong' : 'text-on-surface hover:text-on-surface-strong'"
                                class="flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium transition-all duration-200"
                            >
                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                                {{ __('Dark') }}
                            </button>
                        </div>
                    </div>

                    {{-- Preview miniature landing page --}}
                    <div
                        class="rounded-xl overflow-hidden border border-outline dark:border-outline shadow-card"
                        :style="'background-color: ' + getPreviewBg()"
                    >
                        {{-- Preview Hero --}}
                        <div
                            class="px-5 py-5"
                            :style="'background-color: ' + getPreviewPrimary() + '20; border-bottom: 1px solid ' + getPreviewPrimary() + '30'"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div
                                        class="text-base font-bold mb-1 leading-tight"
                                        :style="'font-family: ' + getPreviewFontFamily() + '; color: ' + getPreviewText()"
                                    >
                                        {{ __('My Kitchen') }}
                                    </div>
                                    <div
                                        class="text-xs mb-3 leading-snug"
                                        :style="'font-family: ' + getPreviewFontFamily() + '; color: ' + getPreviewSubText()"
                                    >
                                        {{ __('Homemade meals delivered fresh to your door.') }}
                                    </div>
                                    <button
                                        type="button"
                                        class="px-3 py-1.5 text-xs font-semibold shadow-sm transition-all duration-200"
                                        :style="'font-family: ' + getPreviewFontFamily() + '; background-color: ' + getPreviewPrimary() + '; color: #FFFFFF; border-radius: ' + getPreviewRadius()"
                                    >
                                        {{ __('Order Now') }}
                                    </button>
                                </div>
                                {{-- Meal card preview --}}
                                <div
                                    class="shrink-0 w-28 p-2.5 border shadow-sm"
                                    :style="'background-color: ' + getPreviewSurface() + '; border-color: ' + getPreviewPrimary() + '25; border-radius: ' + getPreviewRadius()"
                                >
                                    <div
                                        class="w-full h-14 mb-2 flex items-center justify-center"
                                        :style="'background-color: ' + getPreviewPrimary() + '15; border-radius: calc(' + getPreviewRadius() + ' - 2px)'"
                                    >
                                        <svg
                                            class="w-6 h-6 opacity-40"
                                            :style="'color: ' + getPreviewPrimary()"
                                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                                        >
                                            <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path>
                                            <path d="M7 2v20"></path>
                                            <path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path>
                                        </svg>
                                    </div>
                                    <div
                                        class="text-xs font-semibold mb-1 truncate"
                                        :style="'font-family: ' + getPreviewFontFamily() + '; color: ' + getPreviewText()"
                                    >{{ __('Ndole Special') }}</div>
                                    <div
                                        class="text-xs font-bold"
                                        :style="'color: ' + getPreviewPrimary() + '; font-family: ' + getPreviewFontFamily()"
                                    >2,500 XAF</div>
                                </div>
                            </div>
                        </div>

                        {{-- Preview caption --}}
                        <div
                            class="px-4 py-2 text-center"
                            :style="'background-color: ' + getPreviewBg()"
                        >
                            <span
                                class="text-xs"
                                :style="'color: ' + getPreviewSubText() + '; font-family: ' + getPreviewFontFamily()"
                            >
                                {{ __('Preview of your public site') }}
                            </span>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Card Footer: Save + Reset buttons --}}
            <div class="px-5 sm:px-6 py-4 border-t border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    {{-- Left: Reset to Default --}}
                    <button
                        type="button"
                        @click="reset()"
                        :disabled="$fetching()"
                        class="text-sm text-on-surface hover:text-on-surface-strong underline underline-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                    >
                        {{ __('Reset to Default') }}
                    </button>

                    {{-- Right: Save + confirmation --}}
                    <div class="flex items-center gap-3">
                        {{-- Saved confirmation --}}
                        <span
                            x-show="appearance_saved"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="flex items-center gap-1.5 text-sm font-medium text-success"
                            x-cloak
                        >
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            {{ __('Saved!') }}
                        </span>

                        <button
                            type="button"
                            @click="save()"
                            :disabled="$fetching()"
                            class="flex items-center gap-2 px-5 py-2.5 bg-primary text-on-primary font-semibold text-sm rounded-lg hover:bg-primary-hover disabled:opacity-60 disabled:cursor-not-allowed transition-colors duration-200 shadow-sm"
                        >
                            <span x-show="!$fetching()">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                            </span>
                            <span x-show="$fetching()" x-cloak>
                                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                            </span>
                            <span x-show="!$fetching()">{{ __('Save Appearance') }}</span>
                            <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
                        </button>
                    </div>
                </div>

                {{-- Note about where the theme applies --}}
                <div class="mt-3 flex items-start gap-2">
                    <svg class="w-4 h-4 text-info mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                    <p class="text-xs text-on-surface">
                        {{ __('These appearance settings apply to your public-facing site only, not this dashboard.') }}
                    </p>
                </div>
            </div>
        </div>
        {{-- End Appearance Card --}}

    </div>

</div>
@endsection
