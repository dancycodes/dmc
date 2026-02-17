<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ComplaintListRequest;
use App\Models\Complaint;

class ComplaintController extends Controller
{
    /**
     * Display the complaint escalation queue.
     *
     * F-060: Complaint Escalation Queue
     * BR-160: Default sort: oldest unresolved complaints first (priority queue)
     * BR-163: Only shows complaints that have reached admin level
     * BR-164: Resolved and dismissed complaints remain visible but sorted below unresolved
     */
    public function index(ComplaintListRequest $request): mixed
    {
        $search = $request->input('search', '');
        $category = $request->input('category', '');
        $status = $request->input('status', '');
        $sortBy = $request->input('sort', '');
        $sortDir = $request->input('direction', 'asc');

        // Validate sort column
        $allowedSorts = ['id', 'submitted_at', 'escalated_at', 'category', 'status'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = '';
        }
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'asc';

        // BR-163: Only escalated complaints
        $query = Complaint::query()
            ->with(['client', 'cook', 'tenant'])
            ->escalated()
            ->search($search)
            ->ofCategory($category)
            ->ofStatus($status);

        // BR-160: Default sort is priority (oldest unresolved first)
        // If user chooses a specific sort, use that instead
        if ($sortBy !== '') {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->prioritySort();
        }

        $complaints = $query->paginate(20)->withQueryString();

        // Summary counts for dashboard cards
        $totalEscalated = Complaint::escalated()->count();
        $pendingCount = Complaint::escalated()->where('status', 'pending_resolution')->count();
        $resolvedThisWeek = Complaint::escalated()
            ->where('status', 'resolved')
            ->where('resolved_at', '>=', now()->startOfWeek())
            ->count();

        $data = [
            'complaints' => $complaints,
            'search' => $search,
            'category' => $category,
            'status' => $status,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'totalEscalated' => $totalEscalated,
            'pendingCount' => $pendingCount,
            'resolvedThisWeek' => $resolvedThisWeek,
        ];

        // Handle Gale navigate requests (search/filter/sort triggers)
        if ($request->isGaleNavigate('complaint-list')) {
            return gale()->fragment('admin.complaints.index', 'complaint-list-content', $data);
        }

        return gale()->view('admin.complaints.index', $data, web: true);
    }

    /**
     * Display a single complaint for resolution.
     *
     * F-061: Admin Complaint Resolution (stub — full implementation in F-061)
     */
    public function show(Complaint $complaint): mixed
    {
        $complaint->load(['client', 'cook', 'tenant']);

        $data = [
            'complaint' => $complaint,
        ];

        // Stub view — F-061 will implement the full resolution page
        return gale()->view('admin.complaints.show', $data, web: true);
    }
}
