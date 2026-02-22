{{--
    F-182: Approved Testimonials Display
    -------------------------------------
    Displays approved testimonials in a carousel (mobile) or grid (desktop).

    BR-446: Only approved testimonials are displayed.
    BR-447: Maximum 10 testimonials displayed.
    BR-448: If more than 10 approved, cook selects featured ones (from F-181 dashboard).
    BR-449: Each card shows: client name (first name + last initial), date, text.
    BR-450: Carousel on mobile (swipeable, dots), grid on desktop (2-3 cols).
    BR-451: "Submit Testimonial" CTA visible for eligible authenticated clients.
    BR-452: CTA is disabled for ineligible clients, login prompt for unauthenticated.
    BR-453: Testimonials are tenant-scoped.
    BR-454: All user-facing text uses __() localization.

    Variables expected:
        $testimonialsDisplay: array{
            testimonials: Collection,
            hasTestimonials: bool,
            totalApproved: int,
            hasFeaturedSelection: bool
        }
        $testimonialContext: array{isAuthenticated: bool, isEligible: bool, existingTestimonial: Testimonial|null}
        $tenant: Tenant
--}}

@php
    $testimonials = $testimonialsDisplay['testimonials'];
    $hasTestimonials = $testimonialsDisplay['hasTestimonials'];
    $testimonialCount = $testimonials->count();
@endphp

<div
    x-data="{
        currentSlide: 0,
        totalSlides: {{ $testimonialCount }},
        isDragging: false,
        startX: 0,
        goToSlide(index) {
            this.currentSlide = ((index % this.totalSlides) + this.totalSlides) % this.totalSlides;
        },
        nextSlide() {
            this.goToSlide(this.currentSlide + 1);
        },
        prevSlide() {
            this.goToSlide(this.currentSlide - 1);
        },
        handleTouchStart(e) {
            this.startX = e.touches[0].clientX;
            this.isDragging = true;
        },
        handleTouchEnd(e) {
            if (!this.isDragging) return;
            this.isDragging = false;
            const diff = this.startX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 40) {
                diff > 0 ? this.nextSlide() : this.prevSlide();
            }
        }
    }"
