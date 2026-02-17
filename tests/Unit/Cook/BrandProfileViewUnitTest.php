<?php

/**
 * F-079: Cook Brand Profile View — Unit Tests
 *
 * Tests for the BrandProfileController, brand profile view blade template,
 * route configuration, and translation strings.
 */

use App\Http\Controllers\Cook\BrandProfileController;
use App\Models\Tenant;

$projectRoot = dirname(__DIR__, 3);

// Test group: BrandProfileController
describe('BrandProfileController', function () use ($projectRoot) {
    it('exists as a class', function () {
        expect(class_exists(BrandProfileController::class))->toBeTrue();
    });

    it('has a show method', function () {
        $reflection = new ReflectionMethod(BrandProfileController::class, 'show');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('show method accepts a Request parameter', function () {
        $reflection = new ReflectionMethod(BrandProfileController::class, 'show');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('request');
    });

    it('returns gale view response', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/BrandProfileController.php');
        expect($content)->toContain("gale()->view('cook.profile.show'");
        expect($content)->toContain('web: true');
    });

    it('passes tenant data to the view', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/BrandProfileController.php');
        expect($content)->toContain("'tenant'");
        expect($content)->toContain("'coverImages'");
        expect($content)->toContain("'canEdit'");
    });

    it('checks can-manage-brand permission for edit links', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/BrandProfileController.php');
        // BR-185: Edit links only for users with profile edit permission
        expect($content)->toContain("can('can-manage-brand')");
    });

    it('retrieves cover images from Spatie Media Library', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/BrandProfileController.php');
        expect($content)->toContain("getMedia('cover-images')");
        expect($content)->toContain("getUrl('carousel')");
        expect($content)->toContain("getUrl('thumbnail')");
    });
});

// Test group: Brand profile view blade template
describe('Brand profile view blade', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/cook/profile/show.blade.php';

    it('exists', function () use ($viewPath) {
        expect(file_exists($viewPath))->toBeTrue();
    });

    it('extends the cook dashboard layout', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('sets page title to Brand Profile', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("@section('title', __('Brand Profile'))");
    });

    it('sets page-title to Profile', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("@section('page-title', __('Profile'))");
    });

    it('displays brand name in current locale (BR-180)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('$brandName');
        expect($content)->toContain('name_');
    });

    it('displays bio/description (BR-181)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('$brandBio');
        expect($content)->toContain('description_');
    });

    it('has edit links for each section (BR-182)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        // Each section has an edit link to the setup wizard
        expect($content)->toContain("__('Edit cover images')");
        expect($content)->toContain("__('Edit brand name')");
        expect($content)->toContain("__('Edit bio')");
        expect($content)->toContain("__('Edit contact info')");
        expect($content)->toContain("__('Edit social links')");
    });

    it('conditionally shows edit links based on canEdit (BR-185)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        // Edit links are wrapped in @if($canEdit)
        expect(substr_count($content, '@if($canEdit)'))->toBeGreaterThanOrEqual(5);
    });

    it('has cover images carousel (BR-183)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('x-data');
        expect($content)->toContain('current');
        expect($content)->toContain('startAutoplay');
        expect($content)->toContain('stopAutoplay');
    });

    it('has carousel navigation arrows', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Previous image')");
        expect($content)->toContain("__('Next image')");
    });

    it('has carousel dot indicators', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Go to image')");
    });

    it('shows placeholder when no cover images', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Add cover images to make your profile stand out.')");
    });

    it('displays WhatsApp link with wa.me (BR-186)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('wa.me');
        expect($content)->toContain("__('Chat on WhatsApp')");
    });

    it('displays phone link', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('tel:');
        expect($content)->toContain("__('Call phone number')");
    });

    it('displays social links as platform icons (BR-184)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Visit Facebook page')");
        expect($content)->toContain("__('Visit Instagram profile')");
        expect($content)->toContain("__('Visit TikTok profile')");
    });

    it('opens social links in new tab', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        // BR-184: Social links open in new tab
        expect($content)->toContain('target="_blank"');
        expect($content)->toContain('rel="noopener noreferrer"');
    });

    it('shows empty state for no bio', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('No bio added yet')");
    });

    it('shows empty state for no social links', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('No social links')");
    });

    it('shows empty state for no contact information', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('No contact information added')");
    });

    it('shows translation missing indicator', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('French translation missing')");
        expect($content)->toContain("__('English translation missing')");
    });

    it('uses semantic color tokens for light and dark mode', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('bg-surface');
        expect($content)->toContain('dark:bg-surface');
        expect($content)->toContain('text-on-surface');
        expect($content)->toContain('text-primary');
    });

    it('uses __() for all user-facing text', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        // Check key localized strings
        expect($content)->toContain("__('Brand Profile')");
        expect($content)->toContain("__('Cover Images')");
        expect($content)->toContain("__('Brand Name')");
        expect($content)->toContain("__('Bio')");
        expect($content)->toContain("__('Contact Information')");
        expect($content)->toContain("__('Social Links')");
        expect($content)->toContain("__('Edit')");
    });

    it('uses font-display for brand name heading', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('font-display');
    });

    it('uses card-based layout with shadow-card', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('shadow-card');
        expect($content)->toContain('rounded-xl');
    });

    it('is mobile-first responsive', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('sm:');
        expect($content)->toContain('max-w-3xl');
    });
});

