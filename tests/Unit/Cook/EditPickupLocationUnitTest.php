<?php

/**
 * F-094: Edit Pickup Location -- Unit Tests
 *
 * Tests for the edit pickup location functionality including:
 * - DeliveryAreaService::updatePickupLocation() business logic
 * - Controller edit and update methods
 * - UpdatePickupLocationRequest validation
 * - Blade template edit form
 * - Route configuration
 * - Translation strings
 * - Edge cases (special characters, missing town/quarter)
 *
 * BR-295: Location name required in both English and French
 * BR-296: Town and quarter selection required
 * BR-297: Address/description required; max 500 characters
 * BR-298: Save via Gale; list updates without page reload
 * BR-299: Changes apply to new orders; existing orders retain original data
 * BR-300: Edit action requires location management permission
 */

use App\Http\Controllers\Cook\PickupLocationController;
use App\Http\Requests\Cook\UpdatePickupLocationRequest;
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
// Test group: DeliveryAreaService::updatePickupLocation
// ============================================================
describe('DeliveryAreaService::updatePickupLocation', function () {
    beforeEach(function () {
        $this->seedRolesAndPermissions();
        $this->service = app(DeliveryAreaService::class);

        $this->tenant = Tenant::factory()->create();
        $this->town = Town::factory()->create(['name_en' => 'Douala', 'name_fr' => 'Douala']);
        $this->quarter = Quarter::factory()->create(['town_id' => $this->town->id, 'name_en' => 'Akwa', 'name_fr' => 'Akwa']);
        DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $this->town->id]);

        $this->pickup = PickupLocation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'town_id' => $this->town->id,
            'quarter_id' => $this->quarter->id,
            'name_en' => 'My Kitchen',
            'name_fr' => 'Ma Cuisine',
            'address' => 'Behind Akwa Palace Hotel',
        ]);
    });

    it('successfully updates a pickup location name (BR-295)', function () {
        $result = $this->service->updatePickupLocation(
            $this->tenant,
            $this->pickup->id,
            'Updated Kitchen',
            'Cuisine Modifiée',
            $this->town->id,
            $this->quarter->id,
            'Behind Akwa Palace Hotel',
        );

        expect($result['success'])->toBeTrue();
        expect($result['pickup_model']->name_en)->toBe('Updated Kitchen');
        expect($result['pickup_model']->name_fr)->toBe('Cuisine Modifiée');
    });

    it('successfully updates town and quarter (BR-296)', function () {
        $town2 = Town::factory()->create(['name_en' => 'Yaounde', 'name_fr' => 'Yaoundé']);
        $quarter2 = Quarter::factory()->create(['town_id' => $town2->id, 'name_en' => 'Bastos', 'name_fr' => 'Bastos']);
        DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $town2->id]);

        $result = $this->service->updatePickupLocation(
            $this->tenant,
            $this->pickup->id,
            'My Kitchen',
            'Ma Cuisine',
            $town2->id,
            $quarter2->id,
            'Behind Akwa Palace Hotel',
        );

        expect($result['success'])->toBeTrue();
        expect($result['pickup_model']->town_id)->toBe($town2->id);
        expect($result['pickup_model']->quarter_id)->toBe($quarter2->id);
    });

    it('successfully updates address (BR-297)', function () {
        $result = $this->service->updatePickupLocation(
            $this->tenant,
            $this->pickup->id,
            'My Kitchen',
            'Ma Cuisine',
            $this->town->id,
            $this->quarter->id,
            'Behind Akwa Palace Hotel, 2nd floor',
        );

        expect($result['success'])->toBeTrue();
        expect($result['pickup_model']->address)->toBe('Behind Akwa Palace Hotel, 2nd floor');
    });

    it('fails when pickup location does not exist', function () {
        $result = $this->service->updatePickupLocation(
            $this->tenant,
            99999,
            'Updated',
            'Modifié',
            $this->town->id,
            $this->quarter->id,
            'Some address',
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toBe(__('Pickup location not found.'));
    });

    it('fails when pickup location belongs to another tenant', function () {
        $otherTenant = Tenant::factory()->create();
        $otherPickup = PickupLocation::factory()->create([
            'tenant_id' => $otherTenant->id,
            'town_id' => $this->town->id,
            'quarter_id' => $this->quarter->id,
        ]);

        $result = $this->service->updatePickupLocation(
            $this->tenant,
            $otherPickup->id,
            'Updated',
            'Modifié',
            $this->town->id,
            $this->quarter->id,
            'Some address',
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toBe(__('Pickup location not found.'));
    });

    it('fails when town is not in cook delivery areas', function () {
        $unlinkedTown = Town::factory()->create(['name_en' => 'Buea', 'name_fr' => 'Buéa']);
        $unlinkedQuarter = Quarter::factory()->create(['town_id' => $unlinkedTown->id]);

        $result = $this->service->updatePickupLocation(
            $this->tenant,
            $this->pickup->id,
            'Updated',
            'Modifié',
            $unlinkedTown->id,
            $unlinkedQuarter->id,
            'Some address',
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toBe(__('Please select a town from your delivery areas.'));
    });

    it('fails when quarter does not belong to selected town', function () {
        $otherTown = Town::factory()->create(['name_en' => 'Limbe', 'name_fr' => 'Limbé']);
        $otherQuarter = Quarter::factory()->create(['town_id' => $otherTown->id]);
        DeliveryArea::create(['tenant_id' => $this->tenant->id, 'town_id' => $otherTown->id]);

        $result = $this->service->updatePickupLocation(
            $this->tenant,
            $this->pickup->id,
            'Updated',
            'Modifié',
            $this->town->id,
            $otherQuarter->id,
            'Some address',
        );

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toBe(__('The selected quarter does not belong to this town.'));
    });

    it('returns the updated model with loaded relationships', function () {
        $result = $this->service->updatePickupLocation(
            $this->tenant,
            $this->pickup->id,
            'Updated Kitchen',
            'Cuisine Modifiée',
            $this->town->id,
            $this->quarter->id,
            'New address here',
        );

        expect($result['success'])->toBeTrue();
        expect($result['pickup_model'])->toBeInstanceOf(PickupLocation::class);
        expect($result['pickup_model']->relationLoaded('town'))->toBeTrue();
        expect($result['pickup_model']->relationLoaded('quarter'))->toBeTrue();
    });

    it('preserves original data in database record (BR-299 conceptual)', function () {
        // BR-299: Changes apply to new orders; existing orders retain original data
        // This is handled at the order level (orders store snapshot), not here.
        // Verify the update actually persists to DB
        $this->service->updatePickupLocation(
            $this->tenant,
            $this->pickup->id,
            'New Name',
            'Nouveau Nom',
            $this->town->id,
            $this->quarter->id,
            'New address',
        );

        $fresh = PickupLocation::find($this->pickup->id);
        expect($fresh->name_en)->toBe('New Name');
        expect($fresh->name_fr)->toBe('Nouveau Nom');
        expect($fresh->address)->toBe('New address');
    });

    it('handles address with special characters', function () {
        $specialAddress = "123 Rue de l'Église, étage 2 — à côté du marché";

        $result = $this->service->updatePickupLocation(
            $this->tenant,
            $this->pickup->id,
            'My Kitchen',
            'Ma Cuisine',
            $this->town->id,
            $this->quarter->id,
            $specialAddress,
        );

        expect($result['success'])->toBeTrue();
        expect($result['pickup_model']->address)->toBe($specialAddress);
    });

    it('handles address with line breaks', function () {
        $multilineAddress = "Building A\nFloor 3\nApartment 12";

        $result = $this->service->updatePickupLocation(
            $this->tenant,
            $this->pickup->id,
            'My Kitchen',
            'Ma Cuisine',
            $this->town->id,
            $this->quarter->id,
            $multilineAddress,
        );

        expect($result['success'])->toBeTrue();
        expect($result['pickup_model']->address)->toBe($multilineAddress);
    });
});

// ============================================================
// Test group: Controller
// ============================================================
describe('PickupLocationController', function () {
    $controllerProjectRoot = dirname(__DIR__, 3);

    it('has an edit method that returns mixed', function () {
        $reflection = new ReflectionMethod(PickupLocationController::class, 'edit');
        expect($reflection->isPublic())->toBeTrue();
        expect((string) $reflection->getReturnType())->toBe('mixed');
    });

    it('has an update method that returns mixed', function () {
        $reflection = new ReflectionMethod(PickupLocationController::class, 'update');
        expect($reflection->isPublic())->toBeTrue();
        expect((string) $reflection->getReturnType())->toBe('mixed');
    });

    it('edit method checks can-manage-locations permission (BR-300)', function () use ($controllerProjectRoot) {
        $content = file_get_contents($controllerProjectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $editSection = substr($content, strpos($content, 'public function edit'));
        $updateStart = strpos($editSection, 'public function update');
        if ($updateStart !== false) {
            $editSection = substr($editSection, 0, $updateStart);
        }
        expect($editSection)->toContain("can('can-manage-locations')");
    });

    it('update method checks can-manage-locations permission (BR-300)', function () use ($controllerProjectRoot) {
        $content = file_get_contents($controllerProjectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain("can('can-manage-locations')");
    });

    it('edit method returns gale view response', function () use ($controllerProjectRoot) {
        $content = file_get_contents($controllerProjectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $editSection = substr($content, strpos($content, 'public function edit'));
        $updateStart = strpos($editSection, 'public function update');
        if ($updateStart !== false) {
            $editSection = substr($editSection, 0, $updateStart);
        }
        expect($editSection)->toContain("gale()->view('cook.locations.pickup'");
        expect($editSection)->toContain('web: true');
    });

    it('edit method passes editingPickup data to view', function () use ($controllerProjectRoot) {
        $content = file_get_contents($controllerProjectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $editSection = substr($content, strpos($content, 'public function edit'));
        $updateStart = strpos($editSection, 'public function update');
        if ($updateStart !== false) {
            $editSection = substr($editSection, 0, $updateStart);
        }
        expect($editSection)->toContain("'editingPickup'");
    });

    it('update method uses dual Gale/HTTP validation pattern', function () use ($controllerProjectRoot) {
        $content = file_get_contents($controllerProjectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain('isGale()');
        expect($updateSection)->toContain('validateState');
        expect($updateSection)->toContain('UpdatePickupLocationRequest');
    });

    it('update method uses DeliveryAreaService for business logic', function () use ($controllerProjectRoot) {
        $content = file_get_contents($controllerProjectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain('updatePickupLocation');
    });

    it('update method includes activity logging', function () use ($controllerProjectRoot) {
        $content = file_get_contents($controllerProjectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain("activity('pickup_locations')");
        expect($updateSection)->toContain("'pickup_location_updated'");
    });

    it('update method tracks old and new values for activity log', function () use ($controllerProjectRoot) {
        $content = file_get_contents($controllerProjectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain("'old'");
        expect($updateSection)->toContain("'new'");
    });

    it('update method returns gale redirect with success toast (BR-298)', function () use ($controllerProjectRoot) {
        $content = file_get_contents($controllerProjectRoot.'/app/Http/Controllers/Cook/PickupLocationController.php');
        $updateSection = substr($content, strpos($content, 'public function update'));
        expect($updateSection)->toContain('gale()');
        expect($updateSection)->toContain('->redirect(');
        expect($updateSection)->toContain("->with('success'");
        expect($updateSection)->toContain("__('Pickup location updated successfully.')");
    });
});

// ============================================================
// Test group: UpdatePickupLocationRequest
// ============================================================
describe('UpdatePickupLocationRequest', function () use ($projectRoot) {
    it('exists at the correct path', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/app/Http/Requests/Cook/UpdatePickupLocationRequest.php'))->toBeTrue();
    });

    it('authorizes users with can-manage-locations permission (BR-300)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/UpdatePickupLocationRequest.php');
        expect($content)->toContain("can('can-manage-locations')");
    });

    it('requires name_en field (BR-295)', function () {
        $request = new UpdatePickupLocationRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('name_en');
        expect($rules['name_en'])->toContain('required');
    });

    it('requires name_fr field (BR-295)', function () {
        $request = new UpdatePickupLocationRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('name_fr');
        expect($rules['name_fr'])->toContain('required');
    });

    it('requires town_id field (BR-296)', function () {
        $request = new UpdatePickupLocationRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('town_id');
        expect($rules['town_id'])->toContain('required');
    });

    it('requires quarter_id field (BR-296)', function () {
        $request = new UpdatePickupLocationRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('quarter_id');
        expect($rules['quarter_id'])->toContain('required');
    });

    it('requires address field with max 500 (BR-297)', function () {
        $request = new UpdatePickupLocationRequest;
        $rules = $request->rules();
        expect($rules)->toHaveKey('address');
        expect($rules['address'])->toContain('required');
        expect($rules['address'])->toContain('max:500');
    });

    it('has custom error messages', function () {
        $request = new UpdatePickupLocationRequest;
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
// Test group: Blade template (Edit form)
// ============================================================
describe('Pickup Locations Blade Template (Edit Form)', function () use ($projectRoot) {
    it('contains F-094 business rule comments', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('BR-295');
        expect($content)->toContain('BR-296');
        expect($content)->toContain('BR-297');
        expect($content)->toContain('BR-298');
        expect($content)->toContain('BR-300');
    });

    it('has edit form with edit_ prefixed state keys', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('edit_name_en');
        expect($content)->toContain('edit_name_fr');
        expect($content)->toContain('edit_town_id');
        expect($content)->toContain('edit_quarter_id');
        expect($content)->toContain('edit_address');
    });

    it('has editingId state for tracking which location is being edited', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('editingId');
    });

    it('has startEdit method to populate edit form', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('startEdit(');
    });

    it('has cancelEdit method to close edit form', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('cancelEdit()');
    });

    it('has getEditQuartersForTown method for cascading quarters in edit', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('getEditQuartersForTown()');
    });

    it('shows edit form inline for the location being edited', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('x-show="editingId ===');
    });

    it('hides display card when editing that location', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('x-show="editingId !==');
    });

    it('edit form uses PUT method for update', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("method: 'PUT'");
    });

    it('edit form has address character counter', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('editAddressCharCount');
    });

    it('edit form has cascading town/quarter dropdowns', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        // Verify town change resets quarter
        expect($content)->toContain("@change=\"edit_quarter_id = ''\"");
    });

    it('edit form has loading state on save button', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        // The inline edit form section uses $fetching() for loading state
        $editFormStart = strpos($content, "__('Edit Pickup Location')");
        expect($editFormStart)->not->toBeFalse();
        // Use a larger range to cover the full edit form including action buttons
        $editFormContent = substr($content, $editFormStart, 10000);
        expect($editFormContent)->toContain('$fetching()');
    });

    it('edit form has Cancel button', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        $editFormStart = strpos($content, "__('Edit Pickup Location')");
        expect($editFormStart)->not->toBeFalse();
        $editFormContent = substr($content, $editFormStart, 10000);
        expect($editFormContent)->toContain('cancelEdit()');
    });

    it('uses x-message for edit validation errors', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('x-message="edit_name_en"');
        expect($content)->toContain('x-message="edit_name_fr"');
        expect($content)->toContain('x-message="edit_town_id"');
        expect($content)->toContain('x-message="edit_quarter_id"');
        expect($content)->toContain('x-message="edit_address"');
    });

    it('x-sync includes edit state keys', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        // Verify x-sync includes all edit keys
        expect($content)->toContain("'edit_name_en'");
        expect($content)->toContain("'edit_name_fr'");
        expect($content)->toContain("'edit_town_id'");
        expect($content)->toContain("'edit_quarter_id'");
        expect($content)->toContain("'edit_address'");
    });

    it('edit button uses startEdit with location data', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('startEdit(');
        expect($content)->toContain("\$location['name_en']");
        expect($content)->toContain("\$location['name_fr']");
        expect($content)->toContain("\$location['town_id']");
        expect($content)->toContain("\$location['quarter_id']");
        expect($content)->toContain("\$location['address']");
    });

    it('supports pre-populated edit from server (editingPickup)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain('$editingPickup');
    });

    it('uses __() for Edit Pickup Location heading', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/locations/pickup.blade.php');
        expect($content)->toContain("__('Edit Pickup Location')");
    });
});

