<?php

use App\Http\Controllers\DashboardController;
use App\Services\ThemeService;

uses(Tests\TestCase::class);

test('DashboardController exists and has required methods', function () {
    $controller = new DashboardController;

    expect($controller)->toBeInstanceOf(DashboardController::class)
        ->and(method_exists($controller, 'home'))->toBeTrue()
        ->and(method_exists($controller, 'adminDashboard'))->toBeTrue()
        ->and(method_exists($controller, 'cookDashboard'))->toBeTrue()
        ->and(method_exists($controller, 'tenantHome'))->toBeTrue();
});

test('all four layout blade files exist', function () {
    $layouts = [
        'resources/views/layouts/app.blade.php',
        'resources/views/layouts/main-public.blade.php',
        'resources/views/layouts/tenant-public.blade.php',
        'resources/views/layouts/admin.blade.php',
        'resources/views/layouts/cook-dashboard.blade.php',
    ];

    foreach ($layouts as $layout) {
        expect(file_exists(base_path($layout)))->toBeTrue("Missing layout: {$layout}");
    }
});

test('layout blade files contain required elements', function () {
    // app.blade.php should have FOIT prevention, @gale, PWA meta tags
    $appLayout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    expect($appLayout)
        ->toContain('ThemeService')
        ->toContain('@gale')
        ->toContain('PwaService')
        ->toContain('loading-bar');
});

test('main-public layout contains DancyMeals branding', function () {
    $layout = file_get_contents(base_path('resources/views/layouts/main-public.blade.php'));

    expect($layout)
        ->toContain("config('app.name'")
        ->toContain('x-theme-switcher')
        ->toContain('x-language-switcher')
        ->toContain('x-nav.notification-bell')
        ->toContain('x-navigate');
});

test('tenant-public layout references tenant branding', function () {
    $layout = file_get_contents(base_path('resources/views/layouts/tenant-public.blade.php'));

    expect($layout)
        ->toContain('tenant()')
        ->toContain('tenantName')
        ->toContain('x-theme-switcher')
        ->toContain('x-language-switcher')
        ->toContain('x-navigate');
});

test('admin layout has sidebar navigation', function () {
    $layout = file_get_contents(base_path('resources/views/layouts/admin.blade.php'));

    expect($layout)
        ->toContain('sidebarCollapsed')
        ->toContain('vault-entry')
        ->toContain('x-navigate')
        ->toContain("__('Tenants')")
        ->toContain("__('Users')")
        ->toContain("__('Roles')");
});

test('cook-dashboard layout has cook-specific navigation', function () {
    $layout = file_get_contents(base_path('resources/views/layouts/cook-dashboard.blade.php'));

    expect($layout)
        ->toContain('sidebarCollapsed')
        ->toContain('tenant()')
        ->toContain('x-navigate')
        ->toContain("__('Meals')")
        ->toContain("__('Orders')")
        ->toContain("__('Brand')");
});

test('all layouts use localized text via __() helper', function () {
    $layouts = [
        'resources/views/layouts/main-public.blade.php',
        'resources/views/layouts/tenant-public.blade.php',
        'resources/views/layouts/admin.blade.php',
        'resources/views/layouts/cook-dashboard.blade.php',
    ];

    foreach ($layouts as $layoutPath) {
        $content = file_get_contents(base_path($layoutPath));

        // Check that navigation text uses __() helper
        expect($content)->toContain("__('")->not->toContain('hardcoded nav text without translation');
    }
});

test('navigation component blade files exist', function () {
    $components = [
        'resources/views/components/nav/loading-bar.blade.php',
        'resources/views/components/nav/notification-bell.blade.php',
    ];

    foreach ($components as $component) {
        expect(file_exists(base_path($component)))->toBeTrue("Missing component: {$component}");
    }
});

test('loading bar component uses $gale.loading', function () {
    $content = file_get_contents(base_path('resources/views/components/nav/loading-bar.blade.php'));

    expect($content)->toContain('$gale.loading');
});

test('ThemeService provides FOIT prevention script', function () {
    $service = app(ThemeService::class);

    $script = $service->getInlineScript();

    expect($script)
        ->toContain('localStorage')
        ->toContain('data-theme')
        ->toContain('dmc-theme');
});
