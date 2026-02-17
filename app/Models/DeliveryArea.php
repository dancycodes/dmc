<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryArea extends Model
{
    /** @use HasFactory<\Database\Factories\DeliveryAreaFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'delivery_areas';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'town_id',
    ];

    /**
     * Get the tenant that owns this delivery area.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the town referenced by this delivery area.
     */
    public function town(): BelongsTo
    {
        return $this->belongsTo(Town::class);
    }

    /**
     * Get the quarters with delivery fees for this delivery area.
     */
    public function deliveryAreaQuarters(): HasMany
    {
        return $this->hasMany(DeliveryAreaQuarter::class);
    }
}
