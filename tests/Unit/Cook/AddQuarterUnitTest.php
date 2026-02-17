<?php

/**
 * F-086: Add Quarter -- Unit Tests
 *
 * Tests for QuarterController, StoreQuarterRequest, DeliveryAreaService::addQuarter(),
 * locations blade view quarter additions, route configuration, and translation strings.
 *
 * BR-232: Quarter name required in both EN and FR
 * BR-233: Quarter name must be unique within its parent town
 * BR-234: Delivery fee is required and must be >= 0 XAF
 * BR-235: Delivery fee is stored as an integer in XAF
 * BR-236: A fee of 0 means free delivery to that quarter
 * BR-237: Quarter can optionally be assigned to a quarter group
 * BR-238: When assigned to a group, the group's delivery fee overrides the individual fee
 * BR-239: Quarter is scoped to its parent town (town_id foreign key)
 * BR-240: Save via Gale; quarter appears in list without page reload
 * BR-241: Only users with location management permission can add quarters
 */

use App\Http\Controllers\Cook\QuarterController;
use App\Http\Requests\Cook\StoreQuarterRequest;
use App\Models\Quarter;
use App\Services\DeliveryAreaService;

$projectRoot = dirname(__DIR__, 3);

// ============================================================
// Test group: QuarterController methods
// ============================================================
describe('QuarterController', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has a store method', function () {
        $reflection = new ReflectionMethod(QuarterController::class, 'store');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('store method accepts Request, deliveryArea int, and DeliveryAreaService parameters', function () {
        $reflection = new ReflectionMethod(QuarterController::class, 'store');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(3);
        expect($params[0]->getName())->toBe('request');
        expect($params[1]->getName())->toBe('deliveryArea');
        expect($params[2]->getName())->toBe('deliveryAreaService');
    });

    it('store checks can-manage-locations permission (BR-241)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        expect($content)->toContain("can('can-manage-locations')");
        expect($content)->toContain('abort(403)');
    });

    it('store uses dual Gale/HTTP validation pattern', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        expect($content)->toContain('isGale()');
        expect($content)->toContain('validateState');
        expect($content)->toContain('StoreQuarterRequest');
    });

    it('store validates quarter_name_en as required (BR-232)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        expect($content)->toContain("'quarter_name_en'");
        expect($content)->toContain("'required'");
    });

    it('store validates quarter_name_fr as required (BR-232)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        expect($content)->toContain("'quarter_name_fr'");
    });

    it('store validates quarter_delivery_fee as integer min:0 (BR-234, BR-235)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        expect($content)->toContain("'quarter_delivery_fee'");
        expect($content)->toContain("'integer'");
        expect($content)->toContain("'min:0'");
    });

    it('store trims whitespace from quarter names', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        expect($content)->toContain("trim(\$validated['quarter_name_en'])");
        expect($content)->toContain("trim(\$validated['quarter_name_fr'])");
    });

    it('store uses DeliveryAreaService::addQuarter for business logic', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        expect($content)->toContain('$deliveryAreaService->addQuarter(');
    });

    it('store returns Gale messages on duplicate error (BR-233)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        expect($content)->toContain('gale()->messages(');
    });

    it('store redirects with success message on save (BR-240)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        expect($content)->toContain("redirect(url('/dashboard/locations'))");
        expect($content)->toContain("__('Quarter added successfully.')");
    });

    it('store logs activity on quarter creation', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        expect($content)->toContain("activity('delivery_areas')");
        expect($content)->toContain("'quarter_added'");
    });

    it('store includes high fee warning in success message', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        expect($content)->toContain("\$result['warning']");
    });

    it('store casts delivery fee to integer (BR-235)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        expect($content)->toContain("(int) \$validated['quarter_delivery_fee']");
    });

    it('never uses bare return view()', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        expect($content)->not->toMatch('/return\s+view\s*\(/');
    });
});

