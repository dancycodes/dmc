<?php

namespace App\Models;

use App\Traits\HasTranslatable;
use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealComponent extends Model
{
    /** @use HasFactory<\Database\Factories\MealComponentFactory> */
    use HasFactory, HasTranslatable, LogsActivityTrait;

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
    ];

    /**
     * Get the meal that owns this component.
     */
    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }
}
