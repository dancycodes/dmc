<?php

/**
 * F-092: Add Pickup Location -- Unit Tests
 *
 * Tests for PickupLocationController, StorePickupLocationRequest, DeliveryAreaService::addPickupLocation(),
 * PickupLocation model, pickup blade view, route configuration, and translation strings.
 *
 * BR-281: Location name is required in both English and French
 * BR-282: Town selection is required (from cook's existing towns)
 * BR-283: Quarter selection is required (from quarters within the selected town)
 * BR-284: Address/description is required (free text, max 500 characters)
 * BR-285: Pickup locations have no delivery fee (fee is always 0/N/A)
 * BR-286: Pickup location is scoped to the current tenant
 * BR-287: Save via Gale; location appears in list without page reload
 * BR-288: Only users with location management permission can add pickup locations
 */

use App\Http\Controllers\Cook\PickupLocationController;
use App\Http\Requests\Cook\StorePickupLocationRequest;
use App\Models\DeliveryArea;
use App\Models\DeliveryAreaQuarter;
use App\Models\PickupLocation;
use App\Models\Quarter;
use App\Models\Tenant;
use App\Models\Town;
use App\Services\DeliveryAreaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

$projectRoot = dirname(__DIR__, 3);

/* Database integration tests require full app context */
uses(Tests\TestCase::class, RefreshDatabase::class);

// ============================================================
// Test group: PickupLocationController methods
// ============================================================
describe('PickupLocationController', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has an index method', function () {
        $reflection = new ReflectionMethod(PickupLocationController::class, 'index');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a store method', function () {
        $reflection = new ReflectionMethod(PickupLocationController::class, 'store');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('index method accepts Request and DeliveryAreaService parameters', function () {
        $reflection = new ReflectionMethod(PickupLocationController::class, 'index');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('request');
        expect($params[1]->getName())->toBe('deliveryAreaService');
    });

    it('store method accepts Request and DeliveryAreaService parameters', function () {
        $reflection = new ReflectionMethod(PickupLocationController::class, 'store');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('request');
        expect($params[1]->getName())->toBe('deliveryAreaService');
    });

    it('index checks can-manage-locations permission (BR-288)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->toContain("can('can-manage-locations')");
        expect($content)->toContain('abort(403)');
    });

    it('store checks can-manage-locations permission (BR-288)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $storeSection = substr($content, strpos($content, 'public function store'));
        expect($storeSection)->toContain("can('can-manage-locations')");
    });

    it('store uses dual Gale/HTTP validation pattern', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->toContain('isGale()');
        expect($content)->toContain('validateState');
        expect($content)->toContain('StorePickupLocationRequest');
    });

    it('store validates pickup_name_en as required (BR-281)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->toContain("'pickup_name_en'");
        expect($content)->toContain("'required'");
    });

    it('store validates pickup_name_fr as required (BR-281)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->toContain("'pickup_name_fr'");
    });

    it('store validates pickup_town_id as required with exists rule (BR-282)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->toContain("'pickup_town_id'");
        expect($content)->toContain("'exists:towns,id'");
    });

    it('store validates pickup_quarter_id as required with exists rule (BR-283)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->toContain("'pickup_quarter_id'");
        expect($content)->toContain("'exists:quarters,id'");
    });

    it('store validates pickup_address as required with max:500 (BR-284)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->toContain("'pickup_address'");
        expect($content)->toContain("'max:500'");
    });

    it('store trims whitespace from all text fields', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->toContain("trim(\$validated['pickup_name_en'])");
        expect($content)->toContain("trim(\$validated['pickup_name_fr'])");
        expect($content)->toContain("trim(\$validated['pickup_address'])");
    });

    it('store uses DeliveryAreaService::addPickupLocation', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->toContain('addPickupLocation');
    });

    it('store logs activity on successful creation', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->toContain("activity('pickup_locations')");
        expect($content)->toContain("'pickup_location_added'");
    });

    it('store returns Gale redirect with success toast (BR-287)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->toContain("gale()")
            ->and($content)->toContain("->redirect(url('/dashboard/locations/pickup'))")
            ->and($content)->toContain("->with('success'");
    });

    it('store returns gale messages on validation error', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->toContain('gale()->messages');
    });

    it('index returns gale view with web:true', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->toContain("gale()->view('cook.locations.pickup'")
            ->and($content)->toContain('web: true');
    });

    it('index passes pickupLocations and deliveryAreas to view', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->toContain("'pickupLocations'")
            ->and($content)->toContain("'deliveryAreas'");
    });
});

