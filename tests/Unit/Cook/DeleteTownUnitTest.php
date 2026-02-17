<?php

/**
 * F-085: Delete Town -- Unit Tests
 *
 * Tests for the delete town feature: controller destroy method,
 * service layer cascade deletion, active order blocking,
 * confirmation modal, error/success toasts, activity logging,
 * Gale compliance, and translation strings.
 *
 * BR-225: Cannot delete a town with active orders
 * BR-226: Deleting a town cascade-deletes all its quarters and delivery fees
 * BR-227: Deleting a town cascade-removes quarters from quarter groups
 * BR-228: Confirmation dialog with town name and quarter count
 * BR-229: On success, toast "Town deleted successfully"
 * BR-230: Delete action requires location management permission
 * BR-231: Town list updates via Gale without page reload
 */

use App\Http\Controllers\Cook\TownController;
use App\Models\Town;
use App\Services\DeliveryAreaService;

$projectRoot = dirname(__DIR__, 3);

// ============================================================
// Test group: TownController destroy method
// ============================================================
describe('TownController destroy method', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('exists on TownController', function () {
        $reflection = new ReflectionMethod(TownController::class, 'destroy');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('accepts Request, deliveryArea int, and DeliveryAreaService', function () {
        $reflection = new ReflectionMethod(TownController::class, 'destroy');
        $params = $reflection->getParameters();

        expect($params)->toHaveCount(3);
        expect($params[0]->getName())->toBe('request');
        expect($params[1]->getName())->toBe('deliveryArea');
        expect($params[2]->getName())->toBe('deliveryAreaService');
    });

    it('returns mixed type', function () {
        $reflection = new ReflectionMethod(TownController::class, 'destroy');
        expect($reflection->getReturnType()->getName())->toBe('mixed');
    });

    it('checks can-manage-locations permission (BR-230)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));

        expect($destroyMethod)->toContain("can('can-manage-locations')");
        expect($destroyMethod)->toContain('abort(403)');
    });

    it('uses DeliveryAreaService removeTown for business logic', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));

        expect($destroyMethod)->toContain('removeTown');
    });

    it('returns gale redirect with success toast on deletion (BR-229, BR-231)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));

        expect($destroyMethod)->toContain('gale()');
        expect($destroyMethod)->toContain('->redirect(');
        expect($destroyMethod)->toContain("__('Town deleted successfully.')");
    });

    it('returns error toast when deletion is blocked (BR-225)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));

        expect($destroyMethod)->toContain("'error'");
        expect($destroyMethod)->toContain("\$result['error']");
    });

    it('logs activity on successful deletion', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));

        expect($destroyMethod)->toContain("activity('delivery_areas')");
        expect($destroyMethod)->toContain("'town_deleted'");
        expect($destroyMethod)->toContain('causedBy');
    });

    it('never uses bare return view()', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TownController.php');
        expect($content)->not->toMatch('/return\s+view\s*\(/');
    });
});

// ============================================================
// Test group: DeliveryAreaService removeTown method
// ============================================================
describe('DeliveryAreaService removeTown', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('exists on DeliveryAreaService', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'removeTown');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('returns array with success, error, town_name, quarter_count', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeTown'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("'success'");
        expect($removeMethod)->toContain("'error'");
        expect($removeMethod)->toContain("'town_name'");
        expect($removeMethod)->toContain("'quarter_count'");
    });

    it('checks for active orders when orders table exists (BR-225)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeTown'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("Schema::hasTable('orders')");
        expect($removeMethod)->toContain("whereNotIn('status', ['completed', 'cancelled'])");
    });

    it('returns error when town has active orders (BR-225)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeTown'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("__('Cannot delete :town because it has active orders.");
    });

    it('handles quarter group membership removal (BR-227)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeTown'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        // F-090 replaced Schema::hasTable with direct DB calls since quarter_groups table now exists
        expect($removeMethod)->toContain("DB::table('quarter_group_quarter')");
        expect($removeMethod)->toContain('quarter_group_quarter');
    });

    it('eagerly loads town and deliveryAreaQuarters', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeTown'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("with(['town', 'deliveryAreaQuarters'])");
    });

    it('scopes deletion to tenant (security)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeTown'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("where('tenant_id', \$tenant->id)");
    });

    it('returns error when delivery area not found', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeTown'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("__('Delivery area not found.')");
    });
});

