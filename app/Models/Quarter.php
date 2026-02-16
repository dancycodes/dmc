<?php

namespace App\Models;

use App\Traits\HasTranslatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quarter extends Model
{
    use HasFactory, HasTranslatable;

    /**
     * The table associated with the model.
     */
    protected $table = 'quarters';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'town_id',
        'name_en',
        'name_fr',
        'is_active',
    ];

    /**
     * Translatable column base names.
     *
     * @var list<string>
     */
    protected array $translatable = ['name'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the town this quarter belongs to.
     */
    public function town(): BelongsTo
    {
        return $this->belongsTo(Town::class);
    }

    /**
     * Get the addresses referencing this quarter.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Scope: only active quarters.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: quarters belonging to a specific town.
     */
    public function scopeForTown(Builder $query, int $townId): Builder
    {
        return $query->where('town_id', $townId);
    }
}
