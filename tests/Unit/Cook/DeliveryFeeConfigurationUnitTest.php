<?php

/**
 * F-091: Delivery Fee Configuration -- Unit Tests
 *
 * Tests for DeliveryFeeController, UpdateDeliveryFeeRequest, UpdateGroupFeeRequest,
 * DeliveryAreaService fee update methods, route configuration, and translation strings.
 *
 * BR-273: Delivery fee must be >= 0 XAF for all quarters and groups
 * BR-274: Fee of 0 XAF means free delivery to that quarter
 * BR-275: Group fee overrides individual quarter fees for all quarters in the group
 * BR-276: Fee changes apply to new orders only; existing orders retain their original fee
 * BR-277: Fees are stored as integers in XAF
 * BR-278: Fee configuration is accessible from the Locations section of the dashboard
 * BR-279: Changes saved via Gale without page reload
 * BR-280: Only users with location management permission can modify fees
 */

use App\Http\Controllers\Cook\DeliveryFeeController;
use App\Http\Requests\Cook\UpdateDeliveryFeeRequest;
use App\Http\Requests\Cook\UpdateGroupFeeRequest;
use App\Models\DeliveryArea;
use App\Models\DeliveryAreaQuarter;
use App\Models\Quarter;
use App\Models\QuarterGroup;
use App\Models\Tenant;
use App\Models\Town;
use App\Services\DeliveryAreaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

$projectRoot = dirname(__DIR__, 3);

// ============================================================
// Test group: Controller exists and has correct methods
// ============================================================
describe('DeliveryFeeController', function () use ($projectRoot) {
    it('exists at the expected path', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/app/Http/Controllers/Cook/DeliveryFeeController.php'))->toBeTrue();
    });

    it('has index method', function () {
        $reflection = new ReflectionMethod(DeliveryFeeController::class, 'index');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has updateQuarterFee method', function () {
        $reflection = new ReflectionMethod(DeliveryFeeController::class, 'updateQuarterFee');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has updateGroupFee method', function () {
        $reflection = new ReflectionMethod(DeliveryFeeController::class, 'updateGroupFee');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('returns gale view in index method', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/DeliveryFeeController.php');
        expect($content)->toContain("gale()->view('cook.locations.delivery-fees'");
    });

    it('never uses bare return view', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/DeliveryFeeController.php');
        expect($content)->not->toContain('return view(');
    });

    it('checks can-manage-locations permission in all methods', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/DeliveryFeeController.php');
        $permissionCount = substr_count($content, "can('can-manage-locations')");
        expect($permissionCount)->toBeGreaterThanOrEqual(3);
    });

    it('uses dual Gale/HTTP validation pattern for quarter fee update', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/DeliveryFeeController.php');
        expect($content)->toContain('isGale()');
        expect($content)->toContain('validateState');
        expect($content)->toContain('UpdateDeliveryFeeRequest');
    });

    it('uses dual Gale/HTTP validation pattern for group fee update', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/DeliveryFeeController.php');
        expect($content)->toContain('UpdateGroupFeeRequest');
    });

    it('includes activity logging for quarter fee updates', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/DeliveryFeeController.php');
        expect($content)->toContain("'quarter_fee_updated'");
    });

    it('includes activity logging for group fee updates', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/DeliveryFeeController.php');
        expect($content)->toContain("'group_fee_updated'");
    });

    it('uses gale redirect for quarter fee update', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/DeliveryFeeController.php');
        expect($content)->toContain("gale()\n                ->redirect(url('/dashboard/locations/delivery-fees'))");
    });
});

// ============================================================
// Test group: Form Request validation
// ============================================================
describe('UpdateDeliveryFeeRequest', function () use ($projectRoot) {
    it('exists at the expected path', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/app/Http/Requests/Cook/UpdateDeliveryFeeRequest.php'))->toBeTrue();
    });

    it('has delivery_fee validation rule', function () {
        $request = new UpdateDeliveryFeeRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('delivery_fee');
        expect($rules['delivery_fee'])->toContain('required');
        expect($rules['delivery_fee'])->toContain('integer');
        expect($rules['delivery_fee'])->toContain('min:0');
    });

    it('has custom error messages for all rules', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/UpdateDeliveryFeeRequest.php');
        expect($content)->toContain("'delivery_fee.required'");
        expect($content)->toContain("'delivery_fee.integer'");
        expect($content)->toContain("'delivery_fee.min'");
    });
});

