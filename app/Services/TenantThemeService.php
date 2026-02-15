<?php

namespace App\Services;

use App\Models\Tenant;

class TenantThemeService
{
    /**
     * Tenant settings keys for theme customization.
     */
    public const SETTING_PRESET = 'theme';

    public const SETTING_FONT = 'font';

    public const SETTING_RADIUS = 'border_radius';

    /**
     * Resolve the theme preset for a tenant, falling back to default.
     */
    public function resolvePreset(?Tenant $tenant): string
    {
        if ($tenant === null) {
            return $this->defaultPreset();
        }

        $preset = $this->getThemeSetting($tenant, self::SETTING_PRESET);

        if ($preset === null || ! $this->isValidPreset($preset)) {
            return $this->defaultPreset();
        }

        return $preset;
    }

    /**
     * Resolve the font for a tenant, falling back to default.
     */
    public function resolveFont(?Tenant $tenant): string
    {
        if ($tenant === null) {
            return $this->defaultFont();
        }

        $font = $this->getThemeSetting($tenant, self::SETTING_FONT);

        if ($font === null || ! $this->isValidFont($font)) {
            return $this->defaultFont();
        }

        return $font;
    }

    /**
     * Resolve the border radius for a tenant, falling back to default.
     */
    public function resolveRadius(?Tenant $tenant): string
    {
        if ($tenant === null) {
            return $this->defaultRadius();
        }

        $radius = $this->getThemeSetting($tenant, self::SETTING_RADIUS);

        if ($radius === null || ! $this->isValidRadius($radius)) {
            return $this->defaultRadius();
        }

        return $radius;
    }

    /**
     * Generate the inline CSS style block for a tenant's theme customization.
     * Returns an empty string for null tenant (main domain) or default theme.
     */
    public function generateInlineCss(?Tenant $tenant): string
    {
        if ($tenant === null) {
            return '';
        }

        $preset = $this->resolvePreset($tenant);
        $font = $this->resolveFont($tenant);
        $radius = $this->resolveRadius($tenant);

        $isDefaultPreset = $preset === $this->defaultPreset();
        $isDefaultFont = $font === $this->defaultFont();
        $isDefaultRadius = $radius === $this->defaultRadius();

        // If everything is default, no overrides needed
        if ($isDefaultPreset && $isDefaultFont && $isDefaultRadius) {
            return '';
        }

        $css = '';

        // Light mode overrides
        $lightVars = $this->buildLightModeVars($preset, $font, $radius);
        if (! empty($lightVars)) {
            $css .= ':root { '.$this->formatCssVars($lightVars).' }';
        }

        // Dark mode overrides
        $darkVars = $this->buildDarkModeVars($preset);
        if (! empty($darkVars)) {
            $css .= ' [data-theme="dark"] { '.$this->formatCssVars($darkVars).' }';
        }

        return $css;
    }

    /**
     * Get the Google Fonts link tag HTML for a tenant's selected font.
     * Returns empty string if the font is the default (already loaded by layout).
     */
    public function getFontLinkTag(?Tenant $tenant): string
    {
        if ($tenant === null) {
            return '';
        }

        $font = $this->resolveFont($tenant);

        if ($font === $this->defaultFont()) {
            return '';
        }

        $fontConfig = $this->getFontConfig($font);

        if ($fontConfig === null || empty($fontConfig['google_fonts_url'])) {
            return '';
        }

        return '<link href="'.e($fontConfig['google_fonts_url']).'" rel="stylesheet">';
    }

    /**
     * Get the complete resolved theme configuration for a tenant.
     *
     * @return array{preset: string, font: string, radius: string, preset_label: string, font_label: string, radius_label: string}
     */
    public function resolveThemeConfig(?Tenant $tenant): array
    {
        $preset = $this->resolvePreset($tenant);
        $font = $this->resolveFont($tenant);
        $radius = $this->resolveRadius($tenant);

        $presetConfig = $this->getPresetConfig($preset);
        $fontConfig = $this->getFontConfig($font);
        $radiusConfig = $this->getRadiusConfig($radius);

        return [
            'preset' => $preset,
            'font' => $font,
            'radius' => $radius,
            'preset_label' => $presetConfig['label'] ?? $preset,
            'font_label' => $fontConfig['label'] ?? $font,
            'radius_label' => $radiusConfig['label'] ?? $radius,
        ];
    }

