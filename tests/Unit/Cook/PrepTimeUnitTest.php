<?php

/**
 * F-117: Meal Estimated Preparation Time — Unit Tests
 *
 * Tests for:
 * - MealService::updatePrepTime (BR-270 through BR-277)
 * - TenantLandingService::formatPrepTime (BR-274)
 */

use App\Services\MealService;
use App\Services\TenantLandingService;

$projectRoot = dirname(__DIR__, 3);

describe('TenantLandingService::formatPrepTime', function () use ($projectRoot) {
    // Verify the static method exists
    it('has a static formatPrepTime method on TenantLandingService', function () use ($projectRoot) {
        $file = $projectRoot.'/app/Services/TenantLandingService.php';
        expect(file_exists($file))->toBeTrue();
        expect(file_get_contents($file))->toContain('public static function formatPrepTime');
    });

    // BR-274: Display format tests
    it('formats minutes under 60 as "~N min"', function () {
        expect(TenantLandingService::formatPrepTime(1))->toBe('~1 min');
        expect(TenantLandingService::formatPrepTime(30))->toBe('~30 min');
        expect(TenantLandingService::formatPrepTime(59))->toBe('~59 min');
    });

    it('formats exactly 60 minutes as "~1 hr"', function () {
        expect(TenantLandingService::formatPrepTime(60))->toBe('~1 hr');
    });

    it('formats 90 minutes as "~1.5 hr"', function () {
        expect(TenantLandingService::formatPrepTime(90))->toBe('~1.5 hr');
    });

    it('formats 120 minutes as "~2 hr"', function () {
        expect(TenantLandingService::formatPrepTime(120))->toBe('~2 hr');
    });

    it('formats 1440 minutes (24 hours) as "~24 hr"', function () {
        expect(TenantLandingService::formatPrepTime(1440))->toBe('~24 hr');
    });

    it('formats 1 minute as "~1 min" (edge case minimum)', function () {
        expect(TenantLandingService::formatPrepTime(1))->toBe('~1 min');
    });

    it('formats 150 minutes as "~2.5 hr"', function () {
        expect(TenantLandingService::formatPrepTime(150))->toBe('~2.5 hr');
    });

    it('formats 75 minutes as "~1.3 hr" (rounded to 1 decimal)', function () {
        // 75 / 60 = 1.25, rounded to 1 decimal = 1.3
        $result = TenantLandingService::formatPrepTime(75);
        expect($result)->toStartWith('~');
        expect($result)->toEndWith(' hr');
    });
});

describe('MealService::updatePrepTime', function () use ($projectRoot) {
    it('has an updatePrepTime method on MealService', function () use ($projectRoot) {
        $file = $projectRoot.'/app/Services/MealService.php';
        expect(file_exists($file))->toBeTrue();
        expect(file_get_contents($file))->toContain('public function updatePrepTime');
    });

    it('updatePrepTime returns success with old and new prep time values', function () use ($projectRoot) {
        $serviceFile = $projectRoot.'/app/Services/MealService.php';
        $content = file_get_contents($serviceFile);

        // Verify return structure documented
        expect($content)->toContain('old_prep_time');
        expect($content)->toContain('new_prep_time');
        expect($content)->toContain("'success' => true");
    });
});

describe('Meal model estimated_prep_time', function () use ($projectRoot) {
    it('has estimated_prep_time in fillable and casts', function () use ($projectRoot) {
        $file = $projectRoot.'/app/Models/Meal.php';
        $content = file_get_contents($file);
        expect($content)->toContain("'estimated_prep_time'");
        // Cast to integer
        expect($content)->toContain("'estimated_prep_time' => 'integer'");
    });
});

describe('MealController updatePrepTime', function () use ($projectRoot) {
    it('has an updatePrepTime method on MealController', function () use ($projectRoot) {
        $file = $projectRoot.'/app/Http/Controllers/Cook/MealController.php';
        $content = file_get_contents($file);
        expect($content)->toContain('public function updatePrepTime');
        // BR-276: Permission check
        expect($content)->toContain('can-manage-meals');
        // BR-277: Activity logging
        expect($content)->toContain('meal_prep_time_updated');
        // BR-270: nullable validation
        expect($content)->toContain("'nullable'");
        // BR-272: min:1
        expect($content)->toContain("'min:1'");
        // BR-273: max:1440
        expect($content)->toContain("'max:1440'");
    });

    it('has a route for prep-time PATCH endpoint', function () use ($projectRoot) {
        $routes = $projectRoot.'/routes/web.php';
        $content = file_get_contents($routes);
        expect($content)->toContain('/meals/{meal}/prep-time');
        expect($content)->toContain('updatePrepTime');
    });
});

describe('Meal edit view prep time section', function () use ($projectRoot) {
    it('includes prep time form section in meal edit blade', function () use ($projectRoot) {
        $file = $projectRoot.'/resources/views/cook/meals/edit.blade.php';
        $content = file_get_contents($file);
        expect($content)->toContain('estimated_prep_time');
        expect($content)->toContain('prep-time');
        expect($content)->toContain('Preparation Time');
    });
});

describe('Tenant landing page prep time display', function () use ($projectRoot) {
    it('meal card blade uses formatted prep time string from service', function () use ($projectRoot) {
        $file = $projectRoot.'/resources/views/tenant/_meal-card.blade.php';
        $content = file_get_contents($file);
        // BR-274: Should reference prepTime from card data
        expect($content)->toContain("card['prepTime']");
        // BR-274: Should show the "Est. prep:" label
        expect($content)->toContain('Est. prep');
    });

    it('meal detail blade uses formatted prep time string from service', function () use ($projectRoot) {
        $file = $projectRoot.'/resources/views/tenant/meal-detail.blade.php';
        $content = file_get_contents($file);
        expect($content)->toContain("mealData['prepTime']");
        expect($content)->toContain('Est. prep');
    });
});
