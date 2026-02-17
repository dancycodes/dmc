<?php

use App\Models\Tenant;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
});

// --- Tenant::getUrl() ---

it('generates subdomain URL for tenant without custom domain', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'chef-latifa',
        'custom_domain' => null,
    ]);

    $url = $tenant->getUrl();
    $mainHost = parse_url(config('app.url'), PHP_URL_HOST);
    $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'https';

    expect($url)->toBe($scheme.'://chef-latifa.'.$mainHost);
});

it('generates custom domain URL when custom domain is set', function () {
    $tenant = Tenant::factory()->withCustomDomain('latifa.cm')->create([
        'slug' => 'chef-latifa',
    ]);

    $url = $tenant->getUrl();

    expect($url)->toBe('https://latifa.cm');
});

it('uses scheme from app URL configuration', function () {
    config(['app.url' => 'https://dmc.test']);

    $tenant = Tenant::factory()->create([
        'slug' => 'test-cook',
        'custom_domain' => null,
    ]);

    expect($tenant->getUrl())->toStartWith('https://');
});

it('prefers custom domain over subdomain when both exist', function () {
    $tenant = Tenant::factory()->withCustomDomain('custom.cm')->create([
        'slug' => 'my-cook',
    ]);

    $url = $tenant->getUrl();

    expect($url)->toBe('https://custom.cm')
        ->and($url)->not->toContain('my-cook');
});

// --- Cook Card View Data ---

it('resolves cook name via HasTranslatable trait in English', function () {
    app()->setLocale('en');
    $tenant = Tenant::factory()->create([
        'name_en' => 'Chef Latifa Kitchen',
        'name_fr' => 'La Cuisine de Chef Latifa',
    ]);

    expect($tenant->name)->toBe('Chef Latifa Kitchen');
});

it('resolves cook name via HasTranslatable trait in French', function () {
    app()->setLocale('fr');
    $tenant = Tenant::factory()->create([
        'name_en' => 'Chef Latifa Kitchen',
        'name_fr' => 'La Cuisine de Chef Latifa',
    ]);

    expect($tenant->name)->toBe('La Cuisine de Chef Latifa');
});

it('resolves description via HasTranslatable trait', function () {
    app()->setLocale('en');
    $tenant = Tenant::factory()->create([
        'description_en' => 'Authentic Cameroonian dishes',
        'description_fr' => 'Plats camerounais authentiques',
    ]);

    expect($tenant->description)->toBe('Authentic Cameroonian dishes');
});

it('returns null description when both translations are empty', function () {
    $tenant = Tenant::factory()->create([
        'description_en' => null,
        'description_fr' => null,
    ]);

    expect($tenant->description)->toBeNull();
});

it('generates initial from first character of name', function () {
    $tenant = Tenant::factory()->create([
        'name_en' => 'Latifa Kitchen',
    ]);

    $initial = mb_substr($tenant->name, 0, 1);

    expect(strtoupper($initial))->toBe('L');
});

// --- Cook Card Rendering ---

it('renders cook card with all required data fields', function () {
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create([
        'name_en' => 'Chef Amara',
        'name_fr' => 'Chef Amara',
        'description_en' => 'Best Ndole in town',
        'description_fr' => 'Meilleur Ndole en ville',
    ]);

    $html = view('discovery._cook-card', ['tenant' => $tenant])->render();

    // BR-074: Must display cook name, description
    expect($html)->toContain('Chef Amara')
        ->and($html)->toContain('Best Ndole in town');
});

it('renders placeholder when no cover images', function () {
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();

    $html = view('discovery._cook-card', ['tenant' => $tenant])->render();

    // BR-077: Branded placeholder shown with gradient and utensil icon
    expect($html)->toContain('bg-gradient-to-br')
        ->and($html)->toContain('radial-gradient');
});

it('renders "New" badge when no ratings', function () {
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();

    $html = view('discovery._cook-card', ['tenant' => $tenant])->render();

    // BR-078: Shows "New" instead of star rating
    expect($html)->toContain(__('New'));
});

