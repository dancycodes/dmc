{{--
    Tenant Edit & Status Toggle
    ----------------------------
    F-048: Admin edits tenant details and toggles active status.
    Uses Gale for form submission (no full page reload).

    BR-075: Deactivated tenant shows "temporarily unavailable" page
    BR-076: Deactivation does not delete any data
    BR-078: Subdomain changes follow same validation rules as creation
    BR-079: Uniqueness checks exclude current tenant's own values
    BR-080: All edits and status changes recorded in activity log
    BR-081: Deactivation requires explicit confirmation; activation does not

    UI/UX: Pre-filled form, status toggle at top, deactivation confirmation modal,
    save button with loading state, cancel returns to detail page
--}}
@extends('layouts.admin')

@section('title', __('Edit Tenant') . ' — ' . $tenant->name)
@section('page-title', __('Edit Tenant'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb: Admin > Tenants > Tenant Name > Edit --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Tenants'), 'url' => '/vault-entry/tenants'],
        ['label' => $tenant->name, 'url' => '/vault-entry/tenants/' . $tenant->slug],
        ['label' => __('Edit')],
    ]" />

    {{-- Status Toggle Section (BR-081: separate from text fields, prominent at top) --}}
    <div
        x-data="{
            isActive: {{ $tenant->is_active ? 'true' : 'false' }},
            showDeactivateModal: false,
            toggling: false,
            confirmDeactivate() {
                if (this.isActive) {
                    this.showDeactivateModal = true;
                } else {
                    this.executeToggle();
                }
            },
            executeToggle() {
                this.showDeactivateModal = false;
                this.toggling = true;
                $action('{{ url('/vault-entry/tenants/' . $tenant->slug . '/toggle-status') }}');
            }
        }"
        class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6"
    >
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-on-surface-strong">{{ __('Tenant Status') }}</h3>
                <p class="text-sm text-on-surface mt-1">
                    {{ __('Deactivating a tenant will show a "temporarily unavailable" page on their website.') }}
                </p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <button
                    type="button"
                    @click="confirmDeactivate()"
                    :disabled="toggling"
                    :class="isActive
                        ? 'bg-success'
                        : 'bg-outline dark:bg-outline'"
                    class="relative w-12 h-7 rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isActive ? 'true' : 'false'"
                    :aria-label="isActive ? '{{ __('Active') }}' : '{{ __('Inactive') }}'"
                >
                    <span
                        :class="isActive ? 'translate-x-6' : 'translate-x-1'"
                        class="block w-5 h-5 rounded-full bg-white shadow-sm transform transition-transform duration-200"
                    ></span>
                </button>
                <span
                    class="text-sm font-semibold min-w-[70px]"
                    :class="isActive ? 'text-success' : 'text-on-surface/60'"
                    x-text="isActive ? '{{ __('Active') }}' : '{{ __('Inactive') }}'"
                ></span>
                <template x-if="toggling">
                    <svg class="animate-spin h-4 w-4 text-on-surface/50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </template>
            </div>
        </div>

        {{-- BR-081: Deactivation confirmation modal --}}
        <template x-teleport="body">
            <div
                x-show="showDeactivateModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-[70] flex items-center justify-center p-4"
                x-cloak
                @keydown.escape.window="showDeactivateModal = false"
            >
                {{-- Backdrop --}}
                <div class="absolute inset-0 bg-black/50" @click="showDeactivateModal = false"></div>

                {{-- Modal --}}
                <div
                    x-show="showDeactivateModal"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="relative bg-surface dark:bg-surface rounded-xl border border-outline dark:border-outline shadow-dropdown p-6 max-w-md w-full"
                    @click.stop
                >
                    {{-- Warning icon --}}
                    <div class="w-12 h-12 mx-auto rounded-full bg-warning-subtle flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                    </div>

                    <h3 class="text-lg font-semibold text-on-surface-strong text-center mb-2">
                        {{ __('Deactivate Tenant?') }}
                    </h3>
                    <p class="text-sm text-on-surface text-center mb-6">
                        {{ __('Deactivating this tenant will show a "temporarily unavailable" page on their website. Visitors will not be able to access the tenant site. You can reactivate at any time.') }}
                    </p>

                    <div class="flex gap-3">
                        <button
                            type="button"
                            @click="showDeactivateModal = false"
                            class="flex-1 h-10 px-4 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200
                                   focus:outline-none focus:ring-2 focus:ring-outline focus:ring-offset-2"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="button"
                            @click="executeToggle()"
                            class="flex-1 h-10 px-4 text-sm rounded-lg font-semibold bg-danger hover:bg-danger/90 text-on-danger transition-all duration-200
                                   focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2"
                        >
                            {{ __('Deactivate') }}
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Edit Form --}}
    <div
        x-data="{
            name_en: {{ json_encode($tenant->name_en) }},
            name_fr: {{ json_encode($tenant->name_fr) }},
            subdomain: {{ json_encode($tenant->slug) }},
            custom_domain: {{ json_encode($tenant->custom_domain ?? '') }},
            description_en: {{ json_encode($tenant->description_en ?? '') }},
            description_fr: {{ json_encode($tenant->description_fr ?? '') }},
            messages: {},
            get subdomainPreview() {
                return this.subdomain
                    ? this.subdomain.toLowerCase().replace(/[^a-z0-9-]/g, '') + '.{{ $mainDomain }}'
                    : '{{ __('your-subdomain') }}.{{ $mainDomain }}';
            },
            get subdomainHint() {
                const s = this.subdomain.toLowerCase().replace(/[^a-z0-9-]/g, '');
                if (s.length > 0 && s.length < 3) return '{{ __('Subdomain must be at least 3 characters.') }}';
                if (/--/.test(s)) return '{{ __('Subdomain must not contain consecutive hyphens.') }}';
                if (s.length > 0 && !/^[a-z0-9]/.test(s)) return '{{ __('Subdomain must start with a letter or number.') }}';
                if (s.length > 0 && !/[a-z0-9]$/.test(s)) return '{{ __('Subdomain must end with a letter or number.') }}';
                return '';
            },
            showDnsHelp: false
        }"
        x-sync
        class="max-w-4xl"
    >
        <form @submit.prevent="$action('{{ url('/vault-entry/tenants/' . $tenant->slug) }}')" class="space-y-8">

            {{-- Tenant Names (side by side on desktop) --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
                <h3 class="text-base font-semibold text-on-surface-strong mb-4">
                    {{ __('Tenant Name') }}
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                    {{-- Name EN --}}
                    <div>
                        <label for="name_en" class="block text-sm font-medium text-on-surface mb-1.5">
                            {{ __('Name (English)') }} <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            id="name_en"
                            x-model="name_en"
                            x-name="name_en"
                            placeholder="{{ __("e.g., Chef Amara's Kitchen") }}"
                            maxlength="255"
                            class="w-full h-10 px-3 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong dark:text-on-surface-strong placeholder:text-on-surface/50
                                   focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200"
                        >
                        <p x-message="name_en" class="mt-1 text-sm text-danger"></p>
                    </div>

                    {{-- Name FR --}}
                    <div>
                        <label for="name_fr" class="block text-sm font-medium text-on-surface mb-1.5">
                            {{ __('Name (French)') }} <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            id="name_fr"
                            x-model="name_fr"
                            x-name="name_fr"
                            placeholder="{{ __('e.g., La Cuisine de Chef Amara') }}"
                            maxlength="255"
                            class="w-full h-10 px-3 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong dark:text-on-surface-strong placeholder:text-on-surface/50
                                   focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200"
                        >
                        <p x-message="name_fr" class="mt-1 text-sm text-danger"></p>
                    </div>
                </div>
            </div>

            {{-- Subdomain & Custom Domain --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
                <h3 class="text-base font-semibold text-on-surface-strong mb-4">
                    {{ __('Domain Configuration') }}
                </h3>

                <div class="space-y-5">
                    {{-- Subdomain --}}
                    <div>
                        <label for="subdomain" class="block text-sm font-medium text-on-surface mb-1.5">
                            {{ __('Subdomain') }} <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            id="subdomain"
                            x-model="subdomain"
                            x-name="subdomain"
                            placeholder="{{ __('e.g., chef-amara') }}"
                            maxlength="63"
                            @input="subdomain = $el.value.toLowerCase().replace(/[^a-z0-9-]/g, '')"
                            class="w-full h-10 px-3 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong dark:text-on-surface-strong placeholder:text-on-surface/50
                                   focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200 font-mono"
                        >
                        {{-- Live preview --}}
                        <div class="mt-2 flex items-center gap-2">
                            <svg class="w-4 h-4 text-on-surface/60 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M2 12h20"></path><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                            <span class="text-sm font-mono" :class="subdomain ? 'text-primary' : 'text-on-surface/50'" x-text="subdomainPreview"></span>
                        </div>
                        {{-- Client-side hint --}}
                        <p x-show="subdomainHint" x-text="subdomainHint" class="mt-1 text-sm text-warning" x-cloak></p>
                        {{-- Server validation error --}}
                        <p x-message="subdomain" class="mt-1 text-sm text-danger"></p>
                    </div>

                    {{-- Custom Domain --}}
                    <div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <label for="custom_domain" class="block text-sm font-medium text-on-surface">
                                {{ __('Custom Domain') }}
                            </label>
                            <span class="text-xs bg-surface dark:bg-surface px-2 py-0.5 rounded-full text-on-surface/60 border border-outline dark:border-outline">
                                {{ __('Optional') }}
                            </span>
                            {{-- Help tooltip --}}
                            <button
                                type="button"
                                @click="showDnsHelp = !showDnsHelp"
                                class="w-5 h-5 rounded-full bg-info-subtle text-info flex items-center justify-center hover:bg-info/20 transition-colors duration-200"
                                title="{{ __('DNS setup information') }}"
                            >
                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><path d="M12 17h.01"></path></svg>
                            </button>
                        </div>
                        <input
                            type="text"
                            id="custom_domain"
                            x-model="custom_domain"
                            x-name="custom_domain"
                            placeholder="{{ __('e.g., chefamara.cm') }}"
                            maxlength="255"
                            class="w-full h-10 px-3 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong dark:text-on-surface-strong placeholder:text-on-surface/50
                                   focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200 font-mono"
                        >
                        {{-- DNS help panel --}}
                        <div
                            x-show="showDnsHelp"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-cloak
                            class="mt-2 p-3 rounded-lg bg-info-subtle dark:bg-info-subtle border border-info/30 text-sm text-on-surface"
                        >
                            <p class="font-medium text-info mb-1">{{ __('DNS Configuration Required') }}</p>
                            <p>{{ __('The tenant owner must configure their DNS to point the custom domain to the platform server. A CNAME record pointing to :domain is recommended.', ['domain' => $mainDomain]) }}</p>
                        </div>
                        <p x-message="custom_domain" class="mt-1 text-sm text-danger"></p>
                    </div>
                </div>
            </div>

            {{-- Descriptions (side by side on desktop) --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
                <h3 class="text-base font-semibold text-on-surface-strong mb-4">
                    {{ __('Description') }}
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                    {{-- Description EN --}}
                    <div>
                        <label for="description_en" class="block text-sm font-medium text-on-surface mb-1.5">
                            {{ __('Description (English)') }} <span class="text-danger">*</span>
                        </label>
                        <textarea
                            id="description_en"
                            x-model="description_en"
                            x-name="description_en"
                            rows="4"
                            maxlength="5000"
                            placeholder="{{ __('Describe this tenant in English...') }}"
                            class="w-full px-3 py-2.5 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong dark:text-on-surface-strong placeholder:text-on-surface/50
                                   focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200 resize-y min-h-[100px]"
                        ></textarea>
                        <div class="flex items-center justify-between mt-1">
                            <p x-message="description_en" class="text-sm text-danger"></p>
                            <span class="text-xs text-on-surface/50" x-text="description_en.length + '/5000'"></span>
                        </div>
                    </div>

                    {{-- Description FR --}}
                    <div>
                        <label for="description_fr" class="block text-sm font-medium text-on-surface mb-1.5">
                            {{ __('Description (French)') }} <span class="text-danger">*</span>
                        </label>
                        <textarea
                            id="description_fr"
                            x-model="description_fr"
                            x-name="description_fr"
                            rows="4"
                            maxlength="5000"
                            placeholder="{{ __('Describe this tenant in French...') }}"
                            class="w-full px-3 py-2.5 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong dark:text-on-surface-strong placeholder:text-on-surface/50
                                   focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200 resize-y min-h-[100px]"
                        ></textarea>
                        <div class="flex items-center justify-between mt-1">
                            <p x-message="description_fr" class="text-sm text-danger"></p>
                            <span class="text-xs text-on-surface/50" x-text="description_fr.length + '/5000'"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Submit & Cancel --}}
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="$fetching()"
                    :class="$fetching() ? 'opacity-80 cursor-wait' : 'hover:bg-primary-hover active:scale-[0.98]'"
                    class="h-10 px-6 text-sm rounded-lg font-semibold bg-primary text-on-primary transition-all duration-200 inline-flex items-center gap-2
                           focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    <template x-if="!$fetching()">
                        <span class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                            {{ __('Save Changes') }}
                        </span>
                    </template>
                    <template x-if="$fetching()">
                        <span class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            {{ __('Saving...') }}
                        </span>
                    </template>
                </button>

                <a
                    href="{{ url('/vault-entry/tenants/' . $tenant->slug) }}"
                    class="h-10 px-5 text-sm rounded-lg font-semibold border border-outline text-on-surface hover:bg-surface-alt transition-all duration-200 inline-flex items-center
                           focus:outline-none focus:ring-2 focus:ring-outline focus:ring-offset-2"
                >
                    {{ __('Cancel') }}
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
