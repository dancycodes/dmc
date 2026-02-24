<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CookAssignmentService;
use Illuminate\Http\Request;

class CookAssignmentController extends Controller
{
    /**
     * Show the cook assignment page for a tenant.
     *
     * F-049: Cook Account Assignment to Tenant
     */
    public function show(Request $request, Tenant $tenant): mixed
    {
        if (! $request->user()?->can('can-edit-tenant')) {
            abort(403);
        }

        $tenant->load('cook');

        return gale()->view('admin.tenants.assign-cook', [
            'tenant' => $tenant,
        ], web: true);
    }

    /**
     * Search users by name or email for cook assignment.
     *
     * BR-085: The user must already exist in the system.
     * Returns JSON for live search via $action.
     */
    public function search(Request $request, Tenant $tenant, CookAssignmentService $service): mixed
    {
        if (! $request->user()?->can('can-edit-tenant')) {
            abort(403);
        }

        $term = $request->isGale()
            ? $request->state('searchTerm', '')
            : $request->input('search', '');

        $users = $service->searchUsers($term);

        // Enrich users with their cook tenants and roles
        $results = $users->map(function (User $user) use ($service, $tenant) {
            $cookTenants = $service->getUserCookTenants($user);
            $roles = $user->getRoleNames()->toArray();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'profile_photo_path' => $user->profile_photo_path,
                'initial' => mb_strtoupper(mb_substr($user->name, 0, 1)),
                'roles' => $roles,
                'cook_tenants' => $cookTenants->map(fn (Tenant $t) => [
                    'name' => $t->name,
                    'slug' => $t->slug,
                ])->values()->toArray(),
                'is_current_cook' => $tenant->cook_id === $user->id,
            ];
        });

        return gale()->state('searchResults', $results->values()->toArray())
            ->state('searchLoading', false);
    }

    /**
     * Assign or reassign a cook to the tenant.
     *
     * BR-082: Each tenant has exactly one cook at a time.
     * BR-084: Reassignment revokes the cook role from the previous user for this tenant only.
     * BR-086: Reassignment requires explicit confirmation via dialog (handled client-side).
     * BR-087: Assignment is logged in the activity log.
     * BR-088: The assigned user gains all cook-level permissions for the tenant.
     */
    public function assign(Request $request, Tenant $tenant, CookAssignmentService $service): mixed
    {
        if (! $request->user()?->can('can-edit-tenant')) {
            abort(403);
        }

        $userId = $request->isGale()
            ? $request->state('selectedUserId')
            : $request->input('user_id');

        if (! $userId) {
            if ($request->isGale()) {
                return gale()->messages(['_error' => __('Please select a user to assign as cook.')]);
            }
            abort(422);
        }

        $user = User::findOrFail($userId);

        // Load the current cook for comparison
        $tenant->load('cook');

        // Perform the assignment
        $result = $service->assignCook($tenant, $user);

        // BR-087: Log the assignment in activity log
        $previousCook = $result['previous_cook'];
        $logProperties = [
            'new_cook_id' => $user->id,
            'new_cook_name' => $user->name,
            'new_cook_email' => $user->email,
            'ip' => $request->ip(),
        ];

        if ($previousCook && $previousCook->id !== $user->id) {
            $logProperties['previous_cook_id'] = $previousCook->id;
            $logProperties['previous_cook_name'] = $previousCook->name;
            $logProperties['previous_cook_email'] = $previousCook->email;

            activity('tenants')
                ->performedOn($tenant)
                ->causedBy($request->user())
                ->withProperties($logProperties)
                ->log('cook_reassigned');
        } else {
            activity('tenants')
                ->performedOn($tenant)
                ->causedBy($request->user())
                ->withProperties($logProperties)
                ->log('cook_assigned');
        }

        // Flash success toast
        $toastMessage = $previousCook && $previousCook->id !== $user->id
            ? __('Cook reassigned from :old to :new for tenant ":tenant".', [
                'old' => $previousCook->name,
                'new' => $user->name,
                'tenant' => $tenant->name,
            ])
            : __('Cook ":name" assigned to tenant ":tenant".', [
                'name' => $user->name,
                'tenant' => $tenant->name,
            ]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => $toastMessage,
        ]);

        if ($request->isGale()) {
            return gale()->redirect(url('/vault-entry/tenants/'.$tenant->slug));
        }

        return redirect('/vault-entry/tenants/'.$tenant->slug);
    }
}
