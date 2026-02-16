{{--
    Cook Assignment Page
    --------------------
    F-049: Cook Account Assignment to Tenant

    BR-082: Each tenant has exactly one cook at a time
    BR-083: A user can be a cook for multiple tenants
    BR-084: Reassignment revokes cook role from previous user (for this tenant only)
    BR-085: User must already exist in the system
    BR-086: Reassignment requires explicit confirmation dialog
    BR-087: Assignment logged in activity log
    BR-088: Assigned user gains all cook-level permissions
--}}
@extends('layouts.admin')

@section('title', __('Assign Cook') . ' — ' . $tenant->name)
@section('page-title', $tenant->cook ? __('Reassign Cook') : __('Assign Cook'))

@section('content')
<div
    x-data="{
        searchTerm: '',
        searchResults: [],
        searchLoading: false,
        selectedUserId: null,
        selectedUser: null,
        showConfirmModal: false,
        hasCook: {{ $tenant->cook ? 'true' : 'false' }},
        currentCookName: '{{ $tenant->cook ? addslashes($tenant->cook->name) : '' }}',

        performSearch() {
            if (this.searchTerm.length < 2) {
                this.searchResults = [];
                return;
            }
            this.searchLoading = true;
            $action('{{ url('/vault-entry/tenants/' . $tenant->slug . '/assign-cook/search') }}', {
                include: ['searchTerm']
            });
        },

        selectUser(user) {
            this.selectedUserId = user.id;
            this.selectedUser = user;

            if (this.hasCook && !user.is_current_cook) {
                this.showConfirmModal = true;
            } else if (!user.is_current_cook) {
                this.confirmAssign();
            }
        },

        confirmAssign() {
            this.showConfirmModal = false;
            $action('{{ url('/vault-entry/tenants/' . $tenant->slug . '/assign-cook') }}', {
                include: ['selectedUserId']
            });
        },

        cancelAssign() {
            this.showConfirmModal = false;
            this.selectedUserId = null;
            this.selectedUser = null;
        }
    }"
    x-sync="['searchTerm', 'selectedUserId']"
    class="space-y-6"
