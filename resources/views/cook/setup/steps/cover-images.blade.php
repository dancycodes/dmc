{{--
    Cover Images Step (Step 2)
    --------------------------
    F-073: Cover Images Step

    Upload, reorder, and preview cover images for the cook's brand.

    BR-127: Maximum 5 cover images per cook.
    BR-128: Accepted formats: JPEG, PNG, WebP.
    BR-129: Maximum file size: 2MB per image.
    BR-130: Images resized to 16:9 aspect ratio for carousel display.
    BR-131: Image order determines carousel display order; first is primary.
    BR-132: This step is optional — can be skipped.
    BR-133: Drag-to-reorder on desktop (mouse) and mobile (touch).
    BR-134: Stored via Spatie Media Library.
    BR-135: Delete requires confirmation.
--}}
@php
    $existingImages = $coverImages ?? [];
    $imageCount = count($existingImages);
    $maxImages = \App\Services\CoverImageService::MAX_IMAGES;
@endphp

<div
    x-data="{
        images: {{ json_encode($existingImages) }},
        imageCount: {{ $imageCount }},
        canUploadMore: {{ $imageCount < $maxImages ? 'true' : 'false' }},
        maxImages: {{ $maxImages }},
        uploadErrors: [],
        reorderSuccess: false,
        deleteConfirmId: null,
        deleteConfirmName: '',
        carouselIndex: 0,
        dragging: null,
        dragOver: null,

        /* Carousel auto-advance */
        carouselInterval: null,
        startCarousel() {
            this.stopCarousel();
            if (this.images.length > 1) {
                this.carouselInterval = setInterval(() => {
                    this.carouselIndex = (this.carouselIndex + 1) % this.images.length;
                }, 4000);
            }
        },
        stopCarousel() {
            if (this.carouselInterval) {
                clearInterval(this.carouselInterval);
                this.carouselInterval = null;
            }
        },

        /* Drag and drop reorder */
        startDrag(index) {
            this.dragging = index;
        },
        onDragOver(index) {
            this.dragOver = index;
        },
        endDrag() {
            if (this.dragging !== null && this.dragOver !== null && this.dragging !== this.dragOver) {
                /* Reorder the images array */
                const item = this.images.splice(this.dragging, 1)[0];
                this.images.splice(this.dragOver, 0, item);
                this.saveOrder();
            }
            this.dragging = null;
            this.dragOver = null;
        },

        /* Arrow button reorder (fallback for non-drag environments) */
        moveUp(index) {
            if (index > 0) {
                const item = this.images.splice(index, 1)[0];
                this.images.splice(index - 1, 0, item);
                this.saveOrder();
            }
        },
        moveDown(index) {
            if (index < this.images.length - 1) {
                const item = this.images.splice(index, 1)[0];
                this.images.splice(index + 1, 0, item);
                this.saveOrder();
            }
        },

        /* Save order to server */
        saveOrder() {
            const orderedIds = this.images.map(img => img.id);
            this.orderedIds = orderedIds;
            this.$nextTick(() => {
                $action('{{ url('/dashboard/setup/cover-images/reorder') }}', {
                    include: ['orderedIds']
                });
            });
        },

        /* Delete confirmation */
        confirmDelete(id, name) {
            this.deleteConfirmId = id;
            this.deleteConfirmName = name;
        },
        cancelDelete() {
            this.deleteConfirmId = null;
            this.deleteConfirmName = '';
        },
        executeDelete() {
            const id = this.deleteConfirmId;
            this.deleteConfirmId = null;
            this.deleteConfirmName = '';
            $action('{{ url('/dashboard/setup/cover-images') }}/' + id, {
                method: 'DELETE'
            });
        },

        orderedIds: []
    }"
    x-sync="['orderedIds']"
    x-init="startCarousel(); $watch('images', () => { startCarousel(); if (carouselIndex >= images.length) carouselIndex = Math.max(0, images.length - 1); })"
    x-on:gale:file-error.window="uploadErrors = [$event.detail.message]"
