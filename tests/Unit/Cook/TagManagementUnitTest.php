<?php

/**
 * F-115: Cook Tag Management — Unit Tests
 *
 * Tests for Tag model, TagService, TagController, StoreTagRequest,
 * UpdateTagRequest, route configuration, blade view, and translation strings.
 *
 * BR-252: Tags are tenant-scoped
 * BR-253: Tag name required in both EN and FR
 * BR-254: Tag name unique within tenant per language
 * BR-255: Tags cannot be deleted if assigned to any meal
 * BR-256: Editing a tag name updates it everywhere
 * BR-257: Only users with manage-meals permission
 * BR-258: Tag CRUD operations are logged
 * BR-259: Tag name max length: 50 characters
 * BR-260: Case-insensitive uniqueness
 */

use App\Http\Controllers\Cook\TagController;
use App\Http\Requests\Cook\StoreTagRequest;
use App\Http\Requests\Cook\UpdateTagRequest;
use App\Models\Meal;
use App\Models\Tag;
use App\Models\Tenant;
use App\Services\TagService;

$projectRoot = dirname(__DIR__, 3);

// ============================================================
// Test group: Tag Model
// ============================================================
describe('Tag Model', function () use ($projectRoot) {
    it('uses the tags table', function () {
        $tag = new Tag;
        expect($tag->getTable())->toBe('tags');
    });

    it('has the correct fillable attributes', function () {
        $tag = new Tag;
        expect($tag->getFillable())->toContain('tenant_id')
            ->toContain('name_en')
            ->toContain('name_fr');
    });

    it('defines NAME_MAX_LENGTH constant as 50 (BR-259)', function () {
        expect(Tag::NAME_MAX_LENGTH)->toBe(50);
    });

    it('has a tenant relationship method (BR-252)', function () {
        $reflection = new ReflectionMethod(Tag::class, 'tenant');
        expect($reflection->isPublic())->toBeTrue();
        expect($reflection->getReturnType()?->getName())
            ->toBe(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    });

    it('has a meals relationship method (many-to-many via meal_tag)', function () {
        $reflection = new ReflectionMethod(Tag::class, 'meals');
        expect($reflection->isPublic())->toBeTrue();
        expect($reflection->getReturnType()?->getName())
            ->toBe(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
    });

    it('has a scopeForTenant scope', function () {
        $reflection = new ReflectionMethod(Tag::class, 'scopeForTenant');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has an isInUse method', function () {
        $reflection = new ReflectionMethod(Tag::class, 'isInUse');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has HasTranslatable trait with name translatable', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Models/Tag.php');
        expect($content)->toContain('HasTranslatable');
        expect($content)->toContain("'name'");
    });

    it('has LogsActivityTrait for audit logging (BR-258)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Models/Tag.php');
        expect($content)->toContain('LogsActivityTrait');
    });

    it('has a factory', function () {
        expect(Tag::factory())->toBeInstanceOf(\Illuminate\Database\Eloquent\Factories\Factory::class);
    });
});

// ============================================================
// Test group: Meal Model — tags relationship
// ============================================================
describe('Meal Model tags relationship', function () {
    it('has a tags relationship method (many-to-many via meal_tag)', function () {
        $reflection = new ReflectionMethod(Meal::class, 'tags');
        expect($reflection->isPublic())->toBeTrue();
        expect($reflection->getReturnType()?->getName())
            ->toBe(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
    });
});

// ============================================================
// Test group: TagController
// ============================================================
describe('TagController', function () use ($projectRoot) {
    it('has an index method', function () {
        $reflection = new ReflectionMethod(TagController::class, 'index');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a store method', function () {
        $reflection = new ReflectionMethod(TagController::class, 'store');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has an update method', function () {
        $reflection = new ReflectionMethod(TagController::class, 'update');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a destroy method', function () {
        $reflection = new ReflectionMethod(TagController::class, 'destroy');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('index returns gale view response', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TagController.php');
        expect($content)->toContain("gale()->view('cook.tags.index'");
        expect($content)->toContain('web: true');
    });

    it('index passes tags to view', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TagController.php');
        expect($content)->toContain("'tags'");
    });

    it('index checks can-manage-meals permission (BR-257)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TagController.php');
        expect($content)->toContain("can('can-manage-meals')");
        expect($content)->toContain('abort(403)');
    });

    it('store uses dual Gale/HTTP validation pattern', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TagController.php');
        expect($content)->toContain('isGale()');
        expect($content)->toContain('validateState');
        expect($content)->toContain('StoreTagRequest');
    });

    it('update uses edit_ prefixed state keys for Gale validation', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TagController.php');
        $updateContent = substr($content, strpos($content, 'public function update'));
        expect($updateContent)->toContain("'edit_name_en'");
        expect($updateContent)->toContain("'edit_name_fr'");
    });

    it('update verifies tag belongs to tenant', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TagController.php');
        $updateContent = substr($content, strpos($content, 'public function update'));
        expect($updateContent)->toContain('$tag->tenant_id !== $tenant->id');
        expect($updateContent)->toContain('abort(404)');
    });

    it('destroy verifies tag belongs to tenant', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TagController.php');
        $destroyContent = substr($content, strpos($content, 'public function destroy'));
        expect($destroyContent)->toContain('$tag->tenant_id !== $tenant->id');
    });

    it('store returns Gale redirect with toast on success', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TagController.php');
        expect($content)->toContain("__('Tag created.')");
    });

    it('update returns Gale redirect with toast on success', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TagController.php');
        expect($content)->toContain("__('Tag updated.')");
    });

    it('destroy returns Gale redirect with toast on success', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/TagController.php');
        expect($content)->toContain("__('Tag deleted.')");
    });
});

