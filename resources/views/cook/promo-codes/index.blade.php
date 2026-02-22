{{--
    Cook Promo Codes Page
    ---------------------
    F-215: Cook Promo Code Creation

    Allows cooks to create promotional discount codes for their tenant site.

    Features:
    - Promo code list: code, type, value, status, uses/max, dates
    - "Create Promo Code" button opens modal form
    - Form modal with: code (auto-uppercase), discount type, value, min order,
      max uses, max per client, start date, end date (optional)
    - "No end date" checkbox hides the end date field
    - After creation: list updates via Gale fragment, modal closes, toast shown
    - Inline validation errors
    - Mobile-first responsive layout
    - Light/dark mode support
    - All text localized with __()

    BR-533: Code alphanumeric, 3-20 chars, stored uppercase.
    BR-534: Code unique within tenant.
    BR-535: Discount types: percentage or fixed.
    BR-536: Percentage 1-100%.
    BR-537: Fixed 1-100,000 XAF.
    BR-538: Minimum order 0-100,000 XAF.
    BR-539: Max uses 0 = unlimited.
    BR-540: Max per client 0 = unlimited.
    BR-541: Start date required, today or future.
    BR-542: End date optional, after start date.
    BR-544: Created as active.
    BR-545: Cook-reserved action.
    BR-548: Gale handles all interactions.
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
        getDiscountUnit() {
            return this.discount_type === 'percentage' ? '%' : 'XAF';
        },
        getDiscountMax() {
            return this.discount_type === 'percentage' ? 100 : 100000;
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
                {{ __('Create discount codes for your customers.') }}
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
        @if ($promoCodes->isEmpty())
            {{-- Empty State --}}
            <div class="bg-surface-alt border border-outline rounded-xl p-10 text-center">
                <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-primary-subtle flex items-center justify-center">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 5H2v7l6.29 6.29c.94.94 2.48.94 3.42 0l3.58-3.58c.94-.94.94-2.48 0-3.42L9 5Z"></path>
                        <path d="M6 9.01V9"></path>
                    </svg>
                </div>
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
            </div>
        @else
            {{-- Desktop Table --}}
            <div class="hidden md:block bg-surface-alt border border-outline rounded-xl overflow-hidden shadow-card">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-outline bg-surface">
                            <th class="px-4 py-3 text-left font-semibold text-on-surface-strong">{{ __('Code') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-on-surface-strong">{{ __('Discount') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-on-surface-strong hidden lg:table-cell">{{ __('Min. Order') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-on-surface-strong">{{ __('Uses') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-on-surface-strong hidden lg:table-cell">{{ __('Dates') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-on-surface-strong">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline">
                        @foreach ($promoCodes as $promoCode)
                        <tr class="hover:bg-surface transition-colors">
                            <td class="px-4 py-3">
                                <span class="font-mono font-semibold text-on-surface-strong tracking-wide">{{ $promoCode->code }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-semibold text-primary">{{ $promoCode->discount_label }}</span>
                                    <span class="text-xs text-on-surface">
                                        @if ($promoCode->discount_type === 'percentage')
                                            {{ __('off') }}
                                        @else
                                            {{ __('off') }}
                                        @endif
                                    </span>
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
                                    <div>{{ __('To') }}: {{ $promoCode->ends_at->format('M j, Y') }}</div>
                                @else
                                    <div class="opacity-50">{{ __('No expiry') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($promoCode->status === 'active')
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
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards --}}
            <div class="md:hidden space-y-3">
                @foreach ($promoCodes as $promoCode)
                <div class="bg-surface-alt border border-outline rounded-xl p-4 shadow-card">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <span class="font-mono font-bold text-base text-on-surface-strong tracking-wide">{{ $promoCode->code }}</span>
                        @if ($promoCode->status === 'active')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-success-subtle text-success shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                {{ __('Active') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-surface text-on-surface border border-outline shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-on-surface opacity-40"></span>
                                {{ __('Inactive') }}
                            </span>
                        @endif
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
                            <p class="text-on-surface">{{ $promoCode->ends_at->format('M j, Y') }}</p>
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
            class="relative w-full sm:max-w-lg bg-surface border border-outline rounded-t-2xl sm:rounded-2xl shadow-dropdown max-h-[90vh] overflow-y-auto"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            {{-- Modal Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-outline sticky top-0 bg-surface z-10">
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
