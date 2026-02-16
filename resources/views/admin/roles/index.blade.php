{{--
    Role List View
    --------------
    F-053: Displays all roles with permission counts, user counts, type badges,
    and filter tabs (All / System / Custom).
    F-055: Delete Role with confirmation modal (type role name to confirm).

    BR-111: System roles are: super-admin, admin, cook, manager, client
    BR-112: System roles display a "System" badge and cannot be deleted
    BR-113: Permission count reflects total permissions assigned to the role
    BR-114: User count reflects total users currently holding this role
    BR-115: Roles sorted: system first (hierarchy order), then custom alphabetically
    BR-122: System roles cannot be deleted (no delete option shown)
    BR-123: Custom roles with assigned users cannot be deleted (button disabled)
    BR-124: Deletion is permanent
    BR-125: Deletion requires typing role name to confirm

    UI/UX: Table layout with filter tabs, summary cards, mobile cards, Create Role button.
    Breadcrumb: Admin > Roles
--}}
@extends('layouts.admin')

@section('title', __('Roles'))
@section('page-title', __('Roles'))

@section('content')
<div class="space-y-6"
    x-data="{
        deleteModal: false,
        deleteRoleId: null,
        deleteRoleName: '',
        deleteRolePermCount: 0,
        deleteRoleUserCount: 0,
        confirmRoleName: '',
        deleteError: '',
        deleting: false,
        messages: {},
        confirmDelete(id, name, permCount, userCount) {
            this.deleteRoleId = id;
            this.deleteRoleName = name;
            this.deleteRolePermCount = permCount;
            this.deleteRoleUserCount = userCount;
            this.confirmRoleName = '';
            this.deleteError = '';
            this.messages = {};
            this.deleteModal = true;
        },
        cancelDelete() {
            this.deleteModal = false;
            this.deleteRoleId = null;
            this.deleteRoleName = '';
            this.deleteRolePermCount = 0;
            this.deleteRoleUserCount = 0;
            this.confirmRoleName = '';
            this.deleteError = '';
            this.messages = {};
        },
        async executeDelete() {
            if (this.deleting || this.confirmRoleName.trim() !== this.deleteRoleName) return;
            this.deleting = true;
            this.deleteError = '';
            try {
                await $action('/vault-entry/roles/' + this.deleteRoleId, {
                    method: 'DELETE',
                    include: ['confirmRoleName']
                });
            } catch (e) {
                this.deleteError = '{{ __('An error occurred while deleting the role. Please try again.') }}';
            } finally {
                this.deleting = false;
            }
        }
    }"
    x-sync
    x-on:open-delete-modal.window="confirmDelete($event.detail.id, $event.detail.name, $event.detail.permCount, $event.detail.userCount)"
