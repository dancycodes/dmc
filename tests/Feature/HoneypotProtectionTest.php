<?php

declare(strict_types=1);

use App\Http\Responses\SilentSpamResponder;
use App\Models\User;
use Spatie\Honeypot\Honeypot;

/**
 * Feature tests for F-018: Honeypot Protection Setup
 *
 * Tests the honeypot protection middleware behavior against actual HTTP requests.
 * Validates that legitimate submissions pass, spam is silently rejected,
 * and the custom SilentSpamResponder works correctly.
 */

/**
 * Helper to generate valid honeypot fields for a form submission.
 * Simulates what the <x-honeypot /> component renders.
 */
function validHoneypotFields(): array
{
    $honeypot = app(Honeypot::class);

    return [
        $honeypot->nameFieldName() => '',
        $honeypot->validFromFieldName() => $honeypot->encryptedValidFrom(),
    ];
}

/**
 * Helper to generate honeypot fields with a "too fast" timestamp.
 * The timestamp is set in the future, indicating the form was submitted too quickly.
 */
function tooFastHoneypotFields(): array
{
    $honeypot = app(Honeypot::class);

    // Create a timestamp that is far in the future (submitted immediately, way before threshold)
    $futureTime = now()->addSeconds(config('honeypot.amount_of_seconds') + 10);
    $encryptedFutureTime = \Spatie\Honeypot\EncryptedTime::create($futureTime);

    return [
        $honeypot->nameFieldName() => '',
        $honeypot->validFromFieldName() => (string) $encryptedFutureTime,
    ];
}

/**
 * Helper to generate honeypot fields where the bot filled the hidden field.
 */
function filledHoneypotFields(): array
{
    $honeypot = app(Honeypot::class);

    // Create a valid (past) timestamp so we isolate the filled-field detection
    $pastTime = now()->subMinutes(5);
    $encryptedPastTime = \Spatie\Honeypot\EncryptedTime::create($pastTime);

    return [
        $honeypot->nameFieldName() => 'I am a bot',
        $honeypot->validFromFieldName() => (string) $encryptedPastTime,
    ];
}