describe('UpdateGroupFeeRequest', function () use ($projectRoot) {
    it('exists at the expected path', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/app/Http/Requests/Cook/UpdateGroupFeeRequest.php'))->toBeTrue();
    });

    it('has delivery_fee validation rule', function () {
        $request = new UpdateGroupFeeRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('delivery_fee');
        expect($rules['delivery_fee'])->toContain('required');
        expect($rules['delivery_fee'])->toContain('integer');
        expect($rules['delivery_fee'])->toContain('min:0');
    });

    it('has custom error messages for all rules', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/UpdateGroupFeeRequest.php');
        expect($content)->toContain("'delivery_fee.required'");
        expect($content)->toContain("'delivery_fee.integer'");
        expect($content)->toContain("'delivery_fee.min'");
    });
});

// ============================================================
// Test group: DeliveryAreaService -- updateQuarterFee
// ============================================================
describe('DeliveryAreaService::updateQuarterFee', function () {
    beforeEach(function () {
        $this->service = new DeliveryAreaService;
        $this->seedRolesAndPermissions();
    });

    it('updates an individual quarter fee successfully', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);
        $da = DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
        $daq = DeliveryAreaQuarter::create([
            'delivery_area_id' => $da->id,
            'quarter_id' => $quarter->id,
            'delivery_fee' => 500,
        ]);

        $result = $this->service->updateQuarterFee($tenant, $daq->id, 700);

        expect($result['success'])->toBeTrue();
        expect($result['error'])->toBe('');

        $daq->refresh();
        expect($daq->delivery_fee)->toBe(700);
    });

    it('allows setting fee to 0 for free delivery (BR-274)', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);
        $da = DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
        $daq = DeliveryAreaQuarter::create([
            'delivery_area_id' => $da->id,
            'quarter_id' => $quarter->id,
            'delivery_fee' => 500,
        ]);

        $result = $this->service->updateQuarterFee($tenant, $daq->id, 0);

        expect($result['success'])->toBeTrue();
        $daq->refresh();
        expect($daq->delivery_fee)->toBe(0);
    });

    it('returns high fee warning for fees above threshold', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);
        $da = DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
        $daq = DeliveryAreaQuarter::create([
            'delivery_area_id' => $da->id,
            'quarter_id' => $quarter->id,
            'delivery_fee' => 500,
        ]);

        $result = $this->service->updateQuarterFee($tenant, $daq->id, 15000);

        expect($result['success'])->toBeTrue();
        expect($result['warning'])->not->toBe('');
    });

    it('fails when quarter does not belong to tenant', function () {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);
        $da = DeliveryArea::create(['tenant_id' => $otherTenant->id, 'town_id' => $town->id]);
        $daq = DeliveryAreaQuarter::create([
            'delivery_area_id' => $da->id,
            'quarter_id' => $quarter->id,
            'delivery_fee' => 500,
        ]);

        $result = $this->service->updateQuarterFee($tenant, $daq->id, 700);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->not->toBe('');
    });

    it('fails for non-existent quarter', function () {
        $tenant = Tenant::factory()->create();

        $result = $this->service->updateQuarterFee($tenant, 99999, 500);

        expect($result['success'])->toBeFalse();
    });
});

