<?php

namespace App\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * DancyMeals activity logging trait.
 *
 * Wraps Spatie's LogsActivity with platform defaults:
 * - Logs all fillable attributes except globally excluded sensitive fields
 * - Only logs dirty (actually changed) attributes
 * - Skips empty logs (no real changes)
 * - Skips logging when only timestamps change
 * - Uses model-specific log names for filtering
 *
 * Models can override getActivitylogOptions() for custom behavior.
 */
trait LogsActivityTrait
{
    use LogsActivity;

    /**
     * Get the default activity log options for this model.
     *
     * Override in individual models for custom logging behavior.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $excludedAttributes = array_merge(
            config('activitylog.excluded_attributes', []),
            $this->getAdditionalExcludedAttributes(),
        );

        return LogOptions::defaults()
            ->logFillable()
            ->logExcept($excludedAttributes)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->dontLogIfAttributesChangedOnly(['updated_at', 'created_at'])
            ->useLogName($this->getActivityLogName())
            ->setDescriptionForEvent(fn (string $eventName) => $this->getActivityDescription($eventName));
    }

    /**
     * Get the log name for this model's activities.
     *
     * Derives from the model's table name by default (e.g., "users", "tenants").
     */
    protected function getActivityLogName(): string
    {
        return $this->getTable();
    }

    /**
     * Get the human-readable description for an activity event.
     */
    protected function getActivityDescription(string $eventName): string
    {
        $modelName = class_basename($this);

        return "{$modelName} was {$eventName}";
    }

    /**
     * Get additional attributes to exclude from logging for this specific model.
     *
     * Override in individual models to exclude model-specific sensitive fields.
     *
     * @return list<string>
     */
    protected function getAdditionalExcludedAttributes(): array
    {
        return [];
    }
}
