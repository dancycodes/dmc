<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CrossDomainSessionService
{
    /**
     * Token prefix used in cache keys for cross-domain auth tokens.
     */
    private const TOKEN_PREFIX = 'cross-domain-token:';

    /**
     * Token expiry in seconds (5 minutes — short-lived for security).
     */
    private const TOKEN_TTL = 300;

    /**
     * Generate a one-time cross-domain authentication token for the given user.
     *
     * The token is stored in cache and can only be used once. It allows a user
     * authenticated on one domain (e.g., dmc.test) to be authenticated on a
     * custom domain (e.g., latifa.cm) without re-entering credentials.
     *
     * @param  User  $user  The authenticated user
     * @return string The generated token
     */
    public function generateToken(User $user): string
    {
        $token = Str::random(64);

        Cache::put(
            self::TOKEN_PREFIX.$token,
            [
                'user_id' => $user->id,
                'created_at' => now()->timestamp,
            ],
            self::TOKEN_TTL,
        );

        return $token;
    }

    /**
     * Validate and consume a cross-domain authentication token.
     *
     * Returns the associated User if the token is valid and not expired.
     * The token is deleted after use (one-time use) to prevent replay attacks.
     *
     * @param  string  $token  The token to validate
     * @return User|null The authenticated user, or null if token is invalid/expired
     */
    public function validateAndConsumeToken(string $token): ?User
    {
        $cacheKey = self::TOKEN_PREFIX.$token;
        $data = Cache::get($cacheKey);

        if ($data === null) {
            return null;
        }

        // Consume the token immediately (one-time use)
        Cache::forget($cacheKey);

        $userId = $data['user_id'] ?? null;

        if ($userId === null) {
            return null;
        }

        return User::find($userId);
    }

    /**
     * Build the cross-domain redirect URL for a custom domain.
     *
     * Generates a URL on the target domain that includes the authentication
     * token as a query parameter. The target domain's controller will validate
     * the token and establish a session.
     *
     * @param  string  $targetDomain  The custom domain to redirect to (e.g., latifa.cm)
     * @param  string  $token  The cross-domain auth token
     * @param  string  $intendedPath  The path the user originally requested (default: /)
     * @return string The full redirect URL
     */
    public function buildRedirectUrl(string $targetDomain, string $token, string $intendedPath = '/'): string
    {
        $scheme = config('app.env') === 'production' ? 'https' : parse_url(config('app.url'), PHP_URL_SCHEME);

        return $scheme.'://'.$targetDomain.'/cross-domain-auth?token='.urlencode($token).'&intended='.urlencode($intendedPath);
    }

    /**
     * Determine if a hostname is a subdomain of the main application domain.
     *
     * Subdomains share session cookies via the SESSION_DOMAIN configuration
     * (e.g., .dmc.test) and do not need token-based authentication.
     *
     * @param  string  $hostname  The hostname to check
     * @return bool True if the hostname is a subdomain of the main domain
     */
    public function isSubdomain(string $hostname): bool
    {
        return TenantService::extractSubdomain($hostname) !== null;
    }

    /**
     * Determine if a hostname is a custom domain (not the main domain or a subdomain).
     *
     * Custom domains require token-based authentication because they do not
     * share the session cookie domain.
     *
     * @param  string  $hostname  The hostname to check
     * @return bool True if the hostname is a custom domain
     */
    public function isCustomDomain(string $hostname): bool
    {
        $mainDomain = strtolower(TenantService::mainDomain());
        $hostname = strtolower($hostname);

        // Not the main domain and not a subdomain = custom domain
        return $hostname !== $mainDomain && ! $this->isSubdomain($hostname);
    }

    /**
     * Get the session cookie domain from configuration.
     *
     * Returns the configured SESSION_DOMAIN value which should have a leading dot
     * for subdomain sharing (e.g., .dmc.test for local, .dancymeals.com for production).
     *
     * @return string|null The session cookie domain, or null if not configured
     */
    public function getSessionCookieDomain(): ?string
    {
        $domain = config('session.domain');

        // Convert string "null" to actual null
        if ($domain === 'null' || $domain === '') {
            return null;
        }

        return $domain;
    }
}
