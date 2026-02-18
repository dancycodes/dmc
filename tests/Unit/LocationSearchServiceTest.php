<?php

use App\Services\LocationSearchService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/*
|--------------------------------------------------------------------------
| F-097: OpenStreetMap Neighbourhood Search — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for the LocationSearchService which proxies requests to the
| OpenStreetMap Nominatim API for neighbourhood-level location autocomplete.
|
| Uses TestCase because the service depends on Laravel facades (Http,
| RateLimiter, Log, App).
|
*/

uses(Tests\TestCase::class);

beforeEach(function () {
    RateLimiter::clear('nominatim-api');
});

// -------------------------------------------------------------------
// BR-316: Minimum 3 characters
// -------------------------------------------------------------------

it('returns empty results when query is less than 3 characters', function () {
    $service = new LocationSearchService;

    $result = $service->search('ab');

    expect($result)
        ->toBeArray()
        ->success->toBeTrue()
        ->results->toBeArray()->toBeEmpty()
        ->error->toBe('');
});

it('returns empty results for empty query', function () {
    $service = new LocationSearchService;

    $result = $service->search('');

    expect($result)
        ->success->toBeTrue()
        ->results->toBeEmpty();
});

it('returns empty results for whitespace-only query', function () {
    $service = new LocationSearchService;

    $result = $service->search('   ');

    expect($result)
        ->success->toBeTrue()
        ->results->toBeEmpty();
});

it('trims whitespace from query before checking length', function () {
    $service = new LocationSearchService;

    // " ab " has only 2 non-whitespace characters
    $result = $service->search(' ab ');

    expect($result)
        ->success->toBeTrue()
        ->results->toBeEmpty();
});

// -------------------------------------------------------------------
// BR-315: Results scoped to Cameroon
// -------------------------------------------------------------------

it('sends countrycodes=cm parameter to Nominatim', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            [
                'display_name' => 'Douala, Littoral, Cameroon',
                'name' => 'Douala',
                'lat' => '4.0511',
                'lon' => '9.7679',
                'address' => [
                    'city' => 'Douala',
                    'state' => 'Littoral',
                    'country' => 'Cameroon',
                ],
            ],
        ], 200),
    ]);

    $service = new LocationSearchService;
    $service->search('Douala');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'countrycodes=cm')
            && str_contains($request->url(), 'format=jsonv2')
            && str_contains($request->url(), 'addressdetails=1')
            && str_contains($request->url(), 'limit=5');
    });
});

// -------------------------------------------------------------------
// BR-318: User-Agent header
// -------------------------------------------------------------------

it('sends a valid User-Agent header', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([], 200),
    ]);

    $service = new LocationSearchService;
    $service->search('Douala');

    Http::assertSent(function ($request) {
        return str_contains($request->header('User-Agent')[0] ?? '', 'DancyMeals');
    });
});

// -------------------------------------------------------------------
// Successful API response
// -------------------------------------------------------------------

it('returns formatted results for successful API response', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            [
                'display_name' => 'Bonaberi, Douala, Littoral, Cameroon',
                'name' => 'Bonaberi',
                'lat' => '4.0700',
                'lon' => '9.6900',
                'address' => [
                    'suburb' => 'Bonaberi',
                    'city' => 'Douala',
                    'state' => 'Littoral',
                    'country' => 'Cameroon',
                ],
            ],
            [
                'display_name' => 'Bonaberi Sector, Douala, Littoral, Cameroon',
                'name' => 'Bonaberi Sector',
                'lat' => '4.0710',
                'lon' => '9.6910',
                'address' => [
                    'neighbourhood' => 'Bonaberi Sector',
                    'city' => 'Douala',
                    'state' => 'Littoral',
                    'country' => 'Cameroon',
                ],
            ],
        ], 200),
    ]);

    $service = new LocationSearchService;
    $result = $service->search('Bonaberi');

    expect($result)
        ->success->toBeTrue()
        ->error->toBe('')
        ->results->toHaveCount(2);

    expect($result['results'][0])
        ->name->toBe('Bonaberi')
        ->area->toBe('Douala, Littoral')
        ->country->toBe('Cameroon')
        ->lat->toBe('4.0700')
        ->lon->toBe('9.6900');

    expect($result['results'][1])
        ->name->toBe('Bonaberi Sector');
});

it('returns up to 5 results maximum', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([], 200),
    ]);

    $service = new LocationSearchService;
    $service->search('Akwa Douala');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'limit=5');
    });
});

// -------------------------------------------------------------------
// No results
// -------------------------------------------------------------------

it('returns empty results when Nominatim returns no matches', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([], 200),
    ]);

    $service = new LocationSearchService;
    $result = $service->search('xyzabc');

    expect($result)
        ->success->toBeTrue()
        ->results->toBeEmpty()
        ->error->toBe('');
});

// -------------------------------------------------------------------
// BR-323: Graceful degradation
// -------------------------------------------------------------------

it('handles API timeout gracefully', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([], 500),
    ]);

    $service = new LocationSearchService;
    $result = $service->search('Douala');

    expect($result)
        ->success->toBeFalse()
        ->results->toBeEmpty()
        ->error->not->toBeEmpty();
});

