<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealLocationOverride extends Model
{
    /** @use HasFactory<\Database\Factories\MealLocationOverrideFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'meal_location_overrides';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'meal_id',
        'quarter_id',
        'pickup_location_id',
        'custom_delivery_fee',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_delivery_fee' => 'integer',
        ];
    }

    /**
     * Get the meal this override belongs to.
     */
    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    /**
     * Get the quarter for this override (delivery location).
     */
    public function quarter(): BelongsTo
    {
        return $this->belongsTo(Quarter::class);
    }

    /**
     * Get the pickup location for this override.
     */
    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(PickupLocation::class);
    }

    /**
     * Check if this override is for a delivery quarter.
     */
    public function isDeliveryOverride(): bool
    {
        return $this->quarter_id !== null;
    }

    /**
     * Check if this override is for a pickup location.
     */
    public function isPickupOverride(): bool
    {
        return $this->pickup_location_id !== null;
    }
}
