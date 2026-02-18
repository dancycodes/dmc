<?php

namespace App\Services;

use App\Models\ScheduleTemplate;
use App\Models\Tenant;

/**
 * F-101: Create Schedule Template
 *
 * Service layer handling all business logic for schedule template management.
 * Reuses interval validation logic from CookScheduleService for consistency.
 */
class ScheduleTemplateService
{
    public function __construct(
        private CookScheduleService $cookScheduleService,
    ) {}

    /**
     * Create a new schedule template.
     *
     * BR-127: Unique name within tenant
     * BR-128: Name required, max 100 chars
     * BR-129: Order interval required
     * BR-130: At least one of delivery/pickup must be enabled
     * BR-131: All time interval validations from F-099 and F-100 apply
     * BR-132: Tenant-scoped
     * BR-134: Template creation logged via Spatie Activitylog
     * BR-135: Templates are independent entities
     *
     * @return array{success: bool, template?: ScheduleTemplate, error?: string, field?: string}
     */
    public function createTemplate(
        Tenant $tenant,
        string $name,
        string $orderStartTime,
        int $orderStartDayOffset,
        string $orderEndTime,
        int $orderEndDayOffset,
        bool $deliveryEnabled,
        ?string $deliveryStartTime,
        ?string $deliveryEndTime,
        bool $pickupEnabled,
        ?string $pickupStartTime,
        ?string $pickupEndTime,
    ): array {
        // BR-128: Trim name
        $name = trim($name);

        // BR-127: Check name uniqueness within tenant (case-insensitive)
        $exists = ScheduleTemplate::query()
            ->forTenant($tenant->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            return [
                'success' => false,
                'error' => __('A template with this name already exists.'),
                'field' => 'name',
            ];
        }

        // BR-131/BR-108: Validate order interval chronological order
        if (! $this->cookScheduleService->isIntervalChronologicallyValid(
            $orderStartTime,
            $orderStartDayOffset,
            $orderEndTime,
            $orderEndDayOffset,
        )) {
            return [
                'success' => false,
                'error' => __('The order interval end must be after the start. Please adjust the times or day offsets.'),
                'field' => 'order_start_time',
            ];
        }

        // BR-130: At least one of delivery or pickup must be enabled
        if (! $deliveryEnabled && ! $pickupEnabled) {
            return [
                'success' => false,
                'error' => __('At least one of delivery or pickup must be enabled.'),
                'field' => 'delivery_enabled',
            ];
        }

        // Calculate order end time in minutes for delivery/pickup validation
        $orderEndMinutes = $this->getOrderEndTimeInMinutes($orderEndTime, $orderEndDayOffset);

        // Validate delivery interval if enabled
        if ($deliveryEnabled) {
            $deliveryValidation = $this->validateTimeWindow(
                'delivery',
                $deliveryStartTime,
                $deliveryEndTime,
                $orderEndMinutes,
            );

            if ($deliveryValidation !== null) {
                return $deliveryValidation;
            }
        }

        // Validate pickup interval if enabled
        if ($pickupEnabled) {
            $pickupValidation = $this->validateTimeWindow(
                'pickup',
                $pickupStartTime,
                $pickupEndTime,
                $orderEndMinutes,
            );

            if ($pickupValidation !== null) {
                return $pickupValidation;
            }
        }

        $template = ScheduleTemplate::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'order_start_time' => $orderStartTime,
            'order_start_day_offset' => $orderStartDayOffset,
            'order_end_time' => $orderEndTime,
            'order_end_day_offset' => $orderEndDayOffset,
            'delivery_enabled' => $deliveryEnabled,
            'delivery_start_time' => $deliveryEnabled ? $deliveryStartTime : null,
            'delivery_end_time' => $deliveryEnabled ? $deliveryEndTime : null,
            'pickup_enabled' => $pickupEnabled,
            'pickup_start_time' => $pickupEnabled ? $pickupStartTime : null,
            'pickup_end_time' => $pickupEnabled ? $pickupEndTime : null,
        ]);

        return [
            'success' => true,
            'template' => $template,
        ];
    }

    /**
     * Get all templates for a tenant, ordered by name.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ScheduleTemplate>
     */
    public function getTemplatesForTenant(Tenant $tenant): \Illuminate\Database\Eloquent\Collection
    {
        return ScheduleTemplate::query()
            ->forTenant($tenant->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get template count for a tenant.
     */
    public function getTemplateCount(Tenant $tenant): int
    {
        return ScheduleTemplate::query()
            ->forTenant($tenant->id)
            ->count();
    }

    /**
     * Calculate order end time in minutes from midnight.
     */
    private function getOrderEndTimeInMinutes(string $orderEndTime, int $orderEndDayOffset): int
    {
        if ($orderEndDayOffset > 0) {
            return 0;
        }

        $parts = explode(':', $orderEndTime);

        return ((int) $parts[0] * 60) + (int) ($parts[1] ?? 0);
    }

    /**
     * Validate a delivery or pickup time window.
     *
     * Reuses the same validation logic as CookScheduleService (F-100).
     *
     * @return array{success: bool, error: string, field: string}|null Null if valid
     */
    private function validateTimeWindow(
        string $type,
        ?string $startTime,
        ?string $endTime,
        int $orderEndMinutes,
    ): ?array {
        $typeLabel = $type === 'delivery' ? __('Delivery') : __('Pickup');

        // Times required when enabled
        if (empty($startTime) || empty($endTime)) {
            return [
                'success' => false,
                'error' => __(':type start and end times are required when enabled.', ['type' => $typeLabel]),
                'field' => $type.'_start_time',
            ];
        }

        $startMinutes = $this->timeToMinutes($startTime);
        $endMinutes = $this->timeToMinutes($endTime);

        // End must be after start
        if ($endMinutes <= $startMinutes) {
            return [
                'success' => false,
                'error' => __(':type end time must be after the start time.', ['type' => $typeLabel]),
                'field' => $type.'_end_time',
            ];
        }

        // Start must be at or after order interval end time
        if ($startMinutes < $orderEndMinutes) {
            $orderEndFormatted = date('g:i A', mktime((int) ($orderEndMinutes / 60), $orderEndMinutes % 60));

            return [
                'success' => false,
                'error' => __(':type start time must be at or after the order interval end time (:time).', [
                    'type' => $typeLabel,
                    'time' => $orderEndFormatted,
                ]),
                'field' => $type.'_start_time',
            ];
        }

        return null;
    }

    /**
     * Convert HH:MM time string to minutes from midnight.
     */
    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) $parts[0] * 60) + (int) ($parts[1] ?? 0);
    }
}