// ============================================================
// Test group: StoreQuarterRequest form request
// ============================================================
describe('StoreQuarterRequest', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('extends FormRequest', function () {
        $reflection = new ReflectionClass(StoreQuarterRequest::class);
        expect($reflection->getParentClass()->getName())->toBe('Illuminate\Foundation\Http\FormRequest');
    });

    it('has authorize method checking can-manage-locations (BR-241)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/StoreQuarterRequest.php');
        expect($content)->toContain("can('can-manage-locations')");
    });

    it('requires name_en field (BR-232)', function () {
        $request = new StoreQuarterRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('name_en');
        expect($rules['name_en'])->toContain('required');
    });

    it('requires name_fr field (BR-232)', function () {
        $request = new StoreQuarterRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('name_fr');
        expect($rules['name_fr'])->toContain('required');
    });

    it('requires delivery_fee field (BR-234)', function () {
        $request = new StoreQuarterRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('delivery_fee');
        expect($rules['delivery_fee'])->toContain('required');
    });

    it('enforces delivery_fee as integer (BR-235)', function () {
        $request = new StoreQuarterRequest;
        $rules = $request->rules();
        expect($rules['delivery_fee'])->toContain('integer');
    });

    it('enforces delivery_fee min:0 (BR-234)', function () {
        $request = new StoreQuarterRequest;
        $rules = $request->rules();
        expect($rules['delivery_fee'])->toContain('min:0');
    });

    it('enforces max length of 255 for name_en', function () {
        $request = new StoreQuarterRequest;
        $rules = $request->rules();
        expect($rules['name_en'])->toContain('max:255');
    });

    it('enforces max length of 255 for name_fr', function () {
        $request = new StoreQuarterRequest;
        $rules = $request->rules();
        expect($rules['name_fr'])->toContain('max:255');
    });

    it('has messages method for localized validation', function () {
        $reflection = new ReflectionMethod(StoreQuarterRequest::class, 'messages');
        expect($reflection->isPublic())->toBeTrue();
        expect($reflection->getReturnType()->getName())->toBe('array');
    });

    it('messages cover all validation rules', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/StoreQuarterRequest.php');
        expect($content)->toContain("'name_en.required'");
        expect($content)->toContain("'name_fr.required'");
        expect($content)->toContain("'delivery_fee.required'");
        expect($content)->toContain("'delivery_fee.integer'");
        expect($content)->toContain("'delivery_fee.min'");
    });
});

// ============================================================
// Test group: DeliveryAreaService::addQuarter()
// ============================================================
describe('DeliveryAreaService::addQuarter', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has addQuarter method', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'addQuarter');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('addQuarter accepts Tenant, deliveryAreaId, nameEn, nameFr, and deliveryFee', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'addQuarter');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(5);
        expect($params[0]->getName())->toBe('tenant');
        expect($params[1]->getName())->toBe('deliveryAreaId');
        expect($params[2]->getName())->toBe('nameEn');
        expect($params[3]->getName())->toBe('nameFr');
        expect($params[4]->getName())->toBe('deliveryFee');
    });

    it('deliveryFee parameter is typed as int (BR-235)', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'addQuarter');
        $params = $reflection->getParameters();
        expect($params[4]->getType()->getName())->toBe('int');
    });

    it('checks uniqueness with case-insensitive comparison (BR-233)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $addQuarterMethod = substr($content, strpos($content, 'public function addQuarter'));
        $addQuarterEnd = strpos($addQuarterMethod, "\n    /**", 10);
        $addQuarterMethod = substr($addQuarterMethod, 0, $addQuarterEnd ?: strlen($addQuarterMethod));
        expect($addQuarterMethod)->toContain('mb_strtolower');
    });

    it('returns array with success, quarter_data, error, and warning', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $addQuarterMethod = substr($content, strpos($content, 'public function addQuarter'));
        $addQuarterEnd = strpos($addQuarterMethod, "\n    /**", 10);
        $addQuarterMethod = substr($addQuarterMethod, 0, $addQuarterEnd ?: strlen($addQuarterMethod));
        expect($addQuarterMethod)->toContain("'success'");
        expect($addQuarterMethod)->toContain("'quarter_data'");
        expect($addQuarterMethod)->toContain("'error'");
        expect($addQuarterMethod)->toContain("'warning'");
    });

    it('has high fee warning threshold constant', function () {
        $reflection = new ReflectionClass(DeliveryAreaService::class);
        expect($reflection->getConstant('HIGH_FEE_THRESHOLD'))->toBe(10000);
    });

    it('generates warning for high delivery fee', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $addQuarterMethod = substr($content, strpos($content, 'public function addQuarter'));
        $addQuarterEnd = strpos($addQuarterMethod, "\n    /**", 10);
        $addQuarterMethod = substr($addQuarterMethod, 0, $addQuarterEnd ?: strlen($addQuarterMethod));
        expect($addQuarterMethod)->toContain('HIGH_FEE_THRESHOLD');
    });
});