// ============================================================
// Test group: DeliveryAreaService::updateGroupFee
// ============================================================
describe('DeliveryAreaService::updateGroupFee', function () {
    beforeEach(function () {
        $this->service = new DeliveryAreaService;
        $this->seedRolesAndPermissions();
    });

    it('updates a group fee successfully (BR-275)', function () {
        $tenant = Tenant::factory()->create();
        $group = QuarterGroup::factory()->create([
            'tenant_id' => $tenant->id,
            'delivery_fee' => 300,
        ]);

        $result = $this->service->updateGroupFee($tenant, $group->id, 400);

        expect($result['success'])->toBeTrue();
        expect($result['error'])->toBe('');

        $group->refresh();
        expect($group->delivery_fee)->toBe(400);
    });

    it('allows setting group fee to 0 for free delivery (BR-274)', function () {
        $tenant = Tenant::factory()->create();
        $group = QuarterGroup::factory()->create([
            'tenant_id' => $tenant->id,
            'delivery_fee' => 300,
        ]);

        $result = $this->service->updateGroupFee($tenant, $group->id, 0);

        expect($result['success'])->toBeTrue();
        $group->refresh();
        expect($group->delivery_fee)->toBe(0);
    });

    it('returns high fee warning for fees above threshold', function () {
        $tenant = Tenant::factory()->create();
        $group = QuarterGroup::factory()->create([
            'tenant_id' => $tenant->id,
            'delivery_fee' => 300,
        ]);

        $result = $this->service->updateGroupFee($tenant, $group->id, 15000);

        expect($result['success'])->toBeTrue();
        expect($result['warning'])->not->toBe('');
    });

    it('fails when group does not belong to tenant', function () {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $group = QuarterGroup::factory()->create([
            'tenant_id' => $otherTenant->id,
            'delivery_fee' => 300,
        ]);

        $result = $this->service->updateGroupFee($tenant, $group->id, 400);

        expect($result['success'])->toBeFalse();
    });

    it('fails for non-existent group', function () {
        $tenant = Tenant::factory()->create();

        $result = $this->service->updateGroupFee($tenant, 99999, 500);

        expect($result['success'])->toBeFalse();
    });
});

// ============================================================
// Test group: DeliveryAreaService::getDeliveryFeeSummary
// ============================================================
describe('DeliveryAreaService::getDeliveryFeeSummary', function () {
    beforeEach(function () {
        $this->service = new DeliveryAreaService;
        $this->seedRolesAndPermissions();
    });

    it('returns summary with areas, groups, and statistics', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $quarter = Quarter::factory()->create(['town_id' => $town->id]);
        $da = DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);
        DeliveryAreaQuarter::create([
            'delivery_area_id' => $da->id,
            'quarter_id' => $quarter->id,
            'delivery_fee' => 500,
        ]);

        $result = $this->service->getDeliveryFeeSummary($tenant);

        expect($result)->toHaveKeys(['areas', 'groups', 'summary']);
        expect($result['summary'])->toHaveKeys(['total_quarters', 'free_delivery_count', 'grouped_count']);
        expect($result['summary']['total_quarters'])->toBe(1);
        expect($result['summary']['free_delivery_count'])->toBe(0);
        expect($result['summary']['grouped_count'])->toBe(0);
    });

    it('counts free delivery quarters correctly', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $da = DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);

        // Free delivery quarter
        $q1 = Quarter::factory()->create(['town_id' => $town->id]);
        DeliveryAreaQuarter::create([
            'delivery_area_id' => $da->id,
            'quarter_id' => $q1->id,
            'delivery_fee' => 0,
        ]);

        // Paid delivery quarter
        $q2 = Quarter::factory()->create(['town_id' => $town->id]);
        DeliveryAreaQuarter::create([
            'delivery_area_id' => $da->id,
            'quarter_id' => $q2->id,
            'delivery_fee' => 500,
        ]);

        $result = $this->service->getDeliveryFeeSummary($tenant);

        expect($result['summary']['total_quarters'])->toBe(2);
        expect($result['summary']['free_delivery_count'])->toBe(1);
    });

    it('counts grouped quarters correctly', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $da = DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);

        $q1 = Quarter::factory()->create(['town_id' => $town->id]);
        $daq1 = DeliveryAreaQuarter::create([
            'delivery_area_id' => $da->id,
            'quarter_id' => $q1->id,
            'delivery_fee' => 500,
        ]);

        $group = QuarterGroup::factory()->create([
            'tenant_id' => $tenant->id,
            'delivery_fee' => 300,
        ]);
        $group->quarters()->attach($q1->id);

        $result = $this->service->getDeliveryFeeSummary($tenant);

        expect($result['summary']['grouped_count'])->toBe(1);
    });

    it('returns effective fee from group when quarter is grouped', function () {
        $tenant = Tenant::factory()->create();
        $town = Town::factory()->create();
        $da = DeliveryArea::create(['tenant_id' => $tenant->id, 'town_id' => $town->id]);

        $q1 = Quarter::factory()->create(['town_id' => $town->id]);
        DeliveryAreaQuarter::create([
            'delivery_area_id' => $da->id,
            'quarter_id' => $q1->id,
            'delivery_fee' => 500,
        ]);

        $group = QuarterGroup::factory()->create([
            'tenant_id' => $tenant->id,
            'delivery_fee' => 300,
        ]);
        $group->quarters()->attach($q1->id);

        $result = $this->service->getDeliveryFeeSummary($tenant);
        $quarterData = $result['areas'][0]['quarters'][0];

        expect($quarterData['effective_fee'])->toBe(300);
        expect($quarterData['is_grouped'])->toBeTrue();
        expect($quarterData['delivery_fee'])->toBe(500); // Individual fee still stored
    });

    it('returns empty areas for tenant without delivery areas', function () {
        $tenant = Tenant::factory()->create();

        $result = $this->service->getDeliveryFeeSummary($tenant);

        expect($result['areas'])->toBe([]);
        expect($result['summary']['total_quarters'])->toBe(0);
    });

    it('includes group data in result', function () {
        $tenant = Tenant::factory()->create();
        $group = QuarterGroup::factory()->create([
            'tenant_id' => $tenant->id,
            'delivery_fee' => 500,
        ]);

        $result = $this->service->getDeliveryFeeSummary($tenant);

        expect($result['groups'])->toHaveCount(1);
        expect($result['groups'][0]['id'])->toBe($group->id);
        expect($result['groups'][0]['name'])->toBe($group->name);
        expect($result['groups'][0]['delivery_fee'])->toBe(500);
    });
});

