<?php

/**
 * F-082: Add Town — Unit Tests
 *
 * Tests for TownController, StoreTownRequest, DeliveryAreaService::addTown(),
 * town blade view, route configuration, and translation strings.
 *
 * BR-207: Town name required in both EN and FR
 * BR-208: Town name must be unique within this cook's towns
 * BR-209: Town is scoped to the current tenant (via delivery_areas)
 * BR-210: Save via Gale; town appears in list without page reload
 * BR-211: All validation messages use __() localization
 * BR-212: Only users with location management permission
 */

use App\Http\Controllers\Cook\TownController;
use App\Http\Requests\Cook\StoreTownRequest;
use App\Models\Town;
use App\Services\DeliveryAreaService;

$projectRoot = dirname(__DIR__, 3);

// ============================================================
// Test group: TownController methods
// ============================================================
describe('TownController', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has an index method', function () {
        $reflection = new ReflectionMethod(TownController::class, 'index');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a store method', function () {
        $reflection = new ReflectionMethod(TownController::class, 'store');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('index method accepts Request and DeliveryAreaService parameters', function () {
        $reflection = new ReflectionMethod(TownController::class, 'index');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('request');
        expect($params[1]->getName())->toBe('deliveryAreaService');
    });

    it('store method accepts Request and DeliveryAreaService parameters', function () {
        $reflection = new ReflectionMethod(TownController::class, 'store');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('request');
        expect($params[1]->getName())->toBe('deliveryAreaService');
    });

    it('index returns gale view response (BR-210)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->toContain("gale()->view('cook.locations.index'");
        expect($content)->toContain('web: true');
    });

    it('index passes deliveryAreas to view', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->toContain("'deliveryAreas'");
    });

    it('index checks can-manage-locations permission (BR-212)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->toContain("can('can-manage-locations')");
        expect($content)->toContain('abort(403)');
    });

    it('store checks can-manage-locations permission (BR-212)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        $storeContent = substr($content, strpos($content, 'public function store'));
        expect($storeContent)->toContain("can('can-manage-locations')");
    });

    it('store uses dual Gale/HTTP validation pattern', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->toContain('isGale()');
        expect($content)->toContain('validateState');
        expect($content)->toContain('StoreTownRequest');
    });

    it('store trims whitespace from names', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->toContain("trim(\$validated['name_en'])");
        expect($content)->toContain("trim(\$validated['name_fr'])");
    });

    it('store uses DeliveryAreaService::addTown for business logic', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->toContain('$deliveryAreaService->addTown(');
    });

    it('store returns Gale messages on duplicate error (BR-208)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->toContain('gale()->messages(');
    });

    it('store redirects with success message on save (BR-210)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->toContain("redirect(url('/dashboard/locations'))");
        expect($content)->toContain("__('Town added successfully.')");
    });

    it('store logs activity on town creation', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->toContain("activity('delivery_areas')");
        expect($content)->toContain("'town_added'");
    });

    it('never uses bare return view()', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->not->toMatch('/return\s+view\s*\(/');
    });
});

// ============================================================
// Test group: StoreTownRequest form request
// ============================================================
describe('StoreTownRequest', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('extends FormRequest', function () {
        $reflection = new ReflectionClass(StoreTownRequest::class);
        expect($reflection->getParentClass()->getName())->toBe('Illuminate\Foundation\Http\FormRequest');
    });

    it('has authorize method checking can-manage-locations (BR-212)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/StoreTownRequest.php');
        expect($content)->toContain("can('can-manage-locations')");
    });

    it('requires name_en field (BR-207)', function () {
        $request = new StoreTownRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('name_en');
        expect($rules['name_en'])->toContain('required');
    });

    it('requires name_fr field (BR-207)', function () {
        $request = new StoreTownRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('name_fr');
        expect($rules['name_fr'])->toContain('required');
    });

    it('enforces max length of 255 for name_en', function () {
        $request = new StoreTownRequest;
        $rules = $request->rules();
        expect($rules['name_en'])->toContain('max:255');
    });

    it('enforces max length of 255 for name_fr', function () {
        $request = new StoreTownRequest;
        $rules = $request->rules();
        expect($rules['name_fr'])->toContain('max:255');
    });

    it('has messages method for localized validation (BR-211)', function () {
        $reflection = new ReflectionMethod(StoreTownRequest::class, 'messages');
        expect($reflection->isPublic())->toBeTrue();
        expect($reflection->getReturnType()->getName())->toBe('array');
    });
});

