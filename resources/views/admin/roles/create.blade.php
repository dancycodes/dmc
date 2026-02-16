{{--
    Role Creation Form
    ------------------
    F-052: Create Role
    Admins create custom roles with bilingual names and description.
    Uses Gale for form submission (no full page reload).

    BR-104: Role names must be unique across the platform
    BR-105: Only users with can-access-admin-panel permission
    BR-106: Guard defaults to "web" (hidden field for MVP)
    BR-107: Both name_en and name_fr required
    BR-108: Role creation logged in activity log
    BR-109: Newly created roles have zero permissions
    BR-110: System role names cannot be used

    UI/UX: Simple single-column form with card sections.
    Breadcrumb: Admin > Roles > Create New Role
--}}
@extends('layouts.admin')

@section('title', __('Create New Role'))
@section('page-title', __('Create New Role'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Roles'), 'url' => '/vault-entry/roles'],
        ['label' => __('Create New Role')]
    ]" />

    {{-- Form --}}
    <div
        x-data="{
            name_en: '',
            name_fr: '',
            description: '',
            messages: {}
        }"
        x-sync
        class="max-w-2xl"
    >
        <form @submit.prevent="$action('{{ url('/vault-entry/roles') }}')" class="space-y-6">

            {{-- Role Names --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
                <h3 class="text-base font-semibold text-on-surface-strong mb-1">
                    {{ __('Role Name') }}
                </h3>
                <p class="text-sm text-on-surface/60 mb-4">
                    {{ __('Provide the role name in both English and French. Only letters, numbers, hyphens, and spaces are allowed.') }}
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                    {{-- Name EN --}}
                    <div>
                        <label for="name_en" class="block text-sm font-medium text-on-surface mb-1.5">
                            {{ __('Name (English)') }} <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            id="name_en"
                            x-model="name_en"
                            x-name="name_en"
                            placeholder="{{ __('e.g., Kitchen Assistant') }}"
                            maxlength="100"
                            class="w-full h-10 px-3 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong dark:text-on-surface-strong placeholder:text-on-surface/50
                                   focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200"
                        >
                        <p x-message="name_en" class="mt-1 text-sm text-danger"></p>
                    </div>

                    {{-- Name FR --}}
                    <div>
                        <label for="name_fr" class="block text-sm font-medium text-on-surface mb-1.5">
                            {{ __('Name (French)') }} <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            id="name_fr"
                            x-model="name_fr"
                            x-name="name_fr"
                            placeholder="{{ __('e.g., Assistant de Cuisine') }}"
                            maxlength="100"
                            class="w-full h-10 px-3 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong dark:text-on-surface-strong placeholder:text-on-surface/50
                                   focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200"
                        >
                        <p x-message="name_fr" class="mt-1 text-sm text-danger"></p>
                    </div>
                </div>
            </div>

            {{-- Description --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
                <h3 class="text-base font-semibold text-on-surface-strong mb-1">
                    {{ __('Description') }}
                </h3>
                <p class="text-sm text-on-surface/60 mb-4">
                    {{ __('Optionally describe what this role is used for.') }}
                </p>

                <div>
                    <label for="description" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Description') }}
                        <span class="text-xs text-on-surface/50 ml-1">{{ __('(Optional)') }}</span>
                    </label>
                    <textarea
                        id="description"
                        x-model="description"
                        x-name="description"
                        rows="3"
                        maxlength="500"
                        placeholder="{{ __('e.g., Handles meal preparation tasks for the cook') }}"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong dark:text-on-surface-strong placeholder:text-on-surface/50
                               focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200 resize-y min-h-[80px]"
                    ></textarea>
                    <div class="flex items-center justify-between mt-1">
                        <p x-message="description" class="text-sm text-danger"></p>
                        <span class="text-xs text-on-surface/50" x-text="description.length + '/500'"></span>
                    </div>
                </div>
            </div>

            {{-- Guard Info --}}
            <div class="bg-info-subtle dark:bg-info-subtle rounded-lg border border-info/30 p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                    <div class="text-sm text-on-surface">
                        <p class="font-medium text-info mb-1">{{ __('Guard & Permissions') }}</p>
                        <p>{{ __('This role will use the "web" guard. After creation, you can assign specific permissions to define what users with this role can do.') }}</p>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="$fetching()"
                    :class="$fetching() ? 'opacity-80 cursor-wait' : 'hover:bg-primary-hover active:scale-[0.98]'"
                    class="h-10 px-6 text-sm rounded-lg font-semibold bg-primary text-on-primary transition-all duration-200 inline-flex items-center gap-2
                           focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    <template x-if="!$fetching()">
                        <span class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg>
                            {{ __('Create Role') }}
                        </span>
                    </template>
                    <template x-if="$fetching()">
                        <span class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            {{ __('Creating...') }}
                        </span>
                    </template>
                </button>

                <a
                    href="{{ url('/vault-entry/roles') }}"
                    class="h-10 px-5 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200 inline-flex items-center
                           focus:outline-none focus:ring-2 focus:ring-outline focus:ring-offset-2"
                >
                    {{ __('Cancel') }}
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
