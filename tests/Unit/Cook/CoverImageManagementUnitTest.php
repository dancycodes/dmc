<?php

/**
 * F-081: Cook Cover Images Management — Unit Tests
 *
 * Tests for the CoverImageController, cover images management blade template,
 * route configuration, and translation strings.
 */

use App\Http\Controllers\Cook\CoverImageController;
use App\Services\CoverImageService;

$projectRoot = dirname(__DIR__, 3);

// Test group: CoverImageController
describe('CoverImageController', function () use ($projectRoot) {
    it('exists as a class', function () {
        expect(class_exists(CoverImageController::class))->toBeTrue();
    });

    it('injects CoverImageService via constructor', function () {
        $reflection = new ReflectionClass(CoverImageController::class);
        $constructor = $reflection->getConstructor();
        expect($constructor)->not->toBeNull();

        $params = $constructor->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('coverImageService');
        expect($params[0]->getType()->getName())->toBe(CoverImageService::class);
    });

    it('has an index method', function () {
        $reflection = new ReflectionMethod(CoverImageController::class, 'index');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has an upload method', function () {
        $reflection = new ReflectionMethod(CoverImageController::class, 'upload');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a reorder method', function () {
        $reflection = new ReflectionMethod(CoverImageController::class, 'reorder');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has a destroy method', function () {
        $reflection = new ReflectionMethod(CoverImageController::class, 'destroy');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('index returns gale view response', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/CoverImageController.php');
        expect($content)->toContain("gale()->view('cook.profile.cover-images'");
        expect($content)->toContain('web: true');
    });

    it('index passes required data to view', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/CoverImageController.php');
        expect($content)->toContain("'tenant'");
        expect($content)->toContain("'images'");
        expect($content)->toContain("'imageCount'");
        expect($content)->toContain("'maxImages'");
        expect($content)->toContain("'canUploadMore'");
    });

    it('checks can-manage-brand permission on all methods', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/CoverImageController.php');
        // Should check permission on index, upload, reorder, and destroy
        $count = substr_count($content, "can('can-manage-brand')");
        expect($count)->toBeGreaterThanOrEqual(4);
    });

    it('upload validates file inputs', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/CoverImageController.php');
        expect($content)->toContain("'images' => ['required', 'array', 'min:1']");
        expect($content)->toContain("'mimes:jpg,jpeg,png,webp'");
        expect($content)->toContain('MAX_FILE_SIZE_KB');
    });

    it('upload enforces maximum image count BR-197', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/CoverImageController.php');
        expect($content)->toContain('MAX_IMAGES');
        expect($content)->toContain('remainingSlots');
    });

    it('reorder validates ordered IDs', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/CoverImageController.php');
        expect($content)->toContain("'orderedIds' => ['required', 'array', 'min:1']");
        expect($content)->toContain("'orderedIds.*' => ['required', 'integer']");
    });

    it('destroy uses CoverImageService deleteImage', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/CoverImageController.php');
        expect($content)->toContain('deleteImage($tenant, $mediaId)');
    });

    it('logs activity on upload', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/CoverImageController.php');
        expect($content)->toContain("'cover_images_uploaded'");
        expect($content)->toContain("->log('Cover images uploaded via profile management')");
    });

    it('logs activity on reorder', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/CoverImageController.php');
        expect($content)->toContain("'cover_images_reordered'");
        expect($content)->toContain("->log('Cover images reordered via profile management')");
    });

    it('logs activity on delete', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/CoverImageController.php');
        expect($content)->toContain("'cover_image_deleted'");
        expect($content)->toContain("->log('Cover image deleted via profile management')");
    });

    it('upload handles Gale state updates', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/CoverImageController.php');
        expect($content)->toContain("->state('images',");
        expect($content)->toContain("->state('imageCount',");
        expect($content)->toContain("->state('canUploadMore',");
    });

    it('destroy removes element via Gale', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/CoverImageController.php');
        expect($content)->toContain("->remove('#cover-image-'");
    });
});

