<?php

/**
 * F-126: Tenant Landing Page Layout — Unit Tests
 *
 * Tests for TenantLandingService, DashboardController tenantHome method,
 * tenant-public layout, tenant home view, route configuration,
 * and translation strings.
 *
 * BR-126: Tenant landing page renders ONLY on tenant domains
 * BR-127: Cook's selected theme applied dynamically
 * BR-128: Navigation includes Home, Meals, About, Contact
 * BR-129: Auth state reflected (guest vs authenticated)
 * BR-130: Fully responsive, mobile-first with hamburger nav
 * BR-131: All user-facing text uses __() localization
 * BR-132: Supports light and dark mode
 * BR-133: Sections render in order: hero, meals, about, ratings, testimonials, schedule, delivery, footer
 * BR-134: Publicly accessible without authentication
 * BR-135: All interactions use Gale
 */

use App\Http\Controllers\DashboardController;
use App\Models\Tenant;
use App\Services\TenantLandingService;
use App\Services\TenantThemeService;

$projectRoot = dirname(__DIR__, 3);

// ============================================================
// Test group: TenantLandingService
// ============================================================
describe('TenantLandingService', function () {
    it('exists and is instantiable', function () {
        $service = new TenantLandingService(new TenantThemeService);
        expect($service)->toBeInstanceOf(TenantLandingService::class);
    });

    it('has getLandingPageData method', function () {
        expect(method_exists(TenantLandingService::class, 'getLandingPageData'))->toBeTrue();
    });

    it('getLandingPageData accepts a Tenant parameter and optional page', function () {
        $reflection = new ReflectionMethod(TenantLandingService::class, 'getLandingPageData');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('tenant');
        expect($params[0]->getType()->getName())->toBe(Tenant::class);
        expect($params[1]->getName())->toBe('page');
        expect($params[1]->isOptional())->toBeTrue();
    });

    it('has constructor with TenantThemeService dependency', function () {
        $reflection = new ReflectionClass(TenantLandingService::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getType()->getName())->toBe(TenantThemeService::class);
    });
});

// ============================================================
// Test group: TenantThemeService Integration
// ============================================================
describe('TenantThemeService', function () {
    it('returns empty CSS for null tenant', function () {
        $service = new TenantThemeService;
        expect($service->generateInlineCss(null))->toBe('');
    });

    it('returns empty font link for null tenant', function () {
        $service = new TenantThemeService;
        expect($service->getFontLinkTag(null))->toBe('');
    });

    it('has resolvePreset method', function () {
        expect(method_exists(TenantThemeService::class, 'resolvePreset'))->toBeTrue();
    });

    it('has resolveFont method', function () {
        expect(method_exists(TenantThemeService::class, 'resolveFont'))->toBeTrue();
    });

    it('has resolveRadius method', function () {
        expect(method_exists(TenantThemeService::class, 'resolveRadius'))->toBeTrue();
    });

    it('has resolveThemeConfig method', function () {
        expect(method_exists(TenantThemeService::class, 'resolveThemeConfig'))->toBeTrue();
    });

    it('has generateInlineCss method', function () {
        expect(method_exists(TenantThemeService::class, 'generateInlineCss'))->toBeTrue();
    });

    it('has getFontLinkTag method', function () {
        expect(method_exists(TenantThemeService::class, 'getFontLinkTag'))->toBeTrue();
    });

    it('has isValidPreset method', function () {
        expect(method_exists(TenantThemeService::class, 'isValidPreset'))->toBeTrue();
    });

    it('has isValidFont method', function () {
        expect(method_exists(TenantThemeService::class, 'isValidFont'))->toBeTrue();
    });

    it('has isValidRadius method', function () {
        expect(method_exists(TenantThemeService::class, 'isValidRadius'))->toBeTrue();
    });

    it('has availablePresets method', function () {
        expect(method_exists(TenantThemeService::class, 'availablePresets'))->toBeTrue();
    });

    it('has availableFonts method', function () {
        expect(method_exists(TenantThemeService::class, 'availableFonts'))->toBeTrue();
    });

    it('has availableRadii method', function () {
        expect(method_exists(TenantThemeService::class, 'availableRadii'))->toBeTrue();
    });

    it('defines SETTING_PRESET constant', function () {
        expect(TenantThemeService::SETTING_PRESET)->toBe('theme');
    });

    it('defines SETTING_FONT constant', function () {
        expect(TenantThemeService::SETTING_FONT)->toBe('font');
    });

    it('defines SETTING_RADIUS constant', function () {
        expect(TenantThemeService::SETTING_RADIUS)->toBe('border_radius');
    });
});

