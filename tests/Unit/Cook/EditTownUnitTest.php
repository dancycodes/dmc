<?php

/**
 * F-084: Edit Town -- Unit Tests
 *
 * Tests for TownController::update(), UpdateTownRequest,
 * DeliveryAreaService::updateTown(), blade view edit form,
 * route configuration, and translation strings.
 *
 * BR-219: Town name required in both EN and FR
 * BR-220: Edited town name must remain unique within this cook's towns (excluding current)
 * BR-221: Save via Gale; list updates without page reload
 * BR-222: Changes to town name do not affect existing order records (orders reference by ID)
 * BR-223: Edit action requires location management permission
 * BR-224: All validation messages use __() localization
 */

use App\Http\Controllers\Cook\TownController;
use App\Http\Requests\Cook\UpdateTownRequest;
use App\Services\DeliveryAreaService;

$projectRoot = dirname(__DIR__, 3);

// ============================================================
// Test group: TownController update method
// ============================================================
describe('TownController update method', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has an update method', function () {
        $reflection = new ReflectionMethod(TownController::class, 'update');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('update method accepts Request, deliveryArea int, and DeliveryAreaService parameters', function () {
        $reflection = new ReflectionMethod(TownController::class, 'update');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(3);
        expect($params[0]->getName())->toBe('request');
        expect($params[1]->getName())->toBe('deliveryArea');
        expect($params[2]->getName())->toBe('deliveryAreaService');
    });

    it('update checks can-manage-locations permission (BR-223)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        $updateContent = substr($content, strpos($content, 'public function update'));
        expect($updateContent)->toContain("can('can-manage-locations')");
        expect($updateContent)->toContain('abort(403)');
    });

    it('update uses dual Gale/HTTP validation pattern', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        $updateContent = substr($content, strpos($content, 'public function update'));
        expect($updateContent)->toContain('isGale()');
        expect($updateContent)->toContain('validateState');
        expect($updateContent)->toContain('UpdateTownRequest');
    });

    it('update validates edit_name_en and edit_name_fr state keys (BR-219)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        $updateContent = substr($content, strpos($content, 'public function update'));
        expect($updateContent)->toContain("'edit_name_en'");
        expect($updateContent)->toContain("'edit_name_fr'");
    });

    it('update trims whitespace from names', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        $updateContent = substr($content, strpos($content, 'public function update'));
        expect($updateContent)->toContain("trim(\$validated['edit_name_en'])");
        expect($updateContent)->toContain("trim(\$validated['edit_name_fr'])");
    });

    it('update uses DeliveryAreaService::updateTown for business logic', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        $updateContent = substr($content, strpos($content, 'public function update'));
        expect($updateContent)->toContain('$deliveryAreaService->updateTown(');
    });

    it('update returns Gale messages on duplicate error (BR-220)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        $updateContent = substr($content, strpos($content, 'public function update'));
        expect($updateContent)->toContain('gale()->messages(');
        expect($updateContent)->toContain('edit_name_en');
    });

    it('update redirects with success message on save (BR-221)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        $updateContent = substr($content, strpos($content, 'public function update'));
        expect($updateContent)->toContain("redirect(url('/dashboard/locations'))");
        expect($updateContent)->toContain("__('Town updated successfully.')");
    });

    it('update logs activity on town edit', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        $updateContent = substr($content, strpos($content, 'public function update'));
        expect($updateContent)->toContain("activity('delivery_areas')");
        expect($updateContent)->toContain("'town_updated'");
    });

    it('never uses bare return view()', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->not->toMatch('/return\s+view\s*\(/');
    });
});

