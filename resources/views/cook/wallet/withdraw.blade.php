{{--
    Cook Withdrawal Request (F-172)
    --------------------------------
    Form for the cook to withdraw funds from their wallet to mobile money.

    BR-344: Amount must be > 0 and <= withdrawable balance.
    BR-345: Minimum withdrawal amount enforced from platform settings.
    BR-346: Maximum daily withdrawal amount enforced.
    BR-348: Cook must confirm mobile money number.
    BR-349: Cameroon phone format validated.
    BR-350: Confirmation dialog before final submission.
    BR-353: Only the cook can initiate withdrawals.
    BR-355: All user-facing text uses __() localization.
--}}
@extends('layouts.cook-dashboard')

@section('page-title', __('Withdraw Funds'))

@section('content')
<div class="max-w-lg mx-auto" x-data="{
    amount: '',
    mobile_money_number: '{{ addslashes($defaultPhone) }}',
    mobile_money_provider: '{{ addslashes($defaultProvider) }}',
    showConfirmation: false,
    maxWithdrawable: {{ $maxWithdrawable }},
    minAmount: {{ $minAmount }},
    withdrawableBalance: {{ (float) $wallet->withdrawable_balance }},
    remainingDaily: {{ $remainingDaily }},

    get formattedAmount() {
        const num = parseInt(this.amount) || 0;
        return new Intl.NumberFormat().format(num);
    },

    get isValidAmount() {
        const num = parseInt(this.amount) || 0;
        return num >= this.minAmount && num <= this.maxWithdrawable;
    },

    get amountError() {
        const num = parseInt(this.amount) || 0;
        if (this.amount === '') return '';
        if (num <= 0) return '{{ __('Amount must be greater than zero.') }}';
        if (num < this.minAmount) return '{{ __('Minimum withdrawal amount is :amount XAF.', ['amount' => number_format($minAmount)]) }}';
        if (num > this.withdrawableBalance) return '{{ __('Insufficient withdrawable balance.') }}';
        if (num > this.remainingDaily) return '{{ __('Exceeds daily withdrawal limit.') }}';
        return '';
    },

    get providerLabel() {
        if (this.mobile_money_provider === 'mtn_momo') return 'MTN MoMo';
        if (this.mobile_money_provider === 'orange_money') return 'Orange Money';
        return '{{ __('Mobile Money') }}';
    },

    setMaxAmount() {
        this.amount = String(Math.floor(this.maxWithdrawable));
    },

    detectProvider() {
        const phone = this.mobile_money_number.replace(/[\s\-()]/g, '');
        if (phone.length >= 2) {
            const prefix2 = phone.substring(0, 2);
            const prefix3 = phone.length >= 3 ? phone.substring(0, 3) : '';
            if (prefix2 === '69' || ['655','656','657','658','659'].includes(prefix3)) {
                this.mobile_money_provider = 'orange_money';
                return;
            }
            if (['67','68'].includes(prefix2) || ['650','651','652','653','654'].includes(prefix3)) {
                this.mobile_money_provider = 'mtn_momo';
                return;
            }
        }
    },

    openConfirmation() {
        if (!this.isValidAmount) return;
        this.showConfirmation = true;
    },

    submitWithdrawal() {
        this.showConfirmation = false;
        $action('{{ url('/dashboard/wallet/withdraw') }}', { method: 'POST' });
    }
}" x-sync="['amount', 'mobile_money_number', 'mobile_money_provider']">

    {{-- Back Link --}}
    <div class="mb-6" x-navigate>
        <a href="{{ url('/dashboard/wallet') }}" class="inline-flex items-center gap-2 text-sm text-on-surface hover:text-primary transition-colors">
            {{-- ArrowLeft icon (Lucide, sm=16) --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
            {{ __('Back to Wallet') }}
        </a>
    </div>

    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-on-surface-strong">
            {{ __('Withdraw Funds') }}
        </h1>
        <p class="text-sm text-on-surface mt-1">
            {{ __('Transfer funds from your wallet to your mobile money account.') }}
        </p>
    </div>

    {{-- Available Balance Card --}}
    <div class="bg-success-subtle dark:bg-success-subtle border border-success/20 rounded-xl p-5 mb-6">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-full bg-success/20 flex items-center justify-center">
                {{-- CircleCheck icon (Lucide, md=20) --}}
                <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
            </span>
            <div>
                <p class="text-xs text-success font-medium uppercase tracking-wider">
                    {{ __('Available for Withdrawal') }}
                </p>
                <p class="text-2xl font-bold text-success font-mono">
                    {{ number_format($maxWithdrawable, 0, '.', ',') }} XAF
                </p>
            </div>
        </div>

        @if($todayWithdrawn > 0)
            <div class="mt-3 pt-3 border-t border-success/20">
                <p class="text-xs text-on-surface">
                    {{ __('Withdrawn today: :amount XAF', ['amount' => number_format($todayWithdrawn)]) }}
                    &middot;
                    {{ __('Daily limit: :amount XAF', ['amount' => number_format($maxDailyAmount)]) }}
                </p>
            </div>
        @endif
    </div>

    {{-- Withdrawal Form --}}
    <div class="bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-5 shadow-card">
        <form @submit.prevent="openConfirmation()">

            {{-- Amount Field --}}
            <div class="mb-5">
                <label for="amount" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                    {{ __('Withdrawal Amount') }} <span class="text-danger">*</span>
                </label>
                <div class="relative">
                    <input
                        type="number"
                        id="amount"
                        x-model="amount"
                        x-name="amount"
                        min="1"
                        :max="maxWithdrawable"
                        step="1"
                        placeholder="{{ __('Enter amount in XAF') }}"
                        class="w-full h-11 pl-4 pr-16 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors font-mono text-lg"
                        :class="amountError ? 'border-danger focus:ring-danger/30 focus:border-danger' : ''"
                    >
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-on-surface/60 font-medium">XAF</span>
                </div>

                {{-- Client-side validation message --}}
                <p x-show="amountError" x-text="amountError" x-cloak class="text-xs text-danger mt-1.5"></p>

                {{-- Server-side validation message --}}
                <p x-message="amount" class="text-xs text-danger mt-1.5"></p>

                {{-- Quick actions --}}
                <div class="flex items-center gap-3 mt-2">
                    <button
                        type="button"
                        @click="setMaxAmount()"
                        class="text-xs text-primary hover:text-primary-hover font-medium transition-colors"
                    >
                        {{ __('Withdraw All') }}
                    </button>

                    <span class="text-xs text-on-surface/40">
                        {{ __('Min: :amount XAF', ['amount' => number_format($minAmount)]) }}
                    </span>
                </div>
            </div>

            {{-- Mobile Money Number --}}
            <div class="mb-5">
                <label for="mobile_money_number" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                    {{ __('Mobile Money Number') }} <span class="text-danger">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-on-surface/60 font-medium">+237</span>
                    <input
                        type="tel"
                        id="mobile_money_number"
                        x-model="mobile_money_number"
                        x-name="mobile_money_number"
                        @input="detectProvider()"
                        maxlength="9"
                        placeholder="6XXXXXXXX"
                        class="w-full h-11 pl-14 pr-4 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors font-mono"
                    >
                </div>
                <p x-message="mobile_money_number" class="text-xs text-danger mt-1.5"></p>
                <p class="text-xs text-on-surface/60 mt-1">
                    {{ __('This number will receive the withdrawal. It does not update your profile.') }}
                </p>
            </div>

            {{-- Mobile Money Provider --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-on-surface-strong mb-2">
                    {{ __('Provider') }} <span class="text-danger">*</span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    {{-- MTN MoMo --}}
                    <label
                        class="relative flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all"
                        :class="mobile_money_provider === 'mtn_momo' ? 'border-[#ffcc00] bg-[#ffcc00]/5' : 'border-outline dark:border-outline hover:border-outline-strong'"
                    >
                        <input
                            type="radio"
                            name="mobile_money_provider"
                            value="mtn_momo"
                            x-model="mobile_money_provider"
                            class="sr-only"
                        >
                        <span class="w-8 h-8 rounded-full bg-[#ffcc00] flex items-center justify-center text-xs font-bold text-black shrink-0">
                            MTN
                        </span>
                        <span class="text-sm font-medium text-on-surface-strong">MTN MoMo</span>
                        <span
                            x-show="mobile_money_provider === 'mtn_momo'"
                            class="absolute top-2 right-2"
                            x-cloak
                        >
                            <svg class="w-4 h-4 text-[#ffcc00]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        </span>
                    </label>

                    {{-- Orange Money --}}
                    <label
                        class="relative flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all"
                        :class="mobile_money_provider === 'orange_money' ? 'border-[#ff6600] bg-[#ff6600]/5' : 'border-outline dark:border-outline hover:border-outline-strong'"
                    >
                        <input
                            type="radio"
                            name="mobile_money_provider"
                            value="orange_money"
                            x-model="mobile_money_provider"
                            class="sr-only"
                        >
                        <span class="w-8 h-8 rounded-full bg-[#ff6600] flex items-center justify-center text-xs font-bold text-white shrink-0">
                            OM
                        </span>
                        <span class="text-sm font-medium text-on-surface-strong">Orange Money</span>
                        <span
                            x-show="mobile_money_provider === 'orange_money'"
                            class="absolute top-2 right-2"
                            x-cloak
                        >
                            <svg class="w-4 h-4 text-[#ff6600]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        </span>
                    </label>
                </div>
                <p x-message="mobile_money_provider" class="text-xs text-danger mt-1.5"></p>
            </div>

            {{-- Submit Button --}}
            <button
                type="submit"
                :disabled="!isValidAmount || !mobile_money_number || !mobile_money_provider"
                class="w-full h-11 rounded-lg bg-primary text-on-primary text-sm font-semibold hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
                <span x-show="!$fetching()">
                    {{-- ArrowUpFromLine icon (Lucide, sm=16) --}}
                    <svg class="w-4 h-4 inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 9-6-6-6 6"></path><path d="M12 3v14"></path><path d="M5 21h14"></path></svg>
                    {{ __('Review Withdrawal') }}
                </span>
                <span x-show="$fetching()" x-cloak>
                    <svg class="w-4 h-4 inline animate-spin-slow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                    {{ __('Processing...') }}
                </span>
            </button>
        </form>
    </div>

    {{-- Info Note --}}
    <div class="mt-4 flex items-start gap-3 p-4 bg-info-subtle dark:bg-info-subtle rounded-lg">
        <svg class="w-5 h-5 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
        <p class="text-xs text-on-surface leading-relaxed">
            {{ __('Withdrawal requests are processed via Flutterwave. Funds typically arrive within a few minutes but may take up to 24 hours during peak periods.') }}
        </p>
    </div>

    {{-- Confirmation Modal --}}
    {{-- BR-350: Confirmation dialog shows amount and destination before final submission --}}
    <div
        x-show="showConfirmation"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @keydown.escape.window="showConfirmation = false"
    >
        {{-- Backdrop --}}
        <div
            class="absolute inset-0 bg-black/50"
            @click="showConfirmation = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        ></div>

        {{-- Dialog --}}
        <div
            class="relative bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl shadow-lg max-w-sm w-full p-6"
            role="dialog"
            aria-modal="true"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >
            {{-- Icon --}}
            <div class="flex justify-center mb-4">
                <span class="w-12 h-12 rounded-full bg-primary-subtle dark:bg-primary-subtle flex items-center justify-center">
                    <svg class="w-6 h-6 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 9-6-6-6 6"></path><path d="M12 3v14"></path><path d="M5 21h14"></path></svg>
                </span>
            </div>

            <h3 class="text-lg font-semibold text-on-surface-strong text-center mb-2">
                {{ __('Confirm Withdrawal') }}
            </h3>

            <p class="text-sm text-on-surface text-center mb-5">
                {{ __('Please review the details below before confirming.') }}
            </p>

            {{-- Summary --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg p-4 mb-5 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-on-surface">{{ __('Amount') }}</span>
                    <span class="text-sm font-bold text-on-surface-strong font-mono" x-text="formattedAmount + ' XAF'"></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-on-surface">{{ __('Provider') }}</span>
                    <span class="text-sm font-medium text-on-surface-strong" x-text="providerLabel"></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-on-surface">{{ __('Number') }}</span>
                    <span class="text-sm font-mono text-on-surface-strong">+237 <span x-text="mobile_money_number"></span></span>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex gap-3">
                <button
                    type="button"
                    @click="showConfirmation = false"
                    class="flex-1 h-10 rounded-lg border border-outline dark:border-outline text-sm font-medium text-on-surface hover:bg-surface-alt transition-colors"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="button"
                    @click="submitWithdrawal()"
                    class="flex-1 h-10 rounded-lg bg-primary text-on-primary text-sm font-semibold hover:bg-primary-hover transition-colors"
                >
                    {{ __('Confirm Withdrawal') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
