{{--
    Meal Tag Assignment
    -------------------
    F-114: Meal Tag Assignment

    Multi-select tag assignment section on the meal edit page.
    Allows cook to assign/remove tags from their existing tag list.

    BR-244: Maximum 10 tags per meal
    BR-245: Tags are assigned from the cook's existing tag list (tenant-scoped)
    BR-246: Tag assignment is a many-to-many relationship
    BR-247: Tags can be assigned and removed without page reload
    BR-248: Removing a tag from a meal does not delete the tag itself
    BR-249: Only users with manage-meals permission
    BR-250: Tag assignment changes are logged via Spatie Activitylog
    BR-251: Tags are used for filtering on the tenant landing page and discovery page
--}}
<div
    class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-card p-6"
    x-data="{
        selected_tag_ids: {{ json_encode($tagData['assignedTagIds']) }},
        showDropdown: false,
        searchQuery: '',

        get tagCount() {
            return this.selected_tag_ids.length;
        },

        get canAddMore() {
            return this.selected_tag_ids.length < {{ $tagData['maxTags'] }};
        },

        get filteredTags() {
            const query = this.searchQuery.toLowerCase().trim();
            if (!query) return this.allTags;
            return this.allTags.filter(tag =>
                tag.name.toLowerCase().includes(query)
            );
        },

        allTags: {{ Js::from($tagData['availableTags']->map(fn($tag) => [
            'id' => $tag->id,
            'name' => $tag->name,
            'name_en' => $tag->name_en,
            'name_fr' => $tag->name_fr,
        ])) }},

        isSelected(tagId) {
            return this.selected_tag_ids.includes(tagId);
        },

        toggleTag(tagId) {
            if (this.isSelected(tagId)) {
                this.selected_tag_ids = this.selected_tag_ids.filter(id => id !== tagId);
            } else {
                if (!this.canAddMore) return;
                this.selected_tag_ids = [...this.selected_tag_ids, tagId];
            }
        },

        removeTag(tagId) {
            this.selected_tag_ids = this.selected_tag_ids.filter(id => id !== tagId);
        },

        getTagName(tagId) {
            const tag = this.allTags.find(t => t.id === tagId);
            return tag ? tag.name : '';
        },

        saveTags() {
            $action('{{ url('/dashboard/meals/' . $meal->id . '/tags') }}', {
                include: ['selected_tag_ids']
            });
        },

        closeDropdown() {
            this.showDropdown = false;
            this.searchQuery = '';
        }
    }"
    x-sync="['selected_tag_ids']"
    x-on:click.outside="closeDropdown()"
    x-on:keydown.escape.window="closeDropdown()"