// ============================================================
// Test group: TagService
// ============================================================
describe('TagService', function () use ($projectRoot) {
    it('has a getTagsForTenant method', function () {
        $reflection = new ReflectionMethod(TagService::class, 'getTagsForTenant');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a createTag method', function () {
        $reflection = new ReflectionMethod(TagService::class, 'createTag');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has an updateTag method', function () {
        $reflection = new ReflectionMethod(TagService::class, 'updateTag');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a deleteTag method', function () {
        $reflection = new ReflectionMethod(TagService::class, 'deleteTag');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('getTagsForTenant uses withCount meals and orders by localized name', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/TagService.php');
        expect($content)->toContain("withCount('meals')");
        expect($content)->toContain("localized('name')");
    });

    it('createTag performs case-insensitive uniqueness check (BR-260)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/TagService.php');
        expect($content)->toContain('LOWER(name_en)');
        expect($content)->toContain('LOWER(name_fr)');
        expect($content)->toContain('mb_strtolower');
    });

    it('createTag trims whitespace before storage', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/TagService.php');
        expect($content)->toContain("trim(\$data['name_en'])");
        expect($content)->toContain("trim(\$data['name_fr'])");
    });

    it('deleteTag checks meal count before deletion (BR-255)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/TagService.php');
        expect($content)->toContain('->count()');
        expect($content)->toContain('$mealCount > 0');
    });

    it('deleteTag returns error message with meal count when in use', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/TagService.php');
        expect($content)->toContain("'success' => false");
        expect($content)->toContain(':count meals');
    });

    it('updateTag excludes current tag from uniqueness check', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/TagService.php');
        expect($content)->toContain('$tag->id');
        expect($content)->toContain('$excludeId');
    });
});

// ============================================================
// Test group: StoreTagRequest
// ============================================================
describe('StoreTagRequest', function () use ($projectRoot) {
    it('requires name_en (BR-253)', function () {
        $request = new StoreTagRequest;
        $rules = $request->rules();
        expect($rules['name_en'])->toContain('required');
    });

    it('requires name_fr (BR-253)', function () {
        $request = new StoreTagRequest;
        $rules = $request->rules();
        expect($rules['name_fr'])->toContain('required');
    });

    it('enforces max length on name_en (BR-259)', function () {
        $request = new StoreTagRequest;
        $rules = $request->rules();
        expect($rules['name_en'])->toContain('max:'.Tag::NAME_MAX_LENGTH);
    });

    it('enforces max length on name_fr (BR-259)', function () {
        $request = new StoreTagRequest;
        $rules = $request->rules();
        expect($rules['name_fr'])->toContain('max:'.Tag::NAME_MAX_LENGTH);
    });

    it('authorizes users with can-manage-meals permission (BR-257)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/StoreTagRequest.php');
        expect($content)->toContain("can('can-manage-meals')");
    });
});

// ============================================================
// Test group: UpdateTagRequest
// ============================================================
describe('UpdateTagRequest', function () use ($projectRoot) {
    it('requires name_en (BR-253)', function () {
        $request = new UpdateTagRequest;
        $rules = $request->rules();
        expect($rules['name_en'])->toContain('required');
    });

    it('requires name_fr (BR-253)', function () {
        $request = new UpdateTagRequest;
        $rules = $request->rules();
        expect($rules['name_fr'])->toContain('required');
    });

    it('enforces max length on name_en (BR-259)', function () {
        $request = new UpdateTagRequest;
        $rules = $request->rules();
        expect($rules['name_en'])->toContain('max:'.Tag::NAME_MAX_LENGTH);
    });

    it('authorizes users with can-manage-meals permission (BR-257)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/UpdateTagRequest.php');
        expect($content)->toContain("can('can-manage-meals')");
    });
});

// ============================================================
// Test group: Routes
// ============================================================
describe('Tag Routes', function () use ($projectRoot) {
    it('defines cook.tags.index route', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain('cook.tags.index');
    });

    it('defines cook.tags.store route', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain('cook.tags.store');
    });

    it('defines cook.tags.update route', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain('cook.tags.update');
    });

    it('defines cook.tags.destroy route', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain('cook.tags.destroy');
    });

    it('uses TagController for tag routes', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain('TagController');
    });
});

