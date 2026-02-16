<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * List all platform users with search, filter, sort, and pagination.
     *
     * F-050: User Management List & Search
     * BR-089: User list shows ALL platform users regardless of tenant
     * BR-090: Pagination defaults to 20 items per page
     * BR-091: Search covers: name, email, phone number
     * BR-092: Role filter shows all system roles
     * BR-093: Status filter options: All, Active, Inactive
     * BR-094: Default sort: registration date descending (newest first)
     * BR-095: Last login shows relative time
     * BR-096: Users with multiple roles show all roles as separate badges
     */
    public function index(Request $request): mixed
    {
        $search = $request->input('search', '');
        $role = $request->input('role', '');
        $status = $request->input('status', '');
        $sortBy = $request->input('sort', 'created_at');
        $sortDir = $request->input('direction', 'desc');

        // Validate sort column to prevent SQL injection
        $allowedSorts = ['name', 'email', 'phone', 'created_at', 'last_login_at', 'status'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        // Map friendly sort names to actual columns
        $sortColumn = match ($sortBy) {
            'status' => 'is_active',
            default => $sortBy,
        };

        $query = User::query()->with('roles');

        // BR-091: Search covers name, email, phone
        if ($search !== '') {
            $searchTerm = '%'.$search.'%';

            // Normalize phone search: strip +237 / 237 prefix
            $phoneSearch = preg_replace('/[\s\-()]/', '', $search);
            if (str_starts_with($phoneSearch, '+237')) {
                $phoneSearch = substr($phoneSearch, 4);
            } elseif (str_starts_with($phoneSearch, '237') && strlen($phoneSearch) >= 12) {
                $phoneSearch = substr($phoneSearch, 3);
            }
            $phoneSearchTerm = '%'.$phoneSearch.'%';

            $query->where(function ($q) use ($searchTerm, $phoneSearchTerm) {
                $q->where('name', 'ilike', $searchTerm)
                    ->orWhere('email', 'ilike', $searchTerm)
                    ->orWhere('phone', 'ilike', $phoneSearchTerm);
            });
        }

        // BR-092: Filter by role
        if ($role !== '') {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        // BR-093: Filter by status
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // BR-094: Sort
        $query->orderBy($sortColumn, $sortDir);

        // BR-090: 20 items per page
        $users = $query->paginate(20)->withQueryString();

        // Summary counts
        $totalCount = User::count();
        $activeCount = User::where('is_active', true)->count();
        $inactiveCount = User::where('is_active', false)->count();
        $newThisMonth = User::where('created_at', '>=', now()->startOfMonth())->count();

        // BR-092: All available roles for filter dropdown
        $allRoles = Role::orderBy('name')->pluck('name');

        $data = [
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'status' => $status,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
            'newThisMonth' => $newThisMonth,
            'allRoles' => $allRoles,
        ];

        // Handle Gale navigate requests (search/filter/sort triggers)
        if ($request->isGaleNavigate('user-list')) {
            return gale()->fragment('admin.users.index', 'user-list-content', $data);
        }

        return gale()->view('admin.users.index', $data, web: true);
    }
}
