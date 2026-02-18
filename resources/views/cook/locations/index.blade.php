{{--
    Locations -- Town List & Add Town & Add Quarter & Quarter List View & Edit Quarter
    ---------------------------------------------------------------------------------
    F-082: Add Town
    F-083: Town List View
    F-084: Edit Town
    F-085: Delete Town
    F-086: Add Quarter
    F-087: Quarter List View
    F-088: Edit Quarter
    F-089: Delete Quarter

    Allows the cook to view existing towns and add new ones to their delivery areas.
    Each town is scoped to the tenant via the delivery_areas junction table.
    Quarters can be added inline within each expanded town section.

    BR-242: Quarter list shows all quarters for the selected town
    BR-243: Each entry displays: quarter name (current locale), delivery fee (XAF), group name (if assigned)
    BR-244: Quarters are sorted alphabetically by name in the current locale
    BR-245: Filter by group is available if quarter groups exist for this tenant
    BR-246: Delivery fee of 0 is displayed as "Free delivery"
    BR-247: Quarters in a group show the group's fee (not their individual fee) with group name indicated
    BR-248: Empty state shown when no quarters exist for the town
    BR-249: List updates via Gale when quarters change
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

    {{-- Error Toast (F-085: BR-225 active order block message) --}}
    @if(session('error'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 6000)"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="mb-6 p-4 rounded-lg border bg-danger-subtle border-danger/30 text-danger flex items-center gap-3"
            role="alert"
        >
            {{-- Lucide: alert-circle --}}
            <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line></svg>
            <p class="text-sm font-medium">{{ session('error') }}</p>
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
            confirmDeleteName: '',
            confirmDeleteQuarterCount: 0,
            editingTownId: null,
            edit_name_en: '',
            edit_name_fr: '',
            /* F-086: Add Quarter state */
            showAddQuarterForm: null,
            quarter_name_en: '',
            quarter_name_fr: '',
            quarter_delivery_fee: '',
            /* F-088: Edit Quarter state */
            editingQuarterId: null,
            edit_quarter_name_en: '',
            edit_quarter_name_fr: '',
            edit_quarter_delivery_fee: '',
            /* F-090: Quarter Group state */
            showGroupForm: false,
            group_name: '',
            group_delivery_fee: '',
            group_quarter_ids: [],
            /* F-087: Quarter List View state */
            quarterGroupFilter: {},
            confirmDeleteQuarterId: null,
            confirmDeleteQuarterName: '',
            resetForm() {
                this.name_en = '';
                this.name_fr = '';
            },
            resetQuarterForm() {
                this.quarter_name_en = '';
                this.quarter_name_fr = '';
                this.quarter_delivery_fee = '';
            },
            resetGroupForm() {
                this.group_name = '';
                this.group_delivery_fee = '';
                this.group_quarter_ids = [];
            },
            toggleGroupQuarter(quarterId) {
                const id = parseInt(quarterId);
                const idx = this.group_quarter_ids.indexOf(id);
                if (idx === -1) {
                    this.group_quarter_ids.push(id);
                } else {
                    this.group_quarter_ids.splice(idx, 1);
                }
            },
            isGroupQuarterSelected(quarterId) {
                return this.group_quarter_ids.includes(parseInt(quarterId));
            },
            toggleTown(areaId) {
                this.expandedTown = this.expandedTown === areaId ? null : areaId;
            },
            startEdit(areaId, nameEn, nameFr) {
                this.editingTownId = areaId;
                this.edit_name_en = nameEn;
                this.edit_name_fr = nameFr;
            },
            cancelEdit() {
                this.editingTownId = null;
                this.edit_name_en = '';
                this.edit_name_fr = '';
            },
            showQuarterForm(areaId) {
                this.showAddQuarterForm = areaId;
                this.resetQuarterForm();
            },
            hideQuarterForm() {
                this.showAddQuarterForm = null;
                this.resetQuarterForm();
            },
            confirmDelete(areaId, townName, quarterCount) {
                this.confirmDeleteId = areaId;
                this.confirmDeleteName = townName;
                this.confirmDeleteQuarterCount = quarterCount;
            },
            cancelDelete() {
                this.confirmDeleteId = null;
                this.confirmDeleteName = '';
                this.confirmDeleteQuarterCount = 0;
            },
            executeDelete() {
                if (this.confirmDeleteId) {
                    $action('/dashboard/locations/towns/' + this.confirmDeleteId, { method: 'DELETE' });
                    this.cancelDelete();
                }
            },
            /* F-088: Edit Quarter methods */
            startEditQuarter(quarterId, nameEn, nameFr, fee) {
                this.editingQuarterId = quarterId;
                this.edit_quarter_name_en = nameEn;
                this.edit_quarter_name_fr = nameFr;
                this.edit_quarter_delivery_fee = String(fee);
            },
            cancelEditQuarter() {
                this.editingQuarterId = null;
                this.edit_quarter_name_en = '';
                this.edit_quarter_name_fr = '';
                this.edit_quarter_delivery_fee = '';
            },
            /* F-087: Quarter delete confirmation */
            confirmDeleteQuarter(quarterId, quarterName) {
                this.confirmDeleteQuarterId = quarterId;
                this.confirmDeleteQuarterName = quarterName;
            },
            cancelDeleteQuarter() {
                this.confirmDeleteQuarterId = null;
                this.confirmDeleteQuarterName = '';
            },
            executeDeleteQuarter() {
                if (this.confirmDeleteQuarterId) {
                    $action('/dashboard/locations/quarters/' + this.confirmDeleteQuarterId, { method: 'DELETE' });
                    this.cancelDeleteQuarter();
                }
            },
            /* F-087: Group filter per town */
            getGroupFilter(areaId) {
                return this.quarterGroupFilter[areaId] || 'all';
            },
            setGroupFilter(areaId, groupId) {
                this.quarterGroupFilter[areaId] = groupId;
            }
        }"
        x-sync="['name_en', 'name_fr', 'edit_name_en', 'edit_name_fr', 'quarter_name_en', 'quarter_name_fr', 'quarter_delivery_fee', 'edit_quarter_name_en', 'edit_quarter_name_fr', 'edit_quarter_delivery_fee', 'group_name', 'group_delivery_fee', 'group_quarter_ids']"
    >
        {{-- Header with Add Town button + Delivery Fees link (F-091) --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl font-semibold text-on-surface-strong">{{ __('Delivery Towns') }}</h2>
                <p class="text-sm text-on-surface mt-1">{{ __('Manage the towns and areas where you offer delivery.') }}</p>
            </div>
            <div class="flex items-center gap-2 self-start sm:self-auto">
                {{-- F-091: Delivery Fees link (BR-278) --}}
                <a
                    href="{{ url('/dashboard/locations/delivery-fees') }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-outline text-sm font-medium text-on-surface hover:bg-surface-alt hover:text-primary transition-colors duration-200"
                >
                    {{-- Lucide: receipt (sm=16) --}}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"></path><path d="M14 8H8"></path><path d="M16 12H8"></path><path d="M13 16H8"></path></svg>
                    {{ __('Delivery Fees') }}
                </a>
                <button
                    x-on:click="showAddTownForm = !showAddTownForm; if (showAddTownForm) { $nextTick(() => $refs.nameEnInput.focus()) }"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-primary text-on-primary text-sm font-medium hover:bg-primary-hover transition-colors duration-200 shadow-sm"
                >
                    {{-- Lucide: plus --}}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                    <span x-text="showAddTownForm ? '{{ __('Cancel') }}' : '{{ __('Add Town') }}'"></span>
                </button>
            </div>
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
                        {{-- Town Row (Display Mode) --}}
                        <div x-show="editingTownId !== {{ $area['id'] }}" class="flex items-center justify-between p-4">
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

                                {{-- Edit button (F-084: Edit Town) --}}
                                <button
                                    type="button"
                                    x-on:click="startEdit({{ $area['id'] }}, '{{ addslashes($area['town_name_en']) }}', '{{ addslashes($area['town_name_fr']) }}')"
                                    class="p-2 rounded-lg text-on-surface/60 hover:text-primary hover:bg-primary-subtle transition-colors duration-200"
                                    title="{{ __('Edit town') }}"
                                    aria-label="{{ __('Edit') }} {{ $townName }}"
                                >
                                    {{-- Lucide: pencil (sm=16) --}}
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                                </button>

                                {{-- Delete button (F-085: Delete Town, BR-228) --}}
                                <button
                                    type="button"
                                    x-on:click="confirmDelete({{ $area['id'] }}, '{{ addslashes($townName) }}', {{ $quarterCount }})"
                                    class="p-2 rounded-lg text-on-surface/60 hover:text-danger hover:bg-danger-subtle transition-colors duration-200"
                                    title="{{ __('Delete town') }}"
                                    aria-label="{{ __('Delete') }} {{ $townName }}"
                                >
                                    {{-- Lucide: trash-2 (sm=16) --}}
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" x2="10" y1="11" y2="17"></line><line x1="14" x2="14" y1="11" y2="17"></line></svg>
                                </button>
                            </div>
                        </div>

                        {{-- Inline Edit Form (F-084: Edit Town) --}}
                        <div x-show="editingTownId === {{ $area['id'] }}" x-cloak class="p-4">
                            <div class="flex items-center gap-2 mb-4">
                                <div class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                                    {{-- Lucide: pencil --}}
                                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                                </div>
                                <h4 class="text-sm font-semibold text-on-surface-strong">{{ __('Edit Town') }}</h4>
                            </div>
                            <form x-on:submit.prevent="$action('{{ url('/dashboard/locations/towns/' . $area['id']) }}', { method: 'PUT' })" class="space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    {{-- English Town Name --}}
                                    <div>
                                        <label for="edit-town-name-en-{{ $area['id'] }}" class="block text-sm font-medium text-on-surface mb-1.5">
                                            {{ __('Town Name (English)') }}
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input
                                            id="edit-town-name-en-{{ $area['id'] }}"
                                            type="text"
                                            x-model="edit_name_en"
                                            x-name="edit_name_en"
                                            class="w-full px-3.5 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200 text-sm"
                                            placeholder="{{ __('e.g. Douala') }}"
                                            autocomplete="off"
                                        >
                                        <p x-message="edit_name_en" class="mt-1 text-xs text-danger"></p>
                                    </div>

                                    {{-- French Town Name --}}
                                    <div>
                                        <label for="edit-town-name-fr-{{ $area['id'] }}" class="block text-sm font-medium text-on-surface mb-1.5">
                                            {{ __('Town Name (French)') }}
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input
                                            id="edit-town-name-fr-{{ $area['id'] }}"
                                            type="text"
                                            x-model="edit_name_fr"
                                            x-name="edit_name_fr"
                                            class="w-full px-3.5 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200 text-sm"
                                            placeholder="{{ __('e.g. Douala') }}"
                                            autocomplete="off"
                                        >
                                        <p x-message="edit_name_fr" class="mt-1 text-xs text-danger"></p>
                                    </div>
                                </div>

                                {{-- Form Actions --}}
                                <div class="flex items-center justify-end gap-3 pt-2">
                                    <button
                                        type="button"
                                        x-on:click="cancelEdit()"
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
                                        <span x-text="$fetching() ? '{{ __('Saving...') }}' : '{{ __('Save Changes') }}'"></span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- Expanded Quarter Section (BR-216, F-086: Add Quarter) --}}
                        <div
                            x-show="expandedTown === {{ $area['id'] }} && editingTownId !== {{ $area['id'] }}"
                            x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="border-t border-outline dark:border-outline bg-surface dark:bg-surface px-4 py-3"
                        >
                            {{-- Quarter List Header with Add Button --}}
                            <div class="flex items-center justify-between mb-3">
                                <h5 class="text-xs font-semibold text-on-surface/60 uppercase tracking-wider">
                                    {{ __('Quarters') }}
                                </h5>
                                <button
                                    type="button"
                                    x-on:click="showQuarterForm({{ $area['id'] }})"
                                    x-show="showAddQuarterForm !== {{ $area['id'] }}"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-primary hover:bg-primary-subtle transition-colors duration-200"
                                >
                                    {{-- Lucide: plus (xs=14) --}}
                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                                    {{ __('Add Quarter') }}
                                </button>
                            </div>

                            {{-- Quarter List (F-087: Quarter List View) --}}
                            @php
                                $hasGroups = collect($area['quarters'])->whereNotNull('group_id')->isNotEmpty();
                                $groups = collect($area['quarters'])->whereNotNull('group_id')
                                    ->pluck('group_name', 'group_id')
                                    ->unique()
                                    ->sortKeys()
                                    ->all();
                            @endphp

                            @if($quarterCount > 0)
                                {{-- BR-245: Group filter (visible only if quarter groups exist) --}}
                                @if($hasGroups)
                                    <div class="mb-3">
                                        <label for="group-filter-{{ $area['id'] }}" class="sr-only">{{ __('Filter by group') }}</label>
                                        <select
                                            id="group-filter-{{ $area['id'] }}"
                                            x-on:change="setGroupFilter({{ $area['id'] }}, $event.target.value)"
                                            class="w-full sm:w-auto px-3 py-1.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-sm text-on-surface-strong focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200"
                                        >
                                            <option value="all">{{ __('All quarters') }}</option>
                                            @foreach($groups as $groupId => $groupName)
                                                <option value="{{ $groupId }}">{{ $groupName }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <div class="space-y-2 mb-3">
                                    @foreach($area['quarters'] as $quarter)
                                        @php
                                            $quarterName = $locale === 'fr' ? $quarter['quarter_name_fr'] : $quarter['quarter_name_en'];
                                            $altQuarterName = $locale === 'fr' ? $quarter['quarter_name_en'] : $quarter['quarter_name_fr'];
                                            $effectiveFee = $quarter['group_fee'] !== null ? $quarter['group_fee'] : $quarter['delivery_fee'];
                                            $isGrouped = $quarter['group_id'] !== null;
                                        @endphp
                                        <div
                                            @if($isGrouped)
                                                x-show="getGroupFilter({{ $area['id'] }}) === 'all' || getGroupFilter({{ $area['id'] }}) === '{{ $quarter['group_id'] }}'"
                                            @endif
                                        >
                                            {{-- Quarter Display Row (F-087) --}}
                                            <div
                                                x-show="editingQuarterId !== {{ $quarter['id'] }}"
                                                class="flex items-center justify-between py-2.5 px-3 rounded-lg bg-surface-alt dark:bg-surface-alt"
                                            >
                                                {{-- Quarter Info (BR-243) --}}
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <span class="text-sm font-medium text-on-surface-strong truncate">
                                                            {{ $quarterName }}
                                                        </span>
                                                        {{-- BR-247: Group badge --}}
                                                        @if($isGrouped)
                                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-info-subtle text-info">
                                                                {{-- Lucide: layers (xs=14) --}}
                                                                <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"></path><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"></path><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"></path></svg>
                                                                {{ $quarter['group_name'] }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @if($quarter['quarter_name_en'] !== $quarter['quarter_name_fr'])
                                                        <span class="text-xs text-on-surface/50 truncate block mt-0.5">
                                                            {{ $altQuarterName }}
                                                        </span>
                                                    @endif
                                                </div>

                                                {{-- Fee + Actions --}}
                                                <div class="flex items-center gap-2 ml-3 shrink-0">
                                                    {{-- Delivery Fee (BR-243, BR-246, BR-247) --}}
                                                    <span class="shrink-0">
                                                        @if($effectiveFee === 0)
                                                            {{-- BR-246: Free delivery badge --}}
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-success-subtle text-success">
                                                                {{-- Lucide: check (xs=14) --}}
                                                                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                                                {{ __('Free delivery') }}
                                                            </span>
                                                        @else
                                                            <span class="text-xs font-medium text-on-surface/70">
                                                                {{ number_format($effectiveFee) }} {{ __('XAF') }}
                                                            </span>
                                                        @endif
                                                    </span>

                                                    {{-- Edit button (F-088: Edit Quarter) --}}
                                                    <button
                                                        type="button"
                                                        x-on:click="startEditQuarter({{ $quarter['id'] }}, '{{ addslashes($quarter['quarter_name_en']) }}', '{{ addslashes($quarter['quarter_name_fr']) }}', {{ $quarter['delivery_fee'] }})"
                                                        class="p-1.5 rounded-lg text-on-surface/50 hover:text-primary hover:bg-primary-subtle transition-colors duration-200"
                                                        title="{{ __('Edit quarter') }}"
                                                        aria-label="{{ __('Edit') }} {{ $quarterName }}"
                                                    >
                                                        {{-- Lucide: pencil (sm=16) --}}
                                                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                                                    </button>

                                                    {{-- Delete button (F-089 stub) --}}
                                                    <button
                                                        type="button"
                                                        x-on:click="confirmDeleteQuarter({{ $quarter['id'] }}, '{{ addslashes($quarterName) }}')"
                                                        class="p-1.5 rounded-lg text-on-surface/50 hover:text-danger hover:bg-danger-subtle transition-colors duration-200"
                                                        title="{{ __('Delete quarter') }}"
                                                        aria-label="{{ __('Delete') }} {{ $quarterName }}"
                                                    >
                                                        {{-- Lucide: trash-2 (sm=16) --}}
                                                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" x2="10" y1="11" y2="17"></line><line x1="14" x2="14" y1="11" y2="17"></line></svg>
                                                    </button>
                                                </div>
                                            </div>

                                            {{-- Inline Edit Form (F-088: Edit Quarter) --}}
                                            <div
                                                x-show="editingQuarterId === {{ $quarter['id'] }}"
                                                x-cloak
                                                class="py-2.5 px-3 rounded-lg bg-surface-alt dark:bg-surface-alt border border-primary/30"
                                            >
                                                <div class="flex items-center gap-2 mb-3">
                                                    <div class="w-7 h-7 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                                                        {{-- Lucide: pencil --}}
                                                        <svg class="w-3.5 h-3.5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                                                    </div>
                                                    <h6 class="text-xs font-semibold text-on-surface-strong">{{ __('Edit Quarter') }}</h6>
                                                </div>
                                                <form x-on:submit.prevent="$action('{{ url('/dashboard/locations/quarters/' . $quarter['id']) }}', { method: 'PUT' })" class="space-y-3">
                                                    {{-- Quarter Names (EN + FR) --}}
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                        {{-- English Quarter Name (BR-250) --}}
                                                        <div>
                                                            <label for="edit-quarter-name-en-{{ $quarter['id'] }}" class="block text-xs font-medium text-on-surface mb-1">
                                                                {{ __('Quarter Name (English)') }}
                                                                <span class="text-danger">*</span>
                                                            </label>
                                                            <input
                                                                id="edit-quarter-name-en-{{ $quarter['id'] }}"
                                                                type="text"
                                                                x-model="edit_quarter_name_en"
                                                                x-name="edit_quarter_name_en"
                                                                class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200 text-sm"
                                                                placeholder="{{ __('e.g. Bonaberi') }}"
                                                                autocomplete="off"
                                                            >
                                                            <p x-message="edit_quarter_name_en" class="mt-1 text-xs text-danger"></p>
                                                        </div>

                                                        {{-- French Quarter Name (BR-250) --}}
                                                        <div>
                                                            <label for="edit-quarter-name-fr-{{ $quarter['id'] }}" class="block text-xs font-medium text-on-surface mb-1">
                                                                {{ __('Quarter Name (French)') }}
                                                                <span class="text-danger">*</span>
                                                            </label>
                                                            <input
                                                                id="edit-quarter-name-fr-{{ $quarter['id'] }}"
                                                                type="text"
                                                                x-model="edit_quarter_name_fr"
                                                                x-name="edit_quarter_name_fr"
                                                                class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200 text-sm"
                                                                placeholder="{{ __('e.g. Bonaberi') }}"
                                                                autocomplete="off"
                                                            >
                                                            <p x-message="edit_quarter_name_fr" class="mt-1 text-xs text-danger"></p>
                                                        </div>
                                                    </div>

                                                    {{-- Delivery Fee (BR-252) --}}
                                                    <div>
                                                        <label for="edit-quarter-fee-{{ $quarter['id'] }}" class="block text-xs font-medium text-on-surface mb-1">
                                                            {{ __('Delivery Fee') }}
                                                            <span class="text-danger">*</span>
                                                        </label>
                                                        <div class="relative">
                                                            <input
                                                                id="edit-quarter-fee-{{ $quarter['id'] }}"
                                                                type="number"
                                                                min="0"
                                                                step="1"
                                                                x-model="edit_quarter_delivery_fee"
                                                                x-name="edit_quarter_delivery_fee"
                                                                class="w-full px-3 py-2 pr-14 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200 text-sm"
                                                                placeholder="{{ __('e.g. 500') }}"
                                                                autocomplete="off"
                                                            >
                                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-on-surface/50">
                                                                {{ __('XAF') }}
                                                            </span>
                                                        </div>
                                                        <p x-message="edit_quarter_delivery_fee" class="mt-1 text-xs text-danger"></p>
                                                        <p class="mt-1 text-xs text-on-surface/50">
                                                            {{ __('Enter 0 for free delivery to this quarter.') }}
                                                        </p>
                                                    </div>

                                                    {{-- BR-253: Group assignment (forward-compatible for F-090) --}}
                                                    {{-- Quarter groups not yet available (F-090). Hidden until groups exist. --}}

                                                    {{-- Form Actions --}}
                                                    <div class="flex items-center justify-end gap-2 pt-1">
                                                        <button
                                                            type="button"
                                                            x-on:click="cancelEditQuarter()"
                                                            class="px-3 py-1.5 rounded-lg text-xs font-medium text-on-surface hover:bg-surface dark:hover:bg-surface transition-colors duration-200"
                                                        >
                                                            {{ __('Cancel') }}
                                                        </button>
                                                        <button
                                                            type="submit"
                                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary text-on-primary text-xs font-medium hover:bg-primary-hover transition-colors duration-200 shadow-sm"
                                                        >
                                                            <span x-show="!$fetching()">
                                                                {{-- Lucide: check (xs=14) --}}
                                                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                                            </span>
                                                            <span x-show="$fetching()">
                                                                <svg class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                            </span>
                                                            <span x-text="$fetching() ? '{{ __('Saving...') }}' : '{{ __('Save Changes') }}'"></span>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                {{-- BR-248: Empty state --}}
                                <div x-show="showAddQuarterForm !== {{ $area['id'] }}" class="py-4 text-center">
                                    <div class="w-10 h-10 rounded-full bg-primary-subtle/50 flex items-center justify-center mx-auto mb-2">
                                        {{-- Lucide: map-pin (sm=16) --}}
                                        <svg class="w-4 h-4 text-primary/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    </div>
                                    <p class="text-sm text-on-surface/60 mb-2">
                                        {{ __('No quarters added yet. Add your first quarter.') }}
                                    </p>
                                    <button
                                        type="button"
                                        x-on:click="showQuarterForm({{ $area['id'] }})"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-primary text-on-primary hover:bg-primary-hover transition-colors duration-200 shadow-sm"
                                    >
                                        {{-- Lucide: plus (xs=14) --}}
                                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                                        {{ __('Add Quarter') }}
                                    </button>
                                </div>
                            @endif

                            {{-- Add Quarter Form (F-086: Inline within expanded town) --}}
                            <div
                                x-show="showAddQuarterForm === {{ $area['id'] }}"
                                x-cloak
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 -translate-y-2"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 translate-y-0"
                                x-transition:leave-end="opacity-0 -translate-y-2"
                                class="mt-2"
                            >
                                <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline/60 dark:border-outline/60 p-4">
                                    <h6 class="text-sm font-semibold text-on-surface-strong mb-3 flex items-center gap-2">
                                        {{-- Lucide: map-pin-plus --}}
                                        <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                        {{ __('Add Quarter to') }} {{ $townName }}
                                    </h6>

                                    <form x-on:submit.prevent="$action('{{ url('/dashboard/locations/quarters/' . $area['id']) }}')" class="space-y-3">
                                        {{-- Quarter Names (EN + FR) --}}
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            {{-- English Quarter Name (BR-232) --}}
                                            <div>
                                                <label for="quarter-name-en-{{ $area['id'] }}" class="block text-xs font-medium text-on-surface mb-1">
                                                    {{ __('Quarter Name (English)') }}
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <input
                                                    id="quarter-name-en-{{ $area['id'] }}"
                                                    type="text"
                                                    x-model="quarter_name_en"
                                                    x-name="quarter_name_en"
                                                    class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200 text-sm"
                                                    placeholder="{{ __('e.g. Bonaberi') }}"
                                                    autocomplete="off"
                                                >
                                                <p x-message="quarter_name_en" class="mt-1 text-xs text-danger"></p>
                                            </div>

                                            {{-- French Quarter Name (BR-232) --}}
                                            <div>
                                                <label for="quarter-name-fr-{{ $area['id'] }}" class="block text-xs font-medium text-on-surface mb-1">
                                                    {{ __('Quarter Name (French)') }}
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <input
                                                    id="quarter-name-fr-{{ $area['id'] }}"
                                                    type="text"
                                                    x-model="quarter_name_fr"
                                                    x-name="quarter_name_fr"
                                                    class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200 text-sm"
                                                    placeholder="{{ __('e.g. Bonabéri') }}"
                                                    autocomplete="off"
                                                >
                                                <p x-message="quarter_name_fr" class="mt-1 text-xs text-danger"></p>
                                            </div>
                                        </div>

                                        {{-- Delivery Fee (BR-234, BR-235) --}}
                                        <div>
                                            <label for="quarter-fee-{{ $area['id'] }}" class="block text-xs font-medium text-on-surface mb-1">
                                                {{ __('Delivery Fee') }}
                                                <span class="text-danger">*</span>
                                            </label>
                                            <div class="relative">
                                                <input
                                                    id="quarter-fee-{{ $area['id'] }}"
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    x-model="quarter_delivery_fee"
                                                    x-name="quarter_delivery_fee"
                                                    class="w-full px-3 py-2 pr-14 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200 text-sm"
                                                    placeholder="{{ __('e.g. 500') }}"
                                                    autocomplete="off"
                                                >
                                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-on-surface/50">
                                                    {{ __('XAF') }}
                                                </span>
                                            </div>
                                            <p x-message="quarter_delivery_fee" class="mt-1 text-xs text-danger"></p>
                                            <p class="mt-1 text-xs text-on-surface/50">
                                                {{ __('Enter 0 for free delivery to this quarter.') }}
                                            </p>
                                        </div>

                                        {{-- Quarter Group (BR-237, BR-238 — forward-compatible for F-090) --}}
                                        {{-- Quarter groups not yet available (F-090). Hidden until groups exist. --}}

                                        {{-- Form Actions --}}
                                        <div class="flex items-center justify-end gap-2 pt-1">
                                            <button
                                                type="button"
                                                x-on:click="hideQuarterForm()"
                                                class="px-3 py-1.5 rounded-lg text-xs font-medium text-on-surface hover:bg-surface dark:hover:bg-surface transition-colors duration-200"
                                            >
                                                {{ __('Cancel') }}
                                            </button>
                                            <button
                                                type="submit"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary text-on-primary text-xs font-medium hover:bg-primary-hover transition-colors duration-200 shadow-sm"
                                            >
                                                <span x-show="!$fetching()">
                                                    {{-- Lucide: check (xs=14) --}}
                                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                                </span>
                                                <span x-show="$fetching()">
                                                    <svg class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                </span>
                                                <span x-text="$fetching() ? '{{ __('Saving...') }}' : '{{ __('Save Quarter') }}'"></span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
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

            {{-- F-090: Quarter Groups Section --}}
            <div class="mt-10">
                {{-- Group Header with Create Group button --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-on-surface-strong">{{ __('Quarter Groups') }}</h2>
                        <p class="text-sm text-on-surface mt-1">{{ __('Group quarters that share the same delivery fee.') }}</p>
                    </div>
                    <button
                        x-on:click="showGroupForm = !showGroupForm; if (!showGroupForm) { resetGroupForm() }"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-primary text-on-primary text-sm font-medium hover:bg-primary-hover transition-colors duration-200 shadow-sm self-start sm:self-auto"
                    >
                        {{-- Lucide: layers --}}
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"></path><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"></path><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"></path></svg>
                        <span x-text="showGroupForm ? '{{ __('Cancel') }}' : '{{ __('Create Group') }}'"></span>
                    </button>
                </div>

                {{-- Create Group Form --}}
                <div
                    x-show="showGroupForm"
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
                            {{-- Lucide: layers --}}
                            <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"></path><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"></path><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"></path></svg>
                            {{ __('Create Quarter Group') }}
                        </h3>

                        <form x-on:submit.prevent="$action('{{ url('/dashboard/locations/groups') }}')" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                {{-- Group Name (BR-264: plain text, not translatable) --}}
                                <div>
                                    <label for="group-name" class="block text-sm font-medium text-on-surface mb-1.5">
                                        {{ __('Group Name') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        id="group-name"
                                        type="text"
                                        x-model="group_name"
                                        x-name="group_name"
                                        maxlength="100"
                                        class="w-full px-3.5 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200 text-sm"
                                        placeholder="{{ __('e.g. Central Douala') }}"
                                        autocomplete="off"
                                    >
                                    <p x-message="group_name" class="mt-1 text-xs text-danger"></p>
                                </div>

                                {{-- Delivery Fee (BR-266) --}}
                                <div>
                                    <label for="group-fee" class="block text-sm font-medium text-on-surface mb-1.5">
                                        {{ __('Delivery Fee') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="relative">
                                        <input
                                            id="group-fee"
                                            type="number"
                                            min="0"
                                            step="1"
                                            x-model="group_delivery_fee"
                                            x-name="group_delivery_fee"
                                            class="w-full px-3.5 py-2.5 pr-14 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200 text-sm"
                                            placeholder="{{ __('e.g. 300') }}"
                                            autocomplete="off"
                                        >
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-on-surface/50">
                                            {{ __('XAF') }}
                                        </span>
                                    </div>
                                    <p x-message="group_delivery_fee" class="mt-1 text-xs text-danger"></p>
                                    <p class="mt-1 text-xs text-on-surface/50">
                                        {{ __('Enter 0 for free delivery. This fee applies to all quarters in the group.') }}
                                    </p>
                                </div>
                            </div>

                            {{-- Quarter Selection (BR-269: Multi-select with checkboxes) --}}
                            @if(count($quartersForGroupAssignment) > 0)
                                <div>
                                    <label class="block text-sm font-medium text-on-surface mb-2">
                                        {{ __('Assign Quarters') }}
                                        <span class="text-xs text-on-surface/50 font-normal ml-1">{{ __('(optional)') }}</span>
                                    </label>
                                    <p class="text-xs text-on-surface/60 mb-3">
                                        {{ __('Select quarters to include in this group. Quarters in another group will be moved to this group.') }}
                                    </p>
                                    <div class="max-h-60 overflow-y-auto rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface p-3 space-y-3">
                                        @foreach($quartersForGroupAssignment as $townGroup)
                                            <div>
                                                <p class="text-xs font-semibold text-on-surface/60 uppercase tracking-wider mb-1.5">
                                                    {{ $townGroup['town_name'] }}
                                                </p>
                                                <div class="space-y-1">
                                                    @foreach($townGroup['quarters'] as $q)
                                                        <label
                                                            class="flex items-center gap-3 py-1.5 px-2 rounded-lg hover:bg-surface-alt dark:hover:bg-surface-alt cursor-pointer transition-colors duration-150"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                value="{{ $q['quarter_id'] }}"
                                                                x-on:change="toggleGroupQuarter({{ $q['quarter_id'] }})"
                                                                x-bind:checked="isGroupQuarterSelected({{ $q['quarter_id'] }})"
                                                                class="w-4 h-4 rounded border-outline text-primary focus:ring-primary/50"
                                                            >
                                                            <span class="text-sm text-on-surface-strong flex-1">
                                                                {{ $q['quarter_name'] }}
                                                            </span>
                                                            @if($q['current_group_name'])
                                                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-warning-subtle text-warning font-medium">
                                                                    {{ $q['current_group_name'] }}
                                                                </span>
                                                            @endif
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Form Actions --}}
                            <div class="flex items-center justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    x-on:click="showGroupForm = false; resetGroupForm()"
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
                                    <span x-text="$fetching() ? '{{ __('Saving...') }}' : '{{ __('Save Group') }}'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Group List --}}
                @if(count($quarterGroups) > 0)
                    <div class="space-y-3">
                        @foreach($quarterGroups as $group)
                            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center shrink-0">
                                            {{-- Lucide: layers --}}
                                            <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"></path><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"></path><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"></path></svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h4 class="text-sm font-semibold text-on-surface-strong truncate">
                                                {{ $group['name'] }}
                                            </h4>
                                            <p class="text-xs text-on-surface/60 mt-0.5">
                                                {{ $group['quarter_count'] }} {{ $group['quarter_count'] === 1 ? __('quarter') : __('quarters') }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="shrink-0 ml-3">
                                        @if($group['delivery_fee'] === 0)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-success-subtle text-success">
                                                {{-- Lucide: check --}}
                                                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                                {{ __('Free delivery') }}
                                            </span>
                                        @else
                                            <span class="text-sm font-medium text-on-surface/70">
                                                {{ number_format($group['delivery_fee']) }} {{ __('XAF') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Group Count Summary --}}
                    <div class="mt-4 text-center">
                        <p class="text-xs text-on-surface/50">
                            {{ count($quarterGroups) }} {{ count($quarterGroups) === 1 ? __('group') : __('groups') }}
                        </p>
                    </div>
                @else
                    {{-- Empty State --}}
                    <div x-show="!showGroupForm" class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline border-dashed p-6 text-center">
                        <div class="w-12 h-12 rounded-full bg-info-subtle/50 flex items-center justify-center mx-auto mb-3">
                            {{-- Lucide: layers --}}
                            <svg class="w-6 h-6 text-info/60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"></path><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"></path><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"></path></svg>
                        </div>
                        <h3 class="text-sm font-semibold text-on-surface-strong mb-1">{{ __('No quarter groups yet') }}</h3>
                        <p class="text-xs text-on-surface/60 mb-3">{{ __('Create groups to manage delivery fees for multiple quarters at once.') }}</p>
                        <button
                            x-on:click="showGroupForm = true"
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-primary text-on-primary text-xs font-medium hover:bg-primary-hover transition-colors duration-200 shadow-sm"
                        >
                            {{-- Lucide: plus --}}
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                            {{ __('Create Your First Group') }}
                        </button>
                    </div>
                @endif
            </div>

            {{-- Delete Confirmation Modal (F-085: Delete Town, BR-228) --}}
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
                    x-on:click="cancelDelete()"
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
                    <div class="flex items-start gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-danger-subtle flex items-center justify-center shrink-0">
                            {{-- Lucide: alert-triangle --}}
                            <svg class="w-5 h-5 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-on-surface-strong">{{ __('Delete this town?') }}</h3>
                            {{-- BR-228: Show town name and quarter count in confirmation --}}
                            <p class="text-sm text-on-surface mt-1">
                                <span x-show="confirmDeleteQuarterCount > 0" x-text="'{{ __('Delete') }} ' + confirmDeleteName + ' {{ __('and its') }} ' + confirmDeleteQuarterCount + ' ' + (confirmDeleteQuarterCount === 1 ? '{{ __('quarter') }}' : '{{ __('quarters') }}') + '? {{ __('This cannot be undone.') }}'"></span>
                                <span x-show="confirmDeleteQuarterCount === 0" x-text="'{{ __('Delete') }} ' + confirmDeleteName + '? {{ __('This cannot be undone.') }}'"></span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            x-on:click="cancelDelete()"
                            class="px-4 py-2 rounded-lg text-sm font-medium text-on-surface hover:bg-surface dark:hover:bg-surface transition-colors duration-200"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="button"
                            x-on:click="executeDelete()"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-danger text-on-danger text-sm font-medium hover:bg-danger/90 transition-colors duration-200 shadow-sm"
                        >
                            {{-- Lucide: trash-2 --}}
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" x2="10" y1="11" y2="17"></line><line x1="14" x2="14" y1="11" y2="17"></line></svg>
                            {{ __('Delete') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- Quarter Delete Confirmation Modal (F-089: Delete Quarter, BR-260) --}}
            <div
                x-show="confirmDeleteQuarterId !== null"
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
                aria-label="{{ __('Delete quarter confirmation') }}"
            >
                {{-- Backdrop --}}
                <div
                    class="absolute inset-0 bg-black/50"
                    x-on:click="cancelDeleteQuarter()"
                ></div>

                {{-- Modal Content --}}
                <div
                    x-show="confirmDeleteQuarterId !== null"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="relative bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-lg max-w-sm w-full p-6"
                >
                    <div class="flex items-start gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-danger-subtle flex items-center justify-center shrink-0">
                            {{-- Lucide: alert-triangle --}}
                            <svg class="w-5 h-5 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-on-surface-strong">{{ __('Delete this quarter?') }}</h3>
                            {{-- BR-260: Show quarter name and warning --}}
                            <p class="text-sm text-on-surface mt-1">
                                <span x-text="'{{ __('Delete') }} ' + confirmDeleteQuarterName + '? {{ __('Clients will no longer be able to order to this quarter.') }}'"></span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            x-on:click="cancelDeleteQuarter()"
                            class="px-4 py-2 rounded-lg text-sm font-medium text-on-surface hover:bg-surface dark:hover:bg-surface transition-colors duration-200"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="button"
                            x-on:click="executeDeleteQuarter()"
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
