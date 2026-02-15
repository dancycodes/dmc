<?php

namespace App\Services;

class ThemeService
{
    /**
     * Valid theme preference values.
     */
    public const VALID_THEMES = ['light', 'dark', 'system'];

    /**
     * The default theme mode for new users.
     */
    public const DEFAULT_THEME = 'system';

    /**
     * The localStorage key used by the frontend.
     */
    public const STORAGE_KEY = 'dmc-theme';

    /**
     * Check if a given theme value is valid.
     */
    public function isValidTheme(?string $theme): bool
    {
        if ($theme === null) {
            return true;
        }

        return in_array($theme, self::VALID_THEMES, true);
    }

    /**
     * Normalize a theme value. Returns null for 'system' (DB default).
     */
    public function normalizeTheme(?string $theme): ?string
    {
        if ($theme === null || $theme === 'system') {
            return null;
        }

        if (! in_array($theme, self::VALID_THEMES, true)) {
            return null;
        }

        return $theme;
    }

    /**
     * Get the resolved theme for display purposes.
     * Returns 'system' when the DB value is null.
     */
    public function resolvePreference(?string $dbValue): string
    {
        return $dbValue ?? self::DEFAULT_THEME;
    }

    /**
     * Get the data-theme attribute value for server-rendered HTML.
     * For 'system' or null, defaults to 'light' (JS will correct).
     */
    public function getDataThemeAttribute(?string $preference): string
    {
        if ($preference === null || $preference === 'system') {
            return 'light';
        }

        return in_array($preference, ['light', 'dark'], true) ? $preference : 'light';
    }

    /**
     * Get the inline script for FOIT (Flash of Incorrect Theme) prevention.
     * This must be placed in <head> before any CSS is loaded.
     */
    public function getInlineScript(): string
    {
        $storageKey = self::STORAGE_KEY;

        return <<<JS
(function(){try{var s=localStorage.getItem('{$storageKey}');var t='light';if(s==='dark'){t='dark'}else if(s==='light'){t='light'}else{if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){t='dark'}}document.documentElement.setAttribute('data-theme',t)}catch(e){if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.documentElement.setAttribute('data-theme','dark')}}})();
JS;
    }

    /**
     * Get the Alpine.js theme manager initialization code.
     * Handles system preference detection and real-time OS theme changes.
     */
    public function getAlpineInitScript(): string
    {
        $storageKey = self::STORAGE_KEY;

        return <<<JS
{
    preference: localStorage.getItem('{$storageKey}') || 'system',
    applied: document.documentElement.getAttribute('data-theme') || 'light',
    mediaQuery: null,

    init() {
        this.mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        this.applyTheme();
        this.mediaQuery.addEventListener('change', () => {
            if (this.preference === 'system') {
                this.applyTheme();
            }
        });
    },

    applyTheme() {
        let resolved = this.preference;
        if (resolved === 'system') {
            resolved = (this.mediaQuery && this.mediaQuery.matches) ? 'dark' : 'light';
        }
        this.applied = resolved;
        document.documentElement.setAttribute('data-theme', resolved);
    },

    setTheme(theme) {
        this.preference = theme;
        try { localStorage.setItem('{$storageKey}', theme); } catch(e) {}
        this.applyTheme();
    }
}
JS;
    }
}