// ============================================================
// Test group: DashboardController tenantHome method
// ============================================================
describe('DashboardController', function () {
    it('has tenantHome method', function () {
        expect(method_exists(DashboardController::class, 'tenantHome'))->toBeTrue();
    });

    it('tenantHome accepts Request and TenantLandingService parameters', function () {
        $reflection = new ReflectionMethod(DashboardController::class, 'tenantHome');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getType()->getName())->toBe(\Illuminate\Http\Request::class);
        expect($params[1]->getType()->getName())->toBe(TenantLandingService::class);
    });
});

// ============================================================
// Test group: File Existence
// ============================================================
describe('File Existence', function () use ($projectRoot) {
    it('tenant home view exists', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/resources/views/tenant/home.blade.php'))->toBeTrue();
    });

    it('tenant-public layout exists', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/resources/views/layouts/tenant-public.blade.php'))->toBeTrue();
    });

    it('TenantLandingService exists', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/app/Services/TenantLandingService.php'))->toBeTrue();
    });
});

// ============================================================
// Test group: Blade Template Content (BR-128, BR-129, BR-131)
// ============================================================
describe('Tenant Public Layout', function () use ($projectRoot) {
    $layoutContent = file_get_contents($projectRoot.'/resources/views/layouts/tenant-public.blade.php');

    it('extends app layout', function () use ($layoutContent) {
        expect($layoutContent)->toContain("@extends('layouts.app')");
    });

    it('has Home navigation link (BR-128)', function () use ($layoutContent) {
        expect($layoutContent)->toContain("__('Home')");
    });

    it('has Meals navigation link (BR-128)', function () use ($layoutContent) {
        expect($layoutContent)->toContain("__('Meals')");
    });

    it('has About navigation link (BR-128)', function () use ($layoutContent) {
        expect($layoutContent)->toContain("__('About')");
    });

    it('has Contact navigation link (BR-128)', function () use ($layoutContent) {
        expect($layoutContent)->toContain("__('Contact')");
    });

    it('has scroll-to functionality for anchor navigation', function () use ($layoutContent) {
        expect($layoutContent)->toContain('scrollTo(');
        expect($layoutContent)->toContain('scrollIntoView');
        expect($layoutContent)->toContain("behavior: 'smooth'");
    });

    it('shows Login link for guests (BR-129)', function () use ($layoutContent) {
        expect($layoutContent)->toContain("__('Login')");
        expect($layoutContent)->toContain("route('login')");
    });

    it('shows Register link for guests (BR-129)', function () use ($layoutContent) {
        expect($layoutContent)->toContain("__('Register')");
        expect($layoutContent)->toContain("route('register')");
    });

    it('shows user dropdown for authenticated users (BR-129)', function () use ($layoutContent) {
        expect($layoutContent)->toContain('@auth');
        expect($layoutContent)->toContain('auth()->user()->name');
        expect($layoutContent)->toContain('userMenuOpen');
    });

    it('has hamburger menu for mobile (BR-130)', function () use ($layoutContent) {
        expect($layoutContent)->toContain('mobileMenuOpen');
        expect($layoutContent)->toContain("__('Toggle menu')");
        expect($layoutContent)->toContain('lg:hidden');
    });

    it('has sticky nav with semi-transparent background on scroll', function () use ($layoutContent) {
        expect($layoutContent)->toContain('sticky top-0');
        expect($layoutContent)->toContain('scrolled');
        expect($layoutContent)->toContain('backdrop-blur');
    });

    it('uses localization for all text (BR-131)', function () use ($layoutContent) {
        expect($layoutContent)->toContain("__('Main navigation')");
        expect($layoutContent)->toContain("__('Mobile navigation')");
        expect($layoutContent)->toContain("__('Powered by')");
        expect($layoutContent)->toContain("__('Logout')");
        expect($layoutContent)->toContain("__('Profile')");
    });

    it('has dark mode support (BR-132)', function () use ($layoutContent) {
        expect($layoutContent)->toContain('dark:bg-surface');
        expect($layoutContent)->toContain('dark:border-outline');
    });

    it('includes theme and language switchers', function () use ($layoutContent) {
        expect($layoutContent)->toContain('<x-theme-switcher');
        expect($layoutContent)->toContain('<x-language-switcher');
    });

    it('has Powered by DancyMeals footer', function () use ($layoutContent) {
        expect($layoutContent)->toContain("__('Powered by')");
        expect($layoutContent)->toContain("config('app.name'");
        expect($layoutContent)->toContain("__('All rights reserved.')");
    });

    it('uses x-navigate-skip for logout form', function () use ($layoutContent) {
        expect($layoutContent)->toContain('x-navigate-skip');
    });

    it('updates URL hash on section navigation', function () use ($layoutContent) {
        expect($layoutContent)->toContain('history.replaceState');
    });

    it('tracks active section for navigation highlighting', function () use ($layoutContent) {
        expect($layoutContent)->toContain('activeSection');
        expect($layoutContent)->toContain('updateActiveSection');
    });
});

