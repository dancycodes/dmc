<?php

use App\Services\PwaService;

beforeEach(function () {
    $this->pwaService = new PwaService;
});

describe('PwaService install prompt constants', function () {
    test('dismiss storage key is defined', function () {
        expect(PwaService::DISMISS_STORAGE_KEY)->toBe('dmc-pwa-dismissed');
    });

    test('installed storage key is defined', function () {
        expect(PwaService::INSTALLED_STORAGE_KEY)->toBe('dmc-pwa-installed');
    });

    test('prompt delay is 3 seconds', function () {
        expect(PwaService::PROMPT_DELAY_MS)->toBe(3000);
    });
});

describe('getInstallPromptAlpineData', function () {
    test('returns a non-empty string', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toBeString()
            ->and($data)->not->toBeEmpty();
    });

    test('contains Alpine data object structure', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain('showBanner')
            ->and($data)->toContain('deferredPrompt')
            ->and($data)->toContain('isIos')
            ->and($data)->toContain('isStandalone')
            ->and($data)->toContain('wasDismissed');
    });

    test('contains init method', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain('init()');
    });

    test('contains installApp method', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain('installApp()');
    });

    test('contains dismissPrompt method', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain('dismissPrompt()');
    });

    test('checks standalone display mode', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain('display-mode: standalone');
    });

    test('detects iOS devices', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain('iPad|iPhone|iPod');
    });

    test('listens for beforeinstallprompt event', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain('beforeinstallprompt');
    });

    test('listens for appinstalled event', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain('appinstalled');
    });

    test('uses correct dismiss storage key', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain(PwaService::DISMISS_STORAGE_KEY);
    });

    test('uses correct installed storage key', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain(PwaService::INSTALLED_STORAGE_KEY);
    });

    test('uses correct prompt delay', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain((string) PwaService::PROMPT_DELAY_MS);
    });

    test('uses sessionStorage for dismissal tracking', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain('sessionStorage.setItem')
            ->and($data)->toContain('sessionStorage.getItem');
    });

    test('uses localStorage for installed tracking', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain('localStorage.setItem')
            ->and($data)->toContain('localStorage.getItem');
    });

    test('calls prompt on deferred event', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain('this.deferredPrompt.prompt()');
    });

    test('checks userChoice outcome', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain('userChoice')
            ->and($data)->toContain("outcome === 'accepted'");
    });

    test('hides banner on dismiss', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        // dismissPrompt sets showBanner to false
        expect($data)->toContain('this.showBanner = false');
    });

    test('checks navigator.standalone for iOS', function () {
        $data = $this->pwaService->getInstallPromptAlpineData();

        expect($data)->toContain('window.navigator.standalone');
    });
});