// ============================================================
// Test group: Blade View -- Delete Town Enhancements
// ============================================================
describe('Locations blade -- delete town enhancements', function () {
    $projectRoot = dirname(__DIR__, 3);
    $viewPath = $projectRoot.'/resources/views/cook/locations/index.blade.php';

    it('has confirmDelete method in Alpine x-data', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('confirmDelete(');
        expect($content)->toContain('confirmDeleteName');
        expect($content)->toContain('confirmDeleteQuarterCount');
    });

    it('has cancelDelete method in Alpine x-data', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('cancelDelete()');
    });

    it('has executeDelete method in Alpine x-data', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('executeDelete()');
    });

    it('delete button passes town name and quarter count (BR-228)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('confirmDelete(');
        expect($content)->toContain('$quarterCount');
    });

    it('modal shows dynamic town name in confirmation message (BR-228)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('confirmDeleteName');
        expect($content)->toContain('confirmDeleteQuarterCount');
    });

    it('modal shows quarter count in confirmation when > 0 (BR-228)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('confirmDeleteQuarterCount > 0');
        expect($content)->toContain("__('and its')");
    });

    it('modal has Cancel and Delete buttons', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('cancelDelete()');
        expect($content)->toContain('executeDelete()');
        expect($content)->toContain("__('Cancel')");
        expect($content)->toContain("__('Delete')");
    });

    it('delete uses $action with method DELETE', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("method: 'DELETE'");
    });

    it('has error toast for blocked deletion (BR-225)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("session('error')");
        expect($content)->toContain('bg-danger-subtle');
        expect($content)->toContain('border-danger/30');
        expect($content)->toContain('text-danger');
    });

    it('has success toast for confirmed deletion (BR-229)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("session('success')");
        expect($content)->toContain('bg-success-subtle');
    });

    it('uses semantic color tokens for modal (dark mode)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('bg-danger-subtle');
        expect($content)->toContain('text-danger');
        expect($content)->toContain('bg-danger');
        expect($content)->toContain('text-on-danger');
    });

    it('modal has proper accessibility attributes', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('role="dialog"');
        expect($content)->toContain('aria-modal="true"');
        expect($content)->toContain("__('Delete town confirmation')");
    });

    it('references F-085 in file comment header', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('F-085');
    });
});

// ============================================================
// Test group: Route Configuration for Delete Town
// ============================================================
describe('Route configuration for delete town', function () {
    $projectRoot = dirname(__DIR__, 3);
    $routesContent = file_get_contents($projectRoot.'/routes/web.php');

    it('has DELETE route for towns', function () use ($routesContent) {
        expect($routesContent)->toContain("Route::delete('/locations/towns/{deliveryArea}'");
    });

    it('maps to TownController destroy method', function () use ($routesContent) {
        expect($routesContent)->toContain("TownController::class, 'destroy'");
    });

    it('has named route cook.locations.towns.destroy', function () use ($routesContent) {
        expect($routesContent)->toContain('cook.locations.towns.destroy');
    });
});

// ============================================================
// Test group: Translation Strings for F-085
// ============================================================
describe('Translation strings for delete town', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has new English translations for delete town', function () use ($projectRoot) {
        $enContent = file_get_contents($projectRoot.'/lang/en.json');
        $en = json_decode($enContent, true);

        expect($en)->toHaveKey('Town deleted successfully.');
        expect($en)->toHaveKey('Cannot delete :town because it has active orders. Complete or cancel the orders first.');
        expect($en)->toHaveKey('and its');
        expect($en)->toHaveKey('Town deleted from delivery areas');
    });

    it('has new French translations for delete town', function () use ($projectRoot) {
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        expect($fr)->toHaveKey('Town deleted successfully.');
        expect($fr)->toHaveKey('Cannot delete :town because it has active orders. Complete or cancel the orders first.');
        expect($fr)->toHaveKey('and its');
        expect($fr)->toHaveKey('Town deleted from delivery areas');
    });

    it('French translations differ from English keys', function () use ($projectRoot) {
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        expect($fr['Town deleted successfully.'])->not->toBe('Town deleted successfully.');
        expect($fr['and its'])->not->toBe('and its');
        expect($fr['Town deleted from delivery areas'])->not->toBe('Town deleted from delivery areas');
    });
});

// ============================================================
// Test group: Edge Cases
// ============================================================
describe('Delete town edge cases', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('allows deleting a town with no quarters (edge case)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeTown'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        // The method does not check for minimum quarter count - straightforward deletion
        expect($removeMethod)->not->toContain('quarter_count > 0');
        expect($removeMethod)->toContain('->delete()');
    });

    it('allows deleting the cook only town (edge case)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeTown'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        // No minimum town count check - cook can have 0 towns after deletion
        expect($removeMethod)->not->toContain('count() > 1');
        expect($removeMethod)->not->toContain('last town');
    });

    it('FK cascade handles delivery_area_quarters deletion (BR-226)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removeTown'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        // The delete call triggers FK cascade for delivery_area_quarters
        expect($removeMethod)->toContain('$deliveryArea->delete()');
    });
});
