{{--
    Meal List View (Stub)
    ---------------------
    F-116: Meal List View (Cook Dashboard) — stub page.
    Full implementation in F-116.

    F-111: Added delete button with confirmation modal.
    F-112: Added inline status toggle button (Draft/Live).

    Provides basic listing, "Add Meal" button, delete action, and status toggle.
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Meals'))
@section('page-title', __('Meals'))

@section('content')
<div
    class="max-w-6xl mx-auto"
    x-data="{
        confirmDeleteId: null,
        confirmDeleteName: '',
        confirmDeleteOrders: 0,
        confirmDelete(id, name, orders) {
            this.confirmDeleteId = id;
            this.confirmDeleteName = name;
            this.confirmDeleteOrders = orders;
        },
        cancelDelete() {
            this.confirmDeleteId = null;
            this.confirmDeleteName = '';
            this.confirmDeleteOrders = 0;
        },
        executeDelete() {
            if (this.confirmDeleteId) {
                $action('/dashboard/meals/' + this.confirmDeleteId, { method: 'DELETE' });
                this.cancelDelete();
            }
        }
    }"
>
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-6" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ url('/dashboard') }}" class="hover:text-primary transition-colors duration-200">
            {{ __('Dashboard') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <span class="text-on-surface-strong font-medium">{{ __('Meals') }}</span>
    </nav>

    {{-- Page header with Add Meal button --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-display font-bold text-on-surface-strong">{{ __('Meals') }}</h2>
            <p class="mt-1 text-sm text-on-surface/70">{{ __('Manage your food menu.') }}</p>
        </div>
        <a
            href="{{ url('/dashboard/meals/create') }}"
            class="px-4 py-2.5 rounded-lg text-sm font-medium bg-primary text-on-primary hover:bg-primary-hover shadow-sm transition-colors duration-200 flex items-center gap-2"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
            {{ __('Add Meal') }}
        </a>
    </div>

    {{-- Toast notifications --}}
    @if(session('success'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="mb-6 p-4 rounded-lg bg-success-subtle border border-success/20 flex items-center gap-3"
        >
            <svg class="w-5 h-5 text-success shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <span class="text-sm text-on-surface">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 7000)"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="mb-6 p-4 rounded-lg bg-danger-subtle border border-danger/20 flex items-center gap-3"
        >
            <svg class="w-5 h-5 text-danger shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <span class="text-sm text-on-surface">{{ session('error') }}</span>
        </div>
    @endif

    {{-- Meal list --}}
    @if($meals->isEmpty())
        {{-- Empty state --}}
        <div class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-card p-12 text-center">
            <div class="w-16 h-16 rounded-full bg-primary-subtle flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 11h.01"></path><path d="M11 15h.01"></path><path d="M16 16h.01"></path><path d="m2 16 20 6-6-20A20 20 0 0 0 2 16"></path><path d="M5.71 17.11a17.04 17.04 0 0 1 11.4-11.4"></path></svg>
            </div>
            <h3 class="text-lg font-semibold text-on-surface-strong mb-2">{{ __('No meals yet') }}</h3>
            <p class="text-sm text-on-surface/70 mb-6">{{ __('Create your first meal to start building your menu.') }}</p>
            <a
                href="{{ url('/dashboard/meals/create') }}"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-medium bg-primary text-on-primary hover:bg-primary-hover shadow-sm transition-colors duration-200"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                {{ __('Add Meal') }}
            </a>
        </div>
    @else
        {{-- Meal cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($meals as $meal)
                <div class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-card p-4 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-2 min-w-0 pr-2">
                            <h3 class="text-base font-semibold text-on-surface-strong truncate">{{ $meal->name }}</h3>
                            @if($meal->has_custom_locations)
                                <span class="shrink-0 px-1.5 py-0.5 rounded text-[10px] font-medium bg-info-subtle text-info" title="{{ __('Custom locations') }}">
                                    <svg class="w-3 h-3 inline-block -mt-px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                </span>
                            @endif
                        </div>
                        <span class="shrink-0 px-2 py-0.5 rounded-full text-xs font-medium {{ $meal->status === 'draft' ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success' }}">
                            {{ $meal->status === 'draft' ? __('Draft') : __('Live') }}
                        </span>
                    </div>
                    @if($meal->description)
                        <p class="text-sm text-on-surface/70 line-clamp-2 mb-3">{{ $meal->description }}</p>
                    @endif
                    <div class="flex items-center justify-between pt-3 border-t border-outline dark:border-outline">
                        <span class="text-xs text-on-surface/50">{{ $meal->created_at->diffForHumans() }}</span>
                        <div class="flex items-center gap-3">
                            {{-- F-112: Inline status toggle --}}
                            @if($meal->isDraft())
                                <button
                                    type="button"
                                    @click="$action('{{ url('/dashboard/meals/' . $meal->id . '/toggle-status') }}', { method: 'PATCH' })"
                                    class="text-xs font-medium text-success hover:text-success/80 transition-colors duration-200"
                                    title="{{ __('Publish this meal') }}"
                                >
                                    {{ __('Go Live') }}
                                </button>
                            @else
                                <button
                                    type="button"
                                    @click="$action('{{ url('/dashboard/meals/' . $meal->id . '/toggle-status') }}', { method: 'PATCH' })"
                                    class="text-xs font-medium text-warning hover:text-warning/80 transition-colors duration-200"
                                    title="{{ __('Move to draft') }}"
                                >
                                    {{ __('Unpublish') }}
                                </button>
                            @endif

                            {{-- F-111: Delete button --}}
                            @php
                                $canDeleteInfo = app(\App\Services\MealService::class)->canDeleteMeal($meal);
                                $completedOrders = app(\App\Services\MealService::class)->getCompletedOrderCount($meal);
                            @endphp
                            @if($canDeleteInfo['can_delete'])
                                <button
                                    type="button"
                                    @click="confirmDelete({{ $meal->id }}, {{ json_encode($meal->name) }}, {{ $completedOrders }})"
                                    class="text-on-surface/40 hover:text-danger transition-colors duration-200"
                                    title="{{ __('Delete meal') }}"
                                >
                                    {{-- Lucide: trash-2 (sm=16) --}}
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                </button>
                            @else
                                <span
                                    class="text-on-surface/20 cursor-not-allowed"
                                    title="{{ $canDeleteInfo['reason'] ?? __('Cannot delete this meal') }}"
                                >
                                    {{-- Lucide: trash-2 (sm=16) --}}
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                </span>
                            @endif

                            <a
                                href="{{ url('/dashboard/meals/' . $meal->id . '/edit') }}"
                                class="text-sm text-primary hover:text-primary-hover font-medium transition-colors duration-200"
                            >
                                {{ __('Edit') }}
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- F-111: Delete Confirmation Modal --}}
    <div
        x-show="confirmDeleteId !== null"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        :aria-label="'{{ __('Delete meal') }}'"
    >
        {{-- Backdrop --}}
        <div
            x-show="confirmDeleteId !== null"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="cancelDelete()"
            class="absolute inset-0 bg-black/50"
        ></div>

        {{-- Modal content --}}
        <div
            x-show="confirmDeleteId !== null"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative w-full max-w-md bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-lg p-6"
        >
            {{-- Warning icon --}}
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-danger-subtle mx-auto mb-4">
                <svg class="w-6 h-6 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>

            <h3 class="text-lg font-semibold text-on-surface-strong text-center mb-2">
                {{ __('Delete Meal') }}
            </h3>

            <p class="text-sm text-on-surface/70 text-center mb-2">
                {{ __('Are you sure you want to delete') }}
                <span class="font-semibold text-on-surface-strong" x-text="confirmDeleteName"></span>?
            </p>

            <p class="text-sm text-on-surface/70 text-center mb-1">
                {{ __('This will remove it from your menu.') }}
            </p>

            {{-- Show completed order count if any --}}
            <template x-if="confirmDeleteOrders > 0">
                <p class="text-sm text-info text-center mb-4">
                    <span x-text="'{{ __('This meal has') }} ' + confirmDeleteOrders + ' {{ __('past orders. Order history will be preserved.') }}'"></span>
                </p>
            </template>

            <template x-if="confirmDeleteOrders === 0">
                <div class="mb-4"></div>
            </template>

            {{-- Action buttons --}}
            <div class="flex items-center justify-end gap-3">
                <button
                    type="button"
                    @click="cancelDelete()"
                    class="px-4 py-2 rounded-lg text-sm font-medium text-on-surface bg-surface dark:bg-surface border border-outline dark:border-outline hover:bg-surface-alt transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="button"
                    @click="executeDelete()"
                    class="px-4 py-2 rounded-lg text-sm font-medium bg-danger text-on-danger hover:bg-danger/90 shadow-sm transition-colors duration-200 flex items-center gap-2"
                >
                    {{-- Lucide: trash-2 (sm=16) --}}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    {{ __('Delete') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
