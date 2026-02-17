<?php

/**
 * F-089: Delete Quarter -- Unit Tests
 *
 * Tests for the delete quarter feature: controller destroy method,
 * service layer deletion with active order blocking,
 * group membership removal, confirmation modal, Gale compliance,
 * activity logging, and translation strings.
 *
 * BR-258: Cannot delete a quarter with active orders
 * BR-259: Deleting a quarter removes it from any quarter group
 * BR-260: Confirmation dialog must show the quarter name
 * BR-261: On success, toast "Quarter deleted successfully"
 * BR-262: Delete action requires location management permission
 * BR-263: Quarter list updates via Gale without page reload
 */

use App\Http\Controllers\Cook\QuarterController;
use App\Services\DeliveryAreaService;

$projectRoot = dirname(__DIR__, 3);

// ============================================================
// Test group: QuarterController destroy method
// ============================================================
describe('QuarterController destroy method', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('exists on QuarterController', function () {
        $reflection = new ReflectionMethod(QuarterController::class, 'destroy');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('accepts Request, deliveryAreaQuarter int, and DeliveryAreaService', function () {
        $reflection = new ReflectionMethod(QuarterController::class, 'destroy');
        $params = $reflection->getParameters();

        expect($params)->toHaveCount(3);
        expect($params[0]->getName())->toBe('request');
        expect($params[1]->getName())->toBe('deliveryAreaQuarter');
        expect($params[2]->getName())->toBe('deliveryAreaService');
    });

    it('returns mixed type', function () {
        $reflection = new ReflectionMethod(QuarterController::class, 'destroy');
        expect($reflection->getReturnType()->getName())->toBe('mixed');
    });

    it('checks can-manage-locations permission (BR-262)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));

        expect($destroyMethod)->toContain("can('can-manage-locations')");
        expect($destroyMethod)->toContain('abort(403)');
    });

    it('uses DeliveryAreaService removeQuarter for business logic', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));

        expect($destroyMethod)->toContain('removeQuarter');
    });

    it('returns gale redirect with success toast on deletion (BR-261, BR-263)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));

        expect($destroyMethod)->toContain('gale()');
        expect($destroyMethod)->toContain('->redirect(');
        expect($destroyMethod)->toContain("__('Quarter deleted successfully.')");
    });

    it('returns error toast when deletion is blocked (BR-258)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));

        expect($destroyMethod)->toContain("'error'");
        expect($destroyMethod)->toContain("\$result['error']");
    });

    it('logs activity on successful deletion', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));

        expect($destroyMethod)->toContain("activity('delivery_areas')");
        expect($destroyMethod)->toContain("'quarter_deleted'");
        expect($destroyMethod)->toContain('causedBy');
    });

    it('never uses bare return view()', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        expect($content)->not->toMatch('/return\s+view\s*\(/');
    });

    it('handles HTTP fallback for non-Gale requests', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/QuarterController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));

        expect($destroyMethod)->toContain('redirect()->route');
        expect($destroyMethod)->toContain('cook.locations.index');
    });
});

// ============================================================
// Test group: DeliveryAreaService removeQuarter method
// ============================================================
describe('DeliveryAreaService removeQuarter', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('exists on DeliveryAreaService', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'removeQuarter');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('returns array with success, error, and quarter_name', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeQuarter'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("'success'");
        expect($removeMethod)->toContain("'error'");
        expect($removeMethod)->toContain("'quarter_name'");
    });

    it('checks for active orders when orders table exists (BR-258)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeQuarter'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("Schema::hasTable('orders')");
        expect($removeMethod)->toContain("whereNotIn('status', ['completed', 'cancelled'])");
    });

    it('returns error when quarter has active orders (BR-258)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeQuarter'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain('Cannot delete');
        expect($removeMethod)->toContain('active order');
    });

    it('handles quarter group membership removal (BR-259)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeQuarter'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("Schema::hasTable('quarter_group_quarter')");
        expect($removeMethod)->toContain('quarter_group_quarter');
    });

    it('eagerly loads quarter and deliveryArea', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeQuarter'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("with(['quarter', 'deliveryArea'])");
    });

    it('scopes deletion to tenant (security)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeQuarter'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("where('tenant_id', \$tenant->id)");
    });

    it('returns error when quarter not found', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeQuarter'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("__('Quarter not found.')");
    });

    it('deletes the delivery area quarter junction record', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeQuarter'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain('$daq->delete()');
    });

    it('uses trans_choice for pluralized active order message (BR-258)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeQuarter'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain('trans_choice');
    });
});

