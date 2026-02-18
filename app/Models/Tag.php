<?php

namespace App\Models;

use App\Traits\HasTranslatable;
use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    /** @use HasFactory<\Database\Factories\TagFactory> */
    use HasFactory, HasTranslatable, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'tags';

    /**
     * BR-259: Max length for tag name per language.
     */
    public const NAME_MAX_LENGTH = 50;

    /**
     * Translatable attributes resolved by HasTranslatable trait.
     *
     * @var array<string>
     */
    protected array $translatable = ['name'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name_en',
        'name_fr',
    ];

    /**
     * Get the tenant that owns this tag.
     * BR-252: Tags are tenant-scoped.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the meals that have this tag.
     * Tags belongsToMany Meals via meal_tag pivot.
     */
    public function meals(): BelongsToMany
    {
        return $this->belongsToMany(Meal::class, 'meal_tag')->withTimestamps();
    }

    /**
     * Scope to tags for a specific tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Get the count of meals using this tag.
     * BR-255: Tags cannot be deleted if assigned to any meal.
     */
    public function getMealCountAttribute(): int
    {
        return $this->meals()->count();
    }

    /**
     * Check if this tag is currently assigned to any meals.
     */
    public function isInUse(): bool
    {
        return $this->meals()->exists();
    }
}
