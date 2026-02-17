<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAreaQuarter extends Model
{
    /** @use HasFactory<\Database\Factories\DeliveryAreaQuarterFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'delivery_area_quarters';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'delivery_area_id',
        'quarter_id',
        'delivery_fee',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_fee' => 'integer',
        ];
    }

    /**
     * Get the delivery area that owns this quarter assignment.
     */
    public function deliveryArea(): BelongsTo
    {
        return $this->belongsTo(DeliveryArea::class);
    }

    /**
     * Get the quarter referenced by this assignment.
     */
    public function quarter(): BelongsTo
    {
        return $this->belongsTo(Quarter::class);
    }
}