// ============================================================
// Test group: StorePickupLocationRequest
// ============================================================
describe('StorePickupLocationRequest', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('exists', function () {
        expect(class_exists(StorePickupLocationRequest::class))->toBeTrue();
    });

    it('extends FormRequest', function () {
        $reflection = new ReflectionClass(StorePickupLocationRequest::class);
        expect($reflection->getParentClass()->getName())->toBe(\Illuminate\Foundation\Http\FormRequest::class);
    });

    it('has authorize method checking can-manage-locations', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/StorePickupLocationRequest.php');
        expect($content)->toContain("can('can-manage-locations')");
    });

    it('validates name_en as required string max:255 (BR-281)', function () {
        $request = new StorePickupLocationRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('name_en');
        expect($rules['name_en'])->toContain('required');
        expect($rules['name_en'])->toContain('string');
        expect($rules['name_en'])->toContain('max:255');
    });

    it('validates name_fr as required string max:255 (BR-281)', function () {
        $request = new StorePickupLocationRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('name_fr');
        expect($rules['name_fr'])->toContain('required');
        expect($rules['name_fr'])->toContain('string');
        expect($rules['name_fr'])->toContain('max:255');
    });

    it('validates town_id as required integer exists (BR-282)', function () {
        $request = new StorePickupLocationRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('town_id');
        expect($rules['town_id'])->toContain('required');
        expect($rules['town_id'])->toContain('integer');
        expect($rules['town_id'])->toContain('exists:towns,id');
    });

    it('validates quarter_id as required integer exists (BR-283)', function () {
        $request = new StorePickupLocationRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('quarter_id');
        expect($rules['quarter_id'])->toContain('required');
        expect($rules['quarter_id'])->toContain('integer');
        expect($rules['quarter_id'])->toContain('exists:quarters,id');
    });

    it('validates address as required string max:500 (BR-284)', function () {
        $request = new StorePickupLocationRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('address');
        expect($rules['address'])->toContain('required');
        expect($rules['address'])->toContain('string');
        expect($rules['address'])->toContain('max:500');
    });

    it('has localized validation messages', function () {
        $request = new StorePickupLocationRequest;
        $messages = $request->messages();
        expect($messages)->toHaveKey('name_en.required');
        expect($messages)->toHaveKey('name_fr.required');
        expect($messages)->toHaveKey('town_id.required');
        expect($messages)->toHaveKey('quarter_id.required');
        expect($messages)->toHaveKey('address.required');
        expect($messages)->toHaveKey('address.max');
    });
});

// ============================================================
// Test group: DeliveryAreaService pickup methods
// ============================================================
describe('DeliveryAreaService pickup methods', function () {

    it('has addPickupLocation method', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'addPickupLocation');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('addPickupLocation accepts correct parameters', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'addPickupLocation');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(6);
        expect($params[0]->getName())->toBe('tenant');
        expect($params[1]->getName())->toBe('nameEn');
        expect($params[2]->getName())->toBe('nameFr');
        expect($params[3]->getName())->toBe('townId');
        expect($params[4]->getName())->toBe('quarterId');
        expect($params[5]->getName())->toBe('address');
    });

    it('addPickupLocation returns array with success, pickup, pickup_model, and error keys', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'addPickupLocation');
        $docComment = $reflection->getDocComment();
        expect($docComment)->toContain('success');
        expect($docComment)->toContain('pickup');
        expect($docComment)->toContain('pickup_model');
        expect($docComment)->toContain('error');
    });

    it('has getPickupLocationsData method', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'getPickupLocationsData');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has removePickupLocation method', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'removePickupLocation');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('addPickupLocation verifies town belongs to tenant delivery areas', function () {
        $projectRoot = dirname(__DIR__, 3);
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $addPickupSection = substr($content, strpos($content, 'function addPickupLocation'));
        expect($addPickupSection)->toContain("where('tenant_id', \$tenant->id)")
            ->and($addPickupSection)->toContain("where('town_id', \$townId)");
    });

    it('addPickupLocation verifies quarter belongs to town', function () {
        $projectRoot = dirname(__DIR__, 3);
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $addPickupSection = substr($content, strpos($content, 'function addPickupLocation'));
        expect($addPickupSection)->toContain("where('id', \$quarterId)")
            ->and($addPickupSection)->toContain("where('town_id', \$townId)");
    });
});

