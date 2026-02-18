<?php

/**
 * F-090: Quarter Group Creation -- Unit Tests
 *
 * Tests for QuarterGroupController, StoreQuarterGroupRequest, DeliveryAreaService::createQuarterGroup(),
 * QuarterGroup model, QuarterGroupFactory, route configuration, and translation strings.
 *
 * BR-264: Group name is required (plain text, not translatable)
 * BR-265: Group name must be unique within this tenant
 * BR-266: Delivery fee is required and must be >= 0 XAF
 * BR-267: Group fee overrides individual quarter fees
 * BR-268: A quarter can belong to at most one group at a time
 * BR-269: Quarters from any town under this tenant can be assigned
 * BR-270: Groups are tenant-scoped
 * BR-271: Creating a group does not require assigning quarters immediately
 * BR-272: Fee changes apply to all member quarters for new orders
 */

use App\Http\Controllers\Cook\QuarterGroupController;
use App\Http\Requests\Cook\StoreQuarterGroupRequest;
use App\Models\Quarter;
use App\Models\QuarterGroup;
use App\Models\Tenant;
use App\Models\Town;
use App\Services\DeliveryAreaService;

$projectRoot = dirname(__DIR__, 3);

// ============================================================
// Test group: QuarterGroup Model
// ============================================================
describe('QuarterGroup Model', function () use ($projectRoot) {
    it('exists at the expected path', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/app/Models/QuarterGroup.php'))->toBeTrue();
    });

    it('has the correct table name', function () {
        $model = new QuarterGroup;
        expect($model->getTable())->toBe('quarter_groups');
    });

    it('has the correct fillable attributes', function () {
        $model = new QuarterGroup;
        expect($model->getFillable())->toBe(['tenant_id', 'name', 'delivery_fee']);
    });

    it('casts delivery_fee to integer', function () {
        $model = new QuarterGroup;
        $casts = $model->getCasts();
        expect($casts)->toHaveKey('delivery_fee');
        expect($casts['delivery_fee'])->toBe('integer');
    });

    it('has tenant relationship', function () {
        $reflection = new ReflectionMethod(QuarterGroup::class, 'tenant');
        expect($reflection->isPublic())->toBeTrue();
        expect($reflection->getReturnType()?->getName())->toBe('Illuminate\Database\Eloquent\Relations\BelongsTo');
    });

    it('has quarters relationship (BelongsToMany)', function () {
        $reflection = new ReflectionMethod(QuarterGroup::class, 'quarters');
        expect($reflection->isPublic())->toBeTrue();
        expect($reflection->getReturnType()?->getName())->toBe('Illuminate\Database\Eloquent\Relations\BelongsToMany');
    });

    it('quarters relationship uses correct pivot table', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Models/QuarterGroup.php');
        expect($content)->toContain("belongsToMany(Quarter::class, 'quarter_group_quarter')");
    });

    it('uses LogsActivityTrait for audit logging', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Models/QuarterGroup.php');
        expect($content)->toContain('LogsActivityTrait');
    });

    it('uses HasFactory trait', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Models/QuarterGroup.php');
        expect($content)->toContain('HasFactory');
    });
});

// ============================================================
// Test group: QuarterGroupFactory
// ============================================================
describe('QuarterGroupFactory', function () use ($projectRoot) {
    it('exists at the expected path', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/database/factories/QuarterGroupFactory.php'))->toBeTrue();
    });

    it('has a definition method', function () {
        $factory = QuarterGroup::factory();
        expect($factory)->toBeInstanceOf(\Database\Factories\QuarterGroupFactory::class);
    });

    it('has a freeDelivery state', function () {
        $factory = QuarterGroup::factory()->freeDelivery();
        expect($factory)->toBeInstanceOf(\Database\Factories\QuarterGroupFactory::class);
    });

    it('has a highFee state', function () {
        $factory = QuarterGroup::factory()->highFee();
        expect($factory)->toBeInstanceOf(\Database\Factories\QuarterGroupFactory::class);
    });
});

