<?php

namespace App\Services;

class PwaService
{
    /**
     * The manifest file path relative to public directory.
     */
    public const MANIFEST_PATH = '/manifest.json';

    /**
     * The service worker file path relative to public directory.
     */
    public const SERVICE_WORKER_PATH = '/service-worker.js';

    /**
     * The offline page path relative to public directory.
     */
    public const OFFLINE_PATH = '/offline.html';

    /**
     * DancyMeals brand theme color (teal-600).
     */
    public const THEME_COLOR = '#0D9488';

    /**
     * DancyMeals background color for splash screen.
     */
    public const BACKGROUND_COLOR = '#FFFFFF';

    /**
     * Get the manifest data as an array.
     *
     * @return array<string, mixed>
     */
    public function getManifestData(): array
    {
        return [
            'name' => 'DancyMeals',
            'short_name' => 'DancyMeals',
            'description' => 'Your favorite home-cooked meals, delivered.',
            'start_url' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'theme_color' => self::THEME_COLOR,
            'background_color' => self::BACKGROUND_COLOR,
            'lang' => 'en',
            'categories' => ['food', 'shopping'],
            'icons' => $this->getIconDefinitions(),
        ];
    }

    /**
     * Get the icon definitions for the manifest.
     *
     * @return array<int, array{src: string, sizes: string, type: string, purpose?: string}>
     */
    public function getIconDefinitions(): array
    {
        return [
            [
                'src' => '/icons/icon-192x192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
            ],
            [
                'src' => '/icons/icon-512x512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
            ],
            [
                'src' => '/icons/icon-maskable-192x192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
            [
                'src' => '/icons/icon-maskable-512x512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
        ];
    }

    /**
     * Get the HTML meta tags for PWA support.
     * These should be included in the <head> of all layouts.
     */
    public function getMetaTags(): string
    {
        $manifestPath = self::MANIFEST_PATH;
        $themeColor = self::THEME_COLOR;

        return <<<HTML
<link rel="manifest" href="{$manifestPath}">
<meta name="theme-color" content="{$themeColor}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="DancyMeals">
<link rel="apple-touch-icon" href="/icons/icon-192x192.png">
HTML;
    }

    /**
     * Get the service worker registration script.
     * This script should be included at the end of <body> or in a <script> tag.
     */
    public function getRegistrationScript(): string
    {
        $swPath = self::SERVICE_WORKER_PATH;

        return <<<JS
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('{$swPath}').then(function(registration) {
            // Service worker registered successfully
        }).catch(function(error) {
            // Service worker registration failed — app works normally without it
            console.warn('ServiceWorker registration failed:', error);
        });
    });
}
JS;
    }

    /**
     * Check if all required PWA files exist in the public directory.
     *
     * @return array{manifest: bool, service_worker: bool, offline: bool, icons: array<string, bool>}
     */
    public function checkInstallation(): array
    {
        $publicPath = public_path();

        $iconFiles = [
            'icon-192x192.png',
            'icon-512x512.png',
            'icon-maskable-192x192.png',
            'icon-maskable-512x512.png',
        ];

        $icons = [];
        foreach ($iconFiles as $icon) {
            $icons[$icon] = file_exists($publicPath.'/icons/'.$icon);
        }

        return [
            'manifest' => file_exists($publicPath.self::MANIFEST_PATH),
            'service_worker' => file_exists($publicPath.self::SERVICE_WORKER_PATH),
            'offline' => file_exists($publicPath.self::OFFLINE_PATH),
            'icons' => $icons,
        ];
    }

    /**
     * Check if all required PWA assets are installed.
     */
    public function isFullyInstalled(): bool
    {
        $check = $this->checkInstallation();

        if (! $check['manifest'] || ! $check['service_worker'] || ! $check['offline']) {
            return false;
        }

        foreach ($check['icons'] as $exists) {
            if (! $exists) {
                return false;
            }
        }

        return true;
    }

    /**
     * The localStorage key for tracking PWA install prompt dismissal.
     */
    public const DISMISS_STORAGE_KEY = 'dmc-pwa-dismissed';

    /**
     * The localStorage key for tracking whether the PWA is installed.
     */
    public const INSTALLED_STORAGE_KEY = 'dmc-pwa-installed';

    /**
     * Delay in milliseconds before showing the install prompt.
     */
    public const PROMPT_DELAY_MS = 3000;

    /**
     * Get the Alpine.js data object for the PWA install prompt component.
     * Handles beforeinstallprompt capture, iOS detection, dismissal tracking,
     * and standalone mode detection.
     */
    public function getInstallPromptAlpineData(): string
    {
        $dismissKey = self::DISMISS_STORAGE_KEY;
        $installedKey = self::INSTALLED_STORAGE_KEY;
        $delayMs = self::PROMPT_DELAY_MS;

        return <<<JS
{
    showBanner: false,
    deferredPrompt: null,
    isIos: false,
    isStandalone: false,
    wasDismissed: false,

    init() {
        this.isStandalone = window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;

        if (this.isStandalone) {
            try { localStorage.setItem('{$installedKey}', 'true'); } catch(e) {}
            return;
        }

        try {
            if (localStorage.getItem('{$installedKey}') === 'true') {
                return;
            }
            if (sessionStorage.getItem('{$dismissKey}') === 'true') {
                this.wasDismissed = true;
                return;
            }
        } catch(e) {}

        var ua = navigator.userAgent || '';
        this.isIos = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;

        if (this.isIos) {
            var isSafari = /Safari/.test(ua) && !/CriOS|FxiOS|Chrome/.test(ua);
            if (isSafari) {
                setTimeout(() => { this.showBanner = true; }, {$delayMs});
            }
            return;
        }

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            if (!this.wasDismissed) {
                setTimeout(() => { this.showBanner = true; }, {$delayMs});
            }
        });

        window.addEventListener('appinstalled', () => {
            this.showBanner = false;
            this.deferredPrompt = null;
            try { localStorage.setItem('{$installedKey}', 'true'); } catch(e) {}
        });
    },

    async installApp() {
        if (this.isIos) {
            return;
        }
        if (!this.deferredPrompt) {
            return;
        }
        this.deferredPrompt.prompt();
        var result = await this.deferredPrompt.userChoice;
        if (result.outcome === 'accepted') {
            try { localStorage.setItem('{$installedKey}', 'true'); } catch(e) {}
        }
        this.deferredPrompt = null;
        this.showBanner = false;
    },

    dismissPrompt() {
        this.showBanner = false;
        this.wasDismissed = true;
        try { sessionStorage.setItem('{$dismissKey}', 'true'); } catch(e) {}
    }
}
JS;
    }
}