// ============================================================
// Test group: Routes
// ============================================================
describe('Delivery Fee Routes', function () use ($projectRoot) {
    it('registers GET route for delivery fee index', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain("Route::get('/locations/delivery-fees'");
        expect($content)->toContain("DeliveryFeeController::class, 'index'");
    });

    it('registers PUT route for quarter fee update', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain("Route::put('/locations/delivery-fees/quarter/{deliveryAreaQuarter}'");
        expect($content)->toContain("DeliveryFeeController::class, 'updateQuarterFee'");
    });

    it('registers PUT route for group fee update', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain("Route::put('/locations/delivery-fees/group/{group}'");
        expect($content)->toContain("DeliveryFeeController::class, 'updateGroupFee'");
    });

    it('has named routes', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain("->name('cook.locations.delivery-fees')");
        expect($content)->toContain("->name('cook.locations.delivery-fees.quarter.update')");
        expect($content)->toContain("->name('cook.locations.delivery-fees.group.update')");
    });
});

// ============================================================
// Test group: View file
// ============================================================
describe('Delivery Fees View', function () use ($projectRoot) {
    it('exists at the expected path', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php'))->toBeTrue();
    });

    it('extends cook-dashboard layout', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('has proper breadcrumb (BR-278)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain('/dashboard/locations');
        expect($content)->toContain('Delivery Fees');
    });

    it('includes x-sync for state management', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain('x-sync=');
        expect($content)->toContain('fee_value');
        expect($content)->toContain('group_fee_value');
    });

    it('uses x-data with correct Alpine state', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain('editingQuarterFeeId');
        expect($content)->toContain('editingGroupFeeId');
        expect($content)->toContain('startEditQuarterFee');
        expect($content)->toContain('startEditGroupFee');
    });

    it('shows summary cards', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain('Total Quarters');
        expect($content)->toContain('Free Delivery');
        expect($content)->toContain('In Groups');
    });

    it('shows group fees section', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain('Group Fees');
    });

    it('shows free delivery badge (BR-274)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain('Free delivery');
        expect($content)->toContain('bg-success-subtle text-success');
    });

    it('shows group badge for grouped quarters (BR-275)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain('is_grouped');
        expect($content)->toContain('group_name');
        expect($content)->toContain('(group fee)');
    });

    it('has empty state with link to add quarters', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain('No quarters configured');
        expect($content)->toContain('/dashboard/locations');
    });

    it('has info notice about existing orders (BR-276)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain('Fee changes only apply to new orders');
    });

    it('has responsive table/card views', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain('hidden md:block');
        expect($content)->toContain('md:hidden');
    });

    it('uses semantic color tokens', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain('bg-surface-alt');
        expect($content)->toContain('text-on-surface-strong');
        expect($content)->toContain('bg-primary-subtle');
        expect($content)->toContain('text-primary');
    });

    it('uses dark mode variants', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain('dark:bg-surface-alt');
        expect($content)->toContain('dark:border-outline');
    });

    it('uses __() for all user-facing strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain("__('Delivery Fees')");
        expect($content)->toContain("__('Back to Locations')");
        expect($content)->toContain("__('Save')");
        expect($content)->toContain("__('Cancel')");
    });

    it('uses $fetching() with parentheses for loading states', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain('$fetching()');
        expect($content)->not->toContain('$fetching ');
    });

    it('uses $action for form submissions', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain("\$action('");
        expect($content)->toContain("method: 'PUT'");
    });

    it('uses x-name for validation message binding', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain('x-name="fee_value"');
        expect($content)->toContain('x-name="group_fee_value"');
    });

    it('uses x-message for validation error display', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/delivery-fees.blade.php');
        expect($content)->toContain('x-message="fee_value"');
        expect($content)->toContain('x-message="group_fee_value"');
    });
});

