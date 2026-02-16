<?php

namespace App\Models;

use App\Traits\HasTranslatable;
use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory, HasTranslatable, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'tenants';

    /**
     * Translatable attributes resolved by HasTranslatable trait.
     *
     * @var array<string>
     */
    protected array $translatable = ['name', 'description'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name_en',
        'name_fr',
        'custom_domain',
        'description_en',
        'description_fr',
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

    /**
     * Get a specific setting value from the tenant's settings JSON.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings;

        if (! is_array($settings)) {
            return $default;
        }

        return $settings[$key] ?? $default;
    }

    /**
     * Set a specific setting value in the tenant's settings JSON.
     */
    public function setSetting(string $key, mixed $value): static
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;

        return $this;
    }

    /**
     * Get the tenant's selected theme preset name.
     */
    public function getThemePreset(): ?string
    {
        $preset = $this->getSetting('theme');

        return is_string($preset) ? $preset : null;
    }

    /**
     * Get the tenant's selected font name.
     */
    public function getThemeFont(): ?string
    {
        $font = $this->getSetting('font');

        return is_string($font) ? $font : null;
    }

    /**
     * Get the tenant's selected border radius name.
     */
    public function getThemeBorderRadius(): ?string
    {
        $radius = $this->getSetting('border_radius');

        return is_string($radius) ? $radius : null;
    }
}
