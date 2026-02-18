<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\ApplyScheduleTemplateRequest;
use App\Http\Requests\Cook\StoreScheduleTemplateRequest;
use App\Http\Requests\Cook\UpdateScheduleTemplateRequest;
use App\Models\CookSchedule;
use App\Models\ScheduleTemplate;
use App\Services\ScheduleTemplateService;
use Illuminate\Http\Request;

/**
 * F-101: Create Schedule Template
 * F-102: Schedule Template List View
 * F-103: Edit Schedule Template
 * F-104: Delete Schedule Template
 * F-105: Schedule Template Application to Days
 *
 * Manages schedule templates for cooks. Templates are reusable
 * configurations that bundle order, delivery, and pickup intervals
 * for quick application to schedule days (via F-105).
 */
class ScheduleTemplateController extends Controller
{
    /**
     * F-102: Display the list of schedule templates.
     *
     * BR-136: Tenant-scoped — only shows templates belonging to the current tenant
     * BR-137: Shows "applied to" count via withCount('cookSchedules')
     * BR-138: Only users with can-manage-schedules permission
     * BR-139: Alphabetical order by name
     */
    public function index(Request $request, ScheduleTemplateService $templateService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-138: Permission check
        if (! $user->can('can-manage-schedules')) {
            abort(403);
        }

        $templates = $templateService->getTemplatesWithAppliedCount($tenant);

        return gale()->view('cook.schedule.templates.index', [
            'templates' => $templates,
        ], web: true);
    }

    /**
     * Show the template creation form.
     *
     * BR-133: Only users with can-manage-schedules permission
     * BR-132: Tenant-scoped
     */
    public function create(Request $request, ScheduleTemplateService $templateService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-133: Permission check
        if (! $user->can('can-manage-schedules')) {
            abort(403);
        }

        $templates = $templateService->getTemplatesForTenant($tenant);
        $templateCount = $templates->count();

        return gale()->view('cook.schedule.templates.create', [
            'templates' => $templates,
            'templateCount' => $templateCount,
            'startDayOffsetOptions' => CookSchedule::getStartDayOffsetOptions(),
            'endDayOffsetOptions' => CookSchedule::getEndDayOffsetOptions(),
        ], web: true);
    }