    /**
     * Get all available theme presets.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public function availablePresets(): array
    {
        $presets = config('tenant-themes.presets', []);
        $result = [];

        foreach ($presets as $key => $preset) {
            $result[$key] = [
                'label' => $preset['label'] ?? $key,
                'description' => $preset['description'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * Get all available fonts.
     *
     * @return array<string, array{label: string, category: string}>
     */
    public function availableFonts(): array
    {
        $fonts = config('tenant-themes.fonts', []);
        $result = [];

        foreach ($fonts as $key => $font) {
            $result[$key] = [
                'label' => $font['label'] ?? $key,
                'category' => $font['category'] ?? 'sans-serif',
            ];
        }

        return $result;
    }

    /**
     * Get all available border radius options.
     *
     * @return array<string, array{label: string, value: string, description: string}>
     */
    public function availableRadii(): array
    {
        return config('tenant-themes.radii', []);
    }

    /**
     * Check if a preset name is valid.
     */
    public function isValidPreset(string $preset): bool
    {
        return array_key_exists($preset, config('tenant-themes.presets', []));
    }

    /**
     * Check if a font name is valid.
     */
    public function isValidFont(string $font): bool
    {
        return array_key_exists($font, config('tenant-themes.fonts', []));
    }

    /**
     * Check if a radius name is valid.
     */
    public function isValidRadius(string $radius): bool
    {
        return array_key_exists($radius, config('tenant-themes.radii', []));
    }

    /**
     * Get the default preset name.
     */
    public function defaultPreset(): string
    {
        return config('tenant-themes.default_preset', 'modern');
    }

    /**
     * Get the default font name.
     */
    public function defaultFont(): string
    {
        return config('tenant-themes.default_font', 'inter');
    }

    /**
     * Get the default radius name.
     */
    public function defaultRadius(): string
    {
        return config('tenant-themes.default_radius', 'medium');
    }

    /**
     * Read a theme setting from the tenant's settings JSON.
     */
    private function getThemeSetting(Tenant $tenant, string $key): ?string
    {
        $settings = $tenant->settings;

        if (! is_array($settings)) {
            return null;
        }

        $value = $settings[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Build light mode CSS variable overrides.
     *
     * @return array<string, string>
     */
    private function buildLightModeVars(string $preset, string $font, string $radius): array
    {
        $vars = [];

        // Preset color overrides (only if not default)
        if ($preset !== $this->defaultPreset()) {
            $presetConfig = $this->getPresetConfig($preset);
            if ($presetConfig !== null && isset($presetConfig['light'])) {
                $vars = array_merge($vars, $presetConfig['light']);
            }
        }

        // Font override
        if ($font !== $this->defaultFont()) {
            $fontConfig = $this->getFontConfig($font);
            if ($fontConfig !== null && isset($fontConfig['family'])) {
                $vars['--font-sans'] = $fontConfig['family'];
            }
        }

        // Border radius override
        if ($radius !== $this->defaultRadius()) {
            $radiusConfig = $this->getRadiusConfig($radius);
            if ($radiusConfig !== null && isset($radiusConfig['value'])) {
                $vars['--radius-md'] = $radiusConfig['value'];
                $vars['--radius-lg'] = $this->scaleRadius($radiusConfig['value'], 1.5);
                $vars['--radius-xl'] = $this->scaleRadius($radiusConfig['value'], 2);
            }
        }

        return $vars;
    }

    /**
     * Build dark mode CSS variable overrides.
     *
     * @return array<string, string>
     */
    private function buildDarkModeVars(string $preset): array
    {
        if ($preset === $this->defaultPreset()) {
            return [];
        }

        $presetConfig = $this->getPresetConfig($preset);

        if ($presetConfig === null || ! isset($presetConfig['dark'])) {
            return [];
        }

        return $presetConfig['dark'];
    }

    /**
     * Get the full configuration for a preset.
     *
     * @return array<string, mixed>|null
     */
    private function getPresetConfig(string $preset): ?array
    {
        return config("tenant-themes.presets.{$preset}");
    }

    /**
     * Get the full configuration for a font.
     *
     * @return array<string, mixed>|null
     */
    private function getFontConfig(string $font): ?array
    {
        return config("tenant-themes.fonts.{$font}");
    }

    /**
     * Get the full configuration for a radius.
     *
     * @return array<string, mixed>|null
     */
    private function getRadiusConfig(string $radius): ?array
    {
        return config("tenant-themes.radii.{$radius}");
    }

    /**
     * Format an array of CSS variable definitions into a CSS string.
     */
    private function formatCssVars(array $vars): string
    {
        $parts = [];

        foreach ($vars as $property => $value) {
            $parts[] = $property.': '.$value.';';
        }

        return implode(' ', $parts);
    }

    /**
     * Scale a pixel radius value by a multiplier.
     */
    private function scaleRadius(string $value, float $multiplier): string
    {
        $px = (float) str_replace('px', '', $value);

        return round($px * $multiplier).'px';
    }
}
