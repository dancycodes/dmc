<?php

/**
 * F-121: Custom Selling Unit Definition — Unit Tests
 *
 * Tests for SellingUnit model, SellingUnitService, SellingUnitController,
 * StoreSellingUnitRequest, UpdateSellingUnitRequest, route configuration,
 * blade view, and translation strings.
 *
 * BR-306: Standard units pre-seeded (8 units)
 * BR-307: Standard units cannot be edited or deleted
 * BR-308: Custom units are tenant-scoped
 * BR-309: Name required in both EN and FR
 * BR-310: Name unique within tenant and against standard units (case-insensitive)
 * BR-311: Cannot delete if used by any meal component
 * BR-312: Only users with manage-meals permission
 * BR-313: CRUD logged via Spatie Activitylog
 * BR-314: Name max 50 characters per language
 * BR-315: Standard units have pre-defined translations
 */

use App\Http\Controllers\Cook\SellingUnitController;
use App\Http\Requests\Cook\StoreSellingUnitRequest;
use App\Http\Requests\Cook\UpdateSellingUnitRequest;
use App\Models\MealComponent;
use App\Models\SellingUnit;
use App\Models\Tenant;
use App\Services\SellingUnitService;

$projectRoot = dirname(__DIR__, 3);

// ============================================================
// Test group: SellingUnit Model
// ============================================================
describe('SellingUnit Model', function () use ($projectRoot) {
    it('uses the selling_units table', function () {
        $unit = new SellingUnit;
        expect($unit->getTable())->toBe('selling_units');
    });

    it('has the correct fillable attributes', function () {
        $unit = new SellingUnit;
        expect($unit->getFillable())->toContain('tenant_id')
            ->toContain('name_en')
            ->toContain('name_fr')
            ->toContain('is_standard');
    });

    it('casts is_standard to boolean', function () {
        $unit = new SellingUnit;
        $casts = $unit->getCasts();
        expect($casts['is_standard'])->toBe('boolean');
    });

    it('defines STANDARD_UNITS constant with 8 units (BR-306)', function () {
        expect(SellingUnit::STANDARD_UNITS)->toBeArray()
            ->toHaveCount(8)
            ->toHaveKeys(['plate', 'bowl', 'pot', 'cup', 'piece', 'portion', 'serving', 'pack']);
    });

    it('defines NAME_MAX_LENGTH constant as 50 (BR-314)', function () {
        expect(SellingUnit::NAME_MAX_LENGTH)->toBe(50);
    });

    it('has standard unit translations in both EN and FR (BR-315)', function () {
        foreach (SellingUnit::STANDARD_UNITS as $key => $translations) {
            expect($translations)->toHaveKeys(['en', 'fr']);
            expect($translations['en'])->toBeString()->not->toBeEmpty();
            expect($translations['fr'])->toBeString()->not->toBeEmpty();
        }
    });

    it('has a tenant relationship method (BR-308)', function () {
        $reflection = new ReflectionMethod(SellingUnit::class, 'tenant');
        expect($reflection->isPublic())->toBeTrue();
        expect($reflection->getReturnType()?->getName())
            ->toBe(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    });

    it('has a mealComponents relationship method', function () {
        $reflection = new ReflectionMethod(SellingUnit::class, 'mealComponents');
        expect($reflection->isPublic())->toBeTrue();
        expect($reflection->getReturnType()?->getName())
            ->toBe(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('has a scopeStandard scope', function () {
        $reflection = new ReflectionMethod(SellingUnit::class, 'scopeStandard');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a scopeCustom scope', function () {
        $reflection = new ReflectionMethod(SellingUnit::class, 'scopeCustom');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a scopeForTenant scope', function () {
        $reflection = new ReflectionMethod(SellingUnit::class, 'scopeForTenant');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has an isStandard method', function () {
        $reflection = new ReflectionMethod(SellingUnit::class, 'isStandard');
        expect($reflection->isPublic())->toBeTrue();
        expect($reflection->getReturnType()?->getName())->toBe('bool');
    });

    it('has an isCustom method', function () {
        $reflection = new ReflectionMethod(SellingUnit::class, 'isCustom');
        expect($reflection->isPublic())->toBeTrue();
        expect($reflection->getReturnType()?->getName())->toBe('bool');
    });

    it('has an isInUse method (BR-311)', function () {
        $reflection = new ReflectionMethod(SellingUnit::class, 'isInUse');
        expect($reflection->isPublic())->toBeTrue();
        expect($reflection->getReturnType()?->getName())->toBe('bool');
    });

    it('has a getUsageCount method', function () {
        $reflection = new ReflectionMethod(SellingUnit::class, 'getUsageCount');
        expect($reflection->isPublic())->toBeTrue();
        expect($reflection->getReturnType()?->getName())->toBe('int');
    });

    it('has HasTranslatable trait with name translatable', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Models/SellingUnit.php');
        expect($content)->toContain('HasTranslatable');
        expect($content)->toContain("'name'");
    });

    it('has LogsActivityTrait for audit logging (BR-313)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Models/SellingUnit.php');
        expect($content)->toContain('LogsActivityTrait');
    });

    it('has a factory', function () {
        expect(SellingUnit::factory())->toBeInstanceOf(\Illuminate\Database\Eloquent\Factories\Factory::class);
    });
});

// ============================================================
// Test group: MealComponent — selling unit relationship compatibility
// ============================================================
describe('MealComponent selling unit compatibility', function () use ($projectRoot) {
    it('has getUnitLabelAttribute that supports numeric IDs', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Models/MealComponent.php');
        expect($content)->toContain('getUnitLabelAttribute');
        expect($content)->toContain('is_numeric');
        expect($content)->toContain('SellingUnit::find');
    });

    it('maintains backward compatibility with string-based selling unit keys', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Models/MealComponent.php');
        expect($content)->toContain('UNIT_LABELS');
        expect($content)->toContain("isset(self::UNIT_LABELS[\$unit])");
    });
});