>
    @if(! $hasTestimonials)
        {{-- ============================================================= --}}
        {{-- EMPTY STATE                                                     --}}
        {{-- ============================================================= --}}
        <div class="flex flex-col items-center justify-center py-10 text-center">
            <div class="w-16 h-16 rounded-full bg-surface dark:bg-surface border border-outline dark:border-outline flex items-center justify-center mx-auto mb-4 shadow-card">
                <svg class="w-8 h-8 text-on-surface/30 dark:text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path></svg>
            </div>
            <p class="text-base font-medium text-on-surface-strong dark:text-on-surface-strong">
                {{ __('No testimonials yet') }}
            </p>
            <p class="text-sm text-on-surface/60 dark:text-on-surface/60 mt-1 max-w-xs">
                {{ __('Be the first to share your experience!') }}
            </p>
        </div>

    @else
        {{-- ============================================================= --}}
        {{-- MOBILE: CAROUSEL                                               --}}
        {{-- BR-450: Mobile carousel, swipeable cards, navigation dots     --}}
        {{-- ============================================================= --}}
        <div
            class="md:hidden relative"
            x-on:touchstart="handleTouchStart($event)"
            x-on:touchend="handleTouchEnd($event)"
        >
            {{-- Carousel track --}}
            <div class="overflow-hidden">
                @foreach($testimonials as $index => $testimonial)
                    <div
                        x-show="currentSlide === {{ $index }}"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-x-4"
                        x-transition:enter-end="opacity-100 translate-x-0"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-x-0"
                        x-transition:leave-end="opacity-0 -translate-x-4"
                    >
                        @include('tenant._testimonial-card', [
                            'testimonial' => $testimonial,
                            'isCarousel' => true,
                        ])
                    </div>
                @endforeach
            </div>

            {{-- Navigation arrows (only when > 1 slide) --}}
            @if($testimonialCount > 1)
                <button
                    @click="prevSlide()"
                    class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-3 w-9 h-9 rounded-full bg-surface dark:bg-surface border border-outline dark:border-outline shadow-card flex items-center justify-center text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors z-10"
                    aria-label="{{ __('Previous testimonial') }}"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>

                <button
                    @click="nextSlide()"
                    class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-3 w-9 h-9 rounded-full bg-surface dark:bg-surface border border-outline dark:border-outline shadow-card flex items-center justify-center text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors z-10"
                    aria-label="{{ __('Next testimonial') }}"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>

                {{-- Navigation dots --}}
                <div class="flex items-center justify-center gap-1.5 mt-5">
                    @foreach($testimonials as $index => $testimonial)
                        <button
                            @click="goToSlide({{ $index }})"
                            :class="currentSlide === {{ $index }} ? 'bg-primary w-5' : 'bg-on-surface/20 dark:bg-on-surface/20 hover:bg-on-surface/40 dark:hover:bg-on-surface/40'"
                            class="h-2 rounded-full transition-all duration-300"
                            aria-label="{{ __('Go to testimonial') }} {{ $index + 1 }}"
                        ></button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ============================================================= --}}
        {{-- DESKTOP: GRID                                                  --}}
        {{-- BR-450: Grid on desktop (2-3 columns)                         --}}
        {{-- ============================================================= --}}
        <div class="hidden md:grid md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($testimonials as $testimonial)
                @include('tenant._testimonial-card', [
                    'testimonial' => $testimonial,
                    'isCarousel' => false,
                ])
            @endforeach
        </div>
    @endif

    {{-- ================================================================= --}}
    {{-- CTA SECTION                                                         --}}
    {{-- BR-451: Submit CTA for eligible authenticated clients              --}}
    {{-- BR-452: Disabled for ineligible, login prompt for unauthenticated --}}
    {{-- ================================================================= --}}
    <div
        id="testimonial-form-section"
        class="mt-8 pt-8 border-t border-outline dark:border-outline"
        x-data="{
            showTestimonialModal: false,
            testimonialText: '',
            testimonialCharCount: 0,
            testimonialSubmitted: {{ $testimonialContext['existingTestimonial'] ? 'true' : 'false' }},

            openModal() {
                this.showTestimonialModal = true;
                this.$nextTick(() => {
                    const ta = document.getElementById('testimonial-textarea');
                    if (ta) { ta.focus(); }
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
        {{-- Already submitted (server-known state) --}}
        @if($testimonialContext['existingTestimonial'])
            <div class="flex flex-col items-center text-center gap-3 py-4">
                <div class="flex items-center gap-2">
                    @if($testimonialContext['existingTestimonial']->isPending())
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-warning-subtle text-warning border border-warning/30">
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4l3 3"></path></svg>
                            {{ __('Pending Review') }}
                        </span>
                    @elseif($testimonialContext['existingTestimonial']->isApproved())
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-success-subtle text-success border border-success/30">
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                            {{ __('Your testimonial is published') }}
                        </span>
                    @endif
                </div>
                <p class="text-sm text-on-surface/60 dark:text-on-surface/60">{{ __("Thank you for sharing your experience!") }}</p>
            </div>

        @else
            {{-- Post-submit reactive state --}}
            <div x-show="testimonialSubmitted" x-cloak class="flex flex-col items-center text-center gap-3 py-4">
                <div class="w-10 h-10 rounded-full bg-success-subtle flex items-center justify-center mx-auto">
                    <svg class="w-5 h-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                </div>
                <p class="text-sm font-semibold text-on-surface-strong dark:text-on-surface-strong">{{ __('Thank you! Your testimonial has been submitted for review.') }}</p>
            </div>

            {{-- Pre-submit CTA --}}
            <div x-show="!testimonialSubmitted" class="flex flex-col items-center text-center gap-3">
                <p class="text-sm text-on-surface/60 dark:text-on-surface/60 max-w-sm">
                    {{ __('Enjoyed your experience? Share it with others!') }}
                </p>

                @if(! $testimonialContext['isAuthenticated'])
                    {{-- BR-452: Unauthenticated visitor — login prompt --}}
                    <a
                        href="{{ route('login') }}"
                        x-navigate-skip
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-outline dark:border-outline text-on-surface dark:text-on-surface font-semibold text-sm hover:bg-surface-alt dark:hover:bg-surface-alt transition-colors"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
                        {{ __('Log in to share your experience') }}
                    </a>

                @elseif(! $testimonialContext['isEligible'])
                    {{-- BR-452: Authenticated but not eligible (no completed orders) --}}
                    <div class="relative group inline-block">
                        <button
                            type="button"
                            disabled
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-surface-alt dark:bg-surface-alt text-on-surface/40 dark:text-on-surface/40 font-semibold text-sm cursor-not-allowed border border-outline dark:border-outline"
                            aria-disabled="true"
                        >
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            {{ __('Share Your Experience') }}
                        </button>
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 bg-on-surface dark:bg-on-surface text-surface dark:text-surface text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none shadow-dropdown z-10">
                            {{ __('Order from this cook to share your experience.') }}
                            <div class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-x-4 border-x-transparent border-t-4 border-t-on-surface dark:border-t-on-surface"></div>
                        </div>
                    </div>

                @else
                    {{-- BR-451: Eligible client — active CTA --}}
                    <button
                        type="button"
                        @click="openModal()"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary text-on-primary font-semibold text-sm hover:bg-primary-hover transition-colors shadow-sm"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        {{ __('Share Your Experience') }}
                    </button>
                @endif
            </div>

            {{-- ====================================================== --}}
            {{-- TESTIMONIAL SUBMISSION MODAL (eligible clients only)    --}}
            {{-- ====================================================== --}}
            @if($testimonialContext['isAuthenticated'] && $testimonialContext['isEligible'])
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
                                    aria-describedby="testimonial-char-count-new testimonial-error-new"
                                ></textarea>

                                <div class="flex items-center justify-between mt-1.5">
                                    <p
                                        x-message="testimonialText"
                                        id="testimonial-error-new"
                                        class="text-xs text-danger"
                                    ></p>
                                    <p
                                        id="testimonial-char-count-new"
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
</div>