// ============================================================
// Test group: PickupLocation model
// ============================================================
describe('PickupLocation model', function () {

    it('exists', function () {
        expect(class_exists(PickupLocation::class))->toBeTrue();
    });

    it('has correct table name', function () {
        $model = new PickupLocation;
        expect($model->getTable())->toBe('pickup_locations');
    });

    it('has correct fillable attributes (BR-286)', function () {
        $model = new PickupLocation;
        $fillable = $model->getFillable();
        expect($fillable)->toContain('tenant_id');
        expect($fillable)->toContain('town_id');
        expect($fillable)->toContain('quarter_id');
        expect($fillable)->toContain('name_en');
        expect($fillable)->toContain('name_fr');
        expect($fillable)->toContain('address');
    });

    it('has translatable property for name field', function () {
        $model = new PickupLocation;
        $reflection = new ReflectionProperty(PickupLocation::class, 'translatable');
        $reflection->setAccessible(true);
        expect($reflection->getValue($model))->toContain('name');
    });

    it('has tenant relationship', function () {
        $model = new PickupLocation;
        expect(method_exists($model, 'tenant'))->toBeTrue();
    });

    it('has town relationship', function () {
        $model = new PickupLocation;
        expect(method_exists($model, 'town'))->toBeTrue();
    });

    it('has quarter relationship', function () {
        $model = new PickupLocation;
        expect(method_exists($model, 'quarter'))->toBeTrue();
    });

    it('uses HasFactory trait', function () {
        $reflection = new ReflectionClass(PickupLocation::class);
        $traits = $reflection->getTraitNames();
        expect($traits)->toContain('Illuminate\Database\Eloquent\Factories\HasFactory');
    });

    it('uses HasTranslatable trait', function () {
        $reflection = new ReflectionClass(PickupLocation::class);
        $traits = $reflection->getTraitNames();
        expect($traits)->toContain('App\Traits\HasTranslatable');
    });

    it('uses LogsActivityTrait for audit logging', function () {
        $reflection = new ReflectionClass(PickupLocation::class);
        $traits = $reflection->getTraitNames();
        expect($traits)->toContain('App\Traits\LogsActivityTrait');
    });
});

// ============================================================
// Test group: PickupLocation factory
// ============================================================
describe('PickupLocationFactory', function () {

    it('factory exists', function () {
        expect(class_exists(\Database\Factories\PickupLocationFactory::class))->toBeTrue();
    });

    it('factory definition returns correct keys', function () {
        $factory = PickupLocation::factory();
        $definition = $factory->definition();
        expect($definition)->toHaveKey('tenant_id');
        expect($definition)->toHaveKey('town_id');
        expect($definition)->toHaveKey('quarter_id');
        expect($definition)->toHaveKey('name_en');
        expect($definition)->toHaveKey('name_fr');
        expect($definition)->toHaveKey('address');
    });
});