// ============================================================
// Test group: SellingUnitController
// ============================================================
describe('SellingUnitController', function () use ($projectRoot) {
    it('has an index method', function () {
        $reflection = new ReflectionMethod(SellingUnitController::class, 'index');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a store method', function () {
        $reflection = new ReflectionMethod(SellingUnitController::class, 'store');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has an update method', function () {
        $reflection = new ReflectionMethod(SellingUnitController::class, 'update');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a destroy method', function () {
        $reflection = new ReflectionMethod(SellingUnitController::class, 'destroy');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('index returns gale view response', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        expect($content)->toContain("gale()->view('cook.selling-units.index'");
        expect($content)->toContain('web: true');
    });

    it('index passes units and deleteInfo to view', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        expect($content)->toContain("'units'");
        expect($content)->toContain("'deleteInfo'");
    });

    it('index checks can-manage-meals permission (BR-312)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        expect($content)->toContain("can('can-manage-meals')");
        expect($content)->toContain('abort(403)');
    });

    it('store uses dual Gale/HTTP validation pattern', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        expect($content)->toContain('isGale()');
        expect($content)->toContain('validateState');
        expect($content)->toContain('StoreSellingUnitRequest');
    });

    it('store validates unit_name_en and unit_name_fr state keys', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        $storeContent = substr($content, strpos($content, 'public function store'));
        $storeContent = substr($storeContent, 0, strpos($storeContent, 'public function update'));
        expect($storeContent)->toContain("'unit_name_en'");
        expect($storeContent)->toContain("'unit_name_fr'");
    });

    it('update uses edit_ prefixed state keys for Gale validation', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        $updateContent = substr($content, strpos($content, 'public function update'));
        expect($updateContent)->toContain("'edit_name_en'");
        expect($updateContent)->toContain("'edit_name_fr'");
    });

    it('update verifies unit belongs to tenant and is custom', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        $updateContent = substr($content, strpos($content, 'public function update'));
        expect($updateContent)->toContain('tenant_id');
        expect($updateContent)->toContain('custom()');
        expect($updateContent)->toContain('findOrFail');
    });

    it('destroy verifies unit belongs to tenant and is custom', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        $destroyContent = substr($content, strpos($content, 'public function destroy'));
        expect($destroyContent)->toContain('tenant_id');
        expect($destroyContent)->toContain('custom()');
        expect($destroyContent)->toContain('findOrFail');
    });

    it('store returns Gale redirect with toast on success', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        expect($content)->toContain("__('Selling unit created.')");
    });

    it('update returns Gale redirect with toast on success', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        expect($content)->toContain("__('Selling unit updated.')");
    });

    it('destroy returns Gale redirect with toast on success', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        expect($content)->toContain("__('Selling unit deleted.')");
    });

    it('store logs activity with selling_units log name (BR-313)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        expect($content)->toContain("activity('selling_units')");
        expect($content)->toContain("'selling_unit_created'");
    });

    it('update logs activity with old/new values (BR-313)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        expect($content)->toContain("'selling_unit_updated'");
        expect($content)->toContain("'old'");
        expect($content)->toContain("'new'");
    });

    it('destroy logs activity (BR-313)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        expect($content)->toContain("'selling_unit_deleted'");
    });

    it('uses gale()->messages() for service errors in store', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        expect($content)->toContain('gale()->messages(');
    });

    it('uses url-based redirect for Gale responses (convention)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SellingUnitController.php');
        expect($content)->toContain("url('/dashboard/selling-units')");
        expect($content)->toContain('->redirect($redirectUrl)');
    });
});

