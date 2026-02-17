<?php

/**
 * F-083: Town List View -- Unit Tests
 *
 * Tests for the town list display enhancements: alphabetical ordering,
 * edit/delete action buttons, clickable town expansion, quarter count display,
 * empty state, and translation strings.
 *
 * BR-213: Town list shows all towns for the current tenant
 * BR-214: Each town entry displays: town name (locale), quarter count, edit link, delete link
 * BR-215: Towns are displayed in alphabetical order by name in the current locale
 * BR-216: Clicking a town navigates to or expands its quarter management view
 * BR-217: Empty state shown when no towns exist
 * BR-218: Town list updates via Gale when towns are added or removed
 */

use App\Http\Controllers\Cook\TownController;
use App\Models\DeliveryArea;
use App\Models\Town;
use App\Services\DeliveryAreaService;

$projectRoot = dirname(__DIR__, 3);

// ============================================================
// Test group: DeliveryAreaService alphabetical ordering (BR-215)
// ============================================================
describe('DeliveryAreaService ordering', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('getDeliveryAreasData method exists', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'getDeliveryAreasData');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('orders delivery areas by town name in locale (BR-215)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        // Verify it joins with towns table and orders alphabetically
        expect($content)->toContain("join('towns'");
        expect($content)->toContain("orderBy('towns.'");
    });

    it('uses current locale for ordering column', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $getDataMethod = substr($content, strpos($content, 'public function getDeliveryAreasData'));
        $methodEnd = strpos($getDataMethod, "\n    /**", 10);
        $getDataMethod = substr($getDataMethod, 0, $methodEnd ?: strlen($getDataMethod));

        expect($getDataMethod)->toContain('app()->getLocale()');
        expect($getDataMethod)->toContain("'name_'.\$locale");
    });

    it('returns quarter count per town in data array', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $getDataMethod = substr($content, strpos($content, 'public function getDeliveryAreasData'));
        $methodEnd = strpos($getDataMethod, "\n    /**", 10);
        $getDataMethod = substr($getDataMethod, 0, $methodEnd ?: strlen($getDataMethod));

        expect($getDataMethod)->toContain("'quarters'");
    });

    it('returns both en and fr town names in data', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $getDataMethod = substr($content, strpos($content, 'public function getDeliveryAreasData'));
        $methodEnd = strpos($getDataMethod, "\n    /**", 10);
        $getDataMethod = substr($getDataMethod, 0, $methodEnd ?: strlen($getDataMethod));

        expect($getDataMethod)->toContain("'town_name_en'");
        expect($getDataMethod)->toContain("'town_name_fr'");
    });
});

// ============================================================
// Test group: TownController index method
// ============================================================
describe('TownController for town list', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('index returns gale view response', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->toContain("gale()->view('cook.locations.index'");
        expect($content)->toContain('web: true');
    });

    it('index passes deliveryAreas to view (BR-213)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->toContain("'deliveryAreas'");
    });

    it('index checks can-manage-locations permission', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->toContain("can('can-manage-locations')");
    });

    it('never uses bare return view()', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->not->toMatch('/return\s+view\s*\(/');
    });
});

// ============================================================
// Test group: Blade View -- Town List Enhancements (BR-214)
// ============================================================
describe('Locations blade -- town list view enhancements', function () {
    $projectRoot = dirname(__DIR__, 3);
    $viewPath = $projectRoot.'/resources/views/cook/locations/index.blade.php';

    it('exists', function () use ($viewPath) {
        expect(file_exists($viewPath))->toBeTrue();
    });

    it('extends cook-dashboard layout', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('has breadcrumb with Dashboard > Locations', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Dashboard')");
        expect($content)->toContain("__('Locations')");
        expect($content)->toContain('aria-label');
    });

    it('has edit button on each town (BR-214)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Edit town')");
        expect($content)->toContain("__('Edit')");
    });

    it('has delete button on each town (BR-214)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Delete town')");
        expect($content)->toContain("__('Delete')");
    });

    it('has delete confirmation modal', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Delete this town?')");
        expect($content)->toContain('confirmDeleteId');
        expect($content)->toContain('role="dialog"');
        expect($content)->toContain('aria-modal="true"');
    });

    it('has clickable town with expand/collapse (BR-216)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('toggleTown');
        expect($content)->toContain('expandedTown');
        expect($content)->toContain('aria-expanded');
    });

    it('shows quarter count badge per town', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('quarter')");
        expect($content)->toContain("__('quarters')");
        expect($content)->toContain('bg-info-subtle');
    });

    it('shows quarters list when town is expanded', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Quarters')");
        expect($content)->toContain('quarter_name_en');
        expect($content)->toContain('quarter_name_fr');
        expect($content)->toContain('delivery_fee');
    });

    it('shows message for town with no quarters', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('No quarters added yet.')");
    });

    it('has empty state when no towns exist (BR-217)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('No delivery towns yet')");
        expect($content)->toContain("__('Add Your First Town')");
    });

    it('has town count summary at bottom', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('town')");
        expect($content)->toContain("__('towns')");
    });

    it('uses x-data with Alpine state for expand and delete', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('expandedTown: null');
        expect($content)->toContain('confirmDeleteId: null');
    });

    it('uses x-sync for Gale state sync', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('x-sync');
    });

    it('uses $action for form submission and delete', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('$action(');
    });

    it('uses $navigate for edit link', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('$navigate(');
        expect($content)->toContain('/edit');
    });

    it('uses delete action with method DELETE', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("method: 'DELETE'");
    });

    it('has loading state with $fetching()', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('$fetching()');
    });

    it('uses semantic color tokens for dark mode', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('bg-surface-alt');
        expect($content)->toContain('text-on-surface-strong');
        expect($content)->toContain('bg-primary');
        expect($content)->toContain('border-outline');
        expect($content)->toContain('bg-danger-subtle');
        expect($content)->toContain('bg-danger');
    });

    it('uses x-cloak on expandable sections', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        $count = substr_count($content, 'x-cloak');
        expect($count)->toBeGreaterThanOrEqual(2);
    });

    it('has town name truncation for long names', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('truncate');
    });

    it('has accessible aria labels on action buttons', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('aria-label');
        expect($content)->toContain('title=');
    });

    it('all user-facing strings use __() helper', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Delivery Towns')");
        expect($content)->toContain("__('Add Town')");
        expect($content)->toContain("__('Edit town')");
        expect($content)->toContain("__('Delete town')");
        expect($content)->toContain("__('Delete this town?')");
        expect($content)->toContain("__('Quarters')");
        expect($content)->toContain("__('Cancel')");
    });
});

