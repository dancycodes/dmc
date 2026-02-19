{{--
    F-129: Meal Detail View — Image Carousel
    BR-157: Up to 3 images with manual navigation (swipe on mobile, arrows on desktop)
    Edge case: Single image without carousel controls
    Edge case: No images = placeholder
--}}
@php
    $imageCount = count($images);
    $hasImages = $imageCount > 0;
    $isSingleImage = $imageCount === 1;
@endphp

@if($hasImages)
    <div
        class="relative rounded-xl overflow-hidden bg-surface-alt dark:bg-surface-alt"
        @if(!$isSingleImage)
            x-data="{
                current: 0,
                total: {{ $imageCount }},
                next() { this.current = (this.current + 1) % this.total; },
                prev() { this.current = (this.current - 1 + this.total) % this.total; },
                goTo(i) { this.current = i; },
                touchStartX: 0,
                handleTouchStart(e) { this.touchStartX = e.touches[0].clientX; },
                handleTouchEnd(e) {
                    const diff = this.touchStartX - e.changedTouches[0].clientX;
                    if (Math.abs(diff) > 50) {
                        diff > 0 ? this.next() : this.prev();
                    }
                }
            }"
            x-on:touchstart.passive="handleTouchStart($event)"
            x-on:touchend.passive="handleTouchEnd($event)"
        @endif
    >
        {{-- Images --}}
        <div class="aspect-[4/3] sm:aspect-[3/2] relative">
            @foreach($images as $index => $image)
                <div
                    class="absolute inset-0"
                    @if(!$isSingleImage)
                        x-show="current === {{ $index }}"
                        x-transition:enter="transition-opacity ease-out duration-500"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition-opacity ease-in duration-300"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                    @endif
                >
                    <img
                        src="{{ $image['url'] }}"
                        alt="{{ $mealName }} - {{ __('Image') }} {{ $index + 1 }}"
                        class="w-full h-full object-cover"
                        loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                        x-on:error="$el.style.display='none'; $el.nextElementSibling.style.display='flex'"
                    >
                    {{-- Fallback on image error --}}
                    <div class="absolute inset-0 items-center justify-center bg-surface-alt dark:bg-surface-alt" style="display:none;">
                        <svg class="w-16 h-16 text-on-surface/20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path></svg>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Navigation arrows (multiple images only) --}}
        @if(!$isSingleImage)
            <button
                @click="prev()"
                class="absolute left-2 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-surface/80 dark:bg-surface/80 backdrop-blur-sm flex items-center justify-center text-on-surface-strong hover:bg-surface transition-colors duration-200 shadow-card cursor-pointer"
                aria-label="{{ __('Previous image') }}"
            >
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
            </button>
            <button
                @click="next()"
                class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-surface/80 dark:bg-surface/80 backdrop-blur-sm flex items-center justify-center text-on-surface-strong hover:bg-surface transition-colors duration-200 shadow-card cursor-pointer"
                aria-label="{{ __('Next image') }}"
            >
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
            </button>

            {{-- Dot indicators --}}
            <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-2">
                @foreach($images as $index => $image)
                    <button
                        @click="goTo({{ $index }})"
                        class="w-2.5 h-2.5 rounded-full transition-all duration-300 cursor-pointer"
                        :class="current === {{ $index }} ? 'bg-white w-6' : 'bg-white/50 hover:bg-white/70'"
                        aria-label="{{ __('Go to image') }} {{ $index + 1 }}"
                    ></button>
                @endforeach
            </div>

            {{-- Image counter --}}
            <div class="absolute top-3 right-3 bg-surface/80 dark:bg-surface/80 backdrop-blur-sm text-xs font-medium text-on-surface-strong rounded-full px-2.5 py-1">
                <span x-text="(current + 1) + ' / {{ $imageCount }}'"></span>
            </div>
        @endif
    </div>
@else
    {{-- Edge case: No images — placeholder --}}
    <div class="aspect-[4/3] sm:aspect-[3/2] rounded-xl bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline flex items-center justify-center">
        <div class="text-center">
            <svg class="w-16 h-16 mx-auto text-on-surface/20 mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path></svg>
            <p class="text-sm text-on-surface/40">{{ __('No image available') }}</p>
        </div>
    </div>
@endif
