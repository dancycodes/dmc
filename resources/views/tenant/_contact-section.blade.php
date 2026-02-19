{{--
    F-134: WhatsApp Contact & Social Links — Footer Contact Section
    BR-207: Phone number in international Cameroon format (+237 6XX XXX XXX)
    BR-208: Click-to-call on mobile; copy-to-clipboard on desktop with feedback toast
    BR-209: Social media links open in new tab
    BR-210: Only configured social links displayed
    BR-211: Supported social platforms: Facebook, Instagram, TikTok
    BR-213: All text labels localized via __()
--}}

@php
    $hasContact = $cookProfile['whatsapp'] || $cookProfile['phone'];
    $hasSocial = $cookProfile['socialLinks']['facebook'] || $cookProfile['socialLinks']['instagram'] || $cookProfile['socialLinks']['tiktok'];
    $hasAnyContactInfo = $hasContact || $hasSocial;
@endphp

@if($hasAnyContactInfo)
    <div class="mb-8">
        {{-- Section header --}}
        <h3 class="text-lg font-display font-bold text-on-surface-strong mb-6">
            {{ __('Get in Touch') }}
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
            {{-- Contact Information --}}
            @if($hasContact)
                <div>
                    <h4 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wider mb-4">
                        {{ __('Contact') }}
                    </h4>
                    <div class="space-y-3">
                        {{-- WhatsApp link --}}
                        @if($cookProfile['whatsapp'])
                            @php
                                $cleanWhatsApp = preg_replace('/[^0-9]/', '', $cookProfile['whatsapp']);
                                $waMessage = urlencode(__('Hi :name, I\'m interested in ordering from DancyMeals!', ['name' => $cookProfile['name']]));
                            @endphp
                            <a
                                href="https://wa.me/{{ $cleanWhatsApp }}?text={{ $waMessage }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="group flex items-center gap-3 text-sm text-on-surface hover:text-[#25D366] transition-colors duration-200"
                            >
                                {{-- WhatsApp icon --}}
                                <span class="w-9 h-9 rounded-lg bg-surface dark:bg-surface border border-outline dark:border-outline flex items-center justify-center text-on-surface group-hover:text-[#25D366] group-hover:border-[#25D366] transition-all duration-200">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                    </svg>
                                </span>
                                <span>{{ __('Chat on WhatsApp') }}</span>
                            </a>
                        @endif

                        {{-- Phone number: click-to-call on mobile, copy on desktop --}}
                        @if($cookProfile['phone'])
                            @php
                                // BR-207: Format phone in international Cameroon format
                                $rawPhone = $cookProfile['phone'];
                                $cleanPhone = preg_replace('/[^0-9+]/', '', $rawPhone);
                                // Ensure +237 prefix for display
                                if (str_starts_with($cleanPhone, '+237')) {
                                    $displayPhone = $cleanPhone;
                                } elseif (str_starts_with($cleanPhone, '237')) {
                                    $displayPhone = '+' . $cleanPhone;
                                } else {
                                    $displayPhone = '+237 ' . ltrim($cleanPhone, '0');
                                }
                                // Format for display: +237 6XX XXX XXX
                                $digits = preg_replace('/[^0-9]/', '', $displayPhone);
                                if (strlen($digits) === 12 && str_starts_with($digits, '237')) {
                                    $formattedPhone = '+237 ' . substr($digits, 3, 3) . ' ' . substr($digits, 6, 3) . ' ' . substr($digits, 9, 3);
                                } else {
                                    $formattedPhone = $displayPhone;
                                }
                                $telLink = 'tel:' . $cleanPhone;
                            @endphp
                            <div
                                x-data="{
                                    copied: false,
                                    isMobile: 'ontouchstart' in window || navigator.maxTouchPoints > 0,
                                    handleClick(event) {
                                        if (!this.isMobile) {
                                            event.preventDefault();
                                            navigator.clipboard.writeText('{{ $cleanPhone }}').then(() => {
                                                this.copied = true;
                                                setTimeout(() => this.copied = false, 2000);
                                            });
                                        }
                                        /* On mobile, the default tel: link behavior triggers the call */
                                    }
                                }"
                                class="relative"
                            >
                                <a
                                    href="{{ $telLink }}"
                                    @click="handleClick($event)"
                                    class="group flex items-center gap-3 text-sm text-on-surface hover:text-primary transition-colors duration-200"
                                >
                                    {{-- Phone icon --}}
                                    <span class="w-9 h-9 rounded-lg bg-surface dark:bg-surface border border-outline dark:border-outline flex items-center justify-center text-on-surface group-hover:text-primary group-hover:border-primary transition-all duration-200">
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                    </span>
                                    <span>{{ $formattedPhone }}</span>
                                </a>

                                {{-- BR-208: Copy-to-clipboard success toast (desktop only) --}}
                                <div
                                    x-show="copied"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100 translate-y-0"
                                    x-transition:leave-end="opacity-0 translate-y-1"
                                    class="absolute left-0 -top-10 bg-success text-on-success text-xs font-medium px-3 py-1.5 rounded-lg shadow-dropdown whitespace-nowrap"
                                    x-cloak
                                >
                                    {{ __('Phone number copied!') }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Social Media Links --}}
            {{-- BR-210: Only configured social links displayed --}}
            {{-- BR-211: Supported: Facebook, Instagram, TikTok --}}
            @if($hasSocial)
                <div>
                    <h4 class="text-sm font-semibold text-on-surface-strong uppercase tracking-wider mb-4">
                        {{ __('Follow Us') }}
                    </h4>
                    <div class="flex gap-3">
                        {{-- Facebook --}}
                        @if($cookProfile['socialLinks']['facebook'])
                            <a
                                href="{{ $cookProfile['socialLinks']['facebook'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="group relative w-10 h-10 rounded-lg bg-surface dark:bg-surface border border-outline dark:border-outline flex items-center justify-center text-on-surface hover:text-[#1877F2] hover:border-[#1877F2] transition-all duration-200"
                                title="Facebook"
                            >
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
                                {{-- Tooltip --}}
                                <span class="absolute -top-9 left-1/2 -translate-x-1/2 bg-on-surface-strong text-surface text-xs font-medium px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap pointer-events-none">
                                    Facebook
                                </span>
                            </a>
                        @endif

                        {{-- Instagram --}}
                        @if($cookProfile['socialLinks']['instagram'])
                            <a
                                href="{{ $cookProfile['socialLinks']['instagram'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="group relative w-10 h-10 rounded-lg bg-surface dark:bg-surface border border-outline dark:border-outline flex items-center justify-center text-on-surface hover:text-[#E4405F] hover:border-[#E4405F] transition-all duration-200"
                                title="Instagram"
                            >
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"></line></svg>
                                {{-- Tooltip --}}
                                <span class="absolute -top-9 left-1/2 -translate-x-1/2 bg-on-surface-strong text-surface text-xs font-medium px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap pointer-events-none">
                                    Instagram
                                </span>
                            </a>
                        @endif

                        {{-- TikTok --}}
                        @if($cookProfile['socialLinks']['tiktok'])
                            <a
                                href="{{ $cookProfile['socialLinks']['tiktok'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="group relative w-10 h-10 rounded-lg bg-surface dark:bg-surface border border-outline dark:border-outline flex items-center justify-center text-on-surface hover:text-on-surface-strong hover:border-on-surface-strong transition-all duration-200"
                                title="TikTok"
                            >
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.89 2.89 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 0 0-.79-.05A6.34 6.34 0 0 0 3.14 15.67 6.34 6.34 0 0 0 9.48 22a6.34 6.34 0 0 0 6.34-6.34V9.39a8.16 8.16 0 0 0 3.77.92V6.69z"></path></svg>
                                {{-- Tooltip --}}
                                <span class="absolute -top-9 left-1/2 -translate-x-1/2 bg-on-surface-strong text-surface text-xs font-medium px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap pointer-events-none">
                                    TikTok
                                </span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
