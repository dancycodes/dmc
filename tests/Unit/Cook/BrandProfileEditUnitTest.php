<?php

/**
 * F-080: Cook Brand Profile Edit — Unit Tests
 *
 * Tests for the BrandProfileController edit/update methods,
 * UpdateBrandProfileRequest form request, edit blade template,
 * route configuration, and translation strings.
 */

use App\Http\Controllers\Cook\BrandProfileController;
use App\Http\Requests\Cook\UpdateBrandProfileRequest;

$projectRoot = dirname(__DIR__, 3);

// Test group: BrandProfileController edit/update methods
describe('BrandProfileController edit/update', function () use ($projectRoot) {
    it('has an edit method', function () {
        $reflection = new ReflectionMethod(BrandProfileController::class, 'edit');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('has an update method', function () {
        $reflection = new ReflectionMethod(BrandProfileController::class, 'update');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('edit method accepts a Request parameter', function () {
        $reflection = new ReflectionMethod(BrandProfileController::class, 'edit');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('request');
    });

    it('update method accepts a Request parameter', function () {
        $reflection = new ReflectionMethod(BrandProfileController::class, 'update');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('request');
    });

    it('edit returns gale view response', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/BrandProfileController.php');
        expect($content)->toContain("gale()->view('cook.profile.edit'");
        expect($content)->toContain('web: true');
    });

    it('edit passes tenant data to the view', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/BrandProfileController.php');
        expect($content)->toContain("'tenant'");
    });

    it('edit checks can-manage-brand permission (BR-195)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/BrandProfileController.php');
        expect($content)->toContain("can('can-manage-brand')");
        expect($content)->toContain('abort(403)');
    });

    it('update method uses dual Gale/HTTP validation pattern', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/BrandProfileController.php');
        expect($content)->toContain('isGale()');
        expect($content)->toContain('validateState');
        expect($content)->toContain('UpdateBrandProfileRequest');
    });

    it('update redirects to profile view with success toast (BR-192, BR-193)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/BrandProfileController.php');
        expect($content)->toContain("redirect(url('/dashboard/profile'))");
        expect($content)->toContain("__('Profile updated successfully.')");
    });

    it('update logs activity with old/new values (BR-194)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/BrandProfileController.php');
        expect($content)->toContain("activity('tenants')");
        expect($content)->toContain("'brand_profile_updated'");
        expect($content)->toContain("'old'");
        expect($content)->toContain("'new'");
    });

    it('normalizes phone numbers before validation', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/BrandProfileController.php');
        expect($content)->toContain('RegisterRequest::normalizePhone');
    });

    it('handles paired bio validation (BR-188)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/BrandProfileController.php');
        expect($content)->toContain("__('Bio is required in French when provided in English.')");
        expect($content)->toContain("__('Bio is required in English when provided in French.')");
    });

    it('updates tenant with all brand profile fields', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/BrandProfileController.php');
        $updateFields = [
            'name_en', 'name_fr', 'description_en', 'description_fr',
            'whatsapp', 'phone', 'social_facebook', 'social_instagram', 'social_tiktok',
        ];
        foreach ($updateFields as $field) {
            expect($content)->toContain("'".$field."'");
        }
    });
});

