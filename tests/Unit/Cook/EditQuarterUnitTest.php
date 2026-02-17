<?php

/**
 * F-088: Edit Quarter -- Unit Tests
 *
 * Tests for QuarterController::update(), UpdateQuarterRequest, DeliveryAreaService::updateQuarter(),
 * locations blade view quarter edit form, route configuration, and translation strings.
 *
 * BR-250: Quarter name required in both EN and FR
 * BR-251: Quarter name must remain unique within the parent town (excluding current quarter)
 * BR-252: Delivery fee required; must be >= 0 XAF
 * BR-253: Group assignment can be changed or removed
 * BR-254: When in a group, the group's fee overrides the individual fee
 * BR-255: Fee changes apply to new orders only, not existing orders
 * BR-256: Save via Gale; list updates without page reload
 * BR-257: Edit action requires location management permission
 */

use App\Http\Controllers\Cook\QuarterController;
use App\Http\Requests\Cook\UpdateQuarterRequest;
use App\Models\Quarter;
use App\Models\Town;
use App\Services\DeliveryAreaService;

$projectRoot = dirname(__DIR__, 3);

// ============================================================
// Test group: QuarterController update method
// ============================================================
describe('QuarterController::update', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has an update method', function () {
        $reflection = new ReflectionMethod(QuarterController::class, 'update');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('update method accepts Request, deliveryAreaQuarter int, and DeliveryAreaService parameters', function () {
        $reflection = new ReflectionMethod(QuarterController::class, 'update');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(3);
        expect($params[0]->getName())->toBe('request');
        expect($params[1]->getName())->toBe('deliveryAreaQuarter');
        expect($params[2]->getName())->toBe('deliveryAreaService');
    });

    it('update checks can-manage-locations permission (BR-257)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        // The update method also checks permission
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain("can('can-manage-locations')");
        expect($updateSection)->toContain('abort(403)');
    });

    it('update uses dual Gale/HTTP validation pattern', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain('isGale()');
        expect($updateSection)->toContain('validateState');
        expect($updateSection)->toContain('UpdateQuarterRequest');
    });

    it('update validates edit_quarter_name_en as required (BR-250)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain("'edit_quarter_name_en'");
        expect($updateSection)->toContain("'required'");
    });

    it('update validates edit_quarter_name_fr as required (BR-250)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain("'edit_quarter_name_fr'");
    });

    it('update validates edit_quarter_delivery_fee as integer min:0 (BR-252)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain("'edit_quarter_delivery_fee'");
        expect($updateSection)->toContain("'integer'");
        expect($updateSection)->toContain("'min:0'");
    });

    it('update trims whitespace from quarter names', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain("trim(\$validated['edit_quarter_name_en'])");
        expect($updateSection)->toContain("trim(\$validated['edit_quarter_name_fr'])");
    });

    it('update casts delivery fee to integer', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain("(int) \$validated['edit_quarter_delivery_fee']");
    });

    it('update returns Gale messages on uniqueness violation (BR-251)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain('gale()->messages(');
        expect($updateSection)->toContain("'edit_quarter_name_en'");
    });

    it('update returns Gale redirect on success (BR-256)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain('gale()');
        expect($updateSection)->toContain('->redirect(');
        expect($updateSection)->toContain("'/dashboard/locations'");
    });

    it('update logs activity on success', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain("activity('delivery_areas')");
        expect($updateSection)->toContain("'quarter_updated'");
    });

    it('update uses localized success message', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain("__('Quarter updated successfully.')");
    });

    it('update includes high fee warning from service result', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain("\$result['warning']");
    });
});

// ============================================================
// Test group: UpdateQuarterRequest
// ============================================================
describe('UpdateQuarterRequest', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('exists as a form request class', function () {
        expect(class_exists(UpdateQuarterRequest::class))->toBeTrue();
        expect(new UpdateQuarterRequest)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
    });

    it('has authorize method checking can-manage-locations permission (BR-257)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/UpdateQuarterRequest.php');
        expect($content)->toContain("can('can-manage-locations')");
    });

    it('validates name_en as required string max:255 (BR-250)', function () {
        $request = new UpdateQuarterRequest;
        $rules = $request->rules();
        expect($rules['name_en'])->toContain('required');
        expect($rules['name_en'])->toContain('string');
        expect($rules['name_en'])->toContain('max:255');
    });

    it('validates name_fr as required string max:255 (BR-250)', function () {
        $request = new UpdateQuarterRequest;
        $rules = $request->rules();
        expect($rules['name_fr'])->toContain('required');
        expect($rules['name_fr'])->toContain('string');
        expect($rules['name_fr'])->toContain('max:255');
    });

    it('validates delivery_fee as required integer min:0 (BR-252)', function () {
        $request = new UpdateQuarterRequest;
        $rules = $request->rules();
        expect($rules['delivery_fee'])->toContain('required');
        expect($rules['delivery_fee'])->toContain('integer');
        expect($rules['delivery_fee'])->toContain('min:0');
    });

    it('has localized validation messages', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/UpdateQuarterRequest.php');
        expect($content)->toContain("'name_en.required'");
        expect($content)->toContain("'name_fr.required'");
        expect($content)->toContain("'delivery_fee.required'");
        expect($content)->toContain("'delivery_fee.integer'");
        expect($content)->toContain("'delivery_fee.min'");
        expect($content)->toContain('__(');
    });
});

