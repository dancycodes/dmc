{{--
    F-183: Client Complaint Submission Form
    ----------------------------------------
    Allows client to submit a complaint on a delivered/completed order.

    BR-185: Categories: food_quality, delivery_issue, missing_item, wrong_order, other.
    BR-186: Description required, min 10, max 1000 chars.
    BR-187: Photo optional, max one image.
    BR-188: JPEG, PNG, WebP; max 5MB.
    BR-189: Initial status "open".
    BR-193: All text uses __() localization.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Report a Problem') . ' - #' . $order->order_number)

@section('content')
<div
    class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8"
    x-data="{
        category: '',
        description: '',
        photoPreview: null,
        photoName: '',
        submitting: false,

        get charCount() {
            return this.description.length;
        },

        get charCountClass() {
            if (this.charCount > 1000) return 'text-danger font-semibold';
            if (this.charCount > 900) return 'text-warning';
            if (this.charCount > 0) return 'text-on-surface/50';
            return 'text-on-surface/40';
        },

        get canSubmit() {
            return this.category !== '' && this.description.length >= 10 && this.description.length <= 1000 && !this.submitting;
        },

        handlePhotoSelect(event) {
            const file = event.target.files[0];
            if (!file) {
                this.clearPhoto();
                return;
            }

            const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                this.clearPhoto();
                event.target.value = '';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                this.clearPhoto();
                event.target.value = '';
                return;
            }

            this.photoName = file.name;
            const reader = new FileReader();
            reader.onload = (e) => {
                this.photoPreview = e.target.result;
            };
            reader.readAsDataURL(file);
        },

        clearPhoto() {
            this.photoPreview = null;
            this.photoName = '';
            const input = this.$refs.photoInput;
            if (input) input.value = '';
        }
    }"
