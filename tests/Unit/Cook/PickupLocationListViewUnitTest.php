<?php

/**
 * F-093: Pickup Location List View -- Unit Tests
 *
 * Tests for the pickup location list view functionality including:
 * - DeliveryAreaService::getPickupLocationsData() sorting and data structure
 * - Controller index method
 * - Blade template content and structure
 * - Translation strings
 * - Route configuration
 * - Edge cases (deleted town/quarter, long addresses, many locations)
 *
 * BR-289: List shows all pickup locations for the current tenant
 * BR-290: Each entry displays: location name (current locale), town name, quarter name, address
 * BR-291: Locations sorted alphabetically by name in current locale
 * BR-292: Empty state shown when no pickup locations exist
 * BR-293: List updates via Gale when locations are added, edited, or removed
 * BR-294: Edit and delete actions require location management permission
 */

use App\Http\Controllers\Cook\PickupLocationController;
use App\Models\DeliveryArea;
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
// Test group: DeliveryAreaService::getPickupLocationsData
// ============================================================
describe('DeliveryAreaService::getPickupLocationsData', function () {
    beforeEach(function () {
        $this->seedRolesAndPermissions();
        $this->service = app(DeliveryAreaService::class);
    });

    it('returns empty array when tenant has no pickup locations (BR-289, BR-292)', function () {
        $tenant = Tenant::factory()->create();
        $result = $this->service->getPickupLocationsData($tenant);
        expect($result)->toBeArray()->toBeEmpty();
    });

    it('returns all pickup locations for the tenant (BR-289)', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);

        DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);

        PickupLocation::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
        ]);

        $result = $this->service->getPickupLocationsData($tenant);
        expect($result)->toHaveCount(3);
    });

    it('does not return locations from other tenants (BR-289)', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);

        DeliveryArea::create(['tenant_id' => $tenant1->id, 'town_id' => $town->id]);
        DeliveryArea::create(['tenant_id' => $tenant2->id, 'town_id' => $town->id]);

        PickupLocation::factory()->count(2)->create([
            'tenant_id' => $tenant1->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
        ]);
        PickupLocation::factory()->create([
            'tenant_id' => $tenant2->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
        ]);

        $result = $this->service->getPickupLocationsData($tenant1);
        expect($result)->toHaveCount(2);
    });

    it('returns correct data structure for each location (BR-290)', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
        $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);

        DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);

        PickupLocation::factory()->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
            'name_en' => 'My Kitchen',
            'name_fr' => 'Ma Cuisine',
            'address' => 'Behind Akwa Palace Hotel',
        ]);

        app()->setLocale('en');
        $result = $this->service->getPickupLocationsData($tenant);

        expect($result)->toHaveCount(1);
        expect($result[0])
            ->toHaveKeys(['id', 'name', 'name_en', 'name_fr', 'town_name', 'quarter_name', 'address', 'town_id', 'quarter_id'])
            ->and($result[0]['name'])->toBe('My Kitchen')
            ->and($result[0]['name_en'])->toBe('My Kitchen')
            ->and($result[0]['name_fr'])->toBe('Ma Cuisine')
            ->and($result[0]['town_name'])->toBe('Douala')
            ->and($result[0]['quarter_name'])->toBe('Akwa')
            ->and($result[0]['address'])->toBe('Behind Akwa Palace Hotel');
    });

    it('returns name in French when locale is fr (BR-290)', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
        $quarter = Quarter::factory()->create(['town_id' => $town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);

        DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);

        PickupLocation::factory()->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
            'name_en' => 'My Kitchen',
            'name_fr' => 'Ma Cuisine',
        ]);

        app()->setLocale('fr');
        $result = $this->service->getPickupLocationsData($tenant);

        expect($result[0]['name'])->toBe('Ma Cuisine');
    });

    it('sorts locations alphabetically by name in current locale (BR-291)', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);

        DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);

        PickupLocation::factory()->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
            'name_en' => 'Central Market',
            'name_fr' => 'Marche Central',
        ]);
        PickupLocation::factory()->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
            'name_en' => 'Airport Junction',
            'name_fr' => 'Carrefour Aeroport',
        ]);
        PickupLocation::factory()->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
            'name_en' => 'Beach Point',
            'name_fr' => 'Point Plage',
        ]);

        app()->setLocale('en');
        $result = $this->service->getPickupLocationsData($tenant);

        expect($result[0]['name_en'])->toBe('Airport Junction');
        expect($result[1]['name_en'])->toBe('Beach Point');
        expect($result[2]['name_en'])->toBe('Central Market');
    });

    it('sorts locations alphabetically by French name when locale is fr (BR-291)', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);

        DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);

        PickupLocation::factory()->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
            'name_en' => 'Central Market',
            'name_fr' => 'Marche Central',
        ]);
        PickupLocation::factory()->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
            'name_en' => 'Airport Junction',
            'name_fr' => 'Carrefour Aeroport',
        ]);

        app()->setLocale('fr');
        $result = $this->service->getPickupLocationsData($tenant);

        // French order: Carrefour Aeroport, Marche Central
        expect($result[0]['name_fr'])->toBe('Carrefour Aeroport');
        expect($result[1]['name_fr'])->toBe('Marche Central');
    });

    it('code handles null town relationship gracefully (edge case guard)', function () {
        // FK constraints prevent orphaned references in DB, but code defensively checks
        // for null town/quarter in case of data inconsistency
        $projectRoot = dirname(__DIR__, 3);
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodStart = strpos($content, 'function getPickupLocationsData');
        $methodEnd = strpos($content, 'function addTown');
        $methodContent = substr($content, $methodStart, $methodEnd - $methodStart);

        // Verify null-safe access pattern for town
        expect($methodContent)->toContain('$loc->town');
        expect($methodContent)->toContain("__('Location unavailable')");
    });

    it('code handles null quarter relationship gracefully (edge case guard)', function () {
        // FK constraints prevent orphaned references in DB, but code defensively checks
        $projectRoot = dirname(__DIR__, 3);
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodStart = strpos($content, 'function getPickupLocationsData');
        $methodEnd = strpos($content, 'function addTown');
        $methodContent = substr($content, $methodStart, $methodEnd - $methodStart);

        // Verify null-safe access pattern for quarter
        expect($methodContent)->toContain('$loc->quarter');
        // Both town and quarter fallbacks use "Location unavailable"
        $fallbackCount = substr_count($methodContent, "__('Location unavailable')");
        expect($fallbackCount)->toBe(2);
    });

    it('handles many locations without issues (10+)', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);

        DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);

        PickupLocation::factory()->count(15)->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
        ]);

        $result = $this->service->getPickupLocationsData($tenant);
        expect($result)->toHaveCount(15);
    });

    it('returns locations from multiple towns (Scenario 3)', function () {
        $tenant = Tenant::factory()->create();

        $town1 = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
        $quarter1 = Quarter::factory()->create(['town_id' => $town1->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);
        DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town1->id]);

        $town2 = Town::factory()->create(['name_en' => 'Yaounde', 'name_fr' => 'Yaounde']);
        $quarter2 = Quarter::factory()->create(['town_id' => $town2->id, 'name_en' => 'Bastos', 'name_fr' => 'Bastos']);
        DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town2->id]);

        PickupLocation::factory()->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town1->id,
            'quarter_id' => $quarter1->id,
            'name_en' => 'Douala Kitchen',
        ]);
        PickupLocation::factory()->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town2->id,
            'quarter_id' => $quarter2->id,
            'name_en' => 'Yaounde Market',
        ]);

        $result = $this->service->getPickupLocationsData($tenant);

        expect($result)->toHaveCount(2);
        $townNames = array_column($result, 'town_name');
        expect($townNames)->toContain('Douala');
        expect($townNames)->toContain('Yaounde');
    });

    it('includes town_id and quarter_id in returned data', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);

        DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);

        PickupLocation::factory()->create([
            'tenant_id' => $tenant->id,
            'town_id' => $town->id,
            'quarter_id' => $quarter->id,
        ]);

        $result = $this->service->getPickupLocationsData($tenant);

        expect($result[0]['town_id'])->toBe($town->id);
        expect($result[0]['quarter_id'])->toBe($quarter->id);
    });
});

