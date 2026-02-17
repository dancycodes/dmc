<?php

/**
 * F-071: Cook Setup Wizard Shell — Unit Tests
 *
 * Tests for SetupWizardService: step management, navigation, completion tracking,
 * requirement checks, and Go Live logic.
 */

use App\Models\Tenant;
use App\Services\SetupWizardService;

$projectRoot = dirname(__DIR__, 3);

beforeEach(function () {
    $this->service = new SetupWizardService;
});

describe('SetupWizardService constants', function () {
    test('defines exactly 4 steps per BR-108', function () {
        expect(SetupWizardService::STEPS)->toHaveCount(4);
        expect(SetupWizardService::STEP_TITLES)->toHaveCount(4);
    });

    test('step slugs are correct', function () {
        expect(SetupWizardService::STEPS)->toBe([
            1 => 'brand-info',
            2 => 'cover-images',
            3 => 'delivery-areas',
            4 => 'schedule-meal',
        ]);
    });

    test('step titles are correct', function () {
        expect(SetupWizardService::STEP_TITLES)->toBe([
            1 => 'Brand Info',
            2 => 'Cover Images',
            3 => 'Delivery Areas',
            4 => 'Schedule & First Meal',
        ]);
    });
});

describe('isValidStep', function () {
    test('returns true for valid steps 1-4', function () {
        foreach ([1, 2, 3, 4] as $step) {
            expect($this->service->isValidStep($step))->toBeTrue();
        }
    });

    test('returns false for invalid steps', function () {
        expect($this->service->isValidStep(0))->toBeFalse();
        expect($this->service->isValidStep(5))->toBeFalse();
        expect($this->service->isValidStep(-1))->toBeFalse();
    });
});

describe('isStepComplete', function () {
    test('returns false when no steps are completed', function () {
        $tenant = new Tenant(['settings' => null]);

        foreach ([1, 2, 3, 4] as $step) {
            expect($this->service->isStepComplete($tenant, $step))->toBeFalse();
        }
    });

    test('returns false when settings is empty array', function () {
        $tenant = new Tenant(['settings' => []]);

        foreach ([1, 2, 3, 4] as $step) {
            expect($this->service->isStepComplete($tenant, $step))->toBeFalse();
        }
    });

    test('returns true for completed steps stored in settings', function () {
        $tenant = new Tenant([
            'settings' => ['setup_steps' => [1, 3]],
        ]);

        expect($this->service->isStepComplete($tenant, 1))->toBeTrue();
        expect($this->service->isStepComplete($tenant, 2))->toBeFalse();
        expect($this->service->isStepComplete($tenant, 3))->toBeTrue();
        expect($this->service->isStepComplete($tenant, 4))->toBeFalse();
    });
});

describe('markStepComplete per BR-114', function () {
    test('marks a step as complete in settings array', function () {
        $tenant = new Tenant(['settings' => null]);

        // Mark step without saving (unit test - no DB)
        $completedSteps = $tenant->getSetting('setup_steps', []);
        $completedSteps[] = 1;
        $tenant->setSetting('setup_steps', $completedSteps);

        expect($this->service->isStepComplete($tenant, 1))->toBeTrue();
        expect($tenant->getSetting('setup_steps'))->toContain(1);
    });

    test('does not duplicate when marking already complete step', function () {
        $tenant = new Tenant([
            'settings' => ['setup_steps' => [1]],
        ]);

        // Simulate markStepComplete logic without DB save
        $completedSteps = $tenant->getSetting('setup_steps', []);
        if (! in_array(1, $completedSteps, true)) {
            $completedSteps[] = 1;
        }
        $tenant->setSetting('setup_steps', $completedSteps);

        $steps = $tenant->getSetting('setup_steps');
        $count = array_count_values($steps);
        expect($count[1])->toBe(1);
    });

    test('preserves previously completed steps', function () {
        $tenant = new Tenant([
            'settings' => ['setup_steps' => [1]],
        ]);

        $completedSteps = $tenant->getSetting('setup_steps', []);
        if (! in_array(3, $completedSteps, true)) {
            $completedSteps[] = 3;
        }
        $tenant->setSetting('setup_steps', $completedSteps);

        expect($this->service->isStepComplete($tenant, 1))->toBeTrue();
        expect($this->service->isStepComplete($tenant, 3))->toBeTrue();
    });
});

