{{--
    Brand Info Step (Step 1)
    -----------------------
    F-072: Brand Info Step

    Collects brand name (en/fr), bio (en/fr), WhatsApp, phone, and social links.

    BR-117: Brand name required in both EN and FR.
    BR-118: If bio provided in one language, must be in both.
    BR-119: WhatsApp required, valid Cameroon phone format.
    BR-120: Phone optional but valid Cameroon format if provided.
    BR-121: Social links all optional.
    BR-122: Social link URLs must be valid if provided.
    BR-123: Brand name max 100 chars per language.
    BR-124: Bio max 1000 chars per language.
    BR-125: Step complete when name (both) and WhatsApp are saved.
    BR-126: All labels use __() localization.
--}}
<div
    x-data="{
        name_en: '{{ old('name_en', addslashes($tenant->name_en ?? '')) }}',
        name_fr: '{{ old('name_fr', addslashes($tenant->name_fr ?? '')) }}',
        description_en: {{ json_encode(old('description_en', $tenant->description_en ?? '')) }},
        description_fr: {{ json_encode(old('description_fr', $tenant->description_fr ?? '')) }},
        whatsapp: '{{ old('whatsapp', $tenant->whatsapp ?? '') }}',
        phone: '{{ old('phone', $tenant->phone ?? '') }}',
        social_facebook: '{{ old('social_facebook', $tenant->social_facebook ?? '') }}',
        social_instagram: '{{ old('social_instagram', $tenant->social_instagram ?? '') }}',
        social_tiktok: '{{ old('social_tiktok', $tenant->social_tiktok ?? '') }}',
        errors: {},
        saving: false
    }"
    x-sync="['name_en', 'name_fr', 'description_en', 'description_fr', 'whatsapp', 'phone', 'social_facebook', 'social_instagram', 'social_tiktok']"
