<?php

declare(strict_types=1);

/**
 * Unit tests for F-019: Rate Limiting Setup
 *
 * Tests configuration files, template content, and translation keys
 * without requiring database access or full application boot.
 */
$basePath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR;
$viewPath = $basePath.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR;

describe('Rate Limiter Definitions in AppServiceProvider', function () use ($basePath) {
    it('defines rate limiters in AppServiceProvider boot method (BR-158)', function () use ($basePath) {
        $providerContent = file_get_contents($basePath.'app'.DIRECTORY_SEPARATOR.'Providers'.DIRECTORY_SEPARATOR.'AppServiceProvider.php');

        expect($providerContent)->toContain("RateLimiter::for('strict'");
        expect($providerContent)->toContain("RateLimiter::for('moderate'");
        expect($providerContent)->toContain("RateLimiter::for('generous'");
    });

    it('imports RateLimiter facade', function () use ($basePath) {
        $providerContent = file_get_contents($basePath.'app'.DIRECTORY_SEPARATOR.'Providers'.DIRECTORY_SEPARATOR.'AppServiceProvider.php');

        expect($providerContent)->toContain('use Illuminate\Support\Facades\RateLimiter;');
    });

    it('imports Limit class', function () use ($basePath) {
        $providerContent = file_get_contents($basePath.'app'.DIRECTORY_SEPARATOR.'Providers'.DIRECTORY_SEPARATOR.'AppServiceProvider.php');

        expect($providerContent)->toContain('use Illuminate\Cache\RateLimiting\Limit;');
    });

    it('uses Limit::perMinute for all three tiers', function () use ($basePath) {
        $providerContent = file_get_contents($basePath.'app'.DIRECTORY_SEPARATOR.'Providers'.DIRECTORY_SEPARATOR.'AppServiceProvider.php');

        // Strict: 5/min
        expect($providerContent)->toContain('Limit::perMinute(5)');
        // Moderate: 60/min
        expect($providerContent)->toContain('Limit::perMinute(60)');
        // Generous: 120/min
        expect($providerContent)->toContain('Limit::perMinute(120)');
    });

    it('uses IP-based keying for strict limiter (BR-155)', function () use ($basePath) {
        $providerContent = file_get_contents($basePath.'app'.DIRECTORY_SEPARATOR.'Providers'.DIRECTORY_SEPARATOR.'AppServiceProvider.php');

        // Strict limiter should key by IP only
        expect($providerContent)->toMatch("/RateLimiter::for\('strict'.*?->by\(.*?->ip\(\)/s");
    });

    it('uses user-or-IP keying for moderate limiter (BR-155, BR-156)', function () use ($basePath) {
        $providerContent = file_get_contents($basePath.'app'.DIRECTORY_SEPARATOR.'Providers'.DIRECTORY_SEPARATOR.'AppServiceProvider.php');

        // Moderate limiter should key by user ID or IP
        expect($providerContent)->toMatch("/RateLimiter::for\('moderate'.*?user\(\)\?->id.*?->ip\(\)/s");
    });

    it('centralizes all rate limiter configuration in a single method (BR-158)', function () use ($basePath) {
        $providerContent = file_get_contents($basePath.'app'.DIRECTORY_SEPARATOR.'Providers'.DIRECTORY_SEPARATOR.'AppServiceProvider.php');

        expect($providerContent)->toContain('configureRateLimiting');
    });
});

