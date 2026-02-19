<?php

namespace App\Models;

use App\Traits\HasTranslatable;
use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class SellingUnit extends Model
{
    /** @use HasFactory<\Database\Factories\SellingUnitFactory> */
    use HasFactory, HasTranslatable, LogsActivityTrait;

    /**
     * BR-306: Standard selling units pre-seeded.
     *
     * @var array<string, array{en: string, fr: string}>
     */
    public const STANDARD_UNITS = [
        'plate' => ['en' => 'Plate', 'fr' => 'Assiette'],
        'bowl' => ['en' => 'Bowl', 'fr' => 'Bol'],
        'pot' => ['en' => 'Pot', 'fr' => 'Marmite'],
        'cup' => ['en' => 'Cup', 'fr' => 'Tasse'],
        'piece' => ['en' => 'Piece', 'fr' => 'Piece'],
        'portion' => ['en' => 'Portion', 'fr' => 'Portion'],
        'serving' => ['en' => 'Serving', 'fr' => 'Service'],
        'pack' => ['en' => 'Pack', 'fr' => 'Paquet'],
    ];

    /**
     * BR-314: Custom unit name max length per language.
     */
    public const NAME_MAX_LENGTH = 50;

    /**
     * The table associated with the model.
     */
    protected $table = 'selling_units';

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
        'is_standard',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_standard' => 'boolean',
        ];
    }

    /**
     * Get the tenant that owns this selling unit (nullable for standard units).
     *
     * BR-308: Custom units are tenant-scoped.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the meal components that use this selling unit.
     */
    public function mealComponents(): HasMany
    {
        return $this->hasMany(MealComponent::class, 'selling_unit', 'id');
    }

    /**
     * Scope to standard units only.
     */
    public function scopeStandard(Builder $query): Builder
    {
        return $query->where('is_standard', true);
    }

    /**
     * Scope to custom units only.
     */
    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('is_standard', false);
    }

    /**
     * Scope to units for a specific tenant (includes standard units).
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where(function (Builder $q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)
                ->orWhere('is_standard', true);
        });
    }

    /**
     * Check if this is a standard unit.
     */
    public function isStandard(): bool
    {
        return $this->is_standard;
    }

    /**
     * Check if this is a custom unit.
     */
    public function isCustom(): bool
    {
        return ! $this->is_standard;
    }

    /**
     * Check if this unit is in use by any meal component.
     *
     * BR-311: Custom units cannot be deleted if used by any meal component.
     */
    public function isInUse(): bool
    {
        if (! Schema::hasTable('meal_components')) {
            return false;
        }

        return MealComponent::where('selling_unit', (string) $this->id)->exists();
    }

    /**
     * Get the count of meal components using this unit.
     */
    public function getUsageCount(): int
    {
        if (! Schema::hasTable('meal_components')) {
            return 0;
        }

        return MealComponent::where('selling_unit', (string) $this->id)->count();
    }
}
