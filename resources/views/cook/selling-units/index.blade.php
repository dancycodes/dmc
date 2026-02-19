{{--
    Selling Units Management Page
    ----------------------------
    F-121: Custom Selling Unit Definition
    Allows cooks to manage custom selling units for meal components.

    Features:
    - Standard units listed (non-editable, marked with "Standard" badge)
    - Custom units with edit/delete
    - Inline add form for new custom units
    - Inline edit form per custom unit
    - Delete confirmation modal with usage count warning
    - All interactions via Gale
    - Mobile-friendly card layout
    - Light/dark mode support
    - All text localized with __()

    BR-306: Standard units pre-seeded: plate, bowl, pot, cup, piece, portion, serving, pack
    BR-307: Standard units cannot be edited or deleted
    BR-308: Custom units are tenant-scoped
    BR-309: Custom unit name required in both EN and FR
    BR-310: Name unique within tenant and against standard units
    BR-311: Cannot delete if used by any meal component
    BR-312: Only users with manage-meals permission
    BR-313: CRUD logged via Spatie Activitylog
    BR-314: Name max 50 characters per language
    BR-315: Standard units have pre-defined translations
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Selling Units'))
@section('page-title', __('Selling Units'))

@section('content')
<div
    class="max-w-4xl mx-auto"
    x-data="{
        showAddForm: false,
        unit_name_en: '',
        unit_name_fr: '',
        editingId: null,
        edit_name_en: '',
        edit_name_fr: '',
        confirmDeleteId: null,
        confirmDeleteName: '',

        openAddForm() {
            this.showAddForm = true;
            this.cancelEdit();
        },
        cancelAdd() {
            this.showAddForm = false;
            this.unit_name_en = '';
            this.unit_name_fr = '';
        },
        startEdit(id, nameEn, nameFr) {
            this.editingId = id;
            this.edit_name_en = nameEn;
            this.edit_name_fr = nameFr;
            this.showAddForm = false;
        },
        cancelEdit() {
            this.editingId = null;
            this.edit_name_en = '';
            this.edit_name_fr = '';
        },
        confirmDelete(id, name) {
            this.confirmDeleteId = id;
            this.confirmDeleteName = name;
        },
        cancelDelete() {
            this.confirmDeleteId = null;
            this.confirmDeleteName = '';
        },
        executeDelete() {
            if (this.confirmDeleteId) {
                $action('/dashboard/selling-units/' + this.confirmDeleteId, { method: 'DELETE' });
                this.cancelDelete();
            }
        }
    }"
>
    {{-- Header with Add Unit button --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-display font-bold text-on-surface-strong">{{ __('Selling Units') }}</h2>
            <p class="mt-1 text-sm text-on-surface">{{ __('Manage the units used when pricing meal components.') }}</p>
        </div>
        <button
            x-show="!showAddForm"
            @click="openAddForm()"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-on-primary rounded-lg font-medium text-sm hover:bg-primary-hover transition-colors duration-200 shadow-sm"
        >
            {{-- Lucide: plus (sm=16) --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
            <span class="hidden sm:inline">{{ __('Add Unit') }}</span>
        </button>
    </div>

    {{-- Add Unit Form (inline, collapsible) --}}
    <div
        x-show="showAddForm"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-cloak
        class="mb-6 bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 sm:p-6 shadow-card"
    >
        <h3 class="text-base font-semibold text-on-surface-strong mb-4">{{ __('New Selling Unit') }}</h3>
        <form @submit.prevent="$action('{{ url('/dashboard/selling-units') }}', { include: ['unit_name_en', 'unit_name_fr'] })">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                {{-- English name --}}
                <div>
                    <label for="add-unit-name-en" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Name (English)') }} <span class="text-danger">*</span>
                    </label>
                    <input
                        id="add-unit-name-en"
                        type="text"
                        x-model="unit_name_en"
                        x-name="unit_name_en"
                        maxlength="50"
                        placeholder="{{ __('e.g. Calabash') }}"
                        class="w-full px-3 py-2.5 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                    >
                    <div class="flex items-center justify-between mt-1">
                        <p x-message="unit_name_en" class="text-xs text-danger"></p>
                        <span class="text-xs text-on-surface/50" x-text="unit_name_en.length + '/50'"></span>
                    </div>
                </div>
                {{-- French name --}}
                <div>
                    <label for="add-unit-name-fr" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Name (French)') }} <span class="text-danger">*</span>
                    </label>
                    <input
                        id="add-unit-name-fr"
                        type="text"
                        x-model="unit_name_fr"
                        x-name="unit_name_fr"
                        maxlength="50"
                        placeholder="{{ __('e.g. Calebasse') }}"
                        class="w-full px-3 py-2.5 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                    >
                    <div class="flex items-center justify-between mt-1">
                        <p x-message="unit_name_fr" class="text-xs text-danger"></p>
                        <span class="text-xs text-on-surface/50" x-text="unit_name_fr.length + '/50'"></span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="$fetching()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-on-primary rounded-lg font-medium text-sm hover:bg-primary-hover transition-colors duration-200 disabled:opacity-50"
                >
                    <span x-show="!$fetching()">{{ __('Create Unit') }}</span>
                    <span x-show="$fetching()" x-cloak>{{ __('Creating...') }}</span>
                </button>
                <button
                    type="button"
                    @click="cancelAdd()"
                    class="px-4 py-2 text-sm font-medium text-on-surface hover:bg-surface rounded-lg transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </button>
            </div>
        </form>
    </div>

    @php
        $standardUnits = $units->where('is_standard', true);
        $customUnits = $units->where('is_standard', false);
    @endphp

    {{-- Standard Units Section --}}
    <div class="mb-6">
        <h3 class="text-sm font-semibold text-on-surface/60 uppercase tracking-wider mb-3">
            {{ __('Standard Units') }}
            <span class="text-xs font-normal normal-case ml-1">({{ $standardUnits->count() }})</span>
        </h3>

        {{-- Desktop table view for standard units --}}
        <div class="hidden md:block bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-outline dark:border-outline">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('English Name') }}</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('French Name') }}</th>
                        <th class="px-6 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('Type') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline dark:divide-outline">
                    @foreach($standardUnits as $unit)
                        <tr class="hover:bg-surface dark:hover:bg-surface transition-colors duration-150">
                            <td class="px-6 py-4 text-sm font-medium text-on-surface-strong">{{ $unit->name_en }}</td>
                            <td class="px-6 py-4 text-sm text-on-surface">{{ $unit->name_fr }}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-info-subtle text-info">
                                    {{ __('Standard') }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile card view for standard units --}}
        <div class="md:hidden space-y-2">
            @foreach($standardUnits as $unit)
                <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 shadow-card">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-semibold text-on-surface-strong truncate">{{ $unit->name_en }}</h4>
                            <p class="text-xs text-on-surface">{{ __('FR') }}: {{ $unit->name_fr }}</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-info-subtle text-info ml-2 shrink-0">
                            {{ __('Standard') }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Custom Units Section --}}
    <div>
        <h3 class="text-sm font-semibold text-on-surface/60 uppercase tracking-wider mb-3">
            {{ __('Custom Units') }}
            <span class="text-xs font-normal normal-case ml-1">({{ $customUnits->count() }})</span>
        </h3>

        @if($customUnits->isEmpty())
            {{-- Empty state for custom units --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-8 sm:p-12 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-primary-subtle flex items-center justify-center">
                    {{-- Lucide: ruler (xl=32) --}}
                    <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.41 2.41 0 0 1 0-3.4l2.6-2.6a2.41 2.41 0 0 1 3.4 0Z"/><path d="m14.5 12.5 2-2"/><path d="m11.5 9.5 2-2"/><path d="m8.5 6.5 2-2"/><path d="m17.5 15.5 2-2"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-on-surface-strong mb-2">{{ __('No custom units yet') }}</h3>
                <p class="text-sm text-on-surface mb-4">{{ __('Create custom selling units for your unique cuisine measurements.') }}</p>
                <button
                    @click="openAddForm()"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-on-primary rounded-lg font-medium text-sm hover:bg-primary-hover transition-colors duration-200"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                    {{ __('Create Your First Unit') }}
                </button>
            </div>
        @else
            {{-- Desktop table view for custom units --}}
            <div class="hidden md:block bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-outline dark:border-outline">
                            <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('English Name') }}</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('French Name') }}</th>
                            <th class="px-6 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('Usage') }}</th>
                            <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline dark:divide-outline">
                        @foreach($customUnits as $unit)
                            @php
                                $unitDeleteInfo = $deleteInfo[$unit->id] ?? ['can_delete' => true];
                                $canDelete = $unitDeleteInfo['can_delete'];
                                $deleteReason = $unitDeleteInfo['reason'] ?? '';
                                $usageCount = $unit->getUsageCount();
                            @endphp
                            <tr class="hover:bg-surface dark:hover:bg-surface transition-colors duration-150">
                                {{-- Display row (hidden when editing this unit) --}}
                                <template x-if="editingId !== {{ $unit->id }}">
                                    <td class="px-6 py-4 text-sm font-medium text-on-surface-strong">{{ $unit->name_en }}</td>
                                </template>
                                <template x-if="editingId !== {{ $unit->id }}">
                                    <td class="px-6 py-4 text-sm text-on-surface">{{ $unit->name_fr }}</td>
                                </template>
                                <template x-if="editingId !== {{ $unit->id }}">
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $usageCount > 0 ? 'bg-primary-subtle text-primary' : 'bg-surface text-on-surface/60' }}">
                                            {{ trans_choice(':count component|:count components', $usageCount, ['count' => $usageCount]) }}
                                        </span>
                                    </td>
                                </template>
                                <template x-if="editingId !== {{ $unit->id }}">
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            {{-- Edit button --}}
                                            <button
                                                @click="startEdit({{ $unit->id }}, '{{ addslashes($unit->name_en) }}', '{{ addslashes($unit->name_fr) }}')"
                                                class="p-2 text-on-surface hover:text-primary hover:bg-primary-subtle rounded-lg transition-colors duration-200"
                                                title="{{ __('Edit') }}"
                                            >
                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                                            </button>
                                            {{-- Delete button --}}
                                            @if(!$canDelete)
                                                <button
                                                    disabled
                                                    class="p-2 text-on-surface/30 cursor-not-allowed rounded-lg"
                                                    title="{{ $deleteReason }}"
                                                >
                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                                </button>
                                            @else
                                                <button
                                                    @click="confirmDelete({{ $unit->id }}, '{{ addslashes($unit->name) }}')"
                                                    class="p-2 text-on-surface hover:text-danger hover:bg-danger-subtle rounded-lg transition-colors duration-200"
                                                    title="{{ __('Delete') }}"
                                                >
                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </template>

                                {{-- Inline edit form (shown when editing this unit) --}}
                                <template x-if="editingId === {{ $unit->id }}">
                                    <td colspan="4" class="px-6 py-4">
                                        <form @submit.prevent="$action('/dashboard/selling-units/{{ $unit->id }}', { method: 'PUT', include: ['edit_name_en', 'edit_name_fr'] })">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-3">
                                                <div>
                                                    <label class="block text-sm font-medium text-on-surface mb-1">{{ __('Name (English)') }}</label>
                                                    <input
                                                        type="text"
                                                        x-model="edit_name_en"
                                                        x-name="edit_name_en"
                                                        maxlength="50"
                                                        class="w-full px-3 py-2 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                                                    >
                                                    <p x-message="edit_name_en" class="mt-1 text-sm text-danger"></p>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-on-surface mb-1">{{ __('Name (French)') }}</label>
                                                    <input
                                                        type="text"
                                                        x-model="edit_name_fr"
                                                        x-name="edit_name_fr"
                                                        maxlength="50"
                                                        class="w-full px-3 py-2 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                                                    >
                                                    <p x-message="edit_name_fr" class="mt-1 text-sm text-danger"></p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <button
                                                    type="submit"
                                                    :disabled="$fetching()"
                                                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-primary text-on-primary rounded-lg font-medium text-sm hover:bg-primary-hover transition-colors duration-200 disabled:opacity-50"
                                                >
                                                    <span x-show="!$fetching()">{{ __('Save') }}</span>
                                                    <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="cancelEdit()"
                                                    class="px-3 py-1.5 text-sm font-medium text-on-surface hover:bg-surface rounded-lg transition-colors duration-200"
                                                >
                                                    {{ __('Cancel') }}
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </template>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile card view for custom units --}}
            <div class="md:hidden space-y-3">
                @foreach($customUnits as $unit)
                    @php
                        $unitDeleteInfo = $deleteInfo[$unit->id] ?? ['can_delete' => true];
                        $canDelete = $unitDeleteInfo['can_delete'];
                        $deleteReason = $unitDeleteInfo['reason'] ?? '';
                        $usageCount = $unit->getUsageCount();
                    @endphp

                    {{-- Display card (hidden when editing this unit) --}}
                    <div
                        x-show="editingId !== {{ $unit->id }}"
                        class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 shadow-card"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h4 class="text-sm font-semibold text-on-surface-strong truncate">{{ $unit->name_en }}</h4>
                                    @if($usageCount > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary-subtle text-primary">
                                            {{ trans_choice(':count component|:count components', $usageCount, ['count' => $usageCount]) }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-on-surface">{{ __('FR') }}: {{ $unit->name_fr }}</p>
                            </div>
                            <div class="flex items-center gap-1 ml-2">
                                <button
                                    @click="startEdit({{ $unit->id }}, '{{ addslashes($unit->name_en) }}', '{{ addslashes($unit->name_fr) }}')"
                                    class="p-2 text-on-surface hover:text-primary hover:bg-primary-subtle rounded-lg transition-colors duration-200"
                                    title="{{ __('Edit') }}"
                                >
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                                </button>
                                @if(!$canDelete)
                                    <button
                                        disabled
                                        class="p-2 text-on-surface/30 cursor-not-allowed rounded-lg"
                                        title="{{ $deleteReason }}"
                                    >
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                    </button>
                                @else
                                    <button
                                        @click="confirmDelete({{ $unit->id }}, '{{ addslashes($unit->name) }}')"
                                        class="p-2 text-on-surface hover:text-danger hover:bg-danger-subtle rounded-lg transition-colors duration-200"
                                        title="{{ __('Delete') }}"
                                    >
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Inline edit card (shown when editing this unit) --}}
                    <div
                        x-show="editingId === {{ $unit->id }}"
                        x-cloak
                        class="bg-surface-alt dark:bg-surface-alt rounded-xl border-2 border-primary dark:border-primary p-4 shadow-card"
                    >
                        <h4 class="text-sm font-semibold text-on-surface-strong mb-3">{{ __('Edit Selling Unit') }}</h4>
                        <form @submit.prevent="$action('/dashboard/selling-units/{{ $unit->id }}', { method: 'PUT', include: ['edit_name_en', 'edit_name_fr'] })">
                            <div class="space-y-3 mb-3">
                                <div>
                                    <label class="block text-sm font-medium text-on-surface mb-1">{{ __('Name (English)') }}</label>
                                    <input
                                        type="text"
                                        x-model="edit_name_en"
                                        x-name="edit_name_en"
                                        maxlength="50"
                                        class="w-full px-3 py-2 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                                    >
                                    <p x-message="edit_name_en" class="mt-1 text-sm text-danger"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-on-surface mb-1">{{ __('Name (French)') }}</label>
                                    <input
                                        type="text"
                                        x-model="edit_name_fr"
                                        x-name="edit_name_fr"
                                        maxlength="50"
                                        class="w-full px-3 py-2 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                                    >
                                    <p x-message="edit_name_fr" class="mt-1 text-sm text-danger"></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <button
                                    type="submit"
                                    :disabled="$fetching()"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-primary text-on-primary rounded-lg font-medium text-sm hover:bg-primary-hover transition-colors duration-200 disabled:opacity-50"
                                >
                                    <span x-show="!$fetching()">{{ __('Save') }}</span>
                                    <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
                                </button>
                                <button
                                    type="button"
                                    @click="cancelEdit()"
                                    class="px-3 py-1.5 text-sm font-medium text-on-surface hover:bg-surface rounded-lg transition-colors duration-200"
                                >
                                    {{ __('Cancel') }}
                                </button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Total count summary --}}
    <div class="mt-4 text-center">
        <p class="text-xs text-on-surface/60">
            {{ trans_choice(':count standard unit|:count standard units', $standardUnits->count(), ['count' => $standardUnits->count()]) }},
            {{ trans_choice(':count custom unit|:count custom units', $customUnits->count(), ['count' => $customUnits->count()]) }}
        </p>
    </div>

    {{-- Delete Confirmation Modal --}}
    <div
        x-show="confirmDeleteId !== null"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
    >
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-black/50" @click="cancelDelete()"></div>

        {{-- Modal content --}}
        <div
            x-show="confirmDeleteId !== null"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative bg-surface dark:bg-surface-alt rounded-xl shadow-dropdown max-w-sm w-full p-6"
        >
            {{-- Warning icon --}}
            <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-danger-subtle flex items-center justify-center">
                <svg class="w-6 h-6 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
            </div>

            <h3 class="text-lg font-semibold text-on-surface-strong text-center mb-2">{{ __('Delete Selling Unit') }}</h3>
            <p class="text-sm text-on-surface text-center mb-6">
                {{ __('Are you sure you want to delete') }} "<span class="font-medium" x-text="confirmDeleteName"></span>"?
                {{ __('This action cannot be undone.') }}
            </p>

            <div class="flex items-center justify-end gap-3">
                <button
                    @click="cancelDelete()"
                    class="px-4 py-2 text-sm font-medium text-on-surface hover:bg-surface-alt dark:hover:bg-surface rounded-lg transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    @click="executeDelete()"
                    class="px-4 py-2 bg-danger text-on-danger rounded-lg font-medium text-sm hover:bg-danger/90 transition-colors duration-200"
                >
                    {{ __('Delete') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
