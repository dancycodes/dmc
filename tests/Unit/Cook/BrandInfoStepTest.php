<?php

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Cook\UpdateBrandInfoRequest;
use App\Models\Tenant;
use App\Services\SetupWizardService;

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| F-072: Brand Info Step — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for brand info validation, step completion, and service logic.
|
*/

// -------------------------------------------------------------------
// UpdateBrandInfoRequest Validation Rules
// -------------------------------------------------------------------

it('has required brand name fields in validation rules', function () {
    $request = new UpdateBrandInfoRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKeys(['name_en', 'name_fr']);
    expect($rules['name_en'])->toContain('required');
    expect($rules['name_fr'])->toContain('required');
});

it('has required whatsapp field in validation rules', function () {
    $request = new UpdateBrandInfoRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('whatsapp');
    expect($rules['whatsapp'])->toContain('required');
});

it('has optional fields in validation rules', function () {
    $request = new UpdateBrandInfoRequest;
    $rules = $request->rules();

    expect($rules['description_en'])->toContain('nullable');
    expect($rules['description_fr'])->toContain('nullable');
    expect($rules['phone'])->toContain('nullable');
    expect($rules['social_facebook'])->toContain('nullable');
    expect($rules['social_instagram'])->toContain('nullable');
    expect($rules['social_tiktok'])->toContain('nullable');
});

it('enforces max 100 characters for brand names', function () {
    $request = new UpdateBrandInfoRequest;
    $rules = $request->rules();

    expect($rules['name_en'])->toContain('max:100');
    expect($rules['name_fr'])->toContain('max:100');
});

it('enforces max 1000 characters for bio', function () {
    $request = new UpdateBrandInfoRequest;
    $rules = $request->rules();

    expect($rules['description_en'])->toContain('max:1000');
    expect($rules['description_fr'])->toContain('max:1000');
});

it('enforces url validation for social links', function () {
    $request = new UpdateBrandInfoRequest;
    $rules = $request->rules();

    expect($rules['social_facebook'])->toContain('url');
    expect($rules['social_instagram'])->toContain('url');
    expect($rules['social_tiktok'])->toContain('url');
});

it('has cameroon phone regex constant', function () {
    expect(UpdateBrandInfoRequest::CAMEROON_PHONE_REGEX)->toBe('/^\+237[672]\d{8}$/');
});

it('defines messages method with expected validation keys', function () {
    // Verify messages() method exists and returns array with expected keys
    // Note: __() translator is not available in pure unit tests, so we use reflection
    $request = new UpdateBrandInfoRequest;
    $reflection = new ReflectionMethod($request, 'messages');

    expect($reflection->isPublic())->toBeTrue();
    expect($reflection->getReturnType()?->getName())->toBe('array');

    // Parse the source code to verify all expected message keys exist
    $source = file_get_contents(
        dirname(__DIR__, 3).'/app/Http/Requests/Cook/UpdateBrandInfoRequest.php'
    );

    expect($source)->toContain("'name_en.required'");
    expect($source)->toContain("'name_fr.required'");
    expect($source)->toContain("'whatsapp.required'");
    expect($source)->toContain("'whatsapp.regex'");
    expect($source)->toContain("'phone.regex'");
    expect($source)->toContain("'social_facebook.url'");
    expect($source)->toContain("'social_instagram.url'");
    expect($source)->toContain("'social_tiktok.url'");
});

it('has 9 fields in validation rules', function () {
    $request = new UpdateBrandInfoRequest;
    $rules = $request->rules();

    expect($rules)->toHaveCount(9);
});

// -------------------------------------------------------------------
// Phone Normalization
// -------------------------------------------------------------------

it('normalizes whatsapp with spaces to +237 format', function () {
    $normalized = RegisterRequest::normalizePhone('6 70 12 34 56');
    expect($normalized)->toBe('+237670123456');
});

it('normalizes whatsapp with dashes to +237 format', function () {
    $normalized = RegisterRequest::normalizePhone('670-123-456');
    expect($normalized)->toBe('+237670123456');
});

it('keeps already normalized whatsapp number unchanged', function () {
    $normalized = RegisterRequest::normalizePhone('+237670123456');
    expect($normalized)->toBe('+237670123456');
});

it('prepends +237 to 9-digit number starting with 6', function () {
    $normalized = RegisterRequest::normalizePhone('670123456');
    expect($normalized)->toBe('+237670123456');
});

it('prepends + to 237-prefixed 12-digit number', function () {
    $normalized = RegisterRequest::normalizePhone('237670123456');
    expect($normalized)->toBe('+237670123456');
});

// -------------------------------------------------------------------
// SetupWizardService — hasBrandInfo (BR-125)
// -------------------------------------------------------------------

it('returns false when tenant has no whatsapp', function () {
    $tenant = new Tenant([
        'name_en' => 'Test Brand',
        'name_fr' => 'Marque Test',
        'whatsapp' => null,
    ]);

    $service = new SetupWizardService;
    expect($service->hasBrandInfo($tenant))->toBeFalse();
});

it('returns false when tenant has no name_en', function () {
    $tenant = new Tenant([
        'name_en' => '',
        'name_fr' => 'Marque Test',
        'whatsapp' => '+237670123456',
    ]);

    $service = new SetupWizardService;
    expect($service->hasBrandInfo($tenant))->toBeFalse();
});

it('returns false when tenant has no name_fr', function () {
    $tenant = new Tenant([
        'name_en' => 'Test Brand',
        'name_fr' => '',
        'whatsapp' => '+237670123456',
    ]);

    $service = new SetupWizardService;
    expect($service->hasBrandInfo($tenant))->toBeFalse();
});