// ============================================================
// Test group: Blade View — Quarter additions (F-086)
// ============================================================
describe('Locations index blade view — quarter additions', function () {
    $projectRoot = dirname(__DIR__, 3);
    $viewPath = $projectRoot.'/resources/views/cook/locations/index.blade.php';

    it('has Add Quarter button in expanded town section', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Add Quarter')");
    });

    it('has quarter form state in x-data', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('quarter_name_en');
        expect($content)->toContain('quarter_name_fr');
        expect($content)->toContain('quarter_delivery_fee');
        expect($content)->toContain('showAddQuarterForm');
    });

    it('includes quarter state keys in x-sync', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("'quarter_name_en'");
        expect($content)->toContain("'quarter_name_fr'");
        expect($content)->toContain("'quarter_delivery_fee'");
    });

    it('has inline add quarter form with name fields (BR-232)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Quarter Name (English)')");
        expect($content)->toContain("__('Quarter Name (French)')");
    });

    it('has delivery fee input with XAF suffix (BR-234)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Delivery Fee')");
        expect($content)->toContain("__('XAF')");
        expect($content)->toContain('type="number"');
        expect($content)->toContain('min="0"');
    });

    it('has free delivery helper text', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Enter 0 for free delivery to this quarter.')");
    });

    it('shows free delivery badge for 0 fee quarters (BR-236)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        // F-087: Updated from "Free" to "Free delivery" per BR-246
        expect($content)->toContain("__('Free delivery')");
        expect($content)->toContain('bg-success-subtle');
        expect($content)->toContain('text-success');
    });

    it('uses $action for quarter form submission to correct endpoint', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("url('/dashboard/locations/quarters/'");
    });

    it('has x-message for quarter validation errors', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('x-message="quarter_name_en"');
        expect($content)->toContain('x-message="quarter_name_fr"');
        expect($content)->toContain('x-message="quarter_delivery_fee"');
    });

    it('has x-name for quarter form Gale name bindings', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('x-name="quarter_name_en"');
        expect($content)->toContain('x-name="quarter_name_fr"');
        expect($content)->toContain('x-name="quarter_delivery_fee"');
    });

    it('has save quarter and cancel buttons', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Save Quarter')");
    });

    it('has loading state with $fetching() in quarter form', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        // Multiple $fetching() calls exist (town form + quarter form)
        preg_match_all('/\$fetching\(\)/', $content, $matches);
        expect(count($matches[0]))->toBeGreaterThanOrEqual(4);
    });

    it('has empty state for quarters within a town', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        // F-087: Updated empty state per BR-248
        expect($content)->toContain("__('No quarters added yet. Add your first quarter.')");
    });

    it('displays bilingual quarter names in list', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("quarter['quarter_name_en']");
        expect($content)->toContain("quarter['quarter_name_fr']");
    });

    it('has showQuarterForm and hideQuarterForm Alpine methods', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('showQuarterForm(');
        expect($content)->toContain('hideQuarterForm()');
    });

    it('has resetQuarterForm Alpine method', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('resetQuarterForm()');
    });

    it('does not have "Quarter management coming soon" anymore', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->not->toContain("__('Quarter management coming soon')");
    });
});

// ============================================================
// Test group: Route Configuration
// ============================================================
describe('Quarter route configuration', function () {
    $projectRoot = dirname(__DIR__, 3);
    $routesContent = file_get_contents($projectRoot.'/routes/web.php');

    it('has quarter store route', function () use ($routesContent) {
        expect($routesContent)->toContain("Route::post('/locations/quarters/{deliveryArea}'");
    });

    it('uses QuarterController', function () use ($routesContent) {
        expect($routesContent)->toContain('QuarterController::class');
    });

    it('has QuarterController import', function () use ($routesContent) {
        expect($routesContent)->toContain('use App\Http\Controllers\Cook\QuarterController;');
    });

    it('has named route for quarter store', function () use ($routesContent) {
        expect($routesContent)->toContain('cook.locations.quarters.store');
    });
});

