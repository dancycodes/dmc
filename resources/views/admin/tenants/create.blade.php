{{--
    Tenant Creation Form
    --------------------
    F-045: Admin creates new tenant (cook website).
    Uses Gale for form submission (no full page reload).

    BR-056: Subdomain uniqueness validated server-side
    BR-057: Subdomain format: lowercase letters, numbers, hyphens; 3-63 chars
    BR-058: Custom domain optional, valid hostname format
    BR-059: Custom domain must not conflict with platform domain
    BR-060: Both en/fr name and description required
    BR-061: Status defaults to active
    BR-062: Creation logged in activity log
    BR-063: Reserved subdomains rejected

    UI/UX: Single column mobile, two-column desktop (en/fr side by side)
    Subdomain live preview, custom domain help tooltip, status pill toggle
--}}
@extends('layouts.admin')

@section('title', __('Create Tenant'))
@section('page-title', __('Create Tenant'))

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb --}}
    <x-admin.breadcrumb :items="[
        ['label' => __('Tenants'), 'url' => '/vault-entry/tenants'],
        ['label' => __('Create')]
    ]" />

    {{-- Form --}}
    <div
        x-data="{
            name_en: '',
            name_fr: '',
            subdomain: '',
            custom_domain: '',
            description_en: '',
            description_fr: '',
            is_active: true,
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
        <form @submit.prevent="$action('{{ url('/vault-entry/tenants') }}')" class="space-y-8">

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
                                :title="__('DNS setup information')"
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

            {{-- Status --}}
            <div class="bg-surface-alt dark:bg-surface-alt rounded-lg border border-outline dark:border-outline p-4 sm:p-6">
                <h3 class="text-base font-semibold text-on-surface-strong mb-4">
                    {{ __('Status') }}
                </h3>
                <div class="flex items-center gap-4">
                    <button
                        type="button"
                        @click="is_active = !is_active"
                        :class="is_active
                            ? 'bg-success'
                            : 'bg-outline dark:bg-outline'"
                        class="relative w-12 h-7 rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        role="switch"
                        :aria-checked="is_active ? 'true' : 'false'"
                        :aria-label="is_active ? '{{ __('Active') }}' : '{{ __('Inactive') }}'"
                    >
                        <span
                            :class="is_active ? 'translate-x-6' : 'translate-x-1'"
                            class="block w-5 h-5 rounded-full bg-white shadow-sm transform transition-transform duration-200"
                        ></span>
                    </button>
                    <span
                        class="text-sm font-medium"
                        :class="is_active ? 'text-success' : 'text-on-surface/60'"
                        x-text="is_active ? '{{ __('Active') }}' : '{{ __('Inactive') }}'"
                    ></span>
                    <p class="text-xs text-on-surface/50">
                        {{ __('Active tenants are publicly accessible. Inactive tenants are hidden.') }}
                    </p>
                </div>
            </div>

            {{-- Submit --}}
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
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                            {{ __('Create Tenant') }}
                        </span>
                    </template>
                    <template x-if="$fetching()">
                        <span class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            {{ __('Creating...') }}
                        </span>
                    </template>
                </button>

                <a
                    href="{{ url('/vault-entry/tenants') }}"
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