// ============================================================
// Test group: Blade view
// ============================================================
describe('Pickup Locations blade view', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('view file exists', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/resources/views/cook/locations/pickup.blade.php'))->toBeTrue();
    });

    it('extends cook-dashboard layout', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('uses __() for all user-facing strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("__('Pickup Locations')");
        expect($content)->toContain("__('Dashboard')");
        expect($content)->toContain("__('Locations')");
        expect($content)->toContain("__('Add Pickup Location')");
        expect($content)->toContain("__('Save')");
        expect($content)->toContain("__('Cancel')");
    });

    it('has breadcrumb navigation', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("__('Breadcrumb')")
            ->and($content)->toContain("__('Dashboard')")
            ->and($content)->toContain("__('Locations')")
            ->and($content)->toContain("__('Pickup Locations')");
    });

    it('has x-data with pickup form state keys', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('pickup_name_en')
            ->and($content)->toContain('pickup_name_fr')
            ->and($content)->toContain('pickup_town_id')
            ->and($content)->toContain('pickup_quarter_id')
            ->and($content)->toContain('pickup_address');
    });

    it('has x-sync for required state keys', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("x-sync=")
            ->and($content)->toContain("'pickup_name_en'")
            ->and($content)->toContain("'pickup_name_fr'")
            ->and($content)->toContain("'pickup_town_id'")
            ->and($content)->toContain("'pickup_quarter_id'")
            ->and($content)->toContain("'pickup_address'");
    });

    it('has cascading town/quarter dropdown with getQuartersForTown()', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('getQuartersForTown()');
    });

    it('resets quarter when town changes', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("@change=\"pickup_quarter_id = ''\"");
    });

    it('has x-message directives for validation error display', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('x-message="pickup_name_en"')
            ->and($content)->toContain('x-message="pickup_name_fr"')
            ->and($content)->toContain('x-message="pickup_town_id"')
            ->and($content)->toContain('x-message="pickup_quarter_id"')
            ->and($content)->toContain('x-message="pickup_address"');
    });

    it('has $action call to store endpoint (BR-287)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("\$action('{{ url('/dashboard/locations/pickup') }}')");
    });

    it('has $fetching() loading state on submit button', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('$fetching()');
    });

    it('disables submit when fields are empty', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('!pickup_name_en.trim()')
            ->and($content)->toContain('!pickup_name_fr.trim()')
            ->and($content)->toContain('!pickup_town_id')
            ->and($content)->toContain('!pickup_quarter_id')
            ->and($content)->toContain('!pickup_address.trim()');
    });

    it('has no towns warning when deliveryAreas is empty (Scenario 3)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("__('Add a town first before creating pickup locations.')");
    });

    it('has no quarters warning when town has no quarters', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("__('No quarters available for this town. Add quarters first.')");
    });

    it('has empty state for no pickup locations', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("__('No pickup locations yet')");
    });

    it('displays Free badge for pickup locations (BR-285)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("__('Free')");
    });

    it('has character counter for address field (max 500)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('addressCharCount')
            ->and($content)->toContain('500');
    });

    it('has maxlength=500 on address textarea (BR-284)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('maxlength="500"');
    });

    it('uses semantic color tokens (not hardcoded colors)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('bg-surface')
            ->and($content)->toContain('text-on-surface')
            ->and($content)->toContain('bg-primary')
            ->and($content)->toContain('text-on-primary')
            ->and($content)->toContain('border-outline');
    });

    it('has dark mode variants', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('dark:');
    });

    it('has success and error toast sections', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("session('success')")
            ->and($content)->toContain("session('error')");
    });

    it('has Back to Locations link', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("__('Back to Locations')");
    });

    it('uses pin icon for pickup locations', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        // Lucide map-pin icon path
        expect($content)->toContain('M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z');
    });
});

// ============================================================
// Test group: Route configuration
// ============================================================
describe('Pickup location routes', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('registers GET route for pickup locations index', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain("Route::get('/locations/pickup'")
            ->and($content)->toContain('PickupLocationController::class')
            ->and($content)->toContain("'index'");
    });

    it('registers POST route for pickup locations store', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain("Route::post('/locations/pickup'")
            ->and($content)->toContain('PickupLocationController::class')
            ->and($content)->toContain("'store'");
    });

    it('has named routes for pickup locations', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain("'cook.locations.pickup.index'")
            ->and($content)->toContain("'cook.locations.pickup.store'");
    });

    it('imports PickupLocationController', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain('use App\Http\Controllers\Cook\PickupLocationController;');
    });
});

