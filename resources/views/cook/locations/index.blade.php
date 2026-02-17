{{--
    Locations -- Town List & Add Town
    --------------------------------
    F-082: Add Town
    F-083: Town List View

    Allows the cook to view existing towns and add new ones to their delivery areas.
    Each town is scoped to the tenant via the delivery_areas junction table.

    BR-207: Town name required in both EN and FR
    BR-208: Town name must be unique within this cook's towns (per language)
    BR-209: Town is scoped to the current tenant (tenant_id via delivery_areas)
    BR-210: Save via Gale; town appears in list without page reload
    BR-211: All validation messages use __() localization
    BR-212: Only users with location management permission can access
    BR-213: Town list shows all towns for the current tenant
    BR-214: Each town entry displays: town name (locale), quarter count, edit link, delete link
    BR-215: Towns are displayed in alphabetical order by name in the current locale
    BR-216: Clicking a town navigates to or expands its quarter management view
    BR-217: Empty state shown when no towns exist
    BR-218: Town list updates via Gale when towns are added or removed (no page reload)
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Locations'))
@section('page-title', __('Locations'))

@section('content')
<div class="max-w-4xl mx-auto">
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-6" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ url('/dashboard') }}" class="hover:text-primary transition-colors duration-200">
            {{ __('Dashboard') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <span class="text-on-surface-strong font-medium">{{ __('Locations') }}</span>
    </nav>

    {{-- Success Toast --}}
    @if(session('success'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 4000)"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="mb-6 p-4 rounded-lg border bg-success-subtle border-success/30 text-success flex items-center gap-3"
            role="alert"
        >
            {{-- Lucide: check-circle --}}
            <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
            <p class="text-sm font-medium">{{ session('success') }}</p>
        </div>
    @endif

    {{-- Locations Page Content --}}
    <div
        x-data="{
            showAddTownForm: false,
            name_en: '',
            name_fr: '',
            expandedTown: null,
            confirmDeleteId: null,
            resetForm() {
                this.name_en = '';
                this.name_fr = '';
            },
            toggleTown(areaId) {
                this.expandedTown = this.expandedTown === areaId ? null : areaId;
            }
        }"
        x-sync="['name_en', 'name_fr']"
    >
        {{-- Header with Add Town button --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl font-semibold text-on-surface-strong">{{ __('Delivery Towns') }}</h2>
                <p class="text-sm text-on-surface mt-1">{{ __('Manage the towns and areas where you offer delivery.') }}</p>
            </div>
            <button
                x-on:click="showAddTownForm = !showAddTownForm; if (showAddTownForm) { $nextTick(() => $refs.nameEnInput.focus()) }"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-primary text-on-primary text-sm font-medium hover:bg-primary-hover transition-colors duration-200 shadow-sm self-start sm:self-auto"
            >
                {{-- Lucide: plus --}}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                <span x-text="showAddTownForm ? '{{ __('Cancel') }}' : '{{ __('Add Town') }}'"></span>
            </button>
        </div>

        {{-- Add Town Form (Inline Expandable) --}}
        <div
            x-show="showAddTownForm"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            class="mb-6"
        >
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-5 shadow-card">
                <h3 class="text-sm font-semibold text-on-surface-strong mb-4 flex items-center gap-2">
                    {{-- Lucide: map-pin-plus --}}
                    <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    {{ __('Add New Town') }}
                </h3>

                <form @submit.prevent="$action('{{ url('/dashboard/locations/towns') }}')" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- English Town Name --}}
                        <div>
                            <label for="town-name-en" class="block text-sm font-medium text-on-surface mb-1.5">
                                {{ __('Town Name (English)') }}
                                <span class="text-danger">*</span>
                            </label>
                            <input
                                id="town-name-en"
                                type="text"
                                x-model="name_en"
                                x-name="name_en"
                                x-ref="nameEnInput"
                                class="w-full px-3.5 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200 text-sm"
                                placeholder="{{ __('e.g. Douala') }}"
                                autocomplete="off"
                            >
                            <p x-message="name_en" class="mt-1 text-xs text-danger"></p>
                        </div>

                        {{-- French Town Name --}}
                        <div>
                            <label for="town-name-fr" class="block text-sm font-medium text-on-surface mb-1.5">
                                {{ __('Town Name (French)') }}
                                <span class="text-danger">*</span>
                            </label>
                            <input
                                id="town-name-fr"
                                type="text"
                                x-model="name_fr"
                                x-name="name_fr"
                                class="w-full px-3.5 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200 text-sm"
                                placeholder="{{ __('e.g. Douala') }}"
                                autocomplete="off"
                            >
                            <p x-message="name_fr" class="mt-1 text-xs text-danger"></p>
                        </div>
                    </div>

                    {{-- Form Actions --}}
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            x-on:click="showAddTownForm = false; resetForm()"
                            class="px-4 py-2 rounded-lg text-sm font-medium text-on-surface hover:bg-surface dark:hover:bg-surface transition-colors duration-200"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-primary text-on-primary text-sm font-medium hover:bg-primary-hover transition-colors duration-200 shadow-sm"
                        >
                            <span x-show="!$fetching()">
                                {{-- Lucide: check --}}
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                            </span>
                            <span x-show="$fetching()">
                                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            </span>
                            <span x-text="$fetching() ? '{{ __('Saving...') }}' : '{{ __('Save Town') }}'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Town List (BR-213, BR-214, BR-215) --}}
        @php
            $locale = app()->getLocale();
        @endphp

        @if(count($deliveryAreas) > 0)
            <div class="space-y-3">
                @foreach($deliveryAreas as $area)
                    @php
                        $townName = $locale === 'fr' ? $area['town_name_fr'] : $area['town_name_en'];
                        $altName = $locale === 'fr' ? $area['town_name_en'] : $area['town_name_fr'];
                        $quarterCount = count($area['quarters']);
                    @endphp
                    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card transition-all duration-200 hover:shadow-md overflow-hidden">
                        {{-- Town Row --}}
                        <div class="flex items-center justify-between p-4">
                            {{-- Clickable Town Info (BR-216) --}}
                            <button
                                type="button"
                                x-on:click="toggleTown({{ $area['id'] }})"
                                class="flex items-center gap-3 min-w-0 flex-1 text-left group"
                                aria-expanded="false"
                                x-bind:aria-expanded="expandedTown === {{ $area['id'] }}"
                                title="{{ __('Manage quarters') }}"
                            >
                                {{-- Town Icon --}}
                                <div class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                                    {{-- Lucide: map-pin --}}
                                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-sm font-semibold text-on-surface-strong truncate group-hover:text-primary transition-colors duration-200">
                                        {{ $townName }}
                                    </h4>
                                    @if($area['town_name_en'] !== $area['town_name_fr'])
                                        <p class="text-xs text-on-surface/60 truncate">
                                            {{ $altName }}
                                        </p>
                                    @endif
                                </div>
                                {{-- Expand Chevron --}}
                                <svg
                                    class="w-4 h-4 text-on-surface/40 transition-transform duration-200 shrink-0"
                                    x-bind:class="expandedTown === {{ $area['id'] }} ? 'rotate-180' : ''"
                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                ><path d="m6 9 6 6 6-6"></path></svg>
                            </button>

                            {{-- Right side: Badge + Actions (BR-214) --}}
                            <div class="flex items-center gap-2 ml-3 shrink-0">
                                {{-- Quarter count badge --}}
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-info-subtle text-info">
                                    {{ $quarterCount }} {{ $quarterCount === 1 ? __('quarter') : __('quarters') }}
                                </span>

                                {{-- Edit button (BR-214) — stub link for F-084 --}}
                                <button
                                    type="button"
                                    x-on:click="$navigate('{{ url('/dashboard/locations/towns/' . $area['id'] . '/edit') }}')"
                                    class="p-2 rounded-lg text-on-surface/60 hover:text-primary hover:bg-primary-subtle transition-colors duration-200"
                                    title="{{ __('Edit town') }}"
                                    aria-label="{{ __('Edit') }} {{ $townName }}"
                                >
                                    {{-- Lucide: pencil (sm=16) --}}
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                                </button>

                                {{-- Delete button (BR-214) — stub for F-085 --}}
                                <button
                                    type="button"
                                    x-on:click="confirmDeleteId = {{ $area['id'] }}"
                                    class="p-2 rounded-lg text-on-surface/60 hover:text-danger hover:bg-danger-subtle transition-colors duration-200"
                                    title="{{ __('Delete town') }}"
                                    aria-label="{{ __('Delete') }} {{ $townName }}"
                                >
                                    {{-- Lucide: trash-2 (sm=16) --}}
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" x2="10" y1="11" y2="17"></line><line x1="14" x2="14" y1="11" y2="17"></line></svg>
                                </button>
                            </div>
                        </div>

                        {{-- Expanded Quarter Section (BR-216) --}}
                        <div
                            x-show="expandedTown === {{ $area['id'] }}"
                            x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="border-t border-outline dark:border-outline bg-surface dark:bg-surface px-4 py-3"
                        >
                            @if($quarterCount > 0)
                                <div class="space-y-2">
                                    <h5 class="text-xs font-semibold text-on-surface/60 uppercase tracking-wider mb-2">
                                        {{ __('Quarters') }}
                                    </h5>
                                    @foreach($area['quarters'] as $quarter)
                                        <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-surface-alt dark:bg-surface-alt">
                                            <span class="text-sm text-on-surface-strong truncate">
                                                {{ $locale === 'fr' ? $quarter['quarter_name_fr'] : $quarter['quarter_name_en'] }}
                                            </span>
                                            <span class="text-xs text-on-surface/60 shrink-0 ml-2">
                                                {{ number_format($quarter['delivery_fee']) }} {{ __('XAF') }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-on-surface/60 italic py-2">
                                    {{ __('No quarters added yet.') }}
                                </p>
                            @endif

                            {{-- Manage Quarters link (forward-compatible for F-087) --}}
                            <div class="mt-3 pt-3 border-t border-outline/50 dark:border-outline/50">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-primary cursor-default">
                                    {{-- Lucide: layout-grid (xs=14) --}}
                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                                    {{ __('Quarter management coming soon') }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Town Count Summary --}}
            <div class="mt-4 text-center">
                <p class="text-xs text-on-surface/50">
                    {{ count($deliveryAreas) }} {{ count($deliveryAreas) === 1 ? __('town') : __('towns') }}
                </p>
            </div>

            {{-- Delete Confirmation Modal (stub for F-085) --}}
            <div
                x-show="confirmDeleteId !== null"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                aria-label="{{ __('Delete town confirmation') }}"
            >
                {{-- Backdrop --}}
                <div
                    class="absolute inset-0 bg-black/50"
                    x-on:click="confirmDeleteId = null"
                ></div>

                {{-- Modal Content --}}
                <div
                    x-show="confirmDeleteId !== null"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="relative bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-lg max-w-sm w-full p-6"
                >
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-danger-subtle flex items-center justify-center shrink-0">
                            {{-- Lucide: alert-triangle --}}
                            <svg class="w-5 h-5 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-on-surface-strong">{{ __('Delete this town?') }}</h3>
                            <p class="text-sm text-on-surface mt-1">{{ __('This will remove the town and all its quarters from your delivery areas. This action cannot be undone.') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            x-on:click="confirmDeleteId = null"
                            class="px-4 py-2 rounded-lg text-sm font-medium text-on-surface hover:bg-surface dark:hover:bg-surface transition-colors duration-200"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="button"
                            x-on:click="$action('/dashboard/locations/towns/' + confirmDeleteId, { method: 'DELETE' }); confirmDeleteId = null"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-danger text-on-danger text-sm font-medium hover:bg-danger/90 transition-colors duration-200 shadow-sm"
                        >
                            {{-- Lucide: trash-2 --}}
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" x2="10" y1="11" y2="17"></line><line x1="14" x2="14" y1="11" y2="17"></line></svg>
                            {{ __('Delete') }}
                        </button>
                    </div>
                </div>
            </div>
        @else
            {{-- Empty State (BR-217) --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline border-dashed p-8 text-center">
                <div class="w-16 h-16 rounded-full bg-primary-subtle flex items-center justify-center mx-auto mb-4">
                    {{-- Lucide: map --}}
                    <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"></path><path d="M15 5.764v15"></path><path d="M9 3.236v15"></path></svg>
                </div>
                <h3 class="text-base font-semibold text-on-surface-strong mb-1">{{ __('No delivery towns yet') }}</h3>
                <p class="text-sm text-on-surface mb-4">{{ __('Add towns where you offer food delivery to get started.') }}</p>
                <button
                    x-on:click="showAddTownForm = true; $nextTick(() => $refs.nameEnInput.focus())"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-primary text-on-primary text-sm font-medium hover:bg-primary-hover transition-colors duration-200 shadow-sm"
                >
                    {{-- Lucide: plus --}}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                    {{ __('Add Your First Town') }}
                </button>
            </div>
        @endif
    </div>
</div>
@endsection
