{{--
    Permission Assignment to Roles
    ------------------------------
    F-056: Permission Assignment to Roles
    Admins assign/revoke permissions for a role using grouped checkboxes.
    Changes save immediately via Gale without full page reload.

    BR-128: Super-admin role always has ALL permissions; cannot be modified (read-only)
    BR-129: Admin can only assign permissions they themselves possess
    BR-130: Permissions the admin does not have are visible but disabled
    BR-131: Changes save immediately without full page reload (via Gale)
    BR-132: Permission changes take effect on the user's next request
    BR-133: Permission changes logged in activity log
    BR-134: Permissions grouped by module for organized display

    UI/UX: Collapsible sections by module, Select All/Deselect All per module,
    disabled checkboxes for inaccessible permissions, green flash on save,
    count indicator per section, sticky header with role name.
    Breadcrumb: Admin > Roles > {Role Name} > Permissions
--}}
@extends('layouts.admin')

@section('title', __('Permissions') . ' — ' . ($role->name_en ?? $role->name))
@section('page-title', __('Permissions'))

@section('content')
@php
    // Build the permissions state map for Alpine
    $permissionsState = [];
    foreach ($groupedPermissions as $module => $moduleData) {
        foreach ($moduleData['permissions'] as $perm) {
            $permissionsState[$perm['name']] = $perm['assigned'];
        }
    }

    // Build modules structure for Alpine
    $modulesData = [];
    foreach ($groupedPermissions as $module => $moduleData) {
        $modulesData[$module] = [
            'all' => collect($moduleData['permissions'])->pluck('name')->toArray(),
            'assignable' => collect($moduleData['permissions'])->filter(fn($p) => $p['canAssign'])->pluck('name')->toArray(),
        ];
    }
@endphp
<div class="space-y-6"
    x-data="{
        assignedCount: {{ $assignedCount }},
        totalPermissions: {{ $totalPermissions }},
        lastToggled: null,
        lastAction: null,
        error: null,
        messages: {},
        saving: {},
        permissions: {{ json_encode($permissionsState) }},
        modules: {{ json_encode($modulesData) }},
        expanded: {},
        flashTimeout: null,
        init() {
            Object.keys(this.modules).forEach(m => { this.expanded[m] = true; });
        },
        toggleExpand(module) {
            this.expanded[module] = !this.expanded[module];
        },
        isExpanded(module) {
            return this.expanded[module] !== false;
        },
        moduleAssigned(module) {
            return this.modules[module].all.filter(p => this.permissions[p]).length;
        },
        moduleTotal(module) {
            return this.modules[module].all.length;
        },
        allAssignableChecked(module) {
            const a = this.modules[module].assignable;
            if (a.length === 0) return false;
            return a.every(p => this.permissions[p]);
        },
        someAssignableChecked(module) {
            const a = this.modules[module].assignable;
            if (a.length === 0) return false;
            const checked = a.filter(p => this.permissions[p]).length;
            return checked > 0 && checked < a.length;
        },
        async togglePermission(permName) {
            if (this.saving[permName]) return;
            this.saving[permName] = true;
            this.error = null;
            const prev = this.permissions[permName];
            this.permissions[permName] = !prev;
            try {
                await $action('{{ url('/vault-entry/roles/' . $role->id . '/permissions/toggle') }}', {
                    include: ['permission'],
                    beforeSend(state) {
                        state.permission = permName;
                        return state;
                    }
                });
                this.flashToggled(permName);
            } catch (e) {
                this.permissions[permName] = prev;
                this.error = '{{ __('An error occurred. Please try again.') }}';
            } finally {
                this.saving[permName] = false;
            }
        },
        async toggleModule(module) {
            const shouldGrant = !this.allAssignableChecked(module);
            const perms = this.modules[module].assignable;
            if (perms.length === 0) return;
            this.error = null;
            const prevStates = {};
            perms.forEach(p => {
                prevStates[p] = this.permissions[p];
                this.permissions[p] = shouldGrant;
            });
            try {
                await $action('{{ url('/vault-entry/roles/' . $role->id . '/permissions/toggle-module') }}', {
                    include: ['permissions', 'grant'],
                    beforeSend(state) {
                        state.permissions = perms;
                        state.grant = shouldGrant;
                        return state;
                    }
                });
            } catch (e) {
                Object.entries(prevStates).forEach(([p, v]) => { this.permissions[p] = v; });
                this.error = '{{ __('An error occurred. Please try again.') }}';
            }
        },
        flashToggled(permName) {
            this.lastToggled = permName;
            if (this.flashTimeout) clearTimeout(this.flashTimeout);
            this.flashTimeout = setTimeout(() => { this.lastToggled = null; }, 1200);
        }
    }"
    x-sync