// ============================================================
// Test group: UpdateTownRequest form request
// ============================================================
describe('UpdateTownRequest', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('extends FormRequest', function () {
        $reflection = new ReflectionClass(UpdateTownRequest::class);
        expect($reflection->getParentClass()->getName())->toBe('Illuminate\Foundation\Http\FormRequest');
    });

    it('has authorize method checking can-manage-locations (BR-223)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/UpdateTownRequest.php');
        expect($content)->toContain("can('can-manage-locations')");
    });

    it('requires name_en field (BR-219)', function () {
        $request = new UpdateTownRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('name_en');
        expect($rules['name_en'])->toContain('required');
    });

    it('requires name_fr field (BR-219)', function () {
        $request = new UpdateTownRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('name_fr');
        expect($rules['name_fr'])->toContain('required');
    });

    it('enforces max length of 255 for name_en', function () {
        $request = new UpdateTownRequest;
        $rules = $request->rules();
        expect($rules['name_en'])->toContain('max:255');
    });

    it('enforces max length of 255 for name_fr', function () {
        $request = new UpdateTownRequest;
        $rules = $request->rules();
        expect($rules['name_fr'])->toContain('max:255');
    });

    it('has messages method for localized validation (BR-224)', function () {
        $reflection = new ReflectionMethod(UpdateTownRequest::class, 'messages');
        expect($reflection->isPublic())->toBeTrue();
        expect($reflection->getReturnType()->getName())->toBe('array');
    });

    it('messages contain localized validation strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/UpdateTownRequest.php');
        expect($content)->toContain("__('Town name is required in English.')");
        expect($content)->toContain("__('Town name is required in French.')");
        expect($content)->toContain("__('Town name must not exceed 255 characters.')");
    });
});

// ============================================================
// Test group: DeliveryAreaService::updateTown()
// ============================================================
describe('DeliveryAreaService::updateTown', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has updateTown method', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'updateTown');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('updateTown accepts Tenant, deliveryAreaId, nameEn, nameFr', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'updateTown');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(4);
        expect($params[0]->getName())->toBe('tenant');
        expect($params[1]->getName())->toBe('deliveryAreaId');
        expect($params[2]->getName())->toBe('nameEn');
        expect($params[3]->getName())->toBe('nameFr');
    });

    it('checks uniqueness excluding current town (BR-220)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $updateMethod = substr($content, strpos($content, 'public function updateTown'));
        $methodEnd = strpos($updateMethod, "\n    /**", 10);
        $updateMethod = substr($updateMethod, 0, $methodEnd ?: strlen($updateMethod));

        expect($updateMethod)->toContain("'!=', \$deliveryAreaId");
        expect($updateMethod)->toContain('mb_strtolower');
    });

    it('updates the town record with new names', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $updateMethod = substr($content, strpos($content, 'public function updateTown'));
        $methodEnd = strpos($updateMethod, "\n    /**", 10);
        $updateMethod = substr($updateMethod, 0, $methodEnd ?: strlen($updateMethod));

        expect($updateMethod)->toContain('->update(');
        expect($updateMethod)->toContain("'name_en'");
        expect($updateMethod)->toContain("'name_fr'");
    });

    it('returns array with success flag', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $updateMethod = substr($content, strpos($content, 'public function updateTown'));
        $methodEnd = strpos($updateMethod, "\n    /**", 10);
        $updateMethod = substr($updateMethod, 0, $methodEnd ?: strlen($updateMethod));

        expect($updateMethod)->toContain("'success'");
        expect($updateMethod)->toContain("'error'");
    });

    it('returns error for non-existent delivery area', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $updateMethod = substr($content, strpos($content, 'public function updateTown'));
        $methodEnd = strpos($updateMethod, "\n    /**", 10);
        $updateMethod = substr($updateMethod, 0, $methodEnd ?: strlen($updateMethod));

        expect($updateMethod)->toContain("__('Delivery area not found.')");
    });

    it('returns error for duplicate name (BR-220)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $updateMethod = substr($content, strpos($content, 'public function updateTown'));
        $methodEnd = strpos($updateMethod, "\n    /**", 10);
        $updateMethod = substr($updateMethod, 0, $methodEnd ?: strlen($updateMethod));

        expect($updateMethod)->toContain("__('A town with this name already exists in your delivery areas.')");
    });
});

