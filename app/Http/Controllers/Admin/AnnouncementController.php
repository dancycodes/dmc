<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAnnouncementRequest;
use App\Http\Requests\Admin\UpdateAnnouncementRequest;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * F-195: System Announcement Notifications
 *
 * Admin-only controller for managing platform announcements.
 * Handles: list, create, store, edit, update, and cancel actions.
 *
 * BR-311: Only admins can access this.
 * BR-316: Immediate send dispatches via queue.
 * BR-317: Scheduled stored for dispatch by command.
 * BR-322: Cancel action stops scheduled announcements from sending.
 */
class AnnouncementController extends Controller
{
    public function __construct(
        private AnnouncementService $announcementService,
    ) {}

    /**
     * Display a paginated list of announcements.
     */
    public function index(Request $request): mixed
    {
        $announcements = $this->announcementService->getAnnouncementsList();

        return gale()->view('admin.announcements.index', compact('announcements'), web: true);
    }

    /**
     * Show the form to create a new announcement.
     */
    public function create(Request $request): mixed
    {
        $tenants = $this->announcementService->getActiveTenantsForDropdown();
        $targetOptions = Announcement::targetTypeOptions();

        return gale()->view('admin.announcements.create', compact('tenants', 'targetOptions'), web: true);
    }

    /**
     * Store a new announcement and dispatch or schedule it.
     *
     * BR-316: Immediate send dispatches via queue.
     * BR-317: Scheduled stored for dispatch at specified time.
     */
    public function store(Request $request): mixed
    {
        if ($request->isGale()) {
            $validated = $request->validateState([
                'content' => ['required', 'string', 'max:2000'],
                'target_type' => ['required', 'in:all_users,all_cooks,all_clients,specific_tenant'],
                'target_tenant_id' => [
                    'nullable',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($request->state('target_type') === Announcement::TARGET_SPECIFIC_TENANT && empty($value)) {
                            $fail(__('Please select a tenant when targeting a specific tenant.'));
                        }
                    },
                    'exists:tenants,id',
                ],
                'scheduled_at' => [
                    'nullable',
                    'date',
                    function ($attribute, $value, $fail) {
                        if (! empty($value) && Carbon::parse($value)->lessThanOrEqualTo(now()->addMinutes(StoreAnnouncementRequest::MIN_SCHEDULE_MINUTES))) {
                            $fail(__('Scheduled time must be at least :minutes minutes in the future.', [
                                'minutes' => StoreAnnouncementRequest::MIN_SCHEDULE_MINUTES,
                            ]));
                        }
                    },
                ],
            ]);
        } else {
            $validated = app(StoreAnnouncementRequest::class)->validated();
        }

        $announcement = $this->announcementService->createAnnouncement(
            admin: $request->user(),
            data: $validated,
        );

        $message = $announcement->status === Announcement::STATUS_SCHEDULED
            ? __('Announcement scheduled successfully.')
            : __('Announcement sent successfully.');

        return gale()->redirect('/vault-entry/announcements')
            ->with('toast', ['type' => 'success', 'message' => $message]);
    }

    /**
     * Show the form to edit a draft or scheduled announcement.
     */
    public function edit(Request $request, Announcement $announcement): mixed
    {
        if (! $announcement->canBeEdited()) {
            return gale()->redirect('/vault-entry/announcements')
                ->with('toast', ['type' => 'error', 'message' => __('This announcement cannot be edited.')]);
        }

        $tenants = $this->announcementService->getActiveTenantsForDropdown();
        $targetOptions = Announcement::targetTypeOptions();

        return gale()->view('admin.announcements.edit', compact('announcement', 'tenants', 'targetOptions'), web: true);
    }

    /**
     * Update a draft or scheduled announcement.
     */
    public function update(Request $request, Announcement $announcement): mixed
    {
        if (! $announcement->canBeEdited()) {
            if ($request->isGale()) {
                return gale()->state('error', __('This announcement cannot be edited.'));
            }

            return gale()->redirect('/vault-entry/announcements')
                ->with('toast', ['type' => 'error', 'message' => __('This announcement cannot be edited.')]);
        }

        if ($request->isGale()) {
            $validated = $request->validateState([
                'content' => ['required', 'string', 'max:2000'],
                'target_type' => ['required', 'in:all_users,all_cooks,all_clients,specific_tenant'],
                'target_tenant_id' => [
                    'nullable',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($request->state('target_type') === Announcement::TARGET_SPECIFIC_TENANT && empty($value)) {
                            $fail(__('Please select a tenant when targeting a specific tenant.'));
                        }
                    },
                    'exists:tenants,id',
                ],
                'scheduled_at' => [
                    'nullable',
                    'date',
                    function ($attribute, $value, $fail) {
                        if (! empty($value) && Carbon::parse($value)->lessThanOrEqualTo(now()->addMinutes(UpdateAnnouncementRequest::MIN_SCHEDULE_MINUTES))) {
                            $fail(__('Scheduled time must be at least :minutes minutes in the future.', [
                                'minutes' => UpdateAnnouncementRequest::MIN_SCHEDULE_MINUTES,
                            ]));
                        }
                    },
                ],
            ]);
        } else {
            $validated = app(UpdateAnnouncementRequest::class)->validated();
        }

        $this->announcementService->updateAnnouncement(
            admin: $request->user(),
            announcement: $announcement,
            data: $validated,
        );

        return gale()->redirect('/vault-entry/announcements')
            ->with('toast', ['type' => 'success', 'message' => __('Announcement updated successfully.')]);
    }

    /**
     * Cancel a scheduled announcement.
     *
     * BR-322: Admin can cancel a scheduled announcement before it is sent.
     */
    public function cancel(Request $request, Announcement $announcement): mixed
    {
        if (! $announcement->canBeCancelled()) {
            if ($request->isGale()) {
                return gale()->state('cancelError', __('Only scheduled announcements can be cancelled.'));
            }

            return gale()->redirect('/vault-entry/announcements')
                ->with('toast', ['type' => 'error', 'message' => __('Only scheduled announcements can be cancelled.')]);
        }

        $this->announcementService->cancelAnnouncement(
            admin: $request->user(),
            announcement: $announcement,
        );

        return gale()->redirect('/vault-entry/announcements')
            ->with('toast', ['type' => 'success', 'message' => __('Announcement cancelled successfully.')]);
    }
}