// ============================================================
// Test group: Controller
// ============================================================
describe('PickupLocationController', function () {
    $controllerProjectRoot = dirname(__DIR__, 3);

    it('has an index method that returns mixed', function () {
        $reflection = new ReflectionMethod(PickupLocationController::class, 'index');
        expect($reflection->isPublic())->toBeTrue();
        expect((string) $reflection->getReturnType())->toBe('mixed');
    });

    it('index method checks can-manage-locations permission (BR-294)', function () use ($controllerProjectRoot) {
        $content = file_get_contents($controllerProjectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $indexSection = substr($content, strpos($content, 'public function index'));
        $storeStart = strpos($indexSection, 'public function store');
        if ($storeStart !== false) {
            $indexSection = substr($indexSection, 0, $storeStart);
        }
        expect($indexSection)->toContain("can('can-manage-locations')");
    });

    it('index method returns gale view response (BR-293)', function () use ($controllerProjectRoot) {
        $content = file_get_contents($controllerProjectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->toContain("gale()->view('cook.locations.pickup'");
        expect($content)->toContain('web: true');
    });

    it('index passes pickupLocations and deliveryAreas to view', function () use ($controllerProjectRoot) {
        $content = file_get_contents($controllerProjectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $indexSection = substr($content, strpos($content, 'public function index'));
        $storeStart = strpos($indexSection, 'public function store');
        if ($storeStart !== false) {
            $indexSection = substr($indexSection, 0, $storeStart);
        }
        expect($indexSection)->toContain("'pickupLocations'");
        expect($indexSection)->toContain("'deliveryAreas'");
    });
});

// ============================================================
// Test group: Blade template
// ============================================================
describe('Pickup Locations Blade Template', function () use ($projectRoot) {
    it('exists at the correct path', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/resources/views/cook/locations/pickup.blade.php'))->toBeTrue();
    });

    it('extends cook-dashboard layout', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('contains breadcrumb navigation', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("__('Dashboard')");
        expect($content)->toContain("__('Locations')");
        expect($content)->toContain("__('Pickup Locations')");
    });

    it('displays location name (BR-290)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("\$location['name']");
    });

    it('displays town name for each location (BR-290)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("\$location['town_name']");
    });

    it('displays quarter name for each location (BR-290)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("\$location['quarter_name']");
    });

    it('displays address for each location (BR-290)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("\$location['address']");
    });

    it('contains empty state message (BR-292)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("__('No pickup locations added yet')");
        expect($content)->toContain("__('Add a pickup location so clients can collect their orders.')");
    });

    it('contains edit button with pencil icon (BR-294)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("__('Edit')");
        expect($content)->toContain('startEdit(');
    });

    it('contains delete button with trash icon (BR-294)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("__('Delete')");
        expect($content)->toContain('confirmDeleteId');
    });

    it('gates edit and delete behind can-manage-locations permission (BR-294)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("@can('can-manage-locations')");
        expect($content)->toContain('@endcan');
    });

    it('has delete confirmation modal (F-095)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("__('Delete Pickup Location')");
        expect($content)->toContain("__('Delete')");
        expect($content)->toContain("__('Clients will no longer be able to pick up from this location.')");
        expect($content)->toContain('cancelDelete()');
        expect($content)->toContain('executeDelete()');
    });

    it('has Alpine x-data with delete state (F-095 stub)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('confirmDeleteId: null');
        expect($content)->toContain("confirmDeleteName: ''");
    });

    it('truncates long addresses with ellipsis', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('mb_strlen');
        expect($content)->toContain('mb_substr');
    });

    it('uses __() for all user-facing strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        // Check that key user-facing labels use translation helper
        expect($content)->toContain("__('Pickup Locations')");
        expect($content)->toContain("__('Add Pickup Location')");
        expect($content)->toContain("__('Back to Locations')");
        expect($content)->toContain("__('Free')");
        expect($content)->toContain("__('Edit')");
        expect($content)->toContain("__('Delete')");
        expect($content)->toContain("__('Cancel')");
    });

    it('uses semantic color tokens not hardcoded colors', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('bg-surface-alt');
        expect($content)->toContain('text-on-surface-strong');
        expect($content)->toContain('bg-primary');
        expect($content)->toContain('text-primary');
        expect($content)->toContain('bg-danger-subtle');
        expect($content)->toContain('text-danger');
    });

    it('includes dark mode variants', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('dark:');
    });

    it('has free badge for each location (BR-285)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("__('Free')");
        expect($content)->toContain('bg-success-subtle');
        expect($content)->toContain('text-success');
    });

    it('has pin icon for each location', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        // map-pin SVG path
        expect($content)->toContain('M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z');
    });

    it('displays location count summary', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('trans_choice');
        expect($content)->toContain(':count pickup location');
    });

    it('contains the F-093 business rule comments', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('BR-289');
        expect($content)->toContain('BR-290');
        expect($content)->toContain('BR-291');
        expect($content)->toContain('BR-292');
        expect($content)->toContain('BR-293');
        expect($content)->toContain('BR-294');
    });
});

