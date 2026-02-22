{{--
    Cook Promo Codes Page
    ---------------------
    F-215: Cook Promo Code Creation
    F-216: Cook Promo Code Edit
    F-217: Cook Promo Code Deactivation

    Allows cooks to create, edit, and toggle promo code status.

    Features:
    - Promo code list: code, type, value, status badge, toggle switch, uses/max, dates
    - Status filter tabs: All, Active, Inactive, Expired
    - Sort controls: by created date, usage count, end date
    - Toggle switch inline for active/inactive (disabled for expired)
    - Checkbox column for bulk selection
    - Bulk action bar: "Deactivate Selected (X)" appears when items are selected
    - "Create Promo Code" button opens create modal form
    - "Edit" button on each row opens edit modal pre-populated
    - Status badges: green=Active, grey=Inactive, red=Expired
    - Expired codes shown with dimmed row, disabled toggle
    - Usage count display: "47/100" or "47/unlimited"
    - After toggle/bulk: list updates via Gale fragment, toast shown
    - Mobile-first responsive layout (table on desktop, card list on mobile)
    - Light/dark mode support
    - All text localized with __()

    BR-560: Status: active, inactive (manual), expired (computed from end date).
    BR-561: Deactivated code cannot be applied at checkout.
    BR-562: Deactivated code can be reactivated.
    BR-563: Expired code cannot be reactivated via toggle; end date must be extended.
    BR-564: List shows code, discount type/value, status, usage count, start date, end date.
    BR-565: Bulk deactivation: cook selects multiple codes and deactivates them in one action.
    BR-566: Bulk reactivation not supported.
    BR-567: Expired status is computed: current date > end date.
    BR-568: Only the cook can toggle promo code status.
    BR-569: All status changes logged via Spatie Activitylog.
    BR-570: All user-facing text uses __() localization.
    BR-571: Gale handles all toggle and bulk actions without page reloads.
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Promo Codes'))
@section('page-title', __('Promo Codes'))

@section('content')
<div
    class="max-w-5xl mx-auto"
    x-data="{
        showCreateModal: false,
        code: '',
        discount_type: 'percentage',
        discount_value: '',
        minimum_order_amount: 0,
        max_uses: 0,
        max_uses_per_client: 0,
        starts_at: '{{ now()->toDateString() }}',
        ends_at: '',
        no_end_date: true,

        showEditModal: false,
        editId: 0,
        editCode: '',
        editDiscountType: 'percentage',
        editDiscountValue: '',
        editMinimumOrderAmount: 0,
        editMaxUses: 0,
        editMaxUsesPerClient: 0,
        editStartsAt: '{{ now()->toDateString() }}',
        editEndsAt: '',
        editNoEndDate: true,
        editUsageCount: 0,
        editTimesUsed: 0,

        // F-217: Bulk deactivation state
        selectedIds: [],
        selectAll: false,

        openModal() {
            this.showCreateModal = true;
            this.$nextTick(() => {
                const el = document.getElementById('promo-code-input');
                if (el) { el.focus(); }
            });
        },
        closeModal() {
            this.showCreateModal = false;
            this.code = '';
            this.discount_type = 'percentage';
            this.discount_value = '';
            this.minimum_order_amount = 0;
            this.max_uses = 0;
            this.max_uses_per_client = 0;
            this.starts_at = '{{ now()->toDateString() }}';
            this.ends_at = '';
            this.no_end_date = true;
        },
        closeEditModal() {
            this.showEditModal = false;
            this.editId = 0;
            this.editCode = '';
            this.editDiscountType = 'percentage';
            this.editDiscountValue = '';
            this.editMinimumOrderAmount = 0;
            this.editMaxUses = 0;
            this.editMaxUsesPerClient = 0;
            this.editStartsAt = '{{ now()->toDateString() }}';
            this.editEndsAt = '';
            this.editNoEndDate = true;
            this.editUsageCount = 0;
            this.editTimesUsed = 0;
        },
        openEditModal(promoCodeId) {
            $action('/dashboard/promo-codes/' + promoCodeId + '/edit', {
                include: []
            });
        },
        getEditDiscountUnit() {
            return this.editDiscountType === 'percentage' ? '%' : 'XAF';
        },
        getEditDiscountMax() {
            return this.editDiscountType === 'percentage' ? 100 : 100000;
        },
        isEditExhausted() {
            return this.editMaxUses > 0 && this.editTimesUsed > 0 && this.editMaxUses < this.editTimesUsed;
        },
        getDiscountUnit() {
            return this.discount_type === 'percentage' ? '%' : 'XAF';
        },
        getDiscountMax() {
            return this.discount_type === 'percentage' ? 100 : 100000;
        },
        // F-217: Toggle an individual promo code status
        toggleStatus(promoCodeId, page, status, sortBy, sortDir) {
            $action('/dashboard/promo-codes/' + promoCodeId + '/toggle-status', {
                include: [],
                params: { page: page, status: status, sort_by: sortBy, sort_dir: sortDir }
            });
        },
        // F-217: Bulk deactivate selected codes
        bulkDeactivate(page, status, sortBy, sortDir) {
            if (this.selectedIds.length === 0) { return; }
            $action('/dashboard/promo-codes/bulk-deactivate', {
                include: ['selectedIds'],
                params: { page: page, status: status, sort_by: sortBy, sort_dir: sortDir }
            });
        },
        // F-217: Toggle all checkboxes
        toggleSelectAll(allIds) {
            if (this.selectAll) {
                this.selectedIds = [...allIds];
            } else {
                this.selectedIds = [];
            }
        },
        // F-217: Check if a specific id is selected
        isSelected(id) {
            return this.selectedIds.includes(id);
        },
        // F-217: Toggle selection of a single id
        toggleSelection(id) {
            const idx = this.selectedIds.indexOf(id);
            if (idx === -1) {
                this.selectedIds.push(id);
            } else {
                this.selectedIds.splice(idx, 1);
            }
        }
    }"
    x-sync
>

    {{-- Page Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-display font-bold text-on-surface-strong">
                {{ __('Promo Codes') }}
            </h1>
            <p class="mt-1 text-sm text-on-surface">
                {{ __('Create and manage discount codes for your customers.') }}
            </p>
        </div>
        <button
            @click="openModal()"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:bg-primary-hover transition-colors shrink-0"
        >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 12h14"></path><path d="M12 5v14"></path>
            </svg>
            {{ __('Create Promo Code') }}
        </button>
    </div>

    {{-- Promo Code List (Gale fragment target) --}}
    @fragment('promo-list')
    <div id="promo-list">

        {{-- Filter & Sort Bar --}}
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center gap-3">
            {{-- Status Filter Tabs --}}
            <div class="flex items-center gap-1 bg-surface-alt border border-outline rounded-lg p-1 overflow-x-auto shrink-0">
                @foreach ([
                    'all' => __('All'),
                    'active' => __('Active'),
                    'inactive' => __('Inactive'),
                    'expired' => __('Expired'),
                ] as $filterValue => $filterLabel)
                <a
                    href="{{ route('cook.promo-codes.index', array_merge(request()->query(), ['status' => $filterValue, 'page' => 1])) }}"
                    x-navigate.key.promo-list
                    class="px-3 py-1.5 text-xs font-medium rounded-md whitespace-nowrap transition-colors {{ $statusFilter === $filterValue ? 'bg-primary text-on-primary' : 'text-on-surface hover:bg-surface' }}"
                >
                    {{ $filterLabel }}
                </a>
                @endforeach
            </div>

            {{-- Sort Controls --}}
            <div class="flex items-center gap-2 ml-auto shrink-0">
                <span class="text-xs text-on-surface opacity-70 hidden sm:inline">{{ __('Sort by') }}:</span>
                <div class="flex items-center gap-1">
                    @foreach ([
                        'created_at' => __('Date'),
                        'times_used' => __('Usage'),
                        'ends_at' => __('Expiry'),
                    ] as $sortField => $sortLabel)
                    <a
                        href="{{ route('cook.promo-codes.index', array_merge(request()->query(), [
                            'sort_by' => $sortField,
                            'sort_dir' => ($sortBy === $sortField && $sortDir === 'desc') ? 'asc' : 'desc',
                            'page' => 1,
                        ])) }}"
                        x-navigate.key.promo-list
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg border transition-colors {{ $sortBy === $sortField ? 'border-primary bg-primary-subtle text-primary' : 'border-outline text-on-surface hover:bg-surface-alt' }}"
                    >
                        {{ $sortLabel }}
                        @if ($sortBy === $sortField)
                            @if ($sortDir === 'asc')
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg>
                            @else
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            @endif
                        @endif
                    </a>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($promoCodes->isEmpty())
            {{-- Empty State --}}
            <div class="bg-surface-alt border border-outline rounded-xl p-10 text-center">
                <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-primary-subtle flex items-center justify-center">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 5H2v7l6.29 6.29c.94.94 2.48.94 3.42 0l3.58-3.58c.94-.94.94-2.48 0-3.42L9 5Z"></path>
                        <path d="M6 9.01V9"></path>
                    </svg>
                </div>
                @if ($statusFilter !== 'all')
                    <h3 class="text-base font-semibold text-on-surface-strong mb-1">{{ __('No promo codes found') }}</h3>
                    <p class="text-sm text-on-surface mb-5">{{ __('No promo codes match the selected filter.') }}</p>
                    <a
                        href="{{ route('cook.promo-codes.index') }}"
                        x-navigate.key.promo-list
                        class="inline-flex items-center gap-2 px-4 py-2 bg-surface text-on-surface text-sm font-medium rounded-lg border border-outline hover:bg-surface-alt transition-colors"
                    >
                        {{ __('Show all') }}
                    </a>
                @else
                    <h3 class="text-base font-semibold text-on-surface-strong mb-1">{{ __('No promo codes yet') }}</h3>
                    <p class="text-sm text-on-surface mb-5">{{ __('Create your first promo code to offer discounts to your customers.') }}</p>
                    <button
                        @click="openModal()"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:bg-primary-hover transition-colors"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12h14"></path><path d="M12 5v14"></path>
                        </svg>
                        {{ __('Create Promo Code') }}
                    </button>
                @endif
            </div>
        @else
            {{-- Collect IDs for selectAll (non-expired, selectable codes) --}}
            @php
                $today = now()->toDateString();
                $selectableIds = $promoCodes->filter(function ($pc) use ($today) {
                    return !($pc->ends_at !== null && $pc->ends_at->toDateString() < $today);
                })->pluck('id')->values()->toArray();
            @endphp

            {{-- Bulk Action Bar (appears when items are selected) --}}
            <div
                x-show="selectedIds.length > 0"
                x-cloak
                class="mb-3 flex items-center justify-between gap-3 px-4 py-2.5 bg-primary-subtle border border-primary/30 rounded-xl"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
            >
                <span class="text-sm font-medium text-primary">
                    <span x-text="selectedIds.length"></span>
                    {{ __('selected') }}
                </span>
                <button
                    @click="bulkDeactivate({{ $promoCodes->currentPage() }}, @js($statusFilter), @js($sortBy), @js($sortDir))"
                    class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-danger text-on-danger text-xs font-semibold rounded-lg hover:opacity-90 transition-opacity disabled:opacity-60"
                    :disabled="$fetching()"
                >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
                    </svg>
                    <span x-show="!$fetching()">{{ __('Deactivate Selected') }} (<span x-text="selectedIds.length"></span>)</span>
                    <span x-show="$fetching()">{{ __('Deactivating...') }}</span>
                </button>
            </div>

            {{-- Desktop Table --}}
            <div class="hidden md:block bg-surface-alt border border-outline rounded-xl overflow-hidden shadow-card">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-outline bg-surface">
                            {{-- Select all checkbox --}}
                            <th class="w-10 px-3 py-3 text-left">
                                <input
                                    type="checkbox"
                                    x-model="selectAll"
                                    @change="toggleSelectAll({{ json_encode($selectableIds) }})"
                                    class="w-3.5 h-3.5 rounded border-outline accent-primary cursor-pointer"
                                    :title="@js(__('Select all'))"
                                    :disabled="{{ count($selectableIds) === 0 ? 'true' : 'false' }}"
                                >
                            </th>
                            <th class="px-4 py-3 text-left font-semibold text-on-surface-strong">{{ __('Code') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-on-surface-strong">{{ __('Discount') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-on-surface-strong hidden lg:table-cell">{{ __('Min. Order') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-on-surface-strong">{{ __('Uses') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-on-surface-strong hidden lg:table-cell">{{ __('Dates') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-on-surface-strong">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-on-surface-strong">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline">
                        @foreach ($promoCodes as $promoCode)
                        @php
                            $isExpired = $promoCode->ends_at !== null && $promoCode->ends_at->toDateString() < $today;
                        @endphp
                        <tr class="hover:bg-surface transition-colors {{ $isExpired ? 'opacity-60' : '' }}">
                            {{-- Checkbox --}}
                            <td class="w-10 px-3 py-3">
                                @if (!$isExpired)
                                <input
                                    type="checkbox"
                                    :checked="isSelected({{ $promoCode->id }})"
                                    @change="toggleSelection({{ $promoCode->id }})"
                                    class="w-3.5 h-3.5 rounded border-outline accent-primary cursor-pointer"
                                    :aria-label="@js(__('Select :code', ['code' => $promoCode->code]))"
                                >
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-mono font-semibold text-on-surface-strong tracking-wide {{ $isExpired ? 'line-through' : '' }}">{{ $promoCode->code }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-semibold text-primary">{{ $promoCode->discount_label }}</span>
                                    <span class="text-xs text-on-surface">{{ __('off') }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell text-on-surface">
                                @if ($promoCode->minimum_order_amount > 0)
                                    {{ number_format($promoCode->minimum_order_amount) }} XAF
                                @else
                                    <span class="text-on-surface opacity-50">{{ __('None') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-on-surface">
                                <span class="font-mono">{{ $promoCode->times_used }}</span>
                                <span class="text-on-surface opacity-50">/</span>
                                <span class="font-mono">{{ $promoCode->max_uses_label }}</span>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell text-xs text-on-surface">
                                <div>{{ __('From') }}: {{ $promoCode->starts_at->format('M j, Y') }}</div>
                                @if ($promoCode->ends_at)
                                    <div class="{{ $isExpired ? 'text-danger font-medium' : '' }}">{{ __('To') }}: {{ $promoCode->ends_at->format('M j, Y') }}</div>
                                @else
                                    <div class="opacity-50">{{ __('No expiry') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($isExpired)
                                    {{-- BR-567: Expired = computed status --}}
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-danger-subtle text-danger">
                                        <span class="w-1.5 h-1.5 rounded-full bg-danger"></span>
                                        {{ __('Expired') }}
                                    </span>
                                @elseif ($promoCode->status === 'active')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-success-subtle text-success">
                                        <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                        {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-surface text-on-surface border border-outline">
                                        <span class="w-1.5 h-1.5 rounded-full bg-on-surface opacity-40"></span>
                                        {{ __('Inactive') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    {{-- F-217: Toggle switch --}}
                                    <button
                                        @click="{{ !$isExpired ? "toggleStatus({$promoCode->id}, {$promoCodes->currentPage()}, @js(\$statusFilter), @js(\$sortBy), @js(\$sortDir))" : '' }}"
                                        class="relative inline-flex items-center w-9 h-5 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary/40 {{ $isExpired ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer' }} {{ (!$isExpired && $promoCode->status === 'active') ? 'bg-success' : ($isExpired ? 'bg-on-surface/20' : 'bg-on-surface/20') }}"
                                        :disabled="{{ $isExpired ? 'true' : 'false' }}"
                                        :aria-label="@js($isExpired ? __('Toggle disabled — code is expired') : ($promoCode->status === 'active' ? __('Deactivate :code', ['code' => $promoCode->code]) : __('Activate :code', ['code' => $promoCode->code])))"
                                        role="switch"
                                        aria-checked="{{ !$isExpired && $promoCode->status === 'active' ? 'true' : 'false' }}"
                                        title="{{ $isExpired ? __('Expired codes cannot be toggled') : ($promoCode->status === 'active' ? __('Deactivate') : __('Activate')) }}"
                                    >
                                        <span class="absolute left-0.5 w-4 h-4 bg-white dark:bg-surface rounded-full shadow-sm transition-transform {{ (!$isExpired && $promoCode->status === 'active') ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                    </button>

                                    {{-- F-216: Edit button --}}
                                    <button
                                        @click="openEditModal({{ $promoCode->id }})"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-primary bg-primary-subtle hover:bg-primary/20 rounded-lg transition-colors"
                                        :aria-label="'{{ __('Edit') }}'"
                                    >
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
                                            <path d="m15 5 4 4"></path>
                                        </svg>
                                        {{ __('Edit') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards --}}
            <div class="md:hidden space-y-3">
                @foreach ($promoCodes as $promoCode)
                @php
                    $isExpired = $promoCode->ends_at !== null && $promoCode->ends_at->toDateString() < $today;
                @endphp
                <div class="bg-surface-alt border border-outline rounded-xl p-4 shadow-card {{ $isExpired ? 'opacity-70' : '' }}">
                    {{-- Card header: checkbox, code, status, toggle, edit --}}
                    <div class="flex items-start gap-2 mb-3">
                        {{-- Checkbox --}}
                        @if (!$isExpired)
                        <div class="pt-0.5">
                            <input
                                type="checkbox"
                                :checked="isSelected({{ $promoCode->id }})"
                                @change="toggleSelection({{ $promoCode->id }})"
                                class="w-3.5 h-3.5 rounded border-outline accent-primary cursor-pointer"
                                :aria-label="@js(__('Select :code', ['code' => $promoCode->code]))"
                            >
                        </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <span class="font-mono font-bold text-base text-on-surface-strong tracking-wide {{ $isExpired ? 'line-through' : '' }}">{{ $promoCode->code }}</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            {{-- Status badge --}}
                            @if ($isExpired)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-danger-subtle text-danger">
                                    <span class="w-1.5 h-1.5 rounded-full bg-danger"></span>
                                    {{ __('Expired') }}
                                </span>
                            @elseif ($promoCode->status === 'active')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-success-subtle text-success">
                                    <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                    {{ __('Active') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-surface text-on-surface border border-outline">
                                    <span class="w-1.5 h-1.5 rounded-full bg-on-surface opacity-40"></span>
                                    {{ __('Inactive') }}
                                </span>
                            @endif

                            {{-- F-217: Toggle switch (mobile) --}}
                            <button
                                @click="{{ !$isExpired ? "toggleStatus({$promoCode->id}, {$promoCodes->currentPage()}, @js(\$statusFilter), @js(\$sortBy), @js(\$sortDir))" : '' }}"
                                class="relative inline-flex items-center w-9 h-5 rounded-full transition-colors focus:outline-none {{ $isExpired ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer' }} {{ (!$isExpired && $promoCode->status === 'active') ? 'bg-success' : 'bg-on-surface/20' }}"
                                :disabled="{{ $isExpired ? 'true' : 'false' }}"
                                role="switch"
                                aria-checked="{{ !$isExpired && $promoCode->status === 'active' ? 'true' : 'false' }}"
                                :aria-label="@js($isExpired ? __('Toggle disabled — code is expired') : ($promoCode->status === 'active' ? __('Deactivate :code', ['code' => $promoCode->code]) : __('Activate :code', ['code' => $promoCode->code])))"
                            >
                                <span class="absolute left-0.5 w-4 h-4 bg-white dark:bg-surface rounded-full shadow-sm transition-transform {{ (!$isExpired && $promoCode->status === 'active') ? 'translate-x-4' : 'translate-x-0' }}"></span>
                            </button>

                            {{-- F-216: Edit button --}}
                            <button
                                @click="openEditModal({{ $promoCode->id }})"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-primary bg-primary-subtle hover:bg-primary/20 rounded-lg transition-colors"
                                :aria-label="'{{ __('Edit') }}'"
                            >
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
                                    <path d="m15 5 4 4"></path>
                                </svg>
                                {{ __('Edit') }}
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div>
                            <span class="text-xs text-on-surface opacity-70 uppercase tracking-wide">{{ __('Discount') }}</span>
                            <p class="font-semibold text-primary">{{ $promoCode->discount_label }} {{ __('off') }}</p>
                        </div>
                        <div>
                            <span class="text-xs text-on-surface opacity-70 uppercase tracking-wide">{{ __('Uses') }}</span>
                            <p class="font-mono text-on-surface-strong">{{ $promoCode->times_used }} / {{ $promoCode->max_uses_label }}</p>
                        </div>
                        <div>
                            <span class="text-xs text-on-surface opacity-70 uppercase tracking-wide">{{ __('Min. Order') }}</span>
                            <p class="text-on-surface">
                                @if ($promoCode->minimum_order_amount > 0)
                                    {{ number_format($promoCode->minimum_order_amount) }} XAF
                                @else
                                    <span class="opacity-50">{{ __('None') }}</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <span class="text-xs text-on-surface opacity-70 uppercase tracking-wide">{{ __('Valid from') }}</span>
                            <p class="text-on-surface">{{ $promoCode->starts_at->format('M j, Y') }}</p>
                        </div>
                        @if ($promoCode->ends_at)
                        <div>
                            <span class="text-xs text-on-surface opacity-70 uppercase tracking-wide">{{ __('Expires') }}</span>
                            <p class="{{ $isExpired ? 'text-danger font-medium' : 'text-on-surface' }}">{{ $promoCode->ends_at->format('M j, Y') }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if ($promoCodes->hasPages())
            <div class="mt-4">
                {{ $promoCodes->links() }}
            </div>
            @endif
        @endif
    </div>
    @endfragment

    {{-- F-216: Edit Promo Code Modal --}}
    <div
        x-show="showEditModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
        role="dialog"
        aria-modal="true"
        x-on:keydown.escape.window="closeEditModal()"
    >
        {{-- Backdrop --}}
        <div
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
            @click="closeEditModal()"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            aria-hidden="true"
        ></div>

        {{-- Modal Panel --}}
        <div
            class="relative w-full sm:max-w-lg bg-surface dark:bg-surface border border-outline dark:border-outline rounded-t-2xl sm:rounded-2xl shadow-dropdown max-h-[90vh] overflow-y-auto"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            {{-- Modal Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-outline dark:border-outline sticky top-0 bg-surface dark:bg-surface z-10">
                <h2 class="text-base font-semibold text-on-surface-strong">{{ __('Edit Promo Code') }}</h2>
                <button
                    @click="closeEditModal()"
                    class="p-1.5 rounded-lg text-on-surface hover:bg-surface-alt transition-colors"
                    :aria-label="'{{ __('Close') }}'"
                >
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
                    </svg>
                </button>
            </div>

            {{-- Modal Form --}}
            <form
                @submit.prevent="if (editNoEndDate) { editEndsAt = '' } $action('/dashboard/promo-codes/' + editId, { method: 'PUT', include: ['editDiscountValue','editMinimumOrderAmount','editMaxUses','editMaxUsesPerClient','editStartsAt','editEndsAt'] })"
                class="p-5 space-y-4"
            >
                {{-- BR-552: Usage count warning for used codes --}}
                <div x-show="editTimesUsed > 0" class="flex items-center gap-2.5 px-3.5 py-2.5 bg-warning-subtle border border-warning/30 rounded-lg">
                    <svg class="w-4 h-4 text-warning shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path>
                        <path d="M12 9v4"></path><path d="M12 17h.01"></path>
                    </svg>
                    <span class="text-sm text-warning font-medium">
                        {{ __('This code has been redeemed') }} <span x-text="editTimesUsed"></span> <span x-text="editTimesUsed === 1 ? @js(__('time')) : @js(__('times'))"></span>{{ __('. Changes apply to future uses only.') }}
                    </span>
                </div>

                {{-- BR-555: Exhaustion warning when max_uses < times_used --}}
                <div x-show="isEditExhausted()" class="flex items-center gap-2.5 px-3.5 py-2.5 bg-danger-subtle border border-danger/30 rounded-lg">
                    <svg class="w-4 h-4 text-danger shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="m15 9-6 6"></path><path d="m9 9 6 6"></path>
                    </svg>
                    <span class="text-sm text-danger font-medium">
                        {{ __('This code has already been used') }} <span x-text="editTimesUsed"></span> {{ __('times and will be immediately exhausted.') }}
                    </span>
                </div>

                {{-- BR-549: Code string read-only with lock icon --}}
                <div>
                    <label class="block text-sm font-medium text-on-surface-strong dark:text-on-surface-strong mb-1.5">
                        {{ __('Promo Code') }}
                    </label>
                    <div class="relative">
                        <div class="absolute left-3.5 inset-y-0 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-on-surface dark:text-on-surface opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </div>
                        <input
                            type="text"
                            :value="editCode"
                            readonly
                            tabindex="-1"
                            class="w-full pl-10 pr-3.5 py-2.5 bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-lg text-on-surface dark:text-on-surface font-mono text-sm cursor-not-allowed opacity-70"
                        >
                    </div>
                    <p class="mt-1 text-xs text-on-surface dark:text-on-surface opacity-60">{{ __('The code cannot be changed after creation.') }}</p>
                </div>

                {{-- BR-551: Discount type read-only label --}}
                <div>
                    <span class="block text-sm font-medium text-on-surface-strong mb-1.5">{{ __('Discount Type') }}</span>
                    <div class="flex items-center gap-2 px-3.5 py-2.5 bg-surface-alt border border-outline rounded-lg">
                        <span
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-on-surface-strong"
                            x-show="editDiscountType === 'percentage'"
                        >
                            <svg class="w-4 h-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="19" x2="5" y1="5" y2="19"></line>
                                <circle cx="6.5" cy="6.5" r="2.5"></circle>
                                <circle cx="17.5" cy="17.5" r="2.5"></circle>
                            </svg>
                            {{ __('Percentage') }}
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-on-surface-strong"
                            x-show="editDiscountType === 'fixed'"
                        >
                            <svg class="w-4 h-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="12" x2="12" y1="2" y2="22"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                            {{ __('Fixed Amount') }}
                        </span>
                        <svg class="w-4 h-4 text-on-surface opacity-40 ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <p class="mt-1 text-xs text-on-surface opacity-60">{{ __('The discount type cannot be changed after creation.') }}</p>
                </div>

                {{-- BR-550: Editable - Discount Value --}}
                <div>
                    <label for="edit-discount-value-input" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                        {{ __('Discount Value') }}
                        <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="edit-discount-value-input"
                            type="number"
                            x-model.number="editDiscountValue"
                            x-name="editDiscountValue"
                            :min="1"
                            :max="getEditDiscountMax()"
                            step="1"
                            class="w-full px-3.5 py-2.5 pr-14 bg-surface dark:bg-surface border border-outline dark:border-outline rounded-lg text-on-surface-strong dark:text-on-surface-strong placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition-colors"
                            :placeholder="editDiscountType === 'percentage' ? '10' : '500'"
                        >
                        <div class="absolute right-0 inset-y-0 flex items-center px-3 pointer-events-none">
                            <span class="text-sm font-semibold text-on-surface" x-text="getEditDiscountUnit()"></span>
                        </div>
                    </div>
                    <p x-message="editDiscountValue" class="mt-1 text-xs text-danger" role="alert"></p>
                    <p class="mt-1 text-xs text-on-surface opacity-60" x-show="editDiscountType === 'percentage'">{{ __('Enter a value between 1 and 100.') }}</p>
                    <p class="mt-1 text-xs text-on-surface opacity-60" x-show="editDiscountType === 'fixed'">{{ __('Enter a value between 1 and 100,000 XAF.') }}</p>
                </div>

                {{-- BR-550: Editable - Minimum Order Amount --}}
                <div>
                    <label for="edit-min-order-input" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                        {{ __('Minimum Order Amount') }}
                    </label>
                    <div class="relative">
                        <input
                            id="edit-min-order-input"
                            type="number"
                            x-model.number="editMinimumOrderAmount"
                            x-name="editMinimumOrderAmount"
                            min="0"
                            max="100000"
                            step="1"
                            placeholder="0"
                            class="w-full px-3.5 py-2.5 pr-14 bg-surface border border-outline rounded-lg text-on-surface-strong placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition-colors"
                        >
                        <div class="absolute right-0 inset-y-0 flex items-center px-3 pointer-events-none">
                            <span class="text-sm font-semibold text-on-surface">XAF</span>
                        </div>
                    </div>
                    <p x-message="editMinimumOrderAmount" class="mt-1 text-xs text-danger" role="alert"></p>
                    <p class="mt-1 text-xs text-on-surface opacity-60">{{ __('Set to 0 for no minimum required.') }}</p>
                </div>

                {{-- BR-550: Editable - Max Uses & Max Per Client (2-column grid) --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="edit-max-uses-input" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                            {{ __('Max Total Uses') }}
                        </label>
                        <input
                            id="edit-max-uses-input"
                            type="number"
                            x-model.number="editMaxUses"
                            x-name="editMaxUses"
                            min="0"
                            max="100000"
                            step="1"
                            placeholder="0"
                            class="w-full px-3.5 py-2.5 bg-surface border border-outline rounded-lg text-on-surface-strong placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition-colors"
                        >
                        <p x-message="editMaxUses" class="mt-1 text-xs text-danger" role="alert"></p>
                        <p class="mt-1 text-xs text-on-surface opacity-60">{{ __('0 = unlimited') }}</p>
                    </div>
                    <div>
                        <label for="edit-max-per-client-input" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                            {{ __('Max Per Client') }}
                        </label>
                        <input
                            id="edit-max-per-client-input"
                            type="number"
                            x-model.number="editMaxUsesPerClient"
                            x-name="editMaxUsesPerClient"
                            min="0"
                            max="100"
                            step="1"
                            placeholder="0"
                            class="w-full px-3.5 py-2.5 bg-surface border border-outline rounded-lg text-on-surface-strong placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition-colors"
                        >
                        <p x-message="editMaxUsesPerClient" class="mt-1 text-xs text-danger" role="alert"></p>
                        <p class="mt-1 text-xs text-on-surface opacity-60">{{ __('0 = unlimited') }}</p>
                    </div>
                </div>

                {{-- BR-550: Editable - Start Date --}}
                <div>
                    <label for="edit-starts-at-input" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                        {{ __('Start Date') }}
                        <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="edit-starts-at-input"
                        type="date"
                        x-model="editStartsAt"
                        x-name="editStartsAt"
                        class="w-full px-3.5 py-2.5 bg-surface border border-outline rounded-lg text-on-surface-strong focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition-colors"
                    >
                    <p x-message="editStartsAt" class="mt-1 text-xs text-danger" role="alert"></p>
                </div>

                {{-- BR-550: Editable - End Date --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="edit-ends-at-input" class="block text-sm font-medium text-on-surface-strong">
                            {{ __('End Date') }}
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                x-model="editNoEndDate"
                                class="w-3.5 h-3.5 rounded border-outline accent-primary cursor-pointer"
                            >
                            <span class="text-xs text-on-surface">{{ __('No end date') }}</span>
                        </label>
                    </div>
                    <input
                        id="edit-ends-at-input"
                        type="date"
                        x-model="editEndsAt"
                        x-name="editEndsAt"
                        :min="editStartsAt || '{{ now()->toDateString() }}'"
                        :disabled="editNoEndDate"
                        :class="editNoEndDate ? 'opacity-40 cursor-not-allowed' : ''"
                        class="w-full px-3.5 py-2.5 bg-surface border border-outline rounded-lg text-on-surface-strong focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                    <p x-message="editEndsAt" class="mt-1 text-xs text-danger" role="alert"></p>
                    <p class="mt-1 text-xs text-on-surface opacity-60" x-show="!editNoEndDate">{{ __('Must be on or after the start date.') }}</p>
                </div>

                {{-- Form Actions --}}
                <div class="flex items-center gap-3 pt-2 border-t border-outline">
                    <button
                        type="submit"
                        class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:bg-primary-hover transition-colors disabled:opacity-60"
                        :disabled="$fetching()"
                    >
                        <span x-show="!$fetching()">{{ __('Save Changes') }}</span>
                        <span x-show="$fetching()" class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
                            </svg>
                            {{ __('Saving...') }}
                        </span>
                    </button>
                    <button
                        type="button"
                        @click="closeEditModal()"
                        class="px-4 py-2.5 text-on-surface text-sm font-medium rounded-lg border border-outline hover:bg-surface-alt transition-colors"
                    >
                        {{ __('Cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Create Promo Code Modal --}}
    <div
        x-show="showCreateModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
        role="dialog"
        aria-modal="true"
        x-on:keydown.escape.window="closeModal()"
    >
        {{-- Backdrop --}}
        <div
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
            @click="closeModal()"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            aria-hidden="true"
        ></div>

        {{-- Modal Panel --}}
        <div
            class="relative w-full sm:max-w-lg bg-surface dark:bg-surface border border-outline dark:border-outline rounded-t-2xl sm:rounded-2xl shadow-dropdown max-h-[90vh] overflow-y-auto"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            {{-- Modal Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-outline dark:border-outline sticky top-0 bg-surface dark:bg-surface z-10">
                <h2 class="text-base font-semibold text-on-surface-strong">{{ __('Create Promo Code') }}</h2>
                <button
                    @click="closeModal()"
                    class="p-1.5 rounded-lg text-on-surface hover:bg-surface-alt transition-colors"
                    :aria-label="'{{ __('Close') }}'"
                >
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
                    </svg>
                </button>
            </div>

            {{-- Modal Form --}}
            <form
                @submit.prevent="if (no_end_date) { ends_at = '' } $action('/dashboard/promo-codes', { include: ['code','discount_type','discount_value','minimum_order_amount','max_uses','max_uses_per_client','starts_at','ends_at'] })"
                class="p-5 space-y-4"
            >
                {{-- Code --}}
                <div>
                    <label for="promo-code-input" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                        {{ __('Promo Code') }}
                        <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="promo-code-input"
                        type="text"
                        x-model="code"
                        x-name="code"
                        @input="code = code.toUpperCase().replace(/[^A-Z0-9]/g, '')"
                        placeholder="{{ __('e.g. WELCOME10') }}"
                        maxlength="20"
                        autocomplete="off"
                        autocapitalize="characters"
                        class="w-full px-3.5 py-2.5 bg-surface border border-outline rounded-lg text-on-surface-strong placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary font-mono text-sm transition-colors"
                    >
                    <p x-message="code" class="mt-1 text-xs text-danger" role="alert"></p>
                    <p class="mt-1 text-xs text-on-surface opacity-60">{{ __('3-20 characters, letters and numbers only.') }}</p>
                </div>

                {{-- Discount Type --}}
                <div>
                    <span class="block text-sm font-medium text-on-surface-strong mb-2">
                        {{ __('Discount Type') }}
                        <span class="text-danger" aria-hidden="true">*</span>
                    </span>
                    <div class="grid grid-cols-2 gap-2">
                        <label
                            class="relative flex items-center gap-2.5 p-3 border rounded-lg cursor-pointer transition-colors"
                            :class="discount_type === 'percentage' ? 'border-primary bg-primary-subtle' : 'border-outline bg-surface hover:bg-surface-alt'"
                        >
                            <input type="radio" x-model="discount_type" value="percentage" class="sr-only">
                            <div
                                class="w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0"
                                :class="discount_type === 'percentage' ? 'border-primary bg-primary' : 'border-outline'"
                            >
                                <div class="w-1.5 h-1.5 rounded-full bg-on-primary" x-show="discount_type === 'percentage'"></div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-on-surface-strong">{{ __('Percentage') }}</p>
                                <p class="text-xs text-on-surface">{{ __('e.g. 10% off') }}</p>
                            </div>
                        </label>
                        <label
                            class="relative flex items-center gap-2.5 p-3 border rounded-lg cursor-pointer transition-colors"
                            :class="discount_type === 'fixed' ? 'border-primary bg-primary-subtle' : 'border-outline bg-surface hover:bg-surface-alt'"
                        >
                            <input type="radio" x-model="discount_type" value="fixed" class="sr-only">
                            <div
                                class="w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0"
                                :class="discount_type === 'fixed' ? 'border-primary bg-primary' : 'border-outline'"
                            >
                                <div class="w-1.5 h-1.5 rounded-full bg-on-primary" x-show="discount_type === 'fixed'"></div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-on-surface-strong">{{ __('Fixed Amount') }}</p>
                                <p class="text-xs text-on-surface">{{ __('e.g. 500 XAF off') }}</p>
                            </div>
                        </label>
                    </div>
                    <p x-message="discount_type" class="mt-1 text-xs text-danger" role="alert"></p>
                </div>

                {{-- Discount Value --}}
                <div>
                    <label for="discount-value-input" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                        {{ __('Discount Value') }}
                        <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="discount-value-input"
                            type="number"
                            x-model.number="discount_value"
                            x-name="discount_value"
                            :min="1"
                            :max="getDiscountMax()"
                            step="1"
                            class="w-full px-3.5 py-2.5 pr-14 bg-surface border border-outline rounded-lg text-on-surface-strong placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition-colors"
                            :placeholder="discount_type === 'percentage' ? '10' : '500'"
                        >
                        <div class="absolute right-0 inset-y-0 flex items-center px-3 pointer-events-none">
                            <span class="text-sm font-semibold text-on-surface" x-text="getDiscountUnit()"></span>
                        </div>
                    </div>
                    <p x-message="discount_value" class="mt-1 text-xs text-danger" role="alert"></p>
                    <p class="mt-1 text-xs text-on-surface opacity-60" x-show="discount_type === 'percentage'">{{ __('Enter a value between 1 and 100.') }}</p>
                    <p class="mt-1 text-xs text-on-surface opacity-60" x-show="discount_type === 'fixed'">{{ __('Enter a value between 1 and 100,000 XAF.') }}</p>
                </div>

                {{-- Minimum Order Amount --}}
                <div>
                    <label for="min-order-input" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                        {{ __('Minimum Order Amount') }}
                    </label>
                    <div class="relative">
                        <input
                            id="min-order-input"
                            type="number"
                            x-model.number="minimum_order_amount"
                            x-name="minimum_order_amount"
                            min="0"
                            max="100000"
                            step="1"
                            placeholder="0"
                            class="w-full px-3.5 py-2.5 pr-14 bg-surface border border-outline rounded-lg text-on-surface-strong placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition-colors"
                        >
                        <div class="absolute right-0 inset-y-0 flex items-center px-3 pointer-events-none">
                            <span class="text-sm font-semibold text-on-surface">XAF</span>
                        </div>
                    </div>
                    <p x-message="minimum_order_amount" class="mt-1 text-xs text-danger" role="alert"></p>
                    <p class="mt-1 text-xs text-on-surface opacity-60">{{ __('Set to 0 for no minimum required.') }}</p>
                </div>

                {{-- Max Uses & Max Per Client (2-column grid) --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="max-uses-input" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                            {{ __('Max Total Uses') }}
                        </label>
                        <input
                            id="max-uses-input"
                            type="number"
                            x-model.number="max_uses"
                            x-name="max_uses"
                            min="0"
                            max="100000"
                            step="1"
                            placeholder="0"
                            class="w-full px-3.5 py-2.5 bg-surface border border-outline rounded-lg text-on-surface-strong placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition-colors"
                        >
                        <p x-message="max_uses" class="mt-1 text-xs text-danger" role="alert"></p>
                        <p class="mt-1 text-xs text-on-surface opacity-60">{{ __('0 = unlimited') }}</p>
                    </div>
                    <div>
                        <label for="max-per-client-input" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                            {{ __('Max Per Client') }}
                        </label>
                        <input
                            id="max-per-client-input"
                            type="number"
                            x-model.number="max_uses_per_client"
                            x-name="max_uses_per_client"
                            min="0"
                            max="100"
                            step="1"
                            placeholder="0"
                            class="w-full px-3.5 py-2.5 bg-surface border border-outline rounded-lg text-on-surface-strong placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition-colors"
                        >
                        <p x-message="max_uses_per_client" class="mt-1 text-xs text-danger" role="alert"></p>
                        <p class="mt-1 text-xs text-on-surface opacity-60">{{ __('0 = unlimited') }}</p>
                    </div>
                </div>

                {{-- Start Date --}}
                <div>
                    <label for="starts-at-input" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                        {{ __('Start Date') }}
                        <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="starts-at-input"
                        type="date"
                        x-model="starts_at"
                        x-name="starts_at"
                        min="{{ now()->toDateString() }}"
                        class="w-full px-3.5 py-2.5 bg-surface border border-outline rounded-lg text-on-surface-strong focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition-colors"
                    >
                    <p x-message="starts_at" class="mt-1 text-xs text-danger" role="alert"></p>
                </div>

                {{-- End Date --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="ends-at-input" class="block text-sm font-medium text-on-surface-strong">
                            {{ __('End Date') }}
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                x-model="no_end_date"
                                class="w-3.5 h-3.5 rounded border-outline accent-primary cursor-pointer"
                            >
                            <span class="text-xs text-on-surface">{{ __('No end date') }}</span>
                        </label>
                    </div>
                    <input
                        id="ends-at-input"
                        type="date"
                        x-model="ends_at"
                        x-name="ends_at"
                        :min="starts_at || '{{ now()->toDateString() }}'"
                        :disabled="no_end_date"
                        :class="no_end_date ? 'opacity-40 cursor-not-allowed' : ''"
                        class="w-full px-3.5 py-2.5 bg-surface border border-outline rounded-lg text-on-surface-strong focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                    <p x-message="ends_at" class="mt-1 text-xs text-danger" role="alert"></p>
                    <p class="mt-1 text-xs text-on-surface opacity-60" x-show="!no_end_date">{{ __('Must be on or after the start date.') }}</p>
                </div>

                {{-- Form Actions --}}
                <div class="flex items-center gap-3 pt-2 border-t border-outline">
                    <button
                        type="submit"
                        class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-on-primary text-sm font-semibold rounded-lg hover:bg-primary-hover transition-colors disabled:opacity-60"
                        :disabled="$fetching()"
                    >
                        <span x-show="!$fetching()">{{ __('Create Promo Code') }}</span>
                        <span x-show="$fetching()" class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
                            </svg>
                            {{ __('Creating...') }}
                        </span>
                    </button>
                    <button
                        type="button"
                        @click="closeModal()"
                        class="px-4 py-2.5 text-on-surface text-sm font-medium rounded-lg border border-outline hover:bg-surface-alt transition-colors"
                    >
                        {{ __('Cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
