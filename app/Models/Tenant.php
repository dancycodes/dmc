<?php

namespace App\Models;

use App\Traits\HasTranslatable;
use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Tenant extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory, HasTranslatable, InteractsWithMedia, LogsActivityTrait;

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
        'whatsapp',
        'phone',
        'social_facebook',
        'social_instagram',
        'social_tiktok',
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
     * Scope: discovery search across cook name, meal name, town name, and tags.
     *
     * BR-083: Search matches cook name (en/fr), meal name (en/fr), town name (en/fr), meal tags.
     * BR-084: Case-insensitive and accent-insensitive via PostgreSQL unaccent().
     * BR-085: Partial matching (contains, not exact).
     *
     * Forward-compatible: meal and tag search will activate when F-108/F-115 tables exist.
     */
    public function scopeDiscoverySearch(Builder $query, ?string $term): Builder
    {
        if (empty($term) || mb_strlen(trim($term)) < 2) {
            return $query;
        }

        $normalized = '%'.mb_strtolower(trim($term)).'%';

        return $query->where(function (Builder $q) use ($normalized) {
            // Search by cook/tenant name (en/fr) — accent-insensitive
            $q->whereRaw('LOWER(unaccent(name_en)) LIKE LOWER(unaccent(?))', [$normalized])
                ->orWhereRaw('LOWER(unaccent(name_fr)) LIKE LOWER(unaccent(?))', [$normalized])
                ->orWhereRaw('LOWER(unaccent(COALESCE(description_en, \'\'))) LIKE LOWER(unaccent(?))', [$normalized])
                ->orWhereRaw('LOWER(unaccent(COALESCE(description_fr, \'\'))) LIKE LOWER(unaccent(?))', [$normalized]);

            // Search by town name — tenants whose cooks deliver to matching towns
            // Towns are linked via addresses table (user addresses reference towns)
            // However, the direct tenant-town relationship comes via delivery areas (F-074)
            // For now, search towns directly and match tenants in those towns
            if (\Schema::hasTable('towns')) {
                $q->orWhereExists(function ($sub) use ($normalized) {
                    $sub->select(\DB::raw(1))
                        ->from('towns')
                        ->where('towns.is_active', true)
                        ->where(function ($tw) use ($normalized) {
                            $tw->whereRaw('LOWER(unaccent(towns.name_en)) LIKE LOWER(unaccent(?))', [$normalized])
                                ->orWhereRaw('LOWER(unaccent(towns.name_fr)) LIKE LOWER(unaccent(?))', [$normalized]);
                        });

                    // When delivery_areas table exists (F-074), link via tenant_id
                    if (\Schema::hasTable('delivery_areas')) {
                        $sub->whereRaw('delivery_areas.tenant_id = tenants.id')
                            ->join('delivery_areas', 'delivery_areas.town_id', '=', 'towns.id');
                    } else {
                        // Fallback: match any tenant if town name matches (broad match)
                        // This will be tightened when delivery areas are implemented
                    }
                });
            }

            // Forward-compatible: search by meal name when meals table exists (F-108)
            if (\Schema::hasTable('meals')) {
                $q->orWhereExists(function ($sub) use ($normalized) {
                    $sub->select(\DB::raw(1))
                        ->from('meals')
                        ->whereRaw('meals.tenant_id = tenants.id')
                        ->where('meals.is_active', true)
                        ->where(function ($mw) use ($normalized) {
                            $mw->whereRaw('LOWER(unaccent(meals.name_en)) LIKE LOWER(unaccent(?))', [$normalized])
                                ->orWhereRaw('LOWER(unaccent(meals.name_fr)) LIKE LOWER(unaccent(?))', [$normalized]);
                        });
                });
            }

            // Forward-compatible: search by tag name when tags/meal_tag tables exist (F-115)
            if (\Schema::hasTable('tags') && \Schema::hasTable('meal_tag')) {
                $q->orWhereExists(function ($sub) use ($normalized) {
                    $sub->select(\DB::raw(1))
                        ->from('tags')
                        ->join('meal_tag', 'meal_tag.tag_id', '=', 'tags.id')
                        ->join('meals', 'meals.id', '=', 'meal_tag.meal_id')
                        ->whereRaw('meals.tenant_id = tenants.id')
                        ->where(function ($tw) use ($normalized) {
                            $tw->whereRaw('LOWER(unaccent(tags.name_en)) LIKE LOWER(unaccent(?))', [$normalized])
                                ->orWhereRaw('LOWER(unaccent(tags.name_fr)) LIKE LOWER(unaccent(?))', [$normalized]);
                        });
                });
            }
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
     * Get the delivery areas for this tenant.
     *
     * F-074: Delivery Areas Step
     */
    public function deliveryAreas(): HasMany
    {
        return $this->hasMany(DeliveryArea::class);
    }

    /**
     * Get the pickup locations for this tenant.
     *
     * F-074: Delivery Areas Step
     */
    public function pickupLocations(): HasMany
    {
        return $this->hasMany(PickupLocation::class);
    }

    /**
     * Get the schedules for this tenant.
     *
     * F-075: Schedule & First Meal Step
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Get the meals for this tenant.
     *
     * F-075: Schedule & First Meal Step
     */
    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
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
     * Get the full URL for this tenant's site.
     *
     * BR-076: Uses custom_domain if configured, otherwise subdomain.
     * F-067: Cook Card Component uses this for card click navigation.
     */
    public function getUrl(): string
    {
        if (! empty($this->custom_domain)) {
            return 'https://'.$this->custom_domain;
        }

        $mainHost = parse_url(config('app.url'), PHP_URL_HOST);
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'https';

        return $scheme.'://'.$this->slug.'.'.$mainHost;
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
     * Check if the tenant has completed its setup.
     *
     * BR-163: If tenant setup is incomplete, a setup completion banner is shown
     * at the top of every dashboard page.
     */
    public function isSetupComplete(): bool
    {
        return (bool) $this->getSetting('setup_complete', false);
    }

    /**
     * Get the completed setup steps for this tenant.
     *
     * BR-114: Step progress is persisted in settings JSON under 'setup_steps'.
     *
     * @return array<int>
     */
    public function getCompletedSetupSteps(): array
    {
        return (array) $this->getSetting('setup_steps', []);
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

    /**
     * Register media collections for cover images.
     *
     * F-073: Cover Images Step
     * BR-127: Maximum 5 cover images per cook.
     * BR-128: Accepted formats: JPEG, PNG, WebP.
     * BR-129: Maximum file size: 2MB per image.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover-images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('public');
    }

    /**
     * Register media conversions for cover images.
     *
     * BR-130: Images are resized to a consistent 16:9 aspect ratio.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->fit(Fit::Crop, 400, 225)
            ->nonQueued();

        $this->addMediaConversion('carousel')
            ->fit(Fit::Crop, 1200, 675)
            ->nonQueued();
    }
}