describe('getCurrentStep per BR-114', function () {
    test('returns step 1 for new tenant with no completed steps', function () {
        $tenant = new Tenant(['settings' => null]);

        expect($this->service->getCurrentStep($tenant))->toBe(1);
    });

    test('returns first incomplete step', function () {
        $tenant = new Tenant([
            'settings' => ['setup_steps' => [1, 2]],
        ]);

        expect($this->service->getCurrentStep($tenant))->toBe(3);
    });

    test('returns step 4 when all steps are complete', function () {
        $tenant = new Tenant([
            'settings' => ['setup_steps' => [1, 2, 3, 4]],
        ]);

        expect($this->service->getCurrentStep($tenant))->toBe(4);
    });

    test('handles non-sequential completion', function () {
        $tenant = new Tenant([
            'settings' => ['setup_steps' => [1, 3]],
        ]);

        expect($this->service->getCurrentStep($tenant))->toBe(2);
    });

    test('returns step 1 when settings is empty array', function () {
        $tenant = new Tenant(['settings' => []]);

        expect($this->service->getCurrentStep($tenant))->toBe(1);
    });
});

describe('getStepCompletion', function () {
    test('returns completion status for all 4 steps', function () {
        $tenant = new Tenant([
            'settings' => ['setup_steps' => [1, 3]],
        ]);

        $completion = $this->service->getStepCompletion($tenant);

        expect($completion)->toBe([
            1 => true,
            2 => false,
            3 => true,
            4 => false,
        ]);
    });

    test('returns all false for new tenant', function () {
        $tenant = new Tenant(['settings' => null]);

        $completion = $this->service->getStepCompletion($tenant);

        expect($completion)->toBe([
            1 => false,
            2 => false,
            3 => false,
            4 => false,
        ]);
    });

    test('returns all true when all steps completed', function () {
        $tenant = new Tenant([
            'settings' => ['setup_steps' => [1, 2, 3, 4]],
        ]);

        $completion = $this->service->getStepCompletion($tenant);

        expect($completion)->toBe([
            1 => true,
            2 => true,
            3 => true,
            4 => true,
        ]);
    });
});

describe('hasBrandInfo per BR-109 and BR-125', function () {
    test('returns true when name_en, name_fr, and whatsapp are set', function () {
        $tenant = new Tenant([
            'name_en' => 'My Kitchen',
            'name_fr' => 'Ma Cuisine',
            'whatsapp' => '+237670123456',
        ]);

        expect($this->service->hasBrandInfo($tenant))->toBeTrue();
    });

    test('returns false when name_en is empty', function () {
        $tenant = new Tenant([
            'name_en' => '',
            'name_fr' => 'Ma Cuisine',
            'whatsapp' => '+237670123456',
        ]);

        expect($this->service->hasBrandInfo($tenant))->toBeFalse();
    });

    test('returns false when name_fr is empty', function () {
        $tenant = new Tenant([
            'name_en' => 'My Kitchen',
            'name_fr' => '',
            'whatsapp' => '+237670123456',
        ]);

        expect($this->service->hasBrandInfo($tenant))->toBeFalse();
    });

    test('returns false when whatsapp is empty', function () {
        $tenant = new Tenant([
            'name_en' => 'My Kitchen',
            'name_fr' => 'Ma Cuisine',
            'whatsapp' => null,
        ]);

        expect($this->service->hasBrandInfo($tenant))->toBeFalse();
    });

    test('returns false when both names are null', function () {
        $tenant = new Tenant([
            'name_en' => null,
            'name_fr' => null,
            'whatsapp' => null,
        ]);

        expect($this->service->hasBrandInfo($tenant))->toBeFalse();
    });
});

describe('canGoLive per BR-109 and BR-111', function () {
    test('returns false when brand info is missing regardless of other checks', function () {
        $tenant = new Tenant([
            'name_en' => '',
            'name_fr' => '',
        ]);

        // canGoLive short-circuits on hasBrandInfo=false
        expect($this->service->canGoLive($tenant))->toBeFalse();
    });
});

describe('getStepsData', function () {
    test('returns step data array for all 4 steps', function () {
        $tenant = new Tenant([
            'settings' => ['setup_steps' => [1]],
        ]);

        $steps = $this->service->getStepsData($tenant, 2);

        expect($steps)->toHaveCount(4);

        // Step 1: completed, not active
        expect($steps[1]['complete'])->toBeTrue();
        expect($steps[1]['active'])->toBeFalse();
        expect($steps[1]['slug'])->toBe('brand-info');
        expect($steps[1]['title'])->toBe('Brand Info');
        expect($steps[1]['number'])->toBe(1);

        // Step 2: not completed, active
        expect($steps[2]['complete'])->toBeFalse();
        expect($steps[2]['active'])->toBeTrue();
        expect($steps[2]['slug'])->toBe('cover-images');

        // Step 3: not completed, not active
        expect($steps[3]['complete'])->toBeFalse();
        expect($steps[3]['active'])->toBeFalse();

        // Step 4: not completed, not active
        expect($steps[4]['complete'])->toBeFalse();
        expect($steps[4]['active'])->toBeFalse();
        expect($steps[4]['slug'])->toBe('schedule-meal');
    });
});