// ============================================================
// Test group: Translation strings
// ============================================================
describe('Translation strings', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('English translations include all required strings', function () use ($projectRoot) {
        $en = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
        expect($en)->toHaveKey('Pickup location added successfully.');
        expect($en)->toHaveKey('No pickup locations yet');
        expect($en)->toHaveKey('Add pickup locations so clients can collect their orders from you directly.');
        expect($en)->toHaveKey('Add a town first before creating pickup locations.');
        expect($en)->toHaveKey('No quarters available for this town. Add quarters first.');
        expect($en)->toHaveKey('No towns available');
        expect($en)->toHaveKey('Location name must not exceed 255 characters.');
        expect($en)->toHaveKey('Address must not exceed 500 characters.');
        expect($en)->toHaveKey('The selected town is invalid.');
        expect($en)->toHaveKey('The selected quarter is invalid.');
        expect($en)->toHaveKey('Add Pickup Location');
        expect($en)->toHaveKey('Go to Locations');
    });

    it('French translations include all required strings', function () use ($projectRoot) {
        $fr = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);
        expect($fr)->toHaveKey('Pickup location added successfully.');
        expect($fr)->toHaveKey('No pickup locations yet');
        expect($fr)->toHaveKey('Add pickup locations so clients can collect their orders from you directly.');
        expect($fr)->toHaveKey('Add a town first before creating pickup locations.');
        expect($fr)->toHaveKey('No quarters available for this town. Add quarters first.');
        expect($fr)->toHaveKey('No towns available');
        expect($fr)->toHaveKey('Location name must not exceed 255 characters.');
        expect($fr)->toHaveKey('Address must not exceed 500 characters.');
        expect($fr)->toHaveKey('The selected town is invalid.');
        expect($fr)->toHaveKey('The selected quarter is invalid.');
        expect($fr)->toHaveKey('Add Pickup Location');
        expect($fr)->toHaveKey('Go to Locations');
    });

    it('French translations are not just English copies', function () use ($projectRoot) {
        $fr = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);
        expect($fr['Pickup location added successfully.'])->not->toBe('Pickup location added successfully.');
        expect($fr['No pickup locations yet'])->not->toBe('No pickup locations yet');
        expect($fr['Add Pickup Location'])->not->toBe('Add Pickup Location');
    });
});

// ============================================================
// Test group: Database schema
// ============================================================
describe('Database schema', function () {

    it('pickup_locations table exists', function () {
        expect(\Illuminate\Support\Facades\Schema::hasTable('pickup_locations'))->toBeTrue();
    });

    it('pickup_locations table has required columns', function () {
        expect(\Illuminate\Support\Facades\Schema::hasColumns('pickup_locations', [
            'id', 'tenant_id', 'town_id', 'quarter_id', 'name_en', 'name_fr', 'address',
            'created_at', 'updated_at',
        ]))->toBeTrue();
    });
});