>
    {{-- Back Navigation --}}
    <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-6" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ url('/my-orders/' . $order->id) }}" class="hover:text-primary transition-colors duration-200 flex items-center gap-1" x-navigate>
            {{-- ArrowLeft icon (Lucide, sm=16) --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
            {{ __('Back to Order') }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <span class="text-on-surface-strong font-medium">{{ __('Report a Problem') }}</span>
    </nav>

    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-display font-bold text-on-surface-strong">{{ __('Report a Problem') }}</h1>
        <p class="text-sm text-on-surface/60 mt-1">
            {{ __('Order') }} <span class="font-mono font-semibold">#{{ $order->order_number }}</span>
            {{ __('from') }} {{ $cookName }}
        </p>
    </div>

    {{-- Complaint Form Card --}}
    <div class="bg-surface dark:bg-surface rounded-xl shadow-card border border-outline dark:border-outline overflow-hidden">
        <div class="px-5 py-3.5 border-b border-outline dark:border-outline bg-warning-subtle dark:bg-warning-subtle">
            <h2 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                {{-- AlertTriangle icon (Lucide, sm=16) --}}
                <svg class="w-4 h-4 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                {{ __('What went wrong?') }}
            </h2>
        </div>

        <div class="p-5 space-y-5">

            {{-- Category Selection (BR-185) --}}
            <div>
                <label for="complaint-category" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                    {{ __('Category') }} <span class="text-danger">*</span>
                </label>
                <select
                    id="complaint-category"
                    x-model="category"
                    x-name="category"
                    class="w-full rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface text-sm px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors"
                >
                    <option value="">{{ __('Select a category...') }}</option>
                    @foreach($categoryLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p x-message="category" class="text-sm text-danger mt-1"></p>
            </div>

            {{-- Description (BR-186) --}}
            <div>
                <label for="complaint-description" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                    {{ __('Description') }} <span class="text-danger">*</span>
                </label>
                <textarea
                    id="complaint-description"
                    x-model="description"
                    x-name="description"
                    rows="5"
                    class="w-full rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder-on-surface/40 text-sm px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-colors resize-none"
                    :class="charCount > 1000 ? 'border-danger focus:ring-danger/50 focus:border-danger' : ''"
                    placeholder="{{ __('Please describe the issue in detail. What happened? What did you expect?') }}"
                ></textarea>
                <div class="flex items-center justify-between mt-1">
                    <p x-message="description" class="text-sm text-danger"></p>
                    <span
                        class="text-xs transition-colors"
                        :class="charCountClass"
                        x-text="charCount + '/1000'"
                    ></span>
                </div>
            </div>

            {{-- Photo Upload (BR-187, BR-188) --}}
            <div>
                <label class="block text-sm font-medium text-on-surface-strong mb-1.5">
                    {{ __('Photo Evidence') }}
                    <span class="text-on-surface/40 font-normal">({{ __('optional') }})</span>
                </label>

                {{-- Upload Area --}}
                <div x-show="!photoPreview">
                    <label
                        for="complaint-photo"
                        class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-outline dark:border-outline rounded-lg cursor-pointer hover:border-primary/50 hover:bg-primary-subtle/30 transition-colors"
                    >
                        <div class="flex flex-col items-center justify-center py-4">
                            {{-- Upload icon (Lucide, lg=24) --}}
                            <svg class="w-6 h-6 text-on-surface/40 mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                            <p class="text-xs text-on-surface/50">
                                <span class="font-semibold text-primary">{{ __('Click to upload') }}</span>
                                {{ __('or drag and drop') }}
                            </p>
                            <p class="text-xs text-on-surface/40 mt-1">{{ __('JPEG, PNG, WebP (max 5MB)') }}</p>
                        </div>
                        <input
                            id="complaint-photo"
                            type="file"
                            name="photo"
                            x-ref="photoInput"
                            x-files
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            x-on:change="handlePhotoSelect($event)"
                        >
                    </label>
                </div>

                {{-- Photo Preview --}}
                <div x-show="photoPreview" x-cloak class="relative">
                    <div class="rounded-lg overflow-hidden border border-outline dark:border-outline">
                        <img :src="photoPreview" alt="{{ __('Complaint photo preview') }}" class="w-full h-48 object-cover">
                    </div>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-xs text-on-surface/60 truncate max-w-[200px]" x-text="photoName"></span>
                        <button
                            type="button"
                            class="text-xs text-danger hover:text-danger/80 transition-colors flex items-center gap-1"
                            x-on:click="clearPhoto()"
                        >
                            {{-- X icon (Lucide, xs=14) --}}
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                            {{ __('Remove') }}
                        </button>
                    </div>
                </div>
                <p x-message="photo" class="text-sm text-danger mt-1"></p>
            </div>

            {{-- Info Notice --}}
            <div class="bg-info-subtle dark:bg-info-subtle rounded-lg p-3 flex items-start gap-2.5">
                {{-- Info icon (Lucide, sm=16) --}}
                <svg class="w-4 h-4 text-info shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                <p class="text-xs text-on-surface/70 leading-relaxed">
                    {{ __('Your complaint will be reviewed by the cook. If not resolved within 24 hours, it will be automatically escalated to the DancyMeals support team.') }}
                </p>
            </div>

            {{-- Submit Button --}}
            <div class="flex flex-col sm:flex-row items-center gap-3 pt-2">
                <button
                    type="button"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg text-sm font-medium bg-danger text-on-danger hover:bg-danger/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="!canSubmit || $fetching()"
                    x-on:click="$action('{{ url('/my-orders/' . $order->id . '/complaint') }}', { include: ['category', 'description'] })"
                >
                    <span x-show="!$fetching()">
                        {{-- AlertTriangle icon (Lucide, sm=16) --}}
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    </span>
                    <span x-show="$fetching()" class="animate-spin-slow">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                    </span>
                    <span x-show="!$fetching()">{{ __('Submit Complaint') }}</span>
                    <span x-show="$fetching()">{{ __('Submitting...') }}</span>
                </button>

                <a
                    href="{{ url('/my-orders/' . $order->id) }}"
                    class="w-full sm:w-auto text-center text-sm text-on-surface/60 hover:text-on-surface transition-colors py-2"
                    x-navigate
                >
                    {{ __('Cancel') }}
                </a>
            </div>

        </div>
    </div>
</div>
@endsection
