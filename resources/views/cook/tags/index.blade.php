{{--
    Tag Management Page
    -------------------
    F-115: Cook Tag Management
    Allows cooks to create, edit, and delete custom tags for meal categorization.

    Features:
    - Tag list with columns: name (EN/FR), meals count, actions (edit, delete)
    - Inline add tag form at the top
    - Inline edit form per tag
    - Delete confirmation modal with meal count warning
    - All interactions via Gale
    - Mobile-friendly card layout
    - Light/dark mode support
    - All text localized with __()

    BR-252: Tags are tenant-scoped
    BR-253: Tag name required in both EN and FR
    BR-254: Tag name unique within tenant (per language)
    BR-255: Tags cannot be deleted if assigned to any meal
    BR-257: Only users with manage-meals permission
    BR-258: Tag CRUD operations logged
    BR-259: Tag name max length: 50 characters
    BR-260: Case-insensitive uniqueness
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Tags'))
@section('page-title', __('Tags'))

@section('content')
<div
    class="max-w-4xl mx-auto"
    x-data="{
        showAddForm: false,
        name_en: '',
        name_fr: '',
        editingId: null,
        edit_name_en: '',
        edit_name_fr: '',
        confirmDeleteId: null,
        confirmDeleteName: '',
        confirmDeleteMeals: 0,

        openAddForm() {
            this.showAddForm = true;
            this.cancelEdit();
        },
        cancelAdd() {
            this.showAddForm = false;
            this.name_en = '';
            this.name_fr = '';
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
        confirmDelete(id, name, meals) {
            this.confirmDeleteId = id;
            this.confirmDeleteName = name;
            this.confirmDeleteMeals = meals;
        },
        cancelDelete() {
            this.confirmDeleteId = null;
            this.confirmDeleteName = '';
            this.confirmDeleteMeals = 0;
        },
        executeDelete() {
            if (this.confirmDeleteId) {
                $action('/dashboard/tags/' + this.confirmDeleteId, { method: 'DELETE' });
                this.cancelDelete();
            }
        }
    }"
>
    {{-- Header with Add Tag button --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-display font-bold text-on-surface-strong">{{ __('Tags') }}</h2>
            <p class="mt-1 text-sm text-on-surface">{{ __('Manage custom tags for categorizing your meals.') }}</p>
        </div>
        <button
            x-show="!showAddForm"
            @click="openAddForm()"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-on-primary rounded-lg font-medium text-sm hover:bg-primary-hover transition-colors duration-200 shadow-sm"
        >
            {{-- Plus icon (Lucide) --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
            <span class="hidden sm:inline">{{ __('Add Tag') }}</span>
        </button>
    </div>

    {{-- Add Tag Form (inline, collapsible) --}}
    <div
        x-show="showAddForm"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-cloak
        class="mb-6 bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 sm:p-6 shadow-card"
    >
        <h3 class="text-base font-semibold text-on-surface-strong mb-4">{{ __('New Tag') }}</h3>
        <form @submit.prevent="$action('{{ route('cook.tags.store') }}', { include: ['name_en', 'name_fr'] })">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                {{-- English name --}}
                <div>
                    <label for="add-name-en" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('English Name') }} <span class="text-danger">*</span>
                    </label>
                    <input
                        id="add-name-en"
                        type="text"
                        x-name="name_en"
                        maxlength="50"
                        placeholder="{{ __('e.g. Spicy') }}"
                        class="w-full px-3 py-2.5 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                    >
                    <p x-message="name_en" class="mt-1 text-sm text-danger"></p>
                </div>
                {{-- French name --}}
                <div>
                    <label for="add-name-fr" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('French Name') }} <span class="text-danger">*</span>
                    </label>
                    <input
                        id="add-name-fr"
                        type="text"
                        x-name="name_fr"
                        maxlength="50"
                        placeholder="{{ __('e.g. Epice') }}"
                        class="w-full px-3 py-2.5 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                    >
                    <p x-message="name_fr" class="mt-1 text-sm text-danger"></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="$fetching()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-on-primary rounded-lg font-medium text-sm hover:bg-primary-hover transition-colors duration-200 disabled:opacity-50"
                >
                    <span x-show="!$fetching()">{{ __('Create Tag') }}</span>
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

    {{-- Tag List --}}
    @if($tags->isEmpty())
        {{-- Empty state --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-8 sm:p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-primary-subtle flex items-center justify-center">
                {{-- Tag icon (Lucide) --}}
                <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"></path><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"></circle></svg>
            </div>
            <h3 class="text-lg font-semibold text-on-surface-strong mb-2">{{ __('No tags yet') }}</h3>
            <p class="text-sm text-on-surface mb-4">{{ __('Create tags to categorize your meals. Tags help customers find what they want.') }}</p>
            <button
                @click="openAddForm()"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-on-primary rounded-lg font-medium text-sm hover:bg-primary-hover transition-colors duration-200"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                {{ __('Create Your First Tag') }}
            </button>
        </div>
    @else
        {{-- Desktop table view --}}
        <div class="hidden md:block bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-outline dark:border-outline">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('English Name') }}</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('French Name') }}</th>
                        <th class="px-6 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('Meals') }}</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-on-surface/60">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline dark:divide-outline">
                    @foreach($tags as $tag)
                        <tr class="hover:bg-surface dark:hover:bg-surface transition-colors duration-150">
                            {{-- Display row (hidden when editing this tag) --}}
                            <template x-if="editingId !== {{ $tag->id }}">
                                <td class="px-6 py-4 text-sm font-medium text-on-surface-strong">{{ $tag->name_en }}</td>
                            </template>
                            <template x-if="editingId !== {{ $tag->id }}">
                                <td class="px-6 py-4 text-sm text-on-surface">{{ $tag->name_fr }}</td>
                            </template>
                            <template x-if="editingId !== {{ $tag->id }}">
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $tag->meals_count > 0 ? 'bg-primary-subtle text-primary' : 'bg-surface text-on-surface/60' }}">
                                        {{ $tag->meals_count }}
                                    </span>
                                </td>
                            </template>
                            <template x-if="editingId !== {{ $tag->id }}">
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        {{-- Edit button --}}
                                        <button
                                            @click="startEdit({{ $tag->id }}, '{{ addslashes($tag->name_en) }}', '{{ addslashes($tag->name_fr) }}')"
                                            class="p-2 text-on-surface hover:text-primary hover:bg-primary-subtle rounded-lg transition-colors duration-200"
                                            title="{{ __('Edit') }}"
                                        >
                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                                        </button>
                                        {{-- Delete button --}}
                                        @if($tag->meals_count > 0)
                                            <button
                                                disabled
                                                class="p-2 text-on-surface/30 cursor-not-allowed rounded-lg"
                                                title="{{ __('Cannot delete — assigned to :count meals', ['count' => $tag->meals_count]) }}"
                                            >
                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                            </button>
                                        @else
                                            <button
                                                @click="confirmDelete({{ $tag->id }}, '{{ addslashes($tag->name_en) }}', {{ $tag->meals_count }})"
                                                class="p-2 text-on-surface hover:text-danger hover:bg-danger-subtle rounded-lg transition-colors duration-200"
                                                title="{{ __('Delete') }}"
                                            >
                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </template>

                            {{-- Inline edit form (shown when editing this tag) --}}
                            <template x-if="editingId === {{ $tag->id }}">
                                <td colspan="4" class="px-6 py-4">
                                    <form @submit.prevent="$action('/dashboard/tags/{{ $tag->id }}', { method: 'PUT', include: ['edit_name_en', 'edit_name_fr'] })">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-3">
                                            <div>
                                                <label class="block text-sm font-medium text-on-surface mb-1">{{ __('English Name') }}</label>
                                                <input
                                                    type="text"
                                                    x-model="edit_name_en"
                                                    maxlength="50"
                                                    class="w-full px-3 py-2 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                                                >
                                                <p x-message="edit_name_en" class="mt-1 text-sm text-danger"></p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-on-surface mb-1">{{ __('French Name') }}</label>
                                                <input
                                                    type="text"
                                                    x-model="edit_name_fr"
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

        {{-- Mobile card view --}}
        <div class="md:hidden space-y-3">
            @foreach($tags as $tag)
                {{-- Display card (hidden when editing this tag) --}}
                <div
                    x-show="editingId !== {{ $tag->id }}"
                    class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline p-4 shadow-card"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="text-sm font-semibold text-on-surface-strong truncate">{{ $tag->name_en }}</h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $tag->meals_count > 0 ? 'bg-primary-subtle text-primary' : 'bg-surface text-on-surface/60' }}">
                                    {{ trans_choice(':count meal|:count meals', $tag->meals_count, ['count' => $tag->meals_count]) }}
                                </span>
                            </div>
                            <p class="text-xs text-on-surface">{{ __('FR') }}: {{ $tag->name_fr }}</p>
                        </div>
                        <div class="flex items-center gap-1 ml-2">
                            <button
                                @click="startEdit({{ $tag->id }}, '{{ addslashes($tag->name_en) }}', '{{ addslashes($tag->name_fr) }}')"
                                class="p-2 text-on-surface hover:text-primary hover:bg-primary-subtle rounded-lg transition-colors duration-200"
                                title="{{ __('Edit') }}"
                            >
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path></svg>
                            </button>
                            @if($tag->meals_count > 0)
                                <button
                                    disabled
                                    class="p-2 text-on-surface/30 cursor-not-allowed rounded-lg"
                                    title="{{ __('Cannot delete — assigned to :count meals', ['count' => $tag->meals_count]) }}"
                                >
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                </button>
                            @else
                                <button
                                    @click="confirmDelete({{ $tag->id }}, '{{ addslashes($tag->name_en) }}', {{ $tag->meals_count }})"
                                    class="p-2 text-on-surface hover:text-danger hover:bg-danger-subtle rounded-lg transition-colors duration-200"
                                    title="{{ __('Delete') }}"
                                >
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Inline edit card (shown when editing this tag) --}}
                <div
                    x-show="editingId === {{ $tag->id }}"
                    x-cloak
                    class="bg-surface-alt dark:bg-surface-alt rounded-xl border-2 border-primary dark:border-primary p-4 shadow-card"
                >
                    <h3 class="text-sm font-semibold text-on-surface-strong mb-3">{{ __('Edit Tag') }}</h3>
                    <form @submit.prevent="$action('/dashboard/tags/{{ $tag->id }}', { method: 'PUT', include: ['edit_name_en', 'edit_name_fr'] })">
                        <div class="space-y-3 mb-3">
                            <div>
                                <label class="block text-sm font-medium text-on-surface mb-1">{{ __('English Name') }}</label>
                                <input
                                    type="text"
                                    x-model="edit_name_en"
                                    maxlength="50"
                                    class="w-full px-3 py-2 bg-surface dark:bg-surface text-on-surface border border-outline dark:border-outline rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors duration-200"
                                >
                                <p x-message="edit_name_en" class="mt-1 text-sm text-danger"></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-on-surface mb-1">{{ __('French Name') }}</label>
                                <input
                                    type="text"
                                    x-model="edit_name_fr"
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

        {{-- Tags count summary --}}
        <div class="mt-4 text-center">
            <p class="text-xs text-on-surface/60">
                {{ trans_choice(':count tag|:count tags', $tags->count(), ['count' => $tags->count()]) }}
            </p>
        </div>
    @endif

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

            <h3 class="text-lg font-semibold text-on-surface-strong text-center mb-2">{{ __('Delete Tag') }}</h3>
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
