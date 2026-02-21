{{--
    Manager Permission Configuration Panel
    ---------------------------------------
    F-210: Manager Permission Configuration

    Rendered as a fragment ('permissions-panel') that is injected into the managers index page.
    The cook toggles each of the 7 delegatable permissions on or off per manager.
    All interactions via Gale — no page reloads (BR-482).

    This partial is included inside the parent x-data scope from managers/index.blade.php.
    The parent has 'permission' in x-sync so each toggle sets permission then calls $action.

    BR-473: 7 delegatable permissions grouped by category
    BR-474: Toggled per manager per tenant
    BR-475: New managers start with all off
    BR-476: Takes effect immediately
    BR-477: Only cook can configure
    BR-479: Stored as Spatie permissions
    BR-480: Logged with before/after values
    BR-481: All text uses __()
    BR-482: All interactions via Gale
--}}
@fragment('permissions-panel')
<div id="permissions-panel">
    <div class="bg-surface-alt dark:bg-surface-alt rounded-xl border border-outline dark:border-outline shadow-card overflow-hidden">
        {{-- Panel header --}}
        <div class="flex items-center justify-between px-4 sm:px-6 py-4 border-b border-outline dark:border-outline bg-surface dark:bg-surface">
            <div class="flex items-center gap-3 min-w-0">
                {{-- Settings / gear icon (Lucide, sm=16) --}}
                <div class="w-8 h-8 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </div>
                <div class="min-w-0">
                    <h4 class="text-sm font-semibold text-on-surface-strong truncate">
                        {{ __('Permissions for :name', ['name' => $manager->name]) }}
                    </h4>
                    <p class="text-xs text-on-surface mt-0.5">
                        {{ __('Toggle switches save automatically.') }}
                    </p>
                </div>
            </div>
            {{-- Close button --}}
            <button
                @click="closePermissions()"
                class="shrink-0 w-7 h-7 flex items-center justify-center rounded-full text-on-surface hover:bg-surface-alt dark:hover:bg-surface transition-colors duration-200"
                aria-label="{{ __('Close permissions panel') }}"
            >
                {{-- X icon (Lucide, xs=14) --}}
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
            </button>
        </div>

        {{-- Permission groups --}}
        <div class="divide-y divide-outline dark:divide-outline">
            @foreach(\App\Services\ManagerPermissionService::getPermissionGroups() as $groupLabel => $groupPermissions)
            <div class="px-4 sm:px-6 py-4">
                {{-- Group label --}}
                <p class="text-xs font-semibold text-on-surface uppercase tracking-wide mb-3">
                    {{ $groupLabel }}
                </p>

                <div class="space-y-4">
                    @foreach($groupPermissions as $perm)
                    @php $isGranted = $permissions[$perm['key']] ?? false; @endphp
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-on-surface-strong leading-snug">
                                {{ $perm['label'] }}
                            </p>
                            <p class="text-xs text-on-surface mt-0.5 leading-relaxed">
                                {{ $perm['description'] }}
                            </p>
                        </div>
                        {{-- Toggle switch: sets permission key then calls $action --}}
                        <div class="shrink-0 mt-0.5">
                            <button
                                type="button"
                                role="switch"
                                aria-checked="{{ $isGranted ? 'true' : 'false' }}"
                                aria-label="{{ $perm['label'] }}"
                                @click="
                                    permission = '{{ $perm['key'] }}';
                                    $action('{{ route('cook.managers.permissions.toggle', $manager) }}', { include: ['permission'] });
                                "
                                class="{{ $isGranted ? 'bg-primary' : 'bg-outline' }} relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-1"
                            >
                                <span class="{{ $isGranted ? 'translate-x-[18px]' : 'translate-x-0.5' }} inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform duration-200"></span>
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>

        {{-- Cook-reserved note --}}
        <div class="px-4 sm:px-6 py-3 bg-surface dark:bg-surface border-t border-outline dark:border-outline">
            <p class="text-xs text-on-surface flex items-start gap-1.5">
                {{-- Info icon (Lucide, xs=14) --}}
                <svg class="w-3.5 h-3.5 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                {{ __('Wallet, Theme, Team, Promo, Profile, and Settings sections are reserved for the cook.') }}
            </p>
        </div>
    </div>
</div>
@endfragment
