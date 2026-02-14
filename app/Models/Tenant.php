<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'tenants';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'custom_domain',
        'is_active',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * Reserved subdomains that cannot be used as tenant slugs.
     */
    public const RESERVED_SUBDOMAINS = [
        'www',
        'api',
        'mail',
        'admin',
        'staging',
        'dev',
        'test',
        'ftp',
        'smtp',
        'pop',
        'imap',
        'ns1',
        'ns2',
    ];

    /**
     * Scope: only active tenants.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Find a tenant by its slug (subdomain).
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Find a tenant by its custom domain.
     */
    public static function findByCustomDomain(string $domain): ?self
    {
        return static::where('custom_domain', $domain)->first();
    }

    /**
     * Check if a given subdomain is reserved.
     */
    public static function isReservedSubdomain(string $subdomain): bool
    {
        return in_array(strtolower($subdomain), static::RESERVED_SUBDOMAINS, true);
    }

    /**
     * Get the route key name for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