>
    {{-- Section header --}}
    <div class="flex items-center gap-3 mb-5">
        <span class="w-8 h-8 rounded-full bg-secondary-subtle flex items-center justify-center">
            {{-- Lucide: tags (md=20) --}}
            <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 5 6.3 6.3a2.4 2.4 0 0 1 0 3.4L17 19"></path><path d="M9.586 5.586A2 2 0 0 0 8.172 5H3a1 1 0 0 0-1 1v5.172a2 2 0 0 0 .586 1.414L8.29 18.29a2.426 2.426 0 0 0 3.42 0l3.58-3.58a2.426 2.426 0 0 0 0-3.42z"></path><circle cx="6.5" cy="9.5" r=".5" fill="currentColor"></circle></svg>
        </span>
        <div class="flex-1">
            <h3 class="text-lg font-semibold text-on-surface-strong">{{ __('Tags') }}</h3>
            <p class="text-sm text-on-surface/60">
                <span x-text="tagCount"></span>/{{ $tagData['maxTags'] }} {{ __('tags used') }}
            </p>
        </div>
    </div>

    @if($tagData['availableTags']->isEmpty())
        {{-- No tags state (Scenario 4) --}}
        <div class="bg-surface dark:bg-surface rounded-lg border border-outline dark:border-outline p-6 text-center">
            <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-primary-subtle flex items-center justify-center">
                <svg class="w-6 h-6 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"></path><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"></circle></svg>
            </div>
            <p class="text-sm font-medium text-on-surface-strong mb-1">{{ __('No tags created yet.') }}</p>
            <p class="text-sm text-on-surface/60 mb-3">{{ __('Create tags first to assign them to your meals.') }}</p>
            <a
                href="{{ url('/dashboard/tags') }}"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:text-primary-hover transition-colors duration-200"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                {{ __('Manage Tags') }}
            </a>
        </div>
    @else
        {{-- Assigned tags chips --}}
        <div class="mb-4">
            <div class="flex flex-wrap gap-2 min-h-[36px]" role="list" aria-label="{{ __('Assigned tags') }}">
                <template x-for="tagId in selected_tag_ids" :key="tagId">
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium bg-primary-subtle text-primary"
                        role="listitem"
                    >
                        <span x-text="getTagName(tagId)"></span>
                        <button
                            type="button"
                            @click="removeTag(tagId)"
                            class="w-4 h-4 rounded-full hover:bg-primary/20 flex items-center justify-center transition-colors duration-150"
                            :aria-label="'{{ __('Remove tag') }}: ' + getTagName(tagId)"
                        >
                            {{-- Lucide: x (xs=14) --}}
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                        </button>
                    </span>
                </template>

                {{-- Empty state when no tags assigned --}}
                <template x-if="selected_tag_ids.length === 0">
                    <span class="text-sm text-on-surface/50 py-1.5">{{ __('No tags assigned. Select tags below.') }}</span>
                </template>
            </div>
        </div>

        {{-- Tag selector dropdown --}}
        <div class="relative mb-4">
            <button
                type="button"
                @click="showDropdown = !showDropdown"
                :disabled="!canAddMore && selected_tag_ids.length >= {{ $tagData['maxTags'] }}"
                class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface hover:border-primary/50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span class="text-sm" x-text="canAddMore ? '{{ __('Select tags to assign...') }}' : '{{ __('Maximum tags reached') }}'"></span>
                {{-- Lucide: chevron-down (sm=16) --}}
                <svg class="w-4 h-4 text-on-surface/50 transition-transform duration-200" :class="{ 'rotate-180': showDropdown }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
            </button>

            {{-- Dropdown panel --}}
            <div
                x-show="showDropdown"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                x-cloak
                class="absolute z-20 mt-1 w-full bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-lg shadow-dropdown overflow-hidden"
            >
                {{-- Search input --}}
                <div class="p-2 border-b border-outline dark:border-outline">
                    <div class="relative">
                        <svg class="w-4 h-4 text-on-surface/40 absolute left-3 top-1/2 -translate-y-1/2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                        <input
                            type="text"
                            x-model="searchQuery"
                            placeholder="{{ __('Search tags...') }}"
                            class="w-full pl-9 pr-3 py-2 rounded-md border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface text-sm placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200"
                        >
                    </div>
                </div>

                {{-- Tag options list --}}
                <div class="max-h-48 overflow-y-auto p-1">
                    <template x-for="tag in filteredTags" :key="tag.id">
                        <button
                            type="button"
                            @click="toggleTag(tag.id)"
                            :disabled="!isSelected(tag.id) && !canAddMore"
                            :class="{
                                'bg-primary-subtle': isSelected(tag.id),
                                'opacity-50 cursor-not-allowed': !isSelected(tag.id) && !canAddMore,
                                'hover:bg-surface dark:hover:bg-surface': !isSelected(tag.id) && canAddMore
                            }"
                            class="w-full flex items-center gap-3 px-3 py-2 rounded-md text-sm text-left transition-colors duration-150"
                        >
                            {{-- Checkbox indicator --}}
                            <span
                                :class="isSelected(tag.id) ? 'bg-primary border-primary' : 'border-outline dark:border-outline bg-surface dark:bg-surface'"
                                class="w-4 h-4 rounded border flex items-center justify-center shrink-0 transition-colors duration-150"
                            >
                                <svg x-show="isSelected(tag.id)" class="w-3 h-3 text-on-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            </span>
                            <span class="font-medium text-on-surface-strong" x-text="tag.name"></span>
                        </button>
                    </template>

                    {{-- No results --}}
                    <template x-if="filteredTags.length === 0">
                        <p class="px-3 py-4 text-sm text-on-surface/50 text-center">{{ __('No tags match your search.') }}</p>
                    </template>
                </div>

                {{-- Footer with manage tags link --}}
                <div class="border-t border-outline dark:border-outline p-2">
                    <a
                        href="{{ url('/dashboard/tags') }}"
                        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium text-primary hover:bg-primary-subtle transition-colors duration-200"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        {{ __('Manage Tags') }}
                    </a>
                </div>
            </div>
        </div>

        {{-- Validation error --}}
        <p x-message="selected_tag_ids" class="text-sm text-danger mb-4"></p>

        {{-- Save button --}}
        <div class="flex items-center justify-between pt-4 border-t border-outline dark:border-outline">
            <p class="text-xs text-on-surface/50">
                {{ __('Changes are saved when you click Save Tags.') }}
            </p>
            <button
                type="button"
                @click="saveTags()"
                :disabled="$fetching()"
                class="px-4 py-2 rounded-lg text-sm font-medium bg-primary text-on-primary hover:bg-primary-hover shadow-sm transition-colors duration-200 disabled:opacity-50 flex items-center gap-2"
            >
                <span x-show="!$fetching()">
                    {{-- Lucide: save (sm=16) --}}
                    <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    {{ __('Save Tags') }}
                </span>
                <span x-show="$fetching()" x-cloak class="flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    {{ __('Saving...') }}
                </span>
            </button>
        </div>
    @endif
</div>
