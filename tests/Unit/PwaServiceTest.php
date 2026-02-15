<?php

use App\Services\PwaService;

beforeEach(function () {
    $this->pwaService = new PwaService;
});

describe('PwaService constants', function () {
    test('manifest path is correct', function () {
        expect(PwaService::MANIFEST_PATH)->toBe('/manifest.json');
    });

    test('service worker path is correct', function () {
        expect(PwaService::SERVICE_WORKER_PATH)->toBe('/service-worker.js');
    });

    test('offline page path is correct', function () {
        expect(PwaService::OFFLINE_PATH)->toBe('/offline.html');
    });

    test('theme color is DancyMeals teal', function () {
        expect(PwaService::THEME_COLOR)->toBe('#0D9488');
    });

    test('background color is white', function () {
        expect(PwaService::BACKGROUND_COLOR)->toBe('#FFFFFF');
    });
});

describe('getManifestData', function () {
    test('returns correct app name', function () {
        $data = $this->pwaService->getManifestData();

        expect($data['name'])->toBe('DancyMeals')
            ->and($data['short_name'])->toBe('DancyMeals');
    });

    test('returns standalone display mode', function () {
        $data = $this->pwaService->getManifestData();

        expect($data['display'])->toBe('standalone');
    });

    test('returns correct start url', function () {
        $data = $this->pwaService->getManifestData();

        expect($data['start_url'])->toBe('/');
    });

    test('returns correct theme and background colors', function () {
        $data = $this->pwaService->getManifestData();

        expect($data['theme_color'])->toBe('#0D9488')
            ->and($data['background_color'])->toBe('#FFFFFF');
    });

    test('includes all required manifest fields', function () {
        $data = $this->pwaService->getManifestData();

        expect($data)->toHaveKeys([
            'name',
            'short_name',
            'start_url',
            'display',
            'theme_color',
            'background_color',
            'icons',
        ]);
    });

    test('includes icon definitions', function () {
        $data = $this->pwaService->getManifestData();

        expect($data['icons'])->toBeArray()
            ->and($data['icons'])->toHaveCount(4);
    });
});

describe('getIconDefinitions', function () {
    test('includes 192x192 icon', function () {
        $icons = $this->pwaService->getIconDefinitions();

        $sizes = array_column($icons, 'sizes');
        expect($sizes)->toContain('192x192');
    });

    test('includes 512x512 icon', function () {
        $icons = $this->pwaService->getIconDefinitions();

        $sizes = array_column($icons, 'sizes');
        expect($sizes)->toContain('512x512');
    });

    test('includes maskable icons', function () {
        $icons = $this->pwaService->getIconDefinitions();

        $maskable = array_filter($icons, fn ($icon) => ($icon['purpose'] ?? null) === 'maskable');
        expect($maskable)->toHaveCount(2);
    });

    test('all icons are PNG format', function () {
        $icons = $this->pwaService->getIconDefinitions();

        foreach ($icons as $icon) {
            expect($icon['type'])->toBe('image/png');
        }
    });

    test('all icon paths start with /icons/', function () {
        $icons = $this->pwaService->getIconDefinitions();

        foreach ($icons as $icon) {
            expect($icon['src'])->toStartWith('/icons/');
        }
    });
});

describe('getMetaTags', function () {
    test('includes manifest link', function () {
        $tags = $this->pwaService->getMetaTags();

        expect($tags)->toContain('<link rel="manifest" href="/manifest.json">');
    });

    test('includes theme color meta tag', function () {
        $tags = $this->pwaService->getMetaTags();

        expect($tags)->toContain('<meta name="theme-color" content="#0D9488">');
    });

    test('includes apple mobile web app meta tags', function () {
        $tags = $this->pwaService->getMetaTags();

        expect($tags)->toContain('apple-mobile-web-app-capable')
            ->and($tags)->toContain('apple-mobile-web-app-title');
    });

    test('includes apple touch icon', function () {
        $tags = $this->pwaService->getMetaTags();

        expect($tags)->toContain('<link rel="apple-touch-icon" href="/icons/icon-192x192.png">');
    });
});

describe('getRegistrationScript', function () {
    test('includes service worker feature detection', function () {
        $script = $this->pwaService->getRegistrationScript();

        expect($script)->toContain("'serviceWorker' in navigator");
    });

    test('registers the correct service worker path', function () {
        $script = $this->pwaService->getRegistrationScript();

        expect($script)->toContain("register('/service-worker.js')");
    });

    test('registers on window load event', function () {
        $script = $this->pwaService->getRegistrationScript();

        expect($script)->toContain("window.addEventListener('load'");
    });

    test('handles registration failure gracefully', function () {
        $script = $this->pwaService->getRegistrationScript();

        expect($script)->toContain('.catch(');
    });
});