// ============================================================
// Test group: Tenant Home View (BR-133)
// ============================================================
describe('Tenant Home View', function () use ($projectRoot) {
    $homeContent = file_get_contents($projectRoot.'/resources/views/tenant/home.blade.php');

    it('extends tenant-public layout', function () use ($homeContent) {
        expect($homeContent)->toContain("@extends('layouts.tenant-public')");
    });

    it('has hero section (BR-133)', function () use ($homeContent) {
        expect($homeContent)->toContain('id="hero"');
    });

    it('has meals section (BR-133)', function () use ($homeContent) {
        expect($homeContent)->toContain('id="meals"');
    });

    it('has about section (BR-133)', function () use ($homeContent) {
        expect($homeContent)->toContain('id="about"');
    });

    it('has schedule section (BR-133)', function () use ($homeContent) {
        expect($homeContent)->toContain('id="schedule"');
    });

    it('has delivery areas section (BR-133)', function () use ($homeContent) {
        expect($homeContent)->toContain('id="delivery-areas"');
    });

    it('has View Meals CTA button', function () use ($homeContent) {
        expect($homeContent)->toContain("__('View Meals')");
    });

    it('has Our Meals section heading', function () use ($homeContent) {
        expect($homeContent)->toContain("__('Our Meals')");
    });

    it('has About Us section heading', function () use ($homeContent) {
        expect($homeContent)->toContain("__('About Us')");
    });

    it('has Schedule section heading', function () use ($homeContent) {
        expect($homeContent)->toContain("__('Schedule & Availability')");
    });

    it('has Delivery Areas section heading', function () use ($homeContent) {
        expect($homeContent)->toContain("__('Delivery Areas')");
    });

    it('shows empty states for sections without data', function () use ($homeContent) {
        // Meals empty state is now in the included _meals-grid partial (F-128)
        expect($homeContent)->toContain("@include('tenant._meals-grid'");
        $mealsGridContent = file_get_contents(resource_path('views/tenant/_meals-grid.blade.php'));
        expect($mealsGridContent)->toContain("__('No meals available right now.')");
        // About, delivery empty states remain in home.blade.php
        expect($homeContent)->toContain("__('More details coming soon.')");
        // F-132: Schedule empty state is now in the _schedule-section partial
        expect($homeContent)->toContain("@include('tenant._schedule-section'");
        $scheduleContent = file_get_contents(resource_path('views/tenant/_schedule-section.blade.php'));
        expect($scheduleContent)->toContain("__('Schedule not yet available. Contact the cook for ordering information.')");
        expect($homeContent)->toContain("__('Delivery information coming soon.')");
    });

    it('has cover image carousel with slide indicators', function () use ($homeContent) {
        expect($homeContent)->toContain('currentSlide');
        expect($homeContent)->toContain('totalSlides');
        expect($homeContent)->toContain("__('Go to slide')");
    });

    it('has loading skeleton placeholders', function () use ($homeContent) {
        expect($homeContent)->toContain('animate-pulse');
    });

    it('uses scroll-mt-16 for scroll offset on all sections', function () use ($homeContent) {
        expect($homeContent)->toContain('scroll-mt-16');
    });

    it('has footer-content section for social links (F-134)', function () use ($homeContent) {
        expect($homeContent)->toContain("@section('footer-content')");
        expect($homeContent)->toContain("__('Follow Us')");
        expect($homeContent)->toContain("__('Contact')");
    });

    it('has WhatsApp contact link', function () use ($homeContent) {
        expect($homeContent)->toContain('wa.me');
        expect($homeContent)->toContain("__('WhatsApp')");
    });

    it('has social media links (facebook, instagram, tiktok)', function () use ($homeContent) {
        expect($homeContent)->toContain("'facebook'");
        expect($homeContent)->toContain("'instagram'");
        expect($homeContent)->toContain("'tiktok'");
        expect($homeContent)->toContain('socialLinks');
    });

    it('uses dark mode variants for all sections (BR-132)', function () use ($homeContent) {
        expect($homeContent)->toContain('dark:bg-surface-alt');
        expect($homeContent)->toContain('dark:border-outline');
    });

    it('uses semantic color tokens', function () use ($homeContent) {
        expect($homeContent)->toContain('bg-surface');
        expect($homeContent)->toContain('text-on-surface');
        expect($homeContent)->toContain('bg-primary');
        expect($homeContent)->toContain('text-on-primary');
    });

    it('uses responsive grid for meals via included partial', function () use ($homeContent) {
        // F-128: Grid is now in the included _meals-grid partial
        expect($homeContent)->toContain("@include('tenant._meals-grid'");
        $mealsGridContent = file_get_contents(resource_path('views/tenant/_meals-grid.blade.php'));
        expect($mealsGridContent)->toContain('grid-cols-1 sm:grid-cols-2 lg:grid-cols-3');
    });
});

