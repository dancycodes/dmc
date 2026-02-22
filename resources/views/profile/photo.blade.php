{{--
    Profile Photo Upload Page (F-031)
    ----------------------------------
    Allows authenticated users to upload or remove their profile photo.
    - Client-side FileReader preview before upload (BR-106)
    - Gale x-files for multipart FormData upload
    - Intervention Image resizes/crops to 256×256 on server (BR-105)
    - Remove photo with Alpine confirmation dialog (BR-108)
    - Supported formats: JPG, PNG, WebP (BR-103)
    - Max 2MB (BR-104)
    - Activity logged on upload and remove (BR-111)
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Change Photo'))

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">

    {{-- Breadcrumb --}}
    <nav class="mb-6 flex items-center gap-2 text-sm text-on-surface" x-data x-navigate aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ url('/profile') }}" class="hover:text-on-surface-strong transition-colors duration-200">{{ __('Profile') }}</a>
        <svg class="w-4 h-4 shrink-0 text-on-surface opacity-40" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m9 18 6-6-6-6"></path>
        </svg>
        <span class="text-on-surface-strong font-medium">{{ __('Change Photo') }}</span>
    </nav>

    {{-- Photo Upload Card --}}
    <div
        class="bg-surface-alt dark:bg-surface-alt rounded-xl shadow-card border border-outline overflow-hidden"
        x-data="{
            previewSrc: '{{ $user->profile_photo_path ? asset('storage/' . $user->profile_photo_path) : '' }}',
            hasPhoto: {{ $user->profile_photo_path ? 'true' : 'false' }},
            showRemoveConfirm: false,
            isUploading: false,
            uploadProgress: 0,

            handleFileChange() {
                const files = this.$files('photo');
                if (files && files.length > 0) {
                    this.previewSrc = this.$filePreview('photo', 0);
                }
            },

            async submitUpload() {
                this.isUploading = true;
                this.uploadProgress = 0;
                await $action('{{ route('profile.photo.upload') }}', {
                    onProgress: (p) => { this.uploadProgress = Math.round(p); }
                });
                this.isUploading = false;
            },

            confirmRemove() {
                this.showRemoveConfirm = true;
            },

            cancelRemove() {
                this.showRemoveConfirm = false;
            },

            async executeRemove() {
                this.showRemoveConfirm = false;
                await $action('{{ route('profile.photo.destroy') }}', { method: 'DELETE' });
            }
        }"
        x-init="
            $watch('$files(\'photo\')', () => handleFileChange());
        "
    >
        {{-- Card Header --}}
        <div class="px-6 py-5 border-b border-outline dark:border-outline">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-secondary-subtle flex items-center justify-center shrink-0">
                    {{-- Camera icon --}}
                    <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path>
                        <circle cx="12" cy="13" r="3"></circle>
                    </svg>
                </span>
                <div>
                    <h1 class="text-lg font-semibold text-on-surface-strong font-display">{{ __('Change Photo') }}</h1>
                    <p class="text-sm text-on-surface mt-0.5">{{ __('JPG, PNG, or WebP · Max 2MB') }}</p>
                </div>
            </div>
        </div>

        {{-- Card Body --}}
        <div class="px-6 py-8">
            <div class="flex flex-col items-center gap-6">

                {{-- Current / Preview Avatar (circular) --}}
                <div class="relative group">
                    {{-- Photo or Initials Avatar --}}
                    <div class="w-36 h-36 sm:w-44 sm:h-44 rounded-full overflow-hidden border-4 border-outline shadow-card">
                        <template x-if="previewSrc">
                            <img
                                :src="previewSrc"
                                alt="{{ $user->name }}"
                                class="w-full h-full object-cover"
                            >
                        </template>
                        <template x-if="!previewSrc">
                            <div class="w-full h-full bg-primary-subtle flex items-center justify-center">
                                <span class="text-5xl sm:text-6xl font-bold text-primary font-display">
                                    {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                                </span>
                            </div>
                        </template>
                    </div>

                    {{-- Camera overlay label for desktop hover --}}
                    <label
                        for="photo-input"
                        class="absolute inset-0 rounded-full bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex flex-col items-center justify-center cursor-pointer"
                    >
                        <svg class="w-7 h-7 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path>
                            <circle cx="12" cy="13" r="3"></circle>
                        </svg>
                        <span class="text-xs text-white font-medium mt-1">{{ __('Change') }}</span>
                    </label>
                </div>

                {{-- Hidden file input with x-files --}}
                <input
                    id="photo-input"
                    type="file"
                    name="photo"
                    accept="image/jpeg,image/png,image/webp"
                    x-files="photo"
                    class="sr-only"
                    x-on:gale:file-error.window="
                        if ($event.detail?.name === 'photo') {
                            $dispatch('toast', { type: 'error', message: '{{ __('The photo must be a JPG, PNG, or WebP image.') }}' });
                        }
                    "
                >

                {{-- Mobile "Choose Photo" button (always visible below avatar) --}}
                <label
                    for="photo-input"
                    class="sm:hidden inline-flex items-center gap-2 h-10 px-5 rounded-lg text-sm font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 cursor-pointer"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" x2="12" y1="3" y2="15"></line>
                    </svg>
                    {{ __('Choose Photo') }}
                </label>

                {{-- File name indicator once selected --}}
                <p
                    x-show="$files('photo') && $files('photo').length > 0"
                    x-text="$files('photo') && $files('photo').length > 0 ? $files('photo')[0].name : ''"
                    class="text-sm text-on-surface text-center truncate max-w-xs"
                    x-cloak
                ></p>

                {{-- Upload Progress Bar --}}
                <div
                    x-show="isUploading && uploadProgress > 0 && uploadProgress < 100"
                    class="w-full max-w-xs"
                    x-cloak
                >
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs text-on-surface">{{ __('Uploading...') }}</span>
                        <span class="text-xs text-on-surface font-mono" x-text="uploadProgress + '%'"></span>
                    </div>
                    <div class="h-1.5 bg-outline rounded-full overflow-hidden">
                        <div
                            class="h-full bg-primary transition-all duration-150 ease-out rounded-full"
                            :style="'width: ' + uploadProgress + '%'"
                        ></div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">

                    {{-- Desktop "Choose Photo" button --}}
                    <label
                        for="photo-input"
                        class="hidden sm:inline-flex items-center gap-2 h-10 px-5 rounded-lg text-sm font-semibold border border-outline text-on-surface hover:bg-surface dark:hover:bg-surface transition-all duration-200 cursor-pointer"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" x2="12" y1="3" y2="15"></line>
                        </svg>
                        {{ __('Choose Photo') }}
                    </label>

                    {{-- Save Button --}}
                    <button
                        type="button"
                        @click="submitUpload()"
                        x-show="$files('photo') && $files('photo').length > 0"
                        :disabled="isUploading"
                        class="inline-flex items-center justify-center gap-2 h-10 px-6 rounded-lg text-sm font-semibold bg-primary hover:bg-primary-hover text-on-primary transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed"
                        x-cloak
                    >
                        <span x-show="!isUploading">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5"></path>
                            </svg>
                        </span>
                        <span x-show="isUploading">
                            <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
                            </svg>
                        </span>
                        <span x-show="!isUploading">{{ __('Save Photo') }}</span>
                        <span x-show="isUploading">{{ __('Uploading...') }}</span>
                    </button>
                </div>

                {{-- Remove Photo Button (only shown if user has a photo) --}}
                @if($user->profile_photo_path)
                <div class="mt-2 pt-6 border-t border-outline dark:border-outline w-full flex justify-center">
                    <button
                        type="button"
                        @click="confirmRemove()"
                        :disabled="isUploading"
                        class="inline-flex items-center gap-2 h-9 px-4 rounded-lg text-sm font-medium text-danger border border-danger/30 hover:bg-danger-subtle transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"></path>
                            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                        </svg>
                        {{ __('Remove Photo') }}
                    </button>
                </div>
                @endif

            </div>
        </div>

        {{-- Remove Confirmation Dialog --}}
        <div
            x-show="showRemoveConfirm"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            @click.self="cancelRemove()"
            x-cloak
            role="dialog"
            aria-modal="true"
            aria-labelledby="remove-dialog-title"
        >
            <div
                class="bg-surface dark:bg-surface rounded-xl shadow-dropdown border border-outline w-full max-w-sm p-6 animate-scale-in"
                @click.stop
            >
                {{-- Warning Icon --}}
                <div class="flex justify-center mb-4">
                    <span class="w-14 h-14 rounded-full bg-danger-subtle flex items-center justify-center">
                        <svg class="w-7 h-7 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                            <path d="M12 9v4"></path>
                            <path d="M12 17h.01"></path>
                        </svg>
                    </span>
                </div>

                <h2 id="remove-dialog-title" class="text-base font-semibold text-on-surface-strong text-center mb-2">
                    {{ __('Remove profile photo?') }}
                </h2>
                <p class="text-sm text-on-surface text-center mb-6">
                    {{ __('Your profile photo will be deleted. The default avatar (initials) will be shown instead.') }}
                </p>

                <div class="flex flex-col sm:flex-row gap-3">
                    <button
                        type="button"
                        @click="cancelRemove()"
                        class="flex-1 h-10 px-4 rounded-lg text-sm font-semibold border border-outline text-on-surface hover:bg-surface-alt dark:hover:bg-surface-alt transition-all duration-200"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="button"
                        @click="executeRemove()"
                        class="flex-1 h-10 px-4 rounded-lg text-sm font-semibold bg-danger hover:bg-danger/90 text-on-danger transition-all duration-200"
                    >
                        {{ __('Remove Photo') }}
                    </button>
                </div>
            </div>
        </div>

    </div>

    {{-- Tips --}}
    <div class="mt-6 p-4 bg-info-subtle dark:bg-info-subtle rounded-xl border border-info/20">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M12 16v-4"></path>
                <path d="M12 8h.01"></path>
            </svg>
            <div>
                <p class="text-sm font-medium text-info">{{ __('Photo Tips') }}</p>
                <ul class="mt-1 text-sm text-on-surface space-y-1 list-disc list-inside">
                    <li>{{ __('Use a square image for best results') }}</li>
                    <li>{{ __('JPG, PNG, and WebP formats accepted') }}</li>
                    <li>{{ __('Maximum file size: 2MB') }}</li>
                    <li>{{ __('Images are automatically cropped to 256×256 pixels') }}</li>
                </ul>
            </div>
        </div>
    </div>

</div>
@endsection
