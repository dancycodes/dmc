{{--
    Notification Bell Component (Placeholder)
    -----------------------------------------
    Shows a bell icon with an unread count badge for authenticated users.
    BR-128: Placeholder, functional in later features (F-191+).
    Uses semantic color tokens and dark mode variants.
--}}
@auth
<button
    type="button"
    class="relative w-10 h-10 rounded-full flex items-center justify-center
           text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt
           transition-colors duration-200
           focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1"
    aria-label="{{ __('Notifications') }}"
    title="{{ __('Notifications') }}"
>
    {{-- Bell icon (Lucide) --}}
    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
        <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
    </svg>

    {{-- Badge placeholder: hidden by default, shown when notifications exist --}}
    <span
        class="absolute -top-0.5 -right-0.5 hidden w-5 h-5 rounded-full bg-danger text-on-danger text-[10px] font-bold items-center justify-center ring-2 ring-surface"
        aria-hidden="true"
    >
        0
    </span>
</button>
@endauth
