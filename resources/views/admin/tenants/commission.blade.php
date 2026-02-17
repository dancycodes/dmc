{{--
    Commission Configuration per Cook
    ----------------------------------
    F-062: Configure platform commission percentage for a specific tenant.

    BR-175: Default commission rate is 10%
    BR-176: Commission range: 0% to 50% in 0.5% increments
    BR-179: Changes recorded with new rate, admin, timestamp, reason
    BR-180: Reset to default sets rate back to platform default
    BR-181: Commission configuration logged in activity log
--}}
@extends('layouts.admin')

@section('title', $tenant->name . ' — ' . __('Commission Configuration'))
@section('page-title', __('Commission Configuration'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Tenants'), 'url' => '/vault-entry/tenants'],
        ['label' => $tenant->name, 'url' => '/vault-entry/tenants/' . $tenant->slug],
        ['label' => __('Commission')],
    ]" />

    {{-- Current Commission Rate Display --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-on-surface/60 uppercase tracking-wide mb-1">{{ __('Current Commission') }}</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl sm:text-5xl font-bold text-on-surface-strong">{{ number_format($currentRate, 1) }}%</span>
                    @if($isDefault)
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-info-subtle text-info">
                            {{ __('Platform Default') }}
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-warning-subtle text-warning">
                            {{ __('Custom Rate') }}
                        </span>
                    @endif
                </div>
                <p class="text-sm text-on-surface mt-2">
                    {{ __('Platform Default: :rate%', ['rate' => number_format($defaultRate, 1)]) }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                {{-- Tenant avatar --}}
                <div class="w-12 h-12 rounded-xl bg-primary-subtle flex items-center justify-center text-primary font-bold text-lg shrink-0">
                    {{ mb_strtoupper(mb_substr($tenant->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-on-surface-strong">{{ $tenant->name }}</p>
                    <p class="text-xs text-on-surface/60">{{ $tenant->slug }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Commission Rate Form --}}
    <div
        class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6"
        x-data="{
            commission_rate: '{{ number_format($currentRate, 1) }}',
            reason: '',
            showResetConfirm: false,

            updateSlider(val) {
                let num = parseFloat(val);
                if (isNaN(num)) return;
                num = Math.max({{ $minRate }}, Math.min({{ $maxRate }}, num));
                num = Math.round(num * 2) / 2;
                this.commission_rate = num.toFixed(1);
            },

            updateInput(val) {
                let num = parseFloat(val);
                if (isNaN(num)) return;
                num = Math.max({{ $minRate }}, Math.min({{ $maxRate }}, num));
                num = Math.round(num * 2) / 2;
                this.commission_rate = num.toFixed(1);
            },

            get hasChanged() {
                return parseFloat(this.commission_rate) !== {{ $currentRate }};
            },

            get isDefaultRate() {
                return parseFloat(this.commission_rate) === {{ $defaultRate }};
            }
        }"
        x-sync="['commission_rate', 'reason']"
    >
        <h3 class="text-base font-semibold text-on-surface-strong mb-6">{{ __('Update Commission Rate') }}</h3>

        {{-- Rate Slider --}}
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-on-surface-strong mb-2">
                    {{ __('Commission Rate') }}
                </label>

                {{-- Slider + Input synchronized --}}
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    {{-- Slider --}}
                    <div class="flex-1 w-full">
                        <input
                            type="range"
                            :value="commission_rate"
                            @input="updateSlider($event.target.value)"
                            min="{{ $minRate }}"
                            max="{{ $maxRate }}"
                            step="{{ $rateStep }}"
                            class="w-full h-2 rounded-full appearance-none cursor-pointer accent-primary bg-outline/30"
                        >
                        <div class="flex justify-between text-xs text-on-surface/50 mt-1">
                            <span>{{ number_format($minRate, 0) }}%</span>
                            <span>{{ number_format($defaultRate, 0) }}% ({{ __('default') }})</span>
                            <span>{{ number_format($maxRate, 0) }}%</span>
                        </div>
                    </div>

                    {{-- Manual input --}}
                    <div class="flex items-center gap-2 shrink-0">
                        <input
                            type="number"
                            x-name="commission_rate"
                            :value="commission_rate"
                            @change="updateInput($event.target.value)"
                            min="{{ $minRate }}"
                            max="{{ $maxRate }}"
                            step="{{ $rateStep }}"
                            class="w-24 h-10 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong text-center font-mono text-lg
                                   focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                        >
                        <span class="text-lg font-semibold text-on-surface-strong">%</span>
                    </div>
                </div>
                <p x-message="commission_rate" class="mt-1 text-sm text-danger"></p>
            </div>

            {{-- Reason textarea --}}
            <div>
                <label class="block text-sm font-medium text-on-surface-strong mb-2">
                    {{ __('Reason for Change') }}
                    <span class="text-on-surface/50 font-normal">({{ __('optional') }})</span>
                </label>
                <textarea
                    x-name="reason"
                    x-model="reason"
                    rows="3"
                    maxlength="1000"
                    placeholder="{{ __('e.g., Reduced rate for top performer') }}"
                    class="w-full rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40
                           focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm px-3 py-2 resize-none"
                ></textarea>
                <p x-message="reason" class="mt-1 text-sm text-danger"></p>
                <p class="text-xs text-on-surface/50 mt-1" x-text="reason.length + '/1000'"></p>
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 pt-2">
                {{-- Update Rate button --}}
                <button
                    @click="$action('{{ url('/vault-entry/tenants/' . $tenant->slug . '/commission') }}')"
                    :disabled="!hasChanged"
                    class="h-10 px-6 text-sm rounded-lg font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 inline-flex items-center gap-2
                           focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2
                           disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span x-show="!$fetching()">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    </span>
                    <span x-show="$fetching()" class="animate-spin-slow">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                    </span>
                    <span x-text="$fetching() ? '{{ __('Saving...') }}' : '{{ __('Update Commission Rate') }}'"></span>
                </button>

                {{-- Reset to Default button (only shown when rate differs from default) --}}
                @if(!$isDefault)
                    <button
                        @click="showResetConfirm = true"
                        type="button"
                        class="h-10 px-6 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface transition-all duration-200 inline-flex items-center gap-2
                               focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                        {{ __('Reset to Default') }}
                    </button>
                @endif
            </div>
        </div>

        {{-- Reset Confirmation Modal (inside x-data scope per F-055 convention) --}}
        @if(!$isDefault)
            <div
                x-show="showResetConfirm"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @keydown.escape.window="showResetConfirm = false"
            >
                {{-- Backdrop --}}
                <div
                    class="fixed inset-0 bg-black/50 dark:bg-black/70"
                    @click="showResetConfirm = false"
                ></div>

                {{-- Modal content --}}
                <div class="relative bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-dropdown p-6 w-full max-w-md animate-scale-in">
                    <div class="flex items-start gap-4">
                        <span class="w-10 h-10 rounded-full bg-warning-subtle flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-base font-semibold text-on-surface-strong">{{ __('Reset to Default?') }}</h4>
                            <p class="text-sm text-on-surface mt-2">
                                {{ __('This will reset the commission rate from :current% to the platform default of :default%. This change will affect all new orders.', [
                                    'current' => number_format($currentRate, 1),
                                    'default' => number_format($defaultRate, 1),
                                ]) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button
                            @click="showResetConfirm = false"
                            type="button"
                            class="h-9 px-4 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface transition-all duration-200
                                   focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            @click="showResetConfirm = false; $action('{{ url('/vault-entry/tenants/' . $tenant->slug . '/commission/reset') }}', { include: [] })"
                            type="button"
                            class="h-9 px-4 text-sm rounded-lg font-semibold bg-warning hover:bg-warning/90 text-on-warning transition-all duration-200
                                   focus:outline-none focus:ring-2 focus:ring-warning focus:ring-offset-2"
                        >
                            {{ __('Reset to Default') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Commission Change History --}}
    <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
        <h3 class="text-base font-semibold text-on-surface-strong mb-4">{{ __('Commission Change History') }}</h3>

        @fragment('commission-history-content')
        <div id="commission-history-content">
            @if($history->count() > 0)
                <div class="space-y-4">
                    @foreach($history as $change)
                        <div class="flex items-start gap-3 pb-4 {{ !$loop->last ? 'border-b border-outline dark:border-outline' : '' }}">
                            {{-- Timeline indicator --}}
                            <div class="flex flex-col items-center shrink-0 mt-1">
                                <div class="w-3 h-3 rounded-full {{ $change->isResetToDefault() ? 'bg-info' : 'bg-primary' }}"></div>
                                @if(!$loop->last)
                                    <div class="w-0.5 flex-1 bg-outline/30 mt-1" style="min-height: 2rem;"></div>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0">
                                {{-- Rate change display --}}
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-mono font-semibold text-danger line-through">{{ number_format($change->old_rate, 1) }}%</span>
                                    <svg class="w-4 h-4 text-on-surface/50 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                                    <span class="text-sm font-mono font-semibold text-success">{{ number_format($change->new_rate, 1) }}%</span>

                                    @if($change->isResetToDefault())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-info-subtle text-info">
                                            {{ __('Default') }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Admin and timestamp --}}
                                <p class="text-sm text-on-surface mt-1">
                                    {{ __('Changed by') }}
                                    <span class="font-medium text-on-surface-strong">{{ $change->admin?->name ?? __('Unknown') }}</span>
                                    <span class="text-on-surface/50">&middot;</span>
                                    <span class="text-on-surface/60" title="{{ $change->created_at?->format('Y-m-d H:i:s') }}">
                                        {{ $change->created_at?->format('M d, Y \a\t h:i A') }}
                                    </span>
                                </p>

                                {{-- Reason --}}
                                @if($change->reason)
                                    <div class="mt-2 px-3 py-2 bg-surface dark:bg-surface rounded-lg border border-outline/50 dark:border-outline/50">
                                        <p class="text-sm text-on-surface italic">"{{ $change->reason }}"</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- History pagination --}}
                @if($history->hasPages())
                    <div class="mt-6 pt-4 border-t border-outline dark:border-outline" x-data x-navigate>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <p class="text-xs text-on-surface/60">
                                {{ __('Showing :from-:to of :total changes', [
                                    'from' => $history->firstItem(),
                                    'to' => $history->lastItem(),
                                    'total' => $history->total(),
                                ]) }}
                            </p>
                            <div class="flex gap-2">
                                @if($history->previousPageUrl())
                                    <a
                                        href="{{ $history->previousPageUrl() }}"
                                        x-navigate.key.commission-history
                                        class="h-8 px-3 text-xs rounded-lg font-medium border border-outline text-on-surface hover:bg-surface transition-colors inline-flex items-center gap-1"
                                    >
                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                                        {{ __('Previous') }}
                                    </a>
                                @endif
                                @if($history->nextPageUrl())
                                    <a
                                        href="{{ $history->nextPageUrl() }}"
                                        x-navigate.key.commission-history
                                        class="h-8 px-3 text-xs rounded-lg font-medium border border-outline text-on-surface hover:bg-surface transition-colors inline-flex items-center gap-1"
                                    >
                                        {{ __('Next') }}
                                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            @else
                {{-- Empty state: no changes yet --}}
                <div class="text-center py-8">
                    <div class="w-12 h-12 mx-auto rounded-full bg-outline/10 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>
                    </div>
                    <p class="text-sm text-on-surface font-medium">{{ __('No commission changes recorded yet.') }}</p>
                    <p class="text-xs text-on-surface/60 mt-1">{{ __('The commission rate is currently at the platform default (:rate%).', ['rate' => number_format($defaultRate, 1)]) }}</p>
                </div>
            @endif
        </div>
        @endfragment
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
</div>
@endsection