// ============================================================
// Test group: DeliveryAreaService::addTown()
// ============================================================
describe('DeliveryAreaService::addTown', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has addTown method', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'addTown');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('addTown accepts Tenant and name strings', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'addTown');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(3);
        expect($params[0]->getName())->toBe('tenant');
        expect($params[1]->getName())->toBe('nameEn');
        expect($params[2]->getName())->toBe('nameFr');
    });

    it('checks uniqueness with case-insensitive comparison (BR-208)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        // The service uses LOWER() SQL function for case-insensitive comparison
        expect($content)->toContain('mb_strtolower');
    });

    it('creates delivery area link for tenant (BR-209)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $addTownMethod = substr($content, strpos($content, 'public function addTown'));
        $addTownEnd = strpos($addTownMethod, "\n    /**", 10);
        $addTownMethod = substr($addTownMethod, 0, $addTownEnd ?: strlen($addTownMethod));
        expect($addTownMethod)->toContain("'tenant_id'");
        expect($addTownMethod)->toContain("'town_id'");
    });

    it('returns array with success flag', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $addTownMethod = substr($content, strpos($content, 'public function addTown'));
        $addTownEnd = strpos($addTownMethod, "\n    /**", 10);
        $addTownMethod = substr($addTownMethod, 0, $addTownEnd ?: strlen($addTownMethod));
        expect($addTownMethod)->toContain("'success'");
        expect($addTownMethod)->toContain("'delivery_area'");
        expect($addTownMethod)->toContain("'error'");
    });
});

// ============================================================
// Test group: Blade View
// ============================================================
describe('Locations index blade view', function () {
    $projectRoot = dirname(__DIR__, 3);
    $viewPath = $projectRoot.'/resources/views/cook/locations/index.blade.php';

    it('exists', function () use ($viewPath) {
        expect(file_exists($viewPath))->toBeTrue();
    });

    it('extends cook-dashboard layout', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('sets page-title section', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("@section('page-title'");
    });

    it('has breadcrumb navigation', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Dashboard')");
        expect($content)->toContain("__('Locations')");
    });

    it('has Add Town button', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Add Town')");
    });

    it('has inline expandable add town form', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('showAddTownForm');
        expect($content)->toContain("__('Add New Town')");
    });

    it('has both English and French name fields (BR-207)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Town Name (English)')");
        expect($content)->toContain("__('Town Name (French)')");
    });

    it('uses x-data for Alpine state', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('x-data');
        expect($content)->toContain('name_en');
        expect($content)->toContain('name_fr');
    });

    it('uses x-sync for Gale state sync', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('x-sync');
    });

    it('uses $action for form submission (BR-210)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('$action(');
    });

    it('uses x-message for validation error display', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('x-message="name_en"');
        expect($content)->toContain('x-message="name_fr"');
    });

    it('has loading state with $fetching()', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('$fetching()');
    });

    it('has empty state for no towns', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('No delivery towns yet')");
    });

    it('has save and cancel buttons', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Save Town')");
        expect($content)->toContain("__('Cancel')");
    });

    it('uses semantic color tokens for dark mode support', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('bg-surface');
        expect($content)->toContain('text-on-surface');
        expect($content)->toContain('bg-primary');
    });

    it('has success toast for flash messages', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("session('success')");
        expect($content)->toContain('bg-success-subtle');
    });

    it('all user-facing strings use __() helper (BR-211)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Delivery Towns')");
        expect($content)->toContain("__('Add Town')");
        expect($content)->toContain("__('Save Town')");
        expect($content)->toContain("__('Cancel')");
        expect($content)->toContain("__('Locations')");
    });

    it('uses x-name for Gale name binding on inputs', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('x-name="name_en"');
        expect($content)->toContain('x-name="name_fr"');
    });
});

