{{--
    Meal Components Section
    -----------------------
    F-118: Meal Component Creation
    F-119: Meal Component Edit
    F-120: Meal Component Delete

    Displays existing components and provides forms to add, edit, or delete them.
    Components are the sellable items (combos) within a meal.

    Business Rules:
    BR-278: Name required in both EN and FR
    BR-279: Name max 150 characters
    BR-280: Price required, minimum 1 XAF
    BR-281: Selling unit required
    BR-282: Standard units: plate, bowl, pot, cup, piece, portion, serving, pack
    BR-283: Min quantity defaults to 0
    BR-284: Max quantity defaults to unlimited (null)
    BR-285: Available quantity defaults to unlimited (null)
    BR-286: At least 1 component to go live (validated in F-112)
    BR-290: Position field for display ordering
    BR-291: Default availability is true
    BR-292: All validation rules from F-118 apply to edits
    BR-293: Price changes apply to new orders only
    BR-294: Name, selling unit, and quantity changes take effect immediately
    BR-295: Component edits are logged via Spatie Activitylog
    BR-296: Only users with manage-meals permission
    BR-297: If available quantity is edited to 0, auto-toggle to unavailable
    BR-298: Cannot delete the last component of a live meal
    BR-299: Cannot delete a component if pending orders include it
    BR-300: Components are hard-deleted
    BR-301: Confirmation dialog before deletion
    BR-302: Deletion logged via Spatie Activitylog
    BR-303: Only users with manage-meals permission
    BR-304: Remaining positions recalculated
    BR-305: Requirement rules cleaned up
--}}
<div
    class="bg-surface-alt dark:bg-surface-alt border border-outline dark:border-outline rounded-xl shadow-card p-6"
    x-data="{
        showAddForm: false,
        editingComponentId: null,
        comp_name_en: '',
        comp_name_fr: '',
        comp_price: '',
        comp_selling_unit: 'plate',
        comp_min_quantity: '',
        comp_max_quantity: '',
        comp_available_quantity: '',
        edit_comp_name_en: '',
        edit_comp_name_fr: '',
        edit_comp_price: '',
        edit_comp_selling_unit: 'plate',
        edit_comp_min_quantity: '',
        edit_comp_max_quantity: '',
        edit_comp_available_quantity: '',
        rule_type: 'requires_any_of',
        rule_target_ids: [],
        @if(($componentData['count'] ?? 0) > 0)
            @foreach($componentData['components'] as $comp)
                showRuleForm_{{ $comp->id }}: false,
            @endforeach
        @endif
        toggleAddForm() {
            this.showAddForm = !this.showAddForm;
            if (this.showAddForm) {
                this.editingComponentId = null;
                this.resetForm();
            }
        },
        resetForm() {
            this.comp_name_en = '';
            this.comp_name_fr = '';
            this.comp_price = '';
            this.comp_selling_unit = 'plate';
            this.comp_min_quantity = '';
            this.comp_max_quantity = '';
            this.comp_available_quantity = '';
        },
        resetRuleForm() {
            this.rule_type = 'requires_any_of';
            this.rule_target_ids = [];
        },
        startEdit(component) {
            this.editingComponentId = component.id;
            this.showAddForm = false;
            this.edit_comp_name_en = component.name_en;
            this.edit_comp_name_fr = component.name_fr;
            this.edit_comp_price = component.price;
            this.edit_comp_selling_unit = component.selling_unit;
            this.edit_comp_min_quantity = component.min_quantity > 0 ? component.min_quantity : '';
            this.edit_comp_max_quantity = component.max_quantity !== null ? component.max_quantity : '';
            this.edit_comp_available_quantity = component.available_quantity !== null ? component.available_quantity : '';
        },
        cancelEdit() {
            this.editingComponentId = null;
        },
        confirmDeleteId: null,
        confirmDeleteName: '',
        confirmDelete(id, name) {
            this.confirmDeleteId = id;
            this.confirmDeleteName = name;
        },
        cancelDelete() {
            this.confirmDeleteId = null;
            this.confirmDeleteName = '';
        },
        executeDelete() {
            if (this.confirmDeleteId) {
                $action('{{ url('/dashboard/meals/' . $meal->id . '/components') }}/' + this.confirmDeleteId, { method: 'DELETE' });
                this.confirmDeleteId = null;
                this.confirmDeleteName = '';
            }
        }
    }"
    x-sync="['comp_name_en', 'comp_name_fr', 'comp_price', 'comp_selling_unit', 'comp_min_quantity', 'comp_max_quantity', 'comp_available_quantity', 'edit_comp_name_en', 'edit_comp_name_fr', 'edit_comp_price', 'edit_comp_selling_unit', 'edit_comp_min_quantity', 'edit_comp_max_quantity', 'edit_comp_available_quantity', 'rule_type', 'rule_target_ids']"
