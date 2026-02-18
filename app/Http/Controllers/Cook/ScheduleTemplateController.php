<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreScheduleTemplateRequest;
use App\Models\CookSchedule;
use App\Services\ScheduleTemplateService;
use Illuminate\Http\Request;

/**
 * F-101: Create Schedule Template
 *
 * Manages schedule templates for cooks. Templates are reusable
 * configurations that bundle order, delivery, and pickup intervals
 * for quick application to schedule days (via F-105).
 */
class ScheduleTemplateController extends Controller
{
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
}