describe('429 Error Page Template', function () use ($viewPath) {
    it('has a custom 429.blade.php error page', function () use ($viewPath) {
        expect(file_exists($viewPath.'429.blade.php'))->toBeTrue();
    });

    it('contains DancyMeals branding', function () use ($viewPath) {
        $template = file_get_contents($viewPath.'429.blade.php');

        expect($template)->toContain("config('app.name', 'DancyMeals')");
    });

    it('uses localized strings with __() helper (BR-157)', function () use ($viewPath) {
        $template = file_get_contents($viewPath.'429.blade.php');

        expect($template)->toContain("__('Too Many Requests')");
        expect($template)->toContain("__(\"You're making requests a bit too quickly. Please wait a moment and try again.\")");
        expect($template)->toContain("__('You can try again in')");
        expect($template)->toContain("__('seconds')");
        expect($template)->toContain("__('Go Home')");
        expect($template)->toContain("__('Try Again')");
    });

    it('supports dark mode via data-theme attribute and dmc-theme localStorage', function () use ($viewPath) {
        $template = file_get_contents($viewPath.'429.blade.php');

        expect($template)->toContain('data-theme="light"');
        expect($template)->toContain('dmc-theme');
        expect($template)->toContain('prefers-color-scheme: dark');
    });

    it('uses semantic color tokens (not hardcoded colors)', function () use ($viewPath) {
        $template = file_get_contents($viewPath.'429.blade.php');

        expect($template)->toContain('bg-surface');
        expect($template)->toContain('text-on-surface');
        expect($template)->toContain('bg-primary');
        expect($template)->toContain('text-on-primary');
        expect($template)->toContain('bg-warning-subtle');
        expect($template)->toContain('border-outline');
    });

    it('includes a retry countdown using Retry-After header (BR-154)', function () use ($viewPath) {
        $template = file_get_contents($viewPath.'429.blade.php');

        expect($template)->toContain('Retry-After');
        expect($template)->toContain('x-data');
        expect($template)->toContain('seconds');
    });

    it('has a Go Home link and Try Again button', function () use ($viewPath) {
        $template = file_get_contents($viewPath.'429.blade.php');

        expect($template)->toContain("url('/')");
        expect($template)->toContain('window.location.reload()');
    });

    it('includes Vite assets for styling', function () use ($viewPath) {
        $template = file_get_contents($viewPath.'429.blade.php');

        expect($template)->toContain('@vite');
    });
});

describe('Route File Middleware Assignments', function () use ($basePath) {
    it('applies strict throttle to auth POST routes in web.php', function () use ($basePath) {
        $routeContent = file_get_contents($basePath.'routes'.DIRECTORY_SEPARATOR.'web.php');

        // Auth POST routes should have throttle:strict
        expect($routeContent)->toContain("'throttle:strict'");
    });

    it('applies moderate throttle to authenticated route groups', function () use ($basePath) {
        $routeContent = file_get_contents($basePath.'routes'.DIRECTORY_SEPARATOR.'web.php');

        expect($routeContent)->toContain("'throttle:moderate'");
    });

    it('applies generous throttle via web middleware group in bootstrap/app.php', function () use ($basePath) {
        $bootstrapContent = file_get_contents($basePath.'bootstrap'.DIRECTORY_SEPARATOR.'app.php');

        expect($bootstrapContent)->toContain('ThrottleRequests');
        expect($bootstrapContent)->toContain('generous');
    });
});

describe('Translation Keys', function () use ($basePath) {
    it('has English translations for 429 page strings', function () use ($basePath) {
        $enJson = json_decode(file_get_contents($basePath.'lang'.DIRECTORY_SEPARATOR.'en.json'), true);

        expect($enJson)->toHaveKey('Too Many Requests');
        expect($enJson)->toHaveKey("You're making requests a bit too quickly. Please wait a moment and try again.");
        expect($enJson)->toHaveKey('You can try again in');
        expect($enJson)->toHaveKey('seconds');
        expect($enJson)->toHaveKey('Go Home');
    });

    it('has French translations for 429 page strings', function () use ($basePath) {
        $frJson = json_decode(file_get_contents($basePath.'lang'.DIRECTORY_SEPARATOR.'fr.json'), true);

        expect($frJson)->toHaveKey('Too Many Requests');
        expect($frJson)->toHaveKey("You're making requests a bit too quickly. Please wait a moment and try again.");
        expect($frJson)->toHaveKey('You can try again in');
        expect($frJson)->toHaveKey('seconds');
        expect($frJson)->toHaveKey('Go Home');
    });
});