// ============================================================
// Test group: Blade View -- Edit Town Form
// ============================================================
describe('Locations blade -- edit town form', function () {
    $projectRoot = dirname(__DIR__, 3);
    $viewPath = $projectRoot.'/resources/views/cook/locations/index.blade.php';

    it('has edit town Alpine state variables', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('editingTownId: null');
        expect($content)->toContain('edit_name_en');
        expect($content)->toContain('edit_name_fr');
    });

    it('has startEdit method for pre-populating edit form', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('startEdit(');
    });

    it('has cancelEdit method for cancelling edit', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('cancelEdit()');
    });

    it('syncs edit state keys with Gale', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("'edit_name_en'");
        expect($content)->toContain("'edit_name_fr'");
        expect($content)->toContain('x-sync');
    });

    it('has inline edit form that replaces town row', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('editingTownId ===');
        expect($content)->toContain("__('Edit Town')");
    });

    it('edit form has English and French name fields (BR-219)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('x-model="edit_name_en"');
        expect($content)->toContain('x-model="edit_name_fr"');
        expect($content)->toContain('x-name="edit_name_en"');
        expect($content)->toContain('x-name="edit_name_fr"');
    });

    it('edit form uses $action with PUT method (BR-221)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("method: 'PUT'");
    });

    it('edit form has x-message for validation errors', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('x-message="edit_name_en"');
        expect($content)->toContain('x-message="edit_name_fr"');
    });

    it('edit form has Save Changes and Cancel buttons', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Save Changes')");
        expect($content)->toContain('cancelEdit()');
    });

    it('edit form has loading state with $fetching()', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('$fetching()');
    });

    it('edit button calls startEdit with town data', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('startEdit(');
        expect($content)->toContain('town_name_en');
        expect($content)->toContain('town_name_fr');
    });

    it('hides display row when editing', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('editingTownId !==');
    });

    it('hides quarter section when editing', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('editingTownId !==');
        expect($content)->toContain('expandedTown ===');
    });

    it('uses semantic color tokens for dark mode support', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('bg-surface');
        expect($content)->toContain('text-on-surface');
        expect($content)->toContain('bg-primary');
        expect($content)->toContain('border-outline');
    });

    it('all edit-related strings use __() helper (BR-224)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Edit Town')");
        expect($content)->toContain("__('Save Changes')");
        expect($content)->toContain("__('Cancel')");
        expect($content)->toContain("__('Town Name (English)')");
        expect($content)->toContain("__('Town Name (French)')");
    });
});

// ============================================================
// Test group: Route Configuration
// ============================================================
describe('Route configuration for edit town', function () {
    $projectRoot = dirname(__DIR__, 3);
    $routesContent = file_get_contents($projectRoot.'/routes/web.php');

    it('has town update route with PUT method', function () use ($routesContent) {
        expect($routesContent)->toContain("Route::put('/locations/towns/{deliveryArea}'");
    });

    it('update route points to TownController@update', function () use ($routesContent) {
        expect($routesContent)->toContain("[TownController::class, 'update']");
    });

    it('update route has named route', function () use ($routesContent) {
        expect($routesContent)->toContain('cook.locations.towns.update');
    });
});

// ============================================================
// Test group: Translation Strings for F-084
// ============================================================
describe('Translation strings for edit town', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has English translations for edit town', function () use ($projectRoot) {
        $enContent = file_get_contents($projectRoot.'/lang/en.json');
        $en = json_decode($enContent, true);

        expect($en)->toHaveKey('Edit Town');
        expect($en)->toHaveKey('Town updated successfully.');
    });

    it('has French translations for edit town', function () use ($projectRoot) {
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        expect($fr)->toHaveKey('Edit Town');
        expect($fr)->toHaveKey('Town updated successfully.');
    });

    it('French translations differ from English keys', function () use ($projectRoot) {
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        expect($fr['Edit Town'])->not->toBe('Edit Town');
        expect($fr['Town updated successfully.'])->not->toBe('Town updated successfully.');
    });
});