// ============================================================
// Test group: Migration
// ============================================================
describe('Migration', function () use ($projectRoot) {
    it('creates quarter_groups table', function () use ($projectRoot) {
        $migrationFiles = glob($projectRoot.'/database/migrations/*create_quarter_groups_table.php');
        expect($migrationFiles)->not->toBeEmpty();

        $content = file_get_contents($migrationFiles[0]);
        expect($content)->toContain("Schema::create('quarter_groups'");
        expect($content)->toContain("Schema::create('quarter_group_quarter'");
    });

    it('quarter_groups table has tenant_id foreign key', function () use ($projectRoot) {
        $migrationFiles = glob($projectRoot.'/database/migrations/*create_quarter_groups_table.php');
        $content = file_get_contents($migrationFiles[0]);
        expect($content)->toContain("foreignId('tenant_id')");
        expect($content)->toContain('cascadeOnDelete');
    });

    it('quarter_groups table has name column with max 100 chars', function () use ($projectRoot) {
        $migrationFiles = glob($projectRoot.'/database/migrations/*create_quarter_groups_table.php');
        $content = file_get_contents($migrationFiles[0]);
        expect($content)->toContain("string('name', 100)");
    });

    it('quarter_groups table has delivery_fee column', function () use ($projectRoot) {
        $migrationFiles = glob($projectRoot.'/database/migrations/*create_quarter_groups_table.php');
        $content = file_get_contents($migrationFiles[0]);
        expect($content)->toContain("integer('delivery_fee')");
    });

    it('quarter_groups table has unique constraint on tenant_id + name (BR-265)', function () use ($projectRoot) {
        $migrationFiles = glob($projectRoot.'/database/migrations/*create_quarter_groups_table.php');
        $content = file_get_contents($migrationFiles[0]);
        expect($content)->toContain("unique(['tenant_id', 'name'])");
    });

    it('pivot table has unique constraint on quarter_id (BR-268)', function () use ($projectRoot) {
        $migrationFiles = glob($projectRoot.'/database/migrations/*create_quarter_groups_table.php');
        $content = file_get_contents($migrationFiles[0]);
        expect($content)->toContain("unique('quarter_id')");
    });

    it('pivot table has foreign keys with cascadeOnDelete', function () use ($projectRoot) {
        $migrationFiles = glob($projectRoot.'/database/migrations/*create_quarter_groups_table.php');
        $content = file_get_contents($migrationFiles[0]);
        expect($content)->toContain("foreignId('quarter_group_id')->constrained('quarter_groups')->cascadeOnDelete()");
        expect($content)->toContain("foreignId('quarter_id')->constrained('quarters')->cascadeOnDelete()");
    });
});

// ============================================================
// Test group: QuarterGroupController
// ============================================================
describe('QuarterGroupController', function () use ($projectRoot) {
    it('exists at the expected path', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/app/Http/Controllers/Cook/QuarterGroupController.php'))->toBeTrue();
    });

    it('has a store method', function () {
        $reflection = new ReflectionMethod(QuarterGroupController::class, 'store');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('store method accepts Request and DeliveryAreaService parameters', function () {
        $reflection = new ReflectionMethod(QuarterGroupController::class, 'store');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('request');
        expect($params[1]->getName())->toBe('deliveryAreaService');
    });

    it('store checks can-manage-locations permission', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterGroupController.php');
        expect($content)->toContain("can('can-manage-locations')");
        expect($content)->toContain('abort(403)');
    });

    it('store uses dual Gale/HTTP validation pattern', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterGroupController.php');
        expect($content)->toContain('isGale()');
        expect($content)->toContain('validateState');
        expect($content)->toContain('StoreQuarterGroupRequest');
    });

    it('store uses gale() responses (never bare view())', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterGroupController.php');
        expect($content)->toContain('gale()->messages');
        expect($content)->toContain('gale()');
        expect($content)->toContain('->redirect(');
        expect($content)->not->toContain('return view(');
    });

    it('store logs activity', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterGroupController.php');
        expect($content)->toContain("activity('delivery_areas')");
        expect($content)->toContain('quarter_group_created');
    });

    it('store uses localized success message', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterGroupController.php');
        expect($content)->toContain("__('Quarter group created successfully.')");
    });

    it('store validates group_name with max:100 (BR-264)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterGroupController.php');
        expect($content)->toContain("'group_name' => ['required', 'string', 'max:100']");
    });

    it('store validates group_delivery_fee as integer >= 0 (BR-266)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterGroupController.php');
        expect($content)->toContain("'group_delivery_fee' => ['required', 'integer', 'min:0']");
    });

    it('store validates group_quarter_ids as nullable array', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterGroupController.php');
        expect($content)->toContain("'group_quarter_ids' => ['nullable', 'array']");
    });
});

