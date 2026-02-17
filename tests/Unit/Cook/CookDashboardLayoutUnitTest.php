<?php

/**
 * F-076: Cook Dashboard Layout & Navigation — Unit Tests
 *
 * Tests for the EnsureCookAccess middleware, Tenant::isSetupComplete(),
 * and navigation permission filtering logic.
 */

use App\Http\Middleware\EnsureCookAccess;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

$projectRoot = dirname(__DIR__, 3);

// Test group: Tenant::isSetupComplete()
describe('Tenant::isSetupComplete()', function () {
    it('returns false when setup_complete is not set', function () {
        $tenant = new Tenant(['settings' => []]);
        expect($tenant->isSetupComplete())->toBeFalse();
    });

    it('returns false when setup_complete is false', function () {
        $tenant = new Tenant(['settings' => ['setup_complete' => false]]);
        expect($tenant->isSetupComplete())->toBeFalse();
    });

    it('returns true when setup_complete is true', function () {
        $tenant = new Tenant(['settings' => ['setup_complete' => true]]);
        expect($tenant->isSetupComplete())->toBeTrue();
    });

    it('returns false when settings is null', function () {
        $tenant = new Tenant;
        expect($tenant->isSetupComplete())->toBeFalse();
    });
});

// Test group: EnsureCookAccess middleware
describe('EnsureCookAccess middleware', function () use ($projectRoot) {
    it('has the correct handle method signature', function () {
        $middleware = new EnsureCookAccess;
        $reflection = new \ReflectionMethod($middleware, 'handle');

        // Must accept Request and Closure parameters
        expect($reflection->getNumberOfParameters())->toBe(2);
        expect($reflection->getReturnType()->getName())->toBe(Response::class);
    });

    it('exists as a class', function () {
        expect(class_exists(EnsureCookAccess::class))->toBeTrue();
    });

    it('is registered as cook.access middleware alias', function () use ($projectRoot) {
        $bootstrapContent = file_get_contents($projectRoot.'/bootstrap/app.php');
        expect($bootstrapContent)->toContain("'cook.access'");
        expect($bootstrapContent)->toContain('EnsureCookAccess');
    });
});

// Test group: Cook dashboard layout structure
describe('Cook dashboard layout', function () use ($projectRoot) {
    $layoutPath = $projectRoot.'/resources/views/layouts/cook-dashboard.blade.php';

    it('extends the base app layout', function () use ($layoutPath) {
        $content = file_get_contents($layoutPath);
        expect($content)->toContain("@extends('layouts.app')");
    });

    it('contains all 12 navigation section labels', function () use ($layoutPath) {
        $content = file_get_contents($layoutPath);
        // BR-159: All 12 sections
        $sections = [
            'Home', 'Meals', 'Orders', 'Locations', 'Schedule',
            'Profile', 'Managers', 'Settings', 'Analytics', 'Wallet',
            'Complaints', 'Testimonials',
        ];
        foreach ($sections as $section) {
            expect($content)->toContain("__('$section')");
        }
    });

    it('contains all 7 navigation group titles', function () use ($layoutPath) {
        $content = file_get_contents($layoutPath);
        $groups = ['Overview', 'Business', 'Coverage', 'Brand', 'Insights', 'Engagement', 'System'];
        foreach ($groups as $group) {
            expect($content)->toContain("__('$group')");
        }
    });

    it('uses permission-based filtering for navigation', function () use ($layoutPath) {
        $content = file_get_contents($layoutPath);
        // BR-158: Permission-based navigation
        expect($content)->toContain("'permission' =>");
        expect($content)->toContain('$user->can(');
    });

    it('includes tenant branding in sidebar header', function () use ($layoutPath) {
        $content = file_get_contents($layoutPath);
        // BR-160: Tenant branding
        expect($content)->toContain('$tenantName');
        expect($content)->toContain('font-display');
    });

    it('includes setup banner component', function () use ($layoutPath) {
        $content = file_get_contents($layoutPath);
        // BR-163: Setup completion banner
        expect($content)->toContain('<x-cook.setup-banner');
    });

    it('includes mobile hamburger menu', function () use ($layoutPath) {
        $content = file_get_contents($layoutPath);
        // BR-162: Responsive mobile navigation
        expect($content)->toContain('mobileMenuOpen');
        expect($content)->toContain('lg:hidden');
    });

    it('uses semantic color tokens for light and dark mode', function () use ($layoutPath) {
        $content = file_get_contents($layoutPath);
        // BR-161: Light and dark mode support
        expect($content)->toContain('bg-surface');
        expect($content)->toContain('dark:bg-surface');
        expect($content)->toContain('text-on-surface');
        expect($content)->toContain('bg-primary-subtle');
    });

    it('uses x-navigate for SPA navigation', function () use ($layoutPath) {
        $content = file_get_contents($layoutPath);
        expect($content)->toContain('x-navigate');
    });

    it('uses __() for all user-facing text', function () use ($layoutPath) {
        $content = file_get_contents($layoutPath);
        // BR-164: All text localized
        expect($content)->toContain("__('Toggle menu')");
        expect($content)->toContain("__('Expand sidebar')");
        expect($content)->toContain("__('Collapse sidebar')");
        expect($content)->toContain("__('Logout')");
        expect($content)->toContain("__('Cook dashboard navigation')");
    });

    it('has active state indicator with left border', function () use ($layoutPath) {
        $content = file_get_contents($layoutPath);
        // UI/UX: Active nav highlighted with left border indicator
        expect($content)->toContain('bg-primary rounded-r-full');
        expect($content)->toContain('aria-current="page"');
    });

    it('includes theme and language switchers', function () use ($layoutPath) {
        $content = file_get_contents($layoutPath);
        expect($content)->toContain('<x-theme-switcher');
        expect($content)->toContain('<x-language-switcher');
    });

    it('includes logout form with x-navigate-skip', function () use ($layoutPath) {
        $content = file_get_contents($layoutPath);
        // F-025: Logout uses x-navigate-skip for full page reload
        expect($content)->toContain('x-navigate-skip');
        expect($content)->toContain("route('logout')");
    });
});