// Test group: UpdateBrandProfileRequest
describe('UpdateBrandProfileRequest', function () use ($projectRoot) {
    it('exists as a class', function () {
        expect(class_exists(UpdateBrandProfileRequest::class))->toBeTrue();
    });

    it('extends FormRequest', function () {
        $reflection = new ReflectionClass(UpdateBrandProfileRequest::class);
        expect($reflection->isSubclassOf(\Illuminate\Foundation\Http\FormRequest::class))->toBeTrue();
    });

    it('has authorize method checking can-manage-brand permission', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/UpdateBrandProfileRequest.php');
        expect($content)->toContain("can('can-manage-brand')");
    });

    it('requires brand name in both languages (BR-187)', function () {
        $request = new UpdateBrandProfileRequest;
        $rules = $request->rules();
        expect($rules['name_en'])->toContain('required');
        expect($rules['name_fr'])->toContain('required');
        expect($rules['name_en'])->toContain('max:100');
        expect($rules['name_fr'])->toContain('max:100');
    });

    it('validates bio max 1000 chars (BR-188)', function () {
        $request = new UpdateBrandProfileRequest;
        $rules = $request->rules();
        expect($rules['description_en'])->toContain('max:1000');
        expect($rules['description_fr'])->toContain('max:1000');
    });

    it('requires whatsapp with Cameroon format (BR-189)', function () {
        $request = new UpdateBrandProfileRequest;
        $rules = $request->rules();
        expect($rules['whatsapp'])->toContain('required');
        $regexRule = collect($rules['whatsapp'])->first(fn ($r) => is_string($r) && str_starts_with($r, 'regex:'));
        expect($regexRule)->not->toBeNull();
    });

    it('phone is optional with Cameroon format (BR-190)', function () {
        $request = new UpdateBrandProfileRequest;
        $rules = $request->rules();
        expect($rules['phone'])->toContain('nullable');
        $regexRule = collect($rules['phone'])->first(fn ($r) => is_string($r) && str_starts_with($r, 'regex:'));
        expect($regexRule)->not->toBeNull();
    });

    it('social links are optional and validated as URLs (BR-191)', function () {
        $request = new UpdateBrandProfileRequest;
        $rules = $request->rules();
        foreach (['social_facebook', 'social_instagram', 'social_tiktok'] as $field) {
            expect($rules[$field])->toContain('nullable');
            expect($rules[$field])->toContain('url');
            expect($rules[$field])->toContain('max:500');
        }
    });

    it('has custom error messages using __() localization (BR-196)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/UpdateBrandProfileRequest.php');
        expect($content)->toContain("__('Brand name is required in English.')");
        expect($content)->toContain("__('Brand name is required in French.')");
        expect($content)->toContain("__('WhatsApp number is required.')");
        expect($content)->toContain("__('Please enter a valid Cameroon phone number.')");
        expect($content)->toContain("__('Please enter a valid URL.')");
    });

    it('has paired bio validation (BR-188)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/UpdateBrandProfileRequest.php');
        expect($content)->toContain('withValidator');
        expect($content)->toContain("__('Bio is required in French when provided in English.')");
        expect($content)->toContain("__('Bio is required in English when provided in French.')");
    });

    it('normalizes phone numbers in prepareForValidation', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/UpdateBrandProfileRequest.php');
        expect($content)->toContain('prepareForValidation');
        expect($content)->toContain('RegisterRequest::normalizePhone');
    });

    it('cleans empty social links to null', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Requests/Cook/UpdateBrandProfileRequest.php');
        expect($content)->toContain('social_facebook');
        expect($content)->toContain('social_instagram');
        expect($content)->toContain('social_tiktok');
        // Checks for null cleaning logic
        expect($content)->toContain('null');
    });

    it('has CAMEROON_PHONE_REGEX constant', function () {
        expect(UpdateBrandProfileRequest::CAMEROON_PHONE_REGEX)->toBe('/^\+237[672]\d{8}$/');
    });
});

