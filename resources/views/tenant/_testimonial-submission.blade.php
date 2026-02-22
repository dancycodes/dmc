{{--
    F-180: Testimonial Submission Form
    BR-426: Only eligible clients (completed orders) can submit
    BR-427: One submission per client per tenant
    BR-428: Max 1,000 characters
    BR-429: Submitted testimonials are pending; require cook approval
    BR-430: Cannot edit after submission
    BR-431: Available on the cook's tenant landing page
    BR-432: Unauthenticated users see login prompt
    BR-433: Ineligible clients see disabled CTA with explanation
    BR-436: All user-facing text uses __() localization

    Variables expected:
        $testimonialContext: array{isAuthenticated: bool, isEligible: bool, existingTestimonial: Testimonial|null}
        $tenant: Tenant
--}}

<div
    id="testimonial-form-section"
    x-data="{
        showTestimonialModal: false,
        testimonialText: '',
        testimonialCharCount: 0,
        testimonialSubmitted: {{ $testimonialContext['existingTestimonial'] ? 'true' : 'false' }},

        openModal() {
            this.showTestimonialModal = true;
            this.$nextTick(() => {
                const ta = document.getElementById('testimonial-textarea');
                if (ta) ta.focus();
            });
        },
        closeModal() {
            this.showTestimonialModal = false;
        },
        updateCount() {
            this.testimonialCharCount = this.testimonialText.length;
        }
    }"
    x-sync="['testimonialText', 'testimonialCharCount', 'testimonialSubmitted', 'showTestimonialModal']"
