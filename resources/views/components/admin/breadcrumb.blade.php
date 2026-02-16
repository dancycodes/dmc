{{--
    Admin Breadcrumb Component
    --------------------------
    Displays breadcrumb navigation for admin panel pages.
    F-043: Breadcrumb navigation on all admin pages.

    Usage: <x-admin.breadcrumb :items="[['label' => 'Tenants', 'url' => '/vault-entry/tenants'], ['label' => 'Edit']]" />
--}}
@props(['items' => []])

<nav aria-label="{{ __('Breadcrumb') }}" class="mb-4 sm:mb-6">
    <ol class="flex items-center gap-1.5 text-sm text-on-surface">
        {{-- Home/Dashboard is always first --}}
        <li class="flex items-center gap-1.5">
            <a href="{{ url('/vault-entry') }}" class="hover:text-primary transition-colors duration-200">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            </a>
        </li>

        @foreach($items as $item)
            <li class="flex items-center gap-1.5">
                {{-- Separator --}}
                <svg class="w-3.5 h-3.5 text-on-surface/50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>

                @if(isset($item['url']))
                    <a href="{{ url($item['url']) }}" class="hover:text-primary transition-colors duration-200">
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="text-on-surface-strong font-medium">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
