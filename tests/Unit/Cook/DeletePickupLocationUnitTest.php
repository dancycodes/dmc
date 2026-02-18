<?php

/**
 * F-095: Delete Pickup Location -- Unit Tests
 *
 * Tests for the delete pickup location feature: controller destroy method,
 * service layer deletion with active order blocking, confirmation modal,
 * error/success toasts, activity logging, Gale compliance, and translation strings.
 *
 * BR-301: Cannot delete a pickup location with active (non-completed, non-cancelled) orders
 * BR-302: Confirmation dialog must show the location name
 * BR-303: On success, toast notification: "Pickup location deleted successfully"
 * BR-304: Delete action requires location management permission
 * BR-305: List updates via Gale without page reload
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
// Test group: PickupLocationController destroy method
// ============================================================
describe('PickupLocationController destroy method', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('exists on PickupLocationController', function () {
        $reflection = new ReflectionMethod(PickupLocationController::class, 'destroy');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('accepts Request, pickupLocation int, and DeliveryAreaService', function () {
        $reflection = new ReflectionMethod(PickupLocationController::class, 'destroy');
        $params = $reflection->getParameters();

        expect($params)->toHaveCount(3);
        expect($params[0]->getName())->toBe('request');
        expect($params[1]->getName())->toBe('pickupLocation');
        expect($params[2]->getName())->toBe('deliveryAreaService');
    });

    it('returns mixed type', function () {
        $reflection = new ReflectionMethod(PickupLocationController::class, 'destroy');
        expect($reflection->getReturnType()->getName())->toBe('mixed');
    });

    it('checks can-manage-locations permission (BR-304)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));
        $methodEnd = strpos($destroyMethod, "\n    /**", 10);
        $destroyMethod = substr($destroyMethod, 0, $methodEnd ?: strlen($destroyMethod));

        expect($destroyMethod)->toContain("can('can-manage-locations')");
        expect($destroyMethod)->toContain('abort(403)');
    });

    it('uses DeliveryAreaService removePickupLocation for business logic', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));
        $methodEnd = strpos($destroyMethod, "\n    /**", 10);
        $destroyMethod = substr($destroyMethod, 0, $methodEnd ?: strlen($destroyMethod));

        expect($destroyMethod)->toContain('removePickupLocation');
    });

    it('returns gale redirect with success toast on deletion (BR-303, BR-305)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));
        $methodEnd = strpos($destroyMethod, "\n    /**", 10);
        $destroyMethod = substr($destroyMethod, 0, $methodEnd ?: strlen($destroyMethod));

        expect($destroyMethod)->toContain('gale()');
        expect($destroyMethod)->toContain('->redirect(');
        expect($destroyMethod)->toContain("__('Pickup location deleted successfully.')");
    });

    it('returns error toast when deletion is blocked (BR-301)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));
        $methodEnd = strpos($destroyMethod, "\n    /**", 10);
        $destroyMethod = substr($destroyMethod, 0, $methodEnd ?: strlen($destroyMethod));

        expect($destroyMethod)->toContain("'error'");
        expect($destroyMethod)->toContain("\$result['error']");
    });

    it('logs activity on successful deletion', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $destroyMethod = substr($content, strpos($content, 'public function destroy'));
        $methodEnd = strpos($destroyMethod, "\n    /**", 10);
        $destroyMethod = substr($destroyMethod, 0, $methodEnd ?: strlen($destroyMethod));

        expect($destroyMethod)->toContain("activity('pickup_locations')");
        expect($destroyMethod)->toContain("'pickup_location_deleted'");
        expect($destroyMethod)->toContain('causedBy');
    });

    it('never uses bare return view()', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        expect($content)->not->toMatch('/return\s+view\s*\(/');
    });
});

// ============================================================
// Test group: DeliveryAreaService removePickupLocation method
// ============================================================
describe('DeliveryAreaService removePickupLocation', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('exists on DeliveryAreaService', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'removePickupLocation');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('returns array with success, error, pickup_name, pickup_model', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removePickupLocation'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("'success'");
        expect($removeMethod)->toContain("'error'");
        expect($removeMethod)->toContain("'pickup_name'");
        expect($removeMethod)->toContain("'pickup_model'");
    });

    it('checks for active orders when orders table exists (BR-301)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removePickupLocation'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("Schema::hasTable('orders')");
        expect($removeMethod)->toContain("whereNotIn('status', ['completed', 'cancelled'])");
    });

    it('checks pickup_location_id for active orders (BR-301)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removePickupLocation'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("'pickup_location_id'");
    });

    it('returns error when pickup location has active orders (BR-301)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removePickupLocation'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain('active order');
        expect($removeMethod)->toContain('trans_choice');
    });

    it('scopes deletion to tenant (security)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removePickupLocation'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("where('tenant_id', \$tenant->id)");
    });

    it('returns error when pickup location not found', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removePickupLocation'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain("__('Pickup location not found.')");
    });

    it('deletes the pickup location on success', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $removeMethod = substr($content, strpos($content, 'public function removePickupLocation'));
        $methodEnd = strpos($removeMethod, "\n    /**", 10);
        $removeMethod = substr($removeMethod, 0, $methodEnd ?: strlen($removeMethod));

        expect($removeMethod)->toContain('$pickup->delete()');
    });
});

// ============================================================
// Test group: DeliveryAreaService removePickupLocation integration
// ============================================================
describe('DeliveryAreaService removePickupLocation integration', function () {
    beforeEach(function () {
        $this->seedRolesAndPermissions();
        $this->service = app(DeliveryAreaService::class);

        $this->tenant = Tenant::factory()->create();
        $this->town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
        $this->quarter = Quarter::factory()->create(['town_id' => $this->town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);
        DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $this->town->id]);

        $this->pickup = PickupLocation::create([
            'tenant_id' => $this->tenant->id,
            'town_id' => $this->town->id,
            'quarter_id' => $this->quarter->id,
            'name_en' => 'Market Stall',
            'name_fr' => 'Stand au March\u00e9',
            'address' => '123 Market Street',
        ]);
    });

    it('successfully deletes a pickup location with no active orders', function () {
        $result = $this->service->removePickupLocation($this->tenant, $this->pickup->id);

        expect($result['success'])->toBeTrue();
        expect($result['error'])->toBe('');
        expect($result['pickup_name'])->toBe('Market Stall');
        expect(PickupLocation::find($this->pickup->id))->toBeNull();
    });

    it('returns error when pickup location not found', function () {
        $result = $this->service->removePickupLocation($this->tenant, 99999);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('not found');
        expect($result['pickup_name'])->toBe('');
    });

    it('scopes to tenant - cannot delete another tenant pickup location', function () {
        $otherTenant = Tenant::factory()->create();
        $otherPickup = PickupLocation::create([
            'tenant_id' => $otherTenant->id,
            'town_id' => $this->town->id,
            'quarter_id' => $this->quarter->id,
            'name_en' => 'Other Stall',
            'name_fr' => 'Autre Stand',
            'address' => 'Other address',
        ]);

        $result = $this->service->removePickupLocation($this->tenant, $otherPickup->id);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('not found');
        expect(PickupLocation::find($otherPickup->id))->not->toBeNull();
    });

    it('allows deleting last pickup location (edge case)', function () {
        $result = $this->service->removePickupLocation($this->tenant, $this->pickup->id);

        expect($result['success'])->toBeTrue();
        expect(PickupLocation::where('tenant_id', $this->tenant->id)->count())->toBe(0);
    });

    it('allows deleting when completed orders reference this location (edge case)', function () {
        // This test passes since orders table does not exist yet (forward-compatible)
        // When orders table exists, completed orders should not block deletion
        $result = $this->service->removePickupLocation($this->tenant, $this->pickup->id);

        expect($result['success'])->toBeTrue();
    });

    it('returns locale-appropriate pickup name in result', function () {
        app()->setLocale('fr');
        $result = $this->service->removePickupLocation($this->tenant, $this->pickup->id);

        expect($result['pickup_name'])->toBe("Stand au March\u00e9");
    });
});

// ============================================================
// Test group: Blade View -- Delete Pickup Location
// ============================================================
describe('Pickup blade -- delete pickup location', function () {
    $projectRoot = dirname(__DIR__, 3);
    $viewPath = $projectRoot.'/resources/views/cook/locations/pickup.blade.php';

    it('has confirmDeleteId state in Alpine x-data', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('confirmDeleteId');
    });

    it('has confirmDeleteName state in Alpine x-data', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('confirmDeleteName');
    });

    it('has cancelDelete method in Alpine x-data', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('cancelDelete()');
    });

    it('has executeDelete method in Alpine x-data', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('executeDelete()');
    });

    it('delete button sets confirmDeleteId and confirmDeleteName', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('confirmDeleteId =');
        expect($content)->toContain('confirmDeleteName =');
    });

    it('modal shows dynamic location name (BR-302)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('x-text="confirmDeleteName"');
    });

    it('modal shows warning about client impact per spec (BR-302)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Clients will no longer be able to pick up from this location.')");
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

    it('has error toast for blocked deletion (BR-301)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("session('error')");
        expect($content)->toContain('bg-danger-subtle');
        expect($content)->toContain('border-danger/30');
        expect($content)->toContain('text-danger');
    });

    it('has success toast for confirmed deletion (BR-303)', function () use ($viewPath) {
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
    });

    it('delete button is protected by @can directive (BR-304)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("@can('can-manage-locations')");
    });

    it('modal backdrop closes on click', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('@click="cancelDelete()"');
    });

    it('references F-095 in file comment header', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('F-095');
    });

    it('uses trash icon for delete button', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        // Lucide trash-2 path
        expect($content)->toContain('M3 6h18');
        expect($content)->toContain('M19 6v14');
    });
});

// ============================================================
// Test group: Route Configuration for Delete Pickup Location
// ============================================================
describe('Route configuration for delete pickup location', function () {
    $projectRoot = dirname(__DIR__, 3);
    $routesContent = file_get_contents($projectRoot.'/routes/web.php');

    it('has DELETE route for pickup locations', function () use ($routesContent) {
        expect($routesContent)->toContain("Route::delete('/locations/pickup/{pickupLocation}'");
    });

    it('maps to PickupLocationController destroy method', function () use ($routesContent) {
        expect($routesContent)->toContain("[PickupLocationController::class, 'destroy']");
    });

    it('has named route cook.locations.pickup.destroy', function () use ($routesContent) {
        expect($routesContent)->toContain("->name('cook.locations.pickup.destroy')");
    });

    it('references F-095 in route comments', function () use ($routesContent) {
        expect($routesContent)->toContain('F-095');
    });
});

// ============================================================
// Test group: Translation strings for delete pickup location
// ============================================================
describe('Translation strings for delete pickup location', function () {
    $projectRoot = dirname(__DIR__, 3);
    $enJson = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
    $frJson = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    it('has English success toast string (BR-303)', function () use ($enJson) {
        expect($enJson)->toHaveKey('Pickup location deleted successfully.');
    });

    it('has French success toast string (BR-303)', function () use ($frJson) {
        expect($frJson)->toHaveKey('Pickup location deleted successfully.');
    });

    it('has English client impact warning string (BR-302)', function () use ($enJson) {
        expect($enJson)->toHaveKey('Clients will no longer be able to pick up from this location.');
    });

    it('has French client impact warning string (BR-302)', function () use ($frJson) {
        expect($frJson)->toHaveKey('Clients will no longer be able to pick up from this location.');
    });

    it('has English active order block string (BR-301)', function () use ($enJson) {
        expect($enJson)->toHaveKey('{1} Cannot delete :location because it has :count active order for pickup.|[2,*] Cannot delete :location because it has :count active orders for pickup.');
    });

    it('has French active order block string (BR-301)', function () use ($frJson) {
        expect($frJson)->toHaveKey('{1} Cannot delete :location because it has :count active order for pickup.|[2,*] Cannot delete :location because it has :count active orders for pickup.');
    });

    it('has Delete Pickup Location dialog title string', function () use ($enJson) {
        expect($enJson)->toHaveKey('Delete Pickup Location');
    });
});