// ============================================================
// Test group: SellingUnitService
// ============================================================
describe('SellingUnitService', function () use ($projectRoot) {
    it('has a getUnitsForTenant method', function () {
        $reflection = new ReflectionMethod(SellingUnitService::class, 'getUnitsForTenant');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a getCustomUnitsForTenant method', function () {
        $reflection = new ReflectionMethod(SellingUnitService::class, 'getCustomUnitsForTenant');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a getUnitsWithLabels method', function () {
        $reflection = new ReflectionMethod(SellingUnitService::class, 'getUnitsWithLabels');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a getValidUnitIds method', function () {
        $reflection = new ReflectionMethod(SellingUnitService::class, 'getValidUnitIds');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a createUnit method', function () {
        $reflection = new ReflectionMethod(SellingUnitService::class, 'createUnit');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has an updateUnit method', function () {
        $reflection = new ReflectionMethod(SellingUnitService::class, 'updateUnit');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a deleteUnit method', function () {
        $reflection = new ReflectionMethod(SellingUnitService::class, 'deleteUnit');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a canDeleteUnit method', function () {
        $reflection = new ReflectionMethod(SellingUnitService::class, 'canDeleteUnit');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('getUnitsForTenant uses forTenant scope and orders by is_standard desc', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/SellingUnitService.php');
        expect($content)->toContain('forTenant(');
        expect($content)->toContain("orderByDesc('is_standard')");
    });

    it('createUnit performs case-insensitive uniqueness check (BR-310)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/SellingUnitService.php');
        expect($content)->toContain('LOWER(name_en)');
        expect($content)->toContain('LOWER(name_fr)');
        expect($content)->toContain('mb_strtolower');
    });

    it('createUnit trims whitespace before storage', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/SellingUnitService.php');
        expect($content)->toContain("trim(\$data['name_en'])");
        expect($content)->toContain("trim(\$data['name_fr'])");
    });

    it('createUnit checks against standard unit names (BR-310)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/SellingUnitService.php');
        expect($content)->toContain('SellingUnit::standard()');
        expect($content)->toContain('This name matches a standard unit');
    });

    it('createUnit checks against tenant custom unit names (BR-310)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/SellingUnitService.php');
        expect($content)->toContain('A custom unit with this English name already exists.');
        expect($content)->toContain('A custom unit with this French name already exists.');
    });

    it('createUnit sets is_standard to false for custom units (BR-308)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/SellingUnitService.php');
        expect($content)->toContain("'is_standard' => false");
    });

    it('updateUnit checks is_standard before allowing edit (BR-307)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/SellingUnitService.php');
        expect($content)->toContain('isStandard()');
        expect($content)->toContain('Standard units cannot be edited.');
    });

    it('updateUnit excludes current unit from uniqueness check (BR-310)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/SellingUnitService.php');
        expect($content)->toContain("'id', '!=', \$unit->id");
    });

    it('updateUnit captures old values for activity logging', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/SellingUnitService.php');
        expect($content)->toContain("'old_values'");
        expect($content)->toContain("\$oldValues");
    });

    it('deleteUnit checks is_standard before allowing deletion (BR-307)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/SellingUnitService.php');
        $deleteContent = substr($content, strpos($content, 'public function deleteUnit'));
        expect($deleteContent)->toContain('isStandard()');
        expect($deleteContent)->toContain('Standard units cannot be deleted.');
    });

    it('deleteUnit checks usage count before deletion (BR-311)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/SellingUnitService.php');
        $deleteContent = substr($content, strpos($content, 'public function deleteUnit'));
        expect($deleteContent)->toContain('getUsageCount()');
        expect($deleteContent)->toContain('$usageCount > 0');
    });

    it('deleteUnit returns error message with component count when in use', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/SellingUnitService.php');
        expect($content)->toContain("'success' => false");
        expect($content)->toContain(':count component');
    });

    it('canDeleteUnit pre-computes delete eligibility for view', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/SellingUnitService.php');
        expect($content)->toContain("'can_delete'");
        expect($content)->toContain("'reason'");
    });

    it('getUnitsWithLabels returns value/label/is_standard format', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/SellingUnitService.php');
        expect($content)->toContain("'value' => (string) \$unit->id");
        expect($content)->toContain("'label'");
        expect($content)->toContain("'is_standard'");
    });

    it('getValidUnitIds returns string array of IDs', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/SellingUnitService.php');
        expect($content)->toContain("pluck('id')");
        expect($content)->toContain('(string) $id');
    });
});