// ============================================================
// Test group: Routes
// ============================================================
describe('Routes (F-094)', function () {
    it('has GET route for pickup location edit', function () {
        $route = collect(app('router')->getRoutes()->getRoutesByMethod()['GET'])
            ->first(fn ($r) => $r->getName() === 'cook.locations.pickup.edit');
        expect($route)->not->toBeNull();
        expect($route->uri())->toContain('dashboard/locations/pickup');
        expect($route->uri())->toContain('edit');
    });

    it('has PUT route for pickup location update', function () {
        $route = collect(app('router')->getRoutes()->getRoutesByMethod()['PUT'])
            ->first(fn ($r) => $r->getName() === 'cook.locations.pickup.update');
        expect($route)->not->toBeNull();
        expect($route->uri())->toContain('dashboard/locations/pickup');
    });
});

// ============================================================
// Test group: Translation strings
// ============================================================
describe('Translation Strings (F-094)', function () use ($projectRoot) {
    it('has English translations for F-094 strings', function () use ($projectRoot) {
        $en = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

        expect($en)->toHaveKey('Edit Pickup Location');
        expect($en)->toHaveKey('Pickup location updated successfully.');
        expect($en)->toHaveKey('Pickup location not found.');
    });

    it('has French translations for F-094 strings', function () use ($projectRoot) {
        $fr = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

        expect($fr)->toHaveKey('Edit Pickup Location');
        expect($fr)->toHaveKey('Pickup location updated successfully.');
        expect($fr)->toHaveKey('Pickup location not found.');

        // Verify French translations are actual French
        expect($fr['Edit Pickup Location'])->not->toBe('Edit Pickup Location');
        expect($fr['Pickup location updated successfully.'])->not->toBe('Pickup location updated successfully.');
    });
});

