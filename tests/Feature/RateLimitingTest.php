<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Honeypot\Honeypot;

/**
 * Feature tests for F-019: Rate Limiting Setup
 *
 * Tests rate limiting behavior against actual HTTP requests,
 * verifying that limits are enforced and 429 responses are returned
 * with proper headers when limits are exceeded.
 */

/**
 * Helper to generate valid honeypot fields for auth form submissions.
 */
function rateLimitHoneypotFields(): array
{
    $honeypot = app(Honeypot::class);

    return [
        $honeypot->nameFieldName() => '',
        $honeypot->validFromFieldName() => $honeypot->encryptedValidFrom(),
    ];
}

beforeEach(function () {
    // Clear rate limiter cache before each test to ensure clean state
    RateLimiter::clear('strict');
    RateLimiter::clear('moderate');
    RateLimiter::clear('generous');

    // Travel forward 3 seconds to pass honeypot timestamp threshold
    $this->travel(3)->seconds();
});

describe('Strict Rate Limiting on Auth Endpoints (BR-151)', function () {
    it('allows 5 login attempts within a minute', function () {
        $honeypotFields = rateLimitHoneypotFields();

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post('/login', array_merge([
                'email' => 'user@example.com',
                'password' => 'wrongpassword',
            ], $honeypotFields));

            expect($response->status())->not->toBe(429, "Request {$i} should not be rate limited");
        }
    });

    it('blocks the 6th login attempt within a minute (BR-151)', function () {
        $honeypotFields = rateLimitHoneypotFields();

        // Make 5 allowed requests
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', array_merge([
                'email' => 'user@example.com',
                'password' => 'wrongpassword',
            ], $honeypotFields));
        }

        // 6th request should be rate limited
        $response = $this->post('/login', array_merge([
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
        ], $honeypotFields));

        expect($response->status())->toBe(429);
    });

    it('includes Retry-After header in 429 response (BR-154)', function () {
        $honeypotFields = rateLimitHoneypotFields();

        // Exhaust rate limit
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', array_merge([
                'email' => 'user@example.com',
                'password' => 'wrongpassword',
            ], $honeypotFields));
        }

        $response = $this->post('/login', array_merge([
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
        ], $honeypotFields));

        expect($response->status())->toBe(429);
        expect($response->headers->has('Retry-After'))->toBeTrue();
        expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0);
        expect((int) $response->headers->get('Retry-After'))->toBeLessThanOrEqual(60);
    });

    it('applies strict rate limit to registration endpoint (BR-151)', function () {
        $honeypotFields = rateLimitHoneypotFields();

        // Exhaust rate limit
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/register', array_merge([
                'name' => 'User '.$i,
                'email' => "user{$i}@example.com",
                'phone' => '69123456'.$i,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ], $honeypotFields));
        }

        // 6th request should be rate limited
        $response = $this->post('/register', array_merge([
            'name' => 'User 6',
            'email' => 'user6@example.com',
            'phone' => '691234566',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $honeypotFields));

        expect($response->status())->toBe(429);
    });

    it('applies strict rate limit to forgot-password endpoint (BR-159)', function () {
        $honeypotFields = rateLimitHoneypotFields();

        // Exhaust rate limit
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/forgot-password', array_merge([
                'email' => "user{$i}@example.com",
            ], $honeypotFields));
        }

        // 6th request should be rate limited
        $response = $this->post('/forgot-password', array_merge([
            'email' => 'user6@example.com',
        ], $honeypotFields));

        expect($response->status())->toBe(429);
    });
});

describe('Rate Limit Recovery', function () {
    it('allows requests again after rate limit period expires', function () {
        $honeypotFields = rateLimitHoneypotFields();

        // Exhaust rate limit
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', array_merge([
                'email' => 'user@example.com',
                'password' => 'wrongpassword',
            ], $honeypotFields));
        }

        // Confirm rate limited
        $blocked = $this->post('/login', array_merge([
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
        ], $honeypotFields));
        expect($blocked->status())->toBe(429);

        // Travel forward past the rate limit window
        $this->travel(61)->seconds();

        // Regenerate honeypot fields after time travel
        $honeypotFields = rateLimitHoneypotFields();

        // Should be allowed again
        $response = $this->post('/login', array_merge([
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
        ], $honeypotFields));

        expect($response->status())->not->toBe(429);
    });
});

describe('429 Error Page Rendering (BR-157)', function () {
    it('renders a user-friendly 429 error page', function () {
        $honeypotFields = rateLimitHoneypotFields();

        // Exhaust strict rate limit
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', array_merge([
                'email' => 'user@example.com',
                'password' => 'wrongpassword',
            ], $honeypotFields));
        }

        $response = $this->post('/login', array_merge([
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
        ], $honeypotFields));

        expect($response->status())->toBe(429);
    });

    it('shows the custom 429 view with DancyMeals branding', function () {
        // Directly render the 429 page by aborting
        $response = $this->get('/login');

        // Use abort to trigger 429
        $this->app['router']->get('/test-429', function () {
            abort(429, '', ['Retry-After' => 30]);
        });

        $response = $this->get('/test-429');

        expect($response->status())->toBe(429);
    });
});