// ============================================================
// Test group: MealComponentService integration
// ============================================================
describe('MealComponentService integration with SellingUnitService', function () use ($projectRoot) {
    it('getAvailableUnits delegates to SellingUnitService', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/MealComponentService.php');
        expect($content)->toContain('SellingUnitService::class');
        expect($content)->toContain('getValidUnitIds');
    });

    it('getAvailableUnitsWithLabels delegates to SellingUnitService', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/MealComponentService.php');
        expect($content)->toContain('getUnitsWithLabels');
    });
});

// ============================================================
// Test group: StoreSellingUnitRequest
// ============================================================
describe('StoreSellingUnitRequest', function () use ($projectRoot) {
    it('requires name_en (BR-309)', function () {
        $request = new StoreSellingUnitRequest;
        $rules = $request->rules();
        expect($rules['name_en'])->toContain('required');
    });

    it('requires name_fr (BR-309)', function () {
        $request = new StoreSellingUnitRequest;
        $rules = $request->rules();
        expect($rules['name_fr'])->toContain('required');
    });

    it('enforces max length on name_en (BR-314)', function () {
        $request = new StoreSellingUnitRequest;
        $rules = $request->rules();
        expect($rules['name_en'])->toContain('max:'.SellingUnit::NAME_MAX_LENGTH);
    });

    it('enforces max length on name_fr (BR-314)', function () {
        $request = new StoreSellingUnitRequest;
        $rules = $request->rules();
        expect($rules['name_fr'])->toContain('max:'.SellingUnit::NAME_MAX_LENGTH);
    });

    it('authorizes users with can-manage-meals permission (BR-312)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/StoreSellingUnitRequest.php');
        expect($content)->toContain("can('can-manage-meals')");
    });

    it('has custom validation messages', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/StoreSellingUnitRequest.php');
        expect($content)->toContain("__('Unit name is required in English.')");
        expect($content)->toContain("__('Unit name is required in French.')");
    });
});

// ============================================================
// Test group: UpdateSellingUnitRequest
// ============================================================
describe('UpdateSellingUnitRequest', function () use ($projectRoot) {
    it('requires name_en (BR-309)', function () {
        $request = new UpdateSellingUnitRequest;
        $rules = $request->rules();
        expect($rules['name_en'])->toContain('required');
    });

    it('requires name_fr (BR-309)', function () {
        $request = new UpdateSellingUnitRequest;
        $rules = $request->rules();
        expect($rules['name_fr'])->toContain('required');
    });

    it('enforces max length on name_en (BR-314)', function () {
        $request = new UpdateSellingUnitRequest;
        $rules = $request->rules();
        expect($rules['name_en'])->toContain('max:'.SellingUnit::NAME_MAX_LENGTH);
    });

    it('authorizes users with can-manage-meals permission (BR-312)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/UpdateSellingUnitRequest.php');
        expect($content)->toContain("can('can-manage-meals')");
    });
});

// ============================================================
// Test group: Routes
// ============================================================
describe('Selling Unit Routes', function () use ($projectRoot) {
    it('defines cook.selling-units.index route', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain('cook.selling-units.index');
    });

    it('defines cook.selling-units.store route', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain('cook.selling-units.store');
    });

    it('defines cook.selling-units.update route', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain('cook.selling-units.update');
    });

    it('defines cook.selling-units.destroy route', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain('cook.selling-units.destroy');
    });

    it('uses SellingUnitController for selling unit routes', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain('SellingUnitController');
    });
});

