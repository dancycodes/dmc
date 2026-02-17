{{--
    Schedule & First Meal Step (Step 4)
    -----------------------------------
    F-075: Schedule & First Meal Step

    Two sections: "Your Schedule" (top) and "Your First Meal" (bottom).
    Schedule: 7 day toggles with time pickers for start/end when enabled.
    Meal: simple form with bilingual name, optional description, price, and inline components.

    BR-146: Schedule sets availability per day with start and end times.
    BR-147: At least one meal with one component is required for minimum setup.
    BR-148: Meal name required in both EN and FR.
    BR-149: Meal price must be > 0 XAF.
    BR-150: Each meal must have at least one component (name required in en/fr).
    BR-151: Created meals default to is_active = true.
    BR-152: Schedule times use 24-hour format.
    BR-153: End time must be after start time.
    BR-155: Step complete when at least one active meal with one component exists.
--}}
<div
    x-data="{
        scheduleData: {{ json_encode($scheduleData ?? []) }},
        scheduleSaved: false,
        hasSchedule: {{ collect($scheduleData ?? [])->contains(fn($s) => $s['enabled']) ? 'true' : 'false' }},

        /* Meal form */
        mealsData: {{ json_encode($mealsData ?? []) }},
        hasMeal: {{ !empty($mealsData) ? 'true' : 'false' }},
        meal_name_en: '',
        meal_name_fr: '',
        meal_description_en: '',
        meal_description_fr: '',
        meal_price: '',
        components: [{ name_en: '', name_fr: '' }],
        showMealForm: {{ empty($mealsData) ? 'true' : 'false' }},

        /* Helpers */
        addComponent() {
            this.components.push({ name_en: '', name_fr: '' });
        },
        removeComponent(index) {
            if (this.components.length > 1) {
                this.components.splice(index, 1);
            }
        },
        get enabledDayCount() {
            return this.scheduleData.filter(s => s.enabled).length;
        }
    }"
    x-sync="['scheduleData', 'meal_name_en', 'meal_name_fr', 'meal_description_en', 'meal_description_fr', 'meal_price', 'components']"
