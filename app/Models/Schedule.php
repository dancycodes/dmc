<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduleFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'schedules';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_available',
    ];

    /**
     * Day of week constants.
     */
    public const SUNDAY = 0;

    public const MONDAY = 1;

    public const TUESDAY = 2;

    public const WEDNESDAY = 3;

    public const THURSDAY = 4;

    public const FRIDAY = 5;

    public const SATURDAY = 6;

    /**
     * Day labels for display.
     *
     * @var array<int, string>
     */
    public const DAY_LABELS = [
        self::SUNDAY => 'Sun',
        self::MONDAY => 'Mon',
        self::TUESDAY => 'Tue',
        self::WEDNESDAY => 'Wed',
        self::THURSDAY => 'Thu',
        self::FRIDAY => 'Fri',
        self::SATURDAY => 'Sat',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_available' => 'boolean',
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
     * Get the human-readable day label.
     */
    public function getDayLabelAttribute(): string
    {
        return self::DAY_LABELS[$this->day_of_week] ?? 'Unknown';
    }
}