>

    {{-- ======================================================== --}}
    {{-- STATE: Already submitted (BR-430 — cannot resubmit)      --}}
    {{-- ======================================================== --}}
    @if($testimonialContext['existingTestimonial'])
        <div class="flex flex-col items-center text-center gap-4 py-6">
            {{-- Status badge --}}
            <div class="flex items-center gap-2">
                @if($testimonialContext['existingTestimonial']->isPending())
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-warning-subtle text-warning border border-warning/30">
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4l3 3"></path></svg>
                        {{ __('Pending Review') }}
                    </span>
                @elseif($testimonialContext['existingTestimonial']->isApproved())
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-success-subtle text-success border border-success/30">
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        {{ __('Approved') }}
                    </span>
                @endif
            </div>

            <p class="text-sm text-on-surface/70 dark:text-on-surface/70 italic">{{ __("You've already shared your experience with this cook.") }}</p>

            {{-- Show the submitted text --}}
            <div class="max-w-xl w-full bg-surface dark:bg-surface border border-outline dark:border-outline rounded-xl p-5 text-left shadow-card">
                {{-- Opening quote --}}
                <svg class="w-8 h-8 text-primary/20 dark:text-primary/20 mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M11.192 15.757c0-.88-.23-1.618-.69-2.217-.326-.412-.768-.683-1.327-.812-.55-.128-1.07-.137-1.54-.028-.16-.95.1-1.95.78-3-.86.248-1.49.64-1.9 1.18-.41.54-.63 1.14-.63 1.8 0 .84.29 1.54.87 2.1.57.56 1.27.84 2.1.84.8 0 1.48-.28 2.04-.84.57-.57.85-1.28.85-2.16.01-.05.01-.1.01-.15zm7.43 0c0-.88-.23-1.618-.69-2.217-.326-.42-.77-.692-1.327-.812-.55-.128-1.07-.137-1.54-.028-.16-.95.1-1.95.78-3-.86.248-1.49.64-1.9 1.18-.41.54-.63 1.14-.63 1.8 0 .84.29 1.54.87 2.1.57.56 1.27.84 2.1.84.8 0 1.48-.28 2.04-.84.57-.57.85-1.28.85-2.16.01-.05.01-.1.01-.15z"/>
                </svg>
                <p class="text-on-surface dark:text-on-surface leading-relaxed italic text-sm">{{ $testimonialContext['existingTestimonial']->text }}</p>
            </div>
        </div>

    {{-- ======================================================== --}}
    {{-- STATE: Not submitted yet — render the CTA               --}}
    {{-- ======================================================== --}}
    @else
        {{-- Already submitted reactively (post-submit state) --}}
        <div x-show="testimonialSubmitted" x-cloak class="flex flex-col items-center text-center gap-3 py-4">
            <div class="w-12 h-12 rounded-full bg-success-subtle flex items-center justify-center mx-auto">
                <svg class="w-6 h-6 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
            </div>
            <p class="text-sm font-semibold text-on-surface-strong dark:text-on-surface-strong">{{ __('Thank you! Your testimonial has been submitted for review.') }}</p>
        </div>

        {{-- Pre-submit CTA (hidden once submitted) --}}
        <div x-show="!testimonialSubmitted" class="flex flex-col items-center text-center gap-4 py-4">
            <p class="text-on-surface/70 dark:text-on-surface/70 max-w-md">
                {{ __('Have you ordered from us? We would love to hear about your experience!') }}
            </p>

            @if(! $testimonialContext['isAuthenticated'])
                {{-- BR-432: Unauthenticated — show login prompt --}}
                <a
                    href="{{ route('login') }}"
                    x-navigate-skip
                    class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-primary text-on-primary font-semibold text-sm hover:bg-primary-hover transition-colors shadow-sm"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
                    {{ __('Log in to share your experience') }}
                </a>

            @elseif(! $testimonialContext['isEligible'])
                {{-- BR-433: Authenticated but no completed orders — disabled with tooltip --}}
                <div class="relative group">
                    <button
                        type="button"
                        disabled
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-surface-alt dark:bg-surface-alt text-on-surface/40 dark:text-on-surface/40 font-semibold text-sm cursor-not-allowed border border-outline dark:border-outline"
                        aria-disabled="true"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        {{ __('Share Your Experience') }}
                    </button>
                    {{-- Tooltip --}}
                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 bg-on-surface dark:bg-on-surface text-surface dark:text-surface text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none shadow-dropdown">
                        {{ __('Complete an order to share your experience.') }}
                        <div class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-x-4 border-x-transparent border-t-4 border-t-on-surface dark:border-t-on-surface"></div>
                    </div>
                </div>

            @else
                {{-- BR-431: Eligible client — show submission button --}}
                <button
                    type="button"
                    @click="openModal()"
                    class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-primary text-on-primary font-semibold text-sm hover:bg-primary-hover transition-colors shadow-sm"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    {{ __('Share Your Experience') }}
                </button>
            @endif
        </div>

        {{-- ======================================================== --}}
        {{-- MODAL: Testimonial submission form (eligible clients)     --}}
        {{-- ======================================================== --}}
        @if($testimonialContext['isAuthenticated'] && $testimonialContext['isEligible'])
            {{-- Modal backdrop --}}
            <div
                x-show="showTestimonialModal"
                x-cloak
                x-transition:enter="transition-opacity ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-black/50 dark:bg-black/60 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
                @click.self="closeModal()"
                role="dialog"
                aria-modal="true"
                aria-labelledby="testimonial-modal-title"
            >
                {{-- Modal panel --}}
                <div
                    x-show="showTestimonialModal"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                    class="w-full sm:max-w-lg bg-surface dark:bg-surface rounded-t-2xl sm:rounded-2xl shadow-dropdown border border-outline dark:border-outline overflow-hidden"
                    @keydown.escape.window="closeModal()"
                >
                    {{-- Modal header --}}
                    <div class="flex items-center justify-between px-5 py-4 border-b border-outline dark:border-outline">
                        <h3 id="testimonial-modal-title" class="text-base font-semibold text-on-surface-strong dark:text-on-surface-strong font-display">
                            {{ __('Share Your Experience') }}
                        </h3>
                        <button
                            type="button"
                            @click="closeModal()"
                            class="p-1.5 rounded-lg text-on-surface/60 hover:text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors"
                            aria-label="{{ __('Close') }}"
                        >
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>

                    {{-- Modal body --}}
                    <div class="p-5">
                        <p class="text-sm text-on-surface dark:text-on-surface mb-4">
                            {{ __('Tell others about your experience ordering from :name.', ['name' => $tenant->name]) }}
                        </p>

                        <div>
                            <label for="testimonial-textarea" class="block text-sm font-medium text-on-surface-strong dark:text-on-surface-strong mb-2">
                                {{ __('Your Testimonial') }}
                                <span class="text-danger">*</span>
                            </label>

                            <textarea
                                id="testimonial-textarea"
                                x-model="testimonialText"
                                x-name="testimonialText"
                                @input="updateCount()"
                                rows="5"
                                maxlength="{{ \App\Models\Testimonial::MAX_TEXT_LENGTH }}"
                                placeholder="{{ __('Share your experience in detail. What did you love most?') }}"
                                class="w-full rounded-xl border border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt text-on-surface dark:text-on-surface placeholder:text-on-surface/40 dark:placeholder:text-on-surface/40 px-4 py-3 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary dark:focus:border-primary transition-colors"
                                aria-describedby="testimonial-char-count testimonial-error"
                            ></textarea>

                            {{-- BR-428: Character counter with live feedback --}}
                            <div class="flex items-center justify-between mt-1.5">
                                <p
                                    x-message="testimonialText"
                                    id="testimonial-error"
                                    class="text-xs text-danger"
                                ></p>
                                <p
                                    id="testimonial-char-count"
                                    :class="testimonialCharCount > {{ \App\Models\Testimonial::MAX_TEXT_LENGTH }} ? 'text-danger font-semibold' : (testimonialCharCount > {{ (int)(\App\Models\Testimonial::MAX_TEXT_LENGTH * 0.9) }} ? 'text-warning' : 'text-on-surface/50 dark:text-on-surface/50')"
                                    class="text-xs ml-auto shrink-0"
                                    aria-live="polite"
                                >
                                    <span x-text="testimonialCharCount"></span>/{{ \App\Models\Testimonial::MAX_TEXT_LENGTH }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Modal footer --}}
                    <div class="flex items-center justify-end gap-3 px-5 py-4 border-t border-outline dark:border-outline bg-surface-alt/50 dark:bg-surface-alt/50">
                        <button
                            type="button"
                            @click="closeModal()"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-on-surface dark:text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt border border-outline dark:border-outline transition-colors"
                        >
                            {{ __('Cancel') }}
                        </button>

                        {{-- BR-428: Submit disabled when over character limit or empty --}}
                        <button
                            type="button"
                            @click="$action('{{ route('tenant.testimonial.submit') }}', { include: ['testimonialText'] })"
                            :disabled="testimonialCharCount === 0 || testimonialCharCount > {{ \App\Models\Testimonial::MAX_TEXT_LENGTH }} || $fetching()"
                            :class="(testimonialCharCount === 0 || testimonialCharCount > {{ \App\Models\Testimonial::MAX_TEXT_LENGTH }}) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-primary-hover'"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-primary text-on-primary font-semibold text-sm transition-colors disabled:pointer-events-none"
                        >
                            <span x-show="!$fetching()">{{ __('Submit Testimonial') }}</span>
                            <span x-show="$fetching()" class="flex items-center gap-2">
                                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" opacity="0.75"></path></svg>
                                {{ __('Submitting...') }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
