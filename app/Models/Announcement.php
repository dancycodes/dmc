<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F-195: System Announcement
 *
 * Represents a platform-wide announcement created by an admin.
 * Can target all users, all cooks, all clients, or a specific tenant's users.
 * Delivered via push, database, and email notifications.
 */
class Announcement extends Model
{
    use HasFactory, LogsActivityTrait;

    // Target types (BR-312)
    public const TARGET_ALL_USERS = 'all_users';

    public const TARGET_ALL_COOKS = 'all_cooks';

    public const TARGET_ALL_CLIENTS = 'all_clients';

    public const TARGET_SPECIFIC_TENANT = 'specific_tenant';

    // Status constants
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'content',
        'target_type',
        'target_tenant_id',
        'status',
        'scheduled_at',
        'sent_at',
    ];

    public function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * The admin who created this announcement.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The specific tenant targeted (when target_type = specific_tenant).
     */
    public function targetTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'target_tenant_id');
    }

    /**
     * Get a human-readable label for the target type.
     */
    public function getTargetLabel(): string
    {
        return match ($this->target_type) {
            self::TARGET_ALL_USERS => __('All Users'),
            self::TARGET_ALL_COOKS => __('All Cooks'),
            self::TARGET_ALL_CLIENTS => __('All Clients'),
            self::TARGET_SPECIFIC_TENANT => $this->targetTenant?->name ?? __('Specific Tenant'),
            default => __('Unknown'),
        };
    }

    /**
     * Get the content preview (first 100 characters).
     */
    public function getContentPreview(int $length = 100): string
    {
        $content = strip_tags($this->content);

        if (mb_strlen($content) <= $length) {
            return $content;
        }

        return mb_substr($content, 0, $length).'...';
    }

    /**
     * Check if the announcement can be edited (not yet sent or cancelled).
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true);
    }

    /**
     * Check if the announcement can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    /**
     * Scope to announcements ready to be dispatched.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeReadyToDispatch($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now());
    }

    /**
     * Get all available target type options.
     *
     * @return array<string, string>
     */
    public static function targetTypeOptions(): array
    {
        return [
            self::TARGET_ALL_USERS => __('All Users'),
            self::TARGET_ALL_COOKS => __('All Cooks'),
            self::TARGET_ALL_CLIENTS => __('All Clients'),
            self::TARGET_SPECIFIC_TENANT => __('Specific Tenant'),
        ];
    }
}
