<?php

namespace App\Models;

use App\Traits\HasTranslatable;
use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meal extends Model
{
    /** @use HasFactory<\Database\Factories\MealFactory> */
    use HasFactory, HasTranslatable, LogsActivityTrait, SoftDeletes;

    /**
     * Status constants.
     * BR-190: New meals default to "draft" status.
     */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_LIVE = 'live';

    /**
     * Valid statuses.
     *
     * @var array<string>
     */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_LIVE,
    ];

    /**
     * The table associated with the model.
     */
    protected $table = 'meals';

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
        'tenant_id',
        'name_en',
        'name_fr',
        'description_en',
        'description_fr',
        'price',
        'is_active',
        'status',
        'is_available',
        'estimated_prep_time',
        'position',
        'has_custom_locations',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'estimated_prep_time' => 'integer',
            'position' => 'integer',
            'has_custom_locations' => 'boolean',
        ];
    }

    /**
     * Get the tenant that owns this meal.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the components for this meal.
     */
    public function components(): HasMany
    {
        return $this->hasMany(MealComponent::class);
    }

    /**
     * Get the location overrides for this meal.
     *
     * F-096: Meal-Specific Location Override
     */
    public function locationOverrides(): HasMany
    {
        return $this->hasMany(MealLocationOverride::class);
    }

    /**
     * Scope to active meals.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to draft meals.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope to live meals.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_LIVE);
    }

    /**
     * Scope to available meals.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope to meals for a specific tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Check if the meal is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if the meal is live.
     */
    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    /**
     * Get the next position for a meal in this tenant.
     */
    public static function nextPositionForTenant(int $tenantId): int
    {
        return (int) static::forTenant($tenantId)->max('position') + 1;
    }
}