// ============================================================
// Test group: Translation Strings (BR-131)
// ============================================================
describe('Translation Strings', function () use ($projectRoot) {
    $enJson = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
    $frJson = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    $requiredStrings = [
        'About',
        'Contact',
        'My Addresses',
        'All rights reserved.',
        'Our Meals',
        'Discover our selection of freshly prepared dishes.',
        'No meals available yet. Check back soon!',
        'About Us',
        'More details coming soon.',
        'Our operating hours and availability.',
        'Schedule information coming soon.',
        'Find out if we deliver to your area.',
        'Delivery information coming soon.',
        'Follow Us',
        'Cover image',
        'Go to slide',
        'View Meals',
        'Delicious home-cooked meals made with love.',
    ];

    foreach ($requiredStrings as $string) {
        it("has English translation for '{$string}'", function () use ($enJson, $string) {
            expect($enJson)->toHaveKey($string);
        });

        it("has French translation for '{$string}'", function () use ($frJson, $string) {
            expect($frJson)->toHaveKey($string);
            expect($frJson[$string])->not->toBeEmpty();
        });
    }
});

// ============================================================
// Test group: Route Configuration
// ============================================================
describe('Route Configuration', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.'/routes/web.php');

    it('root route dispatches to tenantHome for tenant domains', function () use ($routeContent) {
        expect($routeContent)->toContain('tenantHome');
        expect($routeContent)->toContain('TenantLandingService');
    });

    it('root route uses TenantService to check domain', function () use ($routeContent) {
        expect($routeContent)->toContain('isTenantDomain()');
    });

    it('landing page route is publicly accessible (BR-134)', function () use ($routeContent) {
        // The root route '/' does not have auth middleware — it's publicly accessible
        expect($routeContent)->toContain("Route::get('/', function ()");
    });
});

// ============================================================
// Test group: Tenant Model Theme Methods
// ============================================================
describe('Tenant Model Theme Methods', function () {
    it('has getThemePreset method', function () {
        expect(method_exists(Tenant::class, 'getThemePreset'))->toBeTrue();
    });

    it('has getThemeFont method', function () {
        expect(method_exists(Tenant::class, 'getThemeFont'))->toBeTrue();
    });

    it('has getThemeBorderRadius method', function () {
        expect(method_exists(Tenant::class, 'getThemeBorderRadius'))->toBeTrue();
    });

    it('has getUrl method for tenant URL generation', function () {
        expect(method_exists(Tenant::class, 'getUrl'))->toBeTrue();
    });

    it('has getSetting method', function () {
        expect(method_exists(Tenant::class, 'getSetting'))->toBeTrue();
    });

    it('returns null for theme preset on new tenant', function () {
        $tenant = new Tenant;
        expect($tenant->getThemePreset())->toBeNull();
    });

    it('returns null for theme font on new tenant', function () {
        $tenant = new Tenant;
        expect($tenant->getThemeFont())->toBeNull();
    });

    it('returns null for border radius on new tenant', function () {
        $tenant = new Tenant;
        expect($tenant->getThemeBorderRadius())->toBeNull();
    });

    it('returns setting from settings JSON', function () {
        $tenant = new Tenant;
        $tenant->settings = ['theme' => 'ocean', 'font' => 'poppins'];

        expect($tenant->getSetting('theme'))->toBe('ocean');
        expect($tenant->getSetting('font'))->toBe('poppins');
        expect($tenant->getSetting('unknown', 'default'))->toBe('default');
    });

    it('has media collection for cover images', function () {
        expect(method_exists(Tenant::class, 'registerMediaCollections'))->toBeTrue();
    });
});