// ============================================================
// Test group: DeliveryAreaService::updateQuarter
// ============================================================
describe('DeliveryAreaService::updateQuarter', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has an updateQuarter method', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'updateQuarter');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('updateQuarter accepts correct parameters', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'updateQuarter');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(5);
        expect($params[0]->getName())->toBe('tenant');
        expect($params[1]->getName())->toBe('deliveryAreaQuarterId');
        expect($params[2]->getName())->toBe('nameEn');
        expect($params[3]->getName())->toBe('nameFr');
        expect($params[4]->getName())->toBe('deliveryFee');
    });

    it('updateQuarter returns array with success, error, and warning keys', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $updateSection = substr($content, strpos($content, 'public function updateQuarter'));
        $updateSection = substr($updateSection, 0, strpos($updateSection, 'public function removeQuarter'));
        expect($updateSection)->toContain("'success' => true");
        expect($updateSection)->toContain("'success' => false");
        expect($updateSection)->toContain("'error'");
        expect($updateSection)->toContain("'warning'");
    });

    it('updateQuarter checks for duplicate quarter names within the town (BR-251)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $updateSection = substr($content, strpos($content, 'public function updateQuarter'));
        $updateSection = substr($updateSection, 0, strpos($updateSection, 'public function removeQuarter'));
        expect($updateSection)->toContain('LOWER(name_en)');
        expect($updateSection)->toContain('LOWER(name_fr)');
        expect($updateSection)->toContain("'id', '!=', \$deliveryAreaQuarterId");
    });

    it('updateQuarter excludes the current quarter from uniqueness check (BR-251)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $updateSection = substr($content, strpos($content, 'public function updateQuarter'));
        $updateSection = substr($updateSection, 0, strpos($updateSection, 'public function removeQuarter'));
        expect($updateSection)->toContain("'id', '!=', \$deliveryAreaQuarterId");
    });

    it('updateQuarter returns not found error when quarter does not exist', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $updateSection = substr($content, strpos($content, 'public function updateQuarter'));
        $updateSection = substr($updateSection, 0, strpos($updateSection, 'public function removeQuarter'));
        expect($updateSection)->toContain("__('Quarter not found.')");
    });

    it('updateQuarter returns duplicate error when name already exists', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $updateSection = substr($content, strpos($content, 'public function updateQuarter'));
        $updateSection = substr($updateSection, 0, strpos($updateSection, 'public function removeQuarter'));
        expect($updateSection)->toContain("__('A quarter with this name already exists in this town.')");
    });

    it('updateQuarter updates the quarter name (BR-250)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $updateSection = substr($content, strpos($content, 'public function updateQuarter'));
        $updateSection = substr($updateSection, 0, strpos($updateSection, 'public function removeQuarter'));
        expect($updateSection)->toContain('$daq->quarter->update(');
        expect($updateSection)->toContain("'name_en' => \$nameEn");
        expect($updateSection)->toContain("'name_fr' => \$nameFr");
    });

    it('updateQuarter updates the delivery fee (BR-252)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $updateSection = substr($content, strpos($content, 'public function updateQuarter'));
        $updateSection = substr($updateSection, 0, strpos($updateSection, 'public function removeQuarter'));
        expect($updateSection)->toContain('$daq->update(');
        expect($updateSection)->toContain("'delivery_fee' => \$deliveryFee");
    });

    it('updateQuarter returns high fee warning when fee exceeds threshold', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $updateSection = substr($content, strpos($content, 'public function updateQuarter'));
        $updateSection = substr($updateSection, 0, strpos($updateSection, 'public function removeQuarter'));
        expect($updateSection)->toContain('HIGH_FEE_THRESHOLD');
    });

    it('updateQuarter verifies tenant ownership of the quarter', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $updateSection = substr($content, strpos($content, 'public function updateQuarter'));
        $updateSection = substr($updateSection, 0, strpos($updateSection, 'public function removeQuarter'));
        expect($updateSection)->toContain("'tenant_id', \$tenant->id");
    });
});

