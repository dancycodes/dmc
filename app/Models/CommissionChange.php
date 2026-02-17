<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionChange extends Model
{
    /** @use HasFactory<\Database\Factories\CommissionChangeFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'commission_changes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'old_rate',
        'new_rate',
        'changed_by',
        'reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_rate' => 'decimal:2',
            'new_rate' => 'decimal:2',
        ];
    }

    /**
     * The default platform commission rate.
     */
    public const DEFAULT_RATE = 10.0;

    /**
     * Minimum allowed commission rate.
     */
    public const MIN_RATE = 0.0;

    /**
     * Maximum allowed commission rate.
     */
    public const MAX_RATE = 50.0;

    /**
     * Rate increment step.
     */
    public const RATE_STEP = 0.5;

    /**
     * Get the tenant this commission change belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the admin who made this change.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Check if this change represents a reset to the default rate.
     */
    public function isResetToDefault(): bool
    {
        return (float) $this->new_rate === self::DEFAULT_RATE;
    }
}
