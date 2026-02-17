<?php

namespace App\Models;

use App\Traits\HasTranslatable;
use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'cook_id',
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
     * Scope: search tenants by name (en/fr), subdomain, or custom domain.
     *
     * BR-065: Search covers name_en, name_fr, subdomain, custom_domain
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $term = '%'.mb_strtolower(trim($term)).'%';

        return $query->where(function (Builder $q) use ($term) {
            $q->whereRaw('LOWER(name_en) LIKE ?', [$term])
                ->orWhereRaw('LOWER(name_fr) LIKE ?', [$term])
                ->orWhereRaw('LOWER(slug) LIKE ?', [$term])
                ->orWhereRaw('LOWER(COALESCE(custom_domain, \'\')) LIKE ?', [$term]);
        });
    }

    /**
     * Scope: filter tenants by status.
     *
     * BR-066: Status filter options: All, Active, Inactive
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => $query,
        };
    }

    /**
     * Get the cook (user) assigned to this tenant.
     *
     * BR-082: Each tenant has exactly one cook at a time.
     */
    public function cook(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cook_id');
    }

    /**
     * Get the commission change history for this tenant.
     *
     * F-062: Commission Configuration per Cook
     */
    public function commissionChanges(): HasMany
    {
        return $this->hasMany(CommissionChange::class);
    }

    /**
     * Get the current commission rate for this tenant.
     *
     * BR-175: Default commission rate is 10%
     */
    public function getCommissionRate(): float
    {
        return (float) $this->getSetting('commission_rate', CommissionChange::DEFAULT_RATE);
    }

    /**
     * Check if the tenant has a custom commission rate (different from default).
     */
    public function hasCustomCommissionRate(): bool
    {
        return $this->getCommissionRate() !== CommissionChange::DEFAULT_RATE;
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