// ============================================================
// Test group: Route configuration
// ============================================================
describe('Route configuration', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('registers PUT route for quarter update', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain("Route::put('/locations/quarters/{deliveryAreaQuarter}'");
        expect($content)->toContain("[QuarterController::class, 'update']");
    });

    it('names the quarter update route correctly', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain("->name('cook.locations.quarters.update')");
    });
});

// ============================================================
// Test group: Blade view - Inline edit form
// ============================================================
describe('Blade view - Edit Quarter form (F-088)', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('contains editingQuarterId Alpine state', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('editingQuarterId:');
    });

    it('contains edit_quarter_name_en Alpine state', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('edit_quarter_name_en:');
    });

    it('contains edit_quarter_name_fr Alpine state', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('edit_quarter_name_fr:');
    });

    it('contains edit_quarter_delivery_fee Alpine state', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('edit_quarter_delivery_fee:');
    });

    it('contains startEditQuarter method', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('startEditQuarter(');
    });

    it('contains cancelEditQuarter method', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('cancelEditQuarter()');
    });

    it('edit form uses PUT method via $action', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain("method: 'PUT'");
    });

    it('edit form has x-model for edit_quarter_name_en', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('x-model="edit_quarter_name_en"');
    });

    it('edit form has x-model for edit_quarter_name_fr', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('x-model="edit_quarter_name_fr"');
    });

    it('edit form has x-model for edit_quarter_delivery_fee', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('x-model="edit_quarter_delivery_fee"');
    });

    it('edit form has x-name for edit_quarter_name_en', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('x-name="edit_quarter_name_en"');
    });

    it('edit form has x-name for edit_quarter_name_fr', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('x-name="edit_quarter_name_fr"');
    });

    it('edit form has x-name for edit_quarter_delivery_fee', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('x-name="edit_quarter_delivery_fee"');
    });

    it('edit form has x-message for validation errors', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('x-message="edit_quarter_name_en"');
        expect($content)->toContain('x-message="edit_quarter_name_fr"');
        expect($content)->toContain('x-message="edit_quarter_delivery_fee"');
    });

    it('edit state keys are synced', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain("'edit_quarter_name_en'");
        expect($content)->toContain("'edit_quarter_name_fr'");
        expect($content)->toContain("'edit_quarter_delivery_fee'");
    });

    it('edit button triggers startEditQuarter with correct parameters', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('x-on:click="startEditQuarter(');
    });

    it('display row is hidden when editing', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('x-show="editingQuarterId !==');
    });

    it('edit form is shown when editing', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('x-show="editingQuarterId ===');
    });

    it('edit form has loading state with $fetching()', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        // Should have multiple $fetching() usages (for add form + edit form)
        $fetchingCount = substr_count($content, '$fetching()');
        expect($fetchingCount)->toBeGreaterThanOrEqual(4); // at least for edit form: !$fetching, $fetching, + add form
    });

    it('uses localized labels', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain("__('Edit Quarter')");
        expect($content)->toContain("__('Save Changes')");
        expect($content)->toContain("__('Cancel')");
    });

    it('uses semantic color tokens', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('bg-primary');
        expect($content)->toContain('text-on-primary');
        expect($content)->toContain('text-on-surface');
        expect($content)->toContain('bg-surface');
    });

    it('edit form uses dark: variants', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('dark:bg-surface');
        expect($content)->toContain('dark:border-outline');
    });

    it('contains F-088 feature reference in comments', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('F-088');
    });
});

// ============================================================
// Test group: Translation strings
// ============================================================
describe('Translation strings (F-088)', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has English translation for "Quarter updated successfully."', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/lang/en.json');
        $translations = json_decode($content, true);
        expect($translations)->toHaveKey('Quarter updated successfully.');
    });

    it('has French translation for "Quarter updated successfully."', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/lang/fr.json');
        $translations = json_decode($content, true);
        expect($translations)->toHaveKey('Quarter updated successfully.');
        expect($translations['Quarter updated successfully.'])->not->toBe('Quarter updated successfully.');
    });

    it('has English translation for "Quarter not found."', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/lang/en.json');
        $translations = json_decode($content, true);
        expect($translations)->toHaveKey('Quarter not found.');
    });

    it('has French translation for "Quarter not found."', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/lang/fr.json');
        $translations = json_decode($content, true);
        expect($translations)->toHaveKey('Quarter not found.');
        expect($translations['Quarter not found.'])->not->toBe('Quarter not found.');
    });

    it('has English translation for "Edit Quarter"', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/lang/en.json');
        $translations = json_decode($content, true);
        expect($translations)->toHaveKey('Edit Quarter');
    });

    it('has French translation for "Edit Quarter"', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/lang/fr.json');
        $translations = json_decode($content, true);
        expect($translations)->toHaveKey('Edit Quarter');
        expect($translations['Edit Quarter'])->not->toBe('Edit Quarter');
    });
});