// ============================================================
// Test group: Routes
// ============================================================
describe('Routes', function () {
    it('has GET route for pickup locations index', function () {
        $route = collect(app('router')->getRoutes()->getRoutesByMethod()['GET'])
            ->first(fn ($r) => $r->getName() === 'cook.locations.pickup.index');
        expect($route)->not->toBeNull();
        expect($route->uri())->toContain('dashboard/locations/pickup');
    });

    it('has POST route for pickup locations store', function () {
        $route = collect(app('router')->getRoutes()->getRoutesByMethod()['POST'])
            ->first(fn ($r) => $r->getName() === 'cook.locations.pickup.store');
        expect($route)->not->toBeNull();
    });
});

// ============================================================
// Test group: Translation strings
// ============================================================
describe('Translation Strings', function () use ($projectRoot) {
    it('has English translations for F-093 strings', function () use ($projectRoot) {
        $en = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

        expect($en)->toHaveKey('No pickup locations added yet');
        expect($en)->toHaveKey('Add a pickup location so clients can collect their orders.');
        expect($en)->toHaveKey('Delete Pickup Location');
        expect($en)->toHaveKey('Are you sure you want to delete');
        expect($en)->toHaveKey('This action cannot be undone.');
        expect($en)->toHaveKey('Location unavailable');
    });

    it('has French translations for F-093 strings', function () use ($projectRoot) {
        $fr = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

        expect($fr)->toHaveKey('No pickup locations added yet');
        expect($fr)->toHaveKey('Add a pickup location so clients can collect their orders.');
        expect($fr)->toHaveKey('Delete Pickup Location');
        expect($fr)->toHaveKey('Are you sure you want to delete');
        expect($fr)->toHaveKey('This action cannot be undone.');
        expect($fr)->toHaveKey('Location unavailable');

        // Verify French translations are actual French
        expect($fr['No pickup locations added yet'])->not->toBe('No pickup locations added yet');
        expect($fr['Delete Pickup Location'])->not->toBe('Delete Pickup Location');
    });
});