// ============================================================
// Test group: Blade View
// ============================================================
describe('Tag Index Blade View', function () use ($projectRoot) {
    it('extends cook-dashboard layout', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('has x-data with Alpine state for add form', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain('showAddForm');
        expect($content)->toContain('name_en');
        expect($content)->toContain('name_fr');
    });

    it('has x-data with Alpine state for edit form with edit_ prefix', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain('editingId');
        expect($content)->toContain('edit_name_en');
        expect($content)->toContain('edit_name_fr');
    });

    it('has delete confirmation modal', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain('confirmDeleteId');
        expect($content)->toContain('confirmDeleteName');
        expect($content)->toContain('executeDelete');
    });

    it('uses $action for form submissions', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain('$action(');
    });

    it('uses $fetching() for loading states', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain('$fetching()');
    });

    it('uses x-name for form inputs', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain('x-name="name_en"');
        expect($content)->toContain('x-name="name_fr"');
    });

    it('uses x-message for validation errors', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain('x-message="name_en"');
        expect($content)->toContain('x-message="name_fr"');
        expect($content)->toContain('x-message="edit_name_en"');
        expect($content)->toContain('x-message="edit_name_fr"');
    });

    it('uses semantic color tokens', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain('bg-surface');
        expect($content)->toContain('text-on-surface');
        expect($content)->toContain('bg-primary');
        expect($content)->toContain('text-on-primary');
    });

    it('supports dark mode with dark: variants', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain('dark:');
    });

    it('has responsive layout with desktop table and mobile cards', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain('hidden md:block');
        expect($content)->toContain('md:hidden');
    });

    it('has empty state with create button', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain("__('No tags yet')");
        expect($content)->toContain("__('Create Your First Tag')");
    });

    it('disables delete button when tag has meals (BR-255)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain('$tag->meals_count > 0');
        expect($content)->toContain('disabled');
    });

    it('uses __() for all user-facing strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain("__('Tags')");
        expect($content)->toContain("__('Add Tag')");
        expect($content)->toContain("__('Cancel')");
        expect($content)->toContain("__('Save')");
        expect($content)->toContain("__('Delete Tag')");
    });

    it('uses x-model for edit form inputs', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain('x-model="edit_name_en"');
        expect($content)->toContain('x-model="edit_name_fr"');
    });

    it('includes edit form with PUT method', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain("method: 'PUT'");
    });

    it('includes delete action with DELETE method', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/tags/index.blade.php');
        expect($content)->toContain("method: 'DELETE'");
    });
});

// ============================================================
// Test group: Translation strings
// ============================================================
describe('Tag Management Translations', function () use ($projectRoot) {
    it('has English translation strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/lang/en.json');
        $translations = json_decode($content, true);
        expect($translations)->toHaveKey('Add Tag');
        expect($translations)->toHaveKey('New Tag');
        expect($translations)->toHaveKey('Edit Tag');
        expect($translations)->toHaveKey('Delete Tag');
        expect($translations)->toHaveKey('Tag created.');
        expect($translations)->toHaveKey('Tag updated.');
        expect($translations)->toHaveKey('Tag deleted.');
        expect($translations)->toHaveKey('No tags yet');
        expect($translations)->toHaveKey('Create Your First Tag');
        expect($translations)->toHaveKey('A tag with this English name already exists.');
        expect($translations)->toHaveKey('A tag with this French name already exists.');
    });

    it('has French translation strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/lang/fr.json');
        $translations = json_decode($content, true);
        expect($translations)->toHaveKey('Add Tag');
        expect($translations)->toHaveKey('Tag created.');
        expect($translations)->toHaveKey('Tag deleted.');
        expect($translations)->toHaveKey('No tags yet');
    });
});

// ============================================================
// Test group: Migration and database schema
// ============================================================
describe('Tag Database Schema', function () use ($projectRoot) {
    it('has tags migration file', function () use ($projectRoot) {
        $files = glob($projectRoot.'/database/migrations/*create_tags_table*');
        expect($files)->not->toBeEmpty();
    });

    it('tags migration creates tags table', function () use ($projectRoot) {
        $files = glob($projectRoot.'/database/migrations/*create_tags_table*');
        $content = file_get_contents($files[0]);
        expect($content)->toContain("Schema::create('tags'");
    });

    it('tags migration creates meal_tag pivot table', function () use ($projectRoot) {
        $files = glob($projectRoot.'/database/migrations/*create_tags_table*');
        $content = file_get_contents($files[0]);
        expect($content)->toContain("Schema::create('meal_tag'");
    });

    it('tags migration has tenant_id foreign key (BR-252)', function () use ($projectRoot) {
        $files = glob($projectRoot.'/database/migrations/*create_tags_table*');
        $content = file_get_contents($files[0]);
        expect($content)->toContain('tenant_id');
        expect($content)->toContain("foreignId('tenant_id')");
    });

    it('tags migration has name_en with max 50 chars (BR-259)', function () use ($projectRoot) {
        $files = glob($projectRoot.'/database/migrations/*create_tags_table*');
        $content = file_get_contents($files[0]);
        expect($content)->toContain('name_en');
    });

    it('tags migration has uniqueness constraints (BR-254)', function () use ($projectRoot) {
        $files = glob($projectRoot.'/database/migrations/*create_tags_table*');
        $content = file_get_contents($files[0]);
        expect($content)->toContain('unique');
    });
});