// ============================================================
// Test group: StoreQuarterGroupRequest
// ============================================================
describe('StoreQuarterGroupRequest', function () use ($projectRoot) {
    it('exists at the expected path', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/app/Http/Requests/Cook/StoreQuarterGroupRequest.php'))->toBeTrue();
    });

    it('has validation rules', function () {
        $request = new StoreQuarterGroupRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('name');
        expect($rules)->toHaveKey('delivery_fee');
        expect($rules)->toHaveKey('quarter_ids');
    });

    it('name is required with max 100 (BR-264)', function () {
        $request = new StoreQuarterGroupRequest;
        $rules = $request->rules();
        expect($rules['name'])->toContain('required');
        expect($rules['name'])->toContain('max:100');
    });

    it('delivery_fee is required integer >= 0 (BR-266)', function () {
        $request = new StoreQuarterGroupRequest;
        $rules = $request->rules();
        expect($rules['delivery_fee'])->toContain('required');
        expect($rules['delivery_fee'])->toContain('integer');
        expect($rules['delivery_fee'])->toContain('min:0');
    });

    it('quarter_ids is nullable array', function () {
        $request = new StoreQuarterGroupRequest;
        $rules = $request->rules();
        expect($rules['quarter_ids'])->toContain('nullable');
        expect($rules['quarter_ids'])->toContain('array');
    });

    it('has custom error messages', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/StoreQuarterGroupRequest.php');
        expect($content)->toContain("'name.required'");
        expect($content)->toContain("'name.max'");
        expect($content)->toContain("'delivery_fee.required'");
        expect($content)->toContain("'delivery_fee.integer'");
        expect($content)->toContain("'delivery_fee.min'");
    });
});

// ============================================================
// Test group: DeliveryAreaService::createQuarterGroup()
// ============================================================
describe('DeliveryAreaService::createQuarterGroup', function () use ($projectRoot) {
    it('method exists', function () {
        $service = new DeliveryAreaService;
        expect(method_exists($service, 'createQuarterGroup'))->toBeTrue();
    });

    it('method signature accepts correct parameters', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'createQuarterGroup');
        $params = $reflection->getParameters();
        expect($params[0]->getName())->toBe('tenant');
        expect($params[1]->getName())->toBe('name');
        expect($params[2]->getName())->toBe('deliveryFee');
        expect($params[3]->getName())->toBe('quarterIds');
    });

    it('quarterIds parameter defaults to empty array (BR-271)', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'createQuarterGroup');
        $params = $reflection->getParameters();
        expect($params[3]->isDefaultValueAvailable())->toBeTrue();
        expect($params[3]->getDefaultValue())->toBe([]);
    });

    it('checks for duplicate group name (BR-265)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        expect($content)->toContain('LOWER(name) = ?');
        expect($content)->toContain('A group with this name already exists.');
    });

    it('validates quarter IDs belong to tenant (BR-269)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        expect($content)->toContain('Some selected quarters do not belong to your delivery areas.');
    });

    it('removes quarters from old groups before assigning (BR-268)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        // Should delete from quarter_group_quarter before attaching
        expect($content)->toContain("DB::table('quarter_group_quarter')");
        expect($content)->toContain("whereIn('quarter_id', \$quarterIds)");
        expect($content)->toContain('->delete()');
    });

    it('uses DB transaction for atomicity', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        expect($content)->toContain('DB::transaction');
    });

    it('returns success array with group on success', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        expect($content)->toContain("'success' => true");
        expect($content)->toContain("'group' => \$group");
    });
});

// ============================================================
// Test group: DeliveryAreaService::getQuarterGroupsData()
// ============================================================
describe('DeliveryAreaService::getQuarterGroupsData', function () {
    it('method exists', function () {
        $service = new DeliveryAreaService;
        expect(method_exists($service, 'getQuarterGroupsData'))->toBeTrue();
    });

    it('accepts a Tenant parameter', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'getQuarterGroupsData');
        $params = $reflection->getParameters();
        expect($params[0]->getName())->toBe('tenant');
    });
});

// ============================================================
// Test group: DeliveryAreaService::getQuartersForGroupAssignment()
// ============================================================
describe('DeliveryAreaService::getQuartersForGroupAssignment', function () {
    it('method exists', function () {
        $service = new DeliveryAreaService;
        expect(method_exists($service, 'getQuartersForGroupAssignment'))->toBeTrue();
    });

    it('accepts a Tenant parameter', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'getQuartersForGroupAssignment');
        $params = $reflection->getParameters();
        expect($params[0]->getName())->toBe('tenant');
    });
});