// ============================================================
// Test group: Translation Strings
// ============================================================
describe('Quarter translation strings', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has English translations for quarter feature', function () use ($projectRoot) {
        $enContent = file_get_contents($projectRoot.'/lang/en.json');
        $en = json_decode($enContent, true);

        expect($en)->toHaveKey('Add Quarter');
        expect($en)->toHaveKey('Quarter Name (English)');
        expect($en)->toHaveKey('Quarter Name (French)');
        expect($en)->toHaveKey('Save Quarter');
        expect($en)->toHaveKey('Delivery Fee');
        expect($en)->toHaveKey('Free');
        expect($en)->toHaveKey('Quarter added successfully.');
        expect($en)->toHaveKey('Quarter name is required in English.');
        expect($en)->toHaveKey('Quarter name is required in French.');
        expect($en)->toHaveKey('Delivery fee is required.');
        expect($en)->toHaveKey('Delivery fee must be 0 or greater.');
        expect($en)->toHaveKey('Enter 0 for free delivery to this quarter.');
        expect($en)->toHaveKey('Add Quarter to');
    });

    it('has French translations for quarter feature', function () use ($projectRoot) {
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        expect($fr)->toHaveKey('Add Quarter');
        expect($fr)->toHaveKey('Quarter Name (English)');
        expect($fr)->toHaveKey('Quarter Name (French)');
        expect($fr)->toHaveKey('Save Quarter');
        expect($fr)->toHaveKey('Delivery Fee');
        expect($fr)->toHaveKey('Free');
        expect($fr)->toHaveKey('Quarter added successfully.');
        expect($fr)->toHaveKey('Quarter name is required in English.');
        expect($fr)->toHaveKey('Quarter name is required in French.');
        expect($fr)->toHaveKey('Delivery fee is required.');
        expect($fr)->toHaveKey('Delivery fee must be 0 or greater.');
        expect($fr)->toHaveKey('Enter 0 for free delivery to this quarter.');
        expect($fr)->toHaveKey('Add Quarter to');
    });

    it('French translations are not identical to English keys', function () use ($projectRoot) {
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        expect($fr['Save Quarter'])->not->toBe('Save Quarter');
        expect($fr['Free'])->not->toBe('Free');
        expect($fr['Enter 0 for free delivery to this quarter.'])->not->toBe('Enter 0 for free delivery to this quarter.');
        expect($fr['Add Quarter to'])->not->toBe('Add Quarter to');
    });
});

// ============================================================
// Test group: Quarter Model
// ============================================================
describe('Quarter model', function () {
    it('uses HasTranslatable trait', function () {
        $reflection = new ReflectionClass(Quarter::class);
        $traits = $reflection->getTraitNames();
        expect($traits)->toContain('App\Traits\HasTranslatable');
    });

    it('has translatable name field', function () {
        $quarter = new Quarter;
        $reflection = new ReflectionProperty(Quarter::class, 'translatable');
        $reflection->setAccessible(true);
        expect($reflection->getValue($quarter))->toContain('name');
    });

    it('has name_en and name_fr in fillable', function () {
        $quarter = new Quarter;
        expect($quarter->getFillable())->toContain('name_en');
        expect($quarter->getFillable())->toContain('name_fr');
    });

    it('has town_id in fillable', function () {
        $quarter = new Quarter;
        expect($quarter->getFillable())->toContain('town_id');
    });

    it('has town relationship', function () {
        $reflection = new ReflectionMethod(Quarter::class, 'town');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has scopeForTown query scope (BR-239)', function () {
        $reflection = new ReflectionMethod(Quarter::class, 'scopeForTown');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('casts is_active as boolean', function () {
        $quarter = new Quarter;
        $casts = $quarter->getCasts();
        expect($casts)->toHaveKey('is_active');
        expect($casts['is_active'])->toBe('boolean');
    });
});