// Test group: Route configuration
describe('Brand profile route', function () use ($projectRoot) {
    it('is defined in web.php', function () use ($projectRoot) {
        $routesContent = file_get_contents($projectRoot.'/routes/web.php');
        expect($routesContent)->toContain("Route::get('/profile'");
        expect($routesContent)->toContain('BrandProfileController');
        expect($routesContent)->toContain("name('cook.profile.show')");
    });

    it('is within the tenant domain and cook.access middleware group', function () use ($projectRoot) {
        $routesContent = file_get_contents($projectRoot.'/routes/web.php');
        // Route is within prefix('dashboard')->middleware(['auth', 'cook.access', 'throttle:moderate'])
        expect($routesContent)->toContain("'cook.access'");
        expect($routesContent)->toContain("prefix('dashboard')");
    });

    it('is imported at the top of routes file', function () use ($projectRoot) {
        $routesContent = file_get_contents($projectRoot.'/routes/web.php');
        expect($routesContent)->toContain('use App\Http\Controllers\Cook\BrandProfileController;');
    });
});

// Test group: Translations
describe('Brand profile translations', function () use ($projectRoot) {
    it('has all new keys in English', function () use ($projectRoot) {
        $en = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
        $keys = [
            'Brand Profile',
            'Cover Images',
            'Edit cover images',
            'Previous image',
            'Next image',
            'Go to image',
            'Image failed to load',
            'Add cover images to make your profile stand out.',
            'Add Cover Images',
            'No brand name set',
            'French translation missing',
            'English translation missing',
            'Bio',
            'Edit bio',
            'No bio added yet',
            'Edit brand name',
            'Edit contact info',
            'No contact information added',
            'Chat on WhatsApp',
            'Call phone number',
            'Edit social links',
            'No social links',
            'Visit Facebook page',
            'Visit Instagram profile',
            'Visit TikTok profile',
        ];
        foreach ($keys as $key) {
            expect($en)->toHaveKey($key);
        }
    });

    it('has all new keys in French', function () use ($projectRoot) {
        $fr = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);
        $keys = [
            'Brand Profile',
            'Cover Images',
            'Edit cover images',
            'Previous image',
            'Next image',
            'Go to image',
            'Image failed to load',
            'Add cover images to make your profile stand out.',
            'Add Cover Images',
            'No brand name set',
            'French translation missing',
            'English translation missing',
            'Bio',
            'Edit bio',
            'No bio added yet',
            'Edit brand name',
            'Edit contact info',
            'No contact information added',
            'Chat on WhatsApp',
            'Call phone number',
            'Edit social links',
            'No social links',
            'Visit Facebook page',
            'Visit Instagram profile',
            'Visit TikTok profile',
        ];
        foreach ($keys as $key) {
            expect($fr)->toHaveKey($key);
        }
    });

    it('French translations are not empty', function () use ($projectRoot) {
        $fr = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);
        expect($fr['Brand Profile'])->toBe('Profil de marque');
        expect($fr['Cover Images'])->toBe('Images de couverture');
        expect($fr['No bio added yet'])->toBe('Aucune bio ajoutée');
        expect($fr['No social links'])->toBe('Aucun lien social');
    });
});

// Test group: Tenant model has required fields
describe('Tenant model profile fields', function () {
    it('has social link fields in fillable', function () {
        $tenant = new Tenant;
        $fillable = $tenant->getFillable();
        expect($fillable)->toContain('social_facebook');
        expect($fillable)->toContain('social_instagram');
        expect($fillable)->toContain('social_tiktok');
        expect($fillable)->toContain('whatsapp');
        expect($fillable)->toContain('phone');
    });

    it('has translatable name and description', function () {
        $tenant = new Tenant;
        $fillable = $tenant->getFillable();
        expect($fillable)->toContain('name_en');
        expect($fillable)->toContain('name_fr');
        expect($fillable)->toContain('description_en');
        expect($fillable)->toContain('description_fr');
    });

    it('has cover-images media collection registered', function () {
        $tenant = new Tenant;
        $tenant->registerMediaCollections();
        $collection = collect($tenant->mediaCollections)->firstWhere('name', 'cover-images');
        expect($collection)->not->toBeNull();
    });
});

// Test group: Navigation sidebar references profile
describe('Sidebar navigation profile link', function () use ($projectRoot) {
    it('has profile navigation item pointing to /dashboard/profile', function () use ($projectRoot) {
        $layoutContent = file_get_contents($projectRoot.'/resources/views/layouts/cook-dashboard.blade.php');
        expect($layoutContent)->toContain("'url' => '/dashboard/profile'");
        expect($layoutContent)->toContain("'permission' => 'can-manage-brand'");
    });
});