    /**
     * Store a new schedule template.
     *
     * BR-127: Unique name within tenant
     * BR-128: Name required, max 100 chars
     * BR-129: Order interval required
     * BR-130: At least one of delivery/pickup
     * BR-131: Time interval validations from F-099/F-100
     * BR-132: Tenant-scoped
     * BR-133: Permission check
     * BR-134: Logged via Spatie Activitylog
     */
    public function store(Request $request, ScheduleTemplateService $templateService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-133: Permission check
        if (! $user->can('can-manage-schedules')) {
            abort(403);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'name' => ['required', 'string', 'max:100'],
                'order_start_time' => ['required', 'date_format:H:i'],
                'order_start_day_offset' => ['required', 'integer', 'min:0', 'max:'.CookSchedule::MAX_START_DAY_OFFSET],
                'order_end_time' => ['required', 'date_format:H:i'],
                'order_end_day_offset' => ['required', 'integer', 'min:0', 'max:'.CookSchedule::MAX_END_DAY_OFFSET],
                'delivery_enabled' => ['required'],
                'delivery_start_time' => ['nullable', 'date_format:H:i'],
                'delivery_end_time' => ['nullable', 'date_format:H:i'],
                'pickup_enabled' => ['required'],
                'pickup_start_time' => ['nullable', 'date_format:H:i'],
                'pickup_end_time' => ['nullable', 'date_format:H:i'],
            ], [
                'name.required' => __('Template name is required.'),
                'name.max' => __('Template name must not exceed 100 characters.'),
                'order_start_time.required' => __('Order start time is required.'),
                'order_start_time.date_format' => __('Order start time must be in HH:MM format (24-hour).'),
                'order_start_day_offset.required' => __('Start day offset is required.'),
                'order_start_day_offset.max' => __('Start day offset cannot exceed :max days before.', ['max' => CookSchedule::MAX_START_DAY_OFFSET]),
                'order_end_time.required' => __('Order end time is required.'),
                'order_end_time.date_format' => __('Order end time must be in HH:MM format (24-hour).'),
                'order_end_day_offset.required' => __('End day offset is required.'),
                'order_end_day_offset.max' => __('End day offset cannot exceed :max day before.', ['max' => CookSchedule::MAX_END_DAY_OFFSET]),
                'delivery_enabled.required' => __('Delivery status is required.'),
                'delivery_start_time.date_format' => __('Delivery start time must be in HH:MM format (24-hour).'),
                'delivery_end_time.date_format' => __('Delivery end time must be in HH:MM format (24-hour).'),
                'pickup_enabled.required' => __('Pickup status is required.'),
                'pickup_start_time.date_format' => __('Pickup start time must be in HH:MM format (24-hour).'),
                'pickup_end_time.date_format' => __('Pickup end time must be in HH:MM format (24-hour).'),
            ]);
        } else {
            $formRequest = app(StoreScheduleTemplateRequest::class);
            $validated = $formRequest->validated();
        }

        // Normalize booleans
        $deliveryEnabled = filter_var($validated['delivery_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $pickupEnabled = filter_var($validated['pickup_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $result = $templateService->createTemplate(
            $tenant,
            $validated['name'],
            $validated['order_start_time'],
            (int) $validated['order_start_day_offset'],
            $validated['order_end_time'],
            (int) $validated['order_end_day_offset'],
            $deliveryEnabled,
            $deliveryEnabled ? ($validated['delivery_start_time'] ?? null) : null,
            $deliveryEnabled ? ($validated['delivery_end_time'] ?? null) : null,
            $pickupEnabled,
            $pickupEnabled ? ($validated['pickup_start_time'] ?? null) : null,
            $pickupEnabled ? ($validated['pickup_end_time'] ?? null) : null,
        );

        if (! $result['success']) {
            $field = $result['field'] ?? 'name';

            if ($request->isGale()) {
                return gale()->messages([
                    $field => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors([$field => $result['error']])->withInput();
        }

        // BR-134: Activity logging
        activity('schedule_templates')
            ->performedOn($result['template'])
            ->causedBy($user)
            ->withProperties([
                'action' => 'template_created',
                'name' => $result['template']->name,
                'delivery_enabled' => $deliveryEnabled,
                'pickup_enabled' => $pickupEnabled,
                'tenant_id' => $tenant->id,
            ])
            ->log('Schedule template created');

        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/schedule/templates/create'))
                ->with('success', __('Schedule template created successfully.'));
        }

        return redirect()->route('cook.schedule-templates.create')
            ->with('success', __('Schedule template created successfully.'));
    }

    /**
     * F-103: Show the template edit form.
     *
     * BR-145: Only users with can-manage-schedules permission
     * BR-142: Loads template for pre-population
     */
    public function edit(Request $request, ScheduleTemplate $scheduleTemplate, ScheduleTemplateService $templateService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-145: Permission check
        if (! $user->can('can-manage-schedules')) {
            abort(403);
        }

        // Ensure template belongs to current tenant
        if ($scheduleTemplate->tenant_id !== $tenant->id) {
            abort(404);
        }

        return gale()->view('cook.schedule.templates.edit', [
            'template' => $scheduleTemplate,
            'startDayOffsetOptions' => CookSchedule::getStartDayOffsetOptions(),
            'endDayOffsetOptions' => CookSchedule::getEndDayOffsetOptions(),
        ], web: true);
    }

    /**
     * F-103: Update an existing schedule template.
     *
     * BR-140: All validation rules from F-099 and F-100 apply
     * BR-141: Template name must remain unique within the tenant
     * BR-142: Editing does NOT propagate changes to day schedules
     * BR-144: Template edits are logged via Spatie Activitylog
     * BR-145: Only users with can-manage-schedules permission
     */
    public function update(Request $request, ScheduleTemplate $scheduleTemplate, ScheduleTemplateService $templateService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-145: Permission check
        if (! $user->can('can-manage-schedules')) {
            abort(403);
        }

        // Ensure template belongs to current tenant
        if ($scheduleTemplate->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'name' => ['required', 'string', 'max:100'],
                'order_start_time' => ['required', 'date_format:H:i'],
                'order_start_day_offset' => ['required', 'integer', 'min:0', 'max:'.CookSchedule::MAX_START_DAY_OFFSET],
                'order_end_time' => ['required', 'date_format:H:i'],
                'order_end_day_offset' => ['required', 'integer', 'min:0', 'max:'.CookSchedule::MAX_END_DAY_OFFSET],
                'delivery_enabled' => ['required'],
                'delivery_start_time' => ['nullable', 'date_format:H:i'],
                'delivery_end_time' => ['nullable', 'date_format:H:i'],
                'pickup_enabled' => ['required'],
                'pickup_start_time' => ['nullable', 'date_format:H:i'],
                'pickup_end_time' => ['nullable', 'date_format:H:i'],
            ], [
                'name.required' => __('Template name is required.'),
                'name.max' => __('Template name must not exceed 100 characters.'),
                'order_start_time.required' => __('Order start time is required.'),
                'order_start_time.date_format' => __('Order start time must be in HH:MM format (24-hour).'),
                'order_start_day_offset.required' => __('Start day offset is required.'),
                'order_start_day_offset.max' => __('Start day offset cannot exceed :max days before.', ['max' => CookSchedule::MAX_START_DAY_OFFSET]),
                'order_end_time.required' => __('Order end time is required.'),
                'order_end_time.date_format' => __('Order end time must be in HH:MM format (24-hour).'),
                'order_end_day_offset.required' => __('End day offset is required.'),
                'order_end_day_offset.max' => __('End day offset cannot exceed :max day before.', ['max' => CookSchedule::MAX_END_DAY_OFFSET]),
                'delivery_enabled.required' => __('Delivery status is required.'),
                'delivery_start_time.date_format' => __('Delivery start time must be in HH:MM format (24-hour).'),
                'delivery_end_time.date_format' => __('Delivery end time must be in HH:MM format (24-hour).'),
                'pickup_enabled.required' => __('Pickup status is required.'),
                'pickup_start_time.date_format' => __('Pickup start time must be in HH:MM format (24-hour).'),
                'pickup_end_time.date_format' => __('Pickup end time must be in HH:MM format (24-hour).'),
            ]);
        } else {
            $formRequest = app(UpdateScheduleTemplateRequest::class);
            $validated = $formRequest->validated();
        }

        // Normalize booleans
        $deliveryEnabled = filter_var($validated['delivery_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $pickupEnabled = filter_var($validated['pickup_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Capture old values for activity log diff
        $oldValues = [
            'name' => $scheduleTemplate->name,
            'order_start_time' => $scheduleTemplate->order_start_time,
            'order_start_day_offset' => $scheduleTemplate->order_start_day_offset,
            'order_end_time' => $scheduleTemplate->order_end_time,
            'order_end_day_offset' => $scheduleTemplate->order_end_day_offset,
            'delivery_enabled' => $scheduleTemplate->delivery_enabled,
            'delivery_start_time' => $scheduleTemplate->delivery_start_time,
            'delivery_end_time' => $scheduleTemplate->delivery_end_time,
            'pickup_enabled' => $scheduleTemplate->pickup_enabled,
            'pickup_start_time' => $scheduleTemplate->pickup_start_time,
            'pickup_end_time' => $scheduleTemplate->pickup_end_time,
        ];

        $result = $templateService->updateTemplate(
            $scheduleTemplate,
            $validated['name'],
            $validated['order_start_time'],
            (int) $validated['order_start_day_offset'],
            $validated['order_end_time'],
            (int) $validated['order_end_day_offset'],
            $deliveryEnabled,
            $deliveryEnabled ? ($validated['delivery_start_time'] ?? null) : null,
            $deliveryEnabled ? ($validated['delivery_end_time'] ?? null) : null,
            $pickupEnabled,
            $pickupEnabled ? ($validated['pickup_start_time'] ?? null) : null,
            $pickupEnabled ? ($validated['pickup_end_time'] ?? null) : null,
        );

        if (! $result['success']) {
            $field = $result['field'] ?? 'name';

            if ($request->isGale()) {
                return gale()->messages([
                    $field => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors([$field => $result['error']])->withInput();
        }

        // BR-144: Activity logging with diff tracking
        $newValues = [
            'name' => $result['template']->name,
            'order_start_time' => $result['template']->order_start_time,
            'order_start_day_offset' => $result['template']->order_start_day_offset,
            'order_end_time' => $result['template']->order_end_time,
            'order_end_day_offset' => $result['template']->order_end_day_offset,
            'delivery_enabled' => $result['template']->delivery_enabled,
            'delivery_start_time' => $result['template']->delivery_start_time,
            'delivery_end_time' => $result['template']->delivery_end_time,
            'pickup_enabled' => $result['template']->pickup_enabled,
            'pickup_start_time' => $result['template']->pickup_start_time,
            'pickup_end_time' => $result['template']->pickup_end_time,
        ];

        $changes = array_diff_assoc(
            array_map('strval', $newValues),
            array_map('strval', $oldValues)
        );

        if (! empty($changes)) {
            activity('schedule_templates')
                ->performedOn($result['template'])
                ->causedBy($user)
                ->withProperties([
                    'action' => 'template_updated',
                    'old' => array_intersect_key($oldValues, $changes),
                    'new' => array_intersect_key($newValues, $changes),
                    'tenant_id' => $tenant->id,
                ])
                ->log('Schedule template updated');
        }

        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/schedule/templates'))
                ->with('success', __('Schedule template updated successfully.'));
        }

        return redirect()->route('cook.schedule-templates.index')
            ->with('success', __('Schedule template updated successfully.'));
    }

    /**
     * F-104: Delete a schedule template.
     *
     * BR-146: Deleting does NOT affect day schedules (values were copied, not linked)
     * BR-147: Confirmation handled client-side via Alpine.js modal
     * BR-149: Hard delete
     * BR-150: Logged via Spatie Activitylog
     * BR-151: Only users with can-manage-schedules permission
     * BR-152: template_id on affected CookSchedule entries set to null (via DB FK constraint)
     */
    public function destroy(Request $request, ScheduleTemplate $scheduleTemplate, ScheduleTemplateService $templateService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-151: Permission check
        if (! $user->can('can-manage-schedules')) {
            abort(403);
        }

        // Ensure template belongs to current tenant
        if ($scheduleTemplate->tenant_id !== $tenant->id) {
            abort(404);
        }

        $result = $templateService->deleteTemplate($scheduleTemplate);

        // BR-150: Activity logging
        activity('schedule_templates')
            ->causedBy($user)
            ->withProperties([
                'action' => 'template_deleted',
                'name' => $result['template_name'],
                'applied_count' => $result['applied_count'],
                'tenant_id' => $tenant->id,
            ])
            ->log('Schedule template deleted');

        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/schedule/templates'))
                ->with('success', __('Schedule template ":name" deleted successfully.', ['name' => $result['template_name']]));
        }

        return redirect()->route('cook.schedule-templates.index')
            ->with('success', __('Schedule template ":name" deleted successfully.', ['name' => $result['template_name']]));
    }

    /**
     * F-105: Show the apply template to days form.
     *
     * BR-161: Only users with can-manage-schedules permission
     * Displays day checkboxes with warning icons for days that already have schedules.
     */
    public function showApply(Request $request, ScheduleTemplate $scheduleTemplate, ScheduleTemplateService $templateService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-161: Permission check
        if (! $user->can('can-manage-schedules')) {
            abort(403);
        }

        // Ensure template belongs to current tenant
        if ($scheduleTemplate->tenant_id !== $tenant->id) {
            abort(404);
        }

        $daysWithSchedules = $templateService->getDaysWithExistingSchedules($tenant);

        return gale()->view('cook.schedule.templates.apply', [
            'template' => $scheduleTemplate,
            'daysOfWeek' => CookSchedule::DAYS_OF_WEEK,
            'dayLabels' => CookSchedule::DAY_LABELS,
            'daysWithSchedules' => $daysWithSchedules,
        ], web: true);
    }

    /**
     * F-105: Apply a template to selected days.
     *
     * BR-153: Copies template values (not a live link)
     * BR-154: At least one day must be selected
     * BR-156: Overwrites existing schedule entries
     * BR-157: Sets template_id for tracking
     * BR-158: Sets availability to true
     * BR-159: Replaces first entry, removes extras
     * BR-160: Logged via Spatie Activitylog
     * BR-161: Permission check
     */
    public function apply(Request $request, ScheduleTemplate $scheduleTemplate, ScheduleTemplateService $templateService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-161: Permission check
        if (! $user->can('can-manage-schedules')) {
            abort(403);
        }

        // Ensure template belongs to current tenant
        if ($scheduleTemplate->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'days' => ['required', 'array', 'min:1'],
                'days.*' => ['required', 'string', \Illuminate\Validation\Rule::in(CookSchedule::DAYS_OF_WEEK)],
            ], [
                'days.required' => __('Select at least one day.'),
                'days.min' => __('Select at least one day.'),
                'days.*.in' => __('Invalid day selected.'),
            ]);
        } else {
            $formRequest = app(ApplyScheduleTemplateRequest::class);
            $validated = $formRequest->validated();
        }

        $days = $validated['days'] ?? [];

        $result = $templateService->applyTemplateToDays(
            $scheduleTemplate,
            $tenant,
            $days,
        );

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'days' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['days' => $result['error']])->withInput();
        }

        // BR-160: Activity logging
        $dayLabels = array_map(fn ($day) => __(CookSchedule::DAY_LABELS[$day] ?? $day), $days);

        activity('schedule_templates')
            ->performedOn($scheduleTemplate)
            ->causedBy($user)
            ->withProperties([
                'action' => 'template_applied',
                'template_name' => $scheduleTemplate->name,
                'days_applied' => $days,
                'days_applied_labels' => $dayLabels,
                'days_created' => $result['days_created'],
                'days_overwritten' => $result['days_overwritten'],
                'tenant_id' => $tenant->id,
            ])
            ->log('Schedule template applied to days');

        $message = trans_choice(
            'Template applied to :count day.|Template applied to :count days.',
            $result['days_applied'],
            ['count' => $result['days_applied']],
        );

        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/schedule/templates'))
                ->with('success', $message);
        }

        return redirect()->route('cook.schedule-templates.index')
            ->with('success', $message);
    }
}