>
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Roles')]
    ]" />

    {{-- Header with Create button --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-on-surface-strong">{{ __('All Roles') }}</h2>
            <p class="text-sm text-on-surface mt-1">{{ __('Manage platform roles and their permissions.') }}</p>
        </div>
        @can('can-manage-roles')
            <a
                href="{{ url('/vault-entry/roles/create') }}"
                class="h-10 px-5 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 inline-flex items-center gap-2 self-start shrink-0
                       focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98]"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                {{ __('Create Role') }}
            </a>
        @endcan
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

    {{-- Summary Cards --}}
    <div class="grid grid-cols-3 gap-4">
        {{-- Total Roles --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide">{{ __('Total') }}</p>
                    <p class="text-2xl font-bold text-on-surface-strong">{{ $totalCount }}</p>
                </div>
            </div>
        </div>

        {{-- System Roles --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-warning-subtle flex items-center justify-center">
                    <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path><path d="m9 12 2 2 4-4"></path></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide">{{ __('System') }}</p>
                    <p class="text-2xl font-bold text-warning">{{ $systemCount }}</p>
                </div>
            </div>
        </div>

        {{-- Custom Roles --}}
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center">
                    <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-on-surface uppercase tracking-wide">{{ __('Custom') }}</p>
                    <p class="text-2xl font-bold text-info">{{ $customCount }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Main content with filter tabs and role table --}}
    @fragment('role-list-content')
    <div id="role-list-content"
         x-data="{
             type: '{{ addslashes($type ?? '') }}',
             baseUrl: '{{ url('/vault-entry/roles') }}',
             setType(val) {
                 this.type = val;
                 let url = this.baseUrl;
                 if (val) url += '?type=' + val;
                 $navigate(url, { key: 'role-list', replace: true });
             }
         }"
    >
        {{-- Filter Tabs --}}
        <div class="flex items-center gap-1 mb-4 border-b border-outline dark:border-outline">
            <button
                @click="setType('')"
                :class="type === '' ? 'border-primary text-primary font-semibold' : 'border-transparent text-on-surface hover:text-on-surface-strong hover:border-outline'"
                class="px-4 py-2.5 text-sm border-b-2 transition-colors duration-200 -mb-px"
            >
                {{ __('All') }}
                <span class="ml-1 text-xs font-normal px-1.5 py-0.5 rounded-full"
                      :class="type === '' ? 'bg-primary-subtle text-primary' : 'bg-outline/20 text-on-surface/60'"
                >{{ $totalCount }}</span>
            </button>
            <button
                @click="setType('system')"
                :class="type === 'system' ? 'border-primary text-primary font-semibold' : 'border-transparent text-on-surface hover:text-on-surface-strong hover:border-outline'"
                class="px-4 py-2.5 text-sm border-b-2 transition-colors duration-200 -mb-px"
            >
                {{ __('System') }}
                <span class="ml-1 text-xs font-normal px-1.5 py-0.5 rounded-full"
                      :class="type === 'system' ? 'bg-primary-subtle text-primary' : 'bg-outline/20 text-on-surface/60'"
                >{{ $systemCount }}</span>
            </button>
            <button
                @click="setType('custom')"
                :class="type === 'custom' ? 'border-primary text-primary font-semibold' : 'border-transparent text-on-surface hover:text-on-surface-strong hover:border-outline'"
                class="px-4 py-2.5 text-sm border-b-2 transition-colors duration-200 -mb-px"
            >
                {{ __('Custom') }}
                <span class="ml-1 text-xs font-normal px-1.5 py-0.5 rounded-full"
                      :class="type === 'custom' ? 'bg-primary-subtle text-primary' : 'bg-outline/20 text-on-surface/60'"
                >{{ $customCount }}</span>
            </button>
        </div>

        @if($roles->count() > 0)
            {{-- Desktop Table --}}
            <div class="hidden md:block bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-outline dark:border-outline">
                                <th class="text-left text-xs font-semibold uppercase tracking-wider text-on-surface/60 px-4 py-3">{{ __('Role Name') }}</th>
                                <th class="text-left text-xs font-semibold uppercase tracking-wider text-on-surface/60 px-4 py-3">{{ __('Type') }}</th>
                                <th class="text-center text-xs font-semibold uppercase tracking-wider text-on-surface/60 px-4 py-3">{{ __('Permissions') }}</th>
                                <th class="text-center text-xs font-semibold uppercase tracking-wider text-on-surface/60 px-4 py-3">{{ __('Users') }}</th>
                                <th class="text-right text-xs font-semibold uppercase tracking-wider text-on-surface/60 px-4 py-3">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline dark:divide-outline" x-navigate>
                            @foreach($roles as $role)
                                <tr class="hover:bg-surface dark:hover:bg-surface transition-colors group">
                                    {{-- Role Name --}}
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/vault-entry/roles/' . $role->id . '/edit') }}" class="block">
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
                                                <span class="w-9 h-9 rounded-full {{ $roleIconClass }} flex items-center justify-center shrink-0">
                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path></svg>
                                                </span>
                                                <div>
                                                    <span class="text-sm font-medium text-on-surface-strong group-hover:text-primary transition-colors">
                                                        {{ $role->name_en ?? $role->name }}
                                                    </span>
                                                    @if($role->name_fr)
                                                        <span class="text-xs text-on-surface/50 ml-1">({{ $role->name_fr }})</span>
                                                    @endif
                                                    <div class="text-xs text-on-surface/50 font-mono mt-0.5">{{ $role->name }}</div>
                                                    @if($role->description)
                                                        <p class="text-xs text-on-surface/60 mt-0.5 max-w-xs truncate">{{ $role->description }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </a>
                                    </td>

                                    {{-- Type Badge --}}
                                    <td class="px-4 py-3">
                                        @if($role->is_system)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border border-warning/30 text-warning bg-transparent">
                                                <svg class="w-3 h-3 mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                                {{ __('System') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-info-subtle text-info">
                                                {{ __('Custom') }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Permission Count (BR-113) --}}
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-sm font-semibold text-on-surface-strong">{{ $role->permissions_count }}</span>
                                    </td>

                                    {{-- User Count (BR-114) --}}
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-sm font-semibold text-on-surface-strong">{{ $role->users_count }}</span>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            {{-- Edit --}}
                                            <a
                                                href="{{ url('/vault-entry/roles/' . $role->id . '/edit') }}"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-outline text-on-surface hover:bg-surface hover:border-primary/30 hover:text-primary transition-colors"
                                                title="{{ __('Edit') }}"
                                            >
                                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path><path d="m15 5 4 4"></path></svg>
                                                {{ __('Edit') }}
                                            </a>

                                            {{-- Delete (F-055) --}}
                                            @if(!$role->is_system)
                                                @can('can-manage-roles')
                                                    @if($role->users_count > 0)
                                                        {{-- BR-123: Disabled for roles with assigned users --}}
                                                        <span
                                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-outline/50 text-on-surface/30 cursor-not-allowed"
                                                            title="{{ __('Cannot delete: :count users assigned', ['count' => $role->users_count]) }}"
                                                        >
                                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                                            {{ __('Delete') }}
                                                        </span>
                                                    @else
                                                        {{-- BR-125: Opens confirmation modal --}}
                                                        <button
                                                            type="button"
                                                            @click.stop="$dispatch('open-delete-modal', { id: {{ $role->id }}, name: '{{ addslashes($role->name_en ?? $role->name) }}', permCount: {{ $role->permissions_count }}, userCount: {{ $role->users_count }} })"
                                                            x-navigate-skip
                                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-danger/30 text-danger hover:bg-danger-subtle transition-colors"
                                                            title="{{ __('Delete') }}"
                                                        >
                                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                                            {{ __('Delete') }}
                                                        </button>
                                                    @endif
                                                @endcan
                                            @else
                                                {{-- BR-122: System roles cannot be deleted --}}
                                                <span
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-outline/50 text-on-surface/30 cursor-not-allowed"
                                                    title="{{ __('System roles cannot be deleted') }}"
                                                >
                                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                                    {{ __('Delete') }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Mobile Card View --}}
            <div class="md:hidden space-y-3" x-navigate>
                @foreach($roles as $role)
                    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 hover:border-primary/30 transition-colors">
                        <a href="{{ url('/vault-entry/roles/' . $role->id . '/edit') }}" class="block">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
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
                                    <div class="min-w-0">
                                        <h3 class="text-sm font-semibold text-on-surface-strong truncate">{{ $role->name_en ?? $role->name }}</h3>
                                        <p class="text-xs text-on-surface/50 font-mono mt-0.5">{{ $role->name }}</p>
                                    </div>
                                </div>
                                @if($role->is_system)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border border-warning/30 text-warning bg-transparent shrink-0">
                                        {{ __('System') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-info-subtle text-info shrink-0">
                                        {{ __('Custom') }}
                                    </span>
                                @endif
                            </div>
                            @if($role->description)
                                <p class="text-xs text-on-surface/60 mt-2 line-clamp-2">{{ $role->description }}</p>
                            @endif
                            <div class="mt-3 flex items-center gap-4 text-xs text-on-surface/60">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"></rect><path d="m9 12 2 2 4-4"></path></svg>
                                    <span class="font-semibold text-on-surface-strong">{{ $role->permissions_count }}</span> {{ __('permissions') }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    <span class="font-semibold text-on-surface-strong">{{ $role->users_count }}</span> {{ __('users') }}
                                </span>
                            </div>
                        </a>
                        {{-- Mobile delete button for custom roles (F-055) --}}
                        @if(!$role->is_system)
                            @can('can-manage-roles')
                                <div class="mt-3 pt-3 border-t border-outline dark:border-outline">
                                    @if($role->users_count > 0)
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-outline/50 text-on-surface/30 cursor-not-allowed">
                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                            {{ __('Cannot delete: :count users assigned', ['count' => $role->users_count]) }}
                                        </span>
                                    @else
                                        <button
                                            type="button"
                                            @click.stop.prevent="$dispatch('open-delete-modal', { id: {{ $role->id }}, name: '{{ addslashes($role->name_en ?? $role->name) }}', permCount: {{ $role->permissions_count }}, userCount: {{ $role->users_count }} })"
                                            x-navigate-skip
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-danger/30 text-danger hover:bg-danger-subtle transition-colors"
                                        >
                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                            {{ __('Delete Role') }}
                                        </button>
                                    @endif
                                </div>
                            @endcan
                        @endif
                    </div>
                @endforeach
            </div>

        @elseif($type === 'custom')
            {{-- Empty state for custom filter --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-8 sm:p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-on-surface/30 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path></svg>
                <p class="text-on-surface font-medium">{{ __('No custom roles created yet') }}</p>
                <p class="text-sm text-on-surface/60 mt-1">{{ __('Create a custom role to define specialized access for your team.') }}</p>
                @can('can-manage-roles')
                    <a
                        href="{{ url('/vault-entry/roles/create') }}"
                        class="mt-4 inline-flex items-center gap-2 h-10 px-5 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200
                               focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-[0.98]"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                        {{ __('Create Role') }}
                    </a>
                @endcan
            </div>
        @else
            {{-- Empty state for no roles --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-8 sm:p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-on-surface/30 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path></svg>
                <p class="text-on-surface font-medium">{{ __('No roles found.') }}</p>
                <p class="text-sm text-on-surface/60 mt-1">{{ __('Run the permission seeder to create system roles.') }}</p>
            </div>
        @endif
    </div>
    @endfragment

    {{-- Delete Confirmation Modal (F-055 / BR-125) --}}
    <template x-teleport="body">
        <div
            x-show="deleteModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            @keydown.escape.window="cancelDelete()"
            x-cloak
        >
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/50 dark:bg-black/70" @click="cancelDelete()"></div>

            {{-- Modal Content --}}
            <div
                x-show="deleteModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-surface-alt dark:bg-surface-alt rounded-xl shadow-lg border border-outline w-full max-w-md p-6"
                @click.stop
            >
                {{-- Warning Icon --}}
                <div class="w-12 h-12 rounded-full bg-danger-subtle mx-auto flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                        <path d="M12 9v4"></path>
                        <path d="M12 17h.01"></path>
                    </svg>
                </div>

                {{-- Title --}}
                <h3 class="text-base font-semibold text-on-surface-strong text-center mb-2">
                    {{ __('Delete this role?') }}
                </h3>

                {{-- Description --}}
                <p class="text-sm text-on-surface text-center mb-1">
                    {{ __('This action cannot be undone. The role and all its permission assignments will be permanently removed.') }}
                </p>

                {{-- Role Details --}}
                <div class="my-4 bg-danger-subtle dark:bg-danger-subtle rounded-lg border border-danger/20 p-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-on-surface font-medium">{{ __('Role:') }}</span>
                        <span class="font-semibold text-danger" x-text="deleteRoleName"></span>
                    </div>
                    <div class="flex items-center justify-between text-sm mt-1">
                        <span class="text-on-surface font-medium">{{ __('Permissions to remove:') }}</span>
                        <span class="font-semibold text-on-surface-strong" x-text="deleteRolePermCount"></span>
                    </div>
                </div>

                {{-- Type to confirm (BR-125) --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Type the role name to confirm:') }}
                        <span class="font-semibold text-danger" x-text="deleteRoleName"></span>
                    </label>
                    <input
                        type="text"
                        x-model="confirmRoleName"
                        x-name="confirmRoleName"
                        @keydown.enter="confirmRoleName.trim() === deleteRoleName && executeDelete()"
                        placeholder=""
                        autocomplete="off"
                        class="w-full h-10 px-3 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong dark:text-on-surface-strong
                               focus:outline-none focus:ring-2 focus:ring-danger focus:border-danger transition-colors duration-200"
                    >
                    <p x-message="confirmRoleName" class="mt-1 text-sm text-danger"></p>
                </div>

                {{-- Error message --}}
                <template x-if="deleteError">
                    <p class="text-sm text-danger text-center mb-4" x-text="deleteError"></p>
                </template>

                {{-- Action Buttons --}}
                <div class="flex gap-3">
                    <button
                        type="button"
                        @click="cancelDelete()"
                        class="flex-1 h-10 px-4 rounded-lg text-sm font-medium border border-outline text-on-surface hover:bg-surface dark:hover:bg-surface transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-outline focus:ring-offset-2"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="button"
                        @click="executeDelete()"
                        :disabled="deleting || confirmRoleName.trim() !== deleteRoleName"
                        :class="(confirmRoleName.trim() !== deleteRoleName || deleting) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-danger/90 active:scale-[0.98]'"
                        class="flex-1 h-10 px-4 rounded-lg text-sm font-semibold bg-danger text-on-danger transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2"
                    >
                        <span x-show="!deleting">{{ __('Delete Role') }}</span>
                        <span x-show="deleting" x-cloak class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin-slow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ __('Deleting...') }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