// ============================================================
// Test group: Route Configuration
// ============================================================
describe('Route Configuration', function () use ($projectRoot) {
    it('has route for group store', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain("Route::post('/locations/groups'");
        expect($content)->toContain('QuarterGroupController::class');
    });

    it('imports QuarterGroupController', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain('use App\Http\Controllers\Cook\QuarterGroupController');
    });

    it('route is named cook.locations.groups.store', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain("name('cook.locations.groups.store')");
    });
});

// ============================================================
// Test group: TownController index passes group data
// ============================================================
describe('TownController index', function () use ($projectRoot) {
    it('passes quarterGroups data to view', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->toContain('quarterGroups');
        expect($content)->toContain('getQuarterGroupsData');
    });

    it('passes quartersForGroupAssignment data to view', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->toContain('quartersForGroupAssignment');
        expect($content)->toContain('getQuartersForGroupAssignment');
    });
});

// ============================================================
// Test group: Blade View
// ============================================================
describe('Blade View', function () use ($projectRoot) {
    it('has quarter groups section heading', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain("__('Quarter Groups')");
    });

    it('has create group button', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain("__('Create Group')");
    });

    it('has group creation form', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain("__('Create Quarter Group')");
        expect($content)->toContain('group_name');
        expect($content)->toContain('group_delivery_fee');
    });

    it('form submits to group store route', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('/dashboard/locations/groups');
    });

    it('has quarter selection checkboxes (BR-269)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('quartersForGroupAssignment');
        expect($content)->toContain('toggleGroupQuarter');
    });

    it('shows current group badge on quarters in other groups', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('current_group_name');
    });

    it('has group list display', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('quarterGroups');
    });

    it('shows free delivery badge for 0 XAF groups', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        // The free delivery badge is used for both individual quarters and groups
        expect($content)->toContain("__('Free delivery')");
    });

    it('has empty state for no groups', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain("__('No quarter groups yet')");
    });

    it('uses x-sync for group form state', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('group_name');
        expect($content)->toContain('group_delivery_fee');
        expect($content)->toContain('group_quarter_ids');
    });

    it('has loading states with $fetching()', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        // Multiple instances of $fetching() usage
        expect($content)->toContain('$fetching()');
    });

    it('uses __() for all user-facing strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain("__('Group Name')");
        expect($content)->toContain("__('Save Group')");
        expect($content)->toContain("__('Assign Quarters')");
    });

    it('uses semantic color tokens (not hardcoded colors)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('bg-surface-alt');
        expect($content)->toContain('text-on-surface-strong');
        expect($content)->toContain('bg-primary');
    });

    it('has dark mode variants', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/index.blade.php');
        expect($content)->toContain('dark:bg-surface-alt');
        expect($content)->toContain('dark:border-outline');
    });
});

// ============================================================
// Test group: Translation Strings
// ============================================================
describe('Translation Strings', function () use ($projectRoot) {
    it('has English translations for group feature', function () use ($projectRoot) {
        $en = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
        expect($en)->toHaveKey('Quarter Groups');
        expect($en)->toHaveKey('Create Group');
        expect($en)->toHaveKey('Group Name');
        expect($en)->toHaveKey('Save Group');
        expect($en)->toHaveKey('Quarter group created successfully.');
        expect($en)->toHaveKey('A group with this name already exists.');
        expect($en)->toHaveKey('No quarter groups yet');
    });

    it('has French translations for group feature', function () use ($projectRoot) {
        $fr = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);
        expect($fr)->toHaveKey('Quarter Groups');
        expect($fr['Quarter Groups'])->toBe('Groupes de quartiers');
        expect($fr)->toHaveKey('Create Group');
        expect($fr['Create Group'])->toBe('Créer un groupe');
        expect($fr)->toHaveKey('Quarter group created successfully.');
        expect($fr['Quarter group created successfully.'])->toBe('Groupe de quartiers créé avec succès.');
    });
});

// ============================================================
// Test group: DeliveryAreaService no longer uses Schema::hasTable for groups
// ============================================================
describe('DeliveryAreaService uses Eloquent for groups', function () use ($projectRoot) {
    it('getDeliveryAreasData uses QuarterGroup model instead of Schema::hasTable', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        // Should not reference Schema::hasTable('quarter_groups') in getDeliveryAreasData
        expect($content)->toContain('use App\Models\QuarterGroup');
        expect($content)->toContain('QuarterGroup::query()');
    });

    it('getQuarterGroupsForArea uses Eloquent model', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        // The method should use QuarterGroup model
        expect($content)->toContain('QuarterGroup::query()');
    });

    it('imports DB facade', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        expect($content)->toContain('use Illuminate\Support\Facades\DB');
    });
});
