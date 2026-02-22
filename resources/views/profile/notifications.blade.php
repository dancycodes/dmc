{{--
    Notification Preferences Management (F-041)
    -------------------------------------------
    Matrix table of notification types vs. channels.
    Rows: Orders, Payments, Complaints, Promotions, System.
    Columns: Push, Email, Database (always ON, non-interactive).

    BR-175: 5 notification types.
    BR-176: Push and email are toggleable.
    BR-177: Database channel always ON, non-interactive.
    BR-178: First visit defaults all channels to ON.
    BR-179: Global per user, not tenant-scoped.
    BR-181: Changes take effect immediately.
    BR-182: Push toggleable only if browser push permission granted.
    BR-183: All text via __().
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Notification Preferences'))

@section('content')
<div
    class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12"
    x-data="{
        preferences: {{ json_encode(
            collect($preferences)->map(fn($pref) => [
                'push_enabled' => (bool) $pref->push_enabled,
                'email_enabled' => (bool) $pref->email_enabled,
            ])->toArray(),
            JSON_HEX_APOS | JSON_HEX_QUOT
        ) }},
        pushPermission: 'default',
        saving: false,
        init() {
            if ('Notification' in window) {
                this.pushPermission = Notification.permission;
            } else {
                this.pushPermission = 'denied';
            }
        },
        hasPushPermission() {
            return this.pushPermission === 'granted';
        },
        async requestPushPermission() {
            if (!('Notification' in window)) { return; }
            const result = await Notification.requestPermission();
            this.pushPermission = result;
        },
        savePreferences() {
            $action('/profile/notifications', {
                include: ['preferences']
            });
        }
    }"
    x-sync="['preferences']"
>
    {{-- Page Header --}}
    <div class="mb-6 sm:mb-8">
        <div class="flex items-center gap-3 mb-2">
            <a
                href="{{ url('/profile') }}"
                class="inline-flex items-center gap-1.5 text-sm text-on-surface hover:text-primary transition-colors"
                x-navigate
            >
                {{-- ChevronLeft icon --}}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m15 18-6-6 6-6"></path>
                </svg>
                {{ __('Profile') }}
            </a>
        </div>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-warning-subtle flex items-center justify-center shrink-0">
                {{-- Bell icon --}}
                <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
                    <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
                </svg>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-on-surface-strong font-display">
                    {{ __('Notification Preferences') }}
                </h1>
                <p class="text-sm text-on-surface mt-0.5">
                    {{ __('Choose how you receive alerts for each event type.') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Push Permission Banner (BR-182) --}}
    <div
        x-show="!hasPushPermission()"
        x-cloak
        class="mb-6 flex items-start gap-3 p-4 bg-info-subtle border border-info rounded-xl"
    >
        {{-- Info icon --}}
        <svg class="w-5 h-5 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M12 16v-4"></path>
            <path d="M12 8h.01"></path>
        </svg>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-info">{{ __('Push notification permission required') }}</p>
            <p class="text-sm text-on-surface mt-0.5">
                {{ __('Enable browser push notifications to receive real-time alerts. Without permission, push toggles cannot be activated.') }}
            </p>
        </div>
        <button
            type="button"
            @click="requestPushPermission()"
            class="shrink-0 inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-xs font-semibold bg-info text-on-info hover:opacity-90 transition-opacity"
        >
            {{ __('Enable') }}
        </button>
    </div>

    {{-- Main Preferences Card --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl shadow-card border border-outline overflow-hidden">

        {{-- Desktop Table View (hidden on mobile) --}}
        <div class="hidden sm:block">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-outline bg-surface dark:bg-surface">
                        <th class="text-left px-6 py-4 text-xs font-semibold text-on-surface uppercase tracking-wide w-1/2">
                            {{ __('Notification Type') }}
                        </th>
                        <th class="text-center px-4 py-4 text-xs font-semibold text-on-surface uppercase tracking-wide">
                            <div class="flex flex-col items-center gap-1">
                                {{-- Smartphone icon --}}
                                <svg class="w-4 h-4 text-on-surface" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="14" height="20" x="5" y="2" rx="2" ry="2"></rect>
                                    <path d="M12 18h.01"></path>
                                </svg>
                                {{ __('Push') }}
                            </div>
                        </th>
                        <th class="text-center px-4 py-4 text-xs font-semibold text-on-surface uppercase tracking-wide">
                            <div class="flex flex-col items-center gap-1">
                                {{-- Mail icon --}}
                                <svg class="w-4 h-4 text-on-surface" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                                </svg>
                                {{ __('Email') }}
                            </div>
                        </th>
                        <th class="text-center px-4 py-4 text-xs font-semibold text-on-surface uppercase tracking-wide">
                            <div class="flex flex-col items-center gap-1">
                                {{-- Database icon --}}
                                <svg class="w-4 h-4 text-on-surface" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                    <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                                    <path d="M3 12A9 3 0 0 0 21 12"></path>
                                </svg>
                                {{ __('In-App') }}
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline">
                    @foreach($types as $type)
                    <tr class="hover:bg-surface dark:hover:bg-surface transition-colors duration-150">
                        {{-- Type label + description --}}
                        <td class="px-6 py-4">
                            <p class="text-sm font-semibold text-on-surface-strong">
                                {{ __($typeLabels[$type]) }}
                            </p>
                            <p class="text-xs text-on-surface mt-0.5">
                                {{ __($typeDescriptions[$type]) }}
                            </p>
                        </td>

                        {{-- Push toggle (BR-182: disabled if no browser permission) --}}
                        <td class="px-4 py-4 text-center">
                            <div class="flex flex-col items-center gap-1.5">
                                <template x-if="hasPushPermission()">
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="preferences['{{ $type }}'].push_enabled ? 'true' : 'false'"
                                        :aria-label="'{{ __('Toggle push for') }} {{ __($typeLabels[$type]) }}'"
                                        @click="preferences['{{ $type }}'].push_enabled = !preferences['{{ $type }}'].push_enabled"
                                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                        :class="preferences['{{ $type }}'].push_enabled ? 'bg-primary' : 'bg-outline'"
                                    >
                                        <span
                                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out"
                                            :class="preferences['{{ $type }}'].push_enabled ? 'translate-x-5' : 'translate-x-0'"
                                        ></span>
                                    </button>
                                </template>
                                <template x-if="!hasPushPermission()">
                                    <span class="inline-flex items-center gap-1 text-xs text-on-surface opacity-60 italic">
                                        {{-- Lock icon --}}
                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                        </svg>
                                        {{ __('Required') }}
                                    </span>
                                </template>
                            </div>
                        </td>

                        {{-- Email toggle --}}
                        <td class="px-4 py-4 text-center">
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="preferences['{{ $type }}'].email_enabled ? 'true' : 'false'"
                                :aria-label="'{{ __('Toggle email for') }} {{ __($typeLabels[$type]) }}'"
                                @click="preferences['{{ $type }}'].email_enabled = !preferences['{{ $type }}'].email_enabled"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                :class="preferences['{{ $type }}'].email_enabled ? 'bg-primary' : 'bg-outline'"
                            >
                                <span
                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out"
                                    :class="preferences['{{ $type }}'].email_enabled ? 'translate-x-5' : 'translate-x-0'"
                                ></span>
                            </button>
                        </td>

                        {{-- Database (always ON, non-interactive) BR-177 --}}
                        <td class="px-4 py-4 text-center">
                            <div class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent bg-success/40 cursor-not-allowed" title="{{ __('In-app notifications are always active') }}">
                                <span class="pointer-events-none inline-block h-5 w-5 translate-x-5 transform rounded-full bg-success/60 shadow-lg ring-0 transition duration-200 ease-in-out"></span>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile Card View (visible only on mobile) --}}
        <div class="sm:hidden divide-y divide-outline">
            @foreach($types as $type)
            <div class="p-4">
                {{-- Type header --}}
                <div class="mb-3">
                    <p class="text-sm font-semibold text-on-surface-strong">{{ __($typeLabels[$type]) }}</p>
                    <p class="text-xs text-on-surface mt-0.5">{{ __($typeDescriptions[$type]) }}</p>
                </div>

                {{-- Channel toggles in a row --}}
                <div class="flex items-center gap-4">
                    {{-- Push --}}
                    <div class="flex items-center gap-2 flex-1">
                        <template x-if="hasPushPermission()">
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="preferences['{{ $type }}'].push_enabled ? 'true' : 'false'"
                                @click="preferences['{{ $type }}'].push_enabled = !preferences['{{ $type }}'].push_enabled"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                :class="preferences['{{ $type }}'].push_enabled ? 'bg-primary' : 'bg-outline'"
                            >
                                <span
                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out"
                                    :class="preferences['{{ $type }}'].push_enabled ? 'translate-x-5' : 'translate-x-0'"
                                ></span>
                            </button>
                        </template>
                        <template x-if="!hasPushPermission()">
                            <div
                                class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent bg-outline/40 cursor-not-allowed"
                                title="{{ __('Permission required') }}"
                            >
                                <span class="pointer-events-none inline-block h-5 w-5 translate-x-0 transform rounded-full bg-white/60 shadow-lg ring-0"></span>
                            </div>
                        </template>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-on-surface">{{ __('Push') }}</p>
                            <template x-if="!hasPushPermission()">
                                <p class="text-xs text-on-surface opacity-60 italic">{{ __('Permission required') }}</p>
                            </template>
                        </div>
                    </div>

                    {{-- Email --}}
                    <div class="flex items-center gap-2 flex-1">
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="preferences['{{ $type }}'].email_enabled ? 'true' : 'false'"
                            @click="preferences['{{ $type }}'].email_enabled = !preferences['{{ $type }}'].email_enabled"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                            :class="preferences['{{ $type }}'].email_enabled ? 'bg-primary' : 'bg-outline'"
                        >
                            <span
                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out"
                                :class="preferences['{{ $type }}'].email_enabled ? 'translate-x-5' : 'translate-x-0'"
                            ></span>
                        </button>
                        <p class="text-xs font-medium text-on-surface">{{ __('Email') }}</p>
                    </div>

                    {{-- In-App (always on) --}}
                    <div class="flex items-center gap-2 flex-1">
                        <div
                            class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent bg-success/40 cursor-not-allowed"
                            title="{{ __('In-app notifications are always active') }}"
                        >
                            <span class="pointer-events-none inline-block h-5 w-5 translate-x-5 transform rounded-full bg-success/60 shadow-lg ring-0"></span>
                        </div>
                        <p class="text-xs font-medium text-on-surface opacity-60">{{ __('In-App') }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Footer: Database note + Save button --}}
        <div class="px-4 sm:px-6 py-4 border-t border-outline bg-surface dark:bg-surface flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            {{-- In-app always-on note --}}
            <p class="text-xs text-on-surface flex items-center gap-1.5">
                {{-- Database icon --}}
                <svg class="w-3.5 h-3.5 text-success shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                    <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                    <path d="M3 12A9 3 0 0 0 21 12"></path>
                </svg>
                {{ __('In-app notifications are always active and cannot be turned off.') }}
            </p>

            {{-- Save button --}}
            <button
                type="button"
                @click="savePreferences()"
                class="inline-flex items-center gap-2 h-10 px-6 rounded-lg text-sm font-semibold bg-primary text-on-primary hover:bg-primary-hover transition-colors duration-200 disabled:opacity-60 disabled:cursor-not-allowed shrink-0"
                :disabled="$fetching()"
            >
                {{-- Loading state --}}
                <template x-if="$fetching()">
                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
                    </svg>
                </template>
                <template x-if="!$fetching()">
                    {{-- Save icon --}}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                        <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                        <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                    </svg>
                </template>
                <span x-text="$fetching() ? @js(__('Saving...')) : @js(__('Save Preferences'))"></span>
            </button>
        </div>
    </div>
</div>
@endsection