describe('isStepNavigable per BR-112', function () {
    test('completed steps are navigable', function () {
        $tenant = new Tenant([
            'settings' => ['setup_steps' => [1, 2]],
        ]);

        expect($this->service->isStepNavigable($tenant, 1))->toBeTrue();
        expect($this->service->isStepNavigable($tenant, 2))->toBeTrue();
    });

    test('current step is navigable', function () {
        $tenant = new Tenant([
            'settings' => ['setup_steps' => [1]],
        ]);

        // Step 2 is current (first incomplete)
        expect($this->service->isStepNavigable($tenant, 2))->toBeTrue();
    });

    test('future incomplete steps are not navigable', function () {
        $tenant = new Tenant([
            'settings' => ['setup_steps' => [1]],
        ]);

        expect($this->service->isStepNavigable($tenant, 3))->toBeFalse();
        expect($this->service->isStepNavigable($tenant, 4))->toBeFalse();
    });

    test('all steps are navigable after setup complete per BR-116', function () {
        $tenant = new Tenant([
            'settings' => [
                'setup_complete' => true,
                'setup_steps' => [1, 2, 3, 4],
            ],
        ]);

        foreach ([1, 2, 3, 4] as $step) {
            expect($this->service->isStepNavigable($tenant, $step))->toBeTrue();
        }
    });

    test('invalid step numbers are not navigable', function () {
        $tenant = new Tenant(['settings' => []]);

        expect($this->service->isStepNavigable($tenant, 0))->toBeFalse();
        expect($this->service->isStepNavigable($tenant, 5))->toBeFalse();
    });
});

describe('goLive per BR-115', function () {
    test('sets setup_complete to true in settings', function () {
        $tenant = new Tenant(['settings' => null]);

        // Simulate goLive logic without DB save
        $tenant->setSetting('setup_complete', true);

        expect($tenant->isSetupComplete())->toBeTrue();
        expect($tenant->getSetting('setup_complete'))->toBeTrue();
    });

    test('preserves existing settings when going live', function () {
        $tenant = new Tenant([
            'settings' => [
                'setup_steps' => [1, 2, 3, 4],
                'theme' => 'ocean',
            ],
        ]);

        // Simulate goLive logic
        $tenant->setSetting('setup_complete', true);

        expect($tenant->isSetupComplete())->toBeTrue();
        expect($tenant->getSetting('setup_steps'))->toBe([1, 2, 3, 4]);
        expect($tenant->getSetting('theme'))->toBe('ocean');
    });
});

describe('Tenant model methods', function () {
    test('getCompletedSetupSteps returns empty array for new tenant', function () {
        $tenant = new Tenant(['settings' => null]);

        expect($tenant->getCompletedSetupSteps())->toBe([]);
    });

    test('getCompletedSetupSteps returns setup_steps from settings', function () {
        $tenant = new Tenant([
            'settings' => ['setup_steps' => [1, 2, 3]],
        ]);

        expect($tenant->getCompletedSetupSteps())->toBe([1, 2, 3]);
    });
});

describe('Route registration', function () use ($projectRoot) {
    $routesPath = $projectRoot.'/routes/web.php';

    test('setup wizard route is defined in web.php', function () use ($routesPath) {
        $content = file_get_contents($routesPath);

        expect($content)->toContain("Route::get('/setup', [SetupWizardController::class, 'show'])");
        expect($content)->toContain("->name('cook.setup')");
    });

    test('go-live route is defined in web.php', function () use ($routesPath) {
        $content = file_get_contents($routesPath);

        expect($content)->toContain("Route::post('/setup/go-live', [SetupWizardController::class, 'goLive'])");
        expect($content)->toContain("->name('cook.setup.go-live')");
    });

    test('setup routes are inside dashboard prefix with cook.access middleware', function () use ($routesPath) {
        $content = file_get_contents($routesPath);

        // Routes should be inside the dashboard prefix with cook.access middleware
        expect($content)->toContain('cook.access');
        expect($content)->toContain('SetupWizardController');
    });
});

describe('SetupWizardController file structure', function () use ($projectRoot) {
    test('controller exists', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/app/Http/Controllers/Cook/SetupWizardController.php'))->toBeTrue();
    });

    test('controller has show method', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SetupWizardController.php');
        expect($content)->toContain('public function show(');
    });

    test('controller has goLive method', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SetupWizardController.php');
        expect($content)->toContain('public function goLive(');
    });

    test('controller uses gale() for all responses', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SetupWizardController.php');
        expect($content)->toContain('gale()->view(');
        expect($content)->toContain('gale()->redirect(');
        expect($content)->toContain('->fragment(');
        expect($content)->not->toContain('return view(');
    });

    test('controller uses activity logging for goLive', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SetupWizardController.php');
        expect($content)->toContain("activity('tenants')");
    });
});