// Test group: Brand profile edit blade template
describe('Brand profile edit blade', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/cook/profile/edit.blade.php';

    it('exists', function () use ($viewPath) {
        expect(file_exists($viewPath))->toBeTrue();
    });

    it('extends the cook dashboard layout', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('sets page title to Edit Brand Profile', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("@section('title', __('Edit Brand Profile'))");
    });

    it('has breadcrumb navigation', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Dashboard')");
        expect($content)->toContain("__('Profile')");
        expect($content)->toContain("__('Edit')");
        expect($content)->toContain("__('Breadcrumb')");
    });

    it('pre-populates form with tenant data', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('$tenant->name_en');
        expect($content)->toContain('$tenant->name_fr');
        expect($content)->toContain('$tenant->description_en');
        expect($content)->toContain('$tenant->description_fr');
        expect($content)->toContain('$tenant->whatsapp');
        expect($content)->toContain('$tenant->phone');
        expect($content)->toContain('$tenant->social_facebook');
        expect($content)->toContain('$tenant->social_instagram');
        expect($content)->toContain('$tenant->social_tiktok');
    });

    it('has x-data with all form fields', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('x-data');
        expect($content)->toContain('name_en:');
        expect($content)->toContain('name_fr:');
        expect($content)->toContain('description_en:');
        expect($content)->toContain('description_fr:');
        expect($content)->toContain('whatsapp:');
        expect($content)->toContain('phone:');
        expect($content)->toContain('social_facebook:');
        expect($content)->toContain('social_instagram:');
        expect($content)->toContain('social_tiktok:');
    });

    it('has x-sync for all form fields', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("x-sync=\"['name_en', 'name_fr', 'description_en', 'description_fr', 'whatsapp', 'phone', 'social_facebook', 'social_instagram', 'social_tiktok']\"");
    });

    it('submits to profile update endpoint via Gale $action (BR-192)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("\$action('{{ url('/dashboard/profile/update') }}')");
    });

    it('has x-name on all form inputs', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('x-name="name_en"');
        expect($content)->toContain('x-name="name_fr"');
        expect($content)->toContain('x-name="description_en"');
        expect($content)->toContain('x-name="description_fr"');
        expect($content)->toContain('x-name="whatsapp"');
        expect($content)->toContain('x-name="phone"');
        expect($content)->toContain('x-name="social_facebook"');
        expect($content)->toContain('x-name="social_instagram"');
        expect($content)->toContain('x-name="social_tiktok"');
    });

    it('has x-message for validation errors on all fields', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('x-message="name_en"');
        expect($content)->toContain('x-message="name_fr"');
        expect($content)->toContain('x-message="description_en"');
        expect($content)->toContain('x-message="description_fr"');
        expect($content)->toContain('x-message="whatsapp"');
        expect($content)->toContain('x-message="phone"');
        expect($content)->toContain('x-message="social_facebook"');
        expect($content)->toContain('x-message="social_instagram"');
        expect($content)->toContain('x-message="social_tiktok"');
    });

    it('has character counters for name fields', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("(name_en || '').length + '/100'");
        expect($content)->toContain("(name_fr || '').length + '/100'");
    });

    it('has character counters for bio fields', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("(description_en || '').length + '/1000'");
        expect($content)->toContain("(description_fr || '').length + '/1000'");
    });

    it('has +237 prefix indicator on phone fields', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect(substr_count($content, '+237'))->toBeGreaterThanOrEqual(2);
    });

    it('has social link platform icons', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        // Facebook blue, Instagram pink, TikTok
        expect($content)->toContain('[#1877F2]');
        expect($content)->toContain('[#E4405F]');
        expect($content)->toContain("__('Facebook')");
        expect($content)->toContain("__('Instagram')");
        expect($content)->toContain("__('TikTok')");
    });

    it('has Save button with loading state (BR-192)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Save')");
        expect($content)->toContain("__('Saving...')");
        expect($content)->toContain('$fetching()');
        expect($content)->toContain(':disabled="$fetching()"');
    });

    it('has Cancel link to profile view', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Cancel')");
        expect($content)->toContain("url('/dashboard/profile')");
    });

    it('uses semantic color tokens', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('bg-surface');
        expect($content)->toContain('dark:bg-surface');
        expect($content)->toContain('text-on-surface');
        expect($content)->toContain('text-primary');
        expect($content)->toContain('bg-primary');
    });

    it('is two-column on desktop, stacked on mobile', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('grid-cols-1 sm:grid-cols-2');
    });

    it('uses __() for all user-facing text (BR-196)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Brand Name')");
        expect($content)->toContain("__('Bio / Description')");
        expect($content)->toContain("__('Contact Information')");
        expect($content)->toContain("__('Social Links')");
        expect($content)->toContain("__('English')");
        expect($content)->toContain("__('French')");
        expect($content)->toContain("__('WhatsApp Number')");
        expect($content)->toContain("__('Phone Number')");
        expect($content)->toContain("__('optional')");
    });

    it('has card-based layout with shadow-card', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('shadow-card');
        expect($content)->toContain('rounded-xl');
    });
});

// Test group: Route configuration
describe('Brand profile edit routes', function () use ($projectRoot) {
    it('has edit route defined in web.php', function () use ($projectRoot) {
        $routesContent = file_get_contents($projectRoot.'/routes/web.php');
        expect($routesContent)->toContain("Route::get('/profile/edit'");
        expect($routesContent)->toContain("name('cook.profile.edit')");
    });

    it('has update route defined in web.php', function () use ($projectRoot) {
        $routesContent = file_get_contents($projectRoot.'/routes/web.php');
        expect($routesContent)->toContain("Route::post('/profile/update'");
        expect($routesContent)->toContain("name('cook.profile.update')");
    });

    it('routes point to BrandProfileController', function () use ($projectRoot) {
        $routesContent = file_get_contents($projectRoot.'/routes/web.php');
        expect($routesContent)->toContain("[BrandProfileController::class, 'edit']");
        expect($routesContent)->toContain("[BrandProfileController::class, 'update']");
    });
});

// Test group: Profile show view now links to edit page
describe('Profile show edit links updated for F-080', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/cook/profile/show.blade.php';

    it('edit links point to /dashboard/profile/edit instead of wizard', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        // Brand name, bio, contact, social sections should link to profile/edit
        expect($content)->toContain("url('/dashboard/profile/edit')");
        // Should NOT link to setup wizard for brand/bio/contact/social sections
        expect($content)->not->toContain("url('/dashboard/setup?step=1')");
    });

    it('links cover images to dedicated management page (F-081)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("url('/dashboard/profile/cover-images')");
    });
});

// Test group: Translations
describe('Brand profile edit translations', function () use ($projectRoot) {
    it('has Edit Brand Profile key in English', function () use ($projectRoot) {
        $en = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
        expect($en)->toHaveKey('Edit Brand Profile');
    });

    it('has Edit Brand Profile key in French', function () use ($projectRoot) {
        $fr = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);
        expect($fr)->toHaveKey('Edit Brand Profile');
        expect($fr['Edit Brand Profile'])->toBe('Modifier le profil de marque');
    });
});