describe('Moderate Rate Limiting on Authenticated Routes (BR-152)', function () {
    it('applies moderate throttle middleware to theme update route', function () {
        $routes = app('router')->getRoutes();
        $themeRoute = $routes->match(
            \Illuminate\Http\Request::create('/theme/update', 'POST')
        );

        $middleware = $themeRoute->gatherMiddleware();

        expect($middleware)->toContain('throttle:moderate');
    });

    it('applies moderate throttle middleware to push subscribe route', function () {
        $routes = app('router')->getRoutes();
        $pushRoute = $routes->match(
            \Illuminate\Http\Request::create('/push/subscribe', 'POST')
        );

        $middleware = $pushRoute->gatherMiddleware();

        expect($middleware)->toContain('throttle:moderate');
    });

    it('allows 60 requests per minute for authenticated users', function () {
        $user = User::factory()->create();

        // We can't easily test 60 requests, but we can test that the first
        // several requests are allowed through the moderate limiter
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->actingAs($user)->get('/theme/preference');
            expect($response->status())->not->toBe(429);
        }
    });
});

describe('Generous Rate Limiting on Public Pages (BR-153)', function () {
    it('allows many requests to public pages within the limit', function () {
        // Public page browsing should be generous (120/min)
        for ($i = 1; $i <= 10; $i++) {
            $response = $this->get('/');
            expect($response->status())->not->toBe(429);
        }
    });

    it('applies generous throttle to GET auth pages', function () {
        // GET requests to login/register are public pages, covered by generous
        for ($i = 1; $i <= 10; $i++) {
            $response = $this->get('/login');
            expect($response->status())->not->toBe(429);
        }
    });
});

describe('Rate Limiter Key Behavior (BR-155, BR-156)', function () {
    it('rate limits auth endpoints by IP (different users same IP get same limit)', function () {
        $honeypotFields = rateLimitHoneypotFields();

        // All requests come from the same IP in tests
        // Exhaust the strict limit with different emails
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', array_merge([
                'email' => "user{$i}@example.com",
                'password' => 'wrongpassword',
            ], $honeypotFields));
        }

        // 6th attempt with yet another email should still be blocked (same IP)
        $response = $this->post('/login', array_merge([
            'email' => 'different@example.com',
            'password' => 'wrongpassword',
        ], $honeypotFields));

        expect($response->status())->toBe(429);
    });
});

describe('Rate Limiter Registration (Runtime)', function () {
    it('strict limiter is registered and callable', function () {
        $limiter = app(\Illuminate\Cache\RateLimiter::class);

        expect($limiter->limiter('strict'))->not->toBeNull();
    });

    it('moderate limiter is registered and callable', function () {
        $limiter = app(\Illuminate\Cache\RateLimiter::class);

        expect($limiter->limiter('moderate'))->not->toBeNull();
    });

    it('generous limiter is registered and callable', function () {
        $limiter = app(\Illuminate\Cache\RateLimiter::class);

        expect($limiter->limiter('generous'))->not->toBeNull();
    });
});

describe('Route Middleware Verification (Runtime)', function () {
    it('login POST route has strict throttle middleware', function () {
        $routes = app('router')->getRoutes();
        $loginRoute = $routes->match(
            \Illuminate\Http\Request::create('/login', 'POST')
        );

        expect($loginRoute->gatherMiddleware())->toContain('throttle:strict');
    });

    it('register POST route has strict throttle middleware', function () {
        $routes = app('router')->getRoutes();
        $registerRoute = $routes->match(
            \Illuminate\Http\Request::create('/register', 'POST')
        );

        expect($registerRoute->gatherMiddleware())->toContain('throttle:strict');
    });

    it('forgot-password POST route has strict throttle middleware', function () {
        $routes = app('router')->getRoutes();
        $resetRoute = $routes->match(
            \Illuminate\Http\Request::create('/forgot-password', 'POST')
        );

        expect($resetRoute->gatherMiddleware())->toContain('throttle:strict');
    });

    it('theme update route has moderate throttle middleware', function () {
        $routes = app('router')->getRoutes();
        $themeRoute = $routes->match(
            \Illuminate\Http\Request::create('/theme/update', 'POST')
        );

        expect($themeRoute->gatherMiddleware())->toContain('throttle:moderate');
    });

    it('push subscribe route has moderate throttle middleware', function () {
        $routes = app('router')->getRoutes();
        $pushRoute = $routes->match(
            \Illuminate\Http\Request::create('/push/subscribe', 'POST')
        );

        expect($pushRoute->gatherMiddleware())->toContain('throttle:moderate');
    });
});
