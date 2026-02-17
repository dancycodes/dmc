<?php

namespace App\Models;

use App\Traits\HasTranslatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupLocation extends Model
{
    /** @use HasFactory<\Database\Factories\PickupLocationFactory> */
    use HasFactory, HasTranslatable;

    /**
     * The table associated with the model.
     */
    protected $table = 'pickup_locations';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'town_id',
        'quarter_id',
        'name_en',
        'name_fr',
        'address',
    ];

    /**
     * Translatable column base names.
     *
     * @var list<string>
     */
    protected array $translatable = ['name'];

    /**
     * Get the tenant that owns this pickup location.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the town this pickup location is in.
     */
    public function town(): BelongsTo
    {
        return $this->belongsTo(Town::class);
    }

    /**
     * Get the quarter this pickup location is in.
     */
    public function quarter(): BelongsTo
    {
        return $this->belongsTo(Quarter::class);
    }
}
