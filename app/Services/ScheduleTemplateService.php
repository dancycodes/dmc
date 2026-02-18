<?php

namespace App\Services;

use App\Models\CookSchedule;
use App\Models\ScheduleTemplate;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * F-101: Create Schedule Template
 * F-102: Schedule Template List View
 * F-105: Schedule Template Application to Days
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
     * F-103: Update an existing schedule template.
     *
     * BR-140: All validation rules from F-099 and F-100 apply to template edits
     * BR-141: Template name must remain unique within the tenant
     * BR-142: Editing a template does NOT propagate changes to day schedules
     *
     * @return array{success: bool, template?: ScheduleTemplate, error?: string, field?: string}
     */
    public function updateTemplate(
        ScheduleTemplate $template,
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
        $name = trim($name);

        // BR-141: Check name uniqueness within tenant (case-insensitive), excluding current template
        $exists = ScheduleTemplate::query()
            ->forTenant($template->tenant_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->where('id', '!=', $template->id)
            ->exists();

        if ($exists) {
            return [
                'success' => false,
                'error' => __('A template with this name already exists.'),
                'field' => 'name',
            ];
        }

        // BR-140/BR-108: Validate order interval chronological order
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

        // BR-142: Update the template only — day schedules remain untouched
        $template->update([
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
            'template' => $template->fresh(),
        ];
    }

    /**
     * Find a template by ID for a specific tenant.
     */
    public function findTemplateForTenant(Tenant $tenant, int $templateId): ?ScheduleTemplate
    {
        return ScheduleTemplate::query()
            ->forTenant($tenant->id)
            ->find($templateId);
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
     * F-102: Get all templates for a tenant with applied-to count.
     *
     * BR-137: The "applied to" count reflects how many schedule entries
     * were created from this template (tracked via template_id reference).
     * BR-139: Templates listed in alphabetical order by name.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ScheduleTemplate>
     */
    public function getTemplatesWithAppliedCount(Tenant $tenant): \Illuminate\Database\Eloquent\Collection
    {
        return ScheduleTemplate::query()
            ->forTenant($tenant->id)
            ->withCount('cookSchedules')
            ->orderBy('name')
            ->get();
    }

    /**
     * F-104: Delete a schedule template.
     *
     * BR-146: Deleting a template does NOT affect day schedules (values were copied)
     * BR-149: Hard delete (not soft delete)
     * BR-152: template_id on CookSchedule is nullified via DB constraint (nullOnDelete)
     *
     * @return array{success: bool, applied_count: int, template_name: string}
     */
    public function deleteTemplate(ScheduleTemplate $template): array
    {
        $templateName = $template->name;
        $appliedCount = $template->cookSchedules()->count();

        // BR-149: Hard delete — DB FK constraint handles BR-152 (nullOnDelete)
        $template->delete();

        return [
            'success' => true,
            'applied_count' => $appliedCount,
            'template_name' => $templateName,
        ];
    }

    /**
     * F-104: Get the applied-to count for a template (for confirmation dialog).
     */
    public function getAppliedCount(ScheduleTemplate $template): int
    {
        return $template->cookSchedules()->count();
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
     * F-105: Apply a template to one or more days of the week.
     *
     * BR-153: Copies template values (not a live link)
     * BR-154: At least one day must be selected
     * BR-155: Warns about overwriting (handled client-side)
     * BR-156: Replaces all interval values on existing entries
     * BR-157: Sets template_id reference for tracking
     * BR-158: Availability set to true for all applied entries
     * BR-159: Replaces first entry and removes extras if day has multiple
     * BR-160: Logged via Spatie Activitylog
     * BR-161: Permission check (handled in controller)
     *
     * @param  list<string>  $days
     * @return array{success: bool, days_applied: int, days_created: int, days_overwritten: int, error?: string}
     */
    public function applyTemplateToDays(
        ScheduleTemplate $template,
        Tenant $tenant,
        array $days,
    ): array {
        // BR-154: At least one day
        if (empty($days)) {
            return [
                'success' => false,
                'error' => __('Select at least one day.'),
                'days_applied' => 0,
                'days_created' => 0,
                'days_overwritten' => 0,
            ];
        }

        // Validate all days are valid
        $validDays = array_intersect($days, CookSchedule::DAYS_OF_WEEK);
        if (count($validDays) !== count($days)) {
            return [
                'success' => false,
                'error' => __('Invalid day selected.'),
                'days_applied' => 0,
                'days_created' => 0,
                'days_overwritten' => 0,
            ];
        }

        $templateData = $this->getTemplateDataForApplication($template);

        $daysCreated = 0;
        $daysOverwritten = 0;

        DB::transaction(function () use ($tenant, $validDays, $templateData, &$daysCreated, &$daysOverwritten) {
            foreach ($validDays as $day) {
                $existingEntries = CookSchedule::query()
                    ->forTenant($tenant->id)
                    ->forDay($day)
                    ->orderBy('position')
                    ->get();

                if ($existingEntries->isEmpty()) {
                    // Create new entry
                    CookSchedule::create(array_merge($templateData, [
                        'tenant_id' => $tenant->id,
                        'day_of_week' => $day,
                        'position' => 1,
                    ]));
                    $daysCreated++;
                } else {
                    // BR-159: Update first entry, delete extras
                    $firstEntry = $existingEntries->first();
                    $firstEntry->update($templateData);

                    // Remove extra entries if day had multiple
                    if ($existingEntries->count() > 1) {
                        CookSchedule::query()
                            ->forTenant($tenant->id)
                            ->forDay($day)
                            ->where('id', '!=', $firstEntry->id)
                            ->delete();
                    }

                    $daysOverwritten++;
                }
            }
        });

        return [
            'success' => true,
            'days_applied' => count($validDays),
            'days_created' => $daysCreated,
            'days_overwritten' => $daysOverwritten,
        ];
    }

    /**
     * F-105: Get days that already have schedule entries for a tenant.
     *
     * Used by the apply form to show warning icons on days with existing schedules.
     *
     * @return list<string>
     */
    public function getDaysWithExistingSchedules(Tenant $tenant): array
    {
        return CookSchedule::query()
            ->forTenant($tenant->id)
            ->select('day_of_week')
            ->distinct()
            ->pluck('day_of_week')
            ->values()
            ->all();
    }

    /**
     * Extract template data for copying to a schedule entry.
     *
     * BR-153: Values are copied (not linked)
     * BR-157: template_id is set for tracking
     * BR-158: is_available set to true
     *
     * @return array<string, mixed>
     */
    private function getTemplateDataForApplication(ScheduleTemplate $template): array
    {
        return [
            'template_id' => $template->id,
            'is_available' => true,
            'label' => $template->name,
            'order_start_time' => $template->order_start_time,
            'order_start_day_offset' => $template->order_start_day_offset,
            'order_end_time' => $template->order_end_time,
            'order_end_day_offset' => $template->order_end_day_offset,
            'delivery_enabled' => $template->delivery_enabled,
            'delivery_start_time' => $template->delivery_start_time,
            'delivery_end_time' => $template->delivery_end_time,
            'pickup_enabled' => $template->pickup_enabled,
            'pickup_start_time' => $template->pickup_start_time,
            'pickup_end_time' => $template->pickup_end_time,
        ];
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