// Test group: Setup banner component
describe('Cook setup banner component', function () use ($projectRoot) {
    $bannerPath = $projectRoot.'/resources/views/components/cook/setup-banner.blade.php';

    it('exists', function () use ($bannerPath) {
        expect(file_exists($bannerPath))->toBeTrue();
    });

    it('checks tenant setup status', function () use ($bannerPath) {
        $content = file_get_contents($bannerPath);
        expect($content)->toContain('isSetupComplete()');
    });

    it('links to setup wizard', function () use ($bannerPath) {
        $content = file_get_contents($bannerPath);
        expect($content)->toContain('/dashboard/setup');
    });

    it('uses warning color tokens', function () use ($bannerPath) {
        $content = file_get_contents($bannerPath);
        expect($content)->toContain('bg-warning-subtle');
        expect($content)->toContain('border-warning');
        expect($content)->toContain('text-warning');
    });

    it('contains localized text', function () use ($bannerPath) {
        $content = file_get_contents($bannerPath);
        expect($content)->toContain("__('Complete your setup to go live')");
        expect($content)->toContain("__('Start Setup')");
    });
});

// Test group: Cook permissions exist in seeder
describe('Cook navigation permissions', function () use ($projectRoot) {
    it('all referenced permissions exist in the seeder', function () use ($projectRoot) {
        $seederContent = file_get_contents($projectRoot.'/database/seeders/RoleAndPermissionSeeder.php');
        $navPermissions = [
            'can-manage-meals',
            'can-manage-orders',
            'can-manage-locations',
            'can-manage-schedules',
            'can-manage-brand',
            'can-manage-managers',
            'can-view-cook-analytics',
            'can-manage-cook-wallet',
            'can-manage-testimonials',
            'can-manage-cook-settings',
        ];
        foreach ($navPermissions as $permission) {
            expect($seederContent)->toContain("'$permission'");
        }
    });
});

// Test group: Route configuration
describe('Cook dashboard routes', function () use ($projectRoot) {
    it('uses cook.access middleware', function () use ($projectRoot) {
        $routesContent = file_get_contents($projectRoot.'/routes/web.php');
        expect($routesContent)->toContain("'cook.access'");
    });

    it('has dashboard route within tenant.domain middleware group', function () use ($projectRoot) {
        $routesContent = file_get_contents($projectRoot.'/routes/web.php');
        expect($routesContent)->toContain("middleware('tenant.domain')");
        expect($routesContent)->toContain("name('cook.dashboard')");
    });
});

// Test group: DashboardController
describe('DashboardController::cookDashboard', function () use ($projectRoot) {
    it('passes tenant and setupComplete to view', function () use ($projectRoot) {
        $controllerContent = file_get_contents($projectRoot.'/app/Http/Controllers/DashboardController.php');
        expect($controllerContent)->toContain('isSetupComplete()');
        expect($controllerContent)->toContain("'setupComplete'");
        expect($controllerContent)->toContain("gale()->view('cook.dashboard'");
    });
});

// Test group: Translations
describe('Cook dashboard translations', function () use ($projectRoot) {
    it('has all new keys in English', function () use ($projectRoot) {
        $en = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
        $keys = [
            'Business', 'Coverage', 'Engagement',
            'Complete your setup to go live',
            'Your store is not yet visible to customers. Complete the setup wizard to start receiving orders.',
            'Start Setup',
        ];
        foreach ($keys as $key) {
            expect($en)->toHaveKey($key);
        }
    });

    it('has all new keys in French', function () use ($projectRoot) {
        $fr = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);
        $keys = [
            'Business', 'Coverage', 'Engagement',
            'Complete your setup to go live',
            'Your store is not yet visible to customers. Complete the setup wizard to start receiving orders.',
            'Start Setup',
        ];
        foreach ($keys as $key) {
            expect($fr)->toHaveKey($key);
        }
    });
});