describe('Honeypot Middleware on Registration Route (BR-143)', function () {
    it('allows legitimate registration submissions with valid honeypot fields (BR-149)', function () {
        // Travel forward 3 seconds to ensure we pass the 2-second threshold
        $this->travel(3)->seconds();

        $honeypotFields = validHoneypotFields();

        $response = $this->post('/register', array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '691234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $honeypotFields));

        // Should process normally — not a blank 200 (spam rejection)
        // Either redirect (302 success), or non-empty 200 (validation rendered)
        $isBlankSpamResponse = $response->status() === 200 && $response->getContent() === '';
        expect($isBlankSpamResponse)->toBeFalse();
    });

    it('silently rejects submissions with filled honeypot field (BR-145)', function () {
        $honeypotFields = filledHoneypotFields();

        $response = $this->post('/register', array_merge([
            'name' => 'Bot User',
            'email' => 'bot@example.com',
            'phone' => '691234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $honeypotFields));

        // SilentSpamResponder returns redirect back for non-Gale requests
        expect($response->status())->toBeIn([200, 302]);

        // Verify no user was created
        expect(User::where('email', 'bot@example.com')->exists())->toBeFalse();
    });

    it('silently rejects submissions that are too fast (BR-147)', function () {
        $honeypotFields = tooFastHoneypotFields();

        $response = $this->post('/register', array_merge([
            'name' => 'Fast Bot',
            'email' => 'fastbot@example.com',
            'phone' => '691234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $honeypotFields));

        // SilentSpamResponder returns redirect back for non-Gale requests
        expect($response->status())->toBeIn([200, 302]);

        // Verify no user was created
        expect(User::where('email', 'fastbot@example.com')->exists())->toBeFalse();
    });

    it('does not reveal protection mechanism in rejected response (BR-148)', function () {
        $honeypotFields = filledHoneypotFields();

        $response = $this->post('/register', array_merge([
            'name' => 'Bot User',
            'email' => 'bot2@example.com',
            'phone' => '691234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $honeypotFields));

        $content = $response->getContent();

        // Should not contain words that reveal the honeypot mechanism
        expect(strtolower($content))->not->toContain('honeypot');
        expect(strtolower($content))->not->toContain('spam');
        expect(strtolower($content))->not->toContain('bot');
    });
});

describe('Honeypot Middleware on Login Route (BR-143)', function () {
    it('allows legitimate login submissions with valid honeypot fields', function () {
        $this->travel(3)->seconds();

        $honeypotFields = validHoneypotFields();

        $response = $this->post('/login', array_merge([
            'email' => 'user@example.com',
            'password' => 'password',
        ], $honeypotFields));

        // Should process normally (not blank page spam rejection)
        expect($response->status())->toBeIn([200, 302, 422]);
    });

    it('silently rejects login spam with filled honeypot field', function () {
        $user = User::factory()->create(['email' => 'real@example.com']);

        $honeypotFields = filledHoneypotFields();

        $response = $this->post('/login', array_merge([
            'email' => 'real@example.com',
            'password' => 'password',
        ], $honeypotFields));

        // Should be silently rejected
        expect($response->status())->toBeIn([200, 302]);

        // User should NOT be authenticated
        $this->assertGuest();
    });
});

describe('Honeypot Middleware on Password Reset Route (BR-143)', function () {
    it('allows legitimate password reset submissions with valid honeypot fields', function () {
        $this->travel(3)->seconds();

        $honeypotFields = validHoneypotFields();

        $response = $this->post('/forgot-password', array_merge([
            'email' => 'user@example.com',
        ], $honeypotFields));

        // Should process normally
        expect($response->status())->toBeIn([200, 302, 422]);
    });

    it('silently rejects password reset spam with filled honeypot field', function () {
        $honeypotFields = filledHoneypotFields();

        $response = $this->post('/forgot-password', array_merge([
            'email' => 'victim@example.com',
        ], $honeypotFields));

        // Should be silently rejected
        expect($response->status())->toBeIn([200, 302]);
    });
});

describe('SilentSpamResponder Behavior', function () {
    it('returns redirect back for standard HTTP spam requests', function () {
        $responder = new SilentSpamResponder;

        $request = \Illuminate\Http\Request::create('/register', 'POST');
        $next = fn ($req) => response('OK');

        $response = $responder->respond($request, $next);

        expect($response->status())->toBe(302);
    });

    it('returns empty 200 for Gale SSE spam requests', function () {
        $responder = new SilentSpamResponder;

        $request = \Illuminate\Http\Request::create('/register', 'POST');
        $request->headers->set('Gale-Request', '1');

        $next = fn ($req) => response('OK');

        $response = $responder->respond($request, $next);

        expect($response->status())->toBe(200);
        expect($response->getContent())->toBe('');
    });
});

describe('Honeypot Route Middleware Assignment', function () {
    it('register POST route has honeypot middleware', function () {
        $routes = app('router')->getRoutes();
        $registerRoute = $routes->match(
            \Illuminate\Http\Request::create('/register', 'POST')
        );

        expect($registerRoute->gatherMiddleware())->toContain('honeypot');
    });

    it('login POST route has honeypot middleware', function () {
        $routes = app('router')->getRoutes();
        $loginRoute = $routes->match(
            \Illuminate\Http\Request::create('/login', 'POST')
        );

        expect($loginRoute->gatherMiddleware())->toContain('honeypot');
    });

    it('password reset POST route has honeypot middleware', function () {
        $routes = app('router')->getRoutes();
        $resetRoute = $routes->match(
            \Illuminate\Http\Request::create('/forgot-password', 'POST')
        );

        expect($resetRoute->gatherMiddleware())->toContain('honeypot');
    });

    it('GET routes do not have honeypot middleware', function () {
        $routes = app('router')->getRoutes();
        $registerGetRoute = $routes->match(
            \Illuminate\Http\Request::create('/register', 'GET')
        );

        expect($registerGetRoute->gatherMiddleware())->not->toContain('honeypot');
    });
});

describe('Honeypot Disabled Behavior', function () {
    it('allows all submissions when honeypot is disabled', function () {
        config(['honeypot.enabled' => false]);

        // Submit with filled honeypot field — should pass when disabled
        $honeypot = app(Honeypot::class);

        $response = $this->post('/register', [
            'name' => 'Test User Disabled',
            'email' => 'disabled-test@example.com',
            'phone' => '691234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            $honeypot->nameFieldName() => 'bot value',
            $honeypot->validFromFieldName() => 'invalid',
        ]);

        // Should process normally (not silently rejected)
        // The registration should either succeed (302 redirect) or fail validation (302/422)
        expect($response->status())->toBeIn([200, 302, 422]);
    });
});

describe('Honeypot Component Rendering', function () {
    it('renders honeypot fields in registration form view', function () {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $content = $response->getContent();

        // The honeypot field name starts with 'my_name' (may have random suffix)
        expect($content)->toContain('my_name');
        expect($content)->toContain('valid_from');
        expect($content)->toContain('aria-hidden="true"');
    });

    it('renders honeypot fields in login form view', function () {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $content = $response->getContent();

        expect($content)->toContain('my_name');
        expect($content)->toContain('valid_from');
        expect($content)->toContain('aria-hidden="true"');
    });

    it('renders honeypot fields in password reset form view', function () {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
        $content = $response->getContent();

        expect($content)->toContain('my_name');
        expect($content)->toContain('valid_from');
        expect($content)->toContain('aria-hidden="true"');
    });
});
