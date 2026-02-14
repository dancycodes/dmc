<?php

namespace App\Services;

use App\Models\Tenant;

class TenantService
{
    /**
     * The resolved tenant for the current request.
     */
    private ?Tenant $tenant = null;

    /**
     * Whether resolution has been performed.
     */
    private bool $resolved = false;

    /**
     * Set the current tenant.
     */
    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->resolved = true;
    }

    /**
     * Get the current tenant, or null if on the main domain.
     */
    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Check if a tenant has been resolved (even if null for main domain).
     */
    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /**
     * Check if the current request is on a tenant domain.
     */
    public function isTenantDomain(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Check if the current request is on the main domain.
     */
    public function isMainDomain(): bool
    {
        return $this->resolved && $this->tenant === null;
    }

    /**
     * Get the main application domain from config.
     */
    public static function mainDomain(): string
    {
        $url = config('app.url', 'http://localhost');

        return parse_url($url, PHP_URL_HOST) ?? 'localhost';
    }

    /**
     * Extract the subdomain from a hostname, relative to the main domain.
     *
     * Returns null if the hostname IS the main domain or does not end with the main domain.
     */
    public static function extractSubdomain(string $hostname): ?string
    {
        $mainDomain = static::mainDomain();
        $hostname = strtolower($hostname);
        $mainDomain = strtolower($mainDomain);

        // Exact match means main domain
        if ($hostname === $mainDomain) {
            return null;
        }

        // Check if hostname ends with .mainDomain
        $suffix = '.'.$mainDomain;
        if (str_ends_with($hostname, $suffix)) {
            $subdomain = substr($hostname, 0, -strlen($suffix));

            // Only first-level subdomains (no dots in subdomain)
            if (! str_contains($subdomain, '.') && $subdomain !== '') {
                return $subdomain;
            }
        }

        return null;
    }

    /**
     * Determine if a hostname is an IP address (not a domain name).
     */
    public static function isIpAddress(string $hostname): bool
    {
        return filter_var($hostname, FILTER_VALIDATE_IP) !== false;
    }
}
