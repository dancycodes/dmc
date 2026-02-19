<?php

namespace App\Models;

use App\Traits\HasTranslatable;
use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealComponent extends Model
{
    /** @use HasFactory<\Database\Factories\MealComponentFactory> */
    use HasFactory, HasTranslatable, LogsActivityTrait;

    /**
     * BR-282: Standard selling units available to all cooks.
     *
     * @var array<string>
     */
    public const STANDARD_UNITS = [
        'plate',
        'bowl',
        'pot',
        'cup',
        'piece',
        'portion',
        'serving',
        'pack',
    ];

    /**
     * Translatable unit labels for display.
     *
     * @var array<string, array{en: string, fr: string}>
     */
    public const UNIT_LABELS = [
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
     * The table associated with the model.
     */
    protected $table = 'meal_components';

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
        'meal_id',
        'name_en',
        'name_fr',
        'description_en',
        'description_fr',
        'price',
        'selling_unit',
        'min_quantity',
        'max_quantity',
        'available_quantity',
        'is_available',
        'position',
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
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'available_quantity' => 'integer',
            'is_available' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Get the meal that owns this component.
     */
    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    /**
     * Get the localized unit label for display.
     *
     * F-121: Selling unit is now stored as an ID referencing the selling_units table.
     * Falls back to UNIT_LABELS constants for backward compatibility with
     * string-based selling_unit values from before F-121.
     */
    public function getUnitLabelAttribute(): string
    {
        $locale = app()->getLocale();
        $unit = $this->selling_unit;

        // Check standard units by key first (backward compatibility)
        if (isset(self::UNIT_LABELS[$unit])) {
            return self::UNIT_LABELS[$unit][$locale] ?? self::UNIT_LABELS[$unit]['en'];
        }

        // Look up by ID in selling_units table (F-121)
        if (\Illuminate\Support\Facades\Schema::hasTable('selling_units') && is_numeric($unit)) {
            $sellingUnit = SellingUnit::find((int) $unit);
            if ($sellingUnit) {
                return $sellingUnit->{'name_'.$locale} ?? $sellingUnit->name_en;
            }
        }

        // Fallback: capitalize the unit string
        return ucfirst($unit);
    }

    /**
     * Check if this component has unlimited max quantity.
     */
    public function hasUnlimitedMaxQuantity(): bool
    {
        return $this->max_quantity === null;
    }

    /**
     * Check if this component has unlimited available quantity.
     */
    public function hasUnlimitedAvailableQuantity(): bool
    {
        return $this->available_quantity === null;
    }

    /**
     * Scope to available components.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope to components ordered by position.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position');
    }

    /**
     * Get the next position for a component in the given meal.
     */
    public static function nextPositionForMeal(int $mealId): int
    {
        return (int) static::where('meal_id', $mealId)->max('position') + 1;
    }

    /**
     * Format the price with XAF currency.
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 0, '.', ',').' XAF';
    }
}