// Test group: Cover images management blade template
describe('Cover Images Management Blade Template', function () use ($projectRoot) {
    $bladePath = $projectRoot.'/resources/views/cook/profile/cover-images.blade.php';

    it('exists', function () use ($bladePath) {
        expect(file_exists($bladePath))->toBeTrue();
    });

    it('extends cook dashboard layout', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('has page title', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain("@section('title',");
        expect($content)->toContain("__('Cover Images')");
    });

    it('has breadcrumb navigation', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain("__('Dashboard')");
        expect($content)->toContain("__('Profile')");
        expect($content)->toContain("__('Cover Images')");
        expect($content)->toContain('aria-label=');
    });

    it('has image counter showing current/max BR-117', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain('imageCount');
        expect($content)->toContain('maxImages');
        expect($content)->toContain("__('images uploaded')");
    });

    it('has upload drop zone', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain('x-files');
        expect($content)->toContain('cover-image-input');
        expect($content)->toContain('image/jpeg,image/png,image/webp');
    });

    it('hides upload when max images reached BR-204', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain('x-show="canUploadMore"');
        expect($content)->toContain("__('Maximum reached')");
    });

    it('has upload progress indicator', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain('$uploading');
        expect($content)->toContain('$uploadProgress');
    });

    it('has file preview for staged uploads', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain("\$files('images')");
        expect($content)->toContain("\$filePreview('images'");
    });

    it('has existing images grid with drag-and-drop BR-202', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain('cover-images-grid');
        expect($content)->toContain('draggable="true"');
        expect($content)->toContain('@dragstart');
        expect($content)->toContain('@dragover.prevent');
        expect($content)->toContain('@dragend');
    });

    it('supports touch drag for mobile BR-206', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain('@touchstart');
        expect($content)->toContain('@touchmove');
        expect($content)->toContain('@touchend');
    });

    it('has arrow button fallback for reordering', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain('moveUp(index)');
        expect($content)->toContain('moveDown(index)');
    });

    it('has primary badge on first image BR-201', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain('x-show="index === 0"');
        expect($content)->toContain("__('Primary')");
    });

    it('has delete button with confirmation dialog BR-203', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain('confirmDelete(img.id, img.name)');
        expect($content)->toContain('cancelDelete()');
        expect($content)->toContain('executeDelete()');
        expect($content)->toContain("__('Delete Image?')");
    });

    it('has preview carousel', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain('carouselIndex');
        expect($content)->toContain('startCarousel');
        expect($content)->toContain('stopCarousel');
        expect($content)->toContain("__('Preview')");
    });

    it('has empty state', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain("__('No cover images yet')");
    });

    it('uses semantic color tokens', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain('bg-surface');
        expect($content)->toContain('text-on-surface');
        expect($content)->toContain('bg-primary');
        expect($content)->toContain('text-danger');
        expect($content)->toContain('bg-danger-subtle');
    });

    it('has dark mode variants', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain('dark:bg-surface');
        expect($content)->toContain('dark:border-outline');
    });

    it('has back to profile link', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        expect($content)->toContain("__('Back to Profile')");
        expect($content)->toContain('/dashboard/profile');
    });

    it('all user-facing strings use translation helper', function () use ($bladePath) {
        $content = file_get_contents($bladePath);
        // Key strings should be wrapped in __()
        expect($content)->toContain("__('Cover Images')");
        expect($content)->toContain("__('Upload New Images')");
        expect($content)->toContain("__('Upload Images')");
        expect($content)->toContain("__('Delete')");
        expect($content)->toContain("__('Cancel')");
        expect($content)->toContain("__('Primary')");
        expect($content)->toContain("__('Preview')");
    });
});