>
    {{-- Section header --}}
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-full bg-secondary-subtle flex items-center justify-center">
                {{-- Lucide: layers (md=20) --}}
                <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
            </span>
            <div>
                <h3 class="text-lg font-semibold text-on-surface-strong">{{ __('Components') }}</h3>
                <p class="text-xs text-on-surface/60">
                    {{ trans_choice(':count component|:count components', $componentData['count'] ?? 0, ['count' => $componentData['count'] ?? 0]) }}
                </p>
            </div>
        </div>

        <button
            type="button"
            @click="toggleAddForm()"
            class="px-3 py-1.5 rounded-lg text-sm font-medium bg-primary text-on-primary hover:bg-primary-hover shadow-sm transition-colors duration-200 flex items-center gap-1.5"
        >
            <svg x-show="!showAddForm" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            <svg x-show="showAddForm" x-cloak class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            <span x-text="showAddForm ? '{{ __('Cancel') }}' : '{{ __('Add Component') }}'"></span>
        </button>
    </div>

    {{-- Add Component Form --}}
    <div
        x-show="showAddForm"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="mb-6 p-4 rounded-lg border border-primary/20 bg-primary-subtle/30"
    >
        <h4 class="text-sm font-semibold text-on-surface-strong mb-4">{{ __('New Component') }}</h4>

        <form @submit.prevent="$action('{{ url('/dashboard/meals/' . $meal->id . '/components') }}')">
            {{-- Name fields (EN / FR) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                {{-- Name EN --}}
                <div>
                    <label for="comp_name_en" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                        {{ __('Name (English)') }} <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        id="comp_name_en"
                        x-model="comp_name_en"
                        x-name="comp_name_en"
                        maxlength="150"
                        placeholder="{{ __('e.g. Ndole + Plantain') }}"
                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-sm"
                    >
                    <div class="flex items-center justify-between mt-1">
                        <p x-message="comp_name_en" class="text-xs text-danger"></p>
                        <span class="text-xs text-on-surface/50" x-text="comp_name_en.length + '/150'"></span>
                    </div>
                </div>

                {{-- Name FR --}}
                <div>
                    <label for="comp_name_fr" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                        {{ __('Name (French)') }} <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        id="comp_name_fr"
                        x-model="comp_name_fr"
                        x-name="comp_name_fr"
                        maxlength="150"
                        placeholder="{{ __('e.g. Ndole + Plantain') }}"
                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-sm"
                    >
                    <div class="flex items-center justify-between mt-1">
                        <p x-message="comp_name_fr" class="text-xs text-danger"></p>
                        <span class="text-xs text-on-surface/50" x-text="comp_name_fr.length + '/150'"></span>
                    </div>
                </div>
            </div>

            {{-- Price and Selling Unit --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                {{-- Price --}}
                <div>
                    <label for="comp_price" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                        {{ __('Price (XAF)') }} <span class="text-danger">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="number"
                            id="comp_price"
                            x-model.number="comp_price"
                            x-name="comp_price"
                            min="1"
                            step="1"
                            placeholder="{{ __('e.g. 1000') }}"
                            class="w-full px-3 py-2 pr-14 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-sm"
                        >
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-on-surface/50">XAF</span>
                    </div>
                    <p x-message="comp_price" class="text-xs text-danger mt-1"></p>
                </div>

                {{-- Selling Unit --}}
                <div>
                    <label for="comp_selling_unit" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                        {{ __('Selling Unit') }} <span class="text-danger">*</span>
                    </label>
                    <select
                        id="comp_selling_unit"
                        x-model="comp_selling_unit"
                        x-name="comp_selling_unit"
                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-sm"
                    >
                        @foreach($availableUnits as $unit)
                            <option value="{{ $unit['value'] }}">{{ $unit['label'] }}</option>
                        @endforeach
                    </select>
                    <p x-message="comp_selling_unit" class="text-xs text-danger mt-1"></p>
                </div>
            </div>

            {{-- Quantity fields --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                {{-- Min Quantity --}}
                <div>
                    <label for="comp_min_quantity" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                        {{ __('Min Quantity') }}
                    </label>
                    <input
                        type="number"
                        id="comp_min_quantity"
                        x-model.number="comp_min_quantity"
                        x-name="comp_min_quantity"
                        min="0"
                        step="1"
                        placeholder="{{ __('0 (no min)') }}"
                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-sm"
                    >
                    <p x-message="comp_min_quantity" class="text-xs text-danger mt-1"></p>
                </div>

                {{-- Max Quantity --}}
                <div>
                    <label for="comp_max_quantity" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                        {{ __('Max Quantity') }}
                    </label>
                    <input
                        type="number"
                        id="comp_max_quantity"
                        x-model.number="comp_max_quantity"
                        x-name="comp_max_quantity"
                        min="1"
                        step="1"
                        placeholder="{{ __('Unlimited') }}"
                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-sm"
                    >
                    <p x-message="comp_max_quantity" class="text-xs text-danger mt-1"></p>
                </div>

                {{-- Available Quantity --}}
                <div>
                    <label for="comp_available_quantity" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                        {{ __('Available Qty') }}
                    </label>
                    <input
                        type="number"
                        id="comp_available_quantity"
                        x-model.number="comp_available_quantity"
                        x-name="comp_available_quantity"
                        min="0"
                        step="1"
                        placeholder="{{ __('Unlimited') }}"
                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-sm"
                    >
                    <p x-message="comp_available_quantity" class="text-xs text-danger mt-1"></p>
                </div>
            </div>

            <p class="text-xs text-on-surface/50 mb-4">
                {{ __('Leave quantity fields empty for unlimited.') }}
            </p>

            {{-- Submit button --}}
            <div class="flex items-center justify-end">
                <button
                    type="submit"
                    class="px-4 py-2 rounded-lg text-sm font-medium bg-primary text-on-primary hover:bg-primary-hover shadow-sm transition-colors duration-200 flex items-center gap-2"
                >
                    <span x-show="!$fetching()">
                        {{-- Lucide: plus (sm=16) --}}
                        <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        {{ __('Add Component') }}
                    </span>
                    <span x-show="$fetching()" x-cloak class="flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        {{ __('Adding...') }}
                    </span>
                </button>
            </div>
        </form>
    </div>

    {{-- Component List --}}
    @if(($componentData['count'] ?? 0) > 0)
        <div class="space-y-3">
            @foreach($componentData['components'] as $component)
                <div class="rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface transition-colors duration-200">
                    {{-- Component display row --}}
                    <div
                        x-show="editingComponentId !== {{ $component->id }}"
                        class="flex items-center justify-between p-3 hover:bg-surface-alt dark:hover:bg-surface-alt rounded-lg transition-colors duration-200"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <h4 class="text-sm font-medium text-on-surface-strong truncate">
                                    {{ $component->name }}
                                </h4>
                                @if(!$component->is_available)
                                    <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-medium bg-danger-subtle text-danger">
                                        {{ __('Unavailable') }}
                                    </span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-3 text-xs text-on-surface/60">
                                <span class="font-medium text-on-surface-strong">
                                    {{ $component->formatted_price }}
                                </span>
                                <span class="flex items-center gap-1">
                                    {{-- Lucide: package (xs=14) --}}
                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                                    {{ __('per :unit', ['unit' => $component->unit_label]) }}
                                </span>
                                @if($component->min_quantity > 0)
                                    <span>{{ __('Min: :qty', ['qty' => $component->min_quantity]) }}</span>
                                @endif
                                @if(!$component->hasUnlimitedMaxQuantity())
                                    <span>{{ __('Max: :qty', ['qty' => $component->max_quantity]) }}</span>
                                @endif
                                @if(!$component->hasUnlimitedAvailableQuantity())
                                    <span>{{ __('Available: :qty', ['qty' => $component->available_quantity]) }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Action buttons --}}
                        <div class="flex items-center gap-1 ml-3 shrink-0">
                            {{-- Edit (F-119) --}}
                            <button
                                type="button"
                                @click="startEdit({{ json_encode([
                                    'id' => $component->id,
                                    'name_en' => $component->name_en,
                                    'name_fr' => $component->name_fr,
                                    'price' => $component->price,
                                    'selling_unit' => $component->selling_unit,
                                    'min_quantity' => $component->min_quantity,
                                    'max_quantity' => $component->max_quantity,
                                    'available_quantity' => $component->available_quantity,
                                ]) }})"
                                class="p-1.5 rounded text-on-surface/50 hover:text-primary hover:bg-primary-subtle transition-colors duration-200"
                                title="{{ __('Edit component') }}"
                            >
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.855z"></path><path d="m15 5 3 3"></path></svg>
                            </button>

                            {{-- Delete (F-120) --}}
                            @php
                                $deleteInfo = $componentDeleteInfo[$component->id] ?? ['can_delete' => true];
                                $canDelete = $deleteInfo['can_delete'];
                                $deleteReason = $deleteInfo['reason'] ?? '';
                            @endphp
                            <button
                                type="button"
                                @if($canDelete)
                                    @click="confirmDelete({{ $component->id }}, '{{ addslashes($component->name) }}')"
                                @endif
                                class="p-1.5 rounded transition-colors duration-200 {{ $canDelete ? 'text-on-surface/50 hover:text-danger hover:bg-danger-subtle' : 'text-on-surface/20 cursor-not-allowed' }}"
                                title="{{ $canDelete ? __('Delete component') : $deleteReason }}"
                                {{ $canDelete ? '' : 'disabled' }}
                            >
                                {{-- Lucide: trash-2 (sm=16) --}}
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                            </button>
                        </div>
                    </div>

                    {{-- F-122: Requirement Rules Section --}}
                    @if(isset($componentRulesData[$component->id]))
                        <div x-show="editingComponentId !== {{ $component->id }}" class="px-3 pb-3">
                            @include('cook.meals._requirement-rules', [
                                'component' => $component,
                                'rulesInfo' => $componentRulesData[$component->id],
                            ])
                        </div>
                    @endif

                    {{-- F-119: Inline Edit Form --}}
                    <div
                        x-show="editingComponentId === {{ $component->id }}"
                        x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="p-4 border-t border-outline dark:border-outline"
                    >
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.855z"></path><path d="m15 5 3 3"></path></svg>
                            <h4 class="text-sm font-semibold text-on-surface-strong">{{ __('Edit Component') }}</h4>
                        </div>

                        <form @submit.prevent="$action('{{ url('/dashboard/meals/' . $meal->id . '/components/' . $component->id) }}', { method: 'PUT' })">
                            {{-- Name fields (EN / FR) --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                {{-- Name EN --}}
                                <div>
                                    <label for="edit_comp_name_en_{{ $component->id }}" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                                        {{ __('Name (English)') }} <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="edit_comp_name_en_{{ $component->id }}"
                                        x-model="edit_comp_name_en"
                                        x-name="edit_comp_name_en"
                                        maxlength="150"
                                        placeholder="{{ __('e.g. Ndole + Plantain') }}"
                                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-sm"
                                    >
                                    <div class="flex items-center justify-between mt-1">
                                        <p x-message="edit_comp_name_en" class="text-xs text-danger"></p>
                                        <span class="text-xs text-on-surface/50" x-text="edit_comp_name_en.length + '/150'"></span>
                                    </div>
                                </div>

                                {{-- Name FR --}}
                                <div>
                                    <label for="edit_comp_name_fr_{{ $component->id }}" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                                        {{ __('Name (French)') }} <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="edit_comp_name_fr_{{ $component->id }}"
                                        x-model="edit_comp_name_fr"
                                        x-name="edit_comp_name_fr"
                                        maxlength="150"
                                        placeholder="{{ __('e.g. Ndole + Plantain') }}"
                                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-sm"
                                    >
                                    <div class="flex items-center justify-between mt-1">
                                        <p x-message="edit_comp_name_fr" class="text-xs text-danger"></p>
                                        <span class="text-xs text-on-surface/50" x-text="edit_comp_name_fr.length + '/150'"></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Price and Selling Unit --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                {{-- Price --}}
                                <div>
                                    <label for="edit_comp_price_{{ $component->id }}" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                                        {{ __('Price (XAF)') }} <span class="text-danger">*</span>
                                    </label>
                                    <div class="relative">
                                        <input
                                            type="number"
                                            id="edit_comp_price_{{ $component->id }}"
                                            x-model.number="edit_comp_price"
                                            x-name="edit_comp_price"
                                            min="1"
                                            step="1"
                                            placeholder="{{ __('e.g. 1000') }}"
                                            class="w-full px-3 py-2 pr-14 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-sm"
                                        >
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-on-surface/50">XAF</span>
                                    </div>
                                    <p x-message="edit_comp_price" class="text-xs text-danger mt-1"></p>
                                    <p class="text-xs text-info mt-1">
                                        {{-- Lucide: info (xs=14) --}}
                                        <svg class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                                        {{ __('Price changes apply to new orders only.') }}
                                    </p>
                                </div>

                                {{-- Selling Unit --}}
                                <div>
                                    <label for="edit_comp_selling_unit_{{ $component->id }}" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                                        {{ __('Selling Unit') }} <span class="text-danger">*</span>
                                    </label>
                                    <select
                                        id="edit_comp_selling_unit_{{ $component->id }}"
                                        x-model="edit_comp_selling_unit"
                                        x-name="edit_comp_selling_unit"
                                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-sm"
                                    >
                                        @foreach($availableUnits as $unit)
                                            <option value="{{ $unit['value'] }}">{{ $unit['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <p x-message="edit_comp_selling_unit" class="text-xs text-danger mt-1"></p>
                                </div>
                            </div>

                            {{-- Quantity fields --}}
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                                {{-- Min Quantity --}}
                                <div>
                                    <label for="edit_comp_min_quantity_{{ $component->id }}" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                                        {{ __('Min Quantity') }}
                                    </label>
                                    <input
                                        type="number"
                                        id="edit_comp_min_quantity_{{ $component->id }}"
                                        x-model.number="edit_comp_min_quantity"
                                        x-name="edit_comp_min_quantity"
                                        min="0"
                                        step="1"
                                        placeholder="{{ __('0 (no min)') }}"
                                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-sm"
                                    >
                                    <p x-message="edit_comp_min_quantity" class="text-xs text-danger mt-1"></p>
                                </div>

                                {{-- Max Quantity --}}
                                <div>
                                    <label for="edit_comp_max_quantity_{{ $component->id }}" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                                        {{ __('Max Quantity') }}
                                    </label>
                                    <input
                                        type="number"
                                        id="edit_comp_max_quantity_{{ $component->id }}"
                                        x-model.number="edit_comp_max_quantity"
                                        x-name="edit_comp_max_quantity"
                                        min="1"
                                        step="1"
                                        placeholder="{{ __('Unlimited') }}"
                                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-sm"
                                    >
                                    <p x-message="edit_comp_max_quantity" class="text-xs text-danger mt-1"></p>
                                </div>

                                {{-- Available Quantity --}}
                                <div>
                                    <label for="edit_comp_available_quantity_{{ $component->id }}" class="block text-sm font-medium text-on-surface-strong mb-1.5">
                                        {{ __('Available Qty') }}
                                    </label>
                                    <input
                                        type="number"
                                        id="edit_comp_available_quantity_{{ $component->id }}"
                                        x-model.number="edit_comp_available_quantity"
                                        x-name="edit_comp_available_quantity"
                                        min="0"
                                        step="1"
                                        placeholder="{{ __('Unlimited') }}"
                                        class="w-full px-3 py-2 rounded-lg border border-outline dark:border-outline bg-surface dark:bg-surface text-on-surface placeholder:text-on-surface/40 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors duration-200 text-sm"
                                    >
                                    <p x-message="edit_comp_available_quantity" class="text-xs text-danger mt-1"></p>
                                </div>
                            </div>

                            <p class="text-xs text-on-surface/50 mb-4">
                                {{ __('Leave quantity fields empty for unlimited.') }}
                            </p>

                            {{-- Action buttons --}}
                            <div class="flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    @click="cancelEdit()"
                                    class="px-4 py-2 rounded-lg text-sm font-medium text-on-surface bg-surface dark:bg-surface border border-outline dark:border-outline hover:bg-surface-alt transition-colors duration-200"
                                >
                                    {{ __('Cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    class="px-4 py-2 rounded-lg text-sm font-medium bg-primary text-on-primary hover:bg-primary-hover shadow-sm transition-colors duration-200 flex items-center gap-2"
                                >
                                    <span x-show="!$fetching()">
                                        {{-- Lucide: save (sm=16) --}}
                                        <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                                        {{ __('Save Changes') }}
                                    </span>
                                    <span x-show="$fetching()" x-cloak class="flex items-center gap-2">
                                        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        {{ __('Saving...') }}
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- Empty state --}}
        <div class="text-center py-8">
            <div class="w-12 h-12 rounded-full bg-surface dark:bg-surface mx-auto mb-3 flex items-center justify-center">
                {{-- Lucide: layers (lg=24) --}}
                <svg class="w-6 h-6 text-on-surface/30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
            </div>
            <p class="text-sm text-on-surface/60 mb-1">{{ __('No components yet') }}</p>
            <p class="text-xs text-on-surface/40">{{ __('Add at least one component before going live.') }}</p>
        </div>
    @endif

    {{-- F-120: Delete Confirmation Modal (BR-301) --}}
    <div
        x-show="confirmDeleteId !== null"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-component-dialog-title"
    >
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50" @click="cancelDelete()"></div>

        {{-- Modal content --}}
        <div
            x-show="confirmDeleteId !== null"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative bg-surface dark:bg-surface rounded-xl shadow-lg border border-outline dark:border-outline max-w-sm w-full p-6"
        >
            {{-- Warning icon --}}
            <div class="w-12 h-12 rounded-full bg-danger-subtle mx-auto mb-4 flex items-center justify-center">
                {{-- Lucide: alert-triangle (lg=24) --}}
                <svg class="w-6 h-6 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            </div>

            <h3 id="delete-component-dialog-title" class="text-lg font-semibold text-on-surface-strong text-center mb-2">
                {{ __('Delete Component') }}
            </h3>

            <p class="text-sm text-on-surface/70 text-center mb-6">
                {{ __('Are you sure you want to delete') }}
                <span class="font-medium text-on-surface-strong" x-text="confirmDeleteName"></span>?
                {{ __('This action cannot be undone.') }}
            </p>

            {{-- Modal buttons --}}
            <div class="flex items-center justify-end gap-3">
                <button
                    type="button"
                    @click="cancelDelete()"
                    class="px-4 py-2 rounded-lg text-sm font-medium text-on-surface bg-surface dark:bg-surface border border-outline dark:border-outline hover:bg-surface-alt transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="button"
                    @click="executeDelete()"
                    class="px-4 py-2 rounded-lg text-sm font-medium bg-danger text-on-danger hover:bg-danger/90 shadow-sm transition-colors duration-200 flex items-center gap-2"
                >
                    <span x-show="!$fetching()">
                        {{-- Lucide: trash-2 (sm=16) --}}
                        <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                        {{ __('Delete') }}
                    </span>
                    <span x-show="$fetching()" x-cloak class="flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        {{ __('Deleting...') }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