// ============================================================
// Test group: Blade View -- Delete Quarter Enhancements
// ============================================================
describe('Locations blade -- delete quarter enhancements', function () {
    $projectRoot = dirname(__DIR__, 3);
    $viewPath = $projectRoot.'/resources/views/cook/locations/index.blade.php';

    it('has confirmDeleteQuarter method in Alpine x-data', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('confirmDeleteQuarter(');
        expect($content)->toContain('confirmDeleteQuarterName');
    });

    it('has cancelDeleteQuarter method in Alpine x-data', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('cancelDeleteQuarter()');
    });

    it('has executeDeleteQuarter method using $action with DELETE (BR-263)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('executeDeleteQuarter()');
        expect($content)->toContain("method: 'DELETE'");
        expect($content)->toContain('/dashboard/locations/quarters/');
    });

    it('delete button passes quarter name for confirmation (BR-260)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('confirmDeleteQuarter(');
        expect($content)->toContain('confirmDeleteQuarterName');
    });

    it('modal shows warning about clients not being able to order (BR-260)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Clients will no longer be able to order to this quarter.')");
    });

    it('modal has Cancel and Delete buttons', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('cancelDeleteQuarter()');
        expect($content)->toContain('executeDeleteQuarter()');
    });

    it('modal has proper accessibility attributes', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('role="dialog"');
        expect($content)->toContain('aria-modal="true"');
        expect($content)->toContain("__('Delete quarter confirmation')");
    });

    it('uses semantic color tokens for modal (dark mode)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('bg-danger-subtle');
        expect($content)->toContain('text-danger');
        expect($content)->toContain('bg-danger');
        expect($content)->toContain('text-on-danger');
    });

    it('delete button has trash icon and danger hover styling', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('hover:text-danger');
        expect($content)->toContain('hover:bg-danger-subtle');
        expect($content)->toContain("__('Delete quarter')");
    });

    it('references F-089 in file comment header', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('F-089');
    });

    it('has error toast for blocked deletion (BR-258)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("session('error')");
        expect($content)->toContain('bg-danger-subtle');
    });
});

// ============================================================
// Test group: Route Configuration for Delete Quarter
// ============================================================
describe('Route configuration for delete quarter', function () {
    $projectRoot = dirname(__DIR__, 3);
    $routesContent = file_get_contents($projectRoot.'/routes/web.php');

    it('has DELETE route for quarters', function () use ($routesContent) {
        expect($routesContent)->toContain("Route::delete('/locations/quarters/{deliveryAreaQuarter}'");
    });

    it('maps to QuarterController destroy method', function () use ($routesContent) {
        expect($routesContent)->toContain("QuarterController::class, 'destroy'");
    });

    it('has named route cook.locations.quarters.destroy', function () use ($routesContent) {
        expect($routesContent)->toContain('cook.locations.quarters.destroy');
    });
});

// ============================================================
// Test group: Translation Strings for F-089
// ============================================================
describe('Translation strings for delete quarter', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has English translations for delete quarter', function () use ($projectRoot) {
        $enContent = file_get_contents($projectRoot.'/lang/en.json');
        $en = json_decode($enContent, true);

        expect($en)->toHaveKey('Quarter deleted successfully.');
        expect($en)->toHaveKey('Clients will no longer be able to order to this quarter.');
        expect($en)->toHaveKey('Quarter deleted from delivery area');
    });

    it('has French translations for delete quarter', function () use ($projectRoot) {
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        expect($fr)->toHaveKey('Quarter deleted successfully.');
        expect($fr)->toHaveKey('Clients will no longer be able to order to this quarter.');
        expect($fr)->toHaveKey('Quarter deleted from delivery area');
    });

    it('French translations differ from English keys', function () use ($projectRoot) {
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        expect($fr['Quarter deleted successfully.'])->not->toBe('Quarter deleted successfully.');
        expect($fr['Clients will no longer be able to order to this quarter.'])->not->toBe('Clients will no longer be able to order to this quarter.');
        expect($fr['Quarter deleted from delivery area'])->not->toBe('Quarter deleted from delivery area');
    });
});

// ============================================================
// Test group: Edge Cases
// ============================================================
describe('Delete quarter edge cases', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('allows deleting a quarter when it is the only one in its town', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeQuarter'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        // No minimum quarter count check
        expect($removeMethod)->not->toContain('quarter_count > 0');
        expect($removeMethod)->not->toContain('last quarter');
        expect($removeMethod)->toContain('$daq->delete()');
    });

    it('allows deleting completed order quarters (historical data preserved)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeQuarter'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        // Only blocks non-completed, non-cancelled orders
        expect($removeMethod)->toContain("whereNotIn('status', ['completed', 'cancelled'])");
    });

    it('uses forward-compatible Schema::hasTable for orders table', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeQuarter'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("Schema::hasTable('orders')");
    });

    it('uses forward-compatible Schema::hasTable for quarter_group_quarter table', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeQuarter'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("Schema::hasTable('quarter_group_quarter')");
    });
});
