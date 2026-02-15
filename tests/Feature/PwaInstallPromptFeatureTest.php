<?php

describe('PWA Install Prompt Component', function () {
    test('auth layout includes the install prompt component', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('showBanner', false);
        $response->assertSee('deferredPrompt', false);
    });

    test('install prompt contains beforeinstallprompt listener', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('beforeinstallprompt', false);
    });

    test('install prompt contains standalone detection', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('display-mode: standalone', false);
    });

    test('install prompt contains iOS detection', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('iPad|iPhone|iPod', false);
    });

    test('install prompt shows DancyMeals branding', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Install DancyMeals', false);
    });

    test('install prompt contains install button', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('installApp()', false);
    });

    test('install prompt contains dismiss button', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('dismissPrompt()', false);
        $response->assertSee('Not now', false);
    });

    test('install prompt has aria-live for accessibility', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('aria-live="polite"', false);
    });

    test('install prompt uses semantic color tokens', function () {
        $response = $this->get('/login');

        $content = $response->getContent();

        expect($content)->toContain('bg-surface')
            ->and($content)->toContain('bg-primary')
            ->and($content)->toContain('text-on-primary')
            ->and($content)->toContain('text-on-surface-strong')
            ->and($content)->toContain('border-outline');
    });

    test('install prompt supports dark mode', function () {
        $response = $this->get('/login');

        $content = $response->getContent();

        expect($content)->toContain('dark:bg-surface-alt')
            ->and($content)->toContain('dark:hover:bg-surface');
    });

    test('install prompt uses session storage for dismissal', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('sessionStorage', false);
        $response->assertSee('dmc-pwa-dismissed', false);
    });

    test('install prompt uses local storage for installed state', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('dmc-pwa-installed', false);
    });

    test('install prompt references app icon', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('/icons/icon-192x192.png', false);
    });

    test('install prompt has iOS manual instructions', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Add to Home Screen', false);
    });

    test('install prompt has close button with aria label', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('aria-label=', false);
    });
});

describe('PWA Install Prompt Translations', function () {
    test('english translation strings exist', function () {
        $translations = json_decode(file_get_contents(lang_path('en.json')), true);

        expect($translations)->toHaveKey('Install DancyMeals')
            ->and($translations)->toHaveKey('Add DancyMeals to your home screen for quick access.')
            ->and($translations)->toHaveKey('Install')
            ->and($translations)->toHaveKey('Not now');
    });

    test('french translation strings exist', function () {
        $translations = json_decode(file_get_contents(lang_path('fr.json')), true);

        expect($translations)->toHaveKey('Install DancyMeals')
            ->and($translations['Install DancyMeals'])->toBe('Installer DancyMeals')
            ->and($translations)->toHaveKey('Not now')
            ->and($translations['Not now'])->toBe('Pas maintenant');
    });

    test('french translation for add to home screen exists', function () {
        $translations = json_decode(file_get_contents(lang_path('fr.json')), true);

        expect($translations)->toHaveKey('Add DancyMeals to your home screen for quick access.')
            ->and($translations['Add DancyMeals to your home screen for quick access.'])->not->toBeEmpty();
    });

    test('french translation for iOS instructions exists', function () {
        $translations = json_decode(file_get_contents(lang_path('fr.json')), true);

        $key = 'Tap the share button and select "Add to Home Screen".';
        expect($translations)->toHaveKey($key)
            ->and($translations[$key])->not->toBeEmpty();
    });
});
