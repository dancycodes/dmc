<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F-098: Cook Day Schedule Creation
 *
 * Represents a schedule entry for a specific day of the week within a tenant.
 * Multiple entries can exist per day (e.g., Lunch slot, Dinner slot) up to
 * a configurable maximum (default 3). Each entry can be marked as available
 * or unavailable and serves as the foundation for time interval configuration.
 */
class CookSchedule extends Model
{
    /** @use HasFactory<\Database\Factories\CookScheduleFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'cook_schedules';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'day_of_week',
        'is_available',
        'label',
        'position',
    ];

    /**
     * BR-100: Maximum schedule entries per day (configurable, default 3).
     */
    public const MAX_ENTRIES_PER_DAY = 3;

    /**
     * Valid days of the week.
     *
     * @var list<string>
     */
    public const DAYS_OF_WEEK = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    /**
     * Day labels for display (translatable).
     *
     * @var array<string, string>
     */
    public const DAY_LABELS = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Get the tenant that owns this schedule entry.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the human-readable day label (translatable).
     */
    public function getDayLabelAttribute(): string
    {
        return __(self::DAY_LABELS[$this->day_of_week] ?? 'Unknown');
    }

    /**
     * Get the display label for this schedule entry.
     * BR-105: Defaults to "Slot N" based on position when label is empty.
     */
    public function getDisplayLabelAttribute(): string
    {
        if (! empty($this->label)) {
            return $this->label;
        }

        return __('Slot').' '.$this->position;
    }

    /**
     * Scope to filter by tenant.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to filter by day of week.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForDay($query, string $day)
    {
        return $query->where('day_of_week', $day);
    }

    /**
     * Scope to filter available entries only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }
}