>
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Roles'), 'url' => '/vault-entry/roles'],
        ['label' => $role->name_en ?? $role->name, 'url' => '/vault-entry/roles/' . $role->id . '/edit'],
        ['label' => __('Permissions')],
    ]" />

    {{-- Sticky Header --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5 sticky top-0 z-10">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                @php
                    $roleIconClass = match($role->name) {
                        'super-admin' => 'bg-danger-subtle text-danger',
                        'admin' => 'bg-warning-subtle text-warning',
                        'cook' => 'bg-info-subtle text-info',
                        'manager' => 'bg-secondary-subtle text-secondary',
                        'client' => 'bg-success-subtle text-success',
                        default => 'bg-primary-subtle text-primary',
                    };
                @endphp
                <span class="w-10 h-10 rounded-full {{ $roleIconClass }} flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path></svg>
                </span>
                <div>
                    <h2 class="text-lg font-semibold text-on-surface-strong">
                        {{ $role->name_en ?? $role->name }}
                    </h2>
                    <p class="text-sm text-on-surface/60">
                        {{ __('Manage permissions for this role') }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                {{-- Permission counter --}}
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-subtle text-primary text-sm font-medium">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"></rect><path d="m9 12 2 2 4-4"></path></svg>
                    <span x-text="assignedCount + '/' + totalPermissions"></span>
                    <span class="hidden sm:inline">{{ __('permissions') }}</span>
                </div>
                {{-- Back to role edit --}}
                <a
                    href="{{ url('/vault-entry/roles/' . $role->id . '/edit') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-outline text-on-surface hover:bg-surface hover:border-primary/30 hover:text-primary transition-colors"
                >
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path><path d="m15 5 4 4"></path></svg>
                    {{ __('Edit Role') }}
                </a>
            </div>
        </div>
    </div>

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

    {{-- Error message --}}
    <template x-if="error">
        <div class="p-4 rounded-lg border bg-danger-subtle border-danger/30 text-danger">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
                <p class="text-sm font-medium" x-text="error"></p>
            </div>
        </div>
    </template>

    {{-- BR-128: Super-admin read-only notice --}}
    @if($isReadOnly)
        <div class="bg-warning-subtle dark:bg-warning-subtle rounded-lg border border-warning/30 p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-warning shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                <div class="text-sm text-on-surface">
                    <p class="font-semibold text-warning mb-1">{{ __('Read-Only') }}</p>
                    <p>{{ __('Super-admin always has all permissions. This cannot be modified.') }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Permission Modules --}}
    <div class="space-y-4">
        @foreach($groupedPermissions as $module => $moduleData)
            @php
                $modulePermissions = $moduleData['permissions'];
                $moduleKey = \Illuminate\Support\Str::slug($module);
                $assignableInModule = collect($modulePermissions)->filter(fn($p) => $p['canAssign'])->pluck('name')->toArray();
            @endphp
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                {{-- Module Header --}}
                <div
                    class="flex items-center justify-between px-4 py-3 sm:px-5 cursor-pointer select-none hover:bg-surface dark:hover:bg-surface transition-colors"
                    @click="toggleExpand({{ json_encode($module) }})"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        {{-- Expand/Collapse Arrow --}}
                        <svg
                            class="w-4 h-4 text-on-surface/60 transition-transform duration-200 shrink-0"
                            :class="isExpanded({{ json_encode($module) }}) ? 'rotate-90' : ''"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        ><polyline points="9 18 15 12 9 6"></polyline></svg>

                        <h3 class="text-sm font-semibold text-on-surface-strong">
                            {{ __($module) }}
                        </h3>

                        {{-- Count indicator --}}
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                              :class="moduleAssigned({{ json_encode($module) }}) === moduleTotal({{ json_encode($module) }}) ? 'bg-success-subtle text-success' : moduleAssigned({{ json_encode($module) }}) > 0 ? 'bg-primary-subtle text-primary' : 'bg-outline/20 text-on-surface/60'"
                        >
                            <span x-text="moduleAssigned({{ json_encode($module) }}) + '/' + moduleTotal({{ json_encode($module) }})"></span>
                            <span class="hidden sm:inline"> {{ __('selected') }}</span>
                        </span>
                    </div>

                    {{-- Select All checkbox (only for non-read-only and modules with assignable perms) --}}
                    @if(!$isReadOnly && count($assignableInModule) > 0)
                        <div class="flex items-center gap-2" @click.stop>
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-on-surface/60 hover:text-on-surface transition-colors">
                                <span class="hidden sm:inline">{{ __('Select All') }}</span>
                                <input
                                    type="checkbox"
                                    :checked="allAssignableChecked({{ json_encode($module) }})"
                                    :indeterminate="someAssignableChecked({{ json_encode($module) }})"
                                    @click.prevent="toggleModule({{ json_encode($module) }})"
                                    class="w-4 h-4 rounded border-outline text-primary focus:ring-primary focus:ring-offset-0 cursor-pointer"
                                >
                            </label>
                        </div>
                    @endif
                </div>

                {{-- Permission Checkboxes --}}
                <div
                    x-show="isExpanded({{ json_encode($module) }})"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="border-t border-outline dark:border-outline px-4 py-3 sm:px-5"
                >
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        @foreach($modulePermissions as $perm)
                            @php
                                $permName = $perm['name'];
                                $isDisabled = $isReadOnly || !$perm['canAssign'];
                                $verb = ucfirst($perm['verb']);
                            @endphp
                            <label
                                class="relative flex items-start gap-3 p-2.5 rounded-lg border transition-all duration-200 {{ $isDisabled ? 'border-outline/30 dark:border-outline/30 opacity-60 cursor-not-allowed' : 'border-outline dark:border-outline hover:border-primary/40 hover:bg-primary-subtle/30 cursor-pointer' }}"
                                :class="{
                                    'border-primary/40 bg-primary-subtle/20': permissions[{{ json_encode($permName) }}] && !{{ json_encode($isDisabled) }},
                                    'ring-2 ring-success ring-offset-1 ring-offset-surface-alt': lastToggled === {{ json_encode($permName) }}
                                }"
                                @if(!$isDisabled)
                                    @click.prevent="togglePermission({{ json_encode($permName) }})"
                                @endif
                                @if($isDisabled && !$isReadOnly)
                                    title="{{ __('You cannot assign permissions you do not have.') }}"
                                @endif
                            >
                                <input
                                    type="checkbox"
                                    :checked="permissions[{{ json_encode($permName) }}]"
                                    {{ $isDisabled ? 'disabled' : '' }}
                                    class="mt-0.5 w-4 h-4 rounded border-outline text-primary focus:ring-primary focus:ring-offset-0 {{ $isDisabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }}"
                                    @click.prevent
                                >
                                <div class="min-w-0">
                                    <span class="text-sm font-medium text-on-surface-strong block leading-tight">
                                        {{ __($verb) }}
                                    </span>
                                    <span class="text-xs text-on-surface/50 font-mono block mt-0.5 truncate">
                                        {{ $permName }}
                                    </span>
                                </div>
                                {{-- Loading spinner --}}
                                <template x-if="saving[{{ json_encode($permName) }}]">
                                    <span class="absolute top-2 right-2">
                                        <svg class="w-3.5 h-3.5 animate-spin-slow text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                    </span>
                                </template>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Info note --}}
    <div class="bg-info-subtle dark:bg-info-subtle rounded-lg border border-info/30 p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
            <div class="text-sm text-on-surface">
                <p class="font-medium text-info mb-1">{{ __('How permissions work') }}</p>
                <p>{{ __('Changes save immediately. Permission changes take effect on the user\'s next request — no logout required. Users with this role will gain or lose access to features based on the permissions you assign here.') }}</p>
            </div>
        </div>
    </div>

    {{-- Back to Roles --}}
    <div class="flex items-center gap-3">
        <a
            href="{{ url('/vault-entry/roles') }}"
            class="h-10 px-5 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200 inline-flex items-center gap-2
                   focus:outline-none focus:ring-2 focus:ring-outline focus:ring-offset-2"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
            {{ __('Back to Roles') }}
        </a>
    </div>
</div>
@endsection
