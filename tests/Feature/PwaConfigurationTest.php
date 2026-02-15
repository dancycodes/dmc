<?php

describe('PWA Manifest', function () {
    test('manifest.json exists in public directory', function () {
        expect(file_exists(public_path('manifest.json')))->toBeTrue();
    });

    test('manifest.json is valid JSON', function () {
        $content = file_get_contents(public_path('manifest.json'));
        $manifest = json_decode($content, true);

        expect($manifest)->not->toBeNull();
    });

    test('manifest contains required fields', function () {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);

        expect($manifest)->toHaveKeys([
            'name',
            'short_name',
            'start_url',
            'display',
            'theme_color',
            'background_color',
            'icons',
        ]);
    });

    test('manifest has correct app name on all domains', function () {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);

        expect($manifest['name'])->toBe('DancyMeals')
            ->and($manifest['short_name'])->toBe('DancyMeals');
    });

    test('manifest has standalone display mode', function () {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);

        expect($manifest['display'])->toBe('standalone');
    });

    test('manifest icons include required sizes', function () {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);

        $sizes = array_column($manifest['icons'], 'sizes');

        expect($sizes)->toContain('192x192')
            ->and($sizes)->toContain('512x512');
    });

    test('manifest has correct theme and background colors', function () {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);

        expect($manifest['theme_color'])->toBe('#0D9488')
            ->and($manifest['background_color'])->toBe('#FFFFFF');
    });
});

describe('PWA Service Worker', function () {
    test('service-worker.js exists in public directory', function () {
        expect(file_exists(public_path('service-worker.js')))->toBeTrue();
    });

    test('service worker contains offline cache setup', function () {
        $content = file_get_contents(public_path('service-worker.js'));

        expect($content)->toContain('CACHE_NAME')
            ->and($content)->toContain('offline.html')
            ->and($content)->toContain("self.addEventListener('install'")
            ->and($content)->toContain("self.addEventListener('fetch'");
    });

    test('service worker only intercepts navigation requests', function () {
        $content = file_get_contents(public_path('service-worker.js'));

        expect($content)->toContain("event.request.mode !== 'navigate'");
    });

    test('service worker pre-caches offline page during install', function () {
        $content = file_get_contents(public_path('service-worker.js'));

        expect($content)->toContain('cache.add')
            ->and($content)->toContain('OFFLINE_URL');
    });

    test('service worker cleans up old caches on activate', function () {
        $content = file_get_contents(public_path('service-worker.js'));

        expect($content)->toContain("self.addEventListener('activate'")
            ->and($content)->toContain('caches.delete');
    });
});

describe('PWA Offline Page', function () {
    test('offline.html exists in public directory', function () {
        expect(file_exists(public_path('offline.html')))->toBeTrue();
    });

    test('offline page contains DancyMeals branding', function () {
        $content = file_get_contents(public_path('offline.html'));

        expect($content)->toContain('DancyMeals');
    });

    test('offline page contains try again button', function () {
        $content = file_get_contents(public_path('offline.html'));

        expect($content)->toContain('Try Again')
            ->and($content)->toContain('window.location.reload()');
    });

    test('offline page supports light and dark mode', function () {
        $content = file_get_contents(public_path('offline.html'));

        expect($content)->toContain('prefers-color-scheme: dark')
            ->and($content)->toContain('data-theme="dark"');
    });

    test('offline page has localization support for English and French', function () {
        $content = file_get_contents(public_path('offline.html'));

        expect($content)->toContain('en:')
            ->and($content)->toContain('fr:')
            ->and($content)->toContain('navigator.language');
    });

    test('offline page is fully self-contained with no external resources', function () {
        $content = file_get_contents(public_path('offline.html'));

        // Should not have external stylesheet or script references
        expect($content)->not->toContain('<link rel="stylesheet" href')
            ->and($content)->not->toContain('<script src=');
    });

    test('offline page reads theme from localStorage', function () {
        $content = file_get_contents(public_path('offline.html'));

        expect($content)->toContain('dmc-theme')
            ->and($content)->toContain('localStorage');
    });
});

describe('PWA Icon Files', function () {
    test('icon-192x192.png exists', function () {
        expect(file_exists(public_path('icons/icon-192x192.png')))->toBeTrue();
    });

    test('icon-512x512.png exists', function () {
        expect(file_exists(public_path('icons/icon-512x512.png')))->toBeTrue();
    });

    test('maskable icon variants exist', function () {
        expect(file_exists(public_path('icons/icon-maskable-192x192.png')))->toBeTrue()
            ->and(file_exists(public_path('icons/icon-maskable-512x512.png')))->toBeTrue();
    });

    test('icon files are valid PNG images', function () {
        $icon192 = public_path('icons/icon-192x192.png');
        $icon512 = public_path('icons/icon-512x512.png');

        // Check PNG magic bytes
        $bytes192 = file_get_contents($icon192, false, null, 0, 8);
        $bytes512 = file_get_contents($icon512, false, null, 0, 8);

        $pngSignature = "\x89PNG\r\n\x1a\n";
        expect($bytes192)->toBe($pngSignature)
            ->and($bytes512)->toBe($pngSignature);
    });
});

describe('PwaService installation check', function () {
    test('checkInstallation returns correct structure', function () {
        $service = new \App\Services\PwaService;
        $check = $service->checkInstallation();

        expect($check)->toHaveKeys(['manifest', 'service_worker', 'offline', 'icons'])
            ->and($check['icons'])->toBeArray();
    });

    test('checkInstallation detects all existing files', function () {
        $service = new \App\Services\PwaService;
        $check = $service->checkInstallation();

        expect($check['manifest'])->toBeTrue()
            ->and($check['service_worker'])->toBeTrue()
            ->and($check['offline'])->toBeTrue();
    });

    test('isFullyInstalled returns true when all files exist', function () {
        $service = new \App\Services\PwaService;

        expect($service->isFullyInstalled())->toBeTrue();
    });
});

describe('PWA Layout Integration', function () {
    test('auth layout contains manifest link', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('<link rel="manifest" href="/manifest.json">', false);
    });

    test('auth layout contains theme color meta tag', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('<meta name="theme-color" content="#0D9488">', false);
    });

    test('auth layout contains service worker registration', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee("navigator.serviceWorker.register('/service-worker.js')", false);
    });

    test('auth layout contains apple mobile web app meta tags', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('apple-mobile-web-app-capable', false);
        $response->assertSee('apple-mobile-web-app-title', false);
    });

    test('auth layout contains apple touch icon', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('apple-touch-icon', false);
    });
});