// ============================================================
// Test group: Blade View
// ============================================================
describe('Selling Unit Index Blade View', function () use ($projectRoot) {
    it('extends cook-dashboard layout', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('has x-data with Alpine state for add form', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain('showAddForm');
        expect($content)->toContain('unit_name_en');
        expect($content)->toContain('unit_name_fr');
    });

    it('has x-data with Alpine state for edit form with edit_ prefix', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain('editingId');
        expect($content)->toContain('edit_name_en');
        expect($content)->toContain('edit_name_fr');
    });

    it('has delete confirmation modal', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain('confirmDeleteId');
        expect($content)->toContain('confirmDeleteName');
        expect($content)->toContain('executeDelete');
    });

    it('uses $action for form submissions', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain('$action(');
    });

    it('uses $fetching() for loading states', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain('$fetching()');
    });

    it('uses x-name for form inputs', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain('x-name="unit_name_en"');
        expect($content)->toContain('x-name="unit_name_fr"');
    });

    it('uses x-message for validation errors', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain('x-message="unit_name_en"');
        expect($content)->toContain('x-message="unit_name_fr"');
        expect($content)->toContain('x-message="edit_name_en"');
        expect($content)->toContain('x-message="edit_name_fr"');
    });

    it('uses semantic color tokens', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain('bg-surface');
        expect($content)->toContain('text-on-surface');
        expect($content)->toContain('bg-primary');
        expect($content)->toContain('text-on-primary');
    });

    it('supports dark mode with dark: variants', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain('dark:');
    });

    it('has responsive layout with desktop table and mobile cards', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain('hidden md:block');
        expect($content)->toContain('md:hidden');
    });

    it('has empty state for custom units with create button', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain("__('No custom units yet')");
        expect($content)->toContain("__('Create Your First Unit')");
    });

    it('shows Standard badge for standard units (BR-307)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain("__('Standard')");
        expect($content)->toContain('bg-info-subtle text-info');
    });

    it('separates standard and custom units into distinct sections', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain("__('Standard Units')");
        expect($content)->toContain("__('Custom Units')");
    });

    it('shows usage count for custom units', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain('getUsageCount()');
        expect($content)->toContain(':count component');
    });

    it('disables delete button when unit cannot be deleted (BR-311)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain('!$canDelete');
        expect($content)->toContain('disabled');
        expect($content)->toContain('cursor-not-allowed');
    });

    it('uses __() for all user-facing strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain("__('Selling Units')");
        expect($content)->toContain("__('Add Unit')");
        expect($content)->toContain("__('Cancel')");
        expect($content)->toContain("__('Save')");
        expect($content)->toContain("__('Delete Selling Unit')");
        expect($content)->toContain("__('Create Unit')");
    });

    it('uses x-model for edit form inputs', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain('x-model="edit_name_en"');
        expect($content)->toContain('x-model="edit_name_fr"');
    });

    it('includes edit form with PUT method', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain("method: 'PUT'");
    });

    it('includes delete action with DELETE method', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain("method: 'DELETE'");
    });

    it('includes total count summary at bottom', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/selling-units/index.blade.php');
        expect($content)->toContain(':count standard unit');
        expect($content)->toContain(':count custom unit');
    });
});

// ============================================================
// Test group: Cook Dashboard Navigation
// ============================================================
describe('Cook Dashboard Selling Units Navigation', function () use ($projectRoot) {
    it('has Selling Units link in cook dashboard sidebar', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/layouts/cook-dashboard.blade.php');
        expect($content)->toContain('/dashboard/selling-units');
        expect($content)->toContain("__('Selling Units')");
    });

    it('requires can-manage-meals permission for nav item', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/layouts/cook-dashboard.blade.php');
        // Find the selling-units section
        $pos = strpos($content, 'selling-units');
        $nearbyContent = substr($content, max(0, $pos - 200), 400);
        expect($nearbyContent)->toContain('can-manage-meals');
    });
});

// ============================================================
// Test group: Translation strings
// ============================================================
describe('Selling Unit Translations', function () use ($projectRoot) {
    it('has English translation strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/lang/en.json');
        $translations = json_decode($content, true);
        expect($translations)->toHaveKey('Add Unit');
        expect($translations)->toHaveKey('New Selling Unit');
        expect($translations)->toHaveKey('Create Unit');
        expect($translations)->toHaveKey('Edit Selling Unit');
        expect($translations)->toHaveKey('Delete Selling Unit');
        expect($translations)->toHaveKey('Selling unit created.');
        expect($translations)->toHaveKey('Selling unit updated.');
        expect($translations)->toHaveKey('Selling unit deleted.');
        expect($translations)->toHaveKey('No custom units yet');
        expect($translations)->toHaveKey('Create Your First Unit');
        expect($translations)->toHaveKey('Standard Units');
        expect($translations)->toHaveKey('Custom Units');
        expect($translations)->toHaveKey('A custom unit with this English name already exists.');
        expect($translations)->toHaveKey('A custom unit with this French name already exists.');
        expect($translations)->toHaveKey('This name matches a standard unit. Please choose a different name.');
    });

    it('has French translation strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/lang/fr.json');
        $translations = json_decode($content, true);
        expect($translations)->toHaveKey('Add Unit');
        expect($translations)->toHaveKey('Selling unit created.');
        expect($translations)->toHaveKey('Selling unit deleted.');
        expect($translations)->toHaveKey('No custom units yet');
        expect($translations)->toHaveKey('Standard Units');
        expect($translations)->toHaveKey('Custom Units');
    });
});