>
    {{-- Step Header --}}
    <div class="mb-6">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                {{-- Lucide: store --}}
                <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"></path><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"></path><path d="M2 7h20"></path><path d="M22 7v3a2 2 0 0 1-2 2a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"></path></svg>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-on-surface-strong">{{ __('Brand Info') }}</h3>
                <p class="text-sm text-on-surface">{{ __('Tell your customers about your brand and how to reach you.') }}</p>
            </div>
        </div>
    </div>

    <form @submit.prevent="saving = true; $action('{{ url('/dashboard/setup/brand-info') }}')" class="space-y-6">

        {{-- Brand Name Section --}}
        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                {{-- Lucide: type --}}
                <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 7 4 4 20 4 20 7"></polyline><line x1="9" x2="15" y1="20" y2="20"></line><line x1="12" x2="12" y1="4" y2="20"></line></svg>
                {{ __('Brand Name') }}
                <span class="text-danger text-xs">*</span>
            </legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Brand Name EN --}}
                <div>
                    <label for="name_en" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('English') }}
                    </label>
                    <input
                        type="text"
                        id="name_en"
                        x-model="name_en"
                        x-name="name_en"
                        maxlength="100"
                        placeholder="{{ __('e.g. Chef Latifa\'s Kitchen') }}"
                        class="w-full px-3.5 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                    >
                    <p x-message="name_en" class="mt-1 text-xs text-danger"></p>
                </div>

                {{-- Brand Name FR --}}
                <div>
                    <label for="name_fr" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('French') }}
                    </label>
                    <input
                        type="text"
                        id="name_fr"
                        x-model="name_fr"
                        x-name="name_fr"
                        maxlength="100"
                        placeholder="{{ __('e.g. La Cuisine de Chef Latifa') }}"
                        class="w-full px-3.5 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                    >
                    <p x-message="name_fr" class="mt-1 text-xs text-danger"></p>
                </div>
            </div>
        </fieldset>

        {{-- Bio/Description Section --}}
        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                {{-- Lucide: file-text --}}
                <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path><path d="M14 2v4a2 2 0 0 0 2 2h4"></path><path d="M10 9H8"></path><path d="M16 13H8"></path><path d="M16 17H8"></path></svg>
                {{ __('Bio / Description') }}
                <span class="text-xs text-on-surface/60 font-normal">({{ __('optional') }})</span>
            </legend>
            <p class="text-xs text-on-surface/60">{{ __('If provided in one language, it must be provided in both.') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Bio EN --}}
                <div>
                    <label for="description_en" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('English') }}
                    </label>
                    <textarea
                        id="description_en"
                        x-model="description_en"
                        x-name="description_en"
                        maxlength="1000"
                        rows="4"
                        placeholder="{{ __('Describe your brand and what makes your food special...') }}"
                        class="w-full px-3.5 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 resize-y"
                    ></textarea>
                    <div class="flex items-center justify-between mt-1">
                        <p x-message="description_en" class="text-xs text-danger"></p>
                        <span class="text-xs text-on-surface/40" x-text="(description_en || '').length + '/1000'"></span>
                    </div>
                </div>

                {{-- Bio FR --}}
                <div>
                    <label for="description_fr" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('French') }}
                    </label>
                    <textarea
                        id="description_fr"
                        x-model="description_fr"
                        x-name="description_fr"
                        maxlength="1000"
                        rows="4"
                        placeholder="{{ __('Describe your brand and what makes your food special...') }}"
                        class="w-full px-3.5 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 resize-y"
                    ></textarea>
                    <div class="flex items-center justify-between mt-1">
                        <p x-message="description_fr" class="text-xs text-danger"></p>
                        <span class="text-xs text-on-surface/40" x-text="(description_fr || '').length + '/1000'"></span>
                    </div>
                </div>
            </div>
        </fieldset>

        {{-- Contact Info Section --}}
        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                {{-- Lucide: phone --}}
                <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                {{ __('Contact Information') }}
            </legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- WhatsApp --}}
                <div>
                    <label for="whatsapp" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('WhatsApp Number') }}
                        <span class="text-danger">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            {{-- WhatsApp icon --}}
                            <svg class="w-4 h-4 text-on-surface/40" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                        </div>
                        <span class="absolute inset-y-0 left-9 flex items-center text-sm text-on-surface/50 pointer-events-none">+237</span>
                        <input
                            type="tel"
                            id="whatsapp"
                            x-model="whatsapp"
                            x-name="whatsapp"
                            placeholder="6XX XXX XXX"
                            class="w-full pl-20 pr-3.5 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                        >
                    </div>
                    <p x-message="whatsapp" class="mt-1 text-xs text-danger"></p>
                </div>

                {{-- Phone --}}
                <div>
                    <label for="phone" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Phone Number') }}
                        <span class="text-xs text-on-surface/60 font-normal">({{ __('optional') }})</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            {{-- Lucide: phone --}}
                            <svg class="w-4 h-4 text-on-surface/40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                        </div>
                        <span class="absolute inset-y-0 left-9 flex items-center text-sm text-on-surface/50 pointer-events-none">+237</span>
                        <input
                            type="tel"
                            id="phone"
                            x-model="phone"
                            x-name="phone"
                            placeholder="6XX XXX XXX"
                            class="w-full pl-20 pr-3.5 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                        >
                    </div>
                    <p x-message="phone" class="mt-1 text-xs text-danger"></p>
                </div>
            </div>
        </fieldset>

        {{-- Social Links Section --}}
        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                {{-- Lucide: share-2 --}}
                <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" x2="15.42" y1="13.51" y2="17.49"></line><line x1="15.41" x2="8.59" y1="6.51" y2="10.49"></line></svg>
                {{ __('Social Links') }}
                <span class="text-xs text-on-surface/60 font-normal">({{ __('optional') }})</span>
            </legend>
            <div class="space-y-3">
                {{-- Facebook --}}
                <div>
                    <label for="social_facebook" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Facebook') }}
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-[#1877F2]" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </div>
                        <input
                            type="url"
                            id="social_facebook"
                            x-model="social_facebook"
                            x-name="social_facebook"
                            placeholder="https://facebook.com/yourpage"
                            class="w-full pl-10 pr-3.5 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                        >
                    </div>
                    <p x-message="social_facebook" class="mt-1 text-xs text-danger"></p>
                </div>

                {{-- Instagram --}}
                <div>
                    <label for="social_instagram" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('Instagram') }}
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-[#E4405F]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                        </div>
                        <input
                            type="url"
                            id="social_instagram"
                            x-model="social_instagram"
                            x-name="social_instagram"
                            placeholder="https://instagram.com/yourprofile"
                            class="w-full pl-10 pr-3.5 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                        >
                    </div>
                    <p x-message="social_instagram" class="mt-1 text-xs text-danger"></p>
                </div>

                {{-- TikTok --}}
                <div>
                    <label for="social_tiktok" class="block text-sm font-medium text-on-surface mb-1.5">
                        {{ __('TikTok') }}
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-on-surface" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                        </div>
                        <input
                            type="url"
                            id="social_tiktok"
                            x-model="social_tiktok"
                            x-name="social_tiktok"
                            placeholder="https://tiktok.com/@yourprofile"
                            class="w-full pl-10 pr-3.5 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface-strong placeholder-on-surface/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                        >
                    </div>
                    <p x-message="social_tiktok" class="mt-1 text-xs text-danger"></p>
                </div>
            </div>
        </fieldset>

        {{-- Save & Continue Button --}}
        <div class="flex items-center justify-end pt-2">
            <button
                type="submit"
                :disabled="$fetching()"
                class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span x-show="!$fetching()">{{ __('Save & Continue') }}</span>
                <span x-show="$fetching()" x-cloak class="inline-flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    {{ __('Saving...') }}
                </span>
                <svg x-show="!$fetching()" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
            </button>
        </div>
    </form>
</div>
