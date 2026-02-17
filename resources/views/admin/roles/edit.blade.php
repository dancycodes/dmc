{{--
    Role Edit Form
    ---------------
    F-054: Edit Role
    Admins edit a role's name and description. System role names are read-only.
    Permissions are displayed as a read-only summary with a link to F-056.

    BR-116: System role names (en and fr) cannot be changed
    BR-117: System role descriptions can be updated
    BR-118: Custom role names can be changed but must remain unique
    BR-119: Role name uniqueness check excludes current role's own name
    BR-120: All edits are logged in the activity log
    BR-121: Permission modifications redirected to F-056

    UI/UX: Pre-filled form, system roles have disabled name fields with note,
    read-only permissions summary grouped by module, save and cancel buttons.
    Breadcrumb: Admin > Roles > {Role Name} > Edit
--}}
@extends('layouts.admin')

@section('title', __('Edit Role') . ' — ' . ($role->name_en ?? $role->name))
@section('page-title', __('Edit Role'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Roles'), 'url' => '/vault-entry/roles'],
        ['label' => $role->name_en ?? $role->name],
        ['label' => __('Edit')],
    ]" />

    {{-- Toast message --}}
    @if(session('toast'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="p-4 rounded-lg border {{ session('toast.type') === 'success' ? 'bg-success-subtle border-success/30 text-success' : 'bg-danger-subtle border-danger/30 text-danger' }}"
        >
            <div class="flex items-center gap-2">
                @if(session('toast.type') === 'success')
                    <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                @endif
                <p class="text-sm font-medium">{{ session('toast.message') }}</p>
            </div>
        </div>
    @endif

    {{-- Edit Form --}}
    <div
        x-data="{
            name_en: {{ json_encode($role->name_en ?? '') }},
            name_fr: {{ json_encode($role->name_fr ?? '') }},
            description: {{ json_encode($role->description ?? '') }},
            messages: {}
        }"
        x-sync
        class="max-w-2xl"
    >
        <form @submit.prevent="$action('{{ url('/vault-entry/roles/' . $role->id) }}')" class="space-y-6">

            {{-- Role Names --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
                <h3 class="text-base font-semibold text-on-surface-strong mb-1">
                    {{ __('Role Name') }}
                </h3>
                @if($role->is_system)
                    {{-- BR-116: System role names cannot be changed --}}
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-4 h-4 text-warning shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        <p class="text-sm text-warning">{{ __('System role names cannot be changed.') }}</p>
                    </div>
                @else
                    <p class="text-sm text-on-surface/60 mb-4">
                        {{ __('Update the role name in both English and French. Only letters, numbers, hyphens, and spaces are allowed.') }}
                    </p>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                    {{-- Name EN --}}
                    <div>
                        <label for="name_en" class="block text-sm font-medium text-on-surface mb-1.5">
                            {{ __('Name (English)') }} <span class="text-danger">*</span>
                        </label>
                        @if($role->is_system)
                            <input
                                type="text"
                                id="name_en"
                                value="{{ $role->name_en }}"
                                disabled
                                class="w-full h-10 px-3 text-sm rounded-lg border border-outline/50 dark:border-outline/50 bg-surface-alt dark:bg-surface text-on-surface/50 dark:text-on-surface/50 cursor-not-allowed"
                            >
                        @else
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
                        @endif
                    </div>

                    {{-- Name FR --}}
                    <div>
                        <label for="name_fr" class="block text-sm font-medium text-on-surface mb-1.5">
                            {{ __('Name (French)') }} <span class="text-danger">*</span>
                        </label>
                        @if($role->is_system)
                            <input
                                type="text"
                                id="name_fr"
                                value="{{ $role->name_fr }}"
                                disabled
                                class="w-full h-10 px-3 text-sm rounded-lg border border-outline/50 dark:border-outline/50 bg-surface-alt dark:bg-surface text-on-surface/50 dark:text-on-surface/50 cursor-not-allowed"
                            >
                        @else
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
                        @endif
                    </div>
                </div>

                {{-- Machine name preview for custom roles --}}
                @if(!$role->is_system)
                    <div class="mt-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-on-surface/60 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                        <span class="text-xs font-mono text-on-surface/60">
                            {{ __('Machine name:') }}
                            <span x-text="name_en ? name_en.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '') : '{{ $role->name }}'"></span>
                        </span>
                    </div>
                @else
                    <div class="mt-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-on-surface/60 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                        <span class="text-xs font-mono text-on-surface/60">
                            {{ __('Machine name:') }} {{ $role->name }}
                        </span>
                    </div>
                @endif
            </div>

            {{-- Description --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
                <h3 class="text-base font-semibold text-on-surface-strong mb-1">
                    {{ __('Description') }}
                </h3>
                <p class="text-sm text-on-surface/60 mb-4">
                    {{ __('Describe what this role is used for.') }}
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
                        <span class="text-xs text-on-surface/50" x-text="(description || '').length + '/500'"></span>
                    </div>
                </div>
            </div>

            {{-- Permissions Summary (Read-only, BR-121) --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-semibold text-on-surface-strong">
                            {{ __('Permissions') }}
                        </h3>
                        <p class="text-sm text-on-surface/60 mt-1">
                            {{ __('Currently assigned permissions for this role.') }}
                        </p>
                    </div>
                    {{-- Manage Permissions link (F-056) --}}
                    <a
                        href="{{ url('/vault-entry/roles/' . $role->id . '/permissions') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-primary/30 text-primary hover:bg-primary-subtle transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        {{ __('Manage Permissions') }}
                    </a>
                </div>

                @if(count($groupedPermissions) > 0)
                    <div class="space-y-3">
                        @foreach($groupedPermissions as $module => $verbs)
                            <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-3">
                                <span class="text-sm font-semibold text-on-surface-strong min-w-[140px] shrink-0">
                                    {{ $module }}:
                                </span>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($verbs as $verb)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-subtle text-primary">
                                            {{ $verb }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4">
                        <svg class="w-8 h-8 mx-auto text-on-surface/30 mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"></rect><path d="m9 12 2 2 4-4"></path></svg>
                        <p class="text-sm text-on-surface/60">{{ __('No permissions assigned to this role yet.') }}</p>
                    </div>
                @endif
            </div>

            {{-- Role Info --}}
            <div class="bg-info-subtle dark:bg-info-subtle rounded-lg border border-info/30 p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                    <div class="text-sm text-on-surface">
                        <p class="font-medium text-info mb-1">{{ __('Guard & Type') }}</p>
                        <p>
                            {{ __('This role uses the ":guard" guard and is a :type role.', [
                                'guard' => $role->guard_name,
                                'type' => $role->is_system ? __('system') : __('custom'),
                            ]) }}
                            @if($role->is_system)
                                {{ __('System role names cannot be changed, but you can update the description.') }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- Submit & Cancel --}}
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
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                            {{ __('Save Changes') }}
                        </span>
                    </template>
                    <template x-if="$fetching()">
                        <span class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            {{ __('Saving...') }}
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