>
    {{-- ===== SCHEDULE SECTION ===== --}}
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center shrink-0">
                {{-- Lucide: calendar-clock --}}
                <svg class="w-5 h-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 7.5V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3.5"></path><path d="M16 2v4"></path><path d="M8 2v4"></path><path d="M3 10h5"></path><path d="M17.5 17.5 16 16.3V14"></path><circle cx="16" cy="16" r="6"></circle></svg>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-on-surface-strong">{{ __('Your Schedule') }}</h3>
                <p class="text-sm text-on-surface">{{ __('Set your availability for each day of the week.') }}</p>
            </div>
        </div>

        {{-- Schedule saved indicator --}}
        <template x-if="scheduleSaved">
            <div class="mb-4 p-3 rounded-lg bg-success-subtle text-success text-sm flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                {{ __('Schedule saved successfully.') }}
            </div>
        </template>

        {{-- Day toggles with time pickers --}}
        <div class="space-y-3">
            <template x-for="(day, index) in scheduleData" :key="day.day">
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 rounded-lg border border-outline dark:border-outline"
                     :class="day.enabled ? 'bg-primary-subtle/30 dark:bg-primary-subtle/10 border-primary/30' : 'bg-surface dark:bg-surface'">

                    {{-- Day toggle --}}
                    <div class="flex items-center gap-3 sm:w-28 shrink-0">
                        <button
                            type="button"
                            @click="day.enabled = !day.enabled"
                            :class="day.enabled ? 'bg-primary' : 'bg-outline dark:bg-outline'"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary/50"
                            :aria-checked="day.enabled"
                            role="switch"
                        >
                            <span
                                :class="day.enabled ? 'translate-x-5' : 'translate-x-0'"
                                class="inline-block h-5 w-5 rounded-full bg-white shadow transform transition-transform duration-200"
                            ></span>
                        </button>
                        <span class="text-sm font-medium text-on-surface-strong min-w-[32px]" x-text="day.label"></span>
                    </div>

                    {{-- Time pickers (shown when day is enabled) --}}
                    <div x-show="day.enabled" x-transition class="flex items-center gap-2 flex-1">
                        <select
                            x-model="day.start_time"
                            class="flex-1 sm:w-auto px-3 py-2 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface focus:ring-2 focus:ring-primary/50 focus:border-primary"
                            :aria-label="day.label + ' {{ __('start time') }}'"
                        >
                            @for ($h = 0; $h < 24; $h++)
                                @for ($m = 0; $m < 60; $m += 30)
                                    <option value="{{ sprintf('%02d:%02d', $h, $m) }}">{{ sprintf('%02d:%02d', $h, $m) }}</option>
                                @endfor
                            @endfor
                        </select>

                        <span class="text-on-surface text-sm">{{ __('to') }}</span>

                        <select
                            x-model="day.end_time"
                            class="flex-1 sm:w-auto px-3 py-2 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface focus:ring-2 focus:ring-primary/50 focus:border-primary"
                            :aria-label="day.label + ' {{ __('end time') }}'"
                        >
                            @for ($h = 0; $h < 24; $h++)
                                @for ($m = 0; $m < 60; $m += 30)
                                    <option value="{{ sprintf('%02d:%02d', $h, $m) }}">{{ sprintf('%02d:%02d', $h, $m) }}</option>
                                @endfor
                            @endfor
                        </select>

                        {{-- Time validation warning --}}
                        <template x-if="day.enabled && day.end_time <= day.start_time">
                            <span class="text-danger text-xs flex items-center gap-1 shrink-0">
                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                <span class="hidden sm:inline">{{ __('Invalid') }}</span>
                            </span>
                        </template>
                    </div>

                    {{-- Unavailable label --}}
                    <div x-show="!day.enabled" class="text-sm text-on-surface/50 italic">
                        {{ __('Unavailable') }}
                    </div>
                </div>
            </template>
        </div>

        {{-- Schedule validation message --}}
        <p x-message="schedule" class="mt-2 text-sm text-danger"></p>

        {{-- Schedule summary --}}
        <div class="mt-4 flex items-center justify-between">
            <p class="text-sm text-on-surface">
                <span x-text="enabledDayCount"></span> {{ __('day(s) enabled') }}
                <template x-if="enabledDayCount === 0">
                    <span class="text-warning ml-1">{{ __('- Clients won\'t know your availability') }}</span>
                </template>
            </p>

            {{-- Save Schedule button --}}
            <button
                @click="$action('{{ url('/dashboard/setup/schedule/save') }}', { include: ['scheduleData'] })"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors duration-200 shadow-sm"
            >
                <span x-show="!$fetching()">
                    <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    {{ __('Save Schedule') }}
                </span>
                <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
            </button>
        </div>
    </div>

    {{-- Divider --}}
    <div class="border-t border-outline dark:border-outline my-6"></div>

    {{-- ===== FIRST MEAL SECTION ===== --}}
    <div>
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-full bg-secondary-subtle flex items-center justify-center shrink-0">
                {{-- Lucide: utensils --}}
                <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path><path d="M7 2v20"></path><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path></svg>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-on-surface-strong">{{ __('Your First Meal') }}</h3>
                <p class="text-sm text-on-surface">{{ __('Create at least one meal with a component to complete your setup.') }}</p>
            </div>
        </div>

        {{-- Existing meals display --}}
        <template x-if="mealsData.length > 0">
            <div class="mb-4 space-y-3">
                <h4 class="text-sm font-semibold text-on-surface-strong">{{ __('Your Meals') }}</h4>
                <template x-for="(meal, mealIndex) in mealsData" :key="meal.id">
                    <div class="p-3 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-on-surface-strong truncate" x-text="meal.name_en"></p>
                                <p class="text-xs text-on-surface mt-0.5" x-text="meal.name_fr"></p>
                                <p class="text-sm font-semibold text-primary mt-1">
                                    <span x-text="meal.price.toLocaleString()"></span> XAF
                                </p>
                            </div>
                            <div class="shrink-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-subtle text-success">
                                    {{ __('Active') }}
                                </span>
                            </div>
                        </div>
                        {{-- Component list --}}
                        <template x-if="meal.components && meal.components.length > 0">
                            <div class="mt-2 pt-2 border-t border-outline/50 dark:border-outline/50">
                                <p class="text-xs text-on-surface/70 mb-1">{{ __('Components:') }}</p>
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="comp in meal.components" :key="comp.id">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs bg-surface-alt dark:bg-surface-alt text-on-surface border border-outline/30 dark:border-outline/30" x-text="comp.name_en"></span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        {{-- Add another meal toggle --}}
        <template x-if="mealsData.length > 0 && !showMealForm">
            <button
                @click="showMealForm = true"
                class="w-full py-3 px-4 border-2 border-dashed border-outline dark:border-outline rounded-lg text-sm font-medium text-on-surface hover:text-primary hover:border-primary transition-colors duration-200 flex items-center justify-center gap-2"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                {{ __('Add Another Meal') }}
            </button>
        </template>

        {{-- Meal Creation Form --}}
        <div x-show="showMealForm" x-transition class="space-y-4">
            <h4 class="text-sm font-semibold text-on-surface-strong flex items-center gap-2">
                <svg class="w-4 h-4 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                <template x-if="mealsData.length === 0">
                    <span>{{ __('Create Your First Meal') }}</span>
                </template>
                <template x-if="mealsData.length > 0">
                    <span>{{ __('Add Another Meal') }}</span>
                </template>
            </h4>

            {{-- Meal Name (EN) --}}
            <div>
                <label class="block text-sm font-medium text-on-surface-strong mb-1">
                    {{ __('Meal Name (English)') }} <span class="text-danger">*</span>
                </label>
                <input
                    type="text"
                    x-model="meal_name_en"
                    x-name="meal_name_en"
                    placeholder="{{ __('e.g., Ndole & Plantain') }}"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:ring-2 focus:ring-primary/50 focus:border-primary"
                >
                <p x-message="meal_name_en" class="mt-1 text-xs text-danger"></p>
            </div>

            {{-- Meal Name (FR) --}}
            <div>
                <label class="block text-sm font-medium text-on-surface-strong mb-1">
                    {{ __('Meal Name (French)') }} <span class="text-danger">*</span>
                </label>
                <input
                    type="text"
                    x-model="meal_name_fr"
                    x-name="meal_name_fr"
                    placeholder="{{ __('e.g., Ndole et Plantain') }}"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:ring-2 focus:ring-primary/50 focus:border-primary"
                >
                <p x-message="meal_name_fr" class="mt-1 text-xs text-danger"></p>
            </div>

            {{-- Meal Description (EN) - Optional --}}
            <div>
                <label class="block text-sm font-medium text-on-surface-strong mb-1">
                    {{ __('Description (English)') }}
                    <span class="text-on-surface/50 text-xs font-normal">{{ __('Optional') }}</span>
                </label>
                <textarea
                    x-model="meal_description_en"
                    rows="2"
                    placeholder="{{ __('Describe your meal...') }}"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:ring-2 focus:ring-primary/50 focus:border-primary resize-none"
                ></textarea>
            </div>

            {{-- Meal Description (FR) - Optional --}}
            <div>
                <label class="block text-sm font-medium text-on-surface-strong mb-1">
                    {{ __('Description (French)') }}
                    <span class="text-on-surface/50 text-xs font-normal">{{ __('Optional') }}</span>
                </label>
                <textarea
                    x-model="meal_description_fr"
                    rows="2"
                    placeholder="{{ __('Decrivez votre plat...') }}"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:ring-2 focus:ring-primary/50 focus:border-primary resize-none"
                ></textarea>
            </div>

            {{-- Price --}}
            <div>
                <label class="block text-sm font-medium text-on-surface-strong mb-1">
                    {{ __('Price') }} <span class="text-danger">*</span>
                </label>
                <div class="relative">
                    <input
                        type="number"
                        x-model="meal_price"
                        x-name="meal_price"
                        min="1"
                        step="1"
                        placeholder="0"
                        class="w-full px-3 py-2.5 pr-14 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:ring-2 focus:ring-primary/50 focus:border-primary"
                    >
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-medium text-on-surface/60">XAF</span>
                </div>
                <p x-message="meal_price" class="mt-1 text-xs text-danger"></p>
                {{-- High price warning --}}
                <template x-if="meal_price && parseInt(meal_price) > 50000">
                    <p class="mt-1 text-xs text-warning flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        {{ __('This seems like an unusually high price. Please double-check.') }}
                    </p>
                </template>
            </div>

            {{-- Components Section --}}
            <div class="border-t border-outline dark:border-outline pt-4">
                <div class="flex items-center justify-between mb-3">
                    <label class="text-sm font-semibold text-on-surface-strong">
                        {{ __('Components') }} <span class="text-danger">*</span>
                    </label>
                    <span class="text-xs text-on-surface/60">
                        {{ __('At least 1 required') }}
                    </span>
                </div>

                <p x-message="components" class="mb-2 text-xs text-danger"></p>

                <div class="space-y-3">
                    <template x-for="(component, compIndex) in components" :key="compIndex">
                        <div class="p-3 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-medium text-on-surface/70">
                                    {{ __('Component') }} <span x-text="compIndex + 1"></span>
                                </span>
                                <template x-if="components.length > 1">
                                    <button
                                        type="button"
                                        @click="removeComponent(compIndex)"
                                        class="text-danger hover:text-danger/80 transition-colors p-1"
                                        :aria-label="'{{ __('Remove component') }} ' + (compIndex + 1)"
                                    >
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                    </button>
                                </template>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-on-surface mb-1">
                                        {{ __('Name (English)') }} <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        x-model="component.name_en"
                                        placeholder="{{ __('e.g., Ndole with spinach') }}"
                                        class="w-full px-3 py-2 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:ring-2 focus:ring-primary/50 focus:border-primary"
                                    >
                                    <p x-message="`components.${compIndex}.name_en`" class="mt-1 text-xs text-danger"></p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-on-surface mb-1">
                                        {{ __('Name (French)') }} <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        x-model="component.name_fr"
                                        placeholder="{{ __('e.g., Ndole aux epinards') }}"
                                        class="w-full px-3 py-2 text-sm rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:ring-2 focus:ring-primary/50 focus:border-primary"
                                    >
                                    <p x-message="`components.${compIndex}.name_fr`" class="mt-1 text-xs text-danger"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Add Component button --}}
                <button
                    type="button"
                    @click="addComponent()"
                    class="mt-3 w-full py-2 px-3 border border-dashed border-outline dark:border-outline rounded-lg text-sm font-medium text-on-surface hover:text-primary hover:border-primary transition-colors duration-200 flex items-center justify-center gap-2"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                    {{ __('Add Component') }}
                </button>
            </div>

            {{-- Save Meal button --}}
            <div class="flex items-center justify-end gap-3 pt-2">
                <template x-if="mealsData.length > 0">
                    <button
                        type="button"
                        @click="showMealForm = false; meal_name_en = ''; meal_name_fr = ''; meal_description_en = ''; meal_description_fr = ''; meal_price = ''; components = [{name_en: '', name_fr: ''}];"
                        class="px-4 py-2.5 text-sm font-medium text-on-surface hover:text-on-surface-strong hover:bg-surface rounded-lg transition-colors duration-200"
                    >
                        {{ __('Cancel') }}
                    </button>
                </template>
                <button
                    @click="$action('{{ url('/dashboard/setup/meal/save') }}', { include: ['meal_name_en', 'meal_name_fr', 'meal_description_en', 'meal_description_fr', 'meal_price', 'components'] })"
                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold bg-primary text-on-primary rounded-lg hover:bg-primary-hover transition-colors duration-200 shadow-sm"
                >
                    <span x-show="!$fetching()">
                        <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        {{ __('Save Meal') }}
                    </span>
                    <span x-show="$fetching()" x-cloak>{{ __('Saving...') }}</span>
                </button>
            </div>
        </div>

        {{-- No meal warning --}}
        <template x-if="!hasMeal">
            <div class="mt-4 p-3 rounded-lg bg-warning-subtle text-warning text-sm flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                {{ __('Create at least one meal to go live.') }}
            </div>
        </template>
    </div>
</div>