>
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Tenants'), 'url' => '/vault-entry/tenants'],
        ['label' => $tenant->name, 'url' => '/vault-entry/tenants/' . $tenant->slug],
        ['label' => $tenant->cook ? __('Reassign Cook') : __('Assign Cook')],
    ]" />

    {{-- Header --}}
    <div>
        <h2 class="text-xl sm:text-2xl font-bold text-on-surface-strong">
            {{ $tenant->cook ? __('Reassign Cook') : __('Assign Cook') }}
        </h2>
        <p class="text-sm text-on-surface mt-1">
            {{ __('Search for an existing user to assign as the cook for ":tenant".', ['tenant' => $tenant->name]) }}
        </p>
    </div>

    {{-- Current Cook Section (if reassigning) --}}
    @if($tenant->cook)
        <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
            <h3 class="text-sm font-semibold text-on-surface-strong mb-3">{{ __('Current Cook') }}</h3>
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-full {{ $tenant->cook->is_active ? 'bg-primary-subtle text-primary' : 'bg-outline/20 text-on-surface/60' }} flex items-center justify-center font-semibold text-sm shrink-0">
                    {{ mb_strtoupper(mb_substr($tenant->cook->name, 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-on-surface-strong">{{ $tenant->cook->name }}</p>
                    <p class="text-sm text-on-surface">{{ $tenant->cook->email }}</p>
                </div>
                @if(!$tenant->cook->is_active)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-danger-subtle text-danger">
                        {{ __('Deactivated') }}
                    </span>
                @endif
            </div>
        </div>
    @endif

    {{-- Search Section --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
        <h3 class="text-sm font-semibold text-on-surface-strong mb-3">{{ __('Search Users') }}</h3>
        <p class="text-xs text-on-surface/60 mb-4">{{ __('Type at least 2 characters to search by name or email.') }}</p>

        {{-- Search Input --}}
        <div class="relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <svg class="w-5 h-5 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            </div>
            <input
                type="text"
                x-model="searchTerm"
                @input.debounce.400ms="performSearch()"
                class="w-full h-11 pl-10 pr-4 text-sm rounded-lg border border-outline bg-surface text-on-surface-strong placeholder-on-surface/40
                       focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary
                       dark:bg-surface dark:border-outline dark:text-on-surface-strong"
                placeholder="{{ __('Search by name or email...') }}"
            >
            {{-- Loading spinner --}}
            <div
                x-show="searchLoading"
                x-cloak
                class="absolute inset-y-0 right-0 flex items-center pr-3"
            >
                <svg class="w-5 h-5 text-primary animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>
        </div>

        {{-- Search Results --}}
        <div x-show="searchTerm.length >= 2" x-cloak class="mt-4">
            {{-- Results list --}}
            <template x-if="searchResults.length > 0">
                <div class="space-y-2">
                    <p class="text-xs text-on-surface/60 font-medium mb-2">
                        <span x-text="searchResults.length"></span> {{ __('user(s) found') }}
                    </p>
                    <template x-for="user in searchResults" :key="user.id">
                        <button
                            @click="selectUser(user)"
                            :disabled="user.is_current_cook"
                            class="w-full text-left p-4 rounded-lg border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            :class="user.is_current_cook
                                ? 'border-primary/30 bg-primary-subtle/30 cursor-not-allowed'
                                : 'border-outline hover:border-primary hover:bg-surface dark:hover:bg-surface cursor-pointer'"
                        >
                            <div class="flex items-center gap-4">
                                {{-- User avatar --}}
                                <div
                                    class="w-10 h-10 rounded-full flex items-center justify-center font-semibold text-sm shrink-0"
                                    :class="user.is_active ? 'bg-primary-subtle text-primary' : 'bg-outline/20 text-on-surface/60'"
                                    x-text="user.initial"
                                ></div>

                                {{-- User info --}}
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-on-surface-strong" x-text="user.name"></span>
                                        {{-- Current cook badge --}}
                                        <template x-if="user.is_current_cook">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-primary-subtle text-primary">
                                                {{ __('Current Cook') }}
                                            </span>
                                        </template>
                                        {{-- Deactivated badge --}}
                                        <template x-if="!user.is_active">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-danger-subtle text-danger">
                                                {{ __('Deactivated') }}
                                            </span>
                                        </template>
                                    </div>
                                    <p class="text-sm text-on-surface mt-0.5" x-text="user.email"></p>
                                    {{-- Roles --}}
                                    <div class="flex flex-wrap gap-1 mt-1.5">
                                        <template x-for="role in user.roles" :key="role">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-surface dark:bg-surface border border-outline dark:border-outline text-on-surface" x-text="role"></span>
                                        </template>
                                    </div>
                                </div>

                                {{-- Action indicator --}}
                                <div class="shrink-0">
                                    <template x-if="user.is_current_cook">
                                        <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                    </template>
                                    <template x-if="!user.is_current_cook">
                                        <svg class="w-5 h-5 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                                    </template>
                                </div>
                            </div>

                            {{-- Cook elsewhere warning (Scenario 4) --}}
                            <template x-if="user.cook_tenants.length > 0 && !user.is_current_cook">
                                <div class="mt-3 p-2.5 bg-info-subtle rounded-lg border border-info/20">
                                    <p class="text-xs text-info font-medium">
                                        {{ __('This user is already a cook for:') }}
                                        <template x-for="(ct, idx) in user.cook_tenants" :key="ct.slug">
                                            <span>
                                                <span class="font-semibold" x-text="ct.name"></span><span x-show="idx < user.cook_tenants.length - 1">, </span>
                                            </span>
                                        </template>
                                    </p>
                                </div>
                            </template>

                            {{-- Deactivated user warning (Scenario 5) --}}
                            <template x-if="!user.is_active && !user.is_current_cook">
                                <div class="mt-3 p-2.5 bg-warning-subtle rounded-lg border border-warning/20">
                                    <p class="text-xs text-warning font-medium">{{ __('This user is currently deactivated. They will not be able to manage the tenant until their account is reactivated.') }}</p>
                                </div>
                            </template>
                        </button>
                    </template>
                </div>
            </template>

            {{-- No results (Scenario 3) --}}
            <template x-if="searchResults.length === 0 && !searchLoading && searchTerm.length >= 2">
                <div class="text-center py-8">
                    <div class="w-12 h-12 mx-auto rounded-full bg-outline/10 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="17" x2="22" y1="8" y2="13"></line><line x1="22" x2="17" y1="8" y2="13"></line></svg>
                    </div>
                    <p class="text-sm text-on-surface font-medium">{{ __('No users found.') }}</p>
                    <p class="text-xs text-on-surface/60 mt-1">{{ __('The user must have an account before being assigned as a cook.') }}</p>
                </div>
            </template>
        </div>
    </div>

    {{-- Back navigation --}}
    <div x-data x-navigate>
        <a
            href="{{ url('/vault-entry/tenants/' . $tenant->slug) }}"
            class="inline-flex items-center gap-2 text-sm font-medium text-on-surface hover:text-primary transition-colors"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
            {{ __('Back to Tenant Detail') }}
        </a>
    </div>

    {{-- Reassignment Confirmation Modal (BR-086) --}}
    <template x-teleport="body">
        <div
            x-show="showConfirmModal"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            {{-- Backdrop --}}
            <div class="fixed inset-0 bg-black/50 dark:bg-black/70" @click="cancelAssign()"></div>

            {{-- Modal content --}}
            <div
                class="relative w-full max-w-md bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-dropdown p-6 z-10"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                @click.stop
            >
                {{-- Warning icon --}}
                <div class="w-12 h-12 mx-auto rounded-full bg-warning-subtle flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" x2="12" y1="9" y2="13"></line><line x1="12" x2="12.01" y1="17" y2="17"></line></svg>
                </div>

                <h3 class="text-lg font-bold text-on-surface-strong text-center mb-2">{{ __('Confirm Cook Reassignment') }}</h3>

                <p class="text-sm text-on-surface text-center mb-6">
                    {{ __('This will remove') }}
                    <span class="font-semibold text-on-surface-strong" x-text="currentCookName"></span>
                    {{ __('as cook for this tenant and assign') }}
                    <span class="font-semibold text-on-surface-strong" x-text="selectedUser?.name"></span>.
                    {{ __('Continue?') }}
                </p>

                <div class="flex gap-3">
                    <button
                        @click="cancelAssign()"
                        class="flex-1 h-10 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface transition-all duration-200
                               focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        @click="confirmAssign()"
                        class="flex-1 h-10 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200
                               focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        <span x-show="!$fetching()">{{ __('Confirm Assignment') }}</span>
                        <span x-show="$fetching()" x-cloak class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ __('Assigning...') }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