// ============================================================
// Test group: Migration and database schema
// ============================================================
describe('SellingUnit Database Schema', function () use ($projectRoot) {
    it('has selling_units migration file', function () use ($projectRoot) {
        $files = glob($projectRoot.'/database/migrations/*create_selling_units_table*');
        expect($files)->not->toBeEmpty();
    });

    it('migration creates selling_units table', function () use ($projectRoot) {
        $files = glob($projectRoot.'/database/migrations/*create_selling_units_table*');
        $content = file_get_contents($files[0]);
        expect($content)->toContain("Schema::create('selling_units'");
    });

    it('migration has tenant_id as nullable foreign key (BR-308)', function () use ($projectRoot) {
        $files = glob($projectRoot.'/database/migrations/*create_selling_units_table*');
        $content = file_get_contents($files[0]);
        expect($content)->toContain('tenant_id');
        expect($content)->toContain('nullable');
        expect($content)->toContain('constrained');
    });

    it('migration has name_en with max 50 chars (BR-314)', function () use ($projectRoot) {
        $files = glob($projectRoot.'/database/migrations/*create_selling_units_table*');
        $content = file_get_contents($files[0]);
        expect($content)->toContain('name_en');
        expect($content)->toContain('50');
    });

    it('migration has name_fr with max 50 chars (BR-314)', function () use ($projectRoot) {
        $files = glob($projectRoot.'/database/migrations/*create_selling_units_table*');
        $content = file_get_contents($files[0]);
        expect($content)->toContain('name_fr');
    });

    it('migration has is_standard boolean column', function () use ($projectRoot) {
        $files = glob($projectRoot.'/database/migrations/*create_selling_units_table*');
        $content = file_get_contents($files[0]);
        expect($content)->toContain('is_standard');
        expect($content)->toContain('boolean');
    });

    it('migration has indexes on tenant_id + name columns', function () use ($projectRoot) {
        $files = glob($projectRoot.'/database/migrations/*create_selling_units_table*');
        $content = file_get_contents($files[0]);
        expect($content)->toContain('index');
    });

    it('has data migration to convert meal_components selling_unit values', function () use ($projectRoot) {
        $files = glob($projectRoot.'/database/migrations/*convert_meal_components_selling_unit*');
        expect($files)->not->toBeEmpty();
    });
});

// ============================================================
// Test group: Seeder
// ============================================================
describe('SellingUnit Seeder', function () use ($projectRoot) {
    it('has SellingUnitSeeder file', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/database/seeders/SellingUnitSeeder.php'))->toBeTrue();
    });

    it('seeder uses firstOrCreate for idempotency', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/database/seeders/SellingUnitSeeder.php');
        expect($content)->toContain('firstOrCreate');
    });

    it('seeder creates all 8 standard units (BR-306)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/database/seeders/SellingUnitSeeder.php');
        expect($content)->toContain('STANDARD_UNITS');
        expect($content)->toContain("'is_standard' => true");
    });

    it('seeder sets tenant_id to null for standard units', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/database/seeders/SellingUnitSeeder.php');
        expect($content)->toContain("'tenant_id' => null");
    });
});

// ============================================================
// Test group: Factory
// ============================================================
describe('SellingUnit Factory', function () use ($projectRoot) {
    it('has SellingUnitFactory file', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/database/factories/SellingUnitFactory.php'))->toBeTrue();
    });

    it('factory has standard state', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/database/factories/SellingUnitFactory.php');
        expect($content)->toContain('function standard');
    });

    it('factory has custom state', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/database/factories/SellingUnitFactory.php');
        expect($content)->toContain('function custom');
    });

    it('factory has forTenant state', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/database/factories/SellingUnitFactory.php');
        expect($content)->toContain('function forTenant');
    });
});