// ============================================================
// Test group: DeliveryAreaService getPickupLocationsData method shape
// ============================================================
describe('DeliveryAreaService method', function () use ($projectRoot) {
    it('getPickupLocationsData sorts by locale-specific column (BR-291)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        // Verify the method uses orderBy with locale-based column name
        $methodStart = strpos($content, 'function getPickupLocationsData');
        $methodEnd = strpos($content, 'function addTown');
        $methodContent = substr($content, $methodStart, $methodEnd - $methodStart);

        expect($methodContent)->toContain('orderBy($nameColumn)');
        expect($methodContent)->toContain("'name_'.\$locale");
    });

    it('getPickupLocationsData handles null town gracefully', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodStart = strpos($content, 'function getPickupLocationsData');
        $methodEnd = strpos($content, 'function addTown');
        $methodContent = substr($content, $methodStart, $methodEnd - $methodStart);

        expect($methodContent)->toContain('$loc->town');
        expect($methodContent)->toContain("__('Location unavailable')");
    });

    it('getPickupLocationsData handles null quarter gracefully', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodStart = strpos($content, 'function getPickupLocationsData');
        $methodEnd = strpos($content, 'function addTown');
        $methodContent = substr($content, $methodStart, $methodEnd - $methodStart);

        expect($methodContent)->toContain('$loc->quarter');
    });

    it('getPickupLocationsData eager loads relationships', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodStart = strpos($content, 'function getPickupLocationsData');
        $methodEnd = strpos($content, 'function addTown');
        $methodContent = substr($content, $methodStart, $methodEnd - $methodStart);

        expect($methodContent)->toContain("with(['town', 'quarter'])");
    });
});