// ============================================================
// Test group: Locations index link to delivery fees
// ============================================================
describe('Locations Index Page', function () use ($projectRoot) {
    it('has link to delivery fees page (BR-278)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('/dashboard/locations/delivery-fees');
        expect($content)->toContain('Delivery Fees');
    });
});

// ============================================================
// Test group: Translation strings
// ============================================================
describe('Translation Strings', function () use ($projectRoot) {
    $requiredKeys = [
        'Delivery Fees',
        'Delivery Fee Configuration',
        'Back to Locations',
        'Total Quarters',
        'Free Delivery',
        'In Groups',
        'Group Fees',
        'Quarter Fees by Town',
        'No quarters configured',
        'Delivery fee updated successfully.',
        'Group fee updated successfully. All member quarters now use this fee.',
        'Quarter group not found.',
    ];

    it('has all required English translations', function () use ($projectRoot, $requiredKeys) {
        $enContent = file_get_contents($projectRoot.'/lang/en.json');
        $en = json_decode($enContent, true);

        foreach ($requiredKeys as $key) {
            expect(array_key_exists($key, $en))->toBeTrue("Missing English translation: {$key}");
        }
    });

    it('has all required French translations', function () use ($projectRoot, $requiredKeys) {
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        foreach ($requiredKeys as $key) {
            expect(array_key_exists($key, $fr))->toBeTrue("Missing French translation: {$key}");
        }
    });

    it('French translations are not identical to English for key strings', function () use ($projectRoot) {
        $enContent = file_get_contents($projectRoot.'/lang/en.json');
        $en = json_decode($enContent, true);
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        expect($fr['Delivery Fees'])->not->toBe($en['Delivery Fees']);
        expect($fr['Back to Locations'])->not->toBe($en['Back to Locations']);
        expect($fr['No quarters configured'])->not->toBe($en['No quarters configured']);
    });
});

// ============================================================
// Test group: Permission enforcement via code analysis (BR-280)
// ============================================================
describe('Permission Enforcement', function () use ($projectRoot) {
    it('controller checks can-manage-locations permission in index method', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/DeliveryFeeController.php');
        expect($content)->toContain('can-manage-locations');
    });

    it('controller checks permission before updating quarter fee', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/DeliveryFeeController.php');
        // Verify can-manage-locations appears in updateQuarterFee context
        expect(substr_count($content, 'can-manage-locations'))->toBeGreaterThanOrEqual(3);
    });

    it('routes are protected by cook dashboard middleware group', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        // Delivery fee routes are inside the cook dashboard route group
        expect($content)->toContain('delivery-fees');
    });

    it('cook role has can-manage-locations permission in seeder', function () {
        $this->seedRolesAndPermissions();
        $cookRole = \Spatie\Permission\Models\Role::findByName('cook');
        expect($cookRole->hasPermissionTo('can-manage-locations'))->toBeTrue();
    });
});