>
    {{-- Step Header --}}
    <div class="mb-6">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-full bg-secondary-subtle flex items-center justify-center shrink-0">
                {{-- Lucide: image --}}
                <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path></svg>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-on-surface-strong">{{ __('Cover Images') }}</h3>
                <p class="text-sm text-on-surface">{{ __('Add attractive photos to showcase your food and kitchen.') }}</p>
            </div>
        </div>
        <p class="text-xs text-on-surface/60 mt-1">
            {{ __('Great images help attract more customers. You can upload up to :count images.', ['count' => $maxImages]) }}
        </p>
    </div>

    {{-- Upload Errors --}}
    <template x-if="uploadErrors.length > 0">
        <div class="mb-4 p-3 rounded-lg bg-danger-subtle text-danger text-sm space-y-1">
            <template x-for="(err, i) in uploadErrors" :key="i">
                <p x-text="err"></p>
            </template>
        </div>
    </template>
    <p x-message="images" class="mb-4 text-sm text-danger"></p>

    {{-- Image Counter --}}
    <div class="flex items-center justify-between mb-4">
        <span class="text-sm font-medium text-on-surface">
            <span x-text="imageCount"></span>/<span x-text="maxImages"></span> {{ __('images uploaded') }}
        </span>
        <span x-show="!canUploadMore" class="text-xs text-warning font-medium">
            {{ __('Maximum reached') }}
        </span>
    </div>

    {{-- Upload Drop Zone --}}
    <div x-show="canUploadMore" class="mb-6">
        <label
            for="cover-image-input"
            class="flex flex-col items-center justify-center w-full py-8 px-4 border-2 border-dashed border-outline dark:border-outline rounded-xl bg-surface dark:bg-surface hover:border-primary hover:bg-primary-subtle/30 dark:hover:bg-primary-subtle/10 transition-colors duration-200 cursor-pointer group"
        >
            <div class="flex flex-col items-center text-center space-y-2">
                {{-- Lucide: upload-cloud --}}
                <svg class="w-10 h-10 text-on-surface/40 group-hover:text-primary transition-colors duration-200" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"></path><path d="M12 12v9"></path><path d="m16 16-4-4-4 4"></path></svg>
                <div>
                    <span class="text-sm font-medium text-primary">{{ __('Click to upload') }}</span>
                    <span class="text-sm text-on-surface/60">{{ __('or drag images here') }}</span>
                </div>
                <p class="text-xs text-on-surface/40">
                    {{ __('JPG, PNG, WebP — max 2MB each') }}
                </p>
            </div>
        </label>
        <input
            type="file"
            id="cover-image-input"
            name="images"
            x-files.max-size-2mb.max-files-5
            multiple
            accept="image/jpeg,image/png,image/webp"
            class="hidden"
        >
    </div>

    {{-- Upload Progress --}}
    <div x-show="$uploading" x-cloak class="mb-6">
        <div class="flex items-center gap-3 mb-2">
            <svg class="w-4 h-4 animate-spin text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            <span class="text-sm text-on-surface">{{ __('Uploading...') }}</span>
            <span class="text-sm font-medium text-primary" x-text="Math.round($uploadProgress) + '%'"></span>
        </div>
        <div class="w-full h-2 bg-surface-alt dark:bg-surface-alt rounded-full overflow-hidden">
            <div
                class="h-full bg-primary rounded-full transition-all duration-300"
                :style="'width: ' + $uploadProgress + '%'"
            ></div>
        </div>
    </div>

    {{-- File Preview (staged for upload) --}}
    <template x-if="$files('images').length > 0 && !$uploading">
        <div class="mb-6 space-y-3">
            <p class="text-sm font-medium text-on-surface-strong">{{ __('Ready to upload:') }}</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <template x-for="(file, i) in $files('images')" :key="i">
                    <div class="relative aspect-video rounded-lg overflow-hidden bg-surface dark:bg-surface border border-outline dark:border-outline">
                        <img
                            :src="$filePreview('images', i)"
                            class="w-full h-full object-cover"
                            :alt="file.name"
                        >
                        <div class="absolute bottom-0 left-0 right-0 bg-black/50 px-2 py-1">
                            <p class="text-xs text-white truncate" x-text="file.name"></p>
                            <p class="text-xs text-white/70" x-text="$formatBytes(file.size)"></p>
                        </div>
                    </div>
                </template>
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="$action('{{ url('/dashboard/setup/cover-images/upload') }}', { onProgress: (p) => {} })"
                    :disabled="$fetching()"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{-- Lucide: upload --}}
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" x2="12" y1="3" y2="15"></line></svg>
                    <span x-show="!$fetching()">{{ __('Upload Images') }}</span>
                    <span x-show="$fetching()" x-cloak>{{ __('Uploading...') }}</span>
                </button>
                <button
                    type="button"
                    @click="$clearFiles('images')"
                    class="inline-flex items-center gap-1 px-3 py-2 text-sm text-on-surface hover:text-danger transition-colors duration-200"
                >
                    {{ __('Clear') }}
                </button>
            </div>
        </div>
    </template>

    {{-- Existing Images Grid (Sortable) --}}
    <div x-show="images.length > 0" class="mb-6">
        <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-medium text-on-surface-strong">{{ __('Your cover images') }}</p>
            <p class="text-xs text-on-surface/60">{{ __('Drag to reorder — first image is your primary photo') }}</p>
        </div>

        <div id="cover-images-grid" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <template x-for="(img, index) in images" :key="img.id">
                <div
                    :id="'cover-image-' + img.id"
                    class="relative aspect-video rounded-lg overflow-hidden bg-surface dark:bg-surface border-2 transition-all duration-200 group/img"
                    :class="{
                        'border-primary shadow-md': dragging === index,
                        'border-primary/30': dragOver === index && dragging !== index,
                        'border-outline dark:border-outline': dragging !== index && dragOver !== index,
                        'ring-2 ring-primary/40': index === 0
                    }"
                    draggable="true"
                    @dragstart="startDrag(index)"
                    @dragover.prevent="onDragOver(index)"
                    @dragend="endDrag()"
                    @touchstart.passive="startDrag(index)"
                    @touchmove.passive="
                        const touch = $event.touches[0];
                        const el = document.elementFromPoint(touch.clientX, touch.clientY);
                        if (el) {
                            const card = el.closest('[draggable]');
                            if (card) {
                                const allCards = [...document.querySelectorAll('#cover-images-grid [draggable]')];
                                const idx = allCards.indexOf(card);
                                if (idx >= 0) onDragOver(idx);
                            }
                        }
                    "
                    @touchend="endDrag()"
                >
                    {{-- Image --}}
                    <img
                        :src="img.thumbnail"
                        class="w-full h-full object-cover"
                        :alt="img.name"
                        loading="lazy"
                    >

                    {{-- Primary badge --}}
                    <div
                        x-show="index === 0"
                        class="absolute top-2 left-2 px-2 py-0.5 bg-primary text-on-primary text-xs font-bold rounded-full"
                    >
                        {{ __('Primary') }}
                    </div>

                    {{-- Overlay controls --}}
                    <div class="absolute inset-0 bg-black/0 group-hover/img:bg-black/30 transition-colors duration-200 flex items-center justify-center gap-2 opacity-0 group-hover/img:opacity-100">
                        {{-- Move up --}}
                        <button
                            type="button"
                            x-show="index > 0"
                            @click.stop="moveUp(index)"
                            class="w-8 h-8 rounded-full bg-white/90 dark:bg-surface/90 flex items-center justify-center text-on-surface hover:bg-white transition-colors duration-200"
                            :title="'{{ __('Move left') }}'"
                        >
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                        </button>

                        {{-- Move down --}}
                        <button
                            type="button"
                            x-show="index < images.length - 1"
                            @click.stop="moveDown(index)"
                            class="w-8 h-8 rounded-full bg-white/90 dark:bg-surface/90 flex items-center justify-center text-on-surface hover:bg-white transition-colors duration-200"
                            :title="'{{ __('Move right') }}'"
                        >
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                        </button>
                    </div>

                    {{-- Delete button --}}
                    <button
                        type="button"
                        @click.stop="confirmDelete(img.id, img.name)"
                        class="absolute top-2 right-2 w-7 h-7 rounded-full bg-danger/80 hover:bg-danger text-on-danger flex items-center justify-center opacity-0 group-hover/img:opacity-100 transition-all duration-200 shadow-sm"
                        :title="'{{ __('Delete image') }}'"
                    >
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    </button>

                    {{-- Image info on hover --}}
                    <div class="absolute bottom-0 left-0 right-0 bg-black/50 px-2 py-1 opacity-0 group-hover/img:opacity-100 transition-opacity duration-200">
                        <p class="text-xs text-white truncate" x-text="img.name"></p>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Preview Carousel --}}
    <div x-show="images.length > 0" class="mb-4">
        <p class="text-sm font-medium text-on-surface-strong mb-3">{{ __('Preview') }}</p>
        <div class="relative aspect-video rounded-xl overflow-hidden bg-surface dark:bg-surface border border-outline dark:border-outline shadow-card"
            @mouseenter="stopCarousel()"
            @mouseleave="startCarousel()"
        >
            {{-- Carousel images --}}
            <template x-for="(img, index) in images" :key="'carousel-' + img.id">
                <img
                    :src="img.url"
                    class="absolute inset-0 w-full h-full object-cover transition-opacity duration-500"
                    :class="carouselIndex === index ? 'opacity-100 z-10' : 'opacity-0 z-0'"
                    :alt="img.name"
                    loading="lazy"
                >
            </template>

            {{-- Carousel controls --}}
            <template x-if="images.length > 1">
                <div>
                    {{-- Previous --}}
                    <button
                        type="button"
                        @click.stop="carouselIndex = (carouselIndex - 1 + images.length) % images.length"
                        class="absolute left-2 top-1/2 -translate-y-1/2 z-20 w-8 h-8 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center transition-colors duration-200"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                    </button>

                    {{-- Next --}}
                    <button
                        type="button"
                        @click.stop="carouselIndex = (carouselIndex + 1) % images.length"
                        class="absolute right-2 top-1/2 -translate-y-1/2 z-20 w-8 h-8 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center transition-colors duration-200"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                    </button>

                    {{-- Dots --}}
                    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 z-20 flex items-center gap-1.5">
                        <template x-for="(img, index) in images" :key="'dot-' + img.id">
                            <button
                                type="button"
                                @click.stop="carouselIndex = index"
                                class="w-2 h-2 rounded-full transition-all duration-200"
                                :class="carouselIndex === index ? 'bg-white w-4' : 'bg-white/50 hover:bg-white/80'"
                            ></button>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Empty preview --}}
            <div
                x-show="images.length === 0"
                class="absolute inset-0 flex items-center justify-center bg-surface-alt dark:bg-surface-alt"
            >
                <div class="text-center text-on-surface/40">
                    <svg class="w-12 h-12 mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path></svg>
                    <p class="text-sm">{{ __('Upload images to see preview') }}</p>
                </div>
            </div>
        </div>
        <p class="text-xs text-on-surface/60 mt-2 text-center">
            {{ __('This is how your images will appear on your store page and discovery card.') }}
        </p>
    </div>

    {{-- Empty State --}}
    <div x-show="images.length === 0 && $files('images').length === 0" class="text-center py-6">
        <div class="w-14 h-14 mx-auto rounded-full bg-secondary-subtle flex items-center justify-center mb-3">
            <svg class="w-7 h-7 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path></svg>
        </div>
        <p class="text-sm text-on-surface/60 max-w-sm mx-auto">
            {{ __('No cover images yet. Upload photos of your food and kitchen to attract more customers.') }}
        </p>
        <p class="text-xs text-on-surface/40 mt-2">
            {{ __('This step is optional — you can skip it and add images later.') }}
        </p>
    </div>

    {{-- Delete Confirmation Modal (BR-135) --}}
    <div
        x-show="deleteConfirmId !== null"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @keydown.escape.window="cancelDelete()"
        x-cloak
    >
        <div
            x-show="deleteConfirmId !== null"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.outside="cancelDelete()"
            class="bg-surface dark:bg-surface rounded-xl shadow-dropdown border border-outline dark:border-outline p-6 max-w-sm w-full"
        >
            <div class="text-center space-y-4">
                {{-- Danger icon --}}
                <div class="w-12 h-12 mx-auto rounded-full bg-danger-subtle flex items-center justify-center">
                    <svg class="w-6 h-6 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" x2="10" y1="11" y2="17"></line><line x1="14" x2="14" y1="11" y2="17"></line></svg>
                </div>

                <h3 class="text-base font-bold text-on-surface-strong">{{ __('Delete Image?') }}</h3>
                <p class="text-sm text-on-surface">
                    {{ __('Are you sure you want to delete this image? This action cannot be undone.') }}
                </p>

                <div class="flex gap-3 pt-2">
                    <button
                        type="button"
                        @click="cancelDelete()"
                        class="flex-1 py-2.5 px-4 text-sm font-medium text-on-surface bg-surface-alt hover:bg-surface border border-outline dark:border-outline rounded-lg transition-colors duration-200"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="button"
                        @click="executeDelete()"
                        class="flex-1 py-2.5 px-4 text-sm font-bold text-on-danger bg-danger hover:bg-danger/90 rounded-lg transition-colors duration-200 shadow-sm"
                    >
                        <span x-show="!$fetching()">{{ __('Delete') }}</span>
                        <span x-show="$fetching()" x-cloak>{{ __('Deleting...') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