// ============================================================
// Test group: Translation Strings for F-083
// ============================================================
describe('Translation strings for town list view', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has new English translations for town list', function () use ($projectRoot) {
        $enContent = file_get_contents($projectRoot.'/lang/en.json');
        $en = json_decode($enContent, true);

        expect($en)->toHaveKey('Edit town');
        expect($en)->toHaveKey('Delete town');
        expect($en)->toHaveKey('Delete this town?');
        expect($en)->toHaveKey('Quarters');
        expect($en)->toHaveKey('No quarters added yet.');
        expect($en)->toHaveKey('town');
        expect($en)->toHaveKey('towns');
    });

    it('has new French translations for town list', function () use ($projectRoot) {
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        expect($fr)->toHaveKey('Edit town');
        expect($fr)->toHaveKey('Delete town');
        expect($fr)->toHaveKey('Delete this town?');
        expect($fr)->toHaveKey('Quarters');
        expect($fr)->toHaveKey('No quarters added yet.');
        expect($fr)->toHaveKey('town');
        expect($fr)->toHaveKey('towns');
    });

    it('French translations differ from English keys', function () use ($projectRoot) {
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        expect($fr['Edit town'])->not->toBe('Edit town');
        expect($fr['Delete town'])->not->toBe('Delete town');
        expect($fr['Delete this town?'])->not->toBe('Delete this town?');
        expect($fr['Quarters'])->not->toBe('Quarters');
        expect($fr['No quarters added yet.'])->not->toBe('No quarters added yet.');
        expect($fr['town'])->not->toBe('town');
        expect($fr['towns'])->not->toBe('towns');
    });
});

// ============================================================
// Test group: Town Model for listing support
// ============================================================
describe('Town model for list view', function () {
    it('has name_en and name_fr in fillable for locale display', function () {
        $town = new Town;
        expect($town->getFillable())->toContain('name_en');
        expect($town->getFillable())->toContain('name_fr');
    });

    it('has HasTranslatable trait for locale resolution', function () {
        $reflection = new ReflectionClass(Town::class);
        $traits = $reflection->getTraitNames();
        expect($traits)->toContain('App\Traits\HasTranslatable');
    });

    it('has quarters relationship for count display', function () {
        $reflection = new ReflectionMethod(Town::class, 'quarters');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has deliveryAreas relationship for tenant scoping', function () {
        $reflection = new ReflectionMethod(Town::class, 'deliveryAreas');
        expect($reflection->isPublic())->toBeTrue();
    });
});

// ============================================================
// Test group: DeliveryArea model
// ============================================================
describe('DeliveryArea model for list view', function () {
    it('has town relationship for name display', function () {
        $reflection = new ReflectionMethod(DeliveryArea::class, 'town');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has deliveryAreaQuarters relationship for quarter count', function () {
        $reflection = new ReflectionMethod(DeliveryArea::class, 'deliveryAreaQuarters');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has tenant_id and town_id in fillable', function () {
        $area = new DeliveryArea;
        expect($area->getFillable())->toContain('tenant_id');
        expect($area->getFillable())->toContain('town_id');
    });
});

// ============================================================
// Test group: Route Configuration
// ============================================================
describe('Route configuration for town list', function () {
    $projectRoot = dirname(__DIR__, 3);
    $routesContent = file_get_contents($projectRoot.'/routes/web.php');

    it('has location index route', function () use ($routesContent) {
        expect($routesContent)->toContain("Route::get('/locations'");
        expect($routesContent)->toContain('TownController::class');
    });

    it('has named cook.locations.index route', function () use ($routesContent) {
        expect($routesContent)->toContain('cook.locations.index');
    });
});
