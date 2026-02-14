{{--
    Language Switcher Component
    --------------------------
    Compact dropdown to toggle between English and French.
    Works with Gale for reactive updates, with a no-JS form fallback.
    Uses semantic color tokens for light/dark mode compatibility.
--}}
@php
    $currentLocale = app()->getLocale();
    $locales = [
        'en' => ['label' => 'English', 'flag' => 'EN'],
        'fr' => ['label' => 'Français', 'flag' => 'FR'],
    ];
    $currentFlag = $locales[$currentLocale]['flag'] ?? 'EN';
    $currentLabel = $locales[$currentLocale]['label'] ?? 'English';
@endphp

<div
    x-data="{
        open: false,
        locale: '{{ $currentLocale }}',
        switching: false,
        switchLocale(newLocale) {
            if (newLocale === this.locale || this.switching) return;
            this.switching = true;
            this.locale = newLocale;
            this.open = false;
            $action('{{ route('locale.switch') }}', { include: ['locale'] });
        }
    }"
    x-sync="['locale']"
    class="relative"
    @click.away="open = false"
    @keydown.escape.window="open = false"
>
    {{-- Trigger Button --}}
    <button
        type="button"
        @click="open = !open"
        :aria-expanded="open"
        aria-haspopup="listbox"
        aria-label="{{ __('Language') }}"
        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-sm font-medium rounded-lg
               text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt
               border border-outline dark:border-outline
               transition-all duration-200
               focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1"
    >
        {{-- Globe icon (Lucide) --}}
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path>
            <path d="M2 12h20"></path>
        </svg>

        <span x-text="locale.toUpperCase()" class="font-semibold">{{ $currentFlag }}</span>

        {{-- Chevron --}}
        <svg
            class="w-3.5 h-3.5 transition-transform duration-200"
            :class="open ? 'rotate-180' : ''"
            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
        >
            <path d="m6 9 6 6 6-6"></path>
        </svg>
    </button>

    {{-- Dropdown Menu --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 z-50 mt-1.5 w-40 origin-top-right
               bg-surface dark:bg-surface-alt
               rounded-lg border border-outline dark:border-outline
               shadow-dropdown
               py-1"
        role="listbox"
        :aria-label="'{{ __('Select language') }}'"
        x-cloak
    >
        @foreach($locales as $code => $locale)
            <button
                type="button"
                role="option"
                :aria-selected="locale === '{{ $code }}'"
                @click="switchLocale('{{ $code }}')"
                class="w-full flex items-center gap-3 px-3 py-2 text-sm transition-colors duration-150
                       hover:bg-surface-alt dark:hover:bg-surface
                       {{ $currentLocale === $code ? 'text-primary dark:text-primary font-semibold' : 'text-on-surface dark:text-on-surface' }}"
            >
                <span class="w-6 text-center font-mono text-xs font-bold
                             {{ $currentLocale === $code ? 'text-primary dark:text-primary' : 'text-on-surface dark:text-on-surface' }}">
                    {{ $locale['flag'] }}
                </span>
                <span>{{ $locale['label'] }}</span>
                @if($currentLocale === $code)
                    {{-- Check icon (Lucide) --}}
                    <svg class="w-4 h-4 ml-auto text-primary dark:text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5"></path>
                    </svg>
                @endif
            </button>
        @endforeach
    </div>

    {{-- No-JS Fallback Form --}}
    <noscript>
        <form action="{{ route('locale.switch') }}" method="POST" class="inline">
            @csrf
            @foreach($locales as $code => $locale)
                @if($code !== $currentLocale)
                    <button type="submit" name="locale" value="{{ $code }}"
                            class="text-sm text-primary hover:text-primary-hover underline">
                        {{ $locale['label'] }}
                    </button>
                @endif
            @endforeach
        </form>
    </noscript>
</div>
