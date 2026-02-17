{{--
    Cook Setup Incomplete Banner
    ----------------------------
    F-076: BR-163 — If tenant setup is incomplete, a setup completion banner
    is shown at the top of every dashboard page.

    Displays a prominent warning banner with a link to the setup wizard (F-071).
--}}
@php
    $currentTenant = tenant();
    $showBanner = $currentTenant && ! $currentTenant->isSetupComplete();
@endphp

@if($showBanner)
    <div class="mb-6 rounded-xl border border-warning bg-warning-subtle p-4 sm:p-5">
        <div class="flex items-start gap-3 sm:items-center">
            {{-- Warning icon (Lucide: alert-triangle) --}}
            <div class="w-10 h-10 rounded-full bg-warning/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
            </div>

            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-semibold text-on-surface-strong">
                    {{ __('Complete your setup to go live') }}
                </h3>
                <p class="mt-1 text-sm text-on-surface">
                    {{ __('Your store is not yet visible to customers. Complete the setup wizard to start receiving orders.') }}
                </p>
            </div>

            {{-- Link to setup wizard (F-071 will provide the actual route) --}}
            <a
                href="{{ url('/dashboard/setup') }}"
                class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-warning text-on-warning text-sm font-medium hover:opacity-90 transition-opacity duration-200"
            >
                {{ __('Start Setup') }}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </a>
        </div>
    </div>
@endif