// Test group: Routes
describe('Cover Images Management Routes', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.'/routes/web.php');

    it('has index route', function () use ($routeContent) {
        expect($routeContent)->toContain("Route::get('/profile/cover-images'");
        expect($routeContent)->toContain("CoverImageController::class, 'index'");
        expect($routeContent)->toContain("'cook.cover-images.index'");
    });

    it('has upload route', function () use ($routeContent) {
        expect($routeContent)->toContain("Route::post('/profile/cover-images/upload'");
        expect($routeContent)->toContain("CoverImageController::class, 'upload'");
        expect($routeContent)->toContain("'cook.cover-images.upload'");
    });

    it('has reorder route', function () use ($routeContent) {
        expect($routeContent)->toContain("Route::post('/profile/cover-images/reorder'");
        expect($routeContent)->toContain("CoverImageController::class, 'reorder'");
        expect($routeContent)->toContain("'cook.cover-images.reorder'");
    });

    it('has destroy route', function () use ($routeContent) {
        expect($routeContent)->toContain("Route::delete('/profile/cover-images/{mediaId}'");
        expect($routeContent)->toContain("CoverImageController::class, 'destroy'");
        expect($routeContent)->toContain("'cook.cover-images.destroy'");
    });

    it('routes are under cook dashboard middleware group', function () use ($routeContent) {
        // Routes are within the dashboard prefix group that has auth, cook.access, throttle
        expect($routeContent)->toContain("Route::prefix('dashboard')->middleware(['auth', 'cook.access', 'throttle:moderate'])");
    });

    it('imports CoverImageController', function () use ($routeContent) {
        expect($routeContent)->toContain('use App\Http\Controllers\Cook\CoverImageController;');
    });
});

// Test group: Brand Profile View links to cover images management
describe('Brand Profile View Links to Cover Images Management', function () use ($projectRoot) {
    $showBlade = file_get_contents($projectRoot.'/resources/views/cook/profile/show.blade.php');

    it('links to cover images management page instead of wizard', function () use ($showBlade) {
        expect($showBlade)->toContain('/dashboard/profile/cover-images');
        expect($showBlade)->not->toContain('/dashboard/setup?step=2');
    });
});

// Test group: Translation strings
describe('Cover Images Management Translations', function () use ($projectRoot) {
    it('has English translations for new strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/lang/en.json');
        $translations = json_decode($content, true);

        expect($translations)->toHaveKey('Manage cover images for your store page and discovery card.');
        expect($translations)->toHaveKey('Upload New Images');
        expect($translations)->toHaveKey('No cover images yet');
        expect($translations)->toHaveKey('Maximum :count images allowed. Delete one to upload a new one.');
    });

    it('has French translations for new strings', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/lang/fr.json');
        $translations = json_decode($content, true);

        expect($translations)->toHaveKey('Manage cover images for your store page and discovery card.');
        expect($translations)->toHaveKey('Upload New Images');
        expect($translations)->toHaveKey('No cover images yet');
        expect($translations)->toHaveKey('Maximum :count images allowed. Delete one to upload a new one.');

        // Ensure French values are not just copies of English
        expect($translations['Upload New Images'])->not->toBe('Upload New Images');
        expect($translations['No cover images yet'])->not->toBe('No cover images yet');
    });
});

// Test group: CoverImageService (existing service reuse)
describe('CoverImageService Constants', function () {
    it('has MAX_IMAGES constant set to 5', function () {
        expect(CoverImageService::MAX_IMAGES)->toBe(5);
    });

    it('has MAX_FILE_SIZE_KB constant set to 2048', function () {
        expect(CoverImageService::MAX_FILE_SIZE_KB)->toBe(2048);
    });

    it('has accepted MIME types for JPEG PNG WebP', function () {
        expect(CoverImageService::ACCEPTED_MIMES)->toContain('image/jpeg');
        expect(CoverImageService::ACCEPTED_MIMES)->toContain('image/png');
        expect(CoverImageService::ACCEPTED_MIMES)->toContain('image/webp');
    });

    it('has COLLECTION constant', function () {
        expect(CoverImageService::COLLECTION)->toBe('cover-images');
    });
});