it('renders default description when description is empty', function () {
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create([
        'description_en' => null,
        'description_fr' => null,
    ]);

    $html = view('discovery._cook-card', ['tenant' => $tenant])->render();

    expect($html)->toContain(__('Delicious home-cooked meals'));
});

it('renders card with clickable link to tenant URL', function () {
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create([
        'slug' => 'chef-test',
        'custom_domain' => null,
    ]);

    $html = view('discovery._cook-card', ['tenant' => $tenant])->render();
    $expectedUrl = $tenant->getUrl();

    // BR-076: Card navigates to tenant domain
    expect($html)->toContain($expectedUrl)
        ->and($html)->toContain('cursor-pointer');
});

it('renders card linking to custom domain when configured', function () {
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()
        ->withCook($cook->id)
        ->withCustomDomain('latifa.cm')
        ->create(['slug' => 'latifa']);

    $html = view('discovery._cook-card', ['tenant' => $tenant])->render();

    expect($html)->toContain('https://latifa.cm');
});

it('truncates long names with truncate class', function () {
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create([
        'name_en' => 'This Is An Extremely Long Cook Name That Should Be Truncated In The Card',
    ]);

    $html = view('discovery._cook-card', ['tenant' => $tenant])->render();

    // Uses truncate CSS class for single line truncation
    expect($html)->toContain('truncate');
});

it('truncates description to 2 lines with line-clamp', function () {
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create([
        'description_en' => 'A very long description that should be clipped to only 2 lines on the card display.',
    ]);

    $html = view('discovery._cook-card', ['tenant' => $tenant])->render();

    // BR-079: Description truncated to 2 lines
    expect($html)->toContain('line-clamp-2');
});

it('renders meal count of zero when no meals exist', function () {
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();

    $html = view('discovery._cook-card', ['tenant' => $tenant])->render();

    // Forward-compatible: shows 0 meals when meals table doesn't exist yet
    expect($html)->toContain('>0</span>');
});

it('has proper accessibility attributes', function () {
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create([
        'name_en' => 'Chef Amara',
    ]);

    $html = view('discovery._cook-card', ['tenant' => $tenant])->render();

    expect($html)->toContain('role="link"')
        ->and($html)->toContain('tabindex="0"')
        ->and($html)->toContain('aria-label');
});

it('includes hover and transition effects', function () {
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();

    $html = view('discovery._cook-card', ['tenant' => $tenant])->render();

    // Lift on hover, shadow transition
    expect($html)->toContain('hover:shadow-dropdown')
        ->and($html)->toContain('hover:-translate-y-1')
        ->and($html)->toContain('transition-all');
});

it('uses semantic color tokens and dark mode variants', function () {
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create();

    $html = view('discovery._cook-card', ['tenant' => $tenant])->render();

    expect($html)->toContain('bg-surface-alt')
        ->and($html)->toContain('dark:bg-surface-alt')
        ->and($html)->toContain('text-on-surface-strong')
        ->and($html)->toContain('border-outline')
        ->and($html)->toContain('dark:border-outline');
});

it('renders localized content in French', function () {
    app()->setLocale('fr');

    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create([
        'name_en' => 'Chef Amara Kitchen',
        'name_fr' => 'La Cuisine de Chef Amara',
        'description_en' => 'Best Cameroonian food',
        'description_fr' => 'Meilleure cuisine camerounaise',
    ]);

    $html = view('discovery._cook-card', ['tenant' => $tenant])->render();

    // BR-081: Shows French translations when locale is fr
    expect($html)->toContain('La Cuisine de Chef Amara')
        ->and($html)->toContain('Meilleure cuisine camerounaise');
});

// --- Discovery Page Integration ---

it('renders cook cards within discovery page grid', function () {
    $cook = $this->createUserWithRole('cook');
    $tenant = Tenant::factory()->withCook($cook->id)->create([
        'name_en' => 'Chef Powel Test',
    ]);

    $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);

    $response = $this->get('https://'.$mainDomain.'/');

    $response->assertOk();
    $response->assertSee('Chef Powel Test');
});
