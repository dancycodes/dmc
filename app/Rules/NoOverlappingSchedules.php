<?php

namespace App\Rules;

use App\Services\ScheduleValidationService;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * F-107: Schedule Validation Rules
 *
 * BR-179: No overlapping schedule entries for the same day — delivery windows
 *         must not overlap, pickup windows must not overlap, and order windows
 *         must not overlap across entries for the same day.
 *
 * This rule is used in Form Requests to validate that new or edited schedule
 * entries do not create time window overlaps with existing entries. It checks
 * all three window types (order, delivery, pickup).
 *
 * BR-184: Same rules apply to cook schedules, meal schedules, and templates.
 */
class NoOverlappingSchedules implements DataAwareRule, ValidationRule
{
    /**
     * All of the data under validation.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @param  string  $windowType  The type of window to check: 'order', 'delivery', 'pickup'
     * @param  int  $tenantId  The tenant to check overlaps within
     * @param  string  $dayOfWeek  The day to check overlaps for
     * @param  int|null  $excludeEntryId  Entry ID to exclude (for edits)
     */
    public function __construct(
        private string $windowType,
        private int $tenantId,
        private string $dayOfWeek,
        private ?int $excludeEntryId = null,
    ) {}

    /**
     * Set the data under validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $validationService = app(ScheduleValidationService::class);

        $orderStartTime = null;
        $orderStartDayOffset = null;
        $orderEndTime = null;
        $orderEndDayOffset = null;
        $deliveryStartTime = null;
        $deliveryEndTime = null;
        $pickupStartTime = null;
        $pickupEndTime = null;

        if ($this->windowType === 'order') {
            $orderStartTime = $this->data['order_start_time'] ?? null;
            $orderStartDayOffset = isset($this->data['order_start_day_offset']) ? (int) $this->data['order_start_day_offset'] : null;
            $orderEndTime = $this->data['order_end_time'] ?? null;
            $orderEndDayOffset = isset($this->data['order_end_day_offset']) ? (int) $this->data['order_end_day_offset'] : null;
        } elseif ($this->windowType === 'delivery') {
            $deliveryStartTime = $this->data['delivery_start_time'] ?? null;
            $deliveryEndTime = $this->data['delivery_end_time'] ?? null;
        } elseif ($this->windowType === 'pickup') {
            $pickupStartTime = $this->data['pickup_start_time'] ?? null;
            $pickupEndTime = $this->data['pickup_end_time'] ?? null;
        }

        $overlapCheck = $validationService->checkForOverlaps(
            $this->tenantId,
            $this->dayOfWeek,
            $orderStartTime,
            $orderStartDayOffset,
            $orderEndTime,
            $orderEndDayOffset,
            $deliveryStartTime,
            $deliveryEndTime,
            $pickupStartTime,
            $pickupEndTime,
            $this->excludeEntryId,
        );

        if ($overlapCheck['overlapping']) {
            $fail($overlapCheck['message']);
        }
    }
}
