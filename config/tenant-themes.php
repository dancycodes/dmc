<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Theme Preset
    |--------------------------------------------------------------------------
    |
    | The default theme preset applied when a tenant has no custom theme
    | configured, or when the configured preset is invalid.
    |
    */

    'default_preset' => 'modern',

    /*
    |--------------------------------------------------------------------------
    | Default Font
    |--------------------------------------------------------------------------
    |
    | The default font family applied when a tenant has no custom font
    | configured, or when the configured font is invalid.
    |
    */

    'default_font' => 'inter',

    /*
    |--------------------------------------------------------------------------
    | Default Border Radius
    |--------------------------------------------------------------------------
    |
    | The default border radius applied when a tenant has no custom radius
    | configured, or when the configured radius is invalid.
    |
    */

    'default_radius' => 'medium',

    /*
    |--------------------------------------------------------------------------
    | Theme Presets
    |--------------------------------------------------------------------------
    |
    | Each preset defines a complete set of CSS custom property overrides
    | for both light and dark modes. Colors use raw hex values that get
    | injected as inline CSS on the tenant domain.
    |
    | Every preset must define: primary, primary-hover, primary-subtle,
    | on-primary, secondary, secondary-hover, secondary-subtle, on-secondary
    | for both light and dark variants.
    |
    */

    'presets' => [

        'modern' => [
            'label' => 'Modern',
            'description' => 'Balanced, contemporary palette with teal and amber tones.',
            'light' => [
                '--color-primary' => '#0D9488',
                '--color-primary-hover' => '#0F766E',
                '--color-primary-subtle' => '#F0FDFA',
                '--color-on-primary' => '#FFFFFF',
                '--color-secondary' => '#F59E0B',
                '--color-secondary-hover' => '#D97706',
                '--color-secondary-subtle' => '#FFFBEB',
                '--color-on-secondary' => '#18181B',
            ],
            'dark' => [
                '--color-primary' => '#14B8A6',
                '--color-primary-hover' => '#2DD4BF',
                '--color-primary-subtle' => '#27272A',
                '--color-on-primary' => '#18181B',
                '--color-secondary' => '#FBBF24',
                '--color-secondary-hover' => '#F59E0B',
                '--color-secondary-subtle' => '#27272A',
                '--color-on-secondary' => '#18181B',
            ],
        ],

        'arctic' => [
            'label' => 'Arctic',
            'description' => 'Cool blues and whites, clean and calming.',
            'light' => [
                '--color-primary' => '#0284C7',
                '--color-primary-hover' => '#0369A1',
                '--color-primary-subtle' => '#F0F9FF',
                '--color-on-primary' => '#FFFFFF',
                '--color-secondary' => '#64748B',
                '--color-secondary-hover' => '#475569',
                '--color-secondary-subtle' => '#F8FAFC',
                '--color-on-secondary' => '#FFFFFF',
            ],
            'dark' => [
                '--color-primary' => '#38BDF8',
                '--color-primary-hover' => '#7DD3FC',
                '--color-primary-subtle' => '#1E293B',
                '--color-on-primary' => '#0F172A',
                '--color-secondary' => '#94A3B8',
                '--color-secondary-hover' => '#CBD5E1',
                '--color-secondary-subtle' => '#1E293B',
                '--color-on-secondary' => '#0F172A',
            ],
        ],

        'high-contrast' => [
            'label' => 'High Contrast',
            'description' => 'Maximum readability, bold black/white with accent pops.',
            'light' => [
                '--color-primary' => '#000000',
                '--color-primary-hover' => '#1A1A1A',
                '--color-primary-subtle' => '#F5F5F5',
                '--color-on-primary' => '#FFFFFF',
                '--color-secondary' => '#DC2626',
                '--color-secondary-hover' => '#B91C1C',
                '--color-secondary-subtle' => '#FEF2F2',
                '--color-on-secondary' => '#FFFFFF',
            ],
            'dark' => [
                '--color-primary' => '#FFFFFF',
                '--color-primary-hover' => '#E5E5E5',
                '--color-primary-subtle' => '#262626',
                '--color-on-primary' => '#000000',
                '--color-secondary' => '#EF4444',
                '--color-secondary-hover' => '#F87171',
                '--color-secondary-subtle' => '#292524',
                '--color-on-secondary' => '#FFFFFF',
            ],
        ],

        'minimal' => [
            'label' => 'Minimal',
            'description' => 'Subtle, neutral tones with ample whitespace.',
            'light' => [
                '--color-primary' => '#525252',
                '--color-primary-hover' => '#404040',
                '--color-primary-subtle' => '#FAFAFA',
                '--color-on-primary' => '#FFFFFF',
                '--color-secondary' => '#A3A3A3',
                '--color-secondary-hover' => '#737373',
                '--color-secondary-subtle' => '#F5F5F5',
                '--color-on-secondary' => '#171717',
            ],
            'dark' => [
                '--color-primary' => '#D4D4D4',
                '--color-primary-hover' => '#E5E5E5',
                '--color-primary-subtle' => '#262626',
                '--color-on-primary' => '#171717',
                '--color-secondary' => '#737373',
                '--color-secondary-hover' => '#A3A3A3',
                '--color-secondary-subtle' => '#262626',
                '--color-on-secondary' => '#FAFAFA',
            ],
        ],

        'neo-brutalism' => [
            'label' => 'Neo Brutalism',
            'description' => 'Bold colors, thick borders, playful and striking.',
            'light' => [
                '--color-primary' => '#E11D48',
                '--color-primary-hover' => '#BE123C',
                '--color-primary-subtle' => '#FFF1F2',
                '--color-on-primary' => '#FFFFFF',
                '--color-secondary' => '#FACC15',
                '--color-secondary-hover' => '#EAB308',
                '--color-secondary-subtle' => '#FEFCE8',
                '--color-on-secondary' => '#171717',
                '--color-outline' => '#171717',
                '--color-outline-strong' => '#000000',
            ],
            'dark' => [
                '--color-primary' => '#FB7185',
                '--color-primary-hover' => '#FDA4AF',
                '--color-primary-subtle' => '#1C1917',
                '--color-on-primary' => '#0C0A09',
                '--color-secondary' => '#FDE047',
                '--color-secondary-hover' => '#FACC15',
                '--color-secondary-subtle' => '#1C1917',
                '--color-on-secondary' => '#0C0A09',
                '--color-outline' => '#E5E5E5',
                '--color-outline-strong' => '#FFFFFF',
            ],
        ],

        'ocean' => [
            'label' => 'Ocean',
            'description' => 'Professional, calm, tech-focused blue and cyan tones.',
            'light' => [
                '--color-primary' => '#2563EB',
                '--color-primary-hover' => '#1D4ED8',
                '--color-primary-subtle' => '#EFF6FF',
                '--color-on-primary' => '#FFFFFF',
                '--color-secondary' => '#06B6D4',
                '--color-secondary-hover' => '#0891B2',
                '--color-secondary-subtle' => '#ECFEFF',
                '--color-on-secondary' => '#FFFFFF',
            ],
            'dark' => [
                '--color-primary' => '#3B82F6',
                '--color-primary-hover' => '#60A5FA',
                '--color-primary-subtle' => '#27272A',
                '--color-on-primary' => '#18181B',
                '--color-secondary' => '#22D3EE',
                '--color-secondary-hover' => '#06B6D4',
                '--color-secondary-subtle' => '#27272A',
                '--color-on-secondary' => '#18181B',
            ],
        ],

        'forest' => [
            'label' => 'Forest',
            'description' => 'Natural, organic, eco-friendly green tones.',
            'light' => [
                '--color-primary' => '#059669',
                '--color-primary-hover' => '#047857',
                '--color-primary-subtle' => '#ECFDF5',
                '--color-on-primary' => '#FFFFFF',
                '--color-secondary' => '#84CC16',
                '--color-secondary-hover' => '#65A30D',
                '--color-secondary-subtle' => '#F7FEE7',
                '--color-on-secondary' => '#171717',
            ],
            'dark' => [
                '--color-primary' => '#10B981',
                '--color-primary-hover' => '#34D399',
                '--color-primary-subtle' => '#27272A',
                '--color-on-primary' => '#18181B',
                '--color-secondary' => '#A3E635',
                '--color-secondary-hover' => '#84CC16',
                '--color-secondary-subtle' => '#27272A',
                '--color-on-secondary' => '#18181B',
            ],
        ],

        'sunset' => [
            'label' => 'Sunset',
            'description' => 'Warm, energetic, creative rose and orange tones.',
            'light' => [
                '--color-primary' => '#E11D48',
                '--color-primary-hover' => '#BE123C',
                '--color-primary-subtle' => '#FFF1F2',
                '--color-on-primary' => '#FFFFFF',
                '--color-secondary' => '#F97316',
                '--color-secondary-hover' => '#EA580C',
                '--color-secondary-subtle' => '#FFF7ED',
                '--color-on-secondary' => '#FFFFFF',
            ],
            'dark' => [
                '--color-primary' => '#F43F5E',
                '--color-primary-hover' => '#FB7185',
                '--color-primary-subtle' => '#27272A',
                '--color-on-primary' => '#18181B',
                '--color-secondary' => '#FB923C',
                '--color-secondary-hover' => '#F97316',
                '--color-secondary-subtle' => '#27272A',
                '--color-on-secondary' => '#18181B',
            ],
        ],

        'violet' => [
            'label' => 'Violet',
            'description' => 'Creative, playful, modern purple and pink tones.',
            'light' => [
                '--color-primary' => '#7C3AED',
                '--color-primary-hover' => '#6D28D9',
                '--color-primary-subtle' => '#F5F3FF',
                '--color-on-primary' => '#FFFFFF',
                '--color-secondary' => '#EC4899',
                '--color-secondary-hover' => '#DB2777',
                '--color-secondary-subtle' => '#FDF2F8',
                '--color-on-secondary' => '#FFFFFF',
            ],
            'dark' => [
                '--color-primary' => '#8B5CF6',
                '--color-primary-hover' => '#A78BFA',
                '--color-primary-subtle' => '#27272A',
                '--color-on-primary' => '#18181B',
                '--color-secondary' => '#F472B6',
                '--color-secondary-hover' => '#EC4899',
                '--color-secondary-subtle' => '#27272A',
                '--color-on-secondary' => '#18181B',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Font Catalog
    |--------------------------------------------------------------------------
    |
    | Available font families that tenants can select. Each entry defines
    | the CSS font-family stack and the Google Fonts URL for loading.
    |
    */

    'fonts' => [

        'inter' => [
            'label' => 'Inter',
            'family' => "'Inter', system-ui, -apple-system, sans-serif",
            'google_fonts_url' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            'category' => 'sans-serif',
        ],

        'roboto' => [
            'label' => 'Roboto',
            'family' => "'Roboto', system-ui, -apple-system, sans-serif",
            'google_fonts_url' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
            'category' => 'sans-serif',
        ],

        'poppins' => [
            'label' => 'Poppins',
            'family' => "'Poppins', system-ui, -apple-system, sans-serif",
            'google_fonts_url' => 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
            'category' => 'sans-serif',
        ],

        'nunito' => [
            'label' => 'Nunito',
            'family' => "'Nunito', system-ui, -apple-system, sans-serif",
            'google_fonts_url' => 'https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&display=swap',
            'category' => 'sans-serif',
        ],

        'open-sans' => [
            'label' => 'Open Sans',
            'family' => "'Open Sans', system-ui, -apple-system, sans-serif",
            'google_fonts_url' => 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap',
            'category' => 'sans-serif',
        ],

        'montserrat' => [
            'label' => 'Montserrat',
            'family' => "'Montserrat', system-ui, -apple-system, sans-serif",
            'google_fonts_url' => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap',
            'category' => 'sans-serif',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Border Radius Options
    |--------------------------------------------------------------------------
    |
    | Available border radius presets. The CSS value is applied as a custom
    | property override for --radius-md (the base radius token).
    |
    */

    'radii' => [

        'none' => [
            'label' => 'Sharp',
            'value' => '0px',
            'description' => 'No rounded corners.',
        ],

        'small' => [
            'label' => 'Small',
            'value' => '4px',
            'description' => 'Slightly rounded corners.',
        ],

        'medium' => [
            'label' => 'Medium',
            'value' => '8px',
            'description' => 'Moderately rounded corners.',
        ],

        'large' => [
            'label' => 'Large',
            'value' => '12px',
            'description' => 'Prominently rounded corners.',
        ],

        'full' => [
            'label' => 'Pill',
            'value' => '16px',
            'description' => 'Fully rounded corners.',
        ],

    ],

];