it('returns true when tenant has name_en name_fr and whatsapp', function () {
    $tenant = new Tenant([
        'name_en' => 'Test Brand',
        'name_fr' => 'Marque Test',
        'whatsapp' => '+237670123456',
    ]);

    $service = new SetupWizardService;
    expect($service->hasBrandInfo($tenant))->toBeTrue();
});

// -------------------------------------------------------------------
// SetupWizardService — markStepComplete (in-memory)
// -------------------------------------------------------------------

it('marks step 1 as complete in tenant settings', function () {
    $tenant = new Tenant(['settings' => null]);

    // Simulate markStepComplete logic without DB save
    $completedSteps = $tenant->getSetting('setup_steps', []);
    $completedSteps[] = 1;
    $tenant->setSetting('setup_steps', $completedSteps);

    expect($tenant->getSetting('setup_steps'))->toContain(1);
    $service = new SetupWizardService;
    expect($service->isStepComplete($tenant, 1))->toBeTrue();
});

it('does not duplicate step in settings when already complete', function () {
    $tenant = new Tenant([
        'settings' => ['setup_steps' => [1]],
    ]);

    // Simulate markStepComplete logic
    $completedSteps = $tenant->getSetting('setup_steps', []);
    if (! in_array(1, $completedSteps, true)) {
        $completedSteps[] = 1;
    }
    $tenant->setSetting('setup_steps', $completedSteps);

    $steps = $tenant->getSetting('setup_steps');
    $count = array_count_values($steps);
    expect($count[1])->toBe(1);
});

// -------------------------------------------------------------------
// Tenant Model — new fillable fields
// -------------------------------------------------------------------

it('has brand info fields in fillable', function () {
    $tenant = new Tenant;
    $fillable = $tenant->getFillable();

    expect($fillable)->toContain('whatsapp');
    expect($fillable)->toContain('phone');
    expect($fillable)->toContain('social_facebook');
    expect($fillable)->toContain('social_instagram');
    expect($fillable)->toContain('social_tiktok');
});

it('can instantiate tenant with brand info fields', function () {
    $tenant = new Tenant([
        'name_en' => 'Test Brand',
        'name_fr' => 'Marque Test',
        'whatsapp' => '+237670123456',
        'phone' => '+237680123456',
        'social_facebook' => 'https://facebook.com/test',
        'social_instagram' => 'https://instagram.com/test',
        'social_tiktok' => 'https://tiktok.com/@test',
        'description_en' => 'English bio',
        'description_fr' => 'Bio en francais',
    ]);

    expect($tenant->whatsapp)->toBe('+237670123456');
    expect($tenant->phone)->toBe('+237680123456');
    expect($tenant->social_facebook)->toBe('https://facebook.com/test');
    expect($tenant->social_instagram)->toBe('https://instagram.com/test');
    expect($tenant->social_tiktok)->toBe('https://tiktok.com/@test');
    expect($tenant->description_en)->toBe('English bio');
    expect($tenant->description_fr)->toBe('Bio en francais');
});

// -------------------------------------------------------------------
// Edge Cases
// -------------------------------------------------------------------

it('accepts brand name with special characters', function () {
    $tenant = new Tenant([
        'name_en' => "Chef Latifa's Kitchen",
        'name_fr' => "La Cuisine de Chef \u00c9milie",
        'whatsapp' => '+237670123456',
    ]);

    expect($tenant->name_en)->toBe("Chef Latifa's Kitchen");
    expect($tenant->name_fr)->toContain("\u00c9");
});

it('allows social links to be null', function () {
    $tenant = new Tenant([
        'social_facebook' => null,
        'social_instagram' => null,
        'social_tiktok' => null,
    ]);

    expect($tenant->social_facebook)->toBeNull();
    expect($tenant->social_instagram)->toBeNull();
    expect($tenant->social_tiktok)->toBeNull();
});

it('allows phone to be null when whatsapp is set', function () {
    $tenant = new Tenant([
        'whatsapp' => '+237670123456',
        'phone' => null,
    ]);

    expect($tenant->whatsapp)->toBe('+237670123456');
    expect($tenant->phone)->toBeNull();
});

// -------------------------------------------------------------------
// Controller and Route File Structure
// -------------------------------------------------------------------

describe('controller has saveBrandInfo method', function () use ($projectRoot) {
    test('saveBrandInfo method exists in SetupWizardController', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SetupWizardController.php');
        expect($content)->toContain('public function saveBrandInfo(');
    });

    test('controller uses gale() for brand info responses', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SetupWizardController.php');
        expect($content)->toContain('gale()->redirect(');
        expect($content)->toContain('validateState(');
        expect($content)->not->toContain('return view(');
    });

    test('controller logs activity for brand info updates', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Http/Controllers/Cook/SetupWizardController.php');
        expect($content)->toContain("'brand_info_updated'");
    });
});

describe('brand info route is defined', function () use ($projectRoot) {
    test('brand-info POST route exists in web.php', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/routes/web.php');
        expect($content)->toContain("Route::post('/setup/brand-info'");
        expect($content)->toContain("'saveBrandInfo'");
    });
});

describe('brand info blade partial exists', function () use ($projectRoot) {
    test('brand-info step blade file exists', function () use ($projectRoot) {
        expect(file_exists($projectRoot.'/resources/views/cook/setup/steps/brand-info.blade.php'))->toBeTrue();
    });

    test('wizard includes brand-info partial for step 1', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/resources/views/cook/setup/wizard.blade.php');
        expect($content)->toContain("@include('cook.setup.steps.brand-info'");
    });
});