// ============================================================
// Test group: Route Configuration
// ============================================================
describe('Route configuration', function () {
    $projectRoot = dirname(__DIR__, 3);
    $routesContent = file_get_contents($projectRoot.'/routes/web.php');

    it('has location index route', function () use ($routesContent) {
        expect($routesContent)->toContain("Route::get('/locations'");
        expect($routesContent)->toContain('TownController::class');
    });

    it('has town store route', function () use ($routesContent) {
        expect($routesContent)->toContain("Route::post('/locations/towns'");
    });

    it('routes use TownController import', function () use ($routesContent) {
        expect($routesContent)->toContain('use App\Http\Controllers\Cook\TownController;');
    });

    it('routes have named routes', function () use ($routesContent) {
        expect($routesContent)->toContain('cook.locations.index');
        expect($routesContent)->toContain('cook.locations.towns.store');
    });
});

// ============================================================
// Test group: Translation Strings
// ============================================================
describe('Translation strings', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has English translations for town feature', function () use ($projectRoot) {
        $enContent = file_get_contents($projectRoot.'/lang/en.json');
        $en = json_decode($enContent, true);

        expect($en)->toHaveKey('Delivery Towns');
        expect($en)->toHaveKey('Add Town');
        expect($en)->toHaveKey('Add New Town');
        expect($en)->toHaveKey('Town Name (English)');
        expect($en)->toHaveKey('Town Name (French)');
        expect($en)->toHaveKey('Save Town');
        expect($en)->toHaveKey('Town added successfully.');
        expect($en)->toHaveKey('Town name is required in English.');
        expect($en)->toHaveKey('Town name is required in French.');
        expect($en)->toHaveKey('No delivery towns yet');
    });

    it('has French translations for town feature', function () use ($projectRoot) {
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        expect($fr)->toHaveKey('Delivery Towns');
        expect($fr)->toHaveKey('Add Town');
        expect($fr)->toHaveKey('Add New Town');
        expect($fr)->toHaveKey('Town Name (English)');
        expect($fr)->toHaveKey('Town Name (French)');
        expect($fr)->toHaveKey('Save Town');
        expect($fr)->toHaveKey('Town added successfully.');
        expect($fr)->toHaveKey('Town name is required in English.');
        expect($fr)->toHaveKey('Town name is required in French.');
        expect($fr)->toHaveKey('No delivery towns yet');
    });

    it('French translations are not identical to English keys', function () use ($projectRoot) {
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        expect($fr['Delivery Towns'])->not->toBe('Delivery Towns');
        expect($fr['Add Town'])->not->toBe('Add Town');
        expect($fr['Save Town'])->not->toBe('Save Town');
        expect($fr['Town added successfully.'])->not->toBe('Town added successfully.');
        expect($fr['No delivery towns yet'])->not->toBe('No delivery towns yet');
    });
});

// ============================================================
// Test group: Town Model
// ============================================================
describe('Town model', function () {
    it('uses HasTranslatable trait', function () {
        $reflection = new ReflectionClass(Town::class);
        $traits = $reflection->getTraitNames();
        expect($traits)->toContain('App\Traits\HasTranslatable');
    });

    it('has translatable name field', function () {
        $town = new Town;
        $reflection = new ReflectionProperty(Town::class, 'translatable');
        $reflection->setAccessible(true);
        expect($reflection->getValue($town))->toContain('name');
    });

    it('has name_en and name_fr in fillable', function () {
        $town = new Town;
        expect($town->getFillable())->toContain('name_en');
        expect($town->getFillable())->toContain('name_fr');
    });

    it('has deliveryAreas relationship', function () {
        $reflection = new ReflectionMethod(Town::class, 'deliveryAreas');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has quarters relationship', function () {
        $reflection = new ReflectionMethod(Town::class, 'quarters');
        expect($reflection->isPublic())->toBeTrue();
    });
});