it('handles connection exception gracefully', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        },
    ]);

    $service = new LocationSearchService;
    $result = $service->search('Douala');

    expect($result)
        ->success->toBeFalse()
        ->results->toBeEmpty()
        ->error->not->toBeEmpty();
});

it('handles unexpected exception gracefully', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => function () {
            throw new \RuntimeException('Unexpected error');
        },
    ]);

    $service = new LocationSearchService;
    $result = $service->search('Douala');

    expect($result)
        ->success->toBeFalse()
        ->results->toBeEmpty()
        ->error->not->toBeEmpty();
});

// -------------------------------------------------------------------
// BR-318: Rate limiting
// -------------------------------------------------------------------

it('returns rate limit error when too many requests are made', function () {
    // Hit the rate limiter until exhausted
    for ($i = 0; $i < 60; $i++) {
        RateLimiter::hit('nominatim-api', 60);
    }

    $service = new LocationSearchService;
    $result = $service->search('Douala');

    expect($result)
        ->success->toBeFalse()
        ->results->toBeEmpty()
        ->error->not->toBeEmpty();
});

// -------------------------------------------------------------------
// Result formatting
// -------------------------------------------------------------------

it('extracts primary name from result name field', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            [
                'display_name' => 'Akwa, Douala, Cameroon',
                'name' => 'Akwa',
                'lat' => '4.05',
                'lon' => '9.70',
                'address' => [
                    'city' => 'Douala',
                    'country' => 'Cameroon',
                ],
            ],
        ], 200),
    ]);

    $service = new LocationSearchService;
    $result = $service->search('Akwa');

    expect($result['results'][0]['name'])->toBe('Akwa');
});

it('falls back to address components when name is empty', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            [
                'display_name' => 'Some neighbourhood, Douala, Cameroon',
                'name' => '',
                'lat' => '4.05',
                'lon' => '9.70',
                'address' => [
                    'neighbourhood' => 'Akwa Nord',
                    'city' => 'Douala',
                    'state' => 'Littoral',
                    'country' => 'Cameroon',
                ],
            ],
        ], 200),
    ]);

    $service = new LocationSearchService;
    $result = $service->search('Akwa Nord');

    expect($result['results'][0]['name'])->toBe('Akwa Nord');
});

it('builds area from city and state', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            [
                'display_name' => 'Bastos, Yaounde, Centre, Cameroon',
                'name' => 'Bastos',
                'lat' => '3.88',
                'lon' => '11.50',
                'address' => [
                    'neighbourhood' => 'Bastos',
                    'city' => 'Yaounde',
                    'state' => 'Centre',
                    'country' => 'Cameroon',
                ],
            ],
        ], 200),
    ]);

    $service = new LocationSearchService;
    $result = $service->search('Bastos');

    expect($result['results'][0]['area'])->toBe('Yaounde, Centre');
});

it('handles results with minimal address details', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            [
                'display_name' => 'Cameroon',
                'name' => 'Cameroon',
                'lat' => '5.95',
                'lon' => '10.15',
                'address' => [
                    'country' => 'Cameroon',
                ],
            ],
        ], 200),
    ]);

    $service = new LocationSearchService;
    $result = $service->search('Cameroon');

    expect($result['results'][0])
        ->name->toBe('Cameroon')
        ->area->toBe('')
        ->country->toBe('Cameroon');
});

// -------------------------------------------------------------------
// UTF-8 / Non-Latin characters
// -------------------------------------------------------------------

it('handles non-Latin characters in search query', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([], 200),
    ]);

    $service = new LocationSearchService;
    $result = $service->search('Ngaoundéré');

    expect($result)
        ->success->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains(urldecode($request->url()), 'Ngaoundéré');
    });
});

// -------------------------------------------------------------------
// Multiple instances (BR edge case)
// -------------------------------------------------------------------

it('processes multiple sequential searches independently', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::sequence()
            ->push([
                ['display_name' => 'Result 1', 'name' => 'Douala', 'lat' => '4.0', 'lon' => '9.7', 'address' => ['city' => 'Douala', 'country' => 'Cameroon']],
            ], 200)
            ->push([
                ['display_name' => 'Result 2', 'name' => 'Yaounde', 'lat' => '3.8', 'lon' => '11.5', 'address' => ['city' => 'Yaounde', 'country' => 'Cameroon']],
            ], 200),
    ]);

    $service = new LocationSearchService;

    $result1 = $service->search('Douala');
    $result2 = $service->search('Yaounde');

    expect($result1['results'][0]['name'])->toBe('Douala');
    expect($result2['results'][0]['name'])->toBe('Yaounde');
});

// -------------------------------------------------------------------
// Accept-Language header
// -------------------------------------------------------------------

it('sends Accept-Language header based on current locale', function () {
    app()->setLocale('fr');

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([], 200),
    ]);

    $service = new LocationSearchService;
    $service->search('Douala');

    Http::assertSent(function ($request) {
        return ($request->header('Accept-Language')[0] ?? '') === 'fr';
    });
});

// -------------------------------------------------------------------
// Non-array API response
// -------------------------------------------------------------------

it('handles non-array API response gracefully', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response('not json', 200),
    ]);

    $service = new LocationSearchService;
    $result = $service->search('Douala');

    // The response()->json() will return null for invalid JSON
    // Our code checks is_array which will be false for null
    expect($result)
        ->success->toBeTrue()
        ->results->toBeEmpty();
});