// ============================================================
// Test group: Database integration (with factories)
// ============================================================
describe('PickupLocation database integration', function () {

    it('can create a pickup location via factory', function () {
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);
        $tenant = Tenant::factory()->create();

        $pickup = PickupLocation::factory()->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
        ]);

        expect($pickup)->toBeInstanceOf(PickupLocation::class);
        expect($pickup->tenant_id)->toBe($tenant->id);
        expect($pickup->town_id)->toBe($town->id);
        expect($pickup->quarter_id)->toBe($quarter->id);
    });

    it('pickup location belongs to tenant (BR-286)', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);

        $pickup = PickupLocation::factory()->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
        ]);

        expect($pickup->tenant->id)->toBe($tenant->id);
    });

    it('pickup location belongs to town (BR-282)', function () {
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);

        $pickup = PickupLocation::factory()->create([
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
        ]);

        expect($pickup->town->id)->toBe($town->id);
    });

    it('pickup location belongs to quarter (BR-283)', function () {
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);

        $pickup = PickupLocation::factory()->create([
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
        ]);

        expect($pickup->quarter->id)->toBe($quarter->id);
    });

    it('service adds pickup location for valid input', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);

        // Create delivery area to link town to tenant
        DeliveryArea::create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
        ]);

        $service = new DeliveryAreaService;
        $result = $service->addPickupLocation(
            $tenant,
            'My Kitchen',
            'Ma Cuisine',
            $town->id,
            $quarter->id,
            'Behind Akwa Palace Hotel'
        );

        expect($result['success'])->toBeTrue();
        expect($result['pickup'])->not->toBeNull();
        expect($result['pickup']['name_en'])->toBe('My Kitchen');
        expect($result['pickup']['name_fr'])->toBe('Ma Cuisine');
        expect($result['pickup']['address'])->toBe('Behind Akwa Palace Hotel');
        expect($result['pickup_model'])->toBeInstanceOf(PickupLocation::class);
    });

    it('service rejects town not in delivery areas', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);

        // Do NOT create delivery area link

        $service = new DeliveryAreaService;
        $result = $service->addPickupLocation(
            $tenant,
            'My Kitchen',
            'Ma Cuisine',
            $town->id,
            $quarter->id,
            'Some address'
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->not->toBeEmpty();
    });

    it('service rejects quarter not belonging to town', function () {
        $tenant = Tenant::factory()->create();
        $town1 = Town::factory()->create();
        $town2 = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town2->id]);

        DeliveryArea::create([
            'tenant_id' => $tenant->id,
            'town_id' => $town1->id,
        ]);

        $service = new DeliveryAreaService;
        $result = $service->addPickupLocation(
            $tenant,
            'My Kitchen',
            'Ma Cuisine',
            $town1->id,
            $quarter->id,
            'Some address'
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->not->toBeEmpty();
    });

    it('allows multiple pickup locations in same quarter (edge case)', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);

        DeliveryArea::create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
        ]);

        $service = new DeliveryAreaService;
        $result1 = $service->addPickupLocation($tenant, 'Kitchen 1', 'Cuisine 1', $town->id, $quarter->id, 'Address 1');
        $result2 = $service->addPickupLocation($tenant, 'Kitchen 2', 'Cuisine 2', $town->id, $quarter->id, 'Address 2');

        expect($result1['success'])->toBeTrue();
        expect($result2['success'])->toBeTrue();

        $count = PickupLocation::where('tenant_id', $tenant->id)->where('quarter_id', $quarter->id)->count();
        expect($count)->toBe(2);
    });

    it('cascade deletes pickup location when town is deleted', function () {
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);
        $tenant = Tenant::factory()->create();

        $pickup = PickupLocation::factory()->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
        ]);

        $pickupId = $pickup->id;
        $town->delete();

        expect(PickupLocation::find($pickupId))->toBeNull();
    });

    it('service getPickupLocationsData returns correct format', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);

        PickupLocation::factory()->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
            'name_en' => 'Test Location',
            'name_fr' => 'Lieu Test',
            'address' => 'Test Address',
        ]);

        $service = new DeliveryAreaService;
        $data = $service->getPickupLocationsData($tenant);

        expect($data)->toHaveCount(1);
        expect($data[0])->toHaveKey('id');
        expect($data[0])->toHaveKey('name');
        expect($data[0])->toHaveKey('name_en');
        expect($data[0])->toHaveKey('name_fr');
        expect($data[0])->toHaveKey('town_name');
        expect($data[0])->toHaveKey('quarter_name');
        expect($data[0])->toHaveKey('address');
    });
});

// ============================================================
// Test group: Migration
// ============================================================
describe('Address column migration', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('migration file exists to widen address to 500', function () use ($projectRoot) {
        $migrationFile = $projectRoot.'/database/migrations/2026_02_18_014000_alter_pickup_locations_address_column_to_500.php';
        expect(file_exists($migrationFile))->toBeTrue();
    });

    it('migration changes address column to 500 chars', function () use ($projectRoot) {
        $migrationFile = $projectRoot.'/database/migrations/2026_02_18_014000_alter_pickup_locations_address_column_to_500.php';
        $content = file_get_contents($migrationFile);
        expect($content)->toContain("string('address', 500)")
            ->and($content)->toContain('->change()');
    });
});
