<?php

use App\Models\Tenant;

describe('Tenant Model Methods for Detail View', function () {
    it('returns default commission rate via getSetting', function () {
        $tenant = new Tenant([
            'slug' => 'test',
            'name_en' => 'Test',
            'name_fr' => 'Test FR',
            'settings' => [],
        ]);

        expect($tenant->getSetting('commission_rate', 10))->toBe(10);
    });

    it('returns custom commission rate from settings', function () {
        $tenant = new Tenant([
            'slug' => 'test',
            'name_en' => 'Test',
            'name_fr' => 'Test FR',
            'settings' => ['commission_rate' => 15],
        ]);

        expect($tenant->getSetting('commission_rate', 10))->toBe(15);
    });

    it('returns null for unset setting with no default', function () {
        $tenant = new Tenant([
            'slug' => 'test',
            'name_en' => 'Test',
            'name_fr' => 'Test FR',
            'settings' => [],
        ]);

        expect($tenant->getSetting('nonexistent'))->toBeNull();
    });

    it('handles null settings gracefully', function () {
        $tenant = new Tenant([
            'slug' => 'test',
            'name_en' => 'Test',
            'name_fr' => 'Test FR',
            'settings' => null,
        ]);

        expect($tenant->getSetting('commission_rate', 10))->toBe(10);
    });

    it('sets commission rate via setSetting', function () {
        $tenant = new Tenant([
            'slug' => 'test',
            'name_en' => 'Test',
            'name_fr' => 'Test FR',
            'settings' => [],
        ]);

        $tenant->setSetting('commission_rate', 12);

        expect($tenant->getSetting('commission_rate'))->toBe(12);
    });

    it('uses slug as route key name', function () {
        $tenant = new Tenant;

        expect($tenant->getRouteKeyName())->toBe('slug');
    });
});

describe('Tenant Translatable Attributes', function () {
    it('has name and description in translatable array', function () {
        $tenant = new Tenant;
        $reflection = new ReflectionProperty($tenant, 'translatable');
        $translatable = $reflection->getValue($tenant);

        expect($translatable)->toContain('name')
            ->and($translatable)->toContain('description');
    });
});

describe('Tenant is_active casting', function () {
    it('casts is_active to boolean', function () {
        $tenant = new Tenant([
            'slug' => 'test',
            'name_en' => 'Test',
            'name_fr' => 'Test FR',
            'is_active' => 1,
            'settings' => [],
        ]);

        expect($tenant->is_active)->toBeBool()
            ->and($tenant->is_active)->toBeTrue();
    });

    it('casts false is_active correctly', function () {
        $tenant = new Tenant([
            'slug' => 'test',
            'name_en' => 'Test',
            'name_fr' => 'Test FR',
            'is_active' => 0,
            'settings' => [],
        ]);

        expect($tenant->is_active)->toBeBool()
            ->and($tenant->is_active)->toBeFalse();
    });
});

describe('Translation strings exist for F-047', function () {
    it('has all required English translation keys', function () {
        $projectRoot = dirname(__DIR__, 3);
        $enTranslations = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

        $requiredKeys = [
            'Tenant Detail',
            'Tenant Information',
            'Edit Tenant',
            'Configure Commission',
            'Visit Site',
            'Total Orders',
            'Total Revenue',
            'Commission Rate',
            'Active Meals',
            'Assigned Cook',
            'No cook assigned to this tenant.',
            'Assign Cook',
            'Activity History',
            'No activity recorded yet.',
            'Back to Tenant List',
            'Read more',
            'Show less',
        ];

        foreach ($requiredKeys as $key) {
            expect(array_key_exists($key, $enTranslations))->toBeTrue("Missing EN translation: $key");
        }
    });

    it('has all required French translation keys', function () {
        $projectRoot = dirname(__DIR__, 3);
        $frTranslations = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

        $requiredKeys = [
            'Tenant Detail',
            'Tenant Information',
            'Edit Tenant',
            'Configure Commission',
            'Visit Site',
            'Total Orders',
            'Total Revenue',
            'Commission Rate',
            'Active Meals',
            'Assigned Cook',
            'No cook assigned to this tenant.',
            'Assign Cook',
            'Activity History',
            'No activity recorded yet.',
            'Back to Tenant List',
            'Read more',
            'Show less',
        ];

        foreach ($requiredKeys as $key) {
            expect(array_key_exists($key, $frTranslations))->toBeTrue("Missing FR translation: $key");
        }
    });
});

describe('Tenant Fillable Attributes', function () {
    it('has required fillable attributes for tenant detail', function () {
        $tenant = new Tenant;
        $fillable = $tenant->getFillable();

        expect($fillable)->toContain('slug')
            ->and($fillable)->toContain('name_en')
            ->and($fillable)->toContain('name_fr')
            ->and($fillable)->toContain('custom_domain')
            ->and($fillable)->toContain('description_en')
            ->and($fillable)->toContain('description_fr')
            ->and($fillable)->toContain('is_active')
            ->and($fillable)->toContain('settings');
    });
});

describe('Tenant Settings Methods', function () {
    it('preserves existing settings when adding new one', function () {
        $tenant = new Tenant([
            'slug' => 'test',
            'name_en' => 'Test',
            'name_fr' => 'Test FR',
            'settings' => ['theme' => 'ocean'],
        ]);

        $tenant->setSetting('commission_rate', 12);

        expect($tenant->getSetting('theme'))->toBe('ocean')
            ->and($tenant->getSetting('commission_rate'))->toBe(12);
    });

    it('returns fluent instance from setSetting', function () {
        $tenant = new Tenant([
            'slug' => 'test',
            'name_en' => 'Test',
            'name_fr' => 'Test FR',
            'settings' => [],
        ]);

        $result = $tenant->setSetting('commission_rate', 10);

        expect($result)->toBeInstanceOf(Tenant::class);
    });
});