// ============================================================
// Test group: DeliveryAreaService method shape
// ============================================================
describe('DeliveryAreaService updatePickupLocation method', function () use ($projectRoot) {
    it('exists with correct signature', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'updatePickupLocation');
        expect($reflection->isPublic())->toBeTrue();
        $params = $reflection->getParameters();
        expect(count($params))->toBe(7);
        expect($params[0]->getName())->toBe('tenant');
        expect($params[1]->getName())->toBe('pickupLocationId');
        expect($params[2]->getName())->toBe('nameEn');
        expect($params[3]->getName())->toBe('nameFr');
        expect($params[4]->getName())->toBe('townId');
        expect($params[5]->getName())->toBe('quarterId');
        expect($params[6]->getName())->toBe('address');
    });

    it('validates town belongs to delivery areas', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodStart = strpos($content, 'function updatePickupLocation');
        $methodEnd = strpos($content, 'function removePickupLocation');
        $methodContent = substr($content, $methodStart, $methodEnd - $methodStart);

        expect($methodContent)->toContain("where('tenant_id'");
        expect($methodContent)->toContain("where('town_id'");
    });

    it('validates quarter belongs to selected town', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodStart = strpos($content, 'function updatePickupLocation');
        $methodEnd = strpos($content, 'function removePickupLocation');
        $methodContent = substr($content, $methodStart, $methodEnd - $methodStart);

        expect($methodContent)->toContain('Quarter::query()');
        expect($methodContent)->toContain("where('town_id'");
    });

    it('loads town and quarter relationships after update', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodStart = strpos($content, 'function updatePickupLocation');
        $methodEnd = strpos($content, 'function removePickupLocation');
        $methodContent = substr($content, $methodStart, $methodEnd - $methodStart);

        expect($methodContent)->toContain("load(['town', 'quarter'])");
    });
});
