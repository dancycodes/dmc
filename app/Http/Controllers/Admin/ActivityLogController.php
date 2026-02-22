<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivityLogListRequest;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    /**
     * Display the activity log viewer.
     *
     * F-064: Activity Log Viewer
     * BR-192: Read-only view — no edits or deletes
     * BR-193: All Spatie Activitylog entries displayed
     * BR-194: "System" shown for null causer (automated actions)
     * BR-196: 25 entries per page
     * BR-197: Default sort: newest first (created_at descending)
     * BR-198: Search covers description, causer name, subject type
     */
    public function index(ActivityLogListRequest $request): mixed
    {
        $search = $request->input('search', '');
        $causerUserId = $request->input('causer_user_id', '');
        $subjectType = $request->input('subject_type', '');
        $event = $request->input('event', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');

        // BR-196: 25 entries per page, BR-197: newest first
        $query = Activity::with(['causer'])
            ->latest();

        // BR-198: Search covers description, causer name, subject type
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ilike', "%{$search}%")
                    ->orWhere('subject_type', 'ilike', "%{$search}%")
                    ->orWhere('log_name', 'ilike', "%{$search}%")
                    ->orWhereHas('causer', function ($cq) use ($search) {
                        $cq->where('name', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%");
                    });
            });
        }

        // Filter by specific causer user
        if ($causerUserId !== '') {
            $query->where('causer_type', User::class)
                ->where('causer_id', $causerUserId);
        }

        // Filter by subject model type
        if ($subjectType !== '') {
            $query->where('subject_type', $subjectType);
        }

        // Filter by event type
        if ($event !== '') {
            $query->where('event', $event);
        }

        // Filter by date range
        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $activities = $query->paginate(25)->withQueryString();

        // Summary cards
        $totalCount = Activity::count();
        $todayCount = Activity::whereDate('created_at', today())->count();
        $uniqueCausers = Activity::whereNotNull('causer_id')->distinct('causer_id')->count('causer_id');

        // Filter dropdown options
        $adminUsers = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['super-admin', 'admin']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $subjectTypes = $this->getAvailableSubjectTypes();
        $eventTypes = $this->getAvailableEventTypes();

        $data = [
            'activities' => $activities,
            'search' => $search,
            'causerUserId' => $causerUserId,
            'subjectType' => $subjectType,
            'event' => $event,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'totalCount' => $totalCount,
            'todayCount' => $todayCount,
            'uniqueCausers' => $uniqueCausers,
            'adminUsers' => $adminUsers,
            'subjectTypes' => $subjectTypes,
            'eventTypes' => $eventTypes,
        ];

        if ($request->isGaleNavigate('activity-log')) {
            return gale()->fragment('admin.activity-log.index', 'activity-log-content', $data);
        }

        return gale()->view('admin.activity-log.index', $data, web: true);
    }

    /**
     * Get a human-readable short name from a fully-qualified class name.
     * Used in the subject type filter dropdown.
     */
    public static function getShortModelName(string $fullyQualifiedName): string
    {
        $parts = explode('\\', $fullyQualifiedName);

        return end($parts);
    }

    /**
     * Get distinct subject types from the activity log for the filter dropdown.
     *
     * @return array<string, string>
     */
    private function getAvailableSubjectTypes(): array
    {
        return Activity::whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->sort()
            ->mapWithKeys(fn ($type) => [$type => self::getShortModelName($type)])
            ->all();
    }

    /**
     * Get distinct event types from the activity log for the filter dropdown.
     *
     * @return list<string>
     */
    private function getAvailableEventTypes(): array
    {
        return Activity::whereNotNull('event')
            ->distinct()
            ->pluck('event')
            ->sort()
            ->values()
            ->all();
    }
}
